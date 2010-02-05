<?php
	require_once "functions.php";

	define('SCHEMA_VERSION', 1);
	define('EXPECTED_CONFIG_VERSION', 1);

	$err_msg = ""; 

	if (!file_exists("config.php")) {
		print "<b>Fatal Error</b>: You forgot to copy 
		<b>config.php-dist</b> to <b>config.php</b> and edit it.\n";
		exit;
	}

	require_once "config.php";

	if (CONFIG_VERSION != EXPECTED_CONFIG_VERSION) {
		$err_msg = "config: your config file version is incorrect. See config.php-dist.\n";
	}

	if (!is_dir(LOCK_DIRECTORY)) {
		$err_msg = "config: LOCK_DIRECTORY doesn't exist or isn't a directory.\n";
	}


	if ($err_msg) {
		print "<b>Fatal Error</b>: $err_msg\n";
		exit;
	}

?>
