package org.fox.ttirc;

import org.schwering.irc.lib.IRCConnection;
import org.schwering.irc.lib.IRCEventListener;
import org.schwering.irc.lib.IRCModeParser;
import org.schwering.irc.lib.IRCUser;

//import java.security.cert.X509Certificate;
//import org.schwering.irc.lib.ssl.SSLIRCConnection;
//import org.schwering.irc.lib.ssl.SSLTrustManager;

import java.io.File;
import java.io.IOException;
import java.io.RandomAccessFile;
import java.nio.channels.*;
import java.sql.*;
import java.util.*;
import java.util.logging.*;

public class NativeConnectionHandler extends ConnectionHandler {
	
	protected int connectionId;
	protected Master master;
	
	protected int lastSentId = 0;
	
	protected NickList nicklist = new NickList(this);
	protected ExtNickInfo extnickinfo = new ExtNickInfo(this);
	protected Logger logger;
	
	private IRCConnection irc;
	private boolean active = true;

	private final String lockFileName = "handle.conn-%d.lock";
	private String lockDir;
	private FileLock lock;
	private FileChannel lockChannel;

	public NativeConnectionHandler(int connectionId, Master master) {
		this.connectionId = connectionId;
		this.master = master;
		this.logger = master.getLogger();
		this.lockDir = master.getLockDir();
	}
	
	public IRCConnection getIRConnection() {
		return irc;
	}

	public void requestUserhost(NickList.Nick nick) {
		irc.doWho(nick.getNick());
	}
	
	public Connection getConnection() {
		return master.getConnection();
	}
	
	public void setConnected(boolean connected) throws SQLException {
		PreparedStatement ps = getConnection().prepareStatement("UPDATE ttirc_connections SET " +
				"status = ?, active_server = ? WHERE id = ?");
		
		if (connected)
			ps.setInt(1, Constants.CS_CONNECTED);
		else {
			ps.setInt(1, Constants.CS_DISCONNECTED);
			active = false;
		}
		
		ps.setString(2, irc.getHost() + ":" + irc.getPort());
		ps.setInt(3, connectionId);
		
		ps.execute();
		ps.close();
	}

	public String[] getRandomServer() throws SQLException {
		PreparedStatement ps = getConnection().prepareStatement("SELECT server,port FROM ttirc_servers " +
				"WHERE connection_id = ? ORDER BY RANDOM()");
				
		ps.setInt(1, connectionId);
		ps.execute();
		
		ResultSet rs = ps.getResultSet();
		
		String[] rv = new String[2];
		
		if (rs.next()) {
			rv[0] = rs.getString(1);
			rv[1] = rs.getString(2);
		}
		
		return rv;
	}
	
	public boolean connect() throws SQLException {
		PreparedStatement ps = getConnection().prepareStatement("SELECT *, " +
			"ttirc_connections.nick AS local_nick, ttirc_users.nick AS normal_nick " +
			"FROM ttirc_connections, ttirc_users " +
			"WHERE ttirc_connections.id = ? AND owner_uid = ttirc_users.id");
		
		ps.setInt(1, connectionId);
		ps.execute();
		
		ResultSet rs = ps.getResultSet();
		
		String nick;
		String localNick;
		String encoding;
		String email;
		String realname;
		String[] autojoin;
		
		if (rs.next()) {
			nick = rs.getString("normal_nick");
			localNick = rs.getString("local_nick");
			encoding = rs.getString("encoding");
			email = rs.getString("email");
			realname = rs.getString("realname");
			autojoin = rs.getString("autojoin").split(",");
			lastSentId = rs.getInt("last_sent_id");
		
			// wtf? no nick?!
			if (nick.length() == 0) return false;
			
			if (localNick.length() > 0) nick = localNick;
					
			String[] server = getRandomServer();
			
			if (server.length != 2) {
				pushMessage("---", "---", "NOSERVER", Constants.MSGT_EVENT);
				
				PreparedStatement pst = getConnection().prepareStatement("UPDATE ttirc_connections SET "+
						"auto_connect = false, enabled = false WHERE id = ?");
				
				pst.setInt(1, connectionId);
				pst.execute();	
				pst.close();
				
				return false;
			}

			ps.close();
			
			String host = server[0];
			int port = Integer.valueOf(server[1]);

			pushMessage("---", "---", "CONNECTING:" + server[0] + ":" + server[1], Constants.MSGT_EVENT);
			
			ps = getConnection().prepareStatement("UPDATE ttirc_connections SET " +
					"status = ?, userhosts = '' WHERE id = ?");

			ps.setInt(1, Constants.CS_CONNECTING);
			ps.setInt(2, connectionId);
			ps.execute();
			ps.close();
			
			ps = getConnection().prepareStatement("UPDATE ttirc_channels SET " +
					"nicklist = '' WHERE connection_id = ?");
			
			ps.setInt(1, connectionId);
			ps.execute();
			ps.close();
			
			
			irc = new IRCConnection(host, port, port, "", nick,	email, realname);

			irc.addIRCEventListener(new Listener(connectionId, this, autojoin));
			irc.setEncoding(encoding);
			irc.setPong(true);
			irc.setDaemon(false);
			irc.setColors(false);
			
			try {
				irc.connect();
				
				return true;
			} catch (IOException e) {
				pushMessage("---", "---", "CONNECTION_ERROR:" + server[0] + ":" + server[1], 
						Constants.MSGT_EVENT);
				return false;
			}
		
		} else {
			return false;
		}
		
	}
	
	public void pushMessage(String sender, String channel, String message, int messageType) {
				
		try {
			PreparedStatement ps;

			ps = getConnection().prepareStatement("INSERT INTO ttirc_messages " +
					"(incoming, connection_id, channel, sender, message, message_type) " +
					" VALUES (?, ?, ?, ?, ?, ?)");
			
			ps.setBoolean(1, true);
			ps.setInt(2, this.connectionId);
			ps.setString(3, channel);
			ps.setString(4, sender);
			ps.setString(5, message);
			ps.setInt(6, messageType);
			
			ps.execute();
			ps.close();

		} catch (SQLException e) {
			e.printStackTrace();
		}
	}
	
	public void kill() {
		irc.doQuit(getQuitMessage());
	}
	
	private boolean lock() {
		File f = new File(lockDir + File.separator + lockFileName.replace("%d", new Integer(connectionId).toString()));
		
		try {		
			lockChannel = new RandomAccessFile(f, "rw").getChannel();			
			lock = lockChannel.tryLock();
			
			if (lock != null) {			
				logger.info("[" + connectionId + "] Lock acquired successfully (" + f.getName() + ").");
			} else {
				logger.severe("[" + connectionId + "] Couldn't acquire the lock: maybe the connection is already active?");
				return false;
			}
			
		} catch (Exception e) {
			logger.severe("[" + connectionId + "] Couldn't make the lock: " + e.toString());
			e.printStackTrace();
			return false;
		}
		
		return true;
	}

	
	private String getQuitMessage() {
		try {
			PreparedStatement ps = getConnection().prepareStatement("SELECT quit_message FROM " +
					"ttirc_users, ttirc_connections WHERE " +
					"ttirc_users.id = owner_uid AND ttirc_connections.id = ?");
		
			ps.setInt(1, connectionId);
			ps.execute();
			
			ResultSet rs = ps.getResultSet();
			
			if (rs.next()) {
				return rs.getString("quit_message");
			}
			
		} catch (SQLException e) {
			e.printStackTrace();
		}
		
		return "Tiny Tiny IRC";
	}

	public void handleCommand(String chan, String message) {
		String[] command = message.split(":", 2);
		
		logger.info("COMMAND " + command[0] + "/" + command[1] + " on " + chan);

		if (command[0].equals("away")) {
			irc.doAway(command[1]);
			return;
		}
		
		if (command[0].equals("quote")) {
			irc.send(command[1]);
			return;
		}

		if (command[0].equals("oper")) {
			irc.send("OPER " + command[1]);
			return;
		}

		if (command[0].equals("samode")) {
			irc.send("SAMODE " + command[1]);
			return;
		}

		if (command[0].equals("ping")) {
			// TODO add ping()
			//return;
		}
		
		if (command[0].equals("msg")) {
			String[] msgparts = command[1].split(" ", 2);
			irc.doPrivmsg(msgparts[0], msgparts[1]);
			return;
		}
		
		if (command[0].equals("notice")) {
			String[] msgparts = command[1].split(" ", 2);
			irc.doNotice(msgparts[0], msgparts[1]);
			return;		
		}		
		
		if (command[0].equals("nick")) {
			irc.doNick(command[1]);
			return;
		}
		
		if (command[0].equals("whois")) {
			irc.doWhois(command[1]);
			return;
		}
		
		if (command[0].equals("join")) {
			irc.doJoin(command[1]);
			return;
		}

		if (command[0].equals("topic")) {			
			irc.doTopic(chan, command[1]);
			return;
		}

		if (command[0].equals("part")) {
			irc.doPart(command[1]);
			return;
		}
		
		if (command[0].equals("action")) {
			irc.doPrivmsg(chan, "\001ACTION " + command[1] + "\001");
			return;
		}

		if (command[0].equals("mode")) {
			String[] msgparts = command[1].split(" ", 2);
			
			irc.doMode(msgparts[0], msgparts[1]);
			return;
		}

		if (command[0].equals("umode")) {
			irc.doMode(irc.getNick(), command[1]);
			return;		
		}
		
		if (command[0].equals("op")) {
			irc.doMode(chan, "+o " + command[1]);
			return;
		}

		if (command[0].equals("deop")) {
			irc.doMode(chan, "-o " + command[1]);
			return;
		}

		if (command[0].equals("voice")) {
			irc.doMode(chan, "+v " + command[1]);
			return;
		}

		if (command[0].equals("devoice")) {
			irc.doMode(chan, "-v " + command[1]);
			return;
		}

		pushMessage("---", "---", "UNKNOWN_CMD:" + command[0], Constants.MSGT_EVENT);
	}
	
	public void checkMessages() {
		
		int tmpLastSentId = lastSentId;

		try {
		
			PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM ttirc_messages " +
					"WHERE incoming = false AND " +
					"ts > NOW() - INTERVAL '1 year' AND " +
					"connection_id = ? AND " +
					"id > ? ORDER BY id");
			
			ps.setInt(1, connectionId);
			ps.setInt(2, this.lastSentId);		
			ps.execute();
			
			ResultSet rs = ps.getResultSet();
			
			while (rs.next()) {
				int messageType = rs.getInt("message_type");
				
				tmpLastSentId = rs.getInt("id");
				
				String channel = rs.getString("channel");
				String message = rs.getString("message");
				
				switch (messageType) {
				case Constants.MSGT_PRIVMSG:			
				case Constants.MSGT_PRIVATE_PRIVMSG:				
					irc.doPrivmsg(channel, message);				
					break;
				case Constants.MSGT_COMMAND:
					handleCommand(channel, message);
					break;
				default:
					logger.warning(connectionId + " received unknown MSG_TYPE: " + messageType);
				}			
			}
			
			ps.close();
		} catch (Exception e) {
			e.printStackTrace();
		}
			
		if (lastSentId != tmpLastSentId) {
			syncLastSentId(tmpLastSentId);
		}		
	}
	
	private void syncLastSentId(int tmpLastSentId) {
		this.lastSentId = tmpLastSentId;
		
		try {
			PreparedStatement ps;

			ps = getConnection().prepareStatement("UPDATE ttirc_connections SET last_sent_id = ? " +
					"WHERE id = ?");
			
			ps.setInt(1, lastSentId);
			ps.setInt(2, connectionId);
			ps.execute();
			ps.close();		

		} catch (SQLException e) {
			e.printStackTrace();
		}
	}

	public void disconnectIfDisabled() throws SQLException {
		PreparedStatement ps = getConnection().prepareStatement("SELECT enabled " +
            "FROM ttirc_connections, ttirc_users " +
            "WHERE owner_uid = ttirc_users.id AND " +
            "(heartbeat > NOW() - INTERVAL '10 minutes' OR permanent = true) AND " +
            "ttirc_connections.id = ?");
		
		ps.setInt(1, connectionId);
		ps.execute();
		
		ResultSet rs = ps.getResultSet();
		
		if (rs.next()) {
			boolean enabled = rs.getBoolean("enabled");
			
			setConnected(enabled);
			
		} else {
			setConnected(false);
		}
		
		ps.close();
	}
	
	public void run() {
		try {
			
			if (!lock()) return;			
			if (!connect()) return;
		
			while (active) {
				disconnectIfDisabled();
				
				try {
					checkMessages();
				} catch (Exception e) {
					e.printStackTrace();
				}
				sleep(1000);
			}
			

		} catch (Exception e) {
			e.printStackTrace();
			
			logger.warning("Connection loop terminated, waiting...");
			
			irc.doQuit("");
			
			try {
				sleep(2000);
			} catch (InterruptedException ie) {
				ie.printStackTrace();
			}
			
			irc.close();
		}
		
		try {
			lock.release();
			lockChannel.close();
			logger.info("[" + connectionId + "] Lock released successfully.");
		} catch (IOException e) {
			logger.warning("[" + connectionId + "] Error while releasing connection lock: " + e.toString());
			e.printStackTrace();
		}
	}
	
	public void setTopic(String channel, String nick, String topic) throws SQLException {
		
		PreparedStatement ps = getConnection().prepareStatement("UPDATE ttirc_channels SET " +
				"topic = ?, topic_owner = ?, topic_set = NOW()");
	
		ps.setString(1, topic);
		ps.setString(2, nick);
		
		ps.execute();
		ps.close();
		
	}
	
	public void checkChannel(String channel, int chanType) throws SQLException {	
		
		PreparedStatement ps = getConnection().prepareStatement("SELECT id FROM ttirc_channels WHERE " +
			"channel = ? AND connection_id = ?");
				
		ps.setString(1, channel);
		ps.setInt(2, connectionId);
		ps.execute();
		
		ResultSet rs = ps.getResultSet();
		
		if (!rs.next()) {
			ps.close();
			
			ps = getConnection().prepareStatement("INSERT INTO ttirc_channels (channel, connection_id, chan_type)" +
					"VALUES (?, ?, ?)");
			ps.setString(1, channel);
			ps.setInt(2, connectionId);
			ps.setInt(3, chanType);
			ps.execute();
			ps.close();			
		}		
	}
	
	public void removeChannel(String channel) throws SQLException {
		
		PreparedStatement ps = getConnection().prepareStatement("DELETE FROM ttirc_channels WHERE " +
				"channel = ? AND connection_id = ?");
		
		ps.setString(1, channel);
		ps.setInt(2, connectionId);
		ps.execute();
		ps.close();
	}	
	
	public Vector<String> getChannels() throws SQLException {
		PreparedStatement ps = getConnection().prepareStatement("SELECT channel FROM ttirc_channels WHERE " + 
				"chan_type = ? AND connection_id = ?");
		
		ps.setInt(1, Constants.CT_CHANNEL);
		ps.setInt(2, connectionId);		
		ps.execute();
		
		Vector<String> tmp = new Vector<String>();
		ResultSet rs = ps.getResultSet();
		
		while (rs.next()) {
			tmp.add(rs.getString(1));
		}
		
		return tmp;
	}
	
	public void syncNick() {
		try {
			PreparedStatement ps = getConnection().prepareStatement("UPDATE ttirc_connections SET active_nick = ?" +
				"WHERE id = ?");
		
			ps.setString(1, irc.getNick());
			ps.setInt(2, connectionId);
			ps.execute();
			ps.close();
			
		} catch (SQLException e) {
			e.printStackTrace();
		}
		
	}
	
	public class Listener implements IRCEventListener {

		protected NativeConnectionHandler handler;
		protected int connectionId;
		String[] autojoin;
		
		public Listener(int connectionId, NativeConnectionHandler handler, String[] autojoin) {
			this.connectionId = connectionId;
			this.handler = handler;
			this.autojoin = autojoin;
		}
		
		public void onDisconnected() {			
			try {
				handler.setConnected(false);
			} catch (SQLException e) {
				e.printStackTrace();
			}
		}

		public void onError(String msg) {
			handler.pushMessage("---", "---", "Error: " + msg, Constants.MSGT_SYSTEM);
		}

		@Override
		public void onError(int num, String msg) {
			handler.pushMessage("---", "---", "Error [" + num + "] " + msg, Constants.MSGT_SYSTEM);
			
			if (num == 433) {
				handler.irc.doNick(handler.irc.getNick() + "-");
			}
		}

		@Override
		public void onInvite(String arg0, IRCUser arg1, String arg2) {
			// TODO Auto-generated method stub			
		}

		public void onJoin(String chan, IRCUser user) {
			handler.pushMessage(user.getNick(), chan, 
					"JOIN:" + user.getNick() + ":" + user.getUsername() + "@" + user.getHost(), 
					Constants.MSGT_EVENT);
			
			if (user.getNick().equals(irc.getNick())) {
				try {
					handler.checkChannel(chan, Constants.CT_CHANNEL);
				} catch (SQLException e) {
					e.printStackTrace();
				}
			}
			
			handler.nicklist.addNick(chan, user.getNick());
		}

		@Override
		public void onKick(String chan, IRCUser user, String passiveNick, String msg) {
			
			if (passiveNick.equals(irc.getNick())) {
				
				handler.pushMessage(user.getNick(), "---", 
						"KICK:" + passiveNick + ":" + msg + ":" + chan,
						Constants.MSGT_EVENT);
				try {
					handler.removeChannel(chan);
				} catch (SQLException e) {
					e.printStackTrace();
				}
				
			} else {			
				handler.pushMessage(user.getNick(), chan, "KICK:" + passiveNick + ":" + msg,
					Constants.MSGT_EVENT);
			}			
		}

		public void onMode(String chan, IRCUser user, IRCModeParser modeParser) {
			handler.pushMessage(user.getNick(), chan, 
					"MODE:" + modeParser.getLine().trim() + ":" + chan, 
					Constants.MSGT_EVENT);
			
			for (int i = 0; i < modeParser.getCount(); i++) {
				char mode = modeParser.getModeAt(i+1);
				char op = modeParser.getOperatorAt(i+1);
				
				String nick;
				
				switch (mode) {
				case 'o':
					nick = modeParser.getArgAt(i+1);
					handler.nicklist.setOp(chan, nick, op != '-');
					break;
				case 'v':
					nick = modeParser.getArgAt(i+1);
					handler.nicklist.setVoiced(chan, nick, op != '-');
					break;
				}					
			}			
		}

		@Override
		public void onMode(IRCUser user, String passiveNick, String mode) {
			handler.pushMessage(user.getNick(), "---", 
					"MODE:" + mode + ":" + passiveNick, 
					Constants.MSGT_EVENT);						
		}

		@Override
		public void onNick(IRCUser user, String nick) {
			Vector<String> chans = handler.nicklist.isOn(user.getNick());
			
			for (String chan : chans) {
				handler.pushMessage(user.getNick(), chan, "NICK:" + nick, Constants.MSGT_EVENT);
			}
			handler.nicklist.renameNick(user.getNick(), nick);
			handler.syncNick();
		}

		@Override
		public void onNotice(String target, IRCUser user, String msg) {
			// TODO handle replies to CTCP PING here
			if (target.equals(irc.getNick())) {
				
				// server notice
				if (user.getNick().equals(irc.getHost())) {
					handler.pushMessage(user.getNick(), "---", "NOTICE:" + msg, Constants.MSGT_EVENT);					
				} else {
					try {
						handler.checkChannel(user.getNick(), Constants.CT_PRIVATE);
						handler.pushMessage(user.getNick(), user.getNick(), "NOTICE:" + msg, Constants.MSGT_EVENT);
					} catch (SQLException e) {
						e.printStackTrace();
					}
				}
				
			} else {
				handler.pushMessage(user.getNick(), target, "NOTICE:" + msg, Constants.MSGT_EVENT);				
			}			
		}

		public void onPart(String chan, IRCUser user, String msg) {
			handler.nicklist.delNick(chan, user.getNick());
			
			if (user.getNick().equals(irc.getNick())) {
				try {
					handler.removeChannel(chan);
					handler.nicklist.removeChannel(chan);
				} catch (SQLException e) {
					e.printStackTrace();					
				}
			}
			
			Vector<String> isOn = handler.nicklist.isOn(user.getNick());
			
			if (!isOn.elements().hasMoreElements()) 
				handler.extnickinfo.delete(user.getNick());
			
			handler.pushMessage("---", chan, "PART:" + user.getNick() + ":" + msg, Constants.MSGT_EVENT);		
		}

		@Override
		public void onPing(String arg0) {
			// TODO Auto-generated method stub
			
		}

		public void onPrivmsg(String target, IRCUser user, String msg) {
			try {			
				if (target.equals(handler.irc.getNick())) {
					handler.checkChannel(user.getNick(), Constants.CT_PRIVATE);
					handler.pushMessage(user.getNick(), user.getNick(), msg, Constants.MSGT_PRIVATE_PRIVMSG);
				} else {
					handler.pushMessage(user.getNick(), target, msg, Constants.MSGT_PRIVMSG);				
				}
			} catch (SQLException e) {
				e.printStackTrace();
			}
		}

		public void onQuit(IRCUser user, String message) {
			Vector<String> chans = handler.nicklist.isOn(user.getNick());
			
			for (String chan : chans) {
				handler.pushMessage(user.getNick(), chan, "QUIT:" + message, Constants.MSGT_EVENT);
			}
			
			handler.nicklist.delNick(user.getNick());
			handler.extnickinfo.delete(user.getNick());
		}

		public void onRegistered() {
			
			handler.logger.info("Connected to IRC");
			
			handler.pushMessage("---", "---", "CONNECT", Constants.MSGT_EVENT);
			
			try {
				handler.setConnected(true);
				handler.syncNick();
			} catch (SQLException e) {
				e.printStackTrace();
			}
			
			/* Autojoin channels */
			for (String chan : this.autojoin) {
				handler.irc.doJoin(chan);				
			}
			
			try {
				Vector<String> activeChans = handler.getChannels();
			
				for (String chan : activeChans) {
					handler.irc.doJoin(chan);
				}
			} catch (SQLException e) {
				e.printStackTrace();
			}
			
		}

		public void onReply(int num, String value, String msg) {
			if (num == RPL_TOPIC) {
				String[] params = value.split(" ");
								
				try {
					handler.setTopic(params[1], params[0], msg);					
				} catch (SQLException e) {
					e.printStackTrace();
				}
			}	
			
			if (num == RPL_NAMREPLY) {
				String[] params = value.split(" ");
				String[] nicks = msg.split(" ");
				
				for (String nick : nicks) {
					nicklist.addNick(params[2], nick);
				}
			}
			
			if (num == RPL_ENDOFNAMES) {
				String[] params = value.split(" ");
				try {					
					handler.checkChannel(params[1], Constants.CT_CHANNEL);					
				} catch (SQLException e) {
					e.printStackTrace();
				}
			}
			
			if (num == RPL_WHOREPLY) {
				String[] params = value.split(" ");

				String realName = msg.substring(2);
				String nick = params[5];
				String ident = params[2];
				String host = params[3];
				String server = params[4];
				
				handler.extnickinfo.update(nick, ident, host, server, realName);
			}
		}

		public void onTopic(String chan, IRCUser user, String topic) {
			try {
				handler.setTopic(chan, user.getNick(), topic);
				handler.pushMessage(user.getNick(), chan, "TOPIC:" + topic, Constants.MSGT_EVENT);
			} catch (SQLException e) {
				e.printStackTrace();
			}
		}

		@Override
		public void unknown(String arg0, String arg1, String arg2, String arg3) {
			// TODO Auto-generated method stub
			
		}		
	}

	public void syncExtInfo(String jsonValue) throws SQLException {
		PreparedStatement ps = getConnection().prepareStatement("UPDATE ttirc_connections SET userhosts = ? " +
				"WHERE id = ?");
		
		ps.setString(1, jsonValue);
		ps.setInt(2, connectionId);
		ps.execute();
		ps.close();
		
	}

}
