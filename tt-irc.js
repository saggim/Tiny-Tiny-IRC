var last_id = 0;
var delay = 1000;
var buffers = [];
var nicklists = [];
var li_classes = [];
var topics = [];

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
			$("log-outer").style.right = ($("userlist").offsetWidth + 0) + "px";
			$("input").style.right = ($("userlist").offsetWidth + 0) + "px";

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

//		last_id = params.max_id;

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

function handle_update(transport) {
	try {

		var rv = _eval(transport.responseText);

		if (!handle_error(rv)) return false;

		var params = rv[0];
		var lines = rv[1];
		var nicks = rv[2];

		if (nicks != "") {
			for (var chan in nicks) {
				create_tab_if_needed(chan);
				nicklists[chan] = nicks[chan]["users"];
				topics[chan] = nicks[chan]["topic"];
			}
		}

		switch (params[0].status) {
			case "0":
				$('connect-btn').innerHTML = __("Connect");
				$('connect-btn').disabled = false;
				$('connect-btn').setAttribute("set_enabled", 1);
				break;
			case "1":
				$('connect-btn').innerHTML = __("Connecting...");
				$('connect-btn').disabled = true;
				$('connect-btn').setAttribute("set_enabled", 0);
				break;
			case "2":
				$('connect-btn').innerHTML = __("Disconnect");
				$('connect-btn').disabled = false;
				$('connect-btn').setAttribute("set_enabled", 0);
				break;
		}
	
		var prev_last_id = last_id;
	
		for (var i = 0; i < lines.length; i++) {
	
			var chan = lines[i].destination;
			var tab_id = "tab-" + lines[i].destination;
	
			if (!li_classes[chan]) {
				li_classes[chan] = "odd";
			} else {
				if (li_classes[chan] == "odd") {
					li_classes[chan] = "even";
				} else {
					li_classes[chan] = "odd";
				}
			}

			//lines[i].message += + lines[i].id + "/" + last_id;

			var tmp_html = format_message(li_classes[chan],
				lines[i]);

			if (lines[i].message_type != 2) {
				if (buffers[chan]) {
					buffers[chan].push(tmp_html);
				} else {
					buffers[chan] = [tmp_html];
				}
			} else {
				for (var b in buffers) {
					if (typeof buffers[b] == 'object') {
						buffers[b].push(tmp_html);
					}
				}
			}

			if (get_selected_buffer() != chan && $(tab_id)) {
				$(tab_id).className = "attention";
			}

			last_id = lines[i].id;
		}
	
		if (prev_last_id == last_id) {
			if (delay < 3000) delay += 150;
		} else {
			delay = 1000;
		}
	
		update_buffer();
	
		debug("delay = " + delay);

	} catch (e) {
		exception_error("handle_update", e);
	}

	return true;
}

function update() {
	try {
		debug("update...");		

		new Ajax.Request("backend.php", {
		parameters: "?op=update&last_id=" + last_id + 
			"&active=" + param_escape(get_selected_buffer()),
		onComplete: function (transport) {
	
			if (!handle_update(transport)) return;

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

		if (test_height - $("log").scrollTop < 50) scroll_buffer = true;


		var buffer = buffers[buf_id];

		if (buffer) {
			var tmp = "";

			for (var i = 0; i < buffer.length; i++) {
				tmp += buffer[i];
			}

			$("log-list").innerHTML = tmp;

			if (scroll_buffer) $("log").scrollTop = $("log").scrollHeight;
		} else {
			$("log-list").innerHTML = "&nbsp;";
		}

		show_nicklist(get_selected_buffer() != "---");

		var nicklist = nicklists[buf_id];

		if (nicklist) {

			$("userlist-list").innerHTML = "";

			for (var i = 0; i < nicklist.length; i++) {				

				var row_class = (i % 2) ? "even" : "odd";

				var tmp_html = "<li class=\""+row_class+"\">" + 
					nicklist[i] + "</li>";

				$("userlist-list").innerHTML += tmp_html;
			}
		} else {
			$("userlist-list").innerHTML = "";
		}

		var topic = topics[buf_id];

		if (topic) {
			$("topic-input").value = topics[buf_id][0];
		}

	} catch (e) {
		exception_error("update_buffer", e);
	}	
}

function send(elem) {
	try {

		var query = "?op=send&message=" + param_escape(elem.value) + 
			"&chan=" + param_escape(get_selected_buffer()) +
			"&last_id=" + last_id + 
			"&active=" + param_escape(get_selected_buffer());

		debug(query);

		new Ajax.Request("backend.php", {
		parameters: query,
		onComplete: function (transport) {
			elem.value = '';
			handle_update(transport);
		} });

	} catch (e) {
		exception_error("send", e);
	}
}

function handle_error(obj) {
	try {
		if (obj && obj.error) {
			return fatal_error(obj.error, obj.errormsg);
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

		$("input-prompt").focus();

		update_buffer();
	} catch (e) {
		exception_error("change_tab", e);
	}
}

function toggle_connect(elem) {
	try {

		elem.disabled = true;

		var query = "?op=toggle-connection&set_enabled=" + 
			elem.getAttribute("set_enabled");

		new Ajax.Request("backend.php", {
		parameters: query, 
		onComplete: function (transport) {
			delay = 500;
		} });

	} catch (e) {
		exception_error("change_tab", e);
	}
}

function format_message(row_class, param) {
	try {

		var tmp;

		if (param.message_type == 4) {
			var message = param.sender + " has changed the topic to: " + 
				param.message;

			tmp = "<li class=\""+row_class+"\"><span class='timestamp'>" + 
				param.ts + "</span>" +
				"<span class='sys-message'>" + message + "</span>";

		} else if (param.sender != "---") {
			tmp = "<li class=\""+row_class+"\"><span class='timestamp'>" + 
				param.ts + "</span><span class='sender'>&lt;" +
				param.sender + "&gt;</span><span class='message'>" + 
				param.message + "</span>";
		} else {
			tmp = "<li class=\""+row_class+"\"><span class='timestamp'>" + 
				param.ts + "</span>" +
				"<span class='sys-message'>" + 
				param.message + "</span>";
		}

		return tmp;

	} catch (e) {
		exception_error("format_message", e);
	}
}
