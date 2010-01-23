var last_id = 0;
var delay = 1000;
var buffers = [];
var nicklists = [];
var li_classes = [];
var topics = [];
var active_nicks = [];
var conndata_last = [];

var colormap = ["#cfcfcf", "#000000", "#0000cc", "#00cc00", 
	 "#dd0000", "#aa0000", "#bb00bb", "#ffaa00", "#eedd22", 
	 "#33de55", "#00cccc", "#33eeff", "#0000ff", "#ee22ee", 
	 "#777777", "#999999", "#cfcfcf", "#000000", "#0000cc", 
	 "#00cc00", "#dd0000", "#aa0000", "#bb00bb", "#ffaa00", 
	 "#eedd22", "#33de55", "#00cccc", "#33eeff", "#0000ff", 
	 "#ee22ee", "#777777", "#999999", "#000000", "#a4dfff", 
	 "#dfdfdf", "#000000", "#cc1010", "#8c1010", "#0000ff", 
	 "#f50000", "#999999"];

var _colormap = ["#ffffff", "#000000", "#4141bd", "#40b883", 
	 "#fa0501", "#873742", "#eb05fc", "#fb9304", "#fbf708", 
	 "#03ff9e", "#67bdbd", "#81f4f6", "#5276e0", "#f704cc", 
	 "#858282", "#d2cece", "#d8ba79", "#806546", "#003296", 
	 "#796247", "#fc3200", "#976500", "#d30000", "#fb9304", 
	 "#c04343", "#e68003", "#0169c9", "#b3defd", "#067bcb", 
	 "#af8937", "#565248", "#fdd99b", "#fbf8f1", "#0169c9", 
	 "#555147", "#f4eee3", "#c50603", "#ab9071", "#f30404", 
	 "#e68003", "#959595"];

function create_tab_if_needed(chan, connection_id, tab_type) {
	try {
		var tab_id = "tab-" + chan + ":" + connection_id;

		if (!tab_type) tab_type = "C";

		var tab_caption_id = "tab-" + connection_id;
		var tab_list_id = "tabs-" + connection_id;

		if (!$(tab_caption_id)) {
			var tab = "<li id=\"" + tab_caption_id + "\" " +
				"channel=\"" + chan + "\" " +
				"tab_type=\"" + tab_type + "\" " +
				"connection_id=\"" + connection_id + "\" " +
		  		"onclick=\"change_tab(this)\">" + chan + "</li>";

			tab += "<ul class=\"sub-tabs\" id=\"" + tab_list_id + "\"></ul>";

			debug("creating tab+list: " + tab_id + " " + tab_type);

			$("tabs-list").innerHTML += tab;
		} else if (!$(tab_id) && tab_type != "S") {
			var tab = "<li id=\"" + tab_id + "\" " +
				"channel=\"" + chan + "\" " +
				"tab_type=\"" + tab_type + "\" " +
				"connection_id=\"" + connection_id + "\" " +
		  		"onclick=\"change_tab(this)\">" +
				"&nbsp;&nbsp;" + chan + "</li>";

			debug("creating tab: " + tab_id);

			$(tab_list_id).innerHTML += tab;
		}

/*		if (!$(tab_id)) {
			var tab = "<li id=\"" + tab_id + "\" " +
				"channel=\"" + chan + "\" " +
				"tab_type=\"" + tab_type + "\" " +
				"connection_id=\"" + connection_id + "\" " +
		  		"onclick=\"change_tab(this)\">" + chan + "</li>";

			debug("creating tab: " + tab_id);

			$("tabs-list").innerHTML += tab;
		} */

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

		$("input-prompt").value = "";
		$("input-prompt").focus();

		debug("init_second_stage");

		hide_spinner();

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

		show_spinner();

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
	
		var prev_last_id = last_id;
	
		for (var i = 0; i < lines.length; i++) {

			if (last_id < lines[i].id) {
	
				var chan = lines[i].channel;
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

				while (buffers[connection_id][chan].length > 100) {
					buffers[connection_id][chan].shift();
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

		if (!get_selected_tab()) {
			change_tab(get_all_tabs()[0]);
		}
	
		update_buffer();

	} catch (e) {
		exception_error("handle_update", e);
	}

	return true;
}

function update() {
	try {
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
		var tabs = $("tabs-list").getElementsByTagName("li");

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
		var tabs = $("tabs-list").getElementsByTagName("li");
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

		if (buffers[connection_id]) {

			var buffer = buffers[connection_id][channel];
	
			if (buffer) {

				/* do we need to redraw everything? */

				var log_channel = $("log-list").getAttribute("channel");
				var log_connection = $("log-list").getAttribute("connection_id");
				var log_line_id = parseInt($("log-list").getAttribute("last_id"));

				if (log_channel != channel || log_connection != connection_id) {
					var tmp = "";
					var line_id = 0;
					for (var i = 0; i < buffer.length; i++) {
						tmp += buffer[i][1];
						line_id = buffer[i][0];
					}

					$("log-list").innerHTML = tmp;

				} else {

					var line_id = parseInt(log_line_id);
					var tmp = "";

					for (var i = 0; i < buffer.length; i++) {
						var tmp_id = parseInt(buffer[i][0]);
						if (tmp_id > line_id) {
							tmp += buffer[i][1];
							line_id = tmp_id;
						}
					}

					$("log-list").innerHTML += tmp;

				}

				$("log-list").setAttribute("last_id", line_id);
				$("log-list").setAttribute("channel", channel);
				$("log-list").setAttribute("connection_id", connection_id);

				if (scroll_buffer) $("log").scrollTop = $("log").scrollHeight;
			} else {
				$("log-list").innerHTML = "&nbsp;";
			}
		}

		//show_nicklist(get_selected_buffer() != "---");

		if (nicklists[connection_id]) {

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
	
/*					if (nick == active_nicks[connection_id]) {
						nick = "<strong>" + nick + "</strong>";
					} */
	
					var tmp_html = "<li class=\""+row_class+"\">" + 
						nick_image + " " + nick + "</li>";
	
					$("userlist-list").innerHTML += tmp_html;
				}
			} else {
				$("userlist-list").innerHTML = "";
			}
		}

		if (topics[connection_id]) {
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
						$("topic-input").value = __("Disconnected.");
					}
				}
			}
		} else if (tab.getAttribute("tab_type") == "S") {
			$("topic-input").value = __("Disconnected.");
		}

		if (conndata_last && conndata_last[connection_id]) {
			$("input-prompt").disabled = conndata_last[connection_id].status != 2;
		}

		$("nick").innerHTML = active_nicks[connection_id];

		switch (conndata_last[connection_id].status) {
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

		$('connect-btn').setAttribute("connection_id", connection_id);

	} catch (e) {
		exception_error("update_buffer", e);
	}	
}

function send(elem, evt) {
	try {

     var key;

		if(window.event)
			key = window.event.keyCode;     //IE
		else
			key = evt.which;     //firefox

		if (key == 13) {

			var tab = get_selected_tab();
	
			if (!tab) return;
	
			var channel = tab.getAttribute("channel");
	
			if (tab.getAttribute("tab_type") == "S") channel = "---";
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
		}

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

		if (!elem) return;

		var tabs = $("tabs-list").getElementsByTagName("li");

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

function toggle_connection(elem) {
	try {

		elem.disabled = true;

		var query = "?op=toggle-connection&set_enabled=" + 
			param_escape(elem.getAttribute("set_enabled")) + 
			"&connection_id=" + param_escape(elem.getAttribute("connection_id"));

		debug(query);

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

		var color = "";

		if (param.sender_color) {
			color = "style=\"color : " + colormap[param.sender_color] + "\"";
		}

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
				param.ts + "</span><span class='sender' "+color+">&lt;" +
				param.sender + "&gt;</span><span class='message'>" + 
				param.message + "</span>";
		} else {
			tmp = "<li class=\""+row_class+"\"><span class='timestamp'>" + 
				param.ts + "</span>" +
				"<span class='sys-message'>" + 
				param.message + "</span>";
		}

		return [param.id, tmp];

	} catch (e) {
		exception_error("format_message", e);
	}
}

function handle_conn_data(conndata) {
	try {
		if (conndata != "") {
			for (var i = 0; i < conndata.length; i++) {

				create_tab_if_needed(conndata[i].title, conndata[i].id, "S");
				conndata_last[conndata[i].id] = conndata[i];

				if (conndata[i].status == "2") {
					active_nicks[conndata[i].id] = conndata[i].active_nick;
				} else {
					active_nicks[conndata[i].id] = [];
					nicklists[conndata[i].id] = [];
				}
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

			if (chandata[connection_id]) {
				if (!chandata[connection_id][chan] && 
						tabs[i].getAttribute("tab_type") != "S") {

//					if ($(tabs[i]).className == "selected") {
//						change_tab("tab----");
//					}
			
					$("tabs-list").removeChild(tabs[i]);
				}
			}
		}

		update_title(chandata);

	} catch (e) {
		exception_error("handle_chan_data", e);
	}
}

function update_title() {
	try {
		
		var tab = get_selected_tab();

		if (tab) {
			var title = __("Tiny Tiny IRC [%a @ %b / %c]");
			var connection_id = tab.getAttribute("connection_id");

			if (conndata_last[connection_id]) {
				title = title.replace("%a", active_nicks[connection_id]);
				title = title.replace("%b", conndata_last[connection_id].title);
				title = title.replace("%c", tab.getAttribute("channel"));
				document.title = title;
			} else {
				document.title = __("Tiny Tiny IRC");
			}

		} else {
			document.title = __("Tiny Tiny IRC");
		}

	} catch (e) {
		exception_error("update_title", e);
	}
}

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

function change_nick() {
	try {
		var tab = get_selected_tab();

		if (tab) {


		}
	} catch (e) {
		exception_error("change_nick", e);
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

function handle_action(elem) {
	try {
		debug("action: " + elem[elem.selectedIndex].value);

		elem.selectedIndex = 0;
	} catch (e) {
		exception_error("handle_action", e);
	}
}


