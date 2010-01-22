var last_id = 0;
var delay = 1000;
var buffers = [];
var nicklists = [];
var li_classes = [];
var topics = [];
var active_nicks = [];
var conndata_last = [];

function create_tab_if_needed(chan, connection_id, tab_type) {
	try {
		var tab_id = "tab-" + chan + ":" + connection_id;

		if (!tab_type) tab_type = "C";

		if (!$(tab_id)) {
			var tab = "<div id=\"" + tab_id + "\" " +
				"channel=\"" + chan + "\" " +
				"tab_type=\"" + tab_type + "\" " +
				"connection_id=\"" + connection_id + "\" " +
		  		"onclick=\"change_tab(this)\">" + chan + "</div>";

			debug(tab);

			$("tabs").innerHTML += tab;
		}

		return tab_id;

	} catch (e) {
		exception_error("create_tab_if_needed", e);
	}
}

function show_nicklist(show) {
/*	try {
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
	} */
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

		var conn_data = rv[0];
		var lines = rv[1];
		var chandata = rv[2];

		handle_conn_data(conn_data);
		handle_chan_data(chandata);

/*		switch (params[0].status) {
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

		my_nick = params[0].active_nick; */
	
		var prev_last_id = last_id;
	
		for (var i = 0; i < lines.length; i++) {

			if (last_id < lines[i].id) {
	
				var chan = lines[i].destination;
				var connection_id = lines[i].connection_id;
		
				if (!li_classes[chan]) {
					li_classes[chan] = "odd";
				} else {
					if (li_classes[chan] == "odd") {
						li_classes[chan] = "even";
					} else {
						li_classes[chan] = "odd";
					}
				}
	
				//lines[i].message += " [" + lines[i].id + "/" + last_id + "]";
	
				var tmp_html = format_message(li_classes[chan],
					lines[i]);
	
				if (!buffers[connection_id]) buffers[connection_id] = [];
				if (!buffers[connection_id][chan]) buffers[connection_id][chan] = [];

				if (lines[i].message_type != 2) {
					buffers[connection_id][chan].push(tmp_html);
				} else {
					for (var b in buffers[connection_id]) {
						if (typeof buffers[connection_id][b] == 'object') {
							buffers[connection_id][b].push(tmp_html);						
						}
					}
				}

				var tabs = get_all_tabs();

				for (var j = 0; j < tabs.length; j++) {
					var tab = tabs[j];

					if (tab.getAttribute("connection_id") == connection_id &&
							tab.getAttribute("channel") == chan && 
							tab != get_selected_tab()) {

						tab.className = "attention";
					}
				}	

			}

			last_id = lines[i].id;
		}
	
		if (prev_last_id == last_id) {
			if (delay < 4000) delay += 100;
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
		parameters: "?op=update&last_id=" + last_id,
		onComplete: function (transport) {
	
			if (!handle_update(transport)) return;

			window.setTimeout("update()", delay);

		} });

	} catch (e) {
		exception_error("update", e);
	}
}

function get_selected_tab() {
	try {
		var tabs = $("tabs").getElementsByTagName("div");

		for (var i = 0; i < tabs.length; i++) {
			if (tabs[i].className == "selected") {
				return tabs[i];
			}
		}

		return false;

	} catch (e) {
		exception_error("get_selected_tab", e);
	}
}

function get_all_tabs() {
	try {
		var tabs = $("tabs").getElementsByTagName("div");
		var rv = [];

		for (var i = 0; i < tabs.length; i++) {
			if (tabs[i].id && tabs[i].id.match("tab-")) {
				rv.push(tabs[i]);
			}
		}

		return rv;

	} catch (e) {
		exception_error("get_all_tabs", e);
	}
}

function update_buffer() {
	try {
		var tab = get_selected_tab();

		if (!tab) return;

		var channel = tab.getAttribute("channel");

		if (tab.getAttribute("tab_type") == "S") channel = "---";

		var connection_id = tab.getAttribute("connection_id");

		var test_height = $("log").scrollHeight - $("log").offsetHeight;
		var scroll_buffer = false;

		if (test_height - $("log").scrollTop < 50) scroll_buffer = true;

		var buffer = buffers[connection_id][channel];

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

		//show_nicklist(get_selected_buffer() != "---");

		var nicklist = nicklists[connection_id][channel];

		if (nicklist) {

			$("userlist-list").innerHTML = "";

			for (var i = 0; i < nicklist.length; i++) {				

				var row_class = (i % 2) ? "even" : "odd";

				var nick_image = "<img src=\"images/user_normal.png\" alt=\"\">";
				var nick = nicklist[i];

				switch (nick.substr(0,1)) {
				case "@":
					nick_image = "<img src=\"images/user_op.png\" alt=\"\">";
					nick = nick.substr(1);
					break;
				case "+":
					nick_image = "<img src=\"images/user_voice.png\" alt=\"\">";
					nick = nick.substr(1);
					break;
				}

				if (nick == active_nicks[connection_id]) {
					nick_image = "<img src=\"images/user_me.png\" alt=\"\">";
				} 

				var tmp_html = "<li class=\""+row_class+"\">" + 
					nick_image + " " + nick + "</li>";

				$("userlist-list").innerHTML += tmp_html;
			}
		} else {
			$("userlist-list").innerHTML = "";
		}

		var topic = topics[connection_id][channel];

		if (topic) {
			$("topic-input").value = topics[connection_id][channel][0];
		} else {

			if (tab.getAttribute("tab_type") != "S") {
				$("topic-input").value = "";
			} else {
				if (conndata_last[connection_id].status == "2") {
					$("topic-input").value = __("Connected to: ") + 
						conndata_last[connection_id]["active_server"];
				} else {
					$("topic-input").value = "";		
				}
			}
		}

		if (conndata_last && conndata_last[connection_id]) {
			$("input-prompt").disabled = conndata_last[connection_id].status != 2;
		}

	} catch (e) {
		exception_error("update_buffer", e);
	}	
}

function send(elem) {
	try {

		var tab = get_selected_tab();
		var channel = tab.getAttribute("channel");

		if (tab.getAttribute("tab_type") == "S") channel = "---";

		if (!tab) return;

		var query = "?op=send&message=" + param_escape(elem.value) + 
			"&chan=" + param_escape(channel) +			
			"&connection=" + param_escape(tab.getAttribute("connection_id")) +
			"&last_id=" + last_id;

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

		update_buffer();

		$("input-prompt").focus();

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
			var message = param.sender + __(" has changed the topic to: ") + 
				param.message;

			tmp = "<li class=\""+row_class+"\"><span class='timestamp'>" + 
				param.ts + "</span>" +
				"<span class='sys-message'>" + message + "</span>";
		} else if (param.message_type == 3) {

			message = "* " + param.sender + " " + param.message;

			tmp = "<li class=\""+row_class+"\"><span class='timestamp'>" + 
				param.ts + "</span>" +
				"<span class='action'>" + message + "</span>";

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

function handle_conn_data(conndata) {
	try {
		if (conndata != "") {
			for (var i = 0; i < conndata.length; i++) {
				create_tab_if_needed(conndata[i].title, conndata[i].id, "S");
				active_nicks[conndata[i].id] = conndata[i].active_nick;
				conndata_last[conndata[i].id] = conndata[i];
			}			
		} else {
			conndata_last = [];
		}
	} catch (e) {
		exception_error("handle_conn_data", e);
	}
}

function handle_chan_data(chandata) {
	try {
		if (chandata != "") {
			for (var connection_id in chandata) {

				if (!nicklists[connection_id]) nicklists[connection_id] = [];
				if (!topics[connection_id]) topics[connection_id] = [];

				for (var chan in chandata[connection_id]) {
					create_tab_if_needed(chan, connection_id);

					nicklists[connection_id][chan] = chandata[connection_id][chan]["users"];
					topics[connection_id][chan] = chandata[connection_id][chan]["topic"];
				}
			}
		}

		var tabs = get_all_tabs();

		for (var i = 0; i < tabs.length; i++) {
			var chan = tabs[i].getAttribute("channel");
			var connection_id = tabs[i].getAttribute("connection_id");

			if (!chandata[connection_id][chan] && tabs[i].getAttribute("tab_type") != "S") {

//				if ($(tabs[i]).className == "selected") {
//					change_tab("tab----");
//				}
			
				$("tabs").removeChild(tabs[i]);
			}
		}

	} catch (e) {
		exception_error("handle_chan_data", e);
	}
}

function show_prefs() {
	try {
		if (Element.visible("prefs")) {
			Element.hide("dialog_overlay");
			Element.hide("prefs");

		} else {
			Element.show("dialog_overlay");
			Element.show("prefs");

		}

	} catch (e) {
		exception_error("show_prefs", e);
	}
}
