#!/usr/bin/php4 -q
<?

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4:
 * (C) 2002 by Geir Torstein Kristiansen <gtk@linux.online.no> 
 * This code is released under the GNU GPL. See the file COPYING.
 */

/*
 * Example usage of the Yapircl class. This will join a channel and format
 * some of what is going on in the channel in a sort of console irc client
 * way. It also responds to ctcp ping and ctcp version requests.
 */

require_once '../Yapircl.php';
require_once 'config.php';

set_time_limit (0);
error_reporting (E_ALL);

class Dummy extends Yapircl 
{
    function Dummy()
    {
        // call the parent constructor

        $this->Yapircl();
    }
  
    function run()
    {
        // main function

        global $CHANNEL;
        $this->join($CHANNEL);

        while ($this->connected()) {

            // stay connected
            $this->idle();
            usleep($this->_resolution);
        }
    }

    function event_public_privmsg()
    {
        echo "<" . $this->nick . "> " . $this->full . "\n";
    }

    function event_private_privmsg()
    {
        echo "[" . $this->nick . "(" . $this->user 
        . "@" . $this->host . ")] " . $this->full . "\n"; 
    }

    function event_public_ctcp_action()
    {
        echo "*" . $this->nick . "* " . $this->full . "\n";
    }

    function handle_ctcp_version()
    {
        $this->ctcp($this->nick, "VERSION " . $this->getVersion());
        echo $this->mask . " requested CTCP VERSION from " . $this->from . "\n";
    }

    function handle_ctcp_ping()
    {
        $this->ctcp($this->nick, $this->full);
        echo $this->mask . " requested CTCP PING from " . $this->from . "\n";
    }

    function event_public_ctcp_version()
    {
        $this->handle_ctcp_version();
    }
    
    function event_private_ctcp_version()
    {
        $this->handle_ctcp_version();
    }

    function event_public_ctcp_ping()
    {
        $this->handle_ctcp_ping();
    }

    function event_private_ctcp_ping()
    {
        $this->handle_ctcp_ping();
    }

    function event_rpl_endofnames()
    {
        echo "[Users ". $this->_xline[3] . "]\n";
        
        $i = 0;
        foreach ($this->getNickList($this->_xline[3]) as $nick) {
            if ($i > 4) {
                echo "\n";
                $i = 0;
            } else {
                echo "[ " . str_pad($nick, 10) . " ] ";
                $i++;
            }
        }
        
        echo "\n";
    }
}

$dummy = new Dummy;
$dummy->setDebug(false);
$dummy->setUser($USER, 'dummy', 'Yet Another PHP IRC Library', '+i');
$dummy->setServer($SERVER);

echo "starting yapircl test\n";

$dummy->connect();

echo "connected to $SERVER\n";

$dummy->run();
?>
