<?php

/*
	Jag - Simple PHP web framework
    Copyright (C) 2008  Coopérative Molotov

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

// UTF-8 Stuff
mb_language('uni');

/* FIXME
// Set error log
ini_set('error_log', 'engine/logs/error.log');
ini_set('log_errors', true);
*/

error_reporting(0);

// Create global $_JAG array
$_JAG = array();

// Add app/ and engine/ to include path
set_include_path(get_include_path() . PATH_SEPARATOR . './app' . PATH_SEPARATOR . './engine');

// Load classes
$classesDir = 'engine/classes';
$dirHandle = opendir($classesDir);
while ($filename = readdir($dirHandle)) {
	// Make sure files end in .php
	if ($filename != basename($filename, '.php')) {
		$classPath = $classesDir .'/'. $filename;
		if (!is_dir($classPath)) {
			require_once $classPath;
		}
	}
}

// Start caching engine; this also initializes output buffering
$_JAG['cache'] = new Cache();

// Start output buffering
ob_start('mb_output_handler');

// Define files directory
$_JAG['filesDirectory'] = 'files/';

// Load configuration files
$_JAG['project'] = IniFile::Parse('app/config/project.ini', true);
$_JAG['server'] = IniFile::Parse('app/config/server.ini', true);

// Define constants
define('ROOT', $_JAG['server']['root']);
define('ERROR_FOREIGN_KEY_CONSTRAINT', 2); // Used in engine/classes/Module.php

// Load database field types
$_JAG['fieldTypes'] = IniFile::Parse('engine/database/types.ini');

// Load fields
$_JAG['moduleFields'] = IniFile::Parse('engine/database/moduleFields.ini', true);
$_JAG['versionsSupportFields'] = IniFile::Parse('engine/database/versionsSupportFields.ini', true);

// Load GetID3
require_once('engine/libraries/getid3/getid3.php');

// Determine default language
$_JAG['defaultLanguage'] = $_JAG['project']['languages'][0];

// Determine requested language
$requestedLanguage = $_GET['language'];
if ($requestedLanguage && in_array($requestedLanguage, $_JAG['project']['languages'])) {
	// User has manually switched language
	Cookie::Create('language', $requestedLanguage);
	$_JAG['language'] = $requestedLanguage;
} elseif ($_COOKIE['language']) {
	// User has previously selected a language
	$_JAG['language'] = $_COOKIE['language'];
} else {
	// User has never selected a language; use default
	$_JAG['language'] = $_JAG['defaultLanguage'];
}

// Load strings in the requested language
$_JAG['strings'] = IniFile::Parse('engine/strings/' . $_JAG['language'] . '.ini', true);

// Load shorthands (useful function aliases; must load after strings)
require 'engine/helpers/shorthands.php';

// Connect to database
$db = $_JAG['server']['database'];
if (!$_JAG['databaseLink'] = Database::Connect($db['server'], $db['username'], $db['password'], $db['database'])) {
	trigger_error("Couldn't connect to database " . $db['database'], E_USER_ERROR);
}

// Get available modules list
$engineModules = Filesystem::GetDirNames('engine/modules');
$_JAG['appModules'] = Filesystem::GetDirNames('app/modules');
$_JAG['availableModules'] = $engineModules + $_JAG['appModules'];

// Strip root directory and trailing slashes from full path request
$pathString = $_SERVER['REQUEST_URI'];
$barePath = substr($pathString, 0, strrpos($pathString, '?'));
$pathString = $barePath ? $barePath : $pathString;
$fullRequest = rtrim($pathString, '/');
preg_match('|^'. ROOT .'(.*)$|', $fullRequest, $requestArray);

// Use requested URL, or use root path if none was requested
$_JAG['request'] = $requestArray[1] ? $requestArray[1] : $_JAG['project']['rootPath'];

// We sync using database time (might differ from PHP time)
$databaseTimeQuery = new Query(array('fields' => 'NOW()'));
$databaseTime = $databaseTimeQuery->GetSingleValue();
$_JAG['databaseTime'] = $databaseTime;

// Make sure everything is properly initialized
$tables = Database::GetTables();
if (!$tables['users'] || !$tables['_paths']) {
	require 'engine/helpers/firstrun.php';
}

// Get installed modules list
$_JAG['installedModules'] = Query::SimpleResults('_modules');

// Create User object
$_JAG['user'] = new User();

// Determine mode
$requestedMode = $_GET['mode'];
$availableModes = IniFile::Parse('engine/config/modes.ini');
if ($availableModes[$requestedMode]) {
	$_JAG['requestedMode'] = $requestedMode;
}

// Load paths
$paths = Query::FullResults('_paths');
foreach ($paths as $path) {
	// Use path as key in $_JAG['paths'] array
	$_JAG['paths'][$path['path']] = $path;
}

// Look for request in paths
if ($path = $_JAG['paths'][$_JAG['request']]) {
	// Path does exist
	if ($path['current']) {
		// This is a valid path; proceed to module
		$_JAG['rootModuleName'] = $_JAG['installedModules'][$path['module']];
		if ($_JAG['rootModule'] = Module::GetNewModule($_JAG['rootModuleName'], $path['item'])) {
			// Check whether we have sufficient privileges to display the module
			if ($_JAG['rootModule']->CanView()) {
				$_JAG['rootModule']->Display();
				
				// Display super views, only for root module
				$_JAG['rootModule']->DisplaySuperViews();
				
				// Determine path to admin pane for this item
				$adminPath = 'admin/'. $moduleName;
				if ($_JAG['paths'][$adminPath]) {
					if ($path['item']) {
						$adminPath .= '?a=edit&id='. $path['item'];
					}
					$_JAG['adminPath'] = ROOT . $adminPath;
				} else {
					$_JAG['adminPath'] = ROOT . 'admin';
				}
			} else {
				// Display login if we don't
				$_JAG['user']->Connect();
			}
		} else {
			trigger_error("Couldn't load root module", E_USER_ERROR);
		}
	} else {
		// This is an obsolete URL; find its current (up to date) equivalent
		$whereArray = array(
			'module = '. $path['module'],
			'item = '. $path['item'],
			"language = '". $path['language'] ."'",
			'current = TRUE'
		);
		$currentPath = Query::SingleValue('_paths', 'path', $whereArray);
		HTTP::NewLocation($currentPath);
	}
} else {
	// Path does not exist; throw 404
	header("HTTP/1.0 404 Not Found");
	require 'errors/404.php';
}

// Store and flush the contents of the output buffer
$_JAG['body'] = ob_get_contents();
ob_end_clean();

// Determine mode; default is HTML
if (!$_JAG['mode']) {
	$_JAG['mode'] = 'html';
}

// Determine template
if ($_JAG['mode'] == 'html') {
	// If we're in HTML mode and no template was specified, use HTML
	if (!$_JAG['template']) $_JAG['template'] = 'html';
} else {
	// We're not in HTML mode; use mode as template
	$_JAG['template'] = $_JAG['mode'];
}

// Load template
$templateFile = 'templates/'. $_JAG['template'] .'.php';
if (Filesystem::FileExistsInIncludePath($templateFile)) {
	require $templateFile;
}

// Set MIME type
$contentType = $_JAG['contentType'] ? $_JAG['contentType'] : $availableModes[$_JAG['mode']];
if ($contentType) {
	header('Content-Type: '. $contentType);
}

// Write to cache; this also cleans output buffering
$_JAG['cache']->Write();

?>