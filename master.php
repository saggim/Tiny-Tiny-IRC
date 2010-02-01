#!/usr/bin/php
<?php
	require_once "db.php";
	require_once "functions.php";
	require_once "connection.php";

	define('SPAWN_INTERVAL', 5);

	declare(ticks = 1);

	$children = array();

	function check_children($link) {
		global $children;

		/* FIXME needs more robust implementation */

		foreach ($children as $child) {
			$pid = $child["pid"];
			$conn = $child["id"];
			$need_term = $child["need_term"];

			if ($need_term == 1) {

				++$need_term;

			} else if ($need_term == 2) {


			} else {
				$result = db_query($link, "SELECT id FROM
					ttirc_connections WHERE id = '$conn' AND enabled = true");

				if (db_num_rows($result) != 1) {
					_debug("connection $conn [PID:$pid] needs termination.");

					$need_term = 1;
					posix_kill($pid, 2);

				}
			}
		}
	}

	function reap_children() {
		global $children;

		$tmp = array();

		foreach ($children as $child) {
			$pid = $child["pid"];
			$conn = $child["id"];

			if (pcntl_waitpid($pid, $status, WNOHANG) != $pid) {
				array_push($tmp, $child);
			} else {
				_debug("[SIGCHLD] child $pid died; cleaning up....");

				$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	
				init_connection($link);

				push_message($link, $conn, "---", "DISCONNECT", true, MSGT_EVENT);

				db_query($link, "UPDATE ttirc_connections SET
					status = ".CS_DISCONNECTED." WHERE id = '$conn'");

				db_query($link, "UPDATE ttirc_channels 
					SET nicklist = ''
					WHERE connection_id = '$conn'");

				db_close($link);

				_debug("[SIGCHLD] child $pid reaped; connection $conn terminated.");
			}
		}

		$children = $tmp;

		return count($tmp);
	}
	function cleanup() {
		$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	
		init_connection($link);

		db_query($link, "UPDATE ttirc_connections SET status = ".CS_DISCONNECTED.", 
			userhosts = ''");

		db_query($link, "UPDATE ttirc_channels SET nicklist = ''");

		db_query($link, "UPDATE ttirc_system SET value = 'false' WHERE
			key = 'MASTER_RUNNING'");

		db_close($link);
	}


	function sigint_handler() {
		unlink(LOCK_DIRECTORY . "/ttirc_master.lock");

		cleanup();

		die("[SIGINT] removing lockfile and exiting.\n");
	}

	function sigchld_handler($signal) {
		$running_jobs = reap_children();

		_debug("[SIGCHLD] jobs left: $running_jobs");

		pcntl_waitpid(-1, $status, WNOHANG);
	}

	if (!pcntl_fork()) {
		pcntl_signal(SIGINT, 'sigint_handler');

		// Try to lock a file in order to avoid concurrent update.
		$lock_handle = make_lockfile("update_daemon.lock");

		if (!$lock_handle) {
			die("error: Can't create lockfile. ".
				"Maybe another daemon is already running.\n");
		}

		while (true) { sleep(100); }
	}

	pcntl_signal(SIGCHLD, 'sigchld_handler');
	pcntl_signal(SIGINT, 'sigint_handler');

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	
	init_connection($link);
	db_query($link, "DELETE FROM ttirc_channels");
	db_close($link);

	cleanup();

	while (true) {

		if ($last_checkpoint + SPAWN_INTERVAL < time()) {

			$last_checkpoint = time();

			$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	
			init_connection($link);

			db_query($link, "UPDATE ttirc_system SET value = 'true' WHERE
				key = 'MASTER_RUNNING'");
	
			db_query($link, "UPDATE ttirc_system SET value = NOW() WHERE
				key = 'MASTER_HEARTBEAT'");

			check_children($link);

			$result = db_query($link, "SELECT ttirc_connections.id 
				FROM ttirc_connections, ttirc_users 
				WHERE owner_uid = ttirc_users.id AND
				visible = true AND
				(heartbeat > NOW() - INTERVAL '5 minutes' OR permanent = true) AND
				enabled = true");

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
						status = ".CS_CONNECTING.", userhosts = '' WHERE id = '$id'");

					db_query($link, "UPDATE ttirc_channels SET
						nicklist = '' WHERE connection_id = '$id'");

					push_message($link, $id, "---", "REQUEST_CONNECTION", true,
						MSGT_EVENT);

					db_close($link);

					//system("./handle.php $id");
					pcntl_exec("./handle.php", array($id));

					die;
				}

			}

		} else {
			sleep(1);
		}
	} 
?>
