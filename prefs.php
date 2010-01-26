<?php
	require_once "functions.php";

	function print_servers($link, $id) {
		$result = db_query($link, "SELECT ttirc_servers.*,
				status,active_server
			FROM ttirc_servers,ttirc_connections
			WHERE connection_id = '$id' AND 
			connection_id = ttirc_connections.id AND
			owner_uid = " . $_SESSION["uid"]);

		$lnum = 1;

		while ($line = db_fetch_assoc($result)) {

			$row_class = ($lnum % 2) ? "odd" : "even";

			$id = $line['id'];

			if ($line['status'] != CS_DISCONNECTED && 
					$line['server'] . ':' . $line['port'] == $line['active_server']) {
				$connected = __("(connected)");
			} else {
				$connected = '';
			}			

			print "<li id='S-$id' class='$row_class' server_id='$id'>";
			print "<input type='checkbox' onchange='select_row(this)'
				row_id='S-$id'>&nbsp;";
			print $line['server'] . ":" . $line['port'] . " $connected";
			print "</li>";

			++$lnum;
		}
	}


	function connection_editor($link, $id) {
		$result = db_query($link, "SELECT * FROM ttirc_connections
			WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);

		$line = db_fetch_assoc($result);

		if (sql_bool_to_bool($line['auto_connect'])) {
			$auto_connect_checked = 'checked';
		} else {
			$auto_connect_checked = '';
		}

		if (sql_bool_to_bool($line['permanent'])) {
			$permanent_checked = 'checked';
		} else {
			$permanent_checked = '';
		}

	?>
	<div id="infoBoxTitle"><?php echo __("Edit Connection") ?></div>
	<div class="infoBoxContents">
		<div id="mini-notice" style='display : none'>&nbsp;</div>

		<div class="dlgSec"><?php echo __('Connection') ?></div>

		<form id="prefs_conn_form" onsubmit="return false;">

		<input type="hidden" name="connection_id" value="<?php echo $id ?>"/>
		<input type="hidden" name="op" value="prefs-conn-save"/>

		<div class="dlgSecCont">
			<label class='fixed'><?php echo __('Title:') ?></label>
			<input name="title" size="30" value="<?php echo $line['title'] ?>">
			<br clear='left'/>

			<label class="fixed"><?php echo __('Nickname:') ?></label>
			<input name="nick" size="30" value="<?php echo $line['nick'] ?>">
			<br clear='left'/>

			<label class='fixed'><?php echo __('Favorite channels:') ?></label>
			<input name="autojoin" size="30" value="<?php echo $line['autojoin'] ?>">
			<br clear='left'/>

			<label class='fixed'><?php echo __('Connect command:') ?></label>
			<input name="connect_cmd" size="30" value="<?php echo $line['connect_cmd'] ?>">
			<br clear='left'/>

			<label class='fixed'><?php echo __('Character set:') ?></label>

			<?php print_select('encoding', $line['encoding'], get_iconv_encodings()) ?>

			<br clear='left'/>

		</div>

		<div class="dlgSec">Options</div>

		<div class="dlgSecCont">
			<input name="auto_connect" <?php echo $auto_connect_checked ?> 
				id="pr_auto_connect" type="checkbox" value="1">
				<label for="pr_auto_connect"><?php echo __('Automatically connect') ?>
					</label>
			<br clear='left'/>

			<input name="permanent" <?php echo $permanent_checked ?>
				id="pr_permanent" type="checkbox" value="1">
				<label for="pr_permanent"><?php echo __('Stay connected permanently') ?>
					</label>
			<br clear='left'/>

		</div>

		<button type="submit" style="display : none" onclick="save_conn()">

		</form>

		<div class="dlgSec"><?php echo __('Servers') ?></div>

		<ul class="container" id="servers-list">
			<?php print_servers($link, $id); ?>
		</ul>

		<div class="dlgButtons">
			<div style='float : left'>
			<button onclick="create_server()"><?php echo __('Add server') ?></button>
			<button onclick="delete_server()"><?php echo __('Delete') ?></button>
			</div>
			<button type="submit" onclick="save_conn()"><?php echo __('Save') ?></button>
			<button type="submit" onclick="show_prefs()"><?php echo __('Go back') ?></button></div>
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

			if ($line["status"] != "0") {
				$connected = __("(active)");
			} else {
				$connected = "";
			}

			print "<li id='C-$id' class='$row_class' connection_id='$id'>";
			print "<input type='checkbox' onchange='select_row(this)'
				row_id='C-$id'>";
			print "&nbsp;<a href=\"#\" title=\"".__('Click to edit connection')."\"
				onclick=\"edit_connection($id)\">".
				$line['title']." $connected</a>";
			print "</li>";

			++$lnum;
		}
	}

	function main_prefs($link) {

	$_SESSION["prefs_cache"] = false;

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

		<div id="mini-notice" style='display : none'>&nbsp;</div>

		<input type="hidden" name="op" value="prefs-save"/>

		<div class="dlgSec"><?php echo __('Personal data') ?></div>

		<div class="dlgSecCont">
			<label class="fixed"><?php echo __('Real name:') ?></label>
			<input name="realname" size="30" value="<?php echo $realname ?>">
			<br clear='left'/>

			<label class="fixed"><?php echo __('Nickname:') ?></label>
			<input name="nick" size="30" value="<?php echo $nick ?>">
			<br clear='left'/>

			<label class="fixed"><?php echo __('E-mail:') ?></label>
			<input name="email" size="30" value="<?php echo $email ?>">
			<br clear='left'/>

			<label class="fixed"><?php echo __('Quit message:') ?></label>
			<input name="quit_message" size="30" value="<?php echo $quit_message ?>">
			<br clear='left'/>

			<label class="fixed"><?php echo __('Theme:') ?></label>
			<?php print_theme_select($link); ?>

			<br clear='left'/>

		</div>

		<div class="dlgSec"><?php echo __('Authentication') ?></div>

		<div class="dlgSecCont">
			<label class="fixed"><?php echo __('New password:') ?></label>
			<input name="new_password" type="password" size="30" value="">
			<br clear='left'/>

			<label class="fixed"><?php echo __('Confirm:') ?></label>
			<input name="confirm_password" type="password" size="30" value="">
		</div>

		<button type="submit" style="display : none" onclick="save_prefs()">

		</form>

		<div class="dlgSec"><?php echo __('Connections') ?></div>

		<ul class="container" id="connections-list">
			<?php print_connections($link) ?>
		</ul>

		<div class="dlgButtons">
			<div style='float : left'>
				<button onclick="create_connection()">
					<?php echo __('Create connection') ?></button>
				<button onclick="delete_connection()">
					<?php echo __('Delete') ?></button>
			</div>
			<button type="submit" onclick="save_prefs()">
				<?php echo __('Save') ?></button>
			<button onclick="close_infobox()"><?php echo __('Close') ?></button>
		</div>		
	</div>

<?php } ?>
