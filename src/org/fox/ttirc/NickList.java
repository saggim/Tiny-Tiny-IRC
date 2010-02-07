package org.fox.ttirc;

import org.schwering.irc.lib.IRCConnection;
import org.schwering.irc.lib.IRCEventListener;
import org.schwering.irc.lib.IRCModeParser;
import org.schwering.irc.lib.IRCUser;
import org.schwering.irc.lib.ssl.SSLIRCConnection;
import org.schwering.irc.lib.ssl.SSLTrustManager;

import com.sun.org.apache.xpath.internal.operations.Equals;
import com.sun.xml.internal.bind.v2.runtime.unmarshaller.XsiNilLoader.Array;

import java.sql.PreparedStatement;
import java.sql.SQLException;
import java.util.*;

import org.json.simple.JSONArray;
import org.json.simple.JSONObject;

public class NickList {
	
	public class NickComparator implements Comparator<String> {

		public int compare(String o1, String o2) {
			char c1 = o1.charAt(0);
			char c2 = o2.charAt(0);
			
			if (c1 == '@' && c2 != '@') {
				return 1;
			}

			if (c1 == '+' && c2 != '+') {
				return 1;
			}

			return o1.compareTo(o2);
		}

	}

	public class Nick {
		private String nick;
		private boolean v = false;
		private boolean o = false;
		
		public Nick(String nick) {
			if (nick.charAt(0) == '@') {
				nick = nick.substring(1);
				this.o = true;
			}

			if (nick.charAt(0) == '+') {
				nick = nick.substring(1);
				this.v = true;
			}
			
			this.nick = nick;		
		}
		
		public Nick(String nick, boolean v, boolean o) {
			this.nick = nick;
			this.v = v;
			this.o = o;
		}
		
		public void renameTo(String nick) {
			this.nick = nick;
		}
		
		public void setVoiced(boolean v) {
			this.v = v;
		}
		
		public void setOp(boolean o) {
			this.o = o;
		}
		
		public boolean isVoiced() {
			return v;
		}
		
		public boolean isOp() {
			return o;
		}
		
		public boolean equals(Object obj) {
			boolean result = this.nick.equalsIgnoreCase(obj.toString());
			
			//System.out.println(nick + " EQUALS? " + obj + " = " + result);
			return result;
		}
		
		public String toString() {
			String prefix = "";
			
			if (o) 
				prefix += "@";			
			else if (v) 
				prefix += "+";
			
			return prefix + nick;
		}
		
		public int hashCode() {
			return nick.hashCode();
		}
	}

	private Hashtable<String, Vector<Nick>> nicklist = new Hashtable<String, Vector<Nick>>();
	private ConnectionHandler handler;
	
	public NickList(ConnectionHandler handler) {
		this.handler = handler;  
	}
	
	public void addNick(String chan, String nick) {
		
		if (!nicklist.containsKey(chan)) 
			nicklist.put(chan, new Vector<Nick>());
	
		chan = chan.toLowerCase();
		Nick n = new Nick(nick);
		
		if (!nicklist.get(chan).contains(n))
			nicklist.get(chan).add(n);
		
		//System.out.println("Added " + nick + " on" + chan);
		//System.out.println("L=" + nicklist.get(chan).toArray().length);
				
		Sync(chan);
	}
	
	public void delNick(String chan, String nick) {
		if (!nicklist.containsKey(chan)) 
			nicklist.put(chan, new Vector<Nick>());

		chan = chan.toLowerCase();
		Nick n = new Nick(nick); 
		
		nicklist.get(chan).remove(n);
		
		//System.out.println("Removed " + nick + " from" + chan);
		//System.out.println("L=" + nicklist.get(chan).toArray().length);
		
		Sync(chan);
	}

	public void delNick(String nick) {

		Enumeration<String> chans = nicklist.keys();
		
		Nick n = new Nick(nick);
		
		while (chans.hasMoreElements()) {
			String chan = chans.nextElement();
			
			nicklist.get(chan).remove(n);
		}
		
		Sync();
	}
	
	public Vector<String> isOn(String nick) {
		
		Vector<String> tmp = new Vector<String>();
		Enumeration<String> chans = nicklist.keys();
		
		Nick n = new Nick(nick);
		
		while (chans.hasMoreElements()) {
			String chan = chans.nextElement();
			if (nicklist.get(chan).contains(n)) {
				tmp.add(chan);
			}
		}
	
		return tmp;
	}
	
	public void Sync() {
		Enumeration<String> en = nicklist.keys();
		
		while (en.hasMoreElements()) {
			String chan = en.nextElement();				
			Sync(chan);
		}		
	}
	
	public void renameNick(String oldNick, String newNick) {
		Enumeration<String> chans = nicklist.keys();
		
		while (chans.hasMoreElements()) {
			String chan = chans.nextElement();
			
			Enumeration<Nick> nicks = nicklist.get(chan).elements();
				
			while (nicks.hasMoreElements()) {
				Nick nick = nicks.nextElement();
			
				if (nick.equals(oldNick)) {				
					nick.renameTo(newNick);		
					handler.pushMessage(oldNick, chan, "NICK:" + newNick, Constants.MSGT_EVENT);
				}
			}		
		}
		
		Sync();
	}
	
	@SuppressWarnings("unchecked")
	public void Sync(String channel)  {
		channel = channel.toLowerCase();
		
		Enumeration<Nick> en = this.nicklist.get(channel).elements();

		JSONArray nicks = new JSONArray();

		while (en.hasMoreElements()) {
			nicks.add(en.nextElement().toString());				
		}

		Collections.sort(nicks, new NickComparator());
		
		try {
		
			PreparedStatement ps = handler.conn.prepareStatement("UPDATE ttirc_channels " +
				"SET nicklist = ? WHERE channel = ? AND connection_id = ?");
			
			ps.setString(1, nicks.toJSONString());
			ps.setString(2, channel);
			ps.setInt(3, handler.connectionId);
			ps.execute();
			ps.close();
			
		} catch (SQLException e) {
			e.printStackTrace();
		}
		
	}
	
}
