#!/usr/bin/php
<?php
	require_once "db.php";
	require_once "functions.php";
	require_once "connection.php";

	define('SPAWN_INTERVAL', 5);

	declare(ticks = 1);

	$children = array();

	function reap_children() {
		global $children;

		$tmp = array();

		foreach ($children as $child) {
			$pid = $child["pid"];
			$conn = $child["id"];

			if (pcntl_waitpid($pid, $status, WNOHANG) != $pid) {
				array_push($tmp, $child);
			} else {
				_debug("[SIGCHLD] child $pid reaped; connection $conn terminated.");
			}
		}

		$children = $tmp;

		return count($tmp);
	}
	function sigint_handler() {
		unlink(LOCK_DIRECTORY . "/ttirc_master.lock");

		$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	
		init_connection($link);

		db_query($link, "UPDATE ttirc_connections SET active = false");

		db_query($link, "DELETE FROM ttirc_destinations");

		db_query($link, "UPDATE ttirc_system SET value = 'false' WHERE
			key = 'MASTER_RUNNING'");

		db_close($link);

		die("[SIGINT] removing lockfile and exiting.\n");
	}

	function sigchld_handler($signal) {
		$running_jobs = reap_children();

		_debug("[SIGCHLD] jobs left: $running_jobs");

		pcntl_waitpid(-1, $status, WNOHANG);
	}

	pcntl_signal(SIGCHLD, 'sigchld_handler');
	pcntl_signal(SIGINT, 'sigint_handler');

	// Try to lock a file in order to avoid concurrent update.
	$lock_handle = make_lockfile("ttirc_master.lock");

	if (!$lock_handle) {
		die("error: Can't create lockfile. ".
			"Maybe another daemon is already running.\n");
	}

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	
	init_connection($link);
	db_query($link, "DELETE FROM ttirc_destinations");
	db_close($link);

	while (true) {

		if ($last_checkpoint + SPAWN_INTERVAL < time()) {

			$last_checkpoint = time();

			$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	
			init_connection($link);

			db_query($link, "UPDATE ttirc_system SET value = 'true' WHERE
				key = 'MASTER_RUNNING'");
	
			db_query($link, "UPDATE ttirc_system SET value = NOW() WHERE
				key = 'MASTER_HEARTBEAT'");

			$result = db_query($link, "SELECT * FROM ttirc_connections 
				WHERE enabled = true");

			$ids_to_launch = array();

			while ($line = db_fetch_assoc($result)) {
	
				$child_exists = false;
				foreach ($children as $child) {
					if ($line["id"] == $child["id"]) {
						$child_exists = true;
					}
				}
	
				if (!$child_exists) {
					_debug("launching connection " . $line["id"]);

					array_push($ids_to_launch, $line["id"]);
				}
			}

			db_close($link);

			foreach ($ids_to_launch as $id) {	
				$pid = pcntl_fork();

				if ($pid == -1) {
					die("fork failed!\n");
				} else if ($pid) {
					_debug("[MASTER] spawned connection [$id] PID:$pid...");

					$child = array();
					$child["id"] = $id;
					$child["pid"] = $pid;

					array_push($children, $child);

				} else {
					pcntl_signal(SIGCHLD, SIG_IGN);
					pcntl_signal(SIGINT, SIG_DFL);

					$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	
					init_connection($link);

					db_query($link, "UPDATE ttirc_connections SET
						active = true WHERE id = '$id'");

					db_query($link, "UPDATE ttirc_destinations SET
						nicklist = '' WHERE connection_id = '$id'");

					push_message($link, $id, "---", "Connecting to server...", true);
			
					db_close($link);

					system("./handle.php $id");

					$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	
					init_connection($link);

					push_message($link, $id, "---", "Connection terminated.", true);

					db_query($link, "UPDATE ttirc_connections SET
						active = false WHERE id = '$id'");

					db_query($link, "DELETE FROM ttirc_destinations WHERE
						connection_id = '$id'");

					db_close($link);

					sleep(3);

					die;
				}

			}

		} else {
			sleep(1);
		}
	} 
?>
