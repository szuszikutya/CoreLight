<?php
	error_reporting( E_ALL );

	define('LIBRARY_PATH', '../cLibrary');
	define('SYSTEM_PATH',  '../');

	ini_set('include_path', implode(PATH_SEPARATOR, array(LIBRARY_PATH, SYSTEM_PATH, get_include_path())));

	require_once ( LIBRARY_PATH.'/Core.php');
	Core::Make('script.ini');
