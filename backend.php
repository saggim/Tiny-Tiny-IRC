<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	/* remove ill effects of magic quotes */

	if (get_magic_quotes_gpc()) {
		$_REQUEST = array_map('stripslashes', $_REQUEST);
		$_POST = array_map('stripslashes', $_POST);
		$_REQUEST = array_map('stripslashes', $_REQUEST);
		$_COOKIE = array_map('stripslashes', $_COOKIE);
	}

	require_once "sessions.php";
	require_once "db-prefs.php";
	require_once "functions.php"; 
	require_once "sanity_check.php";
	require_once "version.php"; 
	require_once "config.php";
	require_once "prefs.php";
	require_once "users.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	$dt_add = get_script_dt_add();

	no_cache_incantation();

	header('Content-Type: text/html; charset=utf-8');

	$op = $_REQUEST["op"];

	if (!$_SESSION["uid"] && $op != "fetch-profiles") {
		print json_encode(array("error" => 6));
		return;
	} else if ($_SESSION["uid"]) {
		update_heartbeat($link);
	}

	if (!sanity_check($link)) { return; }

	switch ($op) {
	case "create-user":
		$login = strtolower(db_escape_string($_REQUEST["login"]));
		$rv = array();

		if ($_SESSION["access_level"] >= 10) {

			$result = db_query($link, "SELECT id FROM ttirc_users WHERE
				login = '$login'");

			if (db_num_rows($result) == 0) {
				$tmp_password = make_password();
				$pwd_hash = db_escape_string(encrypt_password($tmp_password, $login));

				$rv[0] = T_sprintf("Created user %s with password <b>%s</b>.",
					$login, $tmp_password);

				db_query($link, "INSERT INTO ttirc_users 
					(login, pwd_hash, email, nick, realname) 
					VALUES
					('$login', '$pwd_hash', '$login@localhost', '$login', '$login')");
			} else {
				$rv[0] = T_sprintf("User %s already exists", $login);
			}

			$rv[1] = format_users($link);

			print json_encode($rv);
		}
		break;
	case "reset-password":
		$id = db_escape_string($_REQUEST["id"]);

		if ($_SESSION["access_level"] >= 10) {
			$tmp_password = make_password();

			$login = get_user_login($link, $id);

			$pwd_hash = db_escape_string(encrypt_password($tmp_password, $login));

			db_query($link, "UPDATE ttirc_users SET pwd_hash = '$pwd_hash'
				WHERE id = '$id'");

			print json_encode(array("message" => 
				T_sprintf("Reset password of user %s to <b>%s</b>.", $login, 
					$tmp_password)));
		}

		break;
	case "delete-user":
		$ids = db_escape_string($_REQUEST["ids"]);

		if ($_SESSION["access_level"] >= 10) {

			db_query($link, "DELETE FROM ttirc_users WHERE
				id in ($ids) AND id != " . $_SESSION["uid"]);

			print format_users($link);
		}
		break;
	case "users":
		if ($_SESSION["access_level"] >= 10) {
			show_users($link);
		}
		break;
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
		$init = db_escape_string($_REQUEST["init"]);

		if (!$init) {
			$sleep_start = time();
			while (time() - $sleep_start < UPDATE_DELAY_MAX && 
					!num_new_lines($link, $last_id)) {

				sleep(1);
			}
		}

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
	case "prefs-conn-save":
		$title = db_escape_string($_REQUEST["title"]);
		$autojoin = db_escape_string($_REQUEST["autojoin"]);
		$connect_cmd = db_escape_string($_REQUEST["connect_cmd"]);
		$encoding = db_escape_string($_REQUEST["encoding"]);
		$nick = db_escape_string($_REQUEST["nick"]);
		$auto_connect = bool_to_sql_bool(db_escape_string($_REQUEST["auto_connect"]));
		$permanent = bool_to_sql_bool(db_escape_string($_REQUEST["permanent"]));
		$connection_id = db_escape_string($_REQUEST["connection_id"]);

		if (!$title) $title = __("[Untitled]");

		if (valid_connection($link, $connection_id)) {

			db_query($link, "UPDATE ttirc_connections SET title = '$title',
				autojoin = '$autojoin',
				connect_cmd = '$connect_cmd',
				auto_connect = '$auto_connect',
				nick = '$nick',
				encoding = '$encoding',
				permanent = '$permanent'
				WHERE id = '$connection_id'");

			//print json_encode(array("error" => "Function not implemented."));
		}
		break;

	case "prefs-save":
		//print json_encode(array("error" => "Function not implemented."));

		$realname = db_escape_string($_REQUEST["realname"]);
		$quit_message = db_escape_string($_REQUEST["quit_message"]);
		$new_password = db_escape_string($_REQUEST["new_password"]);
		$confirm_password = db_escape_string($_REQUEST["confirm_password"]);
		$nick = db_escape_string($_REQUEST["nick"]);
		$email = db_escape_string($_REQUEST["email"]);
		$theme = db_escape_string($_REQUEST["theme"]);

		$theme_changed = false;

		$_SESSION["prefs_cache"] = false;

		if (get_user_theme($link) != $theme) {
			set_pref($link, "USER_THEME", $theme);
			$theme_changed = true;
		}

		db_query($link, "UPDATE ttirc_users SET realname = '$realname',
			quit_message = '$quit_message', 
			email = '$email',
			nick = '$nick' WHERE id = " . $_SESSION["uid"]);

		if ($new_password != $confirm_password) {
			print json_encode(array("error" => "Passwords do not match."));		
		}

		if ($confirm_password == $new_password && $new_password) {
			$pwd_hash =  encrypt_password($new_password, $_SESSION["name"]);

			db_query($link, "UPDATE ttirc_users SET pwd_hash = '$pwd_hash'
				WHERE id = ". $_SESSION["uid"]);
		}

		if ($theme_changed) {
			print json_encode(array("message" => "THEME_CHANGED"));
		}

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

				print_servers($link, $connection_id);

			} else {

				$error = T_sprintf("Couldn't add server (%s:%d): Invalid syntax.",
					$server, $port);

				print json_encode(array("error" => $error));
			}
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

		case "fetch-profiles":
			$login = db_escape_string($_REQUEST["login"]);
			$password = db_escape_string($_REQUEST["password"]);

			if (authenticate_user($link, $login, $password)) {
				$result = db_query($link, "SELECT * FROM ttirc_settings_profiles
					WHERE owner_uid = " . $_SESSION["uid"] . " ORDER BY title");

				print "<select style='width: 100%' name='profile'>";

				print "<option value='0'>" . __("Default profile") . "</option>";

				while ($line = db_fetch_assoc($result)) {
					$id = $line["id"];
					$title = $line["title"];

					print "<option value='$id'>$title</option>";
				}

				print "</select>";

				$_SESSION = array();
			}
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
