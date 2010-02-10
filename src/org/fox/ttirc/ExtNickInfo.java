package org.fox.ttirc;

import java.sql.SQLException;
import java.util.*;
import org.json.simple.*;

public class ExtNickInfo {
	
	public class ExtInfo {
		public String ident;
		public String host;
		public String server;
		public String realName;
		
		public String awayReason;
		public boolean isAway;
		
		public ExtInfo(String ident, String host, String server, String realName) {
			this.ident = ident;
			this.host = host;
			this.server = server;
			this.realName = realName;
		}

		@SuppressWarnings("unchecked")
		public JSONArray toJSON() {
			JSONArray tmp = new JSONArray();
			
			tmp.add(ident);
			tmp.add(host);
			tmp.add(server);
			tmp.add(realName);
			tmp.add(awayReason);
			tmp.add(isAway);
			
			return tmp;
		}
	}

	private Hashtable<String, ExtInfo> extinfo = new Hashtable<String, ExtInfo>(); 
	private NativeConnectionHandler handler;
	
	public ExtNickInfo(NativeConnectionHandler handler) {
		this.handler = handler;
	}
	
	public void update(String nick, String ident, String host, String server, String realName) {
		ExtInfo xi = extinfo.get(nick);
		
		if (xi != null) {
			xi.ident = ident;
			xi.host = host;
			xi.server = server;
			xi.realName = realName;
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
}
