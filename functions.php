<?php
	require_once "config.php";
	require_once "message_types.php";

	if (DB_TYPE == "pgsql") {
		define('SUBSTRING_FOR_DATE', 'SUBSTRING_FOR_DATE');
	} else {
		define('SUBSTRING_FOR_DATE', 'SUBSTRING');
	}

	function get_translations() {
		$tr = array(
					"auto"  => "Detect automatically",					
					"en_US" => "English");

		return $tr;
	}

	if (ENABLE_TRANSLATIONS == true) { // If translations are enabled.
		require_once "lib/accept-to-gettext.php";
		require_once "lib/gettext/gettext.inc";

		function startup_gettext() {
	
			# Get locale from Accept-Language header
			$lang = al2gt(array_keys(get_translations()), "text/html");

			if (defined('_TRANSLATION_OVERRIDE_DEFAULT')) {
				$lang = _TRANSLATION_OVERRIDE_DEFAULT;
			}

			if ($_COOKIE["ttirc_lang"] && $_COOKIE["ttirc_lang"] != "auto") {				
				$lang = $_COOKIE["ttirc_lang"];
			}

			/* In login action of mobile version */
			if ($_POST["language"] && defined('MOBILE_VERSION')) {
				$lang = $_POST["language"];
				$_COOKIE["ttirc_lang"] = $lang;
			}

			if ($lang) {
				if (defined('LC_MESSAGES')) {
					_setlocale(LC_MESSAGES, $lang);
				} else if (defined('LC_ALL')) {
					_setlocale(LC_ALL, $lang);
				} else {
					die("can't setlocale(): please set ENABLE_TRANSLATIONS to false in config.php");
				}

				if (defined('MOBILE_VERSION')) {
					_bindtextdomain("messages", "../locale");
				} else {
					_bindtextdomain("messages", "locale");
				}

				_textdomain("messages");
				_bind_textdomain_codeset("messages", "UTF-8");
			}
		}

		startup_gettext();

	} else { // If translations are enabled.
		function __($msg) {
			return $msg;
		}
		function startup_gettext() {
			// no-op
			return true;
		}
	} // If translations are enabled.

	require_once "errors.php";

	function init_connection($link) {

		if (!$link) {
			if (DB_TYPE == "mysql") {
			print mysql_error();
		}
		// PG seems to display its own errors just fine by default.		
			die("Connection failed.");
		}

		if (DB_TYPE == "pgsql") {
			pg_query($link, "set client_encoding = 'UTF-8'");
			pg_set_client_encoding("UNICODE");
			pg_query($link, "set datestyle = 'ISO, european'");
		} else {
			if (defined('MYSQL_CHARSET') && MYSQL_CHARSET) {
				db_query($link, "SET NAMES " . MYSQL_CHARSET);
	//			db_query($link, "SET CHARACTER SET " . MYSQL_CHARSET);
			}
		}
	}

	// from http://developer.apple.com/internet/safari/faq.html
	function no_cache_incantation() {
		header("Expires: Mon, 22 Dec 1980 00:00:00 GMT"); // Happy birthday to me :)
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); // HTTP/1.1
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache"); // HTTP/1.0
	}

	function login_sequence($link, $mobile = false) {
		if (!SINGLE_USER_MODE) {

			$login_action = $_POST["login_action"];

			# try to authenticate user if called from login form			
			if ($login_action == "do_login") {
				$login = $_POST["login"];
				$password = $_POST["password"];
				$remember_me = $_POST["remember_me"];

				if (authenticate_user($link, $login, $password)) {
					$_POST["password"] = "";

					$_SESSION["language"] = $_POST["language"];
					$_SESSION["ref_schema_version"] = get_schema_version($link, true);
					$_SESSION["bw_limit"] = !!$_POST["bw_limit"];

					header("Location: " . $_SERVER["REQUEST_URI"]);
					exit;

					return;
				} else {
					$_SESSION["login_error_msg"] = __("Incorrect username or password");
				}
			}

			if (!$_SESSION["uid"] || !validate_session($link)) {
				render_login_form($link, $mobile);
				//header("Location: login.php");
				exit;
			} else {
				/* bump login timestamp */
				db_query($link, "UPDATE ttirc_users SET last_login = NOW() WHERE id = " . 
					$_SESSION["uid"]);

				if ($_SESSION["language"] && SESSION_COOKIE_LIFETIME > 0) {
					setcookie("ttirc_lang", $_SESSION["language"], 
						time() + SESSION_COOKIE_LIFETIME);
				}

				/* bump counters stamp since we're getting reloaded anyway */

				$_SESSION["get_all_counters_stamp"] = time();
			}

		} else {
			return authenticate_user($link, "admin", null);
		}
	}

	function render_login_form($link, $mobile = 0) {
		switch ($mobile) {
		case 0:
			require_once "login_form.php";
			break;
		case 1:
			require_once "mobile/login_form.php";
			break;
		case 2:
			require_once "mobile/classic/login_form.php";
		}
	}

	function print_select($id, $default, $values, $attributes = "") {
		print "<select name=\"$id\" id=\"$id\" $attributes>";
		foreach ($values as $v) {
			if ($v == $default)
				$sel = " selected";
			 else
			 	$sel = "";
			
			print "<option$sel>$v</option>";
		}
		print "</select>";
	}

	function print_select_hash($id, $default, $values, $attributes = "") {
		print "<select name=\"$id\" id='$id' $attributes>";
		foreach (array_keys($values) as $v) {
			if ($v == $default)
				$sel = 'selected="selected"';
			 else
			 	$sel = "";
			
			print "<option $sel value=\"$v\">".$values[$v]."</option>";
		}

		print "</select>";
	}

	function encrypt_password($pass, $login = '') {
		if ($login) {
			return "SHA1X:" . sha1("$login:$pass");
		} else {
			return "SHA1:" . sha1($pass);
		}
	} // function encrypt_password

	function authenticate_user($link, $login, $password, $force_auth = false) {

		if (!SINGLE_USER_MODE) {

			$pwd_hash1 = encrypt_password($password);
			$pwd_hash2 = encrypt_password($password, $login);
			$login = db_escape_string($login);

			if (defined('ALLOW_REMOTE_USER_AUTH') && ALLOW_REMOTE_USER_AUTH 
					&& $_SERVER["REMOTE_USER"] && $login != "admin") {

				$login = db_escape_string($_SERVER["REMOTE_USER"]);

				$query = "SELECT id,login,access_level,pwd_hash
	            FROM ttirc_users WHERE
					login = '$login'";

			} else {
				$query = "SELECT id,login,access_level,pwd_hash
	            FROM ttirc_users WHERE
					login = '$login' AND (pwd_hash = '$pwd_hash1' OR
						pwd_hash = '$pwd_hash2')";
			}

			$result = db_query($link, $query);
	
			if (db_num_rows($result) == 1) {
				$_SESSION["uid"] = db_fetch_result($result, 0, "id");
				$_SESSION["name"] = db_fetch_result($result, 0, "login");
				$_SESSION["access_level"] = db_fetch_result($result, 0, "access_level");
	
				db_query($link, "UPDATE ttirc_users SET last_login = NOW() WHERE id = " . 
					$_SESSION["uid"]);
	
				$_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];
				$_SESSION["pwd_hash"] = db_fetch_result($result, 0, "pwd_hash");
	
				//initialize_user_prefs($link, $_SESSION["uid"]);
	
				return true;
			}
	
			return false;

		} else {

			$_SESSION["uid"] = 1;
			$_SESSION["name"] = "admin";

			$_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];
	
			//initialize_user_prefs($link, $_SESSION["uid"]);
	
			return true;
		}
	}

	function get_schema_version($link, $nocache = false) {
		if (!$_SESSION["schema_version"] || $nocache) {
			$result = db_query($link, "SELECT schema_version FROM ttirc_version");
			$version = db_fetch_result($result, 0, "schema_version");
			$_SESSION["schema_version"] = $version;
			return $version;
		} else {
			return $_SESSION["schema_version"];
		}
	}

	function validate_session($link) {
		if (SINGLE_USER_MODE) { 
			return true;
		}

		if (SESSION_CHECK_ADDRESS && $_SESSION["uid"]) {
			if ($_SESSION["ip_address"]) {
				if ($_SESSION["ip_address"] != $_SERVER["REMOTE_ADDR"]) {
					$_SESSION["login_error_msg"] = __("Session failed to validate (incorrect IP)");
					return false;
				}
			}
		}

		if ($_SESSION["ref_schema_version"] != get_schema_version($link, true)) {
			return false;
		}

		if ($_SESSION["uid"]) {

			$result = db_query($link, 
				"SELECT pwd_hash FROM ttirc_users WHERE id = '".$_SESSION["uid"]."'");

			$pwd_hash = db_fetch_result($result, 0, "pwd_hash");

			if ($pwd_hash != $_SESSION["pwd_hash"]) {
				return false;
			}
		}

		return true;
	}

	function get_script_dt_add() {
		return time();
	}

	function theme_image($link, $filename) {
		if ($link) {
			$theme_path = get_user_theme_path($link);

			if ($theme_path && is_file($theme_path.$filename)) {
				return $theme_path.$filename;
			} else {
				return $filename;
			}
		} else {
			return $filename;
		}
	}

	function get_user_theme($link) {
		return ''; // TODO
	}

	function get_user_theme_path($link) {
		return false; // TODO
	}

	function get_all_themes() {
		$themes = glob("themes/*");

		asort($themes);

		$rv = array();

		foreach ($themes as $t) {
			if (is_file("$t/theme.ini")) {
				$ini = parse_ini_file("$t/theme.ini", true);
				if ($ini['theme']['version'] && !$ini['theme']['disabled']) {
					$entry = array();
					$entry["path"] = $t;
					$entry["base"] = basename($t);
					$entry["name"] = $ini['theme']['name'];
					$entry["version"] = $ini['theme']['version'];
					$entry["author"] = $ini['theme']['author'];
					$entry["options"] = $ini['theme']['options'];
					array_push($rv, $entry);
				}
			}
		}

		return $rv;
	}

	function logout_user() {
		session_destroy();
		if (isset($_COOKIE[session_name()])) {
		   setcookie(session_name(), '', time()-42000, '/');
		}
	}

	function format_warning($msg, $id = "") {
		global $link;
		return "<div class=\"warning\" id=\"$id\"> 
			<img src=\"".theme_image($link, "images/sign_excl.png")."\">$msg</div>";
	}

	function format_notice($msg) {
		global $link;
		return "<div class=\"notice\" id=\"$id\"> 
			<img src=\"".theme_image($link, "images/sign_info.png")."\">$msg</div>";
	}

	function format_error($msg) {
		global $link;
		return "<div class=\"error\" id=\"$id\"> 
			<img src=\"".theme_image($link, "images/sign_excl.png")."\">$msg</div>";
	}

	function print_notice($msg) {
		return print format_notice($msg);
	}

	function print_warning($msg) {
		return print format_warning($msg);
	}

	function print_error($msg) {
		return print format_error($msg);
	}


	function T_sprintf() {
		$args = func_get_args();
		return vsprintf(__(array_shift($args)), $args);
	}
	
	function _debug($msg) {
		$ts = strftime("%H:%M:%S", time());
		if (function_exists('posix_getpid')) {
			$ts = "$ts/" . posix_getpid();
		}
		print "[$ts] $msg\n";
	} // function _debug

	function file_is_locked($filename) {
		if (function_exists('flock')) {
			error_reporting(0);
			$fp = fopen(LOCK_DIRECTORY . "/$filename", "r");
			error_reporting(DEFAULT_ERROR_LEVEL);
			if ($fp) {
				if (flock($fp, LOCK_EX | LOCK_NB)) {
					flock($fp, LOCK_UN);
					fclose($fp);
					return false;
				}
				fclose($fp);
				return true;
			} else {
				return false;
			}
		}
		return true; // consider the file always locked and skip the test
	}

	function make_lockfile($filename) {
		$fp = fopen(LOCK_DIRECTORY . "/$filename", "w");

		if (flock($fp, LOCK_EX | LOCK_NB)) {		
			return $fp;
		} else {
			return false;
		}
	}

	# TODO return actual nick, not hardcoded one
	function get_nick($link, $connection_id) {
		$result = db_query($link, "SELECT active_nick FROM ttirc_connections
			WHERE id ='$connection_id'");

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "active_nick");
		} else {
			return "?UNKNOWN?";
		}
	}

	function handle_command($link, $connection_id, $destination, $message) {

		$keywords = array();

		preg_match("/^\/([^ ]+) ?(.*)$/", $message, $keywords);

		$command = $keywords[1];
		$arguments = $keywords[2];

		push_message($link, $connection_id, $destination,
			"$command:$arguments", false, 1);

	}


	function push_message($link, $connection_id, $destination, $message, 
		$incoming = false, $message_type = 0) {

		$incoming = bool_to_sql_bool($incoming);

		if ($destination != "---") {
			$my_nick = get_nick($link, $connection_id);
		} else {
			$my_nick = "---";
		}

		db_query($link, "INSERT INTO ttirc_messages 
			(incoming, connection_id, destination, sender, message, message_type) VALUES
			($incoming, $connection_id, '$destination', '$my_nick', '$message', 
			'$message_type')");
	}

	function get_new_lines($link, $last_id) {

		$result = db_query($link, "SELECT ttirc_messages.id,
			message_type, sender, destination, 
			message, ".SUBSTRING_FOR_DATE."(ts,12,8) AS ts
			FROM ttirc_messages, ttirc_connections WHERE
			connection_id = ttirc_connections.id AND
			status != ".CS_DISCONNECTED." AND
			message_type != 1 AND
			ts > NOW() - INTERVAL '1 hour' AND
			ttirc_messages.id > '$last_id' AND 
			owner_uid = ".$_SESSION["uid"]." ORDER BY ttirc_messages.id");

		$lines = array();

		while ($line = db_fetch_assoc($result)) {
			$line["message"] = htmlspecialchars($line["message"]);
			array_push($lines, $line);
		}

		return $lines;

	}

	function get_nick_list($link, $active_chan = false) {

		if ($active_chan && $active_chan != "---") {
			$active_chan_qpart = "destination = '$active_chan' AND";
		} else {
			$active_chan_qpart = "";
		}

		$result = db_query($link, "SELECT nicklist,destination,
			topic,topic_owner,".SUBSTRING_FOR_DATE."(topic_set,1,16) AS topic_set
			FROM ttirc_destinations, ttirc_connections 
			WHERE connection_id = ttirc_connections.id AND 
			status = ".CS_CONNECTED." AND
			$active_chan_qpart
			owner_uid = ".$_SESSION["uid"]);

		$rv = array();

		while ($line = db_fetch_assoc($result)) {
			$rv[$line["destination"]]["users"] = json_decode($line["nicklist"]);
			$rv[$line["destination"]]["topic"] = array(
				$line["topic"], $line["topic_owner"], $line["topic_set"]);
		}

		return $rv;
	}

	function get_conn_info($link) {

		$result = db_query($link, "SELECT id,server,active_nick,status 
			FROM ttirc_connections
			WHERE owner_uid = ".$_SESSION["uid"]);
	
		$conn = array();

		while ($line = db_fetch_assoc($result)) {
			array_push($conn, $line);
		}

		return $conn;

	}

	function sql_bool_to_string($s) {
		if ($s == "t" || $s == "1") {
			return "true";
		} else {
			return "false";
		}
	}

	function sql_bool_to_bool($s) {
		if ($s == "t" || $s == "1") {
			return true;
		} else {
			return false;
		}
	}
	
	function bool_to_sql_bool($s) {
		if ($s) {
			return "true";
		} else {
			return "false";
		}
	}

	function sanity_check($link) {

		global $ERRORS;

		error_reporting(0);

		$error_code = 0;
		$schema_version = get_schema_version($link);

		if ($schema_version != SCHEMA_VERSION) {
			$error_code = 5;
		}

		if (DB_TYPE == "mysql") {
			$result = db_query($link, "SELECT true", false);
			if (db_num_rows($result) != 1) {
				$error_code = 10;
			}
		}

		if (db_escape_string("testTEST") != "testTEST") {
			$error_code = 12;
		}

		error_reporting (DEFAULT_ERROR_LEVEL);

		$result = db_query($link, "SELECT value FROM ttirc_system WHERE
			key = 'MASTER_RUNNING'");

		$master_running = db_fetch_result($result, 0, "value") == "true";

		if (!$master_running) {
			$error_code = 13;
		}

		if ($error_code != 0) {
			print json_encode(array("error" => $error_code, 
				"errormsg" => $ERRORS[$error_code]));
			return false;
		} else {
			return true;
		}
	}

?>
