#!/usr/bin/php4 -q
<?

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4:
 * (C) 2002 by Geir Torstein Kristiansen <gtk@linux.online.no> 
 * This code is released under the GNU GPL. See the file COPYING.
 */

/*
 * Example usage of the YapirclBot class. The class extends Yapircl with
 * misc convenience functions and the ability to loop a server list when
 * the connection is lost. Try typing '!help' in the channel it joins :)
 */

require_once '../Yapircl.php';
require_once '../YapirclBot.php';
require_once 'config.php';

set_time_limit (0);
error_reporting (E_ALL);

class Bot extends YapirclBot 
{
    function Bot()
    {
        // call the parent constructor

        $this->Yapirclbot();
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
    
    function handleBotCommands()
    {
        // handle user commands

        switch ($this->word) {
        case '!help':
            $this->privmsg($this->from, "I know !help, !uptime and !netuse");
            break;
        case '!uptime':
            $this->privmsg($this->from, $this->botCommandUptime());
            break;
        case '!netuse':
            $this->privmsg($this->from, $this->botCommandNetuse());
        }
    }

    function event_public_privmsg()
    {
        $this->handleBotCommands();
    }
}

$bot = new Bot;
// $bot->setDebug(false);
$bot->setUser($USER, 'bot', 'Yet Another PHP IRC Library', '+i');
$bot->setServer($SERVER);
$bot->setServerList($SERVERLIST);
$bot->loopServerList('run');
?>
