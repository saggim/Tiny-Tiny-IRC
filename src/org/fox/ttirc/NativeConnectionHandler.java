package org.fox.ttirc;

import org.schwering.irc.lib.IRCConnection;
import org.schwering.irc.lib.IRCEventListener;
import org.schwering.irc.lib.IRCModeParser;
import org.schwering.irc.lib.IRCUser;
import org.schwering.irc.lib.ssl.SSLIRCConnection;
import org.schwering.irc.lib.ssl.SSLTrustManager;
import java.io.IOException;
import java.security.cert.X509Certificate;
import java.sql.*;
import java.util.*;

public class NativeConnectionHandler extends ConnectionHandler {
	
	protected int connectionId;
	protected Master master;
	protected Connection conn;	
	
	protected int lastSentId = 0;
	
	protected NickList userlist = new NickList(this);
	
	private IRCConnection irc;
	private boolean active = true;
	
	public NativeConnectionHandler(int connectionId, Master master) {
		this.connectionId = connectionId;
		this.master = master;
		this.conn = master.conn;		
	}
	
	public void setConnected(boolean connected) throws SQLException {
		PreparedStatement ps = conn.prepareStatement("UPDATE ttirc_connections SET " +
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
		PreparedStatement ps = conn.prepareStatement("SELECT server,port FROM ttirc_servers " +
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
		PreparedStatement ps = conn.prepareStatement("SELECT *, " +
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
					
			System.out.println(nick + " " + email + " " + realname + " " + autojoin);
			
			String[] server = getRandomServer();
			
			if (server.length != 2) {
				pushMessage("---", "---", "NOSERVER", Constants.MSGT_EVENT);
				
				PreparedStatement pst = conn.prepareStatement("UPDATE ttirc_connections SET "+
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
			
			ps = conn.prepareStatement("UPDATE ttirc_connections SET " +
					"status = ?, userhosts = '' WHERE id = ?");

			ps.setInt(1, Constants.CS_CONNECTING);
			ps.setInt(2, connectionId);
			ps.execute();
			ps.close();
			
			ps = conn.prepareStatement("UPDATE ttirc_channels SET " +
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

			ps = conn.prepareStatement("INSERT INTO ttirc_messages " +
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
	
	private String getQuitMessage() {
		try {
			PreparedStatement ps = conn.prepareStatement("SELECT quit_message FROM ttirc_connections "+
				"WHERE id = ?");
		
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
		
		System.out.println("COMMAND " + command[0] + "/" + command[1] + " on " + chan);

		if (command[0].equals("away")) {
			irc.doAway(command[1]);
		}
		
		if (command[0].equals("quote")) {
			irc.send(command[1]);
		}
		
		if (command[0].equals("ping")) {
			// TODO add ping()
		}
		
		if (command[0].equals("msg")) {
			String[] msgparts = command[1].split(" ", 2);
			irc.doPrivmsg(msgparts[0], msgparts[1]);
		}
		
		if (command[0].equals("notice")) {
			// TODO add notice()
		}		
		
		if (command[0].equals("nick")) {
			irc.doNick(command[1]);
		}
		
		if (command[0].equals("whois")) {
			irc.doWhois(command[1]);			
		}
		
		if (command[0].equals("join")) {
			irc.doJoin(command[1]);
		}
		
		if (command[0].equals("part")) {
			irc.doPart(command[1]);
		}
		
		if (command[0].equals("action")) {
			irc.doPrivmsg(chan, "\001ACTION " + command[1] + "\001");
		}

		if (command[0].equals("mode")) {
			String[] msgparts = command[1].split(" ", 2);
			
			System.out.println(msgparts[0] + " " + msgparts[1]);
			
			irc.doMode(msgparts[0], msgparts[1]);
		}

		if (command[0].equals("umode")) {
			irc.doMode(irc.getNick(), command[1]);
		}
	}
	
	public void checkMessages() {
		
		int tmpLastSentId = lastSentId;

		try {
		
			PreparedStatement ps = conn.prepareStatement("SELECT * FROM ttirc_messages " +
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
				
				//System.out.println(messageType + ", " + message + " => " + channel);
				
				switch (messageType) {
				case Constants.MSGT_PRIVMSG:			
				case Constants.MSGT_PRIVATE_PRIVMSG:				
					irc.doPrivmsg(channel, message);				
					break;
				case Constants.MSGT_COMMAND:
					handleCommand(channel, message);
					break;
				default:
					System.err.println("Received unknown MSG_TYPE: " + messageType);
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

			ps = conn.prepareStatement("UPDATE ttirc_connections SET last_sent_id = ? " +
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
		PreparedStatement ps = conn.prepareStatement("SELECT enabled " +
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
			
			System.err.println("Connection loop terminated, waiting...");
			
			irc.doQuit("");
			
			try {
				sleep(2000);
			} catch (InterruptedException ie) {
				ie.printStackTrace();
			}
			
			irc.close();
		}
		
		System.out.println("Connection " + connectionId + " done.");
	}
	
	public void setTopic(String channel, String nick, String topic) throws SQLException {
		
		PreparedStatement ps = conn.prepareStatement("UPDATE ttirc_channels SET " +
				"topic = ?, topic_owner = ?, topic_set = NOW()");
	
		ps.setString(1, topic);
		ps.setString(2, nick);
		
		ps.execute();
		ps.close();
		
	}
	
	public void checkChannel(String channel, int chanType) throws SQLException {	
		
		PreparedStatement ps = conn.prepareStatement("SELECT id FROM ttirc_channels WHERE " +
			"channel = ? AND connection_id = ?");
				
		ps.setString(1, channel);
		ps.setInt(2, connectionId);
		ps.execute();
		
		ResultSet rs = ps.getResultSet();
		
		if (!rs.next()) {
			ps.close();
			
			ps = conn.prepareStatement("INSERT INTO ttirc_channels (channel, connection_id, chan_type)" +
					"VALUES (?, ?, ?)");
			ps.setString(1, channel);
			ps.setInt(2, connectionId);
			ps.setInt(3, chanType);
			ps.execute();
			ps.close();			
		}		
	}
	
	public void syncNick() {
		try {
			PreparedStatement ps = conn.prepareStatement("UPDATE ttirc_connections SET active_nick = ?" +
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
			System.out.println("ERROR: " + msg);
			handler.pushMessage("---", "---", "Error: " + msg, Constants.MSGT_SYSTEM);
		}

		@Override
		public void onError(int num, String msg) {
			System.out.println("ERROR: " + num + " " + msg);
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
			
			handler.userlist.addNick(chan, user.getNick());
		}

		@Override
		public void onKick(String chan, IRCUser user, String passiveNick, String msg) {
			handler.pushMessage(user.getNick(), chan, "KICK:" + passiveNick + ":" + msg,
					Constants.MSGT_EVENT);			
		}

		public void onMode(String chan, IRCUser user, IRCModeParser modeParser) {
			handler.pushMessage(user.getNick(), chan, 
					"MODE:" + modeParser.getLine().trim() + ":" + chan, 
					Constants.MSGT_EVENT);
			
			for (int i = 0; i < modeParser.getCount(); i++) {
				char mode = modeParser.getModeAt(i+1);
				char op = modeParser.getOperatorAt(i+1);
				
				switch (mode) {
				case 'o':
					handler.userlist.setOp(chan, user.getNick(), op != '-');
					break;
				case 'v':
					handler.userlist.setVoiced(chan, user.getNick(), op != '-');
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
			Vector<String> chans = handler.userlist.isOn(user.getNick());
			
			for (String chan : chans) {
				handler.pushMessage(user.getNick(), chan, "NICK:" + nick, Constants.MSGT_EVENT);
			}
			handler.userlist.renameNick(user.getNick(), nick);
			handler.syncNick();
		}

		@Override
		public void onNotice(String arg0, IRCUser arg1, String arg2) {
			// TODO Auto-generated method stub
			
		}

		public void onPart(String chan, IRCUser user, String msg) {
			handler.pushMessage(user.getNick(), chan, "PART:" + msg, Constants.MSGT_EVENT);
			handler.userlist.delNick(chan, user.getNick());			
		}

		@Override
		public void onPing(String arg0) {
			// TODO Auto-generated method stub
			
		}

		public void onPrivmsg(String target, IRCUser user, String msg) {
			//System.out.println(target + ":" + user.getNick() + ":" + msg);
			
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
			Vector<String> chans = handler.userlist.isOn(user.getNick());
			
			for (String chan : chans) {
				handler.pushMessage(user.getNick(), chan, "QUIT:" + message, Constants.MSGT_EVENT);
			}
			
			handler.userlist.delNick(user.getNick());
		}

		public void onRegistered() {
			
			System.out.println("Connected to IRC");
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
				System.out.println("NAMREPLY [" + value + "] ["+ msg + "]");
				String[] params = value.split(" ");
				String[] nicks = msg.split(" ");
				
				for (String nick : nicks) {
					userlist.addNick(params[2], nick);
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

}
