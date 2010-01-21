<?
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
// +----------------------------------------------------------------------+
// | PHP version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Geir Torstein Kristiansen <gtk@linux.online.no>             |
// | Ideas and code are also taken from the pear Net_IRC class made by    |
// | Tomas V.V.Cox <cox@idecnet.com>                                      |
// +----------------------------------------------------------------------+
//
// $Id$

class YapirclBot extends Yapircl
{
    // convenience functions for irc bots/clients
    
    // settings
    var $_serverlist;           // irc servers (array)
    var $_prefs_wait_retry_con; // seconds before trying connection again (int)
    var $_prefs_try_each_con;   // try to connect to each server n times (int)

    // internal
    var $_server_current;       // current array offset for $_serverlist (int)
    var $_server_retries;       // retries connecting to server (int)

    function YapirclBot()
    {
        $this->Yapircl();               // call the parent constructor
        
        // settings
        $this->_prefs_wait_retry_con    = 20;
        $this->_prefs_try_each_con      = 3;

        // internal
        $this->_server_current          = 0;
        $this->_server_retries          = 0;
    }

    function setServerList($serverlist)
    {
        $this->_serverlist = $serverlist;
    }

    function _tryServer()
    {
        // try each server 3 times and start at the top of the list again

        if ($this->_server_retries >= $this->_prefs_try_each_con) {
            
            $this->_server_current++;

            if ($this->_server_current >= count($this->_serverlist)) {
                $this->_server_current = 0;
            }

            $this->_server_retries = 0;
        }

        $this->_server_retries++;
        $this->_server = $this->_serverlist[$this->_server_current];
    }

    function loopServerList($mainloop_method)
    {
        // stay connected forever, loops and changes server if needed

        $this->debug('loopServerList() starting ' . $this->getVersion());

        while (true) {
            
            $this->debug("loopServerList() connection attempt " . ($this->_server_retries + 1));
            $this->_tryServer();

            if (!$this->connect()) {
                    $this->debug('loopServerList() connection failed');
            } else {
                    $this->debug('loopServerList() registered');
                    if (!$this->_tryCallback($mainloop_method)) {
                        $this->debug("loopServerList() no valid mainloop!");
                        exit(1);
                    }
            } 

            $this->debug("loopServerList() sleeping for $this->_prefs_wait_retry_con seconds\n");
            sleep($this->_prefs_wait_retry_con);
        }
    }

    // statistics functions

    function secondsToHuman($totalseconds)
    {
        $seconds = $totalseconds % 60;
        $minutes = ($totalseconds / 60) % 60;
        $hours   = ($totalseconds / 3600) % 24; 
        $days    = (int) ($totalseconds / 86400); 

        return "${days}d ${hours}h ${minutes}m ${seconds}s";
    }

    function getUptimeTotal()
    {
        return $this->secondsToHuman(time() - $this->_uptime_total);
    }

    function getUptimeConnected()
    {
        return $this->secondsToHuman(time() - $this->_uptime_connected);
    }

    function bytesToKb($bytes)
    {
        return round($bytes / 1024, 2) . " kb";
    }

    function getRxTotal()
    {
        return $this->bytesToKb($this->_totbytes_rx);
    }
    
    function getTxTotal()
    {
        return $this->bytesToKb($this->_totbytes_tx);
    }

    function getRxConnected()
    {
        return $this->bytesToKb($this->_conbytes_rx);
    } 

    function getTxConnected()
    {
        return $this->bytesToKb($this->_conbytes_tx);
    }

    function botCommandUptime()
    {
        $msg = "[uptime] - [total] " . $this->getUptimeTotal() .
        " - [$this->_server] " . $this->getUptimeConnected();
        return $msg;
    }
    
    function botCommandNetuse()
    {
        $msg  = "[netuse] - [total] "
        . "[RX] " . $this->getRxTotal() . " ($this->_totlines_rx lines) - "
        . "[TX] " . $this->getTxTotal() . " ($this->_totlines_tx lines)"
        . " - [$this->_server] "
        . "[RX] " . $this->getRxConnected() . " ($this->_conlines_rx lines) - "
        . "[TX] " . $this->getTxConnected() . " ($this->_conlines_tx lines)";
        return $msg;
    }
}
?>
