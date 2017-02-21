<?php
/**
* Added by Chase C. Miller
* Caching Engine.
**/

global $cache;

$file = dirname(__FILE__).'/plugins/crumbls_cache/plugin.php';

if (!file_exists($file)) {
	return;
}
	
include($file);

if (!is_admin()) {
	$cache->advancedCache();
}

