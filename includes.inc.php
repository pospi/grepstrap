<?php
	// Load a config file from various places.
	// This allows you to define your search script configurations on a per-vhost basis.
	if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/grepstrap.conf.php')) {							// document root
		include($_SERVER['DOCUMENT_ROOT'] . '/grepstrap.conf.php');
	} else {																						// default config
		include('grepstrap.conf.php');
	}
?>
 
