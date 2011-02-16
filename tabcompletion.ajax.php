<?php
/*================================================================================
	GrepStrap - tab completion
	----------------------------------------------------------------------------
	Provides filesystem tab completion to frontend JavaScript
	----------------------------------------------------------------------------
	Copyright (c) 2008 Sam Pospischil <pospi@spadgos.com>
  ===============================================================================*/
	
	$path		= $_POST['path'];
	
	$lastSep	= strrpos($path, '/');
	$dir		= substr($path, 0, $lastSep);
	$find		= substr($path, $lastSep + 1);
	
	$suggs = `ls $dir | grep '^$find'`;
	$suggs = rtrim($suggs);
	
	echo $suggs;
?>
