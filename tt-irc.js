var last_id = 0;
var delay = 1000;
var buffers = [];
var nicklists = [];

function create_tab_if_needed(chan) {
	try {
		var tab_id = "tab-" + chan;
	
		if (!$(tab_id)) {
			$("tabs").innerHTML += "<div id=\"" + tab_id + "\" " +
				"channel=\"" + chan + "\" " +
		  		"onclick=\"change_tab(this)\">" + chan + "</div>";
		}

		return tab_id;

	} catch (e) {
		exception_error("create_tab_if_needed", e);
	}
}

function show_nicklist(show) {
	try {
		debug("show_nicklist: " + show);
		if (show) {
			Element.show("userlist");
			$("log-outer").style.right = ($("userlist").offsetWidth + 1) + "px";
			$("input").style.right = ($("userlist").offsetWidth + 1) + "px";

		} else {
			Element.hide("userlist");
			$("log-outer").style.right = "0px";
			$("input").style.right = "0px";

		}
	} catch (e) {
		exception_error("show_nicklist", e);
	}
}

function init_second_stage(transport) {
	try {

		var params = _eval(transport.responseText);

		last_id = params.max_id;

		Element.hide("overlay");

		$("input-prompt").focus();

		debug("init_second_stage");

		update();

	} catch (e) {
		exception_error("init_done", e);
	}
}

function init() {
	try {
		if (getURLParam('debug')) {
			Element.show("debug_output");
			debug('debug mode activated');
		}

		new Ajax.Request("backend.php", {
		parameters: "?op=init",
		onComplete: function (transport) {
			init_second_stage(transport);
		} });

	} catch (e) {
		exception_error("init", e);		
	}
}

function _eval(data) {
	try {
		return eval("(" + data + ")");
	} catch (e) {
		exception_error("_eval", e, data);
	}
}

function update() {
	try {
		debug("update...");		

		new Ajax.Request("backend.php", {
		parameters: "?op=update&last_id=" + last_id + 
			"&active=" + param_escape(get_selected_buffer()),
		onComplete: function (transport) {
			try {
				var rv = _eval(transport.responseText);

				if (!handle_error(rv)) return;

				var params = rv[0];
				var lines = rv[1];
				var nicks = rv[2];

				if (nicks != "") {
					for (var chan in nicks) {
						create_tab_if_needed(chan);
						nicklists[chan] = nicks[chan];
					}
				}
	
				if (params[0].active != 't') {
					$('connect-btn').innerHTML = "Connect";
					$('connect-btn').status = 0;

				} else {
					$('connect-btn').innerHTML = "Disconnect";
					$('connect-btn').status = 1;
				}

				$('connect-btn').disabled = false;
				$('input-prompt').disabled = (params[0].active != 't');
	
				var tmp_last_id = last_id;
	
				for (var i = 0; i < lines.length; i++) {
	
					var chan = lines[i].destination;
					var tab_id = create_tab_if_needed(lines[i].destination);
			
					var tmp_html = "<li><span class='timestamp'>" + 
						lines[i].ts + "</span><span class='sender'>&lt;" +
						lines[i].sender + "&gt;</span><span class='message'>" + 
						lines[i].message + "</span>";
	
					if (buffers[chan]) {
						buffers[chan].push(tmp_html);
					} else {
						buffers[chan] = [tmp_html];
					}

					while (buffers[chan].length > 1000) {
						buffers[chan].shift();
					}

					if (get_selected_buffer() != chan) {
						$(tab_id).className = "attention";
					}

					tmp_last_id = lines[i].id;
				}
	
				if (tmp_last_id == last_id) {
					if (delay < 3000) delay += 150;
				} else {
					delay = 1000;
					last_id = tmp_last_id;
				}
	
				update_buffer();
	
				debug("delay = " + delay);

			} catch (e) {
				exception_error("update/onComplete", e);
			}

			window.setTimeout("update()", delay);

		} });

	} catch (e) {
		exception_error("update", e);
	}
}

function get_selected_buffer() {
	try {
		var tabs = $("tabs").getElementsByTagName("div");

		for (var i = 0; i < tabs.length; i++) {
			if (tabs[i].className == "selected") return tabs[i].id.replace("tab-", "");
		}

		return false;

	} catch (e) {
		exception_error("get_selected_buffer", e);
	}
}

function update_buffer() {
	try {
		var buf_id = get_selected_buffer();

		var test_height = $("log").scrollHeight - $("log").offsetHeight;
		var scroll_buffer = false;

		if (test_height == $("log").scrollTop) scroll_buffer = true;

		var buffer = buffers[buf_id];

		if (buffer) {
			var tmp = "";

			for (var i = 0; i < buffer.length; i++) {
				tmp += buffer[i];
			}

			$("log-list").innerHTML = tmp;

			if (scroll_buffer) $("log").scrollTop = $("log").scrollHeight;
		}


		show_nicklist(get_selected_buffer() != "---");

		var nicklist = nicklists[buf_id];

		if (nicklist) {

			$("userlist-list").innerHTML = "";

			for (var i = 0; i < nicklist.length; i++) {				
				var tmp_html = "<li>" + nicklist[i] + "</li>";
				$("userlist-list").innerHTML += tmp_html;
			}
		}

	} catch (e) {
		exception_error("update_buffer", e);
	}	
}

function send(elem) {
	try {

		new Ajax.Request("backend.php", {
		parameters: "?op=send&message=" + param_escape(elem.value) + 
			"&chan=" + param_escape(get_selected_buffer()),
		onComplete: function (transport) {
			elem.value = '';
			delay = 100;
		} });

	} catch (e) {
		exception_error("send", e);
	}
}

function handle_error(obj) {
	try {
		if (obj && obj.error) {
			return fatal_error(obj.error);
		}
		return true;
	} catch (e) {
		exception_error("handle_error", e);
	}
}

function change_tab(elem) {
	try {
		var tabs = $("tabs").getElementsByTagName("div");

		for (var i = 0; i < tabs.length; i++) {
			if (tabs[i].className == "selected") tabs[i].className = "";
		}

		elem.className = "selected";

		update_buffer();
	} catch (e) {
		exception_error("change_tab", e);
	}
}

function toggle_connect(elem) {
	try {

		elem.disabled = true;

		new Ajax.Request("backend.php", {
		parameters: "?op=toggle-connection&status=" + elem.status,
		onComplete: function (transport) {
			delay = 500;
		} });

	} catch (e) {
		exception_error("change_tab", e);
	}
}
