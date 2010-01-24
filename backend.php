<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	require_once "sessions.php";
	require_once "db-prefs.php";
	require_once "functions.php"; 
	require_once "sanity_check.php";
	require_once "version.php"; 
	require_once "config.php";
	require_once "prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	$dt_add = get_script_dt_add();

	no_cache_incantation();

	header('Content-Type: text/html; charset=utf-8');

	if (!$_SESSION["uid"]) {
		print json_encode(array("error" => 6));
		return;
	}

	if (!sanity_check($link)) { return; }

	$op = $_REQUEST["op"];

	update_heartbeat($link);

	switch ($op) {
	case "part-channel":
		$last_id = (int) db_escape_string($_REQUEST["last_id"]);
		$chan = db_escape_string($_REQUEST["chan"]);
		$connection_id = db_escape_string($_REQUEST["connection"]);

		if ($chan && valid_connection($link, $connection_id)) {
			handle_command($link, $connection_id, $chan, "/part");
		}

		$lines = get_new_lines($link, $last_id);
		$conn = get_conn_info($link);
		$chandata = get_chan_data($link, false);

		print json_encode(array($conn, $lines, $chandata));
		break;
	case "query-user":
		$nick = db_escape_string(trim($_REQUEST["nick"]));
		$last_id = (int) db_escape_string($_REQUEST["last_id"]);
		$connection_id = db_escape_string($_REQUEST["connection"]);

		if ($nick && valid_connection($link, $connection_id)) {
			handle_command($link, $connection_id, $chan, "/query $nick");
		}

		$lines = get_new_lines($link, $last_id);
		$conn = get_conn_info($link);
		$chandata = get_chan_data($link, false);

		print json_encode(array($conn, $lines, $chandata));
		break;

	case "send":
		$message = db_escape_string(trim($_REQUEST["message"]));
		$last_id = (int) db_escape_string($_REQUEST["last_id"]);
		$chan = db_escape_string($_REQUEST["chan"]);
		$connection_id = db_escape_string($_REQUEST["connection"]);

		if ($message && valid_connection($link, $connection_id)) {
			if (strpos($message, "/") === 0) {
				handle_command($link, $connection_id, $chan, $message);
			} else {
				push_message($link, $connection_id, $chan, $message);
			}
		}

		$lines = get_new_lines($link, $last_id);
		$conn = get_conn_info($link);
		$chandata = get_chan_data($link, false);

		print json_encode(array($conn, $lines, $chandata));
		break;
	case "update":
		$last_id = (int) db_escape_string($_REQUEST["last_id"]);

		$lines = get_new_lines($link, $last_id);
		$conn = get_conn_info($link);
		$chandata = get_chan_data($link, false);

		print json_encode(array($conn, $lines, $chandata));

		break;

	case "set-topic":
		$last_id = (int) db_escape_string($_REQUEST["last_id"]);
		$topic = db_escape_string($_REQUEST["topic"]);
		$chan = db_escape_string($_REQUEST["chan"]);
		$connection_id = db_escape_string($_REQUEST["connection"]);

		if ($topic) {
			handle_command($link, $connection_id, $chan, "/topic $topic");
		}

		$lines = get_new_lines($link, $last_id);
		$conn = get_conn_info($link);
		$chandata = get_chan_data($link, false);

		print json_encode(array($conn, $lines, $chandata));

		break;

	case "init":
		$result = db_query($link, "SELECT MAX(ttirc_messages.id) AS max_id
			FROM ttirc_messages, ttirc_connections
			WHERE connection_id = ttirc_connections.id AND owner_uid = " . $_SESSION["uid"]);

		$rv = array();

		if (db_num_rows($result) != 0) {
			$rv["max_id"] = db_fetch_result($result, 0, "max_id");
		} else {
			$rv["max_id"] = 0;
		}

		print json_encode($rv);

		break;
	case "prefs":
		main_prefs($link);
		break;
	case "prefs-edit-con":
		$connection_id = (int) db_escape_string($_REQUEST["id"]);
		connection_editor($link, $connection_id);
		break;

	case "create-server":
		$connection_id = (int) db_escape_string($_REQUEST["connection_id"]);
		list($server, $port) = explode(":", db_escape_string($_REQUEST["data"]));

		if (valid_connection($link, $connection_id)) {
			if ($server && $port) {
				db_query($link, "INSERT INTO ttirc_servers (server, port, connection_id)
					VALUES ('$server', '$port', '$connection_id')");
			}

			print_servers($link, $connection_id);
		}

		break;

	case "delete-server":
		$ids = db_escape_string($_REQUEST["ids"]);
		$connection_id = (int) db_escape_string($_REQUEST["connection_id"]);

		if (valid_connection($link, $connection_id)) {
			db_query($link, "DELETE FROM ttirc_servers WHERE
				id in ($ids) AND connection_id = '$connection_id'");

			print_servers($link, $connection_id);
		}
		break;

	case "delete-connection":
		$ids = db_escape_string($_REQUEST["ids"]);

		db_query($link, "DELETE FROM ttirc_connections WHERE
			id IN ($ids) AND status = 0 AND owner_uid = ".$_SESSION["uid"]);

		print_connections($link);

		break;
	case "create-connection":
		$title = db_escape_string(trim($_REQUEST["title"]));

		if ($title) {
			db_query($link, "INSERT INTO ttirc_connections (enabled, title, owner_uid)
				VALUES ('false', '$title', '".$_SESSION["uid"]."')");
		}

		print_connections($link);
		break;

	case "toggle-connection":
		$connection_id = (int) db_escape_string($_REQUEST["connection_id"]);
		
		$status = bool_to_sql_bool(db_escape_string($_REQUEST["set_enabled"]));

		db_query($link, "UPDATE ttirc_connections SET enabled = '$status'
			WHERE id = '$connection_id' AND owner_uid = " . $_SESSION["uid"]);

		print json_encode(array("status" => $status));

		break;
	}
?>
