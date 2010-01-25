function show_users() {
	try {
		show_spinner();

		new Ajax.Request("backend.php", {
		parameters: "?op=users",
		onComplete: function (transport) {
			infobox_callback2(transport);
			hide_spinner();
		} });

	} catch (e) {
		exception_error("show_users", e);
	}
}

function create_user() {
	try {
		var login = prompt(__("Login for a new user:"));

		if (login) {

			show_spinner();

			new Ajax.Request("backend.php", {
			parameters: "?op=create-user&login=" + 
				param_escape(login),
			onComplete: function (transport) {
				$("users-list").innerHTML = transport.responseText;
				hide_spinner();
			} });


		}
	} catch (e) {
		exception_error("create_user", e);
	}
}

function delete_user() {
	try {
		var rows = get_selected_rows($("users-list"));

		if (rows.length > 0) {
			if (confirm(__("Delete selected users?"))) {

				var ids = [];

				for (var i = 0; i < rows.length; i++) {
					ids.push(rows[i].getAttribute("user_id"));
				}

				var query = "?op=delete-user&ids=" + param_escape(ids.toString());

				debug(query);

				show_spinner();

				new Ajax.Request("backend.php", {
				parameters: query, 
				onComplete: function (transport) {
					$("users-list").innerHTML = transport.responseText;
					hide_spinner();
				} });

			}
		} else {
			alert(__("Please select some users to delete."));
		}


	} catch (e) {
		exception_error("delete_user", e);
	}
}

