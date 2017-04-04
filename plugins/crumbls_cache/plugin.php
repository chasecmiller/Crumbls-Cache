<?php
/*
	Plugin Name: Caching
	Plugin URI: http://crumbls.com
	Description: Caching for WP via PHPFastCache Not for production. Works 100%, just not 100% tested.
	Author: Chase C. Miller
	Version: 1.0a
	Author URI: http://crumbls.com
	Text Domain: crumbls\plugins\fastcache
	Domain Path: /assets/lang
 */

namespace crumbls\plugins\fastcache;

use phpFastCache\CacheManager;

defined('ABSPATH') or exit(1);

global $cache;

class Plugin
{
    protected $instance = null;
    protected $tags = null;
    protected $expires = -1;

    public function __construct()
    {
        // Setup File Path on your config files
        CacheManager::setDefaultConfig([
            'path' => WP_CONTENT_DIR . '/cache/crumbls/',
        ]);

        $this->instance = CacheManager::getInstance('files');

        // Break away while possible.
        if (!function_exists('add_action')) {
            return;
        }

        // Save/Insert post handler. - We ignore this now and just use it when a post is published.
        add_action('wp_insert_post', array(&$this, 'savePost'), PHP_INT_MAX - 1, 3);

        // Handle single posts.
        add_action('the_post', array(&$this, 'actionThePost'));

        // Set expiration times
        add_action('pre_get_posts', array(&$this, 'actionPreGetPosts'));

        // On publish
        add_action('publish_post', [&$this, 'postPublish'], 10, 2);

        // Toolbar
        add_action('admin_bar_menu', [$this, 'adminToolbar'], 999);
    }

    /**
     * advanced-cache.php handler.
     **/
    public function advancedCache()
    {
        global $wpdb;
        if (preg_match('#/wp-(admin|login)#', $_SERVER['REQUEST_URI'])) {
            return;
        }

        if (array_key_exists('s', $_REQUEST)) {
            //return;
        }

        if (array_key_exists('p', $_REQUEST) && is_numeric($_REQUEST['p'])) {
            if (!isset($wpdb) || !$wpdb) {
                //	echo 'a';
            }
            return;
        }


        $this->tags = false;
        if (!defined('cache_key')) {
            // Allowed query strings.
            $allowed = [
                'paged',
                'member',
                's',
                'feed'
            ];

            $args = array_filter(array_intersect_key($_REQUEST, array_flip($allowed)));

            /**
             * Add in membership level.
             * Not secure.
             * Allow bypass for search engines.
             * This is written for membership sites.
             **/
            /*
            if (array_key_exists('userdata', $_COOKIE) && preg_match('#"user_status":\s?"(.*?)"#', $_COOKIE['userdata'], $m)) {
                $args['member'] = $m[1];
            } else {
		$args['member'] = false;
	    }
*/
            $args['url'] = explode('?', $_SERVER['REQUEST_URI'], 2)[0];

            if ($args['url'] == '/' || strpos($args['url'], '/category/') !== false) {
                unset($args['paged']);
                unset($args['member']);
            }

            ksort($args);

            $this->tags = [
                $args['url']
            ];

            if (sizeof($args) == 1 && array_key_exists('url', $args)) {
                define('cache_key', $args['url']);
            } else {
                define('cache_key', md5(serialize($args)));
            }
        }

        $storage = $this->read(cache_key);

        if ($storage) {
            echo $storage;
            printf('<!-- Cache: %s -->', cache_key);
            exit(1);
        }

        if (!$this->tags) {
            $this->tags = ['/' . trim(explode('?', $_SERVER['REQUEST_URI'], 2)[0], '/')];
        }

        ob_start(); // Start the output buffer

        // Register shutdown function.
        register_shutdown_function(function () {
            if (defined('DOING_CRON') && DOING_CRON) {
                return;
            }
            if (is_admin()) {
                return;
            }

            // This is being called on the front page.  It should not be.
            if (defined('DONOTCACHEPAGE')) {
                printf('<!-- %s -->', __('Cache Disabled', __NAMESPACE__));
                return;
            }

            if (!defined('cache_key')) {
                printf('<!-- %s -->', __('Cache Disabled Due To Missing Cache Key', __NAMESPACE__));
                return;
            }

            // Quick cleanup.
            $this->tags = array_unique($this->tags);

            $this->add(cache_key, ob_get_contents(), $this->tags, $this->expires);

            ob_end_flush();
        });
    }

    /**
     * (B)rowse
     **/
    public function browse()
    {
    }

    /**
     * (r)ead
     **/
    public function read($key)
    {
        if ($ret = $this->instance->getItem($key)) {
            return $ret->get();
        }
        return false;
    }

    /**
     * (e)dit
     **/
    public function edit($key, $value)
    {
    }

    /**
     * (a)dd
     **/
    public function add($key, $value, $tags = null, $expires = -1)
    {
        $CachedString = $this->instance->getItem($key);
        $CachedString->set($value);
        if ($tags) {
            $CachedString->setTags($tags);
        }
        if ($expires > 0) {
            $CachedString->expiresAfter($expires);
        }
        $this->instance->save($CachedString);
    }

    /**
     * (d)elete
     **/
    public function delete($key = null, $tags = null)
    {
        // Easy way to clean up key or tags.
        if ($key) {
            //echo $key;
            $this->instance->deleteItem($key);
        }
        if ($tags) {
            if (!is_array($tags)) {
                $tags = [$tags];
            }
            $this->instance->deleteItemsByTags($tags);
        }

    }

    /**
     * Return cache instance.
     **/
    public function getInstance()
    {
        return $this->instance;
    }

    public function postPublish($ID, $post)
    {
        // A function to perform actions when a post is published.
        if ($post->post_type != 'post') {
            return;
        }

        $i = strlen(site_url());

        $this->tags = [];

        // Clean up any assosciated, public taxonomies.
        foreach (wp_get_object_terms($post->ID, get_taxonomies(['public' => true, '_builtin' => true], 'names', 'and'), ['fields' => 'all']) as $term) {
            $this->tags[] = '/' . trim(substr(get_term_link($term), $i), '/');
        }

        $this->delete(null, $this->tags);
//		$this->delete(null, ['/']);
    }


    /**
     * Save post handler.
     * Clear the cache and anywhere it may exist.
     **/
    public function savePost($post_id, $post, $update = false)
    {
        global $post;
        // Must match our post types.
        if (!in_array($post->post_type, ['post', 'attachment', 'topic', 'reply'])) {
            return;
        }

        $i = strlen(site_url());

        // Delete all posts tagged with this.
        if ($post->post_name) {
            $this->tags[] = '/' . trim(substr(get_permalink($post), $i), '/');
        }

        $x = array_search('/', $this->tags);

        if ($x !== false) {
            unset($this->tags[$x]);
        }

        // Trap door here.
        if (!$this->tags) {
            return;
        }

        // Delete for all membership levels.

        // Trap door for any status that does not matter to our cache.
        if (in_array($post->post_status, ['future', 'draft', 'pending', 'private', 'trash', 'auto-draft'])) {
            // Debug this real fast.
            if ($this->tags) {
                $this->delete(null, $this->tags);
            }
            return;
        }


        // Trap door for old posts.
        $minutes = round(abs(current_time('timestamp', 0) - strtotime($post->post_date)) / 60, 2);
        $hours = $minutes / 60;
        // Handled by post status change.
        if ($minutes < 1) {
            return;
        }
        if ($hours > 24) {
            $this->delete(null, $this->tags);
            return;
        }

        // Clean up any assosciated, public taxonomies.
        foreach (wp_get_object_terms($post_id, get_taxonomies(['public' => true, '_builtin' => true], 'names', 'and'), ['fields' => 'all']) as $term) {
            $this->tags[] = '/' . trim(substr(get_term_link($term), $i), '/');
        }


        $this->tags = array_unique($this->tags);

//        wp_mail('cmiller@bizwest.com', 'check insert 2', var_export($post,true).' '.var_export($this->tags,true));
        $this->delete(null, $this->tags);
    }


    /**
     * Handles the_post action.
     * The idea is that when the main query uses the_post on a single entry, we add all categories to the tags.
     * It lets us clear the cache easier.
     * @param $post
     */
    public function actionThePost($post)
    {
        if (!is_main_query()) {
            return;
        }
        if (is_archive() || is_category() || !is_single($post->ID)) {
            return;
        }

        $i = strlen(site_url());
        foreach (wp_get_object_terms($post->ID, get_taxonomies(['public' => true, '_builtin' => true], 'names', 'and'), ['fields' => 'all']) as $term) {
            $this->tags[] = '/' . trim(substr(get_term_link($term), $i), '/');
        }
    }

    /**
     * Handles pre_get_posts action.
     * We use this just to set our expiration time for archives.
     */
    public function actionPreGetPosts($query)
    {
        if (!$query->is_main_query() || !$query->is_archive()) {
            return;
        }

        $this->expires = 86400; // In seconds.
    }


    // Add Toolbar Menus
    public function adminToolbar()
    {
        global $wp_admin_bar, $wp;

        if (!current_user_can('manage_options')) {
            return;
        }

        $wp_admin_bar->add_menu([
            'id' => 'crumbls_cache',
            'title' => __('Cache', __NAMESPACE__),
            'href' => admin_url('options-general.php?page=cache')
        ]);

        // Category, archive, etc?
        $wp_admin_bar->add_menu([
            'parent' => 'crumbls_cache',
            'title' => __('Clear all', __NAMESPACE__),
            'href' => admin_url('admin.php?page=cache&action=clearAll&key=' . time())
        ]);
        /*
            // Category, archive, etc?
            $wp_admin_bar->add_menu([
                'parent' => 'crumbls_cache',
                'title' => __('Clear all', __NAMESPACE__),
                'href' => FALSE
            ]);
*/
    }


}

require_once(dirname(__FILE__) . '/assets/php/phpfastcache/src/autoload.php');

if (is_admin()) {
    // No admin side yet.
    require_once(dirname(__FILE__) . '/admin.php');
    $cache = new Admin();
} else {
    $cache = new Plugin();
}
