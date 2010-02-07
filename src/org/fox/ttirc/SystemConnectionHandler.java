package org.fox.ttirc;

public class SystemConnectionHandler extends ConnectionHandler {
	
	protected int connectionId;
	protected Master master;
	protected Process proc;
	
	public SystemConnectionHandler(int connectionId, Master master) {
		this.connectionId = connectionId;
		this.master = master;
	}
	
	public void kill() {
		proc.destroy();
	}
	
	public void run() {
		try {
			
			proc = Runtime.getRuntime().exec("./handle.php " + connectionId);
			
			proc.waitFor();
			
			System.err.println("Exit value = " + proc.exitValue());
			
			System.out.println("[" + connectionId + "] Connection terminated.");

		} catch (Exception e) {
			System.err.println(e);
		}
	}

}
