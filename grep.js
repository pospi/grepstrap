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
		if (input && input.name && input.checked) {
			var filterid = input.name.match(/^cb(\d+)$/);
			if (filterid != null && filterid[1] != null) {
				filter_ids.push(filterid[1]);
			}
		}
	}
	if (filter_ids.length) {
		window.location.href = window.location.href + '&filter=' + filter_ids.join(',');
	}
}
function unfilterResults() {
	window.location.href = window.location.href.replace(/&filter=[^&]*/, '');
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
