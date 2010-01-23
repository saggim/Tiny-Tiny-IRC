<?php
	require_once "functions.php";

	function print_servers($link, $id) {
		$result = db_query($link, "SELECT * FROM ttirc_servers
			WHERE connection_id = '$id'");

		$lnum = 1;

		while ($line = db_fetch_assoc($result)) {

			$row_class = ($lnum % 2) ? "odd" : "even";

			$id = $line['id'];

			print "<li id='S-$id' class='$row_class' server_id='$id'>";
			print "<input type='checkbox' onchange='select_row(this)'
				row_id='S-$id'>&nbsp;";
			print $line['server'] . ":" . $line['port'];
			print "</li>";

			++$lnum;
		}
	}


	function connection_editor($link, $id) {
		$result = db_query($link, "SELECT * FROM ttirc_connections
			WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);

		$line = db_fetch_assoc($result);

		if (sql_bool_to_bool($line['enabled'])) {
			$enabled_checked = 'checked';
		} else {
			$enabled_checked = '';
		}

		if (sql_bool_to_bool($line['permanent'])) {
			$permanent_checked = 'checked';
		} else {
			$permanent_checked = '';
		}

	?>
	<div id="infoBoxTitle"><?php echo __("Edit Connection") ?></div>
	<div class="infoBoxContents">
		<div class="dlgSec">Connection</div>

		<div class="dlgSecCont">
			<label class='fixed'>Title:</label>
			<input name="title" value="<?php echo $line['title'] ?>">
			<br clear='left'/>

			<label class='fixed'>Favorite channels:</label>
			<input name="autojoin" value="<?php echo $line['autojoin'] ?>">
			<br clear='left'/>

			<label class='fixed'>Charset:</label>
			<input name="encoding" value="<?php echo $line['encoding'] ?>">
			<br clear='left'/>

		</div>

		<div class="dlgSec">Options</div>

		<div class="dlgSecCont">
			<input name="enabled" <?php echo $enabled_checked ?> 
				id="pr_enabled" type="checkbox" value="">
			<label for="pr_enabled">Enabled</label>
			<br clear='left'/>

			<input name="permanent" <?php echo $permanent_checked ?>
				id="pr_permanent" type="checkbox" value="">
			<label for="pr_permanent">Keep connected</label>
			<br clear='left'/>

		</div>

		<div class="dlgSec">Servers</div>

		<ul class="container">
			<?php print_servers($link, $id); ?>
		</ul>

		<div class="dlgButtons">
			<div style='float : left'>
				<button>Delete server</button>
			</div>
			<button type="submit" onclick="save_conn()">Save</button>
			<button type="submit" onclick="show_prefs()">Go back</button></div>
		</div>
	</div>

	<?php
	}

	function print_connections($link) {
		$result = db_query($link, "SELECT * FROM ttirc_connections
			WHERE owner_uid = " . $_SESSION["uid"]);

		$lnum = 1;

		while ($line = db_fetch_assoc($result)) {

			$row_class = ($lnum % 2) ? "odd" : "even";

			$id = $line['id'];

			print "<li id='C-$id' class='$row_class' connection_id='$id'>";
			print "<input type='checkbox' onchange='select_row(this)'
				row_id='C-$id'>";
			print "&nbsp;<a href=\"#\" title=\"Click to edit connection\"
				onclick=\"edit_connection($id)\">".
				$line['title']."</a>";
			print "</li>";

			++$lnum;
		}
	}

	function main_prefs($link) {

	$result = db_query($link, "SELECT * FROM ttirc_users WHERE
		id = " . $_SESSION["uid"]);

	$realname = db_fetch_result($result, 0, "realname");
	$nick = db_fetch_result($result, 0, "nick");
	$email = db_fetch_result($result, 0, "email");
	$quit_message = db_fetch_result($result, 0, "quit_message");

?>

	<div id="infoBoxTitle"><?php echo __("Preferences") ?></div>
	<div class="infoBoxContents">

		<form id="prefs_form" onsubmit="return false;">

		<div class="dlgSec">Personal data</div>

		<div class="dlgSecCont">
			<label class="fixed">Real name:</label>
			<input name="realname" value="<?php echo $realname ?>">
			<br clear='left'/>

			<label class="fixed">Default nick:</label>
			<input name="realname" value="<?php echo $nick ?>">
			<br clear='left'/>

			<label class="fixed">E-mail:</label>
			<input name="email" value="<?php echo $email ?>">
			<br clear='left'/>

			<label class="fixed">Quit message:</label>
			<input name="quit_message" value="<?php echo $quit_message ?>">

		</div>

		<div class="dlgSec">Authentication</div>

		<div class="dlgSecCont">
			<label class="fixed">New password:</label>
			<input name="new_password" type="password" value="">
			<br clear='left'/>

			<label class="fixed">Confirm:</label>
			<input name="confirm_password" type="password" value="">
		</div>

		</form>

		<div class="dlgSec">Connections</div>


		<ul class="container" id="connections-list">
			<?php print_connections($link) ?>
		</ul>

		<div class="dlgButtons">
			<div style='float : left'>
				<button onclick="create_connection()">Create connection</button>
				<button onclick="delete_connection()">Delete</button>
			</div>

			<button type="submit" onclick="save_prefs()">Save</button>
			<button type="submit" onclick="close_infobox()">Close</button></div>

		</form>
	</div>

<? } ?>
