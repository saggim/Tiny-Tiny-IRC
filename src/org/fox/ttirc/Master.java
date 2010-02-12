package org.fox.ttirc;

import java.io.*;
import java.lang.Thread.*;
import java.nio.channels.*;
import java.sql.*;
import java.util.*;
import java.util.logging.*;
import java.util.prefs.Preferences;

public class Master {

	protected Preferences prefs;
	protected boolean active;
	protected Hashtable<Integer, ConnectionHandler> connections;
	protected int idleTimeout = 5000;
	protected boolean useNativeCH = true;

	private final int configVersion = 2;
	private final String lockFileName = "master.lock";
	private String lockDir;
	private FileLock lock;
	private FileChannel lockChannel;
	
	private Connection conn;
	private String jdbcUrl;
	private String dbUser;
	private String dbPass;
	
	private Logger logger = Logger.getLogger("org.fox.ttirc");
	
	/**
	 * @param args
	 */
	public static void main(String[] args) {
		
		Master m = new Master(args);	

		m.Run();				
	}	
	
	public Logger getLogger() {
		return logger;
	}
	
	public String getLockDir() {
		return lockDir;
	}
	
	public Connection getConnection() {
	
		return conn;
		
		/*boolean isConnected = false;
		int connAttempt = 0;
		
		while (!isConnected && connAttempt < 20) {
			
			try {
				if (conn != null) {
					
				}
			} catch (SQLException e) {
				e.printStackTrace();
				isConnected = false;			
			}
			
			if (!isConnected) {
				System.out.println("Database connection failed. Retrying...");
				try {
					conn = initConnection();
				} catch (SQLException e) {
					e.printStackTrace();
					isConnected = false;
				}
			}
			
			++connAttempt;
			try {
				Thread.sleep(3000);
			} catch (InterruptedException e) {
				e.printStackTrace();
			}
		}
		
		return conn; */
	}
	
	private Connection initConnection() throws SQLException {
		
		logger.info("JDBC URL: " + jdbcUrl);
		logger.info("Establishing database connection...");
		
		this.conn = DriverManager.getConnection(jdbcUrl, dbUser, dbPass);
		
		return conn;
	}
	
	private boolean lock() {
		File f = new File(lockDir + File.separator + lockFileName);
		
		try {		
			lockChannel = new RandomAccessFile(f, "rw").getChannel();			
			lock = lockChannel.tryLock();
			
			if (lock != null) {			
				logger.info("Lock acquired successfully (" + f.getName() + ").");
			} else {
				logger.severe("Couldn't acquire the lock: maybe another daemon is already running?");
				return false;
			}
			
		} catch (Exception e) {
			logger.severe("Couldn't make the lock: " + e.toString());
			e.printStackTrace();
			return false;
		}
		
		return true;
	}
	
	public Master(String args[]) {
		this.prefs = Preferences.userNodeForPackage(getClass());
		this.active = true;
		this.connections = new Hashtable<Integer, ConnectionHandler>(10,10);		

/*		try {
			Handler fh = new FileHandler("%t/ttirc-backend.log");
			fh.setFormatter(new SimpleFormatter()); 
			logger.addHandler(fh);
		} catch (IOException e) {
			logger.warning(e.toString());
			e.printStackTrace();
		} */
		
		String prefs_node = "";
		boolean need_configure = false;
		boolean show_help = false;
		boolean need_cleanup = false;
	
		logger.info("Master " + getVersion() + " initializing...");
		
		for (int i = 0; i < args.length; i++) {
			String arg = args[i];
			
			if (arg.equals("-help")) show_help = true;
			if (arg.equals("-node")) prefs_node = args[i+1]; 
			if (arg.equals("-configure")) need_configure = true;
			if (arg.equals("-cleanup")) need_cleanup = true;
			if (arg.equals("-native")) useNativeCH = args[i+1].equals("true");
		}
		
		if (show_help) {
			System.out.println("Available options:");
			System.out.println("==================");
			System.out.println(" -help              - Show this help");
			System.out.println(" -node node         - Use custom preferences node");
			System.out.println(" -configure         - Force change configuration");
			System.out.println(" -cleanup           - Cleanup data and exit");
			System.out.println(" -native true/false - Use native (Java-based) connection handler");
			System.exit(0);
		}
		
		if (prefs_node.length() > 0) {
			logger.info("Using custom preferences node: " + prefs_node);
			prefs = prefs.node(prefs_node);
		}
		
		try {
			Class.forName("org.postgresql.Driver");
		} catch (ClassNotFoundException e) {
			logger.severe("error: JDBC driver for PostgreSQL not found.");
			e.printStackTrace();
			System.exit(1);			
		}

		if (prefs.getInt("CONFIG_VERSION", -1) != configVersion || need_configure) {
			configure();
		}
		
		String LOCK_DIR = prefs.get("LOCK_DIR", "/var/tmp");
		
		File f = new File(LOCK_DIR);
		
		if (!f.isDirectory() || !f.canWrite()) {
			logger.severe("Lock directory [" + LOCK_DIR + "] must be a writable directory." );
			System.exit(2);
		}
		
		lockDir = LOCK_DIR;
		
		if (!lock()) System.exit(3);
				
		String DB_HOST = prefs.get("DB_HOST", "localhost");
		String DB_USER = prefs.get("DB_USER", "user");
		String DB_PASS = prefs.get("DB_PASS", "pass");
		String DB_NAME = prefs.get("DB_NAME", "user");
		String DB_PORT = prefs.get("DB_PORT", "5432");
		
		String jdbcUrl = "jdbc:postgresql://" + DB_HOST + ":" + DB_PORT + 
			"/" + DB_NAME;
		
		this.jdbcUrl = jdbcUrl;
		this.dbUser = DB_USER;
		this.dbPass = DB_PASS;
		
		try {
			initConnection();
		} catch (SQLException e) {
			logger.severe("error: Couldn't connect to database.");
			e.printStackTrace();
			System.exit(1);
		}
		
		logger.info("Database connection established."); 

		try {
			cleanup();
			if (need_cleanup) System.exit(0);
		} catch (SQLException e) {
			e.printStackTrace();
			System.exit(1);
		}
	}
	
	public String getVersion() {
		String version = Master.class.getPackage().getImplementationVersion();
		
		if (version != null)		
			return version;
		else
			return "0.0.0 (0)";
	}

	public void configure() {
		System.out.println("Backend configuration");
		System.out.println("=====================");
		
		InputStreamReader input = new InputStreamReader(System.in);
		BufferedReader reader = new BufferedReader(input); 
		String in;
		boolean configured = false;

		try {
		
			while (!configured) {

				System.out.print("Directory for lockfiles [/var/tmp]: ");
				in = reader.readLine();
				prefs.put("LOCK_DIR", in);

				System.out.print("Database host [localhost]: ");
				in = reader.readLine();
				
				if (in.length() == 0) in = "localhost";
				
				prefs.put("DB_HOST", in);

				System.out.print("Database port [5432]: ");
				in = reader.readLine();
				
				if (in.length() == 0) in = "5432";
				
				prefs.put("DB_PORT", in);

				System.out.print("Database name: ");
				in = reader.readLine();
				prefs.put("DB_NAME", in);

				System.out.print("Database user: ");
				in = reader.readLine();
				prefs.put("DB_USER", in);

				System.out.print("Database password: ");
				in = reader.readLine();
				prefs.put("DB_PASS", in);

				System.out.print("Done? [Y/N] ");
				in = reader.readLine();
				
				configured = in.equalsIgnoreCase("Y");				
			}			
	
			prefs.putInt("CONFIG_VERSION", configVersion);
			
			logger.info("Data saved. Please use -configure switch to change it later.");
			
		} catch (IOException e) {
			e.printStackTrace();
		}
	}
	
	public void cleanup() throws SQLException {
		
		logger.info("Cleaning up...");
		
		Statement st = getConnection().createStatement();
		
		st.execute("UPDATE ttirc_connections SET status = " +
				String.valueOf(Constants.CS_DISCONNECTED) + ", userhosts = ''");

      	st.execute("UPDATE ttirc_channels SET nicklist = ''");

      	st.execute("UPDATE ttirc_system SET value = 'false' WHERE " +
      		"key = 'MASTER_RUNNING'");
	
      	st.close();
	}
	
	public void updateHeartbeat() throws SQLException {
		Statement st = getConnection().createStatement();
		
		st.execute("UPDATE ttirc_system SET value = 'true' WHERE key = 'MASTER_RUNNING'");
		st.execute("UPDATE ttirc_system SET value = NOW() WHERE key = 'MASTER_HEARTBEAT'");
		
		st.close();
	}

	public void checkHandlers() throws SQLException {
		Statement st = getConnection().createStatement();

		Enumeration<Integer> e = connections.keys();
		
		while (e.hasMoreElements()) {
			int connectionId = e.nextElement();	
			
    		ConnectionHandler ch = (ConnectionHandler) connections.get(connectionId);
    		
    		PreparedStatement ps = getConnection().prepareStatement("SELECT id FROM ttirc_connections WHERE id = ? " +
    			"AND enabled = true");
    		
    		ps.setInt(1, connectionId);
    		ps.execute();
    		
    		ResultSet rs = ps.getResultSet();
    		
    		if (!rs.next()) {
    			logger.info("Connection " + connectionId + " needs termination.");
    			try {
    				ch.kill();
    			} catch (Exception ee) {
    				logger.warning(ee.toString());
    				ee.printStackTrace();
    			}
    		}
    		
    		if (ch.getState() == State.TERMINATED) {
    			logger.info("Connection " + connectionId + " terminated.");
    			connections.remove(connectionId);
    			cleanup(connectionId);
    		}			
		}
		
	    st.execute("SELECT ttirc_connections.id " +
	    		"FROM ttirc_connections, ttirc_users " +
	    		"WHERE owner_uid = ttirc_users.id AND " +
	    		"visible = true AND " +
	    		"(heartbeat > NOW() - INTERVAL '5 minutes' OR " +
	    		"permanent = true) AND " +
	    		"enabled = true");
	
	    ResultSet rs = st.getResultSet();	    
	    
	    while (rs.next()) {
	    	int connectionId = rs.getInt(1);
	    	
	    	String useCHType = (useNativeCH == true) ? "NativeConnectionHandler" : "SystemConnectionHandler"; 
	    		
	    	if (!connections.containsKey(connectionId)) {
	    	  	logger.info("Spawning connection " + connectionId + " using " + useCHType);
	    	  	
	    	  	ConnectionHandler ch;
	    	  	
	    	  	if (useNativeCH)	    	  	
	    	  		ch = new NativeConnectionHandler(connectionId, this);
	    	  	else
	    	  		ch = new SystemConnectionHandler(connectionId, this);
	    	  	
	    	   	connections.put(connectionId, ch);
	    	   	
	    	   	ch.start();
	    	}
	    }
	}
	
	public String getNick(int connectionId) throws SQLException {
		PreparedStatement ps = getConnection().prepareStatement("SELECT active_nick FROM " +
				"ttirc_connections WHERE id = ?");
		
		ps.setInt(1, connectionId);
		ps.execute();
		
		ResultSet rs = ps.getResultSet();
		
		if (rs.next()) {
			return rs.getString(1);			
		} else {
			return "?UNKNOWN?";
		}
	}
	
	public void pushMessage(int connectionId, String channel, String message, 
			boolean incoming, int messageType, 
			String fromNick) throws SQLException {
	
		String nick;
		
		if (channel.equals("---")) {
			nick = "---";
		} else {
			nick = getNick(connectionId);
		}
		
		if (fromNick.length() != 0) nick = fromNick;
		
		PreparedStatement ps = getConnection().prepareStatement("INSERT INTO ttirc_messages " +
				"(incoming, connection_id, channel, sender, message, message_type) " +
				" VALUES (?, ?, ?, ?, ?, ?)");
				
		ps.setBoolean(1, incoming);
		ps.setInt(2, connectionId);
		ps.setString(3, channel);
		ps.setString(4, nick);
		ps.setString(5, message);
		ps.setInt(6, messageType);
		
		ps.execute();
	}
	
	public void cleanup(int connectionId) throws SQLException {
		PreparedStatement ps = getConnection().prepareStatement("UPDATE ttirc_connections SET status = ? " + 
				"WHERE id = ?");
		
		ps.setInt(1, Constants.CS_DISCONNECTED);
		ps.setInt(2, connectionId);
		ps.execute();
		ps.close();
		
		ps = getConnection().prepareStatement("UPDATE ttirc_channels SET nicklist = '' " +
				"WHERE connection_id = ?");
		
		ps.setInt(1, connectionId);
		ps.execute();
		ps.close();
		
		pushMessage(connectionId, "---", "DISCONNECT", true, Constants.MSGT_EVENT, "");
	}
	
	public void Run() {
		logger.info("Waiting for clients...");
		
		while (active) {
			
			try {	
				updateHeartbeat();
				checkHandlers();				
				
			} catch (SQLException e) {
				e.printStackTrace();
			}
			
			try {
				Thread.sleep(idleTimeout);
			} catch (InterruptedException e) {
				//		
			}
		}
		
		try {
			cleanup();
		} catch (SQLException e) {
			logger.warning(e.toString());
			e.printStackTrace();			
		}
	}
	
	public void finalize() throws Throwable {
		cleanup();		

		lock.release();
		lockChannel.close();
	}

}
