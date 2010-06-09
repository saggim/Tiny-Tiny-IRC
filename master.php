#!/usr/bin/php
<?php
	require_once "db.php";
	require_once "functions.php";
	require_once "connection.php";

	define('SPAWN_INTERVAL', 5);
	define('LOCK_FILE_NAME', "master-daemon.lock");

	declare(ticks = 1);

	$children = array();
	$last_checkpoint = 0;	
	$lock_pid = -1;

	function check_children($link) {
		global $children;

		/* FIXME needs more robust implementation */

		foreach (array_values($children) as $child) {
			$pid = $child["pid"];
			$conn = $child["id"];
			$need_term = $child["need_term"];

			$result = db_query($link, "SELECT id FROM
				ttirc_connections WHERE id = '$conn' AND enabled = true");

			if (db_num_rows($result) != 1) {
				_debug("connection $conn [PID:$pid] needs termination.");
				posix_kill($pid, 2);
			}

/*			if ($need_term != 0) {

				_debug("connection $conn [PID:$pid] needs termination.");
				posix_kill($pid, 2);

			} else {
				$result = db_query($link, "SELECT id FROM
					ttirc_connections WHERE id = '$conn' AND enabled = true");

				if (db_num_rows($result) != 1) {

					_debug("connection $conn [PID:$pid] is finished, ".
						"scheduling termination.");

					$child["need_term"] = 1;
				}
			}
			$children[$conn] = $child; */
		}
	}

	function reap_children() {
		global $children;

		$tmp = array();

		foreach (array_values($children) as $child) {
			$pid = $child["pid"];
			$conn = $child["id"];

			if (pcntl_waitpid($pid, $status, WNOHANG) != $pid) {
				//array_push($tmp, $child);
				$tmp[$conn] = $child;
			} else {
				_debug("[SIGCHLD] child $pid died; cleaning up....");

				$link = db_reconnect($link, DB_HOST, DB_USER, DB_PASS, DB_NAME);	
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

		return count(array_keys($tmp));
	}
	function cleanup() {
		$link = db_reconnect($link, DB_HOST, DB_USER, DB_PASS, DB_NAME);	
		init_connection($link);

		db_query($link, "UPDATE ttirc_connections SET status = ".CS_DISCONNECTED.", 
			userhosts = ''");

		db_query($link, "UPDATE ttirc_channels SET nicklist = ''");

		db_query($link, "UPDATE ttirc_system SET value = 'false' WHERE
			param = 'MASTER_RUNNING'");

		db_close($link);
	}

	function sigint_handler() {
		_debug("[SIGINT] exiting.");
		exit(1);
	}

	function sigchld_handler($signal) {
		$running_jobs = reap_children();

		_debug("[SIGCHLD] jobs left: $running_jobs");

		pcntl_waitpid(-1, $status, WNOHANG);
	}

	function shutdown_handler() {
		global $lock_pid;
		global $children;

		_debug("[MASTER] shutdown handler: cleaning up...");

		foreach (array_values($children) as $child) {
			$pid = $child["pid"];
			pcntl_kill($pid, 2);
		}

		sleep(3);

		cleanup();

		posix_kill($lock_pid, 2);
		pcntl_waitpid($lock_pid);

		unlink(LOCK_DIRECTORY . "/" . LOCK_FILE_NAME);

		_debug("[MASTER] shutdown handler: done.");
	}

	register_shutdown_function("shutdown_handler");

	pcntl_signal(SIGCHLD, 'sigchld_handler');

	_debug("[MASTER] connection daemon initializing... (version " . VERSION . ")");

	if (file_is_locked(LOCK_FILE_NAME)) {
		die("error: Can't create lockfile. ".
			"Maybe another daemon is already running.\n");
	}

	$lock_pid = pcntl_fork();

	if (!$lock_pid) {
		pcntl_signal(SIGINT, 'sigint_handler');

		// Try to lock a file in order to avoid concurrent update.
		$lock_handle = make_lockfile(LOCK_FILE_NAME);

		if (!$lock_handle) {
			die("error: Can't create lockfile. ".
				"Maybe another daemon is already running.\n");
		}

		while (true) { sleep(100); }
	} else {
		_debug("[MASTER] spawned lock process [PID:$lock_pid]");
	}

	$link = db_reconnect($link, DB_HOST, DB_USER, DB_PASS, DB_NAME);	
	init_connection($link);
	db_query($link, "DELETE FROM ttirc_channels");
	db_close($link);

	cleanup();

	_debug("[MASTER] daemon initialized. entering main loop.");

	while (true) {

//		_debug("[MASTER] spawn interval elapsed, checking for connection requests...");

		if ($last_checkpoint + SPAWN_INTERVAL < time()) {

			$last_checkpoint = time();

			$link = db_reconnect($link, DB_HOST, DB_USER, DB_PASS, DB_NAME);	
			init_connection($link);

			db_query($link, "UPDATE ttirc_system SET value = 'true' WHERE
				param = 'MASTER_RUNNING'");
	
			db_query($link, "UPDATE ttirc_system SET value = NOW() WHERE
				param = 'MASTER_HEARTBEAT'");
	
			check_children($link);

			$result = db_query($link, "SELECT ttirc_connections.id 
				FROM ttirc_connections, ttirc_users 
				WHERE owner_uid = ttirc_users.id AND
				visible = true AND
				(heartbeat > ".get_interval_minutes(5)." OR permanent = true) AND
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

					$children[$id] = $child;

					//array_push($children, $child);

				} else {
					pcntl_signal(SIGCHLD, SIG_IGN);
					pcntl_signal(SIGINT, SIG_DFL);

					$link = db_reconnect($link, DB_HOST, DB_USER, DB_PASS, DB_NAME);	
					init_connection($link);

					db_query($link, "UPDATE ttirc_connections SET
						status = ".CS_CONNECTING.", userhosts = '' WHERE id = '$id'");

					db_query($link, "UPDATE ttirc_channels SET
						nicklist = '' WHERE connection_id = '$id'");

					push_message($link, $id, "---", "REQUEST_CONNECTION", true,
						MSGT_EVENT);

					db_close($link);

					pcntl_exec("./handle.php", array($id));

					die;
				}

			}
		}

		sleep(1);
	} 
?>
