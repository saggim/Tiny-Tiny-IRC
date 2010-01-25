var hotkeys_enabled = false;

/* add method to remove element from array */

Array.prototype.remove = function(s) {
	for (var i=0; i < this.length; i++) {
		if (s == this[i]) this.splice(i, 1);
	}
}

function is_opera() {
	return window.opera;
}

function exception_error(location, e, ext_info) {
	var msg = format_exception_error(location, e);

	if (!ext_info) ext_info = "N/A";

	disableHotkeys();

	try {

		var ebc = $("xebContent");
	
		if (ebc) {
	
			Element.show("dialog_overlay");
			Element.show("errorBoxShadow");
	
			if (ext_info) {
				if (ext_info.responseText) {
					ext_info = ext_info.responseText;
				}
			}
	
			ebc.innerHTML = 
				"<div><b>Error message:</b></div>" +
				"<pre>" + msg + "</pre>" +
				"<div><b>Additional information:</b></div>" +
				"<textarea readonly=\"1\">" + ext_info + "</textarea>";
	
		} else {
			alert(msg);
		}

	} catch (e) {
		alert(msg);

	}

}

function format_exception_error(location, e) {
	var msg;

	if (e.fileName) {
		var base_fname = e.fileName.substring(e.fileName.lastIndexOf("/") + 1);
	
		msg = "Exception: " + e.name + ", " + e.message + 
			"\nFunction: " + location + "()" +
			"\nLocation: " + base_fname + ":" + e.lineNumber;

	} else if (e.description) {
		msg = "Exception: " + e.description + "\nFunction: " + location + "()";
	} else {
		msg = "Exception: " + e + "\nFunction: " + location + "()";
	}

	debug("<b>EXCEPTION: " + msg + "</b>");

	return msg;
}


function disableHotkeys() {
	hotkeys_enabled = false;
}

function enableHotkeys() {
	hotkeys_enabled = true;
}

function param_escape(arg) {
	if (typeof encodeURIComponent != 'undefined')
		return encodeURIComponent(arg);	
	else
		return escape(arg);
}

function param_unescape(arg) {
	if (typeof decodeURIComponent != 'undefined')
		return decodeURIComponent(arg);	
	else
		return unescape(arg);
}

var debug_last_class = "even";

function toggle_debug() {
	if (Element.visible('debug_output')) {
		Element.hide('debug_output');
	} else {
		Element.show('debug_output');
	}
}

function debug(msg) {

	if (debug_last_class == "even") {
		debug_last_class = "odd";
	} else {
		debug_last_class = "even";
	}

	var c = $('debug_output');
	if (c && Element.visible(c)) {
		while (c.lastChild != 'undefined' && c.childNodes.length > 100) {
			c.removeChild(c.lastChild);
		}
	
		var d = new Date();
		var ts = leading_zero(d.getHours()) + ":" + leading_zero(d.getMinutes()) +
			":" + leading_zero(d.getSeconds());
		c.innerHTML = "<li class=\"" + debug_last_class + "\"><span class=\"debugTS\">[" + ts + "]</span> " + 
			msg + "</li>" + c.innerHTML;
	}
}

// originally stolen from http://www.11tmr.com/11tmr.nsf/d6plinks/MWHE-695L9Z
// bugfixed just a little bit :-)
function getURLParam(strParamName){
  var strReturn = "";
  var strHref = window.location.href;

  if (strHref.indexOf("#") == strHref.length-1) {
		strHref = strHref.substring(0, strHref.length-1);
  }

  if ( strHref.indexOf("?") > -1 ){
    var strQueryString = strHref.substr(strHref.indexOf("?"));
    var aQueryString = strQueryString.split("&");
    for ( var iParam = 0; iParam < aQueryString.length; iParam++ ){
      if (aQueryString[iParam].indexOf(strParamName + "=") > -1 ){
        var aParam = aQueryString[iParam].split("=");
        strReturn = aParam[1];
        break;
      }
    }
  }
  return strReturn;
} 

function leading_zero(p) {
	var s = String(p);
	if (s.length == 1) s = "0" + s;
	return s;
}

function closeErrorBox() {

	if (Element.visible("errorBoxShadow")) {
		Element.hide("dialog_overlay");
		Element.hide("errorBoxShadow");

		enableHotkeys();
	}

	return false;
}


function closeInfoBox(cleanup) {

	try {
		enableHotkeys();

		if (Element.visible("infoBoxShadow")) {
			Element.hide("dialog_overlay");
			Element.hide("infoBoxShadow");

			if (cleanup) $("infoBoxShadow").innerHTML = "&nbsp;";
		}
	} catch (e) {
		exception_error("closeInfoBox", e);
	}
	
	return false;
}

function fatal_error(code, msg, ext_info) {
	try {	

		if (!ext_info) ext_info = "N/A";

		if (code == 6) {
			window.location.href = "index.php";			
		} else if (code == 5) {
			window.location.href = "update.php";
		} else {
	
			if (msg == "") msg = "Unknown error";

			var ebc = $("xebContent");
	
			if (ebc) {
	
				Element.show("dialog_overlay");
				Element.show("errorBoxShadow");
				Element.hide("xebBtn");

				if (ext_info) {
					if (ext_info.responseText) {
						ext_info = ext_info.responseText;
					}
				}
	
				ebc.innerHTML = 
					"<div><b>Error message:</b></div>" +
					"<pre>" + msg + " (" + code + ")" + "</pre>" +
					"<div><b>Additional information:</b></div>" +
					"<textarea readonly=\"1\">" + ext_info + "</textarea>";
			}
		}

	} catch (e) {
		exception_error("fatal_error", e);
	}
}

function infobox_callback2(transport) {
	try {
		var box = $('infoBox');
		
		Element.show("dialog_overlay");

		if (box) {			
			box.innerHTML=transport.responseText;			
			Element.show("infoBoxShadow");
		}

		disableHotkeys();
	} catch (e) {
		exception_error("infobox_callback2", e);
	}
}

function close_infobox(cleanup) {

	try {
		enableHotkeys();

		if (Element.visible("infoBoxShadow")) {
			Element.hide("dialog_overlay");
			Element.hide("infoBoxShadow");

			if (cleanup) $("infoBoxShadow").innerHTML = "&nbsp;";
		}
	} catch (e) {
		exception_error("closeInfoBox", e);
	}
	
	return false;
}

function show_spinner() {
	try {
		Element.show($("spinner"));
	} catch (e) {
		exception_error("show_spinner", e);
	}
}

function hide_spinner() {
	try {
		Element.hide($("spinner"));
	} catch (e) {
		exception_error("hide_spinner", e);
	}
}

function sort_connection_tabs(node) {

//	debug("sort_connection_tabs " + node);

	try {
		var list = node.getElementsByTagName("li");

		for (i = 0; i < list.length; i++) {
	
			for (j = i+1; j < list.length; j++) {			

				if (list[i].getAttribute("channel") > list[j].getAttribute("channel")) {	
					tempnode_i = list[i].cloneNode(true);
					tempnode_j = list[j].cloneNode(true);
					node.replaceChild(tempnode_i, list[j]);
					node.replaceChild(tempnode_j, list[i]);
				}
			}
		}

	} catch (e) {
		exception_error("sort_connection_tabs", e);
	}
}

function mini_error(msg) {
	try {

		var elem = $("mini-notice");

		if (elem) {
			if (msg) {
				elem.innerHTML = msg;
				Element.show(elem);
				new Effect.Highlight(elem);
			} else {
				Element.hide(elem);
			}
		}

	} catch (e) {
		exception_error("show_mini_error");
	}
}

