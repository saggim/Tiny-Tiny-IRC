package org.fox.ttirc;

import java.io.*;

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
			
			InputStream inputstream = proc.getInputStream();			
            InputStreamReader inputstreamreader = new InputStreamReader(inputstream);            
            BufferedReader bufferedreader = new BufferedReader(inputstreamreader);
			
            String line;
            
            while ((line = bufferedreader.readLine()) != null) {
              System.out.println(line);
            }
            
			proc.waitFor();
			
			System.out.println("[" + connectionId + "] Got exit value = " + proc.exitValue());			
			System.out.println("[" + connectionId + "] Connection terminated.");

		} catch (Exception e) {
			System.err.println(e);
		}
	}

}
