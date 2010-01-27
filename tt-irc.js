var window_active = false;
var last_id = 0;
var last_active_id = 0;
var delay = 1000;
var buffers = [];
var nicklists = [];
var li_classes = [];
var topics = [];
var active_nicks = [];
var conndata_last = [];
var last_update = false;

var MSGT_PRIVMSG = 0;
var MSGT_COMMAND = 1;
var MSGT_BROADCAST = 2;
var MSGT_ACTION = 3;
var MSGT_TOPIC = 4;
var MSGT_PRIVATE_PRIVMSG = 5;
var MSGT_EVENT = 6;

var CS_DISCONNECTED = 0;
var CS_CONNECTING = 1;
var CS_CONNECTED = 2;

var CT_CHANNEL = 0;
var CT_PRIVATE = 1;

var colormap = [ "#00CCCC", "#000000", "#0000CC", "#CC00CC", "#606060", 
	"green", "#00CC00", "maroon", "navy", "olive", "purple", 
	"red", "#909090", "teal", "#CCCC00" ]

function create_tab_if_needed(chan, connection_id, tab_type) {
	try {
		var tab_id = "tab-" + chan + ":" + connection_id;

		if (!tab_type) tab_type = "C";

		var tab_caption_id = "tab-" + connection_id;
		var tab_list_id = "tabs-" + connection_id;

		if (!$(tab_caption_id)) {

			var cimg = "<img class=\"conn-img\" "+
				"src=\"images/srv_offline.png\" alt=\"\" " +
				"id=\"cimg-" + connection_id + "\">";

			var tab = "<li id=\"" + tab_caption_id + "\" " +
				"channel=\"" + chan + "\" " +
				"tab_type=\"" + tab_type + "\" " +
				"connection_id=\"" + connection_id + "\" " +
		  		"onclick=\"change_tab(this)\">" + cimg +
				chan + "</li>";

			tab += "<ul class=\"sub-tabs\" id=\"" + tab_list_id + "\"></ul>";

			debug("creating tab+list: " + tab_id + " " + tab_type);

			$("tabs-list").innerHTML += tab;
		} else if (!$(tab_id) && tab_type != "S") {

			var img = "<img class=\"conn-img\" "+
				"src=\"images/close_tab.png\" alt=\"[X]\" " +
				"title=\"" + __("Close this tab") + "\"" +
				"tab_id=\"" + tab_id + "\"" +
				"onclick=\"close_tab(this)\">";

			var tab = "<li id=\"" + tab_id + "\" " +
				"channel=\"" + chan + "\" " +
				"tab_type=\"" + tab_type + "\" " +
				"connection_id=\"" + connection_id + "\" " +
		  		"onclick=\"change_tab(this)\">" + img +
				"&nbsp;&nbsp;" + chan + "</li>";

			debug("creating tab: " + tab_id + " " + tab_type);

			$(tab_list_id).innerHTML += tab;

			sort_connection_tabs($(tab_list_id));
			
			var tab = $(tab_id);

			if (tab && tab_type =="C") change_tab(tab);
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

		if (!params || params.status != 1) {
			return fatal_error(14, __("The application failed to initialize."), 
				transport.responseText);
		}

//		last_id = params.max_id;

		Element.hide("overlay");

		$("input-prompt").value = "";
		$("input-prompt").focus();

		debug("init_second_stage");

		hide_spinner();

		update(true);

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

function _eval(data, silent) {
	try {
		return eval("(" + data + ")");
	} catch (e) {
		if (!silent) exception_error("_eval", e, data);
	}
}

function handle_update(transport) {
	try {

		var rv = _eval(transport.responseText, true);

		if (!rv) {
			debug("received null object from server, will try again.");
			return true;
		}

		if (!handle_error(rv, transport)) return false;

		var conn_data = rv[0];
		var lines = rv[1];
		var chandata = rv[2];

		last_update = new Date();

		handle_conn_data(conn_data);
		handle_chan_data(chandata);
	
		var prev_last_id = last_id;
	
		for (var i = 0; i < lines.length; i++) {

			if (last_id < lines[i].id) {

//				debug("processing line ID " + lines[i].id);

				var chan = lines[i].channel;
				var connection_id = lines[i].connection_id;
		
				//lines[i].message += " [" + lines[i].id + "/" + last_id + "]";

				if (lines[i].message_type == MSGT_EVENT) {
					handle_event(li_classes[chan], connection_id, lines[i]);
				} else {
					push_message(connection_id, chan, lines[i], lines[i].message_type);
				}

				while (buffers[connection_id][chan].length > 100) {
					buffers[connection_id][chan].shift();
				}

				var tabs = get_all_tabs(connection_id);

				for (var j = 0; j < tabs.length; j++) {
					var tab = tabs[j];

					if (tab.getAttribute("channel") == chan && 
							tab != get_selected_tab()) {

						tab.className = "attention";
					}
				}	

			}

			last_id = lines[i].id;

			if (window_active) last_active_id = last_id;
		}
	
/*		if (prev_last_id == last_id) {
			if (delay < 4000) delay += 100;
		} else {
			delay = 1000;
		} */

		if (!get_selected_tab()) {
			change_tab(get_all_tabs()[0]);
		}
	
		update_buffer();

	} catch (e) {
		exception_error("handle_update", e);
	}

	return true;
}

function update(init) {
	try {
		var query = "?op=update&last_id=" + last_id;
	  		
		if (init) query += "&init=" + init;

		debug("request update..." + query + " last: " + last_update);

		new Ajax.Request("backend.php", {
		parameters: query,
		onComplete: function (transport) {
			if (!handle_update(transport)) return;
			debug("update done.");
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

function get_all_tabs(connection_id) {
	try {
		var tabs;

		if (connection_id) {
			tabs = $("tabs-" + connection_id).getElementsByTagName("LI");
		} else {
			tabs = $("tabs-list").getElementsByTagName("li");
		}

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
		var line_id = 0;

		if (test_height - $("log").scrollTop < 50) scroll_buffer = true;

		if (buffers[connection_id]) {

			var buffer = buffers[connection_id][channel];

			if (buffer) {

				/* do we need to redraw everything? */

				var log_channel = $("log-list").getAttribute("channel");
				var log_connection = $("log-list").getAttribute("connection_id");
				var log_line_id = $("log-list").getAttribute("last_id");

				if (log_channel != channel || log_connection != connection_id) {
					var tmp = "";
					line_id = 0;
					for (var i = 0; i < buffer.length; i++) {
						tmp += buffer[i][1];
						line_id = buffer[i][0];
					}

					$("log-list").innerHTML = tmp;

				} else {

					line_id = parseInt(log_line_id);
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
				if (scroll_buffer) $("log").scrollTop = $("log").scrollHeight;
			} else {
				$("log-list").innerHTML = "";
			}
		} else {
			$("log-list").innerHTML = "";
		}

		$("log-list").setAttribute("last_id", line_id);
		$("log-list").setAttribute("channel", channel);
		$("log-list").setAttribute("connection_id", connection_id);

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
	
					var userhosts = conndata_last[connection_id]["userhosts"];
					var nick_ext_info = "";

					if (userhosts && userhosts[nick]) {
						nick_ext_info = userhosts[nick][0] + '@' + 
							userhosts[nick][1] + " <" + userhosts[nick][3] + ">";
					}					

/*					if (nick == active_nicks[connection_id]) {
						nick = "<strong>" + nick + "</strong>";
					} */
	
					var tmp_html = "<li class=\""+row_class+"\" " +
						"title=\"" + nick_ext_info + "\"" + 
					  	"nick=\"" + nick + "\" " +
						"onclick=\"query_user(this)\">" +
						nick_image + " " + nick + "</li>";
	
					$("userlist-list").innerHTML += tmp_html;
				}
			} else {
				$("userlist-list").innerHTML = "";
			}
		}

		if (topics[connection_id] && tab.getAttribute("tab_type") != "P") {
			var topic = topics[connection_id][channel];
	
			if (topic) {
				if ($("topic-input").value != topics[connection_id][channel][0]) {
					$("topic-input").value = topics[connection_id][channel][0];
				}

				$("topic-input").disabled = conndata_last[connection_id].status != "2";
			} else {
	
				if (tab.getAttribute("tab_type") != "S") {
					$("topic-input").value = "";
					$("topic-input").disabled = true;

				} else {
					if (conndata_last[connection_id].status == CS_CONNECTED) {
						$("topic-input").value = __("Connected to: ") + 
							conndata_last[connection_id]["active_server"];
						$("topic-input").disabled = true;
					} else {
						$("topic-input").value = __("Disconnected.");
						$("topic-input").disabled = true;
					}
				}
			}
		} else if (tab.getAttribute("tab_type") == "S") {
			$("topic-input").value = __("Disconnected.");
			$("topic-input").disabled = true;
		} else {

			var nick = tab.getAttribute("channel");
			var userhosts = conndata_last[connection_id]["userhosts"];
			var nick_ext_info = "";

			if (userhosts && userhosts[nick]) {
				nick_ext_info = userhosts[nick][0] + '@' + userhosts[nick][1];
			}					

			$("topic-input").value = __("Conversation with") + " " +
				tab.getAttribute("channel") + " (" + nick_ext_info + ")";
			$("topic-input").disabled = true;
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

		update_title();

	} catch (e) {
		exception_error("update_buffer", e);
	}	
}

function change_topic(elem, evt) {
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

			var query = "?op=set-topic&topic=" + param_escape(elem.value) + 
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
		exception_error("change_topic", e);
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
				"&last_id=" + last_id + "&tab_type=" + tab.getAttribute("tab_type");
	
			debug(query);

			show_spinner();

			new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function (transport) {
				elem.value = '';
				handle_update(transport);
				hide_spinner();
			} });
		}

	} catch (e) {
		exception_error("send", e);
	}
}

function handle_error(obj, transport) {
	try {
		if (obj && obj.error) {
			return fatal_error(obj.error, obj.errormsg, transport.responseText);
		}
		return true;
	} catch (e) {
		exception_error("handle_error", e);
	}
}

function change_tab(elem) {
	try {

		if (!elem) return;

		var tabs = get_all_tabs();

		for (var i = 0; i < tabs.length; i++) {
			if (tabs[i].className == "selected") tabs[i].className = "";
		}

		elem.className = "selected";

		debug("changing tab to " + elem.id);

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

		if (param.message_type == MSGT_ACTION) {

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

			conndata_last = [];

			for (var i = 0; i < conndata.length; i++) {

				create_tab_if_needed(conndata[i].title, conndata[i].id, "S");
				conndata_last[conndata[i].id] = conndata[i];

				if (conndata[i].status == "2") {
					active_nicks[conndata[i].id] = conndata[i].active_nick;

					$("cimg-" + conndata[i].id).src = $("cimg-" + conndata[i].id).src.replace("offline", "online");

				} else {
					active_nicks[conndata[i].id] = [];
					nicklists[conndata[i].id] = [];

					$("cimg-" + conndata[i].id).src = $("cimg-" + conndata[i].id).src.replace("online", "offline");

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

					var tab_type = "P";

					switch (parseInt(chandata[connection_id][chan].chan_type)) {
					case 0: 
						tab_type = "C";
						break;
					case 1:
						tab_type = "P";
						break;
					}

					create_tab_if_needed(chan, connection_id, tab_type);

					nicklists[connection_id][chan] = chandata[connection_id][chan]["users"];
					topics[connection_id][chan] = chandata[connection_id][chan]["topic"];
				}
			}
		}

		cleanup_tabs(chandata);
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

			if (!window_active && last_active_id != last_id) {
				title = "["+(last_id-last_active_id)+"] " + title;
			}

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

function change_nick() {
	try {
		var tab = get_selected_tab();

		if (tab) {


		}
	} catch (e) {
		exception_error("change_nick", e);
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

function cleanup_tabs(chandata) {
	try {
		var tabs = get_all_tabs();

		for (var i = 0; i < tabs.length; i++) {
			var chan = tabs[i].getAttribute("channel");
			var connection_id = tabs[i].getAttribute("connection_id");

			if (tabs[i].getAttribute("tab_type") == "S") {
				if (conndata_last && !conndata_last[connection_id]) {

					debug("removing unnecessary S-tab: " + tabs[i].id);

					var tab_list = $("tabs-" + connection_id);

					$("tabs-list").removeChild(tabs[i]);
					$("tabs-list").removeChild(tab_list);
				}
			}

			if (tabs[i].getAttribute("tab_type") != "S") {
				if (!chandata[connection_id] || 
						(chandata[connection_id] && !chandata[connection_id][chan])) {

					debug("removing unnecessary C/P-tab: " + tabs[i].id);

					var tab_list = $("tabs-" + connection_id);
					
					if (tab_list) tab_list.removeChild(tabs[i]);

				}
			}
		}
	} catch (e) {
		exception_error("cleanup_tabs", e);
	}

}

function close_tab(elem) {
	try {

		if (!elem) return;

		var tab_id = elem.getAttribute("tab_id");
		var tab = $(tab_id);

		if (tab && confirm(__("Close this tab?"))) {

			var query = "?op=part-channel" +
				"&chan=" + param_escape(tab.getAttribute("channel")) +
				"&connection=" + param_escape(tab.getAttribute("connection_id")) +
				"&last_id=" + last_id;

			debug(query);

			show_spinner();

			new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function (transport) {
				handle_update(transport);
				hide_spinner();
			} });
		}
		
	} catch (e) {
		exception_error("change_tab", e);
	}
}

function query_user(elem) {
	try {

		if (!elem) return;

		var tab = get_selected_tab();
		var nick = elem.getAttribute("nick");
		var pr = __("Start conversation with %s?").replace("%s", nick);

		if (tab && confirm(pr)) {

			var query = "?op=query-user&nick=" + param_escape(nick) +
				"&connection=" + param_escape(tab.getAttribute("connection_id")) +
				"&last_id=" + last_id;

			debug(query);

			show_spinner();

			new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function (transport) {
				handle_update(transport);
				hide_spinner();
			} });

		}

	} catch (e) {
		exception_error("query_user", e);
	}
}

function handle_event(li_class, connection_id, line) {
	try {
		var params = line.message.split(":", 3);

		debug("handle_event " + params);

		switch (params[0]) {
		case "TOPIC":
			var params_topic = line.message.split(":", 2);
			var topic = line.message.replace("TOPIC:", "");

			line.message = __("%u has changed the topic to: %s").replace("%u", line.sender);
			line.message = line.message.replace("%s", topic);
			line.sender = "---";

			push_message(connection_id, line.channel, line, MSGT_PRIVMSG);

			break;
		case "MODE":
			var mode = params[1];
			var subject = params[2];
			
			var msg_type;

			if (mode) {
				line.message = __("%u has changed mode [%m] on %s").replace("%u", 
						line.sender);
				line.message = line.message.replace("%m", mode);
				line.message = line.message.replace("%s", subject);
				line.sender = "---";

				msg_type = MSGT_PRIVMSG;
			} else {
				line.sender = "---";

				line.message = __("%u has changed mode [%m]").replace("%u", 
						line.channel);
				line.message = line.message.replace("%m", subject);

				msg_type = MSGT_BROADCAST;
			}

			push_message(connection_id, line.channel, line, msg_type);

			break;
		case "KICK":
			var nick = params[1];
			var message = params[2];

			line.message = __("%u has been kicked from %c by %n (%m)").replace("%u", nick);
			line.message = line.message.replace("%c", line.channel);
			line.message = line.message.replace("%n", line.sender);
			line.message = line.message.replace("%m", message);
			line.sender = "---";

			push_message(connection_id, line.channel, line, MSGT_PRIVMSG);

			break;

		case "PART":
			var nick = params[1];
			var message = params[2];

			line.message = __("%u has left %c (%m)").replace("%u", nick);
			line.message = line.message.replace("%c", line.channel);
			line.message = line.message.replace("%m", message);

			push_message(connection_id, line.channel, line, MSGT_PRIVMSG);

			break;
		case "JOIN":
			var nick = params[1];
			var host = params[2];

			line.message = __("%u (%h) has joined %c").replace("%u", nick);
			line.message = line.message.replace("%c", line.channel);
			line.message = line.message.replace("%h", host);

			push_message(connection_id, line.channel, line, MSGT_PRIVMSG);

			break;
		case "QUIT":
			var nick = params[1];
			var quit_msg = params[2];

			line.message = __("%u has quit IRC (%s)").replace("%u", nick);
			line.message = line.message.replace("%s", quit_msg);

			push_message(connection_id, '---', line, MSGT_BROADCAST);
			break;
		case "DISCONNECT":
			line.message = __("Connection terminated.");

			push_message(connection_id, '---', line);
			break;
		case "REQUEST_CONNECTION":
			line.message = __("Requesting connection...");

			push_message(connection_id, '---', line);
			break;
		case "CONNECTING":
			var server = params[1];
			var port = params[2];

			line.message = __("Connecting to %s:%d...").replace("%s", server);
			line.message = line.message.replace("%d", port);

			push_message(connection_id, '---', line);
			break;
		case "PING_REPLY":
			var args = params[1];

			line.message = __("Ping reply from %u: %d second(s).").replace("%u", 
					line.sender);
			line.message = line.message.replace("%d", args);
			line.sender = '---';

			push_message(connection_id, '---', line, MSGT_BROADCAST);
			break;

		case "PING":
			var args = params[1];

			line.message = __("Received ping (%s) from %u").replace("%s", args);
			line.message = line.message.replace("%u", line.sender);
			line.sender = '---';

			push_message(connection_id, '---', line, MSGT_BROADCAST);
			break;
		case "CONNECT":
			line.message = __("Connection established.");

			push_message(connection_id, '---', line);
			break;
		case "NICK":
			var old_nick = params[1];
			var new_nick = params[2];
			var tabs = get_all_tabs();

			if (buffers[connection_id] && buffers[connection_id][old_nick]) {
				buffers[connection_id][new_nick] = buffers[connection_id][old_nick];
			}

			line.message = __("%u is now known as %n").replace("%u", old_nick);
			line.message = line.message.replace("%n", new_nick);

			push_message(connection_id, '---', line, MSGT_BROADCAST);

/*			for (var b in buffers[connection_id]) {
				if (typeof buffers[connection_id][b] == 'object') {
					buffers[connection_id][b].push(tmp_html);
				}
			} */

/*			for (var i = 0; i < tabs.length; i++) {
				var tab_conn = tabs[i].getAttribute("connection_id");
				var tab_chan = tabs[i].getAttribute("channel");

				if (tab_conn == connection_id && tab_chan == old_nick) {
					debug("renaming query tab " + tab_chan + " to " + new_nick);
					tabs[i].setAttribute("channel", new_nick);
					tabs[i].innerHTML = "&nbsp;&nbsp;" + new_nick;
					tabs[i].id = "tab-" + new_nick + ":" + connection_id;
					buffers[connection_id][new_nick] = buffers[connection_id][tab_chan];
				}
			} */
			break; 
		}

	} catch (e) {
		exception_error("handle_event", e);
	}
}

function toggle_li_class(channel) {
	if (!li_classes[channel]) {
		li_classes[channel] = "odd";
	} else {
		if (li_classes[channel] == "odd") {
			li_classes[channel] = "even";
		} else {
			li_classes[channel] = "odd";
		}
	} 
}

function push_message(connection_id, channel, message, message_type) {
	try {
		if (!message_type) message_type = MSGT_PRIVMSG;

		if (!buffers[connection_id]) buffers[connection_id] = [];
		if (!buffers[connection_id][channel]) buffers[connection_id][channel] = [];

		if (message_type != MSGT_BROADCAST) {
			toggle_li_class(channel);

			var tmp_html = format_message(li_classes[channel], message);

			buffers[connection_id][channel].push(tmp_html);
		} else {
			var tabs = get_all_tabs(connection_id);

			for (var i = 0; i < tabs.length; i++) {
				var chan = tabs[i].getAttribute("channel");

				if (!buffers[connection_id][chan]) buffers[connection_id][chan] = [];

				toggle_li_class(chan);

				var tmp_html = format_message(li_classes[chan], message);

				buffers[connection_id][chan].push(tmp_html);
			}
		}

	} catch (e) {
		exception_error("push_message", e);
	}
}

function set_window_active(active) {
	try {
		window_active = active;

		if (active) last_active_id = last_id;

		update_title();
	} catch (e) {
		exception_error("window_active", e);
	}
}

function m_i(elem) {
	try {	

		if (!elem.href.toLowerCase().match("(jpg|gif|png|bmp)$"))
			return;

		var timeout = window.setTimeout(function() {

			var xy = Element.cumulativeOffset(elem);
			xy[1] += Element.getHeight(elem);

			$("image-tooltip").style.left = xy[0] + "px";
			$("image-tooltip").style.top = xy[1] + "px";
			$("image-tooltip").innerHTML = "<img src=\"" + elem.href + "\"/>";

			Effect.Appear($("image-tooltip"));
			}, 1000);

		elem.setAttribute("timeout", timeout);

	} catch (e) {
		exception_error("m_i", e);
	}
}

function m_o(elem) {
	try {	

		window.clearTimeout(elem.getAttribute("timeout"));

		Element.hide("image-tooltip");

	} catch (e) {
		exception_error("m_o", e);
	}


}
