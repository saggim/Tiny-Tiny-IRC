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
		<script type="text/javascript" charset="utf-8" src="prefs.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" charset="utf-8" src="users.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" charset="utf-8" src="functions.js?<?php echo $dt_add ?>"></script>

	<?php	$user_theme = get_user_theme_path($link);
		if ($user_theme) { ?>
			<link rel="stylesheet" type="text/css" href="<?php echo $user_theme ?>/theme.css?<?php echo $dt_add ?>">
	<?php } ?>


	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

	<script type="text/javascript">
		Event.observe(window, 'load', function() {
			init();
		});

		Event.observe(window, 'focus', function() {
			set_window_active(true);
		});

		Event.observe(window, 'blur', function() {
			set_window_active(false);
		});

	</script>
</head>
<body class="main">

<div id="image-tooltip" style="display : none"></div>

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

<div id="header">
	<div class="topLinks" id="topLinks">

	<img id="spinner" style="display : none" 
		alt="spinner" title="Loading..."
		src="<?php echo theme_image($link, 'images/indicator_tiny.gif') ?>"/>

	<?php if (!SINGLE_USER_MODE) { ?>
			<?php echo __('Hello,') ?> <b><?php echo $_SESSION["name"] ?></b> |
	<?php } ?>
	<a href="#" onclick="show_prefs()"><?php echo __('Preferences') ?></a>

	<?php if ($_SESSION["access_level"] >= 10) { ?>
	| <a href="#" onclick="show_users()"><?php echo __('Users') ?></a>
	<?php } ?>

	<?php if (!SINGLE_USER_MODE) { ?>
			| <a href="logout.php"><?php echo __('Logout') ?></a>
	<?php } ?>

	| <a href="#" onclick="toggle_debug()">Debug</a>

	</div>

	<img src="<?php echo theme_image($link, 'images/logo.png') ?>" alt="Tiny Tiny IRC"/>	
</div>

<div id="actions">
	<select onchange="handle_action(this)" disabled>
		<option value="">Actions...</option>
		<option value="cmd_nick">Change nick</option>
		<optgroup label="Channel">
			<option value="cmd_join">Join</option>
			<option value="cmd_part">Part</option>
			<option value="cmd_mode">Change mode</option>
		</optgroup>
		<optgroup label="Server">
			<option value="cmd_connect" id="cmd_connect">Connect</option>
			<option value="cmd_disconnect">Disconnect</option>
		</optgroup>
	</select>
</div>

<div id="tabs">
	<div id="tabs-inner"><ul id="tabs-list"></ul></div>
</div>

<div id="content">
	<div id="topic"><div class="wrapper">
		<input disabled onkeypress="change_topic(this, event)" 
			id="topic-input" value=""></div>
	</div>
	<div id="connect"><button onclick="toggle_connection(this)" 
		id="connect-btn">Connect</button></div>
	<div id="log"><ul id="log-list"></ul></div>	
	<div id="nick" onclick="change_nick()"></div>
	<div id="input"><div class="wrapper">
		<input disabled="true" id="input-prompt" onkeypress="send(this, event)"></input>
	</div></div>
	<div id="userlist">
		<div id="userlist-inner"><ul id="userlist-list"></ul></div>
	</div>
</div>

<?php db_close($link); ?>

</body>
</html>
