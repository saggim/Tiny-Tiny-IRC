package org.fox.ttirc;

import org.schwering.irc.lib.IRCConnection;
import org.schwering.irc.lib.IRCEventListener;
import org.schwering.irc.lib.IRCModeParser;
import org.schwering.irc.lib.IRCUser;
import org.schwering.irc.lib.ssl.SSLIRCConnection;
import org.schwering.irc.lib.ssl.SSLTrustManager;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.IOException;
import java.security.cert.X509Certificate;
import java.util.Hashtable;
import java.io.*;
import java.lang.Thread.*;
import java.sql.*;
import java.util.*;
import java.util.prefs.Preferences;


public class ConnectionHandler extends Thread {
	
	protected int connectionId;
	protected Master master;
	protected Connection conn;	
	
	protected String nick;
	protected String localNick;
	protected String encoding;
	protected String email;
	protected String realname;
	protected String[] autojoin;
	protected String quitMessage;
	
	protected int lastSentId = 0;
	
	protected Hashtable userlist = new Hashtable<String, IRCUser[]>();
	
	private IRCConnection irc;
	private boolean active = true;
	
	public ConnectionHandler(int connectionId, Master master) {
		this.connectionId = connectionId;
		this.master = master;
		this.conn = master.conn;
	}
	
	public void SetConnected(boolean connected) throws SQLException {
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

	public String[] GetRandomServer() throws SQLException {
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
	
	public boolean Connect() throws SQLException {
		PreparedStatement ps = conn.prepareStatement("SELECT *, " +
			"ttirc_connections.nick AS local_nick, ttirc_users.nick AS normal_nick " +
			"FROM ttirc_connections, ttirc_users " +
			"WHERE ttirc_connections.id = ? AND owner_uid = ttirc_users.id");
		
		ps.setInt(1, connectionId);
		ps.execute();
		
		ResultSet rs = ps.getResultSet();
		
		if (rs.next()) {
			nick = rs.getString("normal_nick");
			localNick = rs.getString("local_nick");
			encoding = rs.getString("encoding");
			email = rs.getString("email");
			realname = rs.getString("realname");
			autojoin = rs.getString("autojoin").split(",");
			quitMessage = rs.getString("quit_message");
			lastSentId = rs.getInt("last_sent_id");
		
			// wtf? no nick?!
			if (nick.length() == 0) return false;
			
			if (localNick.length() > 0) nick = localNick;
					
			System.out.println(nick + " " + email + " " + realname + " " + autojoin);
			
			String[] server = GetRandomServer();
			
			if (server.length != 2) {
				PushMessage("---", "---", "NOSERVER", Constants.MSGT_EVENT);
				
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

			PushMessage("---", "---", "CONNECTING:" + server[0] + ":" + server[1], Constants.MSGT_EVENT);
			
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
			irc.setDaemon(true);
			irc.setColors(false);
			
			try {
				irc.connect();
				
				return true;
			} catch (IOException e) {
				PushMessage("---", "---", "CONNECTION_ERROR:" + server[0] + ":" + server[1], 
						Constants.MSGT_EVENT);
				return false;
			}
		
		} else {
			return false;
		}
		
	}
	
	public void PushMessage(String sender, String channel, String message,
			int messageType) throws SQLException {
		
		PreparedStatement ps = conn.prepareStatement("INSERT INTO ttirc_messages " +
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
	}
	
	public void kill() {
		irc.doQuit(quitMessage);
	}
	
	public void CheckMessages() throws SQLException {
		PreparedStatement ps = conn.prepareStatement("SELECT * FROM ttirc_messages " +
				"WHERE incoming = false AND" +
				"ts > NOW() - INTERVAL '1 year' AND " +
               "connection_id = ? AND" +
               "id > ? ORDER BY id");
		
	}
	
	public void DisconnectIfDisabled() throws SQLException {
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
			
			SetConnected(enabled);
			
		} else {
			SetConnected(false);
		}
		
		ps.close();
	}
	
	public void run() {
		try {
			
			if (!Connect()) return;
		
			while (active) {
				DisconnectIfDisabled();
				CheckMessages();
				sleep(1000);
			}
			

		} catch (Exception e) {
			e.printStackTrace();
		}
	}
	
	public void SetTopic(String channel, String nick, String topic) throws SQLException {
		
		PreparedStatement ps = conn.prepareStatement("UPDATE ttirc_channels SET " +
				"topic = ?, topic_owner = ?, topic_set = NOW()");
	
		ps.setString(1, topic);
		ps.setString(2, nick);
		
		ps.execute();
		ps.close();
		
	}
	
	public class Listener implements IRCEventListener {

		protected ConnectionHandler handler;
		protected int connectionId;
		String[] autojoin;
		
		public Listener(int connectionId, ConnectionHandler handler, String[] autojoin) {
			this.connectionId = connectionId;
			this.handler = handler;
			this.autojoin = autojoin;
		}
		
		public void onDisconnected() {			
			try {
				handler.SetConnected(false);
			} catch (SQLException e) {
				e.printStackTrace();
			}
		}

		public void onError(String arg0) {
			// TODO Auto-generated method stub

		}

		@Override
		public void onError(int arg0, String arg1) {
			// TODO Auto-generated method stub
			
		}

		@Override
		public void onInvite(String arg0, IRCUser arg1, String arg2) {
			// TODO Auto-generated method stub
			
		}

		@Override
		public void onJoin(String arg0, IRCUser arg1) {
			// TODO Auto-generated method stub
			
		}

		@Override
		public void onKick(String arg0, IRCUser arg1, String arg2, String arg3) {
			// TODO Auto-generated method stub
			
		}

		@Override
		public void onMode(String arg0, IRCUser arg1, IRCModeParser arg2) {
			// TODO Auto-generated method stub
			
		}

		@Override
		public void onMode(IRCUser arg0, String arg1, String arg2) {
			// TODO Auto-generated method stub
			
		}

		@Override
		public void onNick(IRCUser arg0, String arg1) {
			// TODO Auto-generated method stub
			
		}

		@Override
		public void onNotice(String arg0, IRCUser arg1, String arg2) {
			// TODO Auto-generated method stub
			
		}

		@Override
		public void onPart(String arg0, IRCUser arg1, String arg2) {
			// TODO Auto-generated method stub
			
		}

		@Override
		public void onPing(String arg0) {
			// TODO Auto-generated method stub
			
		}

		public void onPrivmsg(String target, IRCUser user, String msg) {
			//System.out.println(target + ";" + user.getNick() + ";" + msg);
			
			try {			
				if (target.equals(handler.irc.getNick())) {
					handler.PushMessage(user.getNick(), target, msg, Constants.MSGT_PRIVATE_PRIVMSG);
				} else {
					handler.PushMessage(user.getNick(), target, msg, Constants.MSGT_PRIVMSG);				
				}
			} catch (SQLException e) {
				e.printStackTrace();
			}
		}

		@Override
		public void onQuit(IRCUser arg0, String arg1) {
			// TODO Auto-generated method stub
			
		}

		public void onRegistered() {
			
			try {
				handler.SetConnected(true);
			} catch (SQLException e) {
				e.printStackTrace();
			}
			
			/* Autojoin channels */
			for (String chan : this.autojoin) {
				handler.irc.doJoin(chan);				
			}
		}

		@Override
		public void onReply(int num, String value, String msg) {
			if (num == RPL_TOPIC) {
				String[] params = value.split(" ");
								
				try {
					handler.SetTopic(params[1], params[0], msg);
				} catch (SQLException e) {
					e.printStackTrace();
				}
			}			
		}

		public void onTopic(String chan, IRCUser user, String topic) {
			
			System.out.println("onTopic: " + chan);
			
			try {
				handler.SetTopic(chan, user.getNick(), topic);
				handler.PushMessage(user.getNick(), chan, "TOPIC:" + topic, Constants.MSGT_EVENT);
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
