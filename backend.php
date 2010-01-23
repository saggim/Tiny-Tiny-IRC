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
	case "send":
		$message = db_escape_string(trim($_REQUEST["message"]));
		$last_id = (int) db_escape_string($_REQUEST["last_id"]);
		$chan = db_escape_string($_REQUEST["chan"]);
		$connection_id = db_escape_string($_REQUEST["connection"]);

		if ($message) {
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
		$active_buf = db_escape_string($_REQUEST["active"]);

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

	case "toggle-connection":
		$connection_id = (int) db_escape_string($_REQUEST["connection_id"]);
		
		$status = bool_to_sql_bool(db_escape_string($_REQUEST["set_enabled"]));

		db_query($link, "UPDATE ttirc_connections SET enabled = '$status'
			WHERE id = '$connection_id' AND owner_uid = " . $_SESSION["uid"]);

		print json_encode(array("status" => $status));

		break;
	}
?>
