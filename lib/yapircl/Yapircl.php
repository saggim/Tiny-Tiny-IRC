<?
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: /* 
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

/**
 * Yet Another PHP IRC Library.
 *
 * An instance of this class can connect to one IRC server and register the
 * user on the server. It will transparently stay connected, answer 
 * serverpings and buffer the commands you send as long as you call the 
 * idle() method to update the state of the Yapircl object. The class is 
 * used by sub-classing it and adding event_* callback funtions to your own
 * class. Several of the most regular messages are parsed into pieces you
 * can use within your callbacks.
 *
 * @author  Geir Torstein Kristiansen <gtk@linux.online.no>
 * @version 0.5.1
 * @access public
 * @package Yapircl
 */
class Yapircl 
{
    
    // these should be private

    var $_debug = true;     // debug level (bool)
   
    var $_name;             // user name (string)
    var $_wantnick;         // user wanted nick (string)
    var $_usednick;         // user nick that actually is used (string)
    var $_nickoff;          // the offset of the alternative nick suffix
    var $_realname;         // user real name (string)
    var $_mode;             // user mode (string)

    var $_server;           // server to connect to (string)

    var $_resolution;       // lower = higher cpu usage/better response (int)

    var $_prefs_timer_reg;  // wait n seconds for register response (int)
    var $_registered;       // registered on server (bool)
    var $_connected;        // connected to server (bool)

    var $_ircfp;            // file pointer to the socket
    var $_fline;            // a full line recieved from the server (string)
    var $_fline_strlen;     // the string lenght of fline (int)
    var $_xline;            // an exploded line separated by space (array)
    var $_xline_sizeof;     // the number of items in _xline (int)

    var $_totlines_rx;      // total lines recieved from server (int)
    var $_totlines_tx;      // total lines sent to server (int)
    var $_totbytes_rx;      // total bytes received from server (int)
    var $_totbytes_tx;      // total bytes sent to server (int)
   
    var $_conlines_rx;      // connected lines recieved from server (int)
    var $_conlines_tx;      // connected lines sent to server (int)
    var $_conbytes_rx;      // connected bytes received from server (int)
    var $_conbytes_tx;      // connected bytes sent to server (int)

    var $_uptime_total;     // timestamp since total uptime (int)
    var $_uptime_connected; // timestamp since connected to server (int)

    var $_pri;              // sendBuf() adds to buffer_pri when true (bool)
    var $_buffer_nor;       // normal buffer (array)
    var $_buffer_pri;       // priority buffer (array)
    var $_buffer_nextsend;  // timestamp of earliest allowed nextsend (int)

    var $_rxtimer;          // receive timer object (array)
    var $_prefs_timer_rx;   // ping timeout after n seconds (int)

    var $_servermodes;      // the supported servermodes (array)

    var $_event;            // active event

    // public variables 
    
    var $major  = 0;        // major version (int)
    var $minor  = 5;        // minor version (int)
    var $micro  = 1;        // micro version (int)

    // each privmsg/notice is parsed into these variables for convenience

    var $channels;          // lists of users in all channels (arrray)

    var $nick;              // nick the msg is from
    var $user;              // ident the msg is from
    var $host;              // host the msg is from
    var $mask;              // full usermask $nick . "!" . $ident . "@" . $host

    var $from;              // origin of a msg, either nick or channel
    var $word;              // first word of a msg
    var $rest;              // rest of the msg
    var $full;              // full msg "$word $rest"

    function Yapircl()
    {
        // initial state set by this constructor
 
        $this->_prefs_timer_reg     = 120;
        $this->_resolution          = 1; 
        $this->_prefs_timer_rx      = 600;
        $this->_uptime_total        = time();

        $this->_totlines_rx         = 0;
        $this->_totlines_tx         = 0;
        $this->_totbytes_rx         = 0;
        $this->_totbytes_tx         = 0;
    }

    function getVersion()
    {
        // return current Yapircl version string

        $uname = strtok(php_uname(),' ');

        $version = "Yapircl v" 
        . $this->major . "."
        . $this->minor . "."
        . $this->micro
        . " on " . $uname;

        return $version; 
    }

    function debug($string)
    {
        // debug output

        if ($this->_debug) {
            echo date('<H:i:s>') . " $string\n";
        }
    }

    function sTimerNew(&$stimer)
    {
        // simple timer is set to it's initial value
        
        $stimer['timestamp'] = time();
        $stimer['nrupdates'] = 0;

        $this->debug('sTimer started');
    }

    function sTimerUpdate(&$stimer)
    {
        // simple timer is updated

        $stimer['timestamp'] = time();
        $stimer['nrupdates'] += 1;

        $this->debug('sTimer updated');
    }

    function sTimerCheck(&$stimer, $limit)
    {
        // simple timer returns true if the timer has timed out

        if ((time() - $stimer['timestamp'] ) > $limit) {
            $this->debug("sTimer timed out after $limit seconds");
            return true;
        }
    }

    // container methods

    function setDebug($bool)
    {
        // set the debug level

        $this->_debug = $bool;
    }

    function setPri($bool)
    {
        // use the priority buffer when true, otherwise the normal buffer 
         
        $this->debug("setPri() buffer priority was set to $bool");
        $this->_pri = $bool;
    }

    function setUser($username, $nick, $realname, $mode)
    {
        // set user data
        
        $this->_name        = $username;
        $this->_wantnick    = $nick;
        $this->_realname    = $realname;
        $this->_mode        = $mode;
    }

    function setServer($server)
    {
        $this->_server = $server;
    }

    // functions to send irc commands 
	
    function nick($nick)
    {
        $this->sendBuf("NICK $nick");
    }

    function user($username, $hostname, $servername, $realname)
    {
        $this->sendBuf("USER $username $hostname $servername :$realname");
    }

    function quit($msg)
    {
        $this->sendBuf("QUIT :$msg");
    }

    function join($channel)
    {
        $this->sendBuf("JOIN $channel");
    }

    function part($channel)
    {
        $this->sendBuf("PART $channel");
    }

    function mode($channel, $mode, $limit, $nick, $mask)
    {
	    // stub for now
    }

    function usermode($target, $mode)
    {
        $this->sendBuf("MODE $target $mode");
    }

    function topic($channel, $topic)
    {
        $this->sendBuf("TOPIC $channel :$topic");
    }

    function invite($nick, $channel)
    {
        $this->sendBuf("INVITE $nick $channel");
    }

    function kick($channel, $nick, $comment = 'no comment')
    {
        $this->sendBuf("KICK $channel $nick :$comment");
    }

    function privmsg($target, $msg)
    {
        $this->sendBuf("PRIVMSG $target :$msg");
    }

    function notice($target, $msg)
    {
        $this->sendBuf("NOTICE $target :$msg");
    }

    function action($target, $msg)
    {
        $this->privmsg($target, "\001ACTION " . $msg . "\001");
    }

    function ctcp($target, $msg)
    {
        $this->notice($target, "\001" . $msg . "\001");
    }

    function pong($msg)
    {
        $this->sendNow("PONG $msg");
    }

    // end functions to send irc commands

    function sendNow($data) 
    {
        // send line directly to irc server unbuffered
    
        fputs($this->_ircfp, "$data\r\n", 512);
        $this->debug("sendNow() $data");
        
        // for statistics
        
        $len = strlen($data);

        $this->_totlines_tx++; 
        $this->_totbytes_tx += $len;
        $this->_conlines_tx++; 
        $this->_conbytes_tx += $len;
    }
    
    function sendBuf($data)
    {
        // put a line in the buffer, will be sent by _checkBuf() when possible
        // everything ends up in the buffer first as this simplifies the code 
        
        if ($this->_buffer_nextsend < time()) {
            $this->_buffer_nextsend = time();
        }

        // normal or priority buffer depending on the state of pri 

        if (!$this->_pri) {
            array_push($this->_buffer_nor, $data);
        } else {
            array_push($this->_buffer_pri, $data);
        }
    }

    function _checkBuf()
    {
        // try to send data in the buffer if any
  
        $norbsize = count($this->_buffer_nor);
        $pribsize = count($this->_buffer_pri);
  
        if ($norbsize != 0 || $pribsize != 0) {
            if ($this->_buffer_nextsend - time() >= 10) {
                return false;
            }

            $this->debug("buffer -> normal: $norbsize, priority: $pribsize");

            if (!$pribsize) {
                $data = array_shift($this->_buffer_nor);
            } else {
                $data = array_shift($this->_buffer_pri);
            }

            $this->_buffer_nextsend += 2 + strlen($data) / 120;
            $this->sendNow($data);
        }
    }

    function _receive()
    {
        // recieve line from irc server

        $this->_fline = fgets($this->_ircfp);
        
        // we don't want to print a debug message every time there is no data

        static $printwait = true;

        if (!empty($this->_fline)) {

            // for statistics
            $this->_fline = trim($this->_fline, " \n\r");
            $this->_fline_strlen = strlen($this->_fline);
            $this->_xline = explode(' ', $this->_fline);
            $this->_xline_sizeof = count($this->_xline);
            $this->_totlines_rx++;
            $this->_totbytes_rx += $this->_fline_strlen;
            $this->_conlines_rx++;
            $this->_conbytes_rx += $this->_fline_strlen;

            $this->debug("_receive() \"$this->_fline\"");
            $printwait = true;
            return true;
        } else {

            if ($printwait) {   
                
                // make sure we are not repeatedly responding to the same data

                $this->_fline = '';
                $this->_xline = '';

                $this->debug('_receive() waiting for data');   
                $printwait = false;
            }

            return false;
        }
    }

    function _event_ping()
    {
        // respond to server ping

        $this->debug('event_ping() sending pong');
        $this->pong($this->_xline[1]);
    }

    function _putUser(&$rawuser)
    {
        // set mask, nick, user and host

        $this->mask = ltrim($rawuser, ':');
        $this->nick = strtok($this->mask, '@');
        list($this->nick, $this->host) = explode('@', $this->mask, 2);
        list($this->nick, $this->user) = explode('!', $this->nick, 2);
    }

    function _putOrigin()
    {
        // set from

        if ($this->_xline[2] == $this->_usednick) {
            // was a private msg
            $this->from = $this->nick;
        } else {
            // was to a channel
            $this->from = $this->_xline[2];
        }
    }

    function _putMsg()
    {
        // set word, rest and full 

        $this->word = ltrim($this->_xline[3], ':');

        for ($i=4; $i < $this->_xline_sizeof; $i++) {
            $this->rest .=  ' ' . $this->_xline[$i];
        }

        $this->full = $this->word . $this->rest;
        $this->rest = ltrim($this->rest);
    }

    function _initPut()
    {
        // initalize all variables

        $this->nick = '';
        $this->user = '';
        $this->host = '';
        $this->mask = '';
        $this->from = '';
        $this->word = '';
        $this->rest = '';
        $this->full = '';
    }

    function _putAll()
    {
        $this->_initPut(); 
        $this->_putUser($this->_xline[0]);
        $this->_putOrigin();
        $this->_putMsg(); 
    }

    function _showPut()
    {
        $put = "nick = \"$this->nick\", "
        . "user = \"$this->user\", " 
        . "host = \"$this->host\", " 
        . "mask = \"$this->mask\", " 
        . "from = \"$this->from\", " 
        . "word = \"$this->word\", " 
        . "rest = \"$this->rest\", " 
        . "full = \"$this->full\"";
        
        $this->debug($put);
    }

    function _msgType()
    {
        // return the type of message, either public or private

        if ($this->_xline[2] == $this->_usednick) {
            return 'private';
        } else {
            return 'public';
        }
    }

    function _numEventToNamed(&$numeric)
    {
        // translate numeric events to event names
        
        static $events = array(
            251 => 'RPL_LUSERCLIENT',
            252 => 'RPL_LUSEROP',
            253 => 'RPL_LUSERUNKNOWN',
            254 => 'RPL_LUSERCHANNELS',
            255 => 'RPL_LUSERME',
            302 => 'RPL_USERHOST',
            303 => 'RPL_ISON',
            305 => 'RPL_UNAWAY',
            306 => 'RPL_NOWAWAY',
            311 => 'RPL_WHOISUSER',
            312 => 'RPL_WHOISSERVER',
            313 => 'RPL_WHOISOPERATOR',
            315 => 'RPL_ENDOFWHO',
            317 => 'RPL_WHOISIDLE',
            318 => 'RPL_ENDOFWHOIS',
            319 => 'RPL_WHOISCHANNELS',
            322 => 'RPL_LIST',
            331 => 'RPL_NOTOPIC',
            332 => 'RPL_TOPIC',
            333 => 'RPL_TOPIC_EXT',
            341 => 'RPL_INVITING',
            351 => 'RPL_VERSION',
            352 => 'RPL_WHOREPLY',
            353 => 'RPL_NAMREPLY',
            366 => 'RPL_ENDOFNAMES',
            367 => 'RPL_BANLIST',
            368 => 'RPL_ENDOFBANLIST',
            371 => 'RPL_INFO',
            372 => 'RPL_MOTD',
            375 => 'RPL_MOTDSTART',
            376 => 'RPL_ENDOFMOTD',
            391 => 'RPL_TIME',
            412 => 'ERR_NOTEXTTOSEND',
            422 => 'ERR_NOMOTD',
            433 => 'ERR_NICKNAMEINUSE',
            441 => 'ERR_USERNOTINCHANNEL',
            462 => 'ERR_ALREADYREGISTRED',
            474 => 'ERR_BANNEDFROMCHAN',
            482 => 'ERR_CHANOPRIVSNEEDED'
        );

        if (isset($events[$numeric])) {
            return $events[$numeric];
        } else {
            return $numeric;
        }
    }

    function _tryCallback($method)
    {
        // if a method exist, then call it
        
        if (method_exists($this, $method)) {
            $this->$method();
            return true;
        } else {
            $this->debug("callback method: \"$method()\" not provided");
            return false;
        }
    }

    function _toCallback()
    {
        // this is what routes events to different callbacks
                
        if ($this->_xline[0]{0} == ':' && strstr($this->_xline[0], "!")) {

            switch ($this->_xline[1]) {
            case 'PRIVMSG':
                if ($this->_xline[3]{1} == "\001") {
                    // ctcp message
                    $event = $this->_msgType() . '_ctcp_'
                    . trim($this->_xline[3], ":\001");
                    $this->_putAll();
                        
                    $this->full = trim($this->full, "\001");

                    if ($this->_xline[3] == ":\001ACTION") {
                        $offset = 1;
                    } else {
                        $offset = 0;
                    }
                        
                    $words = explode(' ', $this->full);
                    $this->word = $words[$offset];
                    $offset++;
                    $sizeof = count($words);
                    $this->rest = '';

                    for ($i=$offset; $i < $sizeof; $i++) {
                        $this->rest .= ' ' . $words[$i];
                    }

                    $this->full = $this->word . $this->rest;
                    $this->rest = ltrim($this->rest);

                } else {
                    // ordinary message
                    $event = $this->_msgType() . '_privmsg';
                    $this->_putAll();
                }
                break;
            case 'NOTICE':
                $event = $this->_msgType() . '_notice';
                $this->_putAll();
                break;
            default:
                $event =& $this->_xline[1];
            }

        } elseif ($this->_xline[0]{0} == ':') {
            
            $event =& $this->_xline[1];

            if (is_numeric($event)) {
                $event = $this->_numEventToNamed($event);
            }

        } else {
            $event =& $this->_xline[0];
        }

        $event = strtolower($event);

        $this->_event = $event;

        // check for method which is called BEFORE the built-in one
        $this->_tryCallback('event_before_' . $event);

        // first check for an internal _event_* callback
        $this->_tryCallback('_event_' . $event);
        // then check for a userscript event_* callback
        $this->_tryCallback('event_all', $event);
        $this->_tryCallback('event_' . $event);
    }

    function idle($usetimer = true)
    {
        // the main idle loop of the Yapircl object

        $this->_checkBuf();

        if ($this->_receive()) {
                if ($usetimer) {
                    $this->sTimerUpdate($this->_rxtimer);
                }
                $this->_toCallback();
        } else {
                // time out connection if no data is received for n seconds 

                if ($usetimer) {
                    if ($this->sTimerCheck($this->_rxtimer, $this->_prefs_timer_rx)) {
                        $this->debug('idle() ping timeout');
                        $this->_connected = false;
                    }
                }
        }
    }

    function _sockOpn()
    {
        // try to open a socket to a server

        $this->debug("_sockOpn() opening $this->_server");
        $this->_ircfp = fsockopen($this->_server, 6667, $errno, $errstr, 5);
    
        if (!$this->_ircfp) {
            $this->debug("_sockOpn() unable to open socket: $errno $errstr");
            return false;
        } else {
            $this->debug('_sockOpn() socket created');
            socket_set_blocking($this->_ircfp, false);
            return true;
        }
    }   

    function _sockDie()
    {
        // try to close an open socket

        if ($this->_ircfp) {
            $this->debug('_sockDie() socket closed');
            @fclose($this->_ircfp);
        }
    }

    function _registered()
    {
        // set the usermode after motd
        
        $this->_registered = true;
        $this->usermode($this->_usednick, $this->_mode);
    }

    function _event_rpl_endofmotd()
    {
        $this->debug("message of the day received");
        $this->_registered();
    }

    function _event_err_nomotd()
    {
        $this->debug("message of the day not found");
        $this->_registered();
    }

    function _send_userdata()
    {
        $this->nick($this->_usednick);
        $this->user($this->_name, '*', $this->_server, $this->_realname);
    }

    function _event_err_nicknameinuse()
    {
        $this->debug("nickname is already in use, trying alternative nick");
        $suffix = '-_^`\|{}[]123abc';
        $this->_usednick = $this->_wantnick . $suffix[$this->_nickoff]; 
        $this->_send_userdata(); 
        $this->_nickoff++;
    }

    function _regUser()
    {
        $this->_registered  = false;
        
        $this->_nickoff = 0;
        $this->_usednick = $this->_wantnick;
        $this->_send_userdata();

        $this->sTimerNew($regtimer);
       
        while (!$this->_registered) {
            $this->idle(false);

            if ($this->sTimerCheck($regtimer, $this->_prefs_timer_reg)) {
                $this->debug('_regUser() timeout no response');
                break 1;
            }

            usleep($this->_resolution);
        }

        return $this->_registered;
    }

    function _event_005()
    {
        // parse the supported servermodes into an associative array
        
        $i = 3;

        while ($i < $this->_xline_sizeof) {

            if (strpos($this->_xline[$i], ':') === 0) {
                break;
            }

            $values = explode('=', $this->_xline[$i]);
            $values[0] = strtolower($values[0]);
           
            if (!empty($values[0])) {
                if (!empty($values[1])) {
                    $this->_servermodes[$values[0]] = $values[1];
                } else {
                    $this->_servermodes[$values[0]] = null;
                }
            }
            $i++;
        }
    }

    // track nicks in the nicklist

    function _nicklist_add(&$channel, &$nick)
    {
        // add nicks to the nicklist with the modeflags
        
        $nicklist =& $this->channels[strtolower($channel)];

        switch ($nick[0]) {
        case '@':
            $nicklist[substr($nick, 1)] = array('o' => true, 'v' => false);
            break;
        case '+':
            $nicklist[substr($nick, 1)] = array('o' => false, 'v' => true);
            break;
        default:
            $nicklist[$nick] = array('o' => false, 'v' => false);
        }
    }

    function _event_rpl_namreply()
    {
        // add nicks to the nicklist

        $this->_nicklist_add($this->_xline[4], substr($this->_xline[5], 1));

        for ($n=6; $n < $this->_xline_sizeof; $n++) {
            $this->_nicklist_add($this->_xline[4], $this->_xline[$n]);
        }
    }

    function _event_join()
    {
        // add new nicks to the nicklist when they join

        $this->_putUser($this->_xline[0]);

        if ($this->_usednick != $this->nick) {
            $this->_nicklist_add(substr($this->_xline[2], 1), $this->nick);
        }
    }

    function _delNickOne(&$nick, &$channel)
    {
        // delete a nick from the nicklist 
        
        unset($this->channels[$channel][$nick]);
    } 

    function _delNickAll(&$nick)
    {
        // delete a nick from the nicklist of all channels

        foreach($this->channels as $key => $value) {
            $this->_delNickOne($nick, $key);
        }
    } 

    function _event_part()
    {
        // delete nicks from the nicklist of one channel when they part

        $this->_putUser($this->_xline[0]);

        if ($this->_usednick == $this->nick) {
            unset($this->channels[$this->_xline[2]]);
        } else {
            $this->_putUser($this->_xline[0]);
            $this->_delNickOne($this->nick, $this->_xline[2]);
        }
    }

    function _event_kick()
    {
        // delete nicks from the nicklist of one channel when they are kicked

        if ($this->_usednick == $this->_xline[3]) {
            unset($this->channels[$this->_xline[2]]);
        } else {
            $this->_delNickOne($this->_xline[3], $this->_xline[2]);
        }
    }
    
    // note that for quit and kill we don't remove nicks from $this->channels
    // if it is ourselves that quits or get killed because $this->channels
    // will then be reset anyway.
    
    function _event_quit()
    {
        // delete nicks from the nicklist of all channels when they quit

        $this->_putUser($this->_xline[0]);
        $this->_delNickAll($this->nick);
    }

    function _event_kill()
    {
        // delete nicks from the nicklist of all channels when they are killed

        $this->_delNickAll($this->_xline[2]);
    }

    function _event_nick()
    {
        // update the nick in the nicklist of all channels and preserve modes
    
        $this->_putUser($this->_xline[0]);
         
        foreach ($this->channels as $key => $value) {
            $new_key = ltrim($this->_xline[2], ':'); 
            $nicklist =& $this->channels[$key];
            $nicklist[$new_key] = $nicklist[$this->nick];
            unset($nicklist[$this->nick]);
        }
    }

    function _event_topic()
    {
        $this->_putUser($this->_xline[0]);
    }

    function _event_mode()
    {
        // update the modes on people in the nicklist 

        if ($this->_usednick == $this->_xline[2]) {
            return false;
        }

        $len = count($this->_xline[3]);
        $nicknr = 4;
        $nicklist =& $this->channels[strtolower($this->_xline[2])];

        for ($i=0; $i <= $len; $i++) {
            switch ($this->_xline[3]{$i}) {
            case '+':
                $type = true;
                break;
            case '-':
                $type = false;
                break;
            case 'o':
                $nick =& $nicklist[$this->_xline[$nicknr]];
                if ($type) {
                    $nick['o'] = true;
                } else {
                    $nick['o'] = false;
                }
                $nicknr++;
                break;
            case 'v':
                $nick =& $nicklist[$this->_xline[$nicknr]];
                if ($type) {
                    $nick['v'] = true;
                } else {
                    $nick['v'] = false;
                }
                $nicknr++;
                break;
            case 'b':
                $nicknr++;
            }
        }
    }

    function getNickList($channel, $ostr = '@', $vstr = '+')
    {
        // return an irc client like sorted nicklist, @nicks and +nicks first
        
        $nicklist =& $this->channels[strtolower($channel)];

        $oarr = array(); $varr = array(); $rarr = array();

        foreach ($nicklist as $key => $value) {
            if ($value['o']) {
                $oarr[] = $ostr . $key;
            } elseif ($value['v']) {
                $varr[] = $vstr . $key;
            } else {
                $rarr[] = $key;
            }
        }

        natcasesort($oarr); natcasesort($varr); natcasesort($rarr);
        return array_merge($oarr, $varr, $rarr);
    }

    function connect()
    {
        // connect and register user and nick on server

        $this->debug('connect() initiating');

        // initialize these for each server connection

        $this->_connected           = false;

        // statistics counters
        $this->_conlines_rx         = 0;
        $this->_conlines_tx         = 0;
        $this->_conbytes_rx         = 0;
        $this->_conbytes_tx         = 0;
        $this->_uptime_connected    = 0;

        // buffer stuff 
        $this->_pri                 = false;
        $this->_buffer_nor          = array();
        $this->_buffer_pri          = array();
        $this->_buffer_nextsend     = time() - 1;
        
        // misc
        $this->_servermodes         = array();
        $this->channels             = array();

        if ($this->_sockOpn()) {
            $this->_connected = true;
            if (!$this->_regUser()) {
                $this->_sockDie();
                $this->debug('connect() _regUser() failed');
                return false;
            } else {
                $this->debug('connect() registered');
                $this->_uptime_connected = time();
                $this->sTimerNew($this->_rxtimer);
                return true;
            }
        }
    }

    function connected()
    {
        // returns true if we are connected, otherwise false

        if ($this->_ircfp && @!feof($this->_ircfp) &&
            $this->_connected && $this->_registered) {
            return true;
        } else {
            $this->debug("connected() disconnected");
            $this->_sockDie();
            return false;
        }
    }
}
?>
