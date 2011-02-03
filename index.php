<?php
/*================================================================================
	GrepStrap - main script
	----------------------------------------------------------------------------
	A frontend for the unix [find] and [grep] commands, for lightning-fast
	codebase searching.

	Can integrate with pDebug for tying result links into your IDE, @see
	 http://pospi.spadgos.com/projects/pdebug
	----------------------------------------------------------------------------
	Copyright (c) 2008 Sam Pospischil <pospi@spadgos.com>
  ===============================================================================*/

require_once('includes.inc.php');

//==================================================================================
//==================================================================================

$COOKIES_SET = array();
function saveToCookie($name, $val) {
	global $COOKIES_SET;
	if (!isset($COOKIES_SET[$name])) {
		setcookie($name, $val, time() + 604800);  //one week
		$COOKIES_SET[$name] = 1;
	}
}
function clearCookie($name) {
	setcookie($name, '', time() - 3600);
}

// error handler function to detect bad regex format
function errorHandler($errno, $errstr = '', $errfile = '', $errline = '') {
	global $regex_error;
	$regex_error = "Regular Expression Error: $errstr";
	return true;        // dont exec internal error handler
}

function formVar($var) {
	global $DEFAULTS, $CHECKBOX_VALS;

	if (isset($_GET[$var])) {
		if (isset($CHECKBOX_VALS[$var])) {
			$val = (int)($_GET[$var] == 'on');
		} else {
			$val = $_GET[$var];
		}
		return $val;
	} else if (!isset($_GET['pattern']) && isset($_COOKIE[$var])) {
		return $_COOKIE[$var];
	} else {
		return $DEFAULTS[$var];
	}
}

function cbSafeIsEqual($v1, $v2, $is_cb = false) {
	if ($is_cb) {
		if ($v1 === 1 || $v1 === 0 || $v1 === '1' || $v1 === '0') $v1 = (bool)$v1;
		if ($v2 === 1 || $v2 === 0 || $v1 === '1' || $v1 === '0') $v2 = (bool)$v2;
	}

	if ($v1 === $v2) {
		return true;
	} else {
		if ($is_cb) {
			if (($v1 == 'on' && $v2 === true) || ($v2 == 'on' && $v1 === true)) {
				return true;
			} else if (($v1 == 'off' && $v2 === false) || ($v2 == 'off' && $v1 === false)) {
				return true;
			}
		}
		return false;
	}
}

function getDifferentVarQuerystring($request_vars, $defaults, $set_cookies = false) {
	global $CHECKBOX_VALS;

	$vars_to_keep = array();
	foreach ($defaults as $key => $value) {
		if (isset($request_vars[$key]) && !cbSafeIsEqual(str_replace("\r", "", $request_vars[$key]), $value, isset($CHECKBOX_VALS[$key]))) {
			$vars_to_keep[] = $key . '=' . (is_bool($request_vars[$key]) ? ($request_vars[$key] ? 'on' : 'off') : urlencode($request_vars[$key]));
		}
		if ($set_cookies) {
			if (isset($CHECKBOX_VALS[$key])) {
				$request_vars[$key] = (int)($request_vars[$key] == 'on');
			}
			saveToCookie($key, $request_vars[$key]);
		}
	}
	return implode('&', $vars_to_keep);
}

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

//====================================================================================

$DEFAULTS = array(
	'path1' => $default_path_base,
	'path2' => $default_path_extra,
	'ignore' => implode("\n", $ignore_paths),
	'types' => implode("\n", $include_types),
	'exclude_types' => implode("\n", $exclude_types),
	'pattern' => '',
	'path_search' => $path_search,
	'path_replace' => $path_replace,
	'win_paths' => $windows_path,
	'launch_pro' => $ide_launch_protocol,
	'launch_str' => $ide_launch_string,
	'regexp_search' => $regexp_search,
	'case_sensitive' => $case_sensitive,
	'line_stats' => $line_stats,
	'filenames_only' => $show_filenames_only,
	'trim_output_lines' => $trim_output_lines,
	'line_separator' => $line_separator,
	'use_line_numbers' => $use_line_numbers
);
$CHECKBOX_VALS = array(         // fucking checkboxes and their non sending
	'win_paths' => true,
	'regexp_search' => true,
	'case_sensitive' => true,
	'line_stats' => true,
	'filenames_only' => true,
	'trim_output_lines' => true,
	'use_line_numbers' => true
);

if (isset($_GET['clear'])) {        // erase all cookies
	foreach ($DEFAULTS as $key => $val) {
		clearCookie($key);
	}
	header('location: ' . $_SERVER['PHP_SELF']);
	exit;
}

if (!empty($_POST['pattern'])) {
	// work out which submissions differ from the defaults, and redirect to a stripped querystring
	$pattern = $_POST['pattern'];
	unset($_POST['pattern']);
	saveToCookie('pattern', $pattern);
	unset($DEFAULTS['pattern']);

	$querystring = getDifferentVarQuerystring($_POST, $DEFAULTS, true);
	header('location: ' . $_SERVER['PHP_SELF'] . '?pattern=' . urlencode($pattern) . ($querystring ? '&' . $querystring : ''));
	exit;
}

$SEARCH_STR = '';

$result_count = 0;
$outputted_result_count = 0;
$file_count = 0;
$line_count = 0;

$execution_time = 0;

$search_basepath = formVar('path1') . formVar('path2');

$regex_special_chars = '\\/^.$|()[]*+?{}-';

// filtering
$filters = array();
if (isset($_GET['filter'])) {
	$filters = explode(',', $_GET['filter']);
}

if (!empty($_GET['pattern']) && !empty($search_basepath)) {
	set_time_limit(0);          // for long searches
	ignore_user_abort(true);    // stop search shellscript hanging when aborting

	$cmd_str_template = 'P=' . escapeshellarg($search_basepath) . ';'
					 . ' find $P -follow %s%s -exec'
					 . ' grep' . (formVar('case_sensitive') ? '' : ' -i') . ' -H -n -P %s {} \\;;';
	if (formVar('line_stats')) {
		$cmd_str_template .= ' echo "---BREAK---";'
					 . ' find $P -follow %s%s -exec'
					 . ' wc {} -l \\;;';
	}

	$highlight_regex = formVar('pattern');
	if (!formVar('regexp_search')) {
		$highlight_regex = addcslashes($highlight_regex, $regex_special_chars);
	}
	$highlight_regex = '/(' . $highlight_regex . ')/' . (formVar('case_sensitive') ? '' : 'i');

	// test regex validity
	set_error_handler('errorHandler');
	$regex_error = false;
	preg_match($highlight_regex, '');       //sets $regex_error internally
	restore_error_handler();

	if ($regex_error) {
		$SEARCH_STR = '<span style="font-weight: bold; color: #900">' . $regex_error . '</span>';
	} else if (!file_exists($search_basepath)) {
		$SEARCH_STR = '<span style="font-weight: bold; color: #900">Directory does not exist</span>';
	} else {

		$search_ignore_paths = array();
		$search_ignore_string = '';
		$temp = explode("\n", formVar('ignore'));
		foreach($temp as $path) {
			$path = trim($path);
			if ($path) {
				$search_ignore_paths[] = '-path "$P' . trim(escapeshellarg('/' . $path), '\'') . '"';
			}
		}
		if (sizeof($search_ignore_paths)) {
			$search_ignore_string = '-type d \( ' . implode(' -o ', $search_ignore_paths) . ' \) -prune -o ';
		}

		$search_include_types = array();
		$search_exclude_types = array();
		$search_include_string = '';
		$temp = explode("\n", formVar('types'));
		foreach($temp as $ext) {
			$ext = trim($ext);
			if ($ext) {
				$search_include_types[] = '-name ' . escapeshellarg('*.' . $ext);
			}
		}
		$temp = explode("\n", formVar('exclude_types'));
		foreach($temp as $ext) {
			$ext = trim($ext);
			if ($ext) {
				$search_exclude_types[] = '-name ' . escapeshellarg('*.' . $ext);
			}
		}
		$has_excludes = sizeof($search_exclude_types) > 0;
		$has_includes = sizeof($search_include_types) > 0;
		if ($has_includes || $has_excludes) {
			$search_include_string = '-type f ' . ($has_excludes && !$has_includes ? '! ' : '') . '\( ';

			if ($has_includes) {
				if ($has_excludes) {
					$search_include_string .= '\( ';
				}
				$search_include_string .= implode(' -o ', $search_include_types);
				if ($has_excludes) {
					$search_include_string .= ' \) ';
				}
			}

			if ($has_excludes) {
				if ($has_includes) {
					$search_include_string .= '! \( ';
				}
				$search_include_string .= implode(' -o ', $search_exclude_types);
				if ($has_includes) {
					$search_include_string .= ' \) ';
				}
			}

			$search_include_string .= ' \)';
		}

		$grep_pattern = formVar('pattern');
		if (!formVar('regexp_search')) {
			// perform plaintext searches as regexes anyway - it's quicker!
			$grep_pattern = addcslashes($grep_pattern, $regex_special_chars);
		}
		$cmd = sprintf($cmd_str_template, $search_ignore_string, $search_include_string, escapeshellarg($grep_pattern), $search_ignore_string, $search_include_string);
//print $cmd;
		$start = microtime(true);
		exec($cmd, $output);
		$execution_time = microtime(true) - $start;

		$parsing_results = true;        // get search results, then get line counts after flagging false

		foreach ($output as $line) {

			if ($line == '---BREAK---') {
				$parsing_results = false;
				continue;
			}

			if ($parsing_results) {
				preg_match('/^([^:]*):(\d+):(.*$)/i', $line, $matches);
				if (count($matches) == 4) {
					++$result_count;

					if (count($filters) && !in_array($result_count, $filters)) {
						continue;
					}
					++$outputted_result_count;

					$result_file = basename($matches[1]);
					$result_line = $matches[2];

					if (formVar('win_paths')) {
						$matches[1] = str_replace('/', '\\', $matches[1]);
					}

					$matches[1] = str_replace(formVar('path_search'), formVar('path_replace'), $matches[1]);
					$matches[1] = htmlentities($matches[1]);

					$result_path = $matches[1];
					if (formVar('use_line_numbers')) {
						 $result_path .= formVar('line_separator') . $matches[2];
					}
					$result_link = '<a href="' . htmlentities(formVar('launch_pro')) . ':' . $result_path . '" title="' . $result_path . '" onclick="resultClicked(this);">' . $result_file . '</a>';
					$result_link_long = '<a href="' . htmlentities(formVar('launch_pro')) . ':' . $result_path . '" onclick="resultClicked(this);">' . $result_path . '</a>';

					// give results unique IDs so last clicked one can be remembered between page views
					// .. helps you to know where you're up to with stuff.
					$result_link      = '<span id="r_' . urlencode($result_path) . '">' . $result_link .      '</span>';
					$result_link_long = '<span id="r_' . urlencode($result_path) . '">' . $result_link_long . '</span>';

					// highlight matching parts

					$match_parts = preg_split($highlight_regex, $matches[3], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE);
					$match_string = '';

					$last_match = array(0, 0);
					foreach ($match_parts as $idx => $match_data) {
						$match = $match_data[0];
						$start_offset = $match_data[1];
						$end_offset = $start_offset + strlen($match);

						if ($start_offset >= $last_match[0] && $end_offset <= $last_match[1]) {
							continue;   // sub-match
						}

						$last_match = array($start_offset, $end_offset);

						if (!preg_match($highlight_regex, $match)) {
							$match_string .= htmlentities($match);
						} else {
							$match_string .= '<span class="h">' . htmlentities($match) . '</span>';
						}
					}

					// highlighting done!

					if (formVar('trim_output_lines')) {
						$match_string = trim($match_string);
					}

					$search_str_template = '<tr%s><td>%s</td><td style="background: #DDD"><input style="width: auto; height: 1em;" type="checkbox" name="cb' . $result_count . '" /></td><td>%s</td><td><b>%s</b>:</td><td><pre>%s</pre></td></tr>';

					if (formVar('filenames_only')) {
						$SEARCH_STR .= sprintf($search_str_template, ($outputted_result_count % 2 == 0 ? ' class="a"' : ''), $result_count, $result_link, $result_line, $match_string);
					} else {
						$SEARCH_STR .= sprintf($search_str_template, ($outputted_result_count % 2 == 0 ? ' class="a"' : ''), $result_count, $result_link_long, $result_line, $match_string);
					}
				}
				//=========================================================
			} else {
				//=========================================================
				if (preg_match('/^\s*(\d+)\s(\S+)$/', $line, $matches)) {
					if ($matches[1] > 0) {
						$line_count += $matches[1];
						++$file_count;
					}
				}
			}
		}
	}
}

// get final form vars

$path1 = htmlentities(formVar('path1'));
$path2 = htmlentities(formVar('path2'));
$ignore = htmlentities(formVar('ignore'));
$ignore_rows = count(explode("\n", $ignore));
$types = htmlentities(formVar('types'));
$types_rows = count(explode("\n", $types));
$exclude_types = htmlentities(formVar('exclude_types'));
$exclude_types_rows = count(explode("\n", $exclude_types));
$pattern = htmlentities(formVar('pattern'));
$path_search = htmlentities(formVar('path_search'));
$path_replace = htmlentities(formVar('path_replace'));
$win_paths = formVar('win_paths');
$launch_pro = htmlentities(formVar('launch_pro'));
$launch_string = htmlentities(formVar('launch_str'));
$regexp_search = formVar('regexp_search');
$case_sensitive = formVar('case_sensitive');
$line_stats = formVar('line_stats');
$filenames_only = formVar('filenames_only');
$trim_output_lines = formVar('trim_output_lines');
$line_separator = formVar('line_separator');
$use_line_numbers = formVar('use_line_numbers');

function checkBox($name, $init_val, $onchange = false) {
	print '<input type="checkbox" name="'.$name.'_cb" '.($init_val ? 'checked' : '').' style="width: auto;" onclick="cbClicked(this);' . ($onchange ? ' '.$onchange : '') . '" /><input type="hidden" name="'.$name.'" value="'.($init_val ? 'on' : 'off').'" />';
}

?>
<html>
<head>
<title>grep browser frontend<?php print $pattern ? ' - ' . $pattern : ''; ?></title>
<link rel="stylesheet" type="text/css" href="grep.css" />
<script type="text/javascript" src="grep.js"></script>
</head>
<body>
<div style="position: absolute; text-align: center;">
    <u>grep frontend v2</u><br/>
    <a onclick="document.getElementById('protocol_help').style.display = 'block';" href="#setup">What is this?</a><br/>
    <input id="poll" type="button" onclick="togglePolling();" style="width: auto; border: 0; padding: 0; cursor: pointer; margin-top: 0.5em;" value="Toggle Polling" />
    <span id="poll_amt_span"><input id="poll_amt" type="text" maxlength="3" style="width: 1em; padding: 0; margin: 0 1px;" />s</span>
    <script type="text/javascript">
        if (getCookie('polling')) {
            togglePolling();    // activate initially
        } else {
            togglePolling(false);
        }
    </script>
</div>
<center>
<form method="post" name="searchForm">
<fieldset><legend>Search</legend>
    <label for="pattern">Search for:</label> <input type="text" name="pattern" value="<?php print $pattern ?>" /> <br/>
    <label for="path1">Search path:</label> <input type="text" name="path1" value="<?php print $path1 ?>" style="width: 230px; text-align: right; border-right: 1px dashed;" /><input type="text" name="path2" value="<?php print $path2 ?>"  style="width: 170px; border-left: none;" /> <br/>
    <label for="case_sensitive_cb">Case sensitive:</label> <?php print checkBox('case_sensitive', $case_sensitive); ?> <br/>
    <label for="line_stats_cb">Show line counts (doubles search time):</label> <?php print checkBox('line_stats', $line_stats); ?> <br/>
    <label for="regexp_search_cb">Regular expressions:</label> <?php print checkBox('regexp_search', $regexp_search); ?> <br/>
    <center><input type="submit" name="search" value="Search" style="width: auto; margin: 2px;" /></center>
</fieldset>

<?php
    if ($SEARCH_STR) {
        print '<fieldset style="width: auto;"><legend>Results: ' . $result_count . ' matches (' . ($line_stats ? 'searched ' . $file_count . ' files / ' . $line_count . ' lines ' : '') . 'in ' . number_format($execution_time, 2) . 's)</legend>';
        if (!count($filters)) {
            print '<a href="#" onclick="filterResults(); return false;" style="background: #DDD; padding: 2px;">Show checked only</a>';
        } else {
            print '<a href="#" onclick="unfilterResults(); return false;" style="background: #DDD; padding: 2px;">(filtered) Show all</a>';
        }
        print '<table cellpadding="0" cellspacing="0"><tbody>' . $SEARCH_STR . '</tbody></table>';
        print '</fieldset>';
    } else if (isset($_GET['search'])) {
        print '<fieldset style="width: auto; color: #900"><legend>Results</legend>';
        print 'NO RESULTS';
        print '</fieldset>';
    }
?>

<script type="text/javascript">
    resultClicked(); // highlight previously clicked link if found
</script>

<fieldset><legend>Options</legend>
    <label for="types">Filetypes to search (separate by newlines):</label> <textarea name="types" rows="<?php print $types_rows ?>"><?php print $types ?></textarea> <br/>
    <label for="exclude_types">Filetypes to exclude (separate by newlines):</label> <textarea name="exclude_types" rows="<?php print $exclude_types_rows ?>"><?php print $exclude_types ?></textarea> <br/>
    <label for="ignore">Ignore folders (relative to search path, separate by newlines):</label> <textarea name="ignore" rows="<?php print $ignore_rows ?>"><?php print $ignore ?></textarea> <br/>
    <label for="filenames_only_cb">Only show filenames:</label> <?php print checkBox('filenames_only', $filenames_only); ?> <br/>
    <label for="trim_output_lines_cb">Trim matching lines:</label> <?php print checkBox('trim_output_lines', $trim_output_lines); ?> <br/>
    <label><a href="?clear">Reset form to defaults</a></label> <br/>
    <center><input type="submit" name="search" value="Search" style="width: auto;" /></center>
</fieldset>

<fieldset><legend>IDE Integration</legend>
    <label for="path_search">Path replacement:</label> <input type="text" name="path_search" value="<?php print $path_search ?>" style="width: 190px;" /> -&gt; <input type="text" name="path_replace" value="<?php print $path_replace ?>" style="width: 190px;" /> <br/>
    <label for="win_paths_cb">Windows paths:</label> <?php print checkBox('win_paths', $win_paths); ?> <br/>
    <label for="use_line_numbers_cb">Use line numbers:</label> <?php print checkBox('use_line_numbers', $use_line_numbers, 'document.getElementById(\'line_separator_s\').style.display = this.checked ? \'\' : \'none\';'); ?> <br/>
    <span id="line_separator_s"<?php echo ($use_line_numbers ? '' : ' style="display: none;"'); ?>><label for="line_separator">Line number separator:</label> <input type="text" name="line_separator" value="<?php print $line_separator ?>" maxlength="1" /></span>
<center><h4 style="margin-bottom: 0; font-weight: bold;"><a name="setup"></a><a href="#" onclick="document.getElementById('protocol_help').style.display = 'block'; return false;">What is this?</a></h4>
<div id="protocol_help">
<p style="text-align: left;">
A utility for performing recursive text searches on a remote (or local) linux webserver.
After a onetime setup, results are quickly openable & editable on the local machine, in the IDE of your choice.<br/>
You can even bookmark searches for yourself or others, filter results & search using regexes!<br/>
Defaults are fully configurable, see the <a href="https://github.com/pospi/grepstrap">source</a>.
</p>
<h4>Setup Instructions (Windows)</h4>
<ol style="text-align: left;">
<li>Download <a href="<?php echo $PDebug_path . 'resources/launchurl.exe'; ?>">launchurl.exe</a> and save wherever you like</li>
<li>Using the inputs above, set your launch protocol (name is not important) &amp; IDE path</li>
<li>Paste the following in a plain text file with extension .reg</li>
<li>Edit "PATH\TO\launchurl.exe" to use the location you downloaded it to (note double backslashes)</li>
<li>Save the text file and run it</li>
<li>Setup <i>path replacement</i> and <i>windows paths</i> options to display file paths as they exist on your local machine</li>
<li>Search!</li>
</ol>
	<label for="launch_pro">Launch protocol:</label> <input type="text" name="launch_pro" value="<?php print $launch_pro ?>" onkeyup="setItemContents(['protocol1', 'protocol2', 'protocol3', 'protocol5', 'protocol6', 'protocol7', 'protocol8'], this.value);" /> <br/>
    <label for="launch_str">Launch path:</label> <input type="text" name="launch_str" value="<?php print $launch_string ?>" onkeyup="setItemContents(['protocol4'], this.value.replace(/\\/g, '\\\\'));" /> <br/>
<pre style="text-align: left;">
Windows Registry Editor Version 5.00

[HKEY_CLASSES_ROOT\<span id="protocol1"><?php print $launch_pro ?></span>]
@="URL:<span id="protocol3"><?php print $launch_pro ?></span> Protocol"
"URL Protocol"=""

[HKEY_CLASSES_ROOT\<span id="protocol5"><?php print $launch_pro ?></span>\DefaultIcon]
@=""

[HKEY_CLASSES_ROOT\<span id="protocol6"><?php print $launch_pro ?></span>\shell]

[HKEY_CLASSES_ROOT\<span id="protocol7"><?php print $launch_pro ?></span>\shell\open]

[HKEY_CLASSES_ROOT\<span id="protocol2"><?php print $launch_pro ?></span>\shell\open\command]
@="<a href="launchurl.exe">PATH\\TO\\launchurl.exe</a> \"%1\""

[HKEY_CURRENT_USER\Software\URL Protocol Launcher]
"<span id="protocol8"><?php print $launch_pro ?></span>"="<span id="protocol4"><?php print str_replace('\\', '\\\\', $launch_string) ?></span> \"%1\""
</pre>
    </div>
    </center>
</fieldset>
</form>
</center>
<script type="text/javascript">
    tooltips = {
        pattern :               "search text",
        path1 :                 "folder to search in",
        path2:                  "folder to search in",
        case_sensitive_cb :     "treat search as case-sensitive",
        line_stats_cb :         "show number of files & lines searched in results",
        regexp_search_cb :      "treat search as a perl regular expression",
        types :                 "file extensions to search in (use * for all filetypes)",
        exclude_types :         "file extensions to exclude from search",
        ignore :                "subfolders of Search Path to exclude from the search",
        filenames_only_cb :     "only display filenames in results, rather than full paths",
        trim_output_lines_cb :  "trim starting and trailing whitespace from matching lines for display",
        path_search :           "replace a string in all search results - useful to convert remote paths to local share",
        path_replace :          "replace a string in all search results - useful to convert remote paths to local share",
        win_paths_cb :          "use windows paths (\\) instead of linux paths (/)",
        use_line_numbers_cb :   "use if your IDE is capable of opening files at a specific line",
        line_separator :        "the line number separator your IDE uses (as in 'matching_file.txt/123')",
        launch_pro :            "the url protocol you have registered to open your IDE",
        launch_str :            "path to IDE executable, only used to generate a windows registry file for importing (below)"
    };

    for (var i = 0, input; input = document.forms['searchForm'].elements[i]; ++i) {
        for (var j in tooltips) {
            if (typeof tooltips[j] == 'string') {
                if (input.name == j) {
                    input.title = tooltips[j];
                }
            }
        }
    }
</script>
</body>
</html>
