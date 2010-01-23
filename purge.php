#!/usr/bin/php
<?php
	require_once "config.php";
	require_once "sanity_check.php";
	require_once "functions.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	init_connection($link);
	purge_old_lines($link);

	db_close($link);

?>
