<?php
/**
* Added by Chase C. Miller
* Caching Engine.
**/

defined('ABSPATH') or exit(1);

global $cache;

if (!$cache) {
	$file = dirname(__FILE__).'/plugins/crumbls_cache/plugin.php';

	if (!file_exists($file)) {
		return;
	}

	if (!is_admin()) {
		require_once($file);
		$cache->advancedCache();
	}

}
