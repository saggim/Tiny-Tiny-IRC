<?php

require_once "config.php";
require_once "lib/yapircl/Yapircl.php";
require_once "db.php";

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

	function handle_command($command, $arguments) {

		switch (strtolower($command)) {
			case "join":
				$this->join($arguments);
				break;
			case "part":
				$this->part($arguments);
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
							$this->privmsg($line["destination"], $message);
							break;
						case 1:
							echo "CMD $message\n";
							list($cmd, $args) = explode(":", $message, 2);
							$this->handle_command($cmd, $args);
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
		$this->push_message('-IRC-', '---', 'Connection established.');
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
		$result = db_query($this->link, "SELECT enabled FROM ttirc_connections
			WHERE id = " . $this->connection_id);

		if (db_num_rows($result) != 1) {
			$this->quit("Tiny Tiny IRC");
		} else {
			$enabled = sql_bool_to_bool(db_fetch_result($result, 0, "enabled"));
			if (!$enabled) {
				$this->quit("Tiny Tiny IRC");
			}
		}

	}

	function push_message($sender, $destination, $message) {

		#FIXME convert sender/dest to unicode
		$message = $this->to_utf($message);
		$destination = $this->to_utf($destination);
		$sender = $this->to_utf($sender);

		$connection_id = $this->connection_id;

		db_query($this->link, "BEGIN");

		$result = db_query($this->link, "SELECT id FROM ttirc_destinations
			WHERE destination = '$destination' AND connection_id = '$connection_id'");

		if (db_num_rows($result) == 0) {
			db_query($this->link, "INSERT INTO ttirc_destinations 
				(destination, connection_id) VALUES ('$destination', '$connection_id')");
		}

		db_query($this->link, "COMMIT");

		$query = sprintf("INSERT INTO ttirc_messages (incoming,
			connection_id, sender, 
			destination, message) VALUES (true, %d, '%s', '%s', '%s')",
			$connection_id,
			db_escape_string($sender), db_escape_string($destination), 
			db_escape_string($message));

		db_query($this->link, $query, false);
	}

	function event_join() {
		$this->update_nicklist($this->from);
	}

	function event_nick() {

		if ($this->nick == $this->_usednick) {
			$new_nick = ltrim($this->_xline[2], ':'); 
			$this->_usednick = $new_nick;
			$this->update_nick();
		}

//		echo sprintf("NICKCH %s -- %s -- %s\n", $this->_usednick,
//			$this->_xline[0], $this->_xline[2]);

		$this->update_nicklist(false);
	}

	function event_part() {

		if ($this->nick == $this->_usednick) {
			$destination = $this->to_utf($this->_xline[2]);

			$result = db_query($this->link, "DELETE FROM ttirc_destinations
				WHERE destination = '$destination' AND connection_id = " .
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
		$this->push_message($this->nick, $this->from, $this->full);
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

	function event_rpl_endofnames() {
		$nicklist = $this->channels[$this->_xline[3]];

		$this->push_message('-IRC-', $this->_xline[3], 
			__('You have joined the channel.'));

		$this->update_nicklist($this->_xline[3]);

	}

	function to_utf($text) {
		return iconv($this->encoding, "utf-8", $text);
	}

	function join_channels() {
		$result = db_query($this->link, "SELECT destination FROM ttirc_preset_destinations
			WHERE connection_id = " . $this->connection_id);

		while ($line = db_fetch_assoc($result)) {
			if (!array_key_exists($line["destination"], $this->channels)) {
				$this->join($line["destination"]);
			}
		}
	}

	function get_unicode_nicklist($destination) {
		$nicks = $this->getNickList($destination);

		$tmp = array();

		foreach ($nicks as $nick) {
			array_push($tmp, $this->to_utf($nick));
		}

		return $tmp;
	}

	function update_nicklist($destination) {
		if ($destination) {

			$nicklist = db_escape_string(json_encode(
				$this->get_unicode_nicklist($destination)));
			$destination = db_escape_string($destination);


			db_query($this->link, "UPDATE ttirc_destinations SET nicklist = '$nicklist'
				WHERE destination = '$destination' AND connection_id = " . 
				$this->connection_id);
		} else {
			foreach (array_keys($this->channels) as $chan) {
				$nicklist = db_escape_string(json_encode(
					$this->get_unicode_nicklist($chan)));
				$destination = db_escape_string($chan);

				db_query($this->link, "UPDATE ttirc_destinations SET nicklist = '$nicklist'
					WHERE destination = '$destination' AND connection_id = " . 
					$this->connection_id);
			}
		}
	}
}


