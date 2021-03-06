#!/usr/bin/php
<?php
	require_once "config.php";
	require_once "functions.php";
	require_once "db.php";
	require_once "connection.php";

	$connection_id = db_escape_string($argv[1]);
	$lock_file_name = "handle.conn-$connection_id.lock";

	if (file_is_locked($lock_file_name)) {
		if (!$lock_handle) {
			die("error: Can't create lockfile [$lock_file_name]. ".
				"Maybe another daemon is already running.\n");
		}
	} else {
		$lock_handle = make_lockfile($lock_file_name);

		if (!$lock_handle) {
			die("error: Can't create lockfile [$lock_file_name]. ".
				"Maybe another daemon is already running.\n");
		}
	}

	$link = db_reconnect($link, DB_HOST, DB_USER, DB_PASS, DB_NAME);

	init_connection($link);

	$result = db_query($link, "SELECT *,
		ttirc_connections.nick AS local_nick, ttirc_users.nick AS nick
		FROM ttirc_connections, ttirc_users
		WHERE ttirc_connections.id = $connection_id AND owner_uid = ttirc_users.id");

	if (db_num_rows($result) == 1) {

		$line = db_fetch_assoc($result);

		foreach (array_keys($line) as $k) {
			$line[$k] = iconv("UTF-8", $line['encoding'], $line[$k]);
		}

		if (!sql_bool_to_bool($line['enabled'])) {
			debug("[$connection_id] connection disabled, aborting");
			return;
		}

		if ($line['local_nick']) $line['nick'] = $line['local_nick'];

		$server = get_random_server($link, $connection_id);

		if (!$server) {
			_debug("[$connection_id] couldn't find any servers :(");
	
			push_message($link, $connection_id, "---", 
				"Couldn't find a server to connect to.", true);

			db_query($link, "UPDATE ttirc_connections SET enabled = false, 
				auto_connect = false WHERE id = '$connection_id'");

			return;
		}

		_debug("[$connection_id] connecting to server " . $server["server"]);

		$server_str = db_escape_string($server["server"] . ":" . $server["port"]);

		push_message($link, $connection_id, "---", 
			"CONNECTING:$server_str...", true, MSGT_EVENT);

		$connection = new Connection($link, $connection_id, $line["encoding"], 
			$line["last_sent_id"]);
		$connection->setDebug(false);
		$connection->setUser($line["email"], $line['nick'], 
			$line['realname'], '+i');
		$connection->setServer($server["server"], $server["port"]);
	
		if ($connection->connect()) {
			_debug("[$connection_id] connection established.");

			db_query($link, "UPDATE ttirc_connections SET
				status = 2, active_server = '$server_str' WHERE id = '$connection_id'");

			$connection->run();
		} else {
			_debug("[$connection_id] connection error.");

			push_message($link, $connection_id, "---", 
				"Could not connect to server.", true);
		}
	}

	unlink(LOCK_DIRECTORY . "/$lock_file_name");

	db_close($link);
?>
