function setItemContents(items, inner) {
	for (var i in items) {
		if (typeof items[i] == 'function') continue;
		document.getElementById(items[i]).innerHTML = inner;
	}
}
function cbClicked(cb) {
	cb.nextSibling.value = cb.checked ? 'on' : 'off';
}
function filterResults() {
	var form = document.forms['searchForm'];
	var filter_ids = [];
	for (var i = 0, input; input = form.elements[i]; ++i) {
		if (input && input.name) {
			var filterid = input.name.match(/^cb(\d+)$/);
			if (filterid != null && filterid[1] != null) {
				if (input.checked) {
					filter_ids.push(filterid[1]);
					input.getParent().getParent().setStyle('display', '');
				} else {
					input.getParent().getParent().setStyle('display', 'none');
				}
			}
		}
	}
	if (filter_ids.length) {
		window.location.hash = 'filter=' + filter_ids.join(',');
		$('filterOn').setStyle('display', 'none');
		$('filterOff').setStyle('display', '');
	} else {
		unfilterResults();
	}
}
function unfilterResults() {
	window.location.hash = window.location.hash.replace(/filter=[^&]*/, '');
	
	var form = document.forms['searchForm'];
	for (var i = 0, input; input = form.elements[i]; ++i) {
		if (input && input.name) {
			var filterid = input.name.match(/^cb(\d+)$/);
			if (filterid != null && filterid[1] != null) {
				input.getParent().getParent().setStyle('display', '');
			}
		}
	}
	$('filterOn').setStyle('display', '');
	$('filterOff').setStyle('display', 'none');
}

var last_clicked;
function resultClicked(link) {
	if (last_clicked && document.getElementById(last_clicked)) {
		document.getElementById(last_clicked).style.backgroundColor = '';
	}
	var last_cookie = getCookie('last_result');
	if (link) {
		last_clicked = link.parentNode.id;
	} else if (last_cookie) {
		last_clicked = last_cookie;
	}
	if (document.getElementById(last_clicked)) {
		document.getElementById(last_clicked).style.backgroundColor = '#FFEDFF';
	}
	setCookie('last_result', last_clicked);
}

function setCookie(cookieName, cookieValue, nDays) {
	var today = new Date();
	var expire = new Date();
	if (nDays == null || nDays == 0) {
		nDays = 7;
	}
	expire.setTime(today.getTime() + 3600000 * 24 * nDays);
	document.cookie = cookieName + "=" + escape(cookieValue) + ";expires=" + expire.toGMTString();
}

function getCookie(c_name) {
	if (document.cookie.length > 0) {
		c_start = document.cookie.indexOf(c_name + "=");
		if (c_start != -1) {
			c_start = c_start + c_name.length + 1;
			c_end = document.cookie.indexOf(";", c_start);
			if (c_end == -1) {
				c_end = document.cookie.length;
			}
			return unescape(document.cookie.substring(c_start, c_end));
		}
	}
	return false;
}

//===========================================================
// really cheap and nasty search result polling, but it works
is_polling = false;
polling_interval = 5000;
polling_timer = null;

function togglePolling(force) {
	is_polling = force != undefined ? force : !is_polling;

	var poll_amt = document.getElementById('poll_amt');
	var poll_amt_span = document.getElementById('poll_amt_span');
	var poll_cookie = getCookie('poll_amt');
	if (poll_amt.value) {
		polling_interval = poll_amt.value * 1000;
	} else if (poll_cookie) {
		polling_interval = poll_cookie * 1000;
	}
	poll_amt.value = polling_interval / 1000;

	if (is_polling) {
		polling_timer = window.setTimeout('window.location.reload();', polling_interval);
		poll_amt_span.style.display = 'none';
	} else {
		window.clearTimeout(polling_timer);
		poll_amt_span.style.display = '';
	}
	setCookie('polling', (is_polling ? 1 : ''));
	setCookie('poll_amt', polling_interval / 1000);
	document.getElementById('poll').value = is_polling ? 'Disable Polling' : 'Enable Polling:';
} 

//===========================================================
// tab completion for the path-related inputs

// ajax call to retrieve matching filesystem entries
function tabCompletion(currPath, input, caretPos) {
	var pathTest = new Request({
		url:	'tabcompletion.ajax.php',
		link:	'cancel',
		noCache: true,
		onComplete: function(req) {
			updateInputTabs(input, caretPos, req);
		}
	});
	pathTest.send('path=' + currPath);
}

// once matches are retrieved, show or insert them
function updateInputTabs(el, caretPos, suggestions) {
	suggestions = suggestions.split("\n");
	if (suggestions[0].trim() == '') suggestions.shift();
	
	if (!suggestions.length) {
		showTipFor(el, caretPos, ["No path matches found"], true);	// nothing matched, show error
		setCaretPos(el, caretPos);
	} else if (suggestions.length == 1) {
		selectCompletionValue(el, caretPos, suggestions[0]);		// only 1 thing matched, inject it at cursor
	} else {
		showTipFor(el, caretPos, suggestions);						// multiple things matched, allow clicking them to choose
		setCaretPos(el, caretPos);
	}
}

// selects a tab-completion string for injection into an input value
function selectCompletionValue(el, caretPos, insertText) {
	var val = el.get('value');								
	var choppedVal = val.substr(0, caretPos);
	var lastSlash	= choppedVal.lastIndexOf("/");
	var lastLine	= choppedVal.lastIndexOf("\n");
	var insertAt	= Math.max(lastSlash, lastLine);
	
	var startAndInserted = choppedVal.substr(0, insertAt + 1) + insertText;
	el.set('value', startAndInserted + val.substr(caretPos));
	setCaretPos(el, startAndInserted.length);
}

// shows a tip for a field's tab completion
function showTipFor(el, caretPos, messages, isError) {
	// determine display position
	var tipPos = el.getPosition();
	var size = el.getSize();
	tipPos.y += size.y;
	if (el.get('id') == 'searchpath2')	tipPos.x -= 230;
	
	// recycle or create indicator element
	var indicator = $('pathIndicator');
	if (!indicator) {
		indicator = new Element('div', {'id' : 'pathIndicator'});
		document.body.grab(indicator);
	}
	if (isError) {
		indicator.addClass('error');
		hideTip.delay('1500');
	} else {
		indicator.removeClass('error');
		window.addEvent('click', hideTip);
	}
	
	// add suggestion nodes
	indicator.empty();
	messages.each(function(msg) {
		var matchEl = new Element('a', {'html' : msg});
		matchEl.addEvent('click', function() {
			selectCompletionValue(el, caretPos, msg);
		});
		indicator.grab(matchEl);
	});
	
	// show!
	indicator.setPosition(tipPos);
	indicator.setStyle('display', '');
}

// hide tab completion tooltip from view
function hideTip() {
	var indicator = $('pathIndicator');
	if (indicator) {
		indicator.setStyle('display', 'none');
	}
	window.removeEvent('click', hideTip);
}

// get the cursor position within an element
function getCaretPos(el) { 
	if (el.selectionStart) { 
		return el.selectionStart; 
	} else if (document.selection) { 
		el.focus(); 
	
		var r = document.selection.createRange(); 
		if (r == null) { 
			return 0; 
		} 
	
		var re = el.createTextRange(), 
		rc = re.duplicate(); 
		re.moveToBookmark(r.getBookmark()); 
		rc.setEndPoint('EndToStart', re); 
	
		return rc.text.length; 
	}  
	return 0;
}
// set the cursor position within an element
function setCaretPos(ctrl, pos) {
	if (ctrl.setSelectionRange) {
		ctrl.focus();
		ctrl.setSelectionRange(pos,pos);
	} else if (ctrl.createTextRange) {
		var range = ctrl.createTextRange();
		range.collapse(true);
		range.moveEnd('character', pos);
		range.moveStart('character', pos);
		range.select();
	}
}

// setup all events
window.addEvent('domready', function() {
	var search1 = $('searchpath1'), search2 = $('searchpath2'), ignore = $('ignorePaths');
	
	search1.addEvent('keydown', function(e) {
		if (e.key == 'tab') {
			var text = search1.get('value').substr(0, getCaretPos(search1));
			tabCompletion(text, search1, getCaretPos(search1), updateInputTabs);
		}
	});
	search2.addEvent('keydown', function(e) {
		if (e.key == 'tab') {
			var text = search1.get('value') + search2.get('value').substr(0, getCaretPos(search2));
			tabCompletion(text, search2, getCaretPos(search2));
		}
	});
	ignore.addEvent('keydown', function(e) {
		if (e.key == 'tab') {
			var text = ignore.get('value');
			var caret = getCaretPos(ignore);
			var lineStart = text.substr(0, caret).lastIndexOf("\n");
			
			var sep = '';
			var test = search2.get('value');
			if (!test.length) {
				test = search1.get('value');
			}
			if (test.charAt(test.length - 1) != '/') {
				sep = '/';
			}
			
			text = search1.get('value') + search2.get('value') + sep + text.substr(lineStart + 1, caret);
			tabCompletion(text, ignore, caret);
		}
	});
	
	// action to show underlying shell command
	$('showCmd').addEvent('click', function() {
		$('searchCmd').setStyle('display', ($('searchCmd').getStyle('display') == 'none' ? '' : 'none'));
	});
	
	// read resultset filter if present on load
	var filtering = window.location.hash.match(/filter=([^&]*)/);
	if (filtering) {
		filterResults();
	}
});
