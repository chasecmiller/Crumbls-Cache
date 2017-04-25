<?php return array (
  'page' => 
  array (
    'securityKey' => 'auto',
    'ignoreSymfonyNotice' => false,
    'defaultTtl' => 900,
    'htaccess' => '1',
    'default_chmod' => '511',
    'path' => '/var/www/html/crumbls/wp-content/cache/crumbls/',
    'fallback' => false,
    'limited_memory_each_object' => 4096,
    'compress_data' => true,
    'type' => 'files',
    'enabled' => true,
  ),
  'object' => 
  array (
    'type' => 'wpobjectcache',
    'enabled' => true,
  ),
  'transient' => 
  array (
    'type' => 'object',
    'enabled' => true,
  ),
);