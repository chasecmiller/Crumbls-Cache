<?php
/**
* Added by Chase C. Miller
* Caching Engine.
**/

defined('ABSPATH') or exit(1);

global $cache;

$file = dirname(__FILE__).'/plugins/crumbls_cache/plugin.php';

if (!file_exists($file)) {
	return;
}
	
require_once($file);
if (!is_admin()) {
	$cache->advancedCache();
}

