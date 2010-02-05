package org.fox.ttirc;

public class ConnectionHandler extends Thread {
	
	protected int connectionId;
	Master master;
	
	public ConnectionHandler(int connectionId, Master master) {
		this.connectionId = connectionId;
		this.master = master;
	}
		
	public void run() {
		try {
			
			Runtime.getRuntime().exec("./handle.php " + connectionId);
		} catch (Exception e) {
			System.out.println(e);
		}
	}

}
