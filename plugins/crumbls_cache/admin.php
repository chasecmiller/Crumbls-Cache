<?php
/**
 * See comments in plugin.php
 */

namespace crumbls\plugins\fastcache;

use phpFastCache\CacheManager;

defined('ABSPATH') or exit(1);

global $cache;

class Admin extends Plugin
{
    public function __construct()
    {
        parent::__construct();

        add_action('admin_menu', array(&$this, 'actionAdminMenu'), PHP_INT_MAX - 1);
    }

    /**
     * Handles action admin_menu
     * Setup submenu
     */
    public function actionAdminMenu()
    {
        global $submenu;

        $parent = 'options-general.php';

        if (!array_key_exists($parent, $submenu)) {
            return;
        }

        add_submenu_page($parent, __('Cache', __NAMESPACE__), __('Cache', __NAMESPACE__), 'manage_options', 'cache', array(&$this, 'pageCache'));
    }

    /**
     * Admin Page - Cache
     */
    public function pageCache() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (

            array_key_exists('key', $_REQUEST)
        &&
            array_key_exists('action', $_REQUEST)
                    &&
            is_numeric($_REQUEST['key'])
            &&
            in_array($_REQUEST['action'], ['clearAll', 'clearFrontpage'])) {
            // How to stop someone from just hitting refresh and retriggering?
            switch($_REQUEST['action']) {
                case 'clearAll':
                    $this->instance->clear();
                    break;
                case 'clearFrontpage':
                    $this->delete('/', ['/']);
                    break;
                default:
            }
        }

        $path = false;

        if (preg_match('#"path":"(.*?)"#', json_encode((array)$this->instance), $path)) {
            $path = stripslashes($path[1]);

            $objects = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::SELF_FIRST);
            $temp = [];
            $i = 0;
            foreach ($objects as $name => $object) {
                $temp[] = $name;//rtrim($name, '/.');
            }

            $folders = array_map(function($e) { return rtrim($e, '/.'); }, preg_grep('#\/\.$#', $temp));
            $files = array_diff(preg_grep('#.*\/\.?.*?\.\w+$#', $temp), $folders);
        }
        ?>
        <div class="wrap">
            <h1><?php _e( 'Cache', __NAMESPACE__); ?></h1>
            <?php
            printf('<p>Cached entries: %d</p>', sizeof($files));

            $url = admin_url('admin.php?page=cache&action=clearAll&key='.time());
            if ($files) {
                // Exists
                printf('<a href="%s" class="button button-primary">%s</a>', $url, __('Clear Cache', __NAMESPACE__));
            } else {
                printf('<a href="%s" class="button button-secondary disabled button-disabled" disabled>%s</a>', $url, __('Clear Cache', __NAMESPACE__));
            }

            echo '<br />';

            $url = admin_url('admin.php?page=cache&action=clearFrontpage&key='.time());

            if ($this->instance->hasItem('/')) {
                // Exists
                printf('<a href="%s" class="button button-primary">%s</a>', $url, __('Clear Front Page', __NAMESPACE__));
            } else {
                printf('<a href="%s" class="button button-secondary disabled button-disabled" disabled>%s</a>', $url, __('Clear Front Page', __NAMESPACE__));
            }

            ?>
        </div>
        <?php
    }
}
