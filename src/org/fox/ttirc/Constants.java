package org.fox.ttirc;

public class Constants {
	
	/* Message types */
	
	public static int MSGT_PRIVMSG = 0;
	public static int MSGT_COMMAND = 1;
	public static int MSGT_BROADCAST = 2;
	public static int MSGT_ACTION = 3;
	public static int MSGT_TOPIC = 4; // unused
	public static int MSGT_PRIVATE_PRIVMSG = 5;
	public static int MSGT_EVENT = 6;
	public static int MSGT_NOTICE = 7;
	public static int MSGT_SYSTEM = 8;

	/* Connection states */
	
	public static int CS_DISCONNECTED = 0;
	public static int CS_CONNECTING = 1;
	public static int CS_CONNECTED = 2;

	/* Channel types */
	
	public static int CT_CHANNEL = 0;
	public static int CT_PRIVATE = 1;	
}
