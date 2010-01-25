function save_prefs(callback) {
	try {
		var query = Form.serialize("prefs_form");

		debug(query);

		new Ajax.Request("backend.php", {
		parameters: query,
		onComplete: function (transport) {

			var obj = _eval(transport.responseText, true);

			if (obj) {
				if (obj.error) {
					mini_error(obj.error);
				} else if (obj.message == "THEME_CHANGED") {
					window.location.reload();
				}
			} else if (callback) {
				callback(obj);
			} else {
				close_infobox();
			}

			hide_spinner();
		} });

	} catch (e) {
		exception_error("save_prefs", e);
	}
	
	return false;
}

function show_prefs() {
	try {
		show_spinner();

		new Ajax.Request("backend.php", {
		parameters: "?op=prefs",
		onComplete: function (transport) {
			infobox_callback2(transport);
			hide_spinner();
		} });

	} catch (e) {
		exception_error("show_prefs", e);
	}
}

function edit_connection(id) {
	try {

		save_prefs(function (obj) {
			new Ajax.Request("backend.php", {
			parameters: "?op=prefs-edit-con&id=" + id,
			onComplete: function (transport) {
				infobox_callback2(transport);
			} });
		});

	} catch (e) {
		exception_error("show_prefs", e);
	}
}

function select_row(elem) {
	try {
		var row_id = elem.getAttribute("row_id");
		var checked = elem.checked;

		if ($(row_id)) {
			if (elem.checked) {
				$(row_id).className = $(row_id).className + "Selected";
			} else {
				$(row_id).className = $(row_id).className.replace("Selected", "");
			}
		}
	} catch (e) {
		exception_error("select_row", e);
	}
}	

function get_selected_rows(elem) {
	try {
		var rv = [];

		if (elem) {
			var boxes = elem.getElementsByTagName("input");

			for (var i = 0; i < boxes.length; i++) {
				if (boxes[i].type == "checkbox" && boxes[i].checked) {
					var row_id = boxes[i].getAttribute("row_id");
					rv.push($(row_id));
				}
			}
		}

		return rv;

	} catch (e) {
		exception_error("get_selected_rows", e);
	}
}

function delete_connection() {
	try {
		var rows = get_selected_rows($("connections-list"));

		if (rows.length > 0) {
			if (confirm(__("Delete selected connections? Active connections will not be deleted."))) {

				var ids = [];

				for (var i = 0; i < rows.length; i++) {
					ids.push(rows[i].getAttribute("connection_id"));
				}

				var query = "?op=delete-connection&ids=" + param_escape(ids.toString());

				debug(query);

				show_spinner();

				new Ajax.Request("backend.php", {
				parameters: query, 
				onComplete: function (transport) {
					$("connections-list").innerHTML = transport.responseText;
					hide_spinner();
				} });

			}
		} else {
			alert(__("Please select some connections to delete."));
		}


	} catch (e) {
		exception_error("delete_connection", e);
	}
}

function create_connection() {
	try {
		var title = prompt(__("Title for a new connection:"));

		if (title) {

			show_spinner();

			new Ajax.Request("backend.php", {
			parameters: "?op=create-connection&title=" + 
				param_escape(title),
			onComplete: function (transport) {
				$("connections-list").innerHTML = transport.responseText;
				hide_spinner();
			} });


		}
	} catch (e) {
		exception_error("create_connection", e);
	}
}

function save_conn(callback) {
	try {
		var query = Form.serialize("prefs_conn_form");

		debug(query);

		new Ajax.Request("backend.php", {
		parameters: query,
		onComplete: function (transport) {

			var obj = _eval(transport.responseText, true);

			if (obj && obj.error) {
				mini_error(obj.error);
			} else if (callback) {
				callback(obj);
			} else {
				//close_infobox();
				show_prefs();
			}

			hide_spinner();
		} });


	} catch (e) {
		exception_error("save_conn", e);
	}
}

function delete_server() {
	try {
		var rows = get_selected_rows($("servers-list"));

		if (rows.length > 0) {
			if (confirm(__("Delete selected servers?"))) {

				var connection_id = document.forms['prefs_conn_form'].connection_id.value;

				var ids = [];

				for (var i = 0; i < rows.length; i++) {
					ids.push(rows[i].getAttribute("server_id"));
				}

				var query = "?op=delete-server&ids=" + param_escape(ids.toString()) + 
					"&connection_id=" + param_escape(connection_id);

				debug(query);

				show_spinner();

				new Ajax.Request("backend.php", {
				parameters: query, 
				onComplete: function (transport) {
					$("servers-list").innerHTML = transport.responseText;
					hide_spinner();
				} });

			}
		} else {
			alert(__("Please select some servers to delete."));
		}


	} catch (e) {
		exception_error("delete_server", e);
	}
}

function create_server() {
	try {
		var data = prompt(__("Server:Port (e.g. irc.example.org:6667):"));

		if (data) {

			var connection_id = document.forms['prefs_conn_form'].connection_id.value;

			var query = "?op=create-server&data=" + param_escape(data) + 			
				"&connection_id=" + param_escape(connection_id);

			debug(query);

			show_spinner();

			new Ajax.Request("backend.php", {
			parameters: query, 
			onComplete: function (transport) {

				var obj = _eval(transport.responseText, true);

				if (obj && obj.error) {
					mini_error(obj.error);
				} else {
					$("servers-list").innerHTML = transport.responseText;
					mini_error();
				}
				hide_spinner();
			} });


		}
	} catch (e) {
		exception_error("create_connection", e);
	}
}


