<?php

require_once "config.php";
require_once "lib/yapircl/Yapircl.php";
require_once "db.php";
require_once "functions.php";

class Connection extends Yapircl {

	var $link = false;
	var $checkpoint = false;
	var $last_sent_id = false;
	var $connection_id = false;
	var $encoding = false;
	var $owner_uid = false;
	var $userhosts = false;

	function Connection($link, $connection_id, $encoding, $last_sent_id) {
		$this->Yapircl();

		$this->link = $link;
		$this->checkpoint = 0;
		$this->connection_id = $connection_id;
		$this->last_sent_id = $last_sent_id;
		$this->encoding = $encoding;
		$this->_resolution = 25000;
		$this->userhosts = array();

		$result = db_query($this->link, "SELECT owner_uid FROM ttirc_connections
			WHERE id = '$connection_id'");

		$this->owner_uid = db_fetch_result($result, 0, "owner_uid");
	}

	function handle_command($command, $arguments, $channel) {

		switch (strtolower($command)) {
			case "quote":
				$this->sendBuf($arguments);
				break;
			case "ping":
				$this->ping($arguments);
				break;
			case "whois":
				$this->whois($arguments);
				break;
			case "join":
				$this->join($arguments);
				break;
			case "msg":
				list ($nick, $message) = explode(" ", $arguments, 2);
				$this->privmsg($nick, $message);
				break;
			case "notice":
				list ($nick, $message) = explode(" ", $arguments, 2);
				$this->sendBuf("NOTICE $nick :$message");
				break;
			case "part":
				$this->part($arguments);
				break;
			case "action":
				$this->action($channel, $arguments);
				break;
			case "topic":
				$this->topic($channel, $arguments);
				break;
			case "nick":
				$this->nick($arguments);
				break;
			case "op":
				list ($nick, $on) = explode(" ", $arguments, 2);
				if (!$on) $on = $channel;
				//$this->sendBuf("MODE $on +o :$nick");
				$this->setmode($on, "+o", $nick);
				break;
			case "deop":
				list ($nick, $on) = explode(" ", $arguments, 2);
				if (!$on) $on = $channel;
				$this->setmode($on, "-o", $nick);
				break;
			case "voice":
				list ($nick, $on) = explode(" ", $arguments, 2);
				if (!$on) $on = $channel;
				//$this->sendBuf("MODE $on +o :$nick");
				$this->setmode($on, "+v", $nick);
				break;
			case "devoice":
				list ($nick, $on) = explode(" ", $arguments, 2);
				if (!$on) $on = $channel;
				$this->setmode($on, "-v", $nick);
				break;
			case "mode":
				list ($on, $mode, $nicks) = explode(" ", $arguments, 2);
				$this->setmode($on, $mode, $nicks);
				break;
			case "oper":
				$this->sendBuf("OPER $arguments");
				break;
			case "umode":
				$this->usermode($this->_usednick, $arguments);
				break;
			case "away":
				$this->sendBuf("AWAY :$arguments");

				if ($this->userhosts[$this->_usednick]) {
					$this->userhosts[$this->_usednick][5] = $this->to_utf($arguments);
					$this->update_userhosts();
				}

				break;
			default:
				$this->push_message('---', '---', "UNKNOWN_CMD:$command", MSGT_EVENT);
				break;
		}
	}

	function setmode($channel, $mode, $subject) {
		$this->sendBuf("MODE $channel $mode :$subject");
	}

	function check_messages() {
		$ts = time();

		if ($ts != $this->checkpoint) {
			$this->checkpoint = $ts;

			$result = db_query($this->link, "SELECT * FROM ttirc_messages
				WHERE incoming = false AND
					ts > ".get_interval_years(1)." AND
					connection_id = '".($this->connection_id)."' AND 
					id > ".($this->last_sent_id) . "  ORDER BY id");

			$tmp_id = $this->last_sent_id;

			while ($line = db_fetch_assoc($result)) {
				if ($line["id"] > $tmp_id) {
					$tmp_id = $line["id"];

					$message = iconv("UTF-8", $this->encoding, $line["message"]);
					$channel = iconv("UTF-8", $this->encoding, $line["channel"]);

					switch ($line["message_type"]) {
					case MSGT_PRIVMSG:
					case MSGT_PRIVATE_PRIVMSG:

						$msgs = explode("\n", wordwrap($message, 200, "\n"));

						foreach ($msgs as $msg) {
							$this->privmsg($channel, $msg);
						}

						break;
					case MSGT_COMMAND:
						list($cmd, $args) = explode(":", $message, 2);
						$this->handle_command($cmd, $args, $channel);
						break;
					default:
						_debug("unknown message type to send: " . $line["message_type"]);
						break;
					}
					
				}
			}

			if ($tmp_id != $this->last_sent_id) {
				$this->last_sent_id = $tmp_id;

				db_query($this->link, "UPDATE ttirc_connections SET last_sent_id = ".
					($this->last_sent_id)." WHERE id = ".
					($this->connection_id));
			}

		}
	}

	function update_nick() {

		$my_nick = db_escape_string($this->to_utf($this->_usednick));

		db_query($this->link, "UPDATE ttirc_connections SET active_nick = '$my_nick'
			WHERE id = " . $this->connection_id);
	}

	function run() {
		$this->push_message('---', '---', 'CONNECT', MSGT_EVENT);
		$this->join_channels();
		$this->update_nick();

		while ($this->connected()) {
			$this->disconnect_if_disabled();
			$this->check_messages();
			$this->idle();
			usleep($this->_resolution);
		}
	}

	function disconnect_if_disabled() {

		if ($this->connected()) {

			$result = db_query($this->link, 
				"SELECT enabled 
				FROM ttirc_connections, ttirc_users
				WHERE owner_uid = ttirc_users.id AND 
				(heartbeat > ".get_interval_minutes(15)." OR permanent = true) AND
				ttirc_connections.id = " . $this->connection_id);
	
			if (db_num_rows($result) != 1) {
				$this->quit($this->quit_message());
			} else {
				$enabled = sql_bool_to_bool(db_fetch_result($result, 0, "enabled"));
				if (!$enabled) {
					$this->quit($this->quit_message());
				}
			}
		}

	}

	function push_message($sender, $channel, $message, 
		$message_type = MSGT_PRIVMSG) {

		$message = $this->to_utf($message);
		$channel = $this->to_utf($channel);
		$sender = $this->to_utf($sender);
		
		$connection_id = $this->connection_id;

		$query = sprintf("INSERT INTO ttirc_messages (incoming,
			connection_id, message_type, sender, 
			channel, message) VALUES (true, %d, '%s', '%s', '%s', '%s')",
				$connection_id, $message_type,
				db_escape_string($sender), db_escape_string($channel), 
				db_escape_string($message));

		db_query($this->link, $query, false);
	}

	function event_private_notice() {

		if ($this->word == "\001PING") {
			$seconds = time() - trim($this->rest, "\001");

			$this->push_message($this->nick, 
				$this->from, "PING_REPLY:$seconds", MSGT_EVENT);
		} else {
			$notice = "";

			for ($i=3; $i < $this->_xline_sizeof; $i++) {
				$notice .=  ' ' . $this->_xline[$i];
			}
	
			$notice = substr(ltrim($notice), 1);

			$this->check_channel($this->nick, CT_PRIVATE);

			$this->push_message($this->nick, 
				$this->from, "NOTICE:$notice", MSGT_EVENT);
		}
	}

	function event_all() {
		if (!$this->registered) {
			if (!method_exists($this, 'event_' . $this->_event)) {

//				echo $this->_event . "\n";

				$this->push_message('---', '---', $this->_fline);
			}
		}
	}

	function event_ping() {
		// no-op
	}

	function event_join() {

		$channel = substr($this->_xline[2], 1);

		$message = sprintf("JOIN:%s:%s", $this->nick, 
			$this->user . '@' . $this->host);

		$this->push_message($this->nick, $channel, $message, MSGT_EVENT);

		$this->update_nicklist($channel);
		$this->request_userhost($this->nick);

	}

	function event_before_quit() {

		$this->_putUser($this->_xline[0]);

		$quit_msg = "";

		for ($i=3; $i < $this->_xline_sizeof; $i++) {
			$quit_msg .=  ' ' . $this->_xline[$i];
		}

		$quit_msg = ltrim($quit_msg);

		$message = sprintf("QUIT:%s", $quit_msg);

		foreach ($this->channels as $key => $value) {
			if ($this->channels[$key][$this->nick]) {
				$this->push_message($this->nick, $key, $message, MSGT_EVENT);
			}
		}

	}

	function event_quit() {
		$this->update_nicklist(false);
		$this->update_userhosts();

		unset($this->userhosts[$this->nick]);
	}

	function event_before_nick() {
		$this->_putUser($this->_xline[0]);

		$new_nick = ltrim($this->_xline[2], ':'); 

		foreach ($this->channels as $key => $value) {
			if ($this->channels[$key][$this->nick]) {
				$this->push_message($this->nick, $key, 
					"NICK:$new_nick", MSGT_EVENT);
			}
		}

	}


	function event_nick() {

		$new_nick = ltrim($this->_xline[2], ':'); 

		if ($this->nick == $this->_usednick) {
			$this->_usednick = $new_nick;
			$this->update_nick();
		}

		$this->userhosts[$new_nick] = $this->userhosts[$this->nick];

		unset($this->userhosts[$this->nick]);

		$old_nick_utf = $this->to_utf($this->nick);
		$new_nick_utf = $this->to_utf($new_nick);

		db_query($this->link, "UPDATE ttirc_channels SET
			channel = '$new_nick_utf' WHERE channel = '$old_nick_utf' AND
			chan_type = ".CT_PRIVATE." AND connection_id = " . $this->connection_id);

/*		db_query($this->link, "UPDATE ttirc_messages SET
			channel = '$new_nick_utf', sender = '$new_nick_utf' 
			WHERE channel = '$old_nick_utf' AND
			connection_id = " . $this->connection_id); */

		$this->update_nicklist(false);
		$this->update_userhosts();

	}

	function event_mode() {
		$this->_putUser($this->_xline[0]);

		$subject = "";

		for ($i=3; $i < $this->_xline_sizeof; $i++) {
			$subject .=  ' ' . $this->_xline[$i];
		}

		$subject = ltrim($subject);

		$message = sprintf("MODE:%s:%s", 
			$subject, $this->_xline[2]);

		$this->push_message($this->nick, $this->_xline[2], 
				$message, MSGT_EVENT);

		if (strpos($this->_xline[2], "#") === 0)
			$this->update_nicklist($this->_xline[2]);
	}

	function event_part() {

		$message = "";

		for ($i=3; $i < $this->_xline_sizeof; $i++) {
			$message .=  ' ' . $this->_xline[$i];
		}

		$message = substr(ltrim($message), 1);

		if ($this->nick == $this->_usednick) {
			$channel = $this->to_utf($this->_xline[2]);

			$result = db_query($this->link, "DELETE FROM ttirc_channels
				WHERE channel = '$channel' AND connection_id = " .
				$this->connection_id);
		} else {
			$message = sprintf("PART:%s:%s", $this->nick, $message);
			$this->push_message('---', $this->_xline[2], $message, MSGT_EVENT);
		}

		$this->update_nicklist($this->_xline[2]);
	}

	function event_kick() {

		$message = "";

		for ($i=4; $i < $this->_xline_sizeof; $i++) {
			$message .=  ' ' . $this->_xline[$i];
		}

		$message = substr(ltrim($message), 1);

		$message = "KICK:" . $this->_xline[3] . ":" . $message;

		if ($this->_usednick == $this->_xline[3]) {
			$channel = $this->to_utf($this->_xline[2]);

			$result = db_query($this->link, "DELETE FROM ttirc_channels
				WHERE channel = '$channel' AND connection_id = " .
				$this->connection_id);

			$this->push_message($this->nick, "---", 
				$message, MSGT_EVENT);
		} else {
			$this->push_message($this->nick, $this->_xline[2], 
				$message, MSGT_EVENT);
		}

		$this->update_nicklist($this->_xline[2]);
	}

	function event_public_privmsg() {
#		echo "<" . $this->nick . "> " . $this->full . "\n";
		$this->push_message($this->nick, $this->from, $this->full);
	}

	function event_private_privmsg() {
//		echo "[" . $this->nick . "(" . $this->user . 
//			"@" . $this->host . ")] " . $this->full . "\n"; 

		$this->check_channel($this->nick, CT_PRIVATE);

		$this->push_message($this->nick, $this->from, $this->full, 
			MSGT_PRIVATE_PRIVMSG);
	}

	function event_public_ctcp_action() {
		$this->push_message($this->nick, $this->from, $this->full, MSGT_ACTION);
	}

	function handle_ctcp_version() {

		$version_str = sprintf("Tiny Tiny IRC (%s) %s",
			VERSION, $this->getVersion());
		
		$this->ctcp($this->nick, "VERSION $version_str");
		//echo $this->mask . " requested CTCP VERSION from " . $this->from . "\n";
	}

	function handle_ctcp_ping() {
		$this->ctcp($this->nick, $this->full);
		//echo $this->mask . " requested CTCP PING from " . $this->from . "\n";

		$message = sprintf("PING:%s", 
			$this->rest);
		
		$this->push_message($this->from, '---', $message, MSGT_EVENT);

	}

	function event_public_ctcp_version() {
		$this->handle_ctcp_version(); 
	}
	
	function event_private_ctcp_version() {
		$this->handle_ctcp_version();
	}

	function event_public_ctcp_ping() {
		$this->handle_ctcp_ping();
	}

	function event_private_ctcp_ping() {
		$this->handle_ctcp_ping();
	}

	function check_channel($channel, $chan_type = CT_CHANNEL) {
		$connection_id = $this->connection_id;
		$channel = $this->to_utf($channel);

		db_query($this->link, "BEGIN");

		$result = db_query($this->link, "SELECT id FROM ttirc_channels
			WHERE channel = '$channel' AND connection_id = '$connection_id'");

		if (db_num_rows($result) == 0) {
			db_query($this->link, "INSERT INTO ttirc_channels 
				(channel, connection_id, chan_type) 
					VALUES ('$channel', '$connection_id', '$chan_type')");
		}

		db_query($this->link, "COMMIT");
	}

	function event_topic() {

		$topic = "";

		for ($i=3; $i < $this->_xline_sizeof; $i++) {
			$topic .=  ' ' . $this->_xline[$i];
		}

		$topic = substr(ltrim($topic), 1);

		$this->set_topic($this->_xline[2], $topic, $this->nick, time());

		$this->push_message($this->nick, $this->_xline[2], 
			"TOPIC:$topic", MSGT_EVENT);
	}


	function set_topic($channel, $topic, $owner, $set_at) {
		$channel = db_escape_string($this->to_utf($channel));
		$topic = db_escape_string($this->to_utf($topic));
		$owner = db_escape_string($this->to_utf($owner));
		$set_at = db_escape_string($this->to_utf($set_at));

		$parts = array();

		if ($topic) array_push($parts, "topic = '$topic'");
		if ($owner) array_push($parts, "topic_owner = '$owner'");
		if ($set_at) array_push($parts, "topic_set = '".date("r", $set_at)."'");

		db_query($this->link, "UPDATE ttirc_channels SET ".
			join(", ", $parts) . " WHERE 
			channel = '$channel' AND connection_id = " .
			$this->connection_id);
	}

	function event_rpl_unaway() {
		$nick = $this->_xline[2];

		if ($this->userhosts[$nick]) {
			$this->userhosts[$nick][4] = false;
			$this->update_userhosts();
		}
	}

	function event_rpl_awayreason() {
		$nick = $this->_xline[2];

		if ($this->userhosts[$nick]) {

			$away_reason = "";

			for ($i=4; $i < $this->_xline_sizeof; $i++) {
				$away_reason .=  ' ' . $this->_xline[$i];
			}

			$away_reason = substr(ltrim($away_reason), 1);

			$this->userhosts[$nick][5] = $this->to_utf($away_reason);
			$this->update_userhosts();
		}
	}

	function event_rpl_nowaway() {
		$nick = $this->_xline[2];

		if ($this->userhosts[$nick]) {
			$this->userhosts[$nick][4] = true;
			$this->update_userhosts();
		}
	}

	function event_rpl_endofnames() {
		$this->check_channel($this->_xline[3]);

		$nicklist = $this->channels[$this->_xline[3]];

//		$this->push_message('---', $this->_xline[3], 
//			__('You have joined the channel.'));

		$this->update_nicklist($this->_xline[3]);
		$this->request_userhost();

	}

	function request_userhost($check_nick = false) {

		$nicks = array();

		if (!$check_nick) {
			foreach (array_keys($this->channels) as $chan) {

//				print_r($this->channels);

				if (is_array($this->channels[$chan])) {
					foreach (array_keys($this->channels[$chan]) as $nick) {
						if (!array_key_exists($nick, $nicks)) {
							array_push($nicks, $nick);
						}
					}
				}
			}

		} else {
			array_push($nicks, $check_nick);
		}

		foreach ($nicks as $nick) {
			if (!$this->userhosts[$nick]) {
				//echo "[*] requesting userhost for $nick...\n";
				$this->userhosts[$nick] = array('', '', '', '', time());
				$this->sendBuf("WHO :$nick");
			}
		}
	}

	function event_rpl_endofwho() {
		// no-op
	}

	function event_rpl_whoreply() {
		//print_r($this->_xline);

		$nick = $this->_xline[7];

		$realname = "";

		for ($i=10; $i < $this->_xline_sizeof; $i++) {
			$realname .=  ' ' . $this->_xline[$i];
		}

		$realname = ltrim($realname);

		$this->userhosts[$nick] = array(
			$this->to_utf($this->_xline[4]),
			$this->to_utf($this->_xline[5]), 
			$this->to_utf($this->_xline[6]), 
			$this->to_utf($realname),
			false,						/* away true/false */
			'');							/* away reason */

		$this->update_userhosts();
	}

	function update_userhosts() {
		$tmp = array();

		foreach (array_keys($this->userhosts) as $nick) {
			$tmp[$this->to_utf($nick)] = $this->userhosts[$nick];
		}

		$tmp = db_escape_string(json_encode($tmp));

		db_query($this->link, "UPDATE ttirc_connections SET userhosts = '$tmp'
			WHERE id = " . $this->connection_id);
	}

	function event_rpl_topic() {
		$this->check_channel($this->_xline[3]);

		$topic = "";

		for ($i=4; $i < $this->_xline_sizeof; $i++) {
			$topic .=  ' ' . $this->_xline[$i];
		}

		$topic = substr(ltrim($topic), 1);

		$this->set_topic($this->_xline[3], $topic, false, false);

	}

	function event_rpl_topic_ext() {
		$this->set_topic(false, false, $this->_xline[4], $this->_xline[5]);
	}

	function to_utf($text) {
		return iconv($this->encoding, "utf-8", $text);
	}

	function from_utf($text) {
		return iconv("utf-8", $this->encoding, $text);
	}

	function join_channels() {
		$result = db_query($this->link, "SELECT autojoin FROM ttirc_connections
			WHERE id = " . $this->connection_id);

		$autojoin = explode(",", db_fetch_result($result, 0, "autojoin"));

		foreach ($autojoin as $chan) {
			if (!array_key_exists($chan, $this->channels)) {
				$this->join($chan);
			}
		}

		$result = db_query($this->link, "SELECT channel FROM ttirc_channels
			WHERE chan_type = ".CT_CHANNEL." AND 
			connection_id = " . $this->connection_id);

		while ($line = db_fetch_assoc($result)) {
			if (!array_key_exists($line['channel'], $this->channels)) {
				$this->join($line['channel']);
			}
		}

	}

	function get_unicode_nicklist($channel) {
		$nicks = $this->getNickList($channel);

		$tmp = array();

		if (is_array($nicks)) {
			foreach ($nicks as $nick) {
				array_push($tmp, $this->to_utf($nick));
			}
		}
		return $tmp;
	}

	function quit_message() {
		$result = db_query($this->link, "SELECT quit_message FROM
			ttirc_users, ttirc_connections WHERE
			ttirc_users.id = owner_uid AND ttirc_connections.id = ".
			$this->connection_id);

		if (db_num_rows($result) == 1) {
			return $this->from_utf(db_fetch_result($result, 0, "quit_message"));
		} else {
			return __("Tiny Tiny IRC");
		}
	}

   function whois($user) {
		$this->sendBuf("WHOIS :$user");
	}

   function ping($target) {
		$this->privmsg($target, "\001PING ".time()."\001");
	}

	function update_nicklist($channel) {

		$channel = strtolower($channel);

		if ($channel) {

			$nicklist = db_escape_string(json_encode(
				$this->get_unicode_nicklist($channel)));
			$channel = db_escape_string($this->to_utf($channel));

			db_query($this->link, "UPDATE ttirc_channels SET nicklist = '$nicklist'
				WHERE channel = '$channel' AND connection_id = " . 
				$this->connection_id);
		} else {
			foreach (array_keys($this->channels) as $chan) {
				$nicklist = db_escape_string(json_encode(
					$this->get_unicode_nicklist($chan)));
				$channel = db_escape_string($chan);

				db_query($this->link, "UPDATE ttirc_channels SET nicklist = '$nicklist'
					WHERE channel = '$channel' AND
					chan_type = '".CT_CHANNEL."' AND
					connection_id = " . $this->connection_id);
			}
		}
	}
}


