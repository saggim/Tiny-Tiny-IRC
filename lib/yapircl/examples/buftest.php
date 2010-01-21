#!/usr/bin/php4 -qe
<?

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4:
 * (C) 2002 by Geir Torstein Kristiansen <gtk@linux.online.no> 
 * This code is released under the GNU GPL. See the file COPYING.
 */

/*
 * Example usage of the Yapircl class. This will join a channel and output
 * the contents of the entire! LICENSE file to the channel. What this is 
 * demonstrating is the way commands are automatically buffered for you to 
 * avoid flooding of the server. Please do not use this to annoy people.
 */

require_once '../Yapircl.php';
require_once 'config.php';

set_time_limit (0);
error_reporting (E_ALL);

class Buftest extends Yapircl 
{

    function Buftest()
    {
        // call the parent constructor

        $this->Yapircl();
    }

    function run()
    {
        // main function

        global $CHANNEL;

        $this->join($CHANNEL);

        $fcontents = file('../LICENSE');
      
        // send the file

        foreach ($fcontents as $line) {
            $this->privmsg($CHANNEL, $line);
        }
 
        while ($this->connected()) {
            // stay connected 

            $this->idle();
            usleep($this->_resolution);
        }
    }
}

$buftest = new Buftest;
$buftest->setUser($USER, 'buftest', 'Yet Another PHP IRC Library', '+i');
$buftest->setServer($SERVER);
$buftest->connect();
$buftest->run();
?>
