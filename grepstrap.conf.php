<?php
/*================================================================================
	GrepStrap - configuration
	----------------------------------------------------------------------------
	Config settings.
	This file only sets defaults, everything is configurable through the UI.
	----------------------------------------------------------------------------
	Copyright (c) 2008 Sam Pospischil <pospi@spadgos.com>
  ===============================================================================*/


// --- SEARCH OPTIONS ---

// Default search path. This would usually be the root of your webserver / project.
// Note that you can split the path into two components for ease of modifying search subpath
$default_path_base  = '/var/www/html/';
$default_path_extra = '';

$case_sensitive = false;
$line_stats = false;
$regexp_search = false;


// --- RESULT OPTIONS ---

// Any paths (relative to search path) which should not be scanned.
// Anything which can be added here, should be - it will make searches faster.
$ignore_paths = array(
	
);
// Filetypes to search within
$include_types = array(
	
);
// Filetypes which should never be searched
$exclude_types = array(
	'bak'
);
$show_filenames_only = true;
$trim_output_lines = false;


// --- IDE INTEGRATION --- (@see http://github.com/pospi/pdebug)

$PDebug_path = 'https://github.com/pospi/pdebug/raw/master/';		// this is just used as a download link for launchurl.exe
$path_search  = '';
$path_replace = '';
$windows_path = false;
$line_separator = '/';          // separates filename & line number, as in "match.php/312"
$use_line_numbers  = true;      // only disable this if your IDE doesn't support it

// Vars for the registry script generator
$ide_launch_protocol = 'pdebug';
$ide_launch_string = 'C:\Program Files\UltraEdit\uedit32.exe';
?>
