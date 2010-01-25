<?php
	require_once "functions.php";

	function format_users($link) {
		$result = db_query($link, "SELECT * FROM ttirc_users ORDER BY login");

		$lnum = 1;

		$tmp = "";

		while ($line = db_fetch_assoc($result)) {

			$row_class = ($lnum % 2) ? "odd" : "even";

			$id = $line['id'];

			$tmp .= "<li id='U-$id' class='$row_class' user_id='$id'>";
			$tmp .= "<input type='checkbox' onchange='select_row(this)'
				row_id='U-$id'>";
			$tmp .= "&nbsp;<a href=\"#\" title=\"Click to edit user\"
				onclick=\"edit_user($id)\">".
				$line['login']."</a>";
			$tmp .= "</li>";

			++$lnum;
		}

		return $tmp;

	}

	function show_users($link) {

	?>
	<div id="infoBoxTitle"><?php echo __("Edit Users") ?></div>
	<div class="infoBoxContents">
		<div id="mini-notice" style='display : none'>&nbsp;</div>

		<ul class="container" id="users-list">
			<?php echo format_users($link); ?>
		</ul>

		<div class="dlgButtons">
			<div style='float : left'>
				<button onclick="create_user()">Add user</button>
				<button onclick="delete_user()">Delete</button>
			</div>
			<button type="submit" onclick="close_infobox()">Close</button></div>
		</div>
	</div>
	<?php

	}
?>
