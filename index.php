<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	require_once "functions.php"; 
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "version.php"; 
	require_once "config.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	login_sequence($link);

	$dt_add = get_script_dt_add();

	no_cache_incantation();

	header('Content-Type: text/html; charset=utf-8');
	
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" 
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>Tiny Tiny IRC</title>

	<link rel="stylesheet" type="text/css" href="tt-irc.css?<?php echo $dt_add ?>"/>

	<?php	$user_theme = get_user_theme_path($link);
		if ($user_theme) { ?>
			<link rel="stylesheet" type="text/css" href="<?php echo $user_theme ?>/theme.css?<?php echo $dt_add ?>">
	<?php } ?>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png"/>
		
	<script type="text/javascript" charset="utf-8" src="localized_js.php?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" src="lib/prototype.js"></script>
	<script type="text/javascript" src="lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls"></script>
	<script type="text/javascript" charset="utf-8" src="tt-irc.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" charset="utf-8" src="functions.js?<?php echo $dt_add ?>"></script>
	
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

	<script type="text/javascript">
		Event.observe(window, 'load', function() {
			init();
		});
	</script>
</head>
<body class="main">

<div id="overlay" style="display : block">
	<div id="overlay_inner">
		<?php echo __("Loading, please wait...") ?>

		<div id="l_progress_o">
			<div id="l_progress_i"></div>
		</div>

	<noscript>
		<p><?php print_error(__("Your browser doesn't support Javascript, which is required
		for this application to function properly. Please check your
		browser settings.")) ?></p>
	</noscript>
	</div>
</div> 

<div id="dialog_overlay" style="display : none"> </div>

<ul id="debug_output" style='display : none'><li>&nbsp;</li></ul>

<div id="infoBoxShadow" style="display : none"><div id="infoBox">&nbsp;</div></div>

<div id="errorBoxShadow" style="display : none">
	<div id="errorBox">
	<div id="xebTitle"><?php echo __('Fatal Exception') ?></div><div id="xebContent">&nbsp;</div>
		<div id="xebBtn" align='center'>
			<button onclick="closeErrorBox()"><?php echo __('Close this window') ?></button>
		</div>
	</div>
</div>

<div id="prefs" style="display : none">
	<div class="pref-title"><?php echo __("Preferences") ?></div>
	<div class="pref-content">

		<form id="prefs_form" name="prefs_farm" onsubmit="return false;">

		<h1>Personal Data</h1>

		<fieldset>
		<label>E-mail:</label>
		<input name="email" value="">
		<br clear="left"/>
		</fieldset>

		<fieldset>
		<label>Change password:</label>
		<input name="new_password" type="password" value="">
		confirm:
		<input name="confirm_password" type="password" value="">
		</fieldset>

		<fieldset>
		<label>Quit message:</label>
		<input name="quit_message" size="30" value="">
		</fieldset>

		<p><button type="submit" onclick="prefs_save()">Save preferences</button></p>
		</form>
	</div>
</div>

<div id="header">
	<div class="topLinks" id="topLinks">

	<span id="topLinksOnline">

	<?php if (!SINGLE_USER_MODE) { ?>
			<?php echo __('Hello,') ?> <b><?php echo $_SESSION["name"] ?></b> |
	<?php } ?>
	<a href="#" onclick="show_prefs()"><?php echo __('Preferences') ?></a>

	<?php if (!SINGLE_USER_MODE) { ?>
			| <a href="logout.php"><?php echo __('Logout') ?></a>
	<?php } ?>

	</div>

	<img src="<?php echo theme_image($link, 'images/logo.png') ?>" alt="Tiny Tiny IRC"/>	
</div>

<div id="actions">
	<button onclick="toggle_debug()">Debug</button> 
	<!-- <button id="connect-btn" disabled='true' c_status="0" onclick="toggle_connect(this)">
		<?php echo __("Connect") ?></button> -->
</div>

<div id="tabs">
	<div class="first">&nbsp;</div>
	<!-- <div class="selected" onclick="change_tab(this.id)" id="tab----">Console</div> -->
</div>

<div id="content">
	<div id="topic"><div class="wrapper">
		<input disabled id="topic-input" value="">
	</div>
	</div>
	<div id="log"><ul id="log-list"></ul></div>	
	<div id="input"><div class="wrapper">
		<input id="input-prompt" onchange="send(this)"></input>
	</div></div>
	<div id="userlist">
		<div id="userlist-inner"><ul id="userlist-list"></ul></div>
	</div>
</div>

<?php db_close($link); ?>

</body>
</html>
