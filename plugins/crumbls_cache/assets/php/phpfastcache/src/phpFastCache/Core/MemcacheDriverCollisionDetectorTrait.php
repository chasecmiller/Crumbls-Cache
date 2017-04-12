<?php
namespace phpFastCache\Core;

/**
 * Trait MemcacheDriverCollisionDetectorTrait
 * @package phpFastCache\Core
 */
trait MemcacheDriverCollisionDetectorTrait
{
    /**
     * @var string
     */
    protected static $driverUsed;
    private static $thrown;

    /**
     * @param $driverName
     * @return bool
     */
    public static function checkCollision($driverName)
    {
        $CONSTANT_NAME = __NAMESPACE__ . '\MEMCACHE_DRIVER_USED';

        if ($driverName && is_string($driverName)) {
            if (!defined($CONSTANT_NAME)) {
                define($CONSTANT_NAME, $driverName);
                return true;
            } else if (constant($CONSTANT_NAME) !== $driverName) {
                if (self::$thrown) {
                    return true;
                }
                self::$thrown = true;
                // Modified by Chase C. Miller
                if (function_exists('add_action')) {
                    add_action('admin_notices', function () {
                        $class = 'notice notice-warning';
                        $message = __('Memcache collision detected, you used both Memcache and Memcached driver in your script, this may leads to unexpected behaviours', 'crumbls\plugins\fastcache');

                        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
                    });
                } else {
                    trigger_error('Memcache collision detected, you used both Memcache and Memcached driver in your script, this may leads to unexpected behaviours',
                        E_USER_WARNING);
                }

                return false;
            }

            return true;
        }

        return false;
    }
}