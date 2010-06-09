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
	
	private int connectionId;
	private Master master;	
	private int lastSentId = 0;
	
	private NickList nicklist = new NickList(this);
	private ExtNickInfo extnickinfo = new ExtNickInfo(this);
	private Logger logger;
	
	private IRCConnection irc;
	private boolean active = true;

	private final String lockFileName = "handle.conn-%d.lock";
	private String lockDir;
	private FileLock lock;
	private FileChannel lockChannel;
	private Connection conn;

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
		if (!extnickinfo.hasNick(nick.getNick())) {
			//System.out.println("requestUserhost [N] " + nick.getNick());
			irc.doWho(nick.getNick());
			extnickinfo.update(nick.getNick());
		}
	}

	public void removeUserhost(String nick) {
		extnickinfo.remove(nick);
	}
	
	public void renameUserhost(String oldNick, String newNick) {
		extnickinfo.renameNick(oldNick, newNick);
	}
	
	public synchronized Connection getConnection() {
		return conn;
	}
	
	public void initConnection() throws SQLException {
		logger.info("[" + connectionId + "] Establishing database connection...");		
		this.conn = master.createConnection();
	}
	
	public void setActive(boolean active) throws SQLException {
		this.active = active;
	}

	public void setStatus(int status) throws SQLException {
		PreparedStatement ps = getConnection().prepareStatement("UPDATE ttirc_connections SET " +
		"status = ?, userhosts = '' WHERE id = ?");

		ps.setInt(1, status);
		ps.setInt(2, connectionId);
		ps.execute();
		ps.close();

	}
	
	public void setEnabled(boolean enabled) throws SQLException {
		PreparedStatement ps = getConnection().prepareStatement("UPDATE ttirc_connections SET " +
				"enabled = ? WHERE id = ?");

		ps.setBoolean(1, enabled);
		ps.setInt(2, connectionId);
		
		ps.execute();
		ps.close();
	}

	
	public String[] getRandomServer() throws SQLException {
		PreparedStatement ps = getConnection().prepareStatement("SELECT server,port FROM ttirc_servers " +
				"WHERE connection_id = ? ORDER BY " + master.getRandomFunction());
				
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

			setStatus(Constants.CS_CONNECTING);
			
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
			irc.setColors(true);
			
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
					"(incoming, connection_id, channel, sender, message, message_type, ts) " +
					" VALUES (?, ?, ?, ?, ?, ?, NOW())");
			
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
		try {
			setActive(false);
		} catch (SQLException e) {
			e.printStackTrace();
		}
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
		
		if (command.length != 2) {
			logger.info("[" + connectionId + "] Incorrect command syntax: " + message);
			return;
		}
		
		//logger.info("COMMAND " + command[0] + "/" + command[1] + " on " + chan);

		if (command[0].equals("away")) {
			irc.doAway(command[1]);
			extnickinfo.setAwayReason(irc.getNick(), command[1]);
			return;
		}
		
		if (command[0].equals("quote")) {
			irc.send(command[1]);
			return;
		}

		if (command[0].equals("discon") || command[0].equals("disconnect")) {
			try {
				irc.doQuit(getQuitMessage());
				setEnabled(false);
			} catch (SQLException e) {
				e.printStackTrace();
			}
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
			irc.doPrivmsg(command[1], '\001' + "PING " + System.currentTimeMillis());
			return;
		}
		
		if (command[0].equals("msg")) {
			String[] msgparts = command[1].split(" ", 2);
			irc.doPrivmsg(msgparts[0], msgparts[1]);
			return;
		}

		if (command[0].equals("ctcp")) {
			String[] msgparts = command[1].split(" ", 2);
			irc.doPrivmsg(msgparts[0], '\001' + msgparts[1] + '\001');
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
					"ts > "+master.getIntervalYears(1)+" AND " +
					"connection_id = ? AND " +
					"id > ? ORDER BY id");
			
			ps.setInt(1, connectionId);
			ps.setInt(2, lastSentId);		
			ps.execute();
			
			ResultSet rs = ps.getResultSet();
			
			while (rs.next()) {
				int messageType = rs.getInt("message_type");
				
				tmpLastSentId = rs.getInt("id");
				
				String channel = rs.getString("channel");
				String message = rs.getString("message");
				String[] lines;
				
				switch (messageType) {
				case Constants.MSGT_NOTICE:
					lines = splitByLength(message, 250);
					
					for (String line : lines)					
						irc.doNotice(channel, line);
					
					break;					
				case Constants.MSGT_PRIVMSG:			
				case Constants.MSGT_PRIVATE_PRIVMSG:
					lines = splitByLength(message, 250);
					
					for (String line : lines)					
						irc.doPrivmsg(channel, line);
					
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
	
	private void syncLastSentId(int lastSentId) {
		this.lastSentId = lastSentId;
		
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
            "(heartbeat > "+master.getIntervalMinutes(10)+" OR permanent = true) AND " +
            "ttirc_connections.id = ?");
		
		ps.setInt(1, connectionId);
		ps.execute();
		
		ResultSet rs = ps.getResultSet();
		
		if (rs.next()) {
			boolean enabled = rs.getBoolean("enabled");			
			setActive(enabled);			
		} else {
			logger.info("[" + connectionId + "] Disconnecting due to user inactivity.");
			setActive(false);
		}
		
		ps.close();
	}
	
	public void run() {
		try {
			initConnection();			

			if (!lock() || !connect()) {
				active = false;
			}
		
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
			
			logger.warning("[" + connectionId + "] Connection loop exception: " + e.toString());
		}

		cleanup();
	}
	
	public void cleanup() {
		logger.info("[" + connectionId + "] Connection loop terminating.");
		
		try {
			irc.doQuit(getQuitMessage());
		} catch (Exception e) {
			e.printStackTrace();
		}
		
		try {
			sleep(1000);
		} catch (InterruptedException ie) {
			ie.printStackTrace();
		}
		
		try {
			irc.close();
		} catch (Exception e) {
			e.printStackTrace();
		}

		try {
			lock.release();
			lockChannel.close();
			logger.info("[" + connectionId + "] Lock released successfully.");
		} catch (IOException e) {
			logger.warning("[" + connectionId + "] Error while releasing connection lock: " + e.toString());
			e.printStackTrace();
		}
		
		try {
			logger.info("[" + connectionId + "] Closing database connection...");
			conn.close();
		} catch (SQLException e) {
			e.printStackTrace();
		}
	}
	
	public void syncTopic(String channel, String owner, String topic, long topicSet) throws SQLException {
		
		PreparedStatement ps = getConnection().prepareStatement("UPDATE ttirc_channels SET " +
				"topic = ?, topic_owner = ?, topic_set = ? WHERE channel = ? AND connection_id = ?");
	
		ps.setString(1, topic);
		ps.setString(2, owner);
		ps.setTimestamp(3, new java.sql.Timestamp(topicSet * 1000));		
		ps.setString(4, channel);
		ps.setInt(5, connectionId);
		
		ps.execute();
		ps.close();
		
	}
	
	public void syncTopic(String channel, String topic) throws SQLException {
	
		logger.info(topic);
		
		PreparedStatement ps = getConnection().prepareStatement("UPDATE ttirc_channels SET " +
			"topic = ? WHERE channel = ? AND connection_id = ?");

		ps.setString(1, topic);
		ps.setString(2, channel);
		ps.setInt(3, connectionId);
		
		ps.execute();
		ps.close();		
	}
	
	public void syncTopic(String channel, String owner, long topicSet) throws SQLException {
		
		PreparedStatement ps = getConnection().prepareStatement("UPDATE ttirc_channels SET " +
			"topic_owner = ?, topic_set = ? WHERE channel = ? AND connection_id = ?");
		
		ps.setString(1, owner);
		ps.setTimestamp(2, new java.sql.Timestamp(topicSet * 1000));
		ps.setString(3, channel);
		ps.setInt(4, connectionId);
		
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
			
			ps = getConnection().prepareStatement("INSERT INTO ttirc_channels " +
					"(channel, connection_id, chan_type, topic, nicklist, topic_set)" +
					"VALUES (?, ?, ?, '', '', NOW())");
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
			PreparedStatement ps = getConnection().prepareStatement("UPDATE ttirc_connections " +
				"SET active_nick = ? " +
				"WHERE id = ?");
		
			ps.setString(1, irc.getNick());
			ps.setInt(2, connectionId);
			ps.execute();
			ps.close();
			
		} catch (SQLException e) {
			e.printStackTrace();
		}
		
	}

	public void syncServer() throws SQLException {
		PreparedStatement ps = getConnection().prepareStatement("UPDATE ttirc_connections " +
			"SET active_server = ? " +
			"WHERE id = ?");
				
		ps.setString(1, irc.getHost() + ":" + irc.getPort());
		ps.setInt(2, connectionId);
		ps.execute();
		ps.close();
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
		
		@Override
		public void onDisconnected() {			
			try {
				handler.setActive(false);
			} catch (SQLException e) {
				e.printStackTrace();
			}
		}

		@Override
		public void onError(String msg) {
			handler.pushMessage("---", "---", "ERROR:" + msg, Constants.MSGT_EVENT);
		}

		@Override
		public void onError(int num, String msg) {
			handler.pushMessage("---", "---", "ERROR:" + num + ":" + msg, Constants.MSGT_EVENT);
			
			if (num == 433) {
				handler.irc.doNick(handler.irc.getNick() + "-");
			}
		}

		@Override
		public void onInvite(String chan, IRCUser user, String passiveNick) {
			handler.pushMessage(user.getNick(), "---", 
					"INVITE:" + chan, Constants.MSGT_EVENT);
			
		}

		@Override
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
				
				handler.nicklist.removeNick(chan, passiveNick);
			}			
		}

		@Override
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
			handler.nicklist.renameNick(user.getNick(), nick);
			handler.syncNick();
		}

		@Override
		public void onNotice(String target, IRCUser user, String msg) {
			
			// CTCP
			if (msg.indexOf('\001') == 0) {
				msg = msg.substring(1, msg.length()-1);
				
				String[] ctcpParts = msg.split(" ", 2);
				
				if (ctcpParts.length == 2)
					onCtcpReply(target, user, ctcpParts[0].toUpperCase(), ctcpParts[1]);
				else
					onCtcpReply(target, user, ctcpParts[0].toUpperCase(), "");
				
			} else {			
				if (target.equals(irc.getNick())) {
					
					// server notice
					if (user.getNick().equals(irc.getHost())) {
						handler.pushMessage(user.getNick(), "---", msg, Constants.MSGT_NOTICE);					
					} else {
						try {
							handler.checkChannel(user.getNick(), Constants.CT_PRIVATE);
							handler.pushMessage(user.getNick(), user.getNick(), msg, Constants.MSGT_NOTICE);
						} catch (SQLException e) {
							e.printStackTrace();
						}
					}
					
				} else {
					handler.pushMessage(user.getNick(), target, msg, Constants.MSGT_NOTICE);				
				}
			}
		}

		@Override
		public void onPart(String chan, IRCUser user, String msg) {
			handler.nicklist.removeNick(chan, user.getNick());
			
			if (user.getNick().equals(irc.getNick())) {
				try {					
					handler.removeChannel(chan);
					handler.nicklist.removeChannel(chan);
				} catch (SQLException e) {
					e.printStackTrace();					
				}				
			}
			
			handler.pushMessage("---", chan, "PART:" + user.getNick() + ":" + msg, Constants.MSGT_EVENT);		
		}

		@Override
		public void onPing(String arg0) {
			// TODO Auto-generated method stub			
		}

		public void onCtcpReply(String target, IRCUser user, String command, String msg) {
			//System.out.println("CTCP reply target: " + target + " CMD: [" + command + "] MSG: " + msg);
			
			if (command.equals("PING")) {
				float pingInterval = (float)(System.currentTimeMillis() - Long.parseLong(msg)) / 1000;
				
				handler.pushMessage(user.getNick(), target, String.format("PING_REPLY:%.2f", pingInterval), Constants.MSGT_EVENT);
				return;
			}
			
			if (target.equals(irc.getNick())) {
				
				try {
					handler.checkChannel(user.getNick(), Constants.CT_PRIVATE);
					handler.pushMessage(user.getNick(), user.getNick(), "CTCP_REPLY:" + command + ":" + msg, Constants.MSGT_EVENT);
				} catch (SQLException e) {
					e.printStackTrace();
				}
				
			} else {
				handler.pushMessage(user.getNick(), target, "CTCP_REPLY:" + command + " " + msg, Constants.MSGT_EVENT);				
			}

		}
		
		public void onCtcp(String target, IRCUser user, String command, String msg) {
			//System.out.println("CTCP target: " + target + " CMD: [" + command + "] MSG: " + msg);
						
			if (command.equals("ACTION")) {
				if (target.equals(handler.irc.getNick())) {
					try {
						handler.checkChannel(user.getNick(), Constants.CT_PRIVATE);
						handler.pushMessage(user.getNick(), user.getNick(), msg, Constants.MSGT_ACTION);					
					} catch (SQLException e) {
						e.printStackTrace();
					}
				} else {
					handler.pushMessage(user.getNick(), target, msg, Constants.MSGT_ACTION);				
				}
				return;
			}
			
			if (command.equals("PING")) {
				irc.doNotice(user.getNick(), '\001' + command + ' ' + msg + '\001');
			}
			
			if (command.equals("VERSION")) {
				String version = handler.master.getVersion();
				String osName= System.getProperty("os.name");
				String osArch = System.getProperty("os.arch");
				
				String versionReply = "Tiny Tiny IRC/" + version + " " + osName + " " + osArch;
				
				irc.doNotice(user.getNick(), '\001' + command + ' ' + versionReply + '\001');				
			}
			
			if (target.equals(handler.irc.getNick())) {
				handler.pushMessage(user.getNick(), "---", "CTCP:" + command + ":" + msg, Constants.MSGT_EVENT);
			} else {
				handler.pushMessage(user.getNick(), target, "CTCP:" + command + ":" + msg, Constants.MSGT_EVENT);
			}
		}
		
		@Override
		public void onPrivmsg(String target, IRCUser user, String msg) {
			
			// CTCP
			if (msg.indexOf('\001') == 0) {
				msg = msg.substring(1, msg.length()-1);
				
				String[] ctcpParts = msg.split(" ", 2);
				
				if (ctcpParts.length == 2)
					onCtcp(target, user, ctcpParts[0].toUpperCase(), ctcpParts[1]);
				else
					onCtcp(target, user, ctcpParts[0].toUpperCase(), "");
				
			} else {			
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
		}

		@Override
		public void onQuit(IRCUser user, String message) {
			Vector<String> chans = handler.nicklist.isOn(user.getNick());
			
			for (String chan : chans) {
				handler.pushMessage(user.getNick(), chan, "QUIT:" + message, Constants.MSGT_EVENT);
			}
			
			handler.nicklist.removeNick(user.getNick());
			handler.extnickinfo.remove(user.getNick());
		}

		// TODO: better implementation
		public String parseCommand(String rawCmd) {
			rawCmd = rawCmd.substring(1);
			rawCmd = rawCmd.replace(' ', ':');
			return rawCmd;			
		}
		
		@Override
		public void onRegistered() {
			
			handler.logger.info("[" + connectionId + "] Connected to IRC.");
			
			try {
				handler.setStatus(Constants.CS_CONNECTED);				
				handler.syncNick();
				handler.syncServer();
			} catch (SQLException e) {
				e.printStackTrace();
			}

			handler.pushMessage("---", "---", "CONNECT", Constants.MSGT_EVENT);

			/* Autojoin channels */
			for (String chan : this.autojoin) {
				handler.irc.doJoin(chan);				
			}
			
			try {
				Vector<String> activeChans = handler.getChannels();
			
				for (String chan : activeChans)
					handler.irc.doJoin(chan);
				
				String[] connectCmd = handler.getConnectCmd();
				
				for (String cmd : connectCmd)
					if (cmd.length() > 0)
							handleCommand("", parseCommand(cmd));

			} catch (SQLException e) {
				e.printStackTrace();
			}
		
		}

		@Override
		public void onReply(int num, String value, String msg) {
			if (num == RPL_TOPIC) {
				String[] params = value.split(" ");
								
				try {
					handler.syncTopic(params[1], msg);					
				} catch (SQLException e) {
					e.printStackTrace();
				}
				
				return;
			}	
			
			if (num == RPL_TOPICINFO) {
				String[] params = value.split(" ");
				
				try {
					handler.syncTopic(params[1], params[2], Integer.parseInt(msg));
				} catch (Exception e) {
					e.printStackTrace();
				}
				
				return;
			}
			
			if (num == RPL_UNAWAY) {
				String nick = value;
				handler.extnickinfo.setAway(nick, false);
				
				if (nick.equals(irc.getNick())) {
					handler.pushMessage("---", "---", msg, Constants.MSGT_SYSTEM);
				}
				
				return;
			}

			if (num == RPL_NOWAWAY) {
				String nick = value;
				handler.extnickinfo.setAway(nick, true);
				
				if (nick.equals(irc.getNick())) {
					handler.pushMessage("---", "---", msg, Constants.MSGT_SYSTEM);
				}
				
				return;
			}

			if (num == 301) { /* AWAYREASON */
				//System.out.println("[AWAYREASON] " + value + "/" + msg);
				String[] nicks = value.split(" ");
				if (nicks.length == 2) {
					handler.extnickinfo.setAwayReason(nicks[1], msg);
				}
				
				return;
			}

			if (num == RPL_NAMREPLY) {
				String[] params = value.split(" ");
				String[] nicks = msg.split(" ");
				
				for (String nick : nicks) {
					nicklist.addNick(params[2], nick);
				}
				
				return;
			}
			
			if (num == RPL_ENDOFNAMES) {
				String[] params = value.split(" ");
				try {					
					handler.checkChannel(params[1], Constants.CT_CHANNEL);					
				} catch (SQLException e) {
					e.printStackTrace();
				}
				return;
			}
			
			if (num == RPL_WHOREPLY) {
				String[] params = value.split(" ");

				String realName = msg.substring(2);
				String nick = params[5];
				String ident = params[2];
				String host = params[3];
				String server = params[4];
				
				handler.extnickinfo.update(nick, ident, host, server, realName);
				
				return;
			}
			
			if (num == RPL_ENDOFWHO) {
				return;
			}
			
			handler.pushMessage(irc.getHost(), "---", num + " " + value + " " + msg, Constants.MSGT_SYSTEM);
		}

		@Override
		public void onTopic(String chan, IRCUser user, String topic) {
			try {
				int timestamp = (int) System.currentTimeMillis() / 1000; 
				
				handler.syncTopic(chan, user.getNick(), topic, timestamp);
				handler.pushMessage(user.getNick(), chan, "TOPIC:" + topic, Constants.MSGT_EVENT);
			} catch (SQLException e) {
				e.printStackTrace();
			}
		}

		@Override
		public void unknown(String prefix, String command, String middle, String trailing) {
			handler.pushMessage("---", "---", prefix + " " + command + " " + middle + " " + trailing, 
				Constants.MSGT_SYSTEM);			
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

	public String[] getConnectCmd() throws SQLException {
		PreparedStatement ps = getConnection().prepareStatement("SELECT connect_cmd FROM ttirc_connections " +
				"WHERE id = ?");

		ps.setInt(1, connectionId);
		ps.execute();
		
		ResultSet rs = ps.getResultSet();

		String[] rv = null;
		
		if (rs.next()) {
			rv = rs.getString("connect_cmd").split(";");			
		}
		
		ps.close();
		
		return rv;
	}

	public String[] splitByLength(String str, int length) {
		int arrSize = (int) Math.ceil(str.length() / (double) length);		
				
		String[] lines = new String[arrSize];
		
		if (str.length() > length) {
			int i = 0;
			while (str.length() > length) {
				lines[i] = str.substring(0, length);
				str = str.substring(length);				
				++i;
			}
			
			if (str.length() > 0) lines[i] = str;
		} else {
			lines[0] = str;
		}
		
		return lines;
	}

	public int getConnectionId() {
		return connectionId;
	}

}
