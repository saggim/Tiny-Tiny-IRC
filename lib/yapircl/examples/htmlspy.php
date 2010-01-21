#!/usr/bin/php4 -q
<?

/*
 * vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4:
 * (C) 2002 by Geir Torstein Kristiansen <gtk@linux.online.no> 
 * This code is released under the GNU GPL. See the file COPYING.
 */

/*
 * This demonstration of the yapircl class provides an html window into the
 * current state of an irc channel mimicing the default look of the console 
 * irc client irssi. Note that this is a quick hack. 
 */

// AND YES, THIS IS UGLY CODE!

require_once '../Yapircl.php';
require_once 'config.php';

set_time_limit (0);
error_reporting (E_ALL);

class HtmlSpy extends Yapircl 
{
    
    var $chanbuf;       // the channel buffer
    
    function HtmlSpy()
    {
        // call the parent constructor

        $this->Yapircl();
        $this->chanbuf = array();
    }
 
    function htmlHeader()
    {
        return "<html>
        <head>
        <meta http-equiv=refresh content=\"10\">
        </head>
        <br>
        <body bgcolor=\"#000000\" text=\"ACAAAC\">

        <table height=\"400\" width=\"600\" align=\"center\"><tr><td>

        <table cellpadding=\"0\" cellspacing=\"1\" width=\"100%\" bgcolor=\"#202020\"><tr><td>
        <table height=\"400\" width=\"100%\" bgcolor=\"#000000\" border=\"0\">
        <tr><td width=\"100%\"><iframe align=\"top\" scrolling=\"yes\" frameborder=\"0\" height=\"100%\" width=\"100%\" src=\"chanbuf.html\"></iframe>
        </td>
        <td width=\"140\"><iframe align=\"top\" scrolling=\"yes\" frameborder=\"0\" height=\"100%\" width=\"140\" src=\"nicklist.html\"></iframe></td></tr>
        </table>
        </td></tr></table>";
    }

    function htmlBottom()
    {
        global $CHANNEL;
        $var = "<table width=\"100%\" bgcolor=\"#0000CD\"><tr><td>";
        $var .= "<font color=\"#00CECD\">[</font>";
        $var .= date('H:i');
        $var .= "<font color=\"#OOCECED\">]</font><font color=\"#00CECD\">[</font>";
        $var .= $CHANNEL;
        $var .= "(<font color=\"#00CECD\">+</font>nst)<font color=\"#00CECD\">]</font></td></tr></body></html>";
        return $var;
    }

    function MiniHtmlHeader()
    {
        return "<html><body bgcolor=\"#000000\" text=\"ACAAAC\">";
    }

    function MiniHtmlBottom()
    {
        return "</body></html>";
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
            
            // AND NOTE THAT IT IS INCREDIBLY STUPID TO WRITE THESE FILES
            // SO OFTEN. AND WE DON'T CARE ABOUT WARNINGS EITHER DO WE :)
            
            $indexfp = fopen('htmlspy.html', 'w');
            fputs($indexfp, $this->htmlHeader());
            fputs($indexfp, $this->htmlBottom());
            fclose($indexfp);
            
            while (count($this->chanbuf) > 15) {
                array_shift($this->chanbuf);
            }

            $chanbuffp = fopen('chanbuf.html', 'w');
            fputs($chanbuffp, $this->MiniHtmlHeader());
            foreach ($this->chanbuf as $value) {
                fputs($chanbuffp, $value);
            }
            fputs($chanbuffp, $this->MiniHtmlBottom());
            fclose($chanbuffp);
            
            $nicklistfp = fopen('nicklist.html', 'w');
            fputs($nicklistfp, $this->MiniHtmlHeader());
            foreach ($this->getNickList($CHANNEL, "<b>@</b>", "<b>+</b>") as $value) {
                fputs($nicklistfp, $value . "<br>");
            }
            fputs($nicklistfp, $this->MiniHtmlBottom());
            fclose($nicklistfp);
            
        }
    }

    function event_public_privmsg()
    {
        $this->chanbuf[] = "&lt;" . $this->nick . "&gt; " . htmlspecialchars($this->full) . "<br>";
    }

    function event_private_privmsg()
    {
        $this->chanbuf[] = "[" . $this->nick . "(" . $this->user 
        . "@" . $this->host . ")] " . htmlspecialchars($this->full) . "<br>"; 
    }

    function event_public_ctcp_action()
    {
        $this->chanbuf[] = "*" . $this->nick . "* " . htmlspecialchars($this->full) . "<br>";
    }
}

$htmlspy = new HtmlSpy;
$htmlspy->setDebug(false);
$htmlspy->setUser($USER, 'shmerlock', 'Yet Another PHP IRC Library', '+i');
$htmlspy->setServer($SERVER);
$htmlspy->connect();
$htmlspy->run();
?>
