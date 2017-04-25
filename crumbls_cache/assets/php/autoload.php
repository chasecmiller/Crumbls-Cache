<?php
/**
 *
 * This file is part of phpFastCache.
 * Heavily modified by Chase C. Miller
 *
 * @license MIT License (MIT)
 *
 * For full copyright and license information, please see the docs/CREDITS.txt file.
 *
 * @author Khoa Bui (khoaofgod)  <khoaofgod@gmail.com> http://www.phpfastcache.com
 * @author Georges.L (Geolim4)  <contact@geolim4.com>
 * @author Chase C. Miller ( chasemiller ) <chase@crumbls.com>
 *
 */


defined('PFC_PHP_EXT') || define('PFC_PHP_EXT', 'php');
defined('PFC_BIN_DIR') || define('PFC_BIN_DIR', __DIR__ . '/phpFastCache/Bin/');

/**
 * Register Autoload
 */
spl_autoload_register(function ($entity) {
    $module = explode('\\', $entity, 2);
    if (!in_array($module[ 0 ], ['crumbls', 'phpFastCache', 'Psr', 'Minify'])) {
        //exit;
        /**
         * Not a part of phpFastCache file
         * then we return here.
         */
        return;
    } else if ($entity == 'phpFastCache\CacheManager') {
        $path = dirname(PFC_BIN_DIR) . DIRECTORY_SEPARATOR . 'CacheManager.php';
        if (is_readable($path)) {
            require_once $path;
        } else {
            trigger_error('Cannot locate the Psr/Cache files', E_USER_ERROR);
        }
        return;
    } else if (strpos($entity, 'phpFastCache\\') === 0) {
        $path = dirname(dirname(PFC_BIN_DIR)).DIRECTORY_SEPARATOR.str_replace('\\', '/', $entity).'.php';
        if (file_exists($path)) {
            require_once($path);
            return;
        }
    } else if (strpos($entity, 'Psr\Cache') === 0) {
        $path = PFC_BIN_DIR . 'legacy/Psr/Cache/src/' . substr(strrchr($entity, '\\'), 1) . '.' . PFC_PHP_EXT;

        if (is_readable($path)) {
            require_once $path;
        }else{
            trigger_error('Cannot locate the Psr/Cache files', E_USER_ERROR);
        }
        return;
    } else if (strpos($entity, 'crumbls\\') === 0) {
        $path = dirname(__FILE__).'/'.$module[1].'/'.$module[1].'.php';
        if (is_readable($path)) {
            require_once $path;
        }else{
            trigger_error('Cannot locate the class: '.$module[1], E_USER_ERROR);
        }
        return;
    } else if (strpos($entity, 'Minify') !== false) {
        $path = dirname(__FILE__).DIRECTORY_SEPARATOR.dirname(str_replace('\\', '/', $entity)).'.php';
        if (file_exists($path)) {
            require_once($path);
            return;
        }
    }
    $entity = str_replace('\\', '/', $entity);
    $path = __DIR__ . '/' . $entity . '.' . PFC_PHP_EXT;

    echo $path;

    if (is_readable($path)) {
        require_once $path;
        return;
    }
    print_r($entity);
    print_r($module);
    exit;


});

if ((!defined('PFC_IGNORE_COMPOSER_WARNING') || !PFC_IGNORE_COMPOSER_WARNING) && class_exists('Composer\Autoload\ClassLoader')) {
  trigger_error('Your project already makes use of Composer. You SHOULD use the composer dependency "phpfastcache/phpfastcache" instead of hard-autoloading.',
    E_USER_WARNING);
}