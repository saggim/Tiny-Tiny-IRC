#!/usr/bin/php
<?php
	require_once "config.php";
	require_once "functions.php";
	require_once "db.php";
	require_once "connection.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	init_connection($link);

	$connection_id = db_escape_string($argv[1]);

	$result = db_query($link, "SELECT * FROM ttirc_connections, ttirc_users
		WHERE ttirc_connections.id = $connection_id AND owner_uid = ttirc_users.id");

	if (db_num_rows($result) == 1) {

		$line = db_fetch_assoc($result);

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
			"Connecting to $server_str...", true);

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
		}
	}

	db_close($link);
?>
