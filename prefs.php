	<div id="infoBoxTitle"><?php echo __("Preferences") ?></div>
	<div class="infoBoxContents">

		<form id="prefs_form" name="prefs_farm" onsubmit="return false;">

		<div class="dlgSec">Personal data</div>

		<div class="dlgSecCont">
			<label>Real name:</label>
			<input name="realname" value="">
			<br clear='left'/>

			<label>Nick:</label>
			<input name="realname" value="">
			<br clear='left'/>

			<label>E-mail:</label>
			<input name="email" value="">
			<br clear='left'/>
		</div>

		<div class="dlgSec">Authentication</div>

		<div class="dlgSecCont">
			<label>New password:</label>
			<input name="new_password" type="password" value="">
			<br clear='left'/>

			<label>Confirm:</label>
			<input name="confirm_password" type="password" value="">
		</div>

		</form>

		<div class="dlgSec">Connections</div>

		<div class="dlgSecCont">

			<label>Server:</label>
			<input name="server" size="20">

			<span>Port:</span>
			<input name="port" size="4">

			<button>Create</button>

		</div>

		<br clear='left'/>

			<ul class="container">
				<li class='odd'><input type='checkbox'></input>
					GBU (irc.volgo-balt.ru)</li>
				<li class='even'><input type='checkbox'></input>FIXME</li>
			</ul>

		<div class="dlgButtons">
			<div style='float : left'>
				<button>Edit connection</button>
				<button>Delete</button>
			</div>

			<button type="submit" onclick="save_prefs()">Save</button>
			<button type="submit" onclick="close_infobox()">Close</button></div>

		</form>
	</div>

