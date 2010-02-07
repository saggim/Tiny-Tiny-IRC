package org.fox.ttirc;

public class Constants {
	
	/* Message types */
	
	public static final int MSGT_PRIVMSG = 0;
	public static final int MSGT_COMMAND = 1;
	public static final int MSGT_BROADCAST = 2;
	public static final int MSGT_ACTION = 3;
	public static final int MSGT_TOPIC = 4; // unused
	public static final int MSGT_PRIVATE_PRIVMSG = 5;
	public static final int MSGT_EVENT = 6;
	public static final int MSGT_NOTICE = 7;
	public static final int MSGT_SYSTEM = 8;

	/* Connection states */
	
	public static final int CS_DISCONNECTED = 0;
	public static final int CS_CONNECTING = 1;
	public static final int CS_CONNECTED = 2;

	/* Channel types */
	
	public static final int CT_CHANNEL = 0;
	public static final int CT_PRIVATE = 1;	
}
