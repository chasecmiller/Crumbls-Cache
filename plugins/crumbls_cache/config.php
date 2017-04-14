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
    'compress_data' => false,
    'type' => 'files',
    'enabled' => true,
  ),
  'object' => 
  array (
    'securityKey' => 'auto',
    'ignoreSymfonyNotice' => false,
    'defaultTtl' => 900,
    'htaccess' => true,
    'default_chmod' => 511,
    'path' => '',
    'fallback' => false,
    'limited_memory_each_object' => 4096,
    'compress_data' => false,
    'servers' => 'crumbls-test.tnfo7n.0001.usw2.cache.amazonaws.com:11211',
    'type' => 'memcached',
    'enabled' => true,
  ),
  'transient' => 
  array (
    'type' => 'object',
    'enabled' => true,
  ),
);