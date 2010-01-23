function save_prefs() {
	try {
		var query = Form.serialize("prefs_form");

		alert(query);

	} catch (e) {
		exception_error("save_prefs", e);
	}
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
		new Ajax.Request("backend.php", {
		parameters: "?op=prefs-edit-con&id=" + id,
		onComplete: function (transport) {
			infobox_callback2(transport);
		} });

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
			if (confirm(__("Delete selected connections?"))) {

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
		exception_error("create_connection", e);
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

