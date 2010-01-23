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

	function Connection($link, $connection_id, $encoding, $last_sent_id) {
		$this->Yapircl();

		$this->link = $link;
		$this->checkpoint = 0;
		$this->connection_id = $connection_id;
		$this->last_sent_id = $last_sent_id;
		$this->encoding = $encoding;
		$this->_resolution = 5000;
	}

	function handle_command($command, $arguments, $channel) {

		switch (strtolower($command)) {
			case "join":
				$this->join($arguments);
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
		}
	}

	function check_messages() {
		$ts = time();

		if ($ts != $this->checkpoint) {
			$this->checkpoint = $ts;

			$result = db_query($this->link, "SELECT * FROM ttirc_messages
				WHERE incoming = false AND
					ts > NOW() - INTERVAL '1 year' AND
					connection_id = '".($this->connection_id)."' AND 
					id > ".($this->last_sent_id) . "  ORDER BY id");

			$tmp_id = $this->last_sent_id;

			while ($line = db_fetch_assoc($result)) {
				if ($line["id"] > $tmp_id) {
					$tmp_id = $line["id"];

					$message = iconv("UTF-8", $this->encoding, $line["message"]);

					switch ($line["message_type"]) {
						case 0:
							$this->privmsg($line["channel"], $message);
							break;
						case 1:
							echo "CMD $message\n";
							list($cmd, $args) = explode(":", $message, 2);
							$this->handle_command($cmd, $args, $line["channel"]);
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
		$this->push_message('---', '---', 'Connection established.');
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
				(heartbeat > NOW() - INTERVAL '10 minutes' OR permanent = true) AND
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

	function event_all() {
		if (!$this->registered) {
			if (!method_exists($this, 'event_' . $this->_event)) {
				$this->push_message('---', '---', $this->_fline, 0);
			}
		}
	}

	function event_ping() {
		// no-op
	}

	function event_join() {
		$this->update_nicklist($this->from);
	}

	function event_quit() {
		$this->update_nicklist($this->from);
	}

	function event_nick() {

		$new_nick = ltrim($this->_xline[2], ':'); 

		if ($this->nick == $this->_usednick) {
			$this->_usednick = $new_nick;
			$this->update_nick();
		}

		$message = sprintf("%s is now known as %s", $this->nick, $new_nick);

		$this->push_message('---', '---', $message, MSGT_BROADCAST);

		$this->update_nicklist(false);
	}

	function event_part() {

		if ($this->nick == $this->_usednick) {
			$channel = $this->to_utf($this->_xline[2]);

			$result = db_query($this->link, "DELETE FROM ttirc_channels
				WHERE channel = '$channel' AND connection_id = " .
				$this->connection_id);
		}

		$this->update_nicklist($this->from);
	}

	function event_kick() {
		$this->update_nicklist($this->from);
	}

	function event_public_privmsg() {
#		echo "<" . $this->nick . "> " . $this->full . "\n";
		$this->push_message($this->nick, $this->from, $this->full);
	}

	function event_private_privmsg() {
//		echo "[" . $this->nick . "(" . $this->user . 
//			"@" . $this->host . ")] " . $this->full . "\n"; 

		$this->push_message($this->nick, $this->from, $this->full);
	}

	function event_public_ctcp_action() {
		$this->push_message($this->nick, $this->from, $this->full, MSGT_ACTION);
	}

	function handle_ctcp_version() {
		$this->ctcp($this->nick, "VERSION " . $this->getVersion());
		echo $this->mask . " requested CTCP VERSION from " . $this->from . "\n";
	}

	function handle_ctcp_ping() {
		$this->ctcp($this->nick, $this->full);
		echo $this->mask . " requested CTCP PING from " . $this->from . "\n";
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

	function check_channel($channel) {
		$connection_id = $this->connection_id;

		db_query($this->link, "BEGIN");

		$result = db_query($this->link, "SELECT id FROM ttirc_channels
			WHERE channel = '$channel' AND connection_id = '$connection_id'");

		if (db_num_rows($result) == 0) {
			db_query($this->link, "INSERT INTO ttirc_channels 
				(channel, connection_id) VALUES ('$channel', '$connection_id')");
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
			$topic, MSGT_TOPIC);
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

	function event_rpl_endofnames() {
		$this->check_channel($this->_xline[3]);

		$nicklist = $this->channels[$this->_xline[3]];

		$this->push_message('---', $this->_xline[3], 
			__('You have joined the channel.'));

		$this->update_nicklist($this->_xline[3]);
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

	function join_channels() {
		$result = db_query($this->link, "SELECT autojoin FROM ttirc_connections
			WHERE id = " . $this->connection_id);

		$autojoin = explode(",", db_fetch_result($result, 0, "autojoin"));

		foreach ($autojoin as $chan) {
			if (!array_key_exists($chan, $this->channels)) {
				$this->join($chan);
			}
		}
	}

	function get_unicode_nicklist($channel) {
		$nicks = $this->getNickList($channel);

		$tmp = array();

		foreach ($nicks as $nick) {
			array_push($tmp, $this->to_utf($nick));
		}

		return $tmp;
	}

	function quit_message() {
		$result = db_query($this->link, "SELECT quit_message FROM
			ttirc_users, ttirc_connections WHERE
			ttirc_users.id = owner_uid AND ttirc_connections.id = ".
			$this->connection_id);

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "quit_message");
		} else {
			return __("Tiny Tiny IRC");
		}
	}

	function update_nicklist($channel) {
		if ($channel) {

			$nicklist = db_escape_string(json_encode(
				$this->get_unicode_nicklist($channel)));
			$channel = db_escape_string($channel);


			db_query($this->link, "UPDATE ttirc_channels SET nicklist = '$nicklist'
				WHERE channel = '$channel' AND connection_id = " . 
				$this->connection_id);
		} else {
			foreach (array_keys($this->channels) as $chan) {
				$nicklist = db_escape_string(json_encode(
					$this->get_unicode_nicklist($chan)));
				$channel = db_escape_string($chan);

				db_query($this->link, "UPDATE ttirc_channels SET nicklist = '$nicklist'
					WHERE channel = '$channel' AND connection_id = " . 
					$this->connection_id);
			}
		}
	}
}


