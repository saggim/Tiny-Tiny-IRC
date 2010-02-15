package org.fox.ttirc;

import java.sql.SQLException;
import java.util.*;
import org.json.simple.*;

public class ExtNickInfo {
	
	public class ExtInfo {
		private String ident;
		private String host;
		private String server;
		private String realName;
		
		private String awayReason;
		private boolean isAway;
		
		private Date lastUpdated;
		
		public ExtInfo() {
			this.lastUpdated = new Date();
		}
		
		public ExtInfo(String ident, String host, String server, String realName) {
			this.ident = ident;
			this.host = host;
			this.server = server;
			this.realName = realName;			
			this.lastUpdated = new Date();
		}

		@SuppressWarnings("unchecked")
		public JSONArray toJSON() {
			JSONArray tmp = new JSONArray();
			
			tmp.add(ident);
			tmp.add(host);
			tmp.add(server);
			tmp.add(realName);
			tmp.add(isAway);
			tmp.add(awayReason);
			
			return tmp;
		}

		public void setIdent(String ident) {
			this.ident = ident;
		}

		public String getIdent() {
			return ident;
		}

		public void setHost(String host) {
			this.host = host;
		}

		public String getHost() {
			return host;
		}

		public void setServer(String server) {
			this.server = server;
		}

		public String getServer() {
			return server;
		}

		public void setRealName(String realName) {
			this.realName = realName;
		}

		public String getRealName() {
			return realName;
		}

		public void setAwayReason(String awayReason) {
			this.awayReason = awayReason;
		}

		public String getAwayReason() {
			return awayReason;			
		}

		public void setAway(boolean isAway) {
			this.isAway = isAway;
		}

		public boolean isAway() {
			return isAway;
		}

		public void setUpdated() {
			this.lastUpdated = new Date();
		}
		
		public Date getLastUpdated() {
			return lastUpdated;
		}
	}

	private Hashtable<String, ExtInfo> extinfo = new Hashtable<String, ExtInfo>(); 
	private NativeConnectionHandler handler;
	
	public ExtNickInfo(NativeConnectionHandler handler) {
		this.handler = handler;
	}
	
	public void update(String nick) {
		ExtInfo xi = extinfo.get(nick);
		
		if (xi == null) {
			xi = new ExtInfo();
			extinfo.put(nick, xi);
		}
		
		/* There is no need to sync, since this is a placeholder entry to keep until we
		 * have received actual WHO reply
		 */
		
	}
	
	public void renameNick(String oldNick, String newNick) {
		ExtInfo xi = extinfo.get(oldNick);
		
		if (xi != null) {
			extinfo.remove(oldNick);
			extinfo.put(newNick, xi);

			Sync();
		}
	}
	
	public void update(String nick, String ident, String host, String server, String realName) {
		ExtInfo xi = extinfo.get(nick);
		
		if (xi != null) {
			xi.setIdent(ident);
			xi.setHost(host);
			xi.setServer(server);
			xi.setRealName(realName);
			xi.setUpdated();
		} else {
			xi = new ExtInfo(ident, host, server, realName);
		}
		
		extinfo.put(nick, xi);
		
		Sync();
	}
	
	@SuppressWarnings("unchecked")
	public void Sync() {
		Map map = new LinkedHashMap();
		Enumeration<String> en = extinfo.keys();
		
		while (en.hasMoreElements()) {
			String nick = en.nextElement();			
			map.put(nick, extinfo.get(nick).toJSON());			
		}
		
		String jsonValue = JSONValue.toJSONString(map);
		
		try {
			handler.syncExtInfo(jsonValue);
		} catch (SQLException e) {
			e.printStackTrace();
		}
	}

	public void delete(String nick) {
		extinfo.remove(nick);
		Sync();
	}
	
	public void setAwayReason(String nick, String awayReason) {
		ExtInfo xi = extinfo.get(nick);
		
		if (xi != null) {
			xi.awayReason = awayReason;
			xi.setUpdated();
			Sync();
		}
	}
	
	public void setAway(String nick, boolean away) {
		ExtInfo xi = extinfo.get(nick);
		
		if (xi != null) {
			xi.isAway = away;			
			if (!away) xi.setAwayReason("");
			xi.setUpdated();

			Sync();
		}
	}

	public boolean hasNick(String nick) {
		return extinfo.containsKey(nick);
	}

}
