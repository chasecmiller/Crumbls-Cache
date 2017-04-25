<?php
/*
	Plugin Name: Caching
	Plugin URI: http://crumbls.com
	Description: Caching for WP via PHPFastCache Not for production. Works 100%, just not 100% tested.
	Author: Chase C. Miller
	Version: 2.0.1a
	Author URI: http://crumbls.com
	Text Domain: crumbls\plugins\fastcache
	Domain Path: /assets/lang
 */

namespace crumbls\plugins\fastcache;

use Minify\HTML\Minify_HTML;
use phpFastCache\CacheManager;

defined('ABSPATH') or exit(1);

global $cache;


class Plugin
{
    // Hand holding.
    protected $page = null;
    protected $object = null;
    protected $transient = null;

    protected $tags = null;
    protected $expires = -1;
    protected $config_path = __DIR__ . '/config.php';

    public function __construct()
    {
        // Initialize our caching engine.
        $this->init();

        // Break away while possible.
        if (!function_exists('add_action')) {
            return;
        }
//});
//        register_deactivation_hook(__FILE__, array($this, 'hook_deactivate'));

//        global $wp_filter;

//        add_action('activate_)

        // Handle initialization
        add_action('init', [$this, 'actionInit']);

        // Updated - We now trigger prior to updating the settings.
        add_filter('pre_update_option_crumbls_settings', [$this, 'optionUpdate'], PHP_INT_MAX, 3);

        // Save/Insert post handler. - We ignore this now and just use it when a post is published.
        add_action('wp_insert_post', [$this, 'savePost'], PHP_INT_MAX - 1, 3);

        // Handle single posts.
        add_action('the_post', [$this, 'actionThePost']);

        // Handle comments
        add_action('comment_post', [$this, 'actionCommentPost'], 10, 3);

        // Set expiration times
        add_action('pre_get_posts', [$this, 'actionPreGetPosts']);

        // On publish
        add_action('publish_post', [&$this, 'postPublish'], 10, 2);

        // Toolbar
        add_action('admin_bar_menu', [$this, 'adminToolbar'], 999);

        // Fallback until we have language files built.
        add_filter('gettext', function ($trans, $text, $dom) {
            if ($dom !== __NAMESPACE__) {
                return $trans;
            }
            $trans = ucwords($trans);
            $trans = preg_replace('#[^A-Za-z0-9]#', ' ', $trans);
            $trans = trim($trans);
            return $trans;
        }, 10, 3);

    }

    /**
     * initialize our engine.
     * @throws \phpFastCache\Exceptions\phpFastCacheDriverCheckException
     */
    private function init()
    {
        global $wp_plugin_paths;

        if (!is_array($wp_plugin_paths)) {
            $wp_plugin_paths = [];
        }

        if (function_exists('wp_plugin_directory_constants')) {
//            wp_plugin_directory_constants();
        }

        defined('WP_PLUGIN_DIR') or define('WP_PLUGIN_DIR', rtrim(WP_CONTENT_DIR, '/') . '/plugins');
        defined('WPMP_PLUGIN_DIR') or define('WPMU_PLUGIN_DIR', rtrim(WP_CONTENT_DIR, '/') . '/mu-plugins');

        $s = null;

        if (
            file_exists($this->config_path)
            &&
            is_readable($this->config_path)
        ) {
            try {
                $s = @include($this->config_path);
            } catch (\Exception $e) {
                $s = false;
                @unlink($this->config_path);
            }
        }

        if (!$s) {
            @unlink($this->config_path);
            return;
        }

        // Set default.
        if (!is_array($s)) {
            // Only run when needed.
//            $this->generateConfig();
            $s = [];
        }

        $s = array_intersect_key($s, array_flip($this->getTypes()));
        $s = array_filter($s, function ($e) {
            return array_key_exists('enabled', $e)
            && $e['enabled']
            && array_key_exists('type', $e)
            && $e['type'];
        });
        //debug;

        foreach ($s as $k => $v) {
            if ($this->$k) {
                // Bypass.
                continue;
            }
            // Check for other type request.
            // Not yet implemented.
            if (
                in_array($v['type'], $this->getTypes())
                &&
                $v['type'] !== $k
            ) {
                $ref = $v['type'];
                $this->$k = &$this->$ref;
            } else {
                $this->$k = CacheManager::getInstance($v['type'], $v);
            }
        }

        // Fallback section.
        if (!$this->object) {
            $this->object = CacheManager::getInstance('wpobjectcache', []);
        }
        if (!$this->transient) {
            $this->object = CacheManager::getInstance('wpobjectcache', []);
        }
    }

    /**
     * initialization handler.
     */
    public function actionInit()
    {
        if (!file_exists($this->config_path)) {
            $this->generateConfig();
        }
    }

    /**
     * advanced-cache.php handler.
     * Auto set/get page cache.
     **/
    public function advancedCache()
    {
        global $wpdb, $current_user;

        // Determine if we should load advanced cache.
        if (!$this->page) {
            $message = 'Page cache is disabled';
            if (function_exists('__')) {
                $message = __($message, __NAMESPACE__);
            }
            printf('<!-- %s -->', $message);
            return;
        }

        if (preg_match('#/wp-(admin|login)#', $_SERVER['REQUEST_URI'])) {
            return;
        }

        if (array_key_exists('s', $_REQUEST)) {
            //return;
        }

        $this->tags = false;
        if (!defined('cache_key')) {
            // Allowed query strings.
            $allowed = [
                'paged',
                'member',
                's',
                'feed',
                'date'
            ];

            $args = array_filter(array_intersect_key($_REQUEST, array_flip($allowed)));
            /**
             * Get current user's data.
             * Allow override, eventually.
             **/
            if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            } else if (true) {
                $args['is_logged_in'] = 0;
                /**
                 * Check login cookie.
                 * This needs reinforced down the road to prevent fake cookies to read.
                 */
                if (!$current_user) {
                    if ($temp = preg_grep('#^wordpress_logged_in_#', array_keys($_COOKIE))) {
                        $temp = array_values($temp);
                        if (sizeof($temp) == 1) {
                            // Look at username.
                            $args['is_logged_in'] = 1;
                        }
                    } else if (array_key_exists('u', $_REQUEST) && is_numeric($_REQUEST['u'])) {
                        /**
                         * We temporarily ignore caching in this case.
                         * Your site will probably never use this.
                         * We use it as an automatic login for our aging subscriber age.
                         * When a user enters via a link, they have a field that has a unique ID from
                         * our mailer service.  That link will give them soft access, where they get
                         * behind our paywall, but are unable to make purchases, changes, etc without
                         * actually entering their password.
                         */
                        defined('DONOTCACHEPAGE') or define('DONOTCACHEPAGE', true);
                        return;
                    }
                }
            }

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

        $storage = $this->page->getItem(cache_key);

        if ($storage->isHit()) {
            echo $storage->get();
            printf('<!-- Cache: %s -->', cache_key);
            exit(1);
        } else {
            echo '<!-- not cached. -->';
        }

        if (!$this->tags) {
            $this->tags = ['/' . trim(explode('?', $_SERVER['REQUEST_URI'], 2)[0], '/')];
        }

        //ob_start(); // Start the output buffer
        ob_start();

        // Register shutdown function.
        register_shutdown_function(function () {
            if (defined('DOING_CRON') && DOING_CRON) {
                return;
            }
            if (is_admin()) {
                return;
            }

            // This is being called on the front page.  It should not be.
            if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
                printf('<!-- %s -->', __('Cache Disabled By Constant', __NAMESPACE__));
            }

            if (!defined('cache_key')) {
                printf('<!-- %s -->', __('Cache Disabled Due To Missing Cache Key', __NAMESPACE__));
                return;
            }

            // Quick cleanup.
            $this->tags = array_unique($this->tags);

            $CachedString = $this->page->getItem(cache_key);

            printf('<!-- Cache created %s -->', date('Y-m-d H:i:s'));

            // If you want to compress html, this is the place.
            $s = trim(ob_get_contents());

            $config = $this->page->getConfig();
            if (
                array_key_exists('minify_html', $config)
                &&
                $config['minify_html']
            ) {
//                    require_once(dirname(__FILE__).'/assets/php/Minify/HTML.php');
                $a = new Minify_HTML();
                $s = $a->minify($s);
            }
//            exit;

            $CachedString->set($s);

            if ($this->tags) {
                $CachedString->setTags($this->tags);
            }
            /**
             * Cache expiration
             * Currently disabled.
             * Page cache clears on edit, add, update, delete.
             */
            $CachedString->expiresAfter(-1);

            $this->page->save($CachedString);

            ob_end_flush();
        });
    }

    /**
     * (B)rowse
     * Browse items in the object cache.
     **/
    public
    function browse()
    {
    }

    /**
     * (r)ead
     * Read an item from the object cache.
     **/
    public
    function read($key)
    {
        global $wpdb;

        if ($key == 'crumbls_settings') {
            echo __LINE__;
            exit;
        }

        // Determine which cache to use, quickly.
        // Not the best way, but it works for now.
        $context = strpos($key, 'transient') > -1 ? $this->transient : $this->object;

        if (!$context) {
            return false;
        }

        $ret = $context->getItem($key);
        if ($ret->isHit()) {
            return $ret->get();
        }
        return false;
    }

    /**
     * (e)dit
     * Edit an item from the object cache.
     **/
    public
    function edit($key, $value, $tags, $expires)
    {
        return $this->add($key, $value, $tags, $expires);
    }

    /**
     * (e)dit Decrease
     * Edit Decrease an item from the object cache.
     */
    public
    function editDecrease($key, $value = 1, $tags = false)
    {
        $context = strpos($key, 'transient') > -1 ? $this->transient : $this->object;
        if (!$context) {
            return;
        }

        if (is_string($tags)) {
            $tags = [$tags];
        }

        if (!is_int($value)) {
            $value = 1;
        }

        $CachedString = $context->getItem($key);
        if (!$CachedString->isHit()) {
            return false;
        }

        $CachedString->decrement($value);

        $context->save($CachedString);

        return $CachedString->get();
    }


    /**
     * (e)dit Increase
     * Edit Increase an item from the object cache.
     */
    public
    function editIncrease($key, $value = 1, $tags = false)
    {
        $context = strpos($key, 'transient') > -1 ? $this->transient : $this->object;
        if (!$context) {
            return;
        }
        if (is_string($tags)) {
            $tags = [$tags];
        }

        if (!is_int($value)) {
            $value = 1;
        }

        $CachedString = $context->getItem($key);
        if (!$CachedString->isHit()) {
            return false;
        }

        $CachedString->increment($value);

        $context->save($CachedString);

        return $CachedString->get();
    }

    /**
     * (a)dd
     * Add an item to the object cache.
     **/
    public
    function add($key, $value, $tags = null, $expires = -1)
    {
        // Auto route
        // Determine which cache to use, quickly.
        // Not the best way, but it works for now.
        $context = strpos($key, 'transient') > -1 ? $this->transient : $this->object;
        if (!$context) {
            return;
        }
        if (is_string($tags)) {
            $tags = [$tags];
        }
        $CachedString = $context->getItem($key);
        $CachedString->set($value);
        if ($tags) {
            $CachedString->setTags($tags);
        }
        if ($expires > 0) {
            $CachedString->expiresAfter($expires);
        }
        $context->save($CachedString);
    }

    /**
     * (d)elete
     * Delete from the object cache.
     **/
    public
    function delete($key = null, $tags = null)
    {
        if (!$key && !$tags) {
            // Nothing was passed.
            return;
        } else if (!$key && $tags) {
            // No key was defined.  Delete anything attached to these tags.
            foreach ($this->getTypes() as $k) {
                $this->$k->deleteItemsByTags($tags);
            }
            // Handle.
            return;
        }
        // Auto route
        // Determine which cache to use, quickly.
        // Not the best way, but it works for now.
        $context = strpos($key, 'transient') > -1 ? $this->transient : $this->object;
        if (!$context) {
            return;
        }
        // Easy way to clean up key or tags.
        if ($key) {
            $context->deleteItem($key);
        }
        if ($tags) {
            if (!is_array($tags)) {
                $tags = [$tags];
            }
            $context->deleteItemsByTags($tags);
        }
    }

    /**
     * Flush cache
     */
    public
    function flush()
    {
        if (!$this->page) {
            return;
        }
        return $this->page->flush();
    }

    /**
     * Output statistics
     */
    public
    function getStats()
    {
        if (!$this->page) {
            return;
        }
        return $this->page->getStats();
    }

    /**
     * Return cache instance.
     **/
    public
    function getInstance()
    {
        return $this->page;
    }

    public
    function postPublish($ID, $post)
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
    public
    function savePost($post_id, $post, $update = false)
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

        $this->delete(null, $this->tags);
    }


    /**
     * Handles the_post action.
     * The idea is that when the main query uses the_post on a single entry, we add all categories to the tags.
     * It lets us clear the cache easier.
     * @param $post
     */
    public
    function actionThePost($post)
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
     * Handles comments.
     * @param $comment_id
     * @param $status
     */
    public
    function actionCommentPost($comment_id, $status)
    {
        // Not yet implemented.
    }

    /**
     * Handles pre_get_posts action.
     * We use this just to set our expiration time for archives.
     */
    public
    function actionPreGetPosts($query)
    {
        if (!$query->is_main_query() || !$query->is_archive()) {
            return;
        }

        $this->expires = 86400; // In seconds.
    }


    // Add Toolbar Menus
    public
    function adminToolbar()
    {
        global $wp_admin_bar, $wp;

        if (!current_user_can('manage_options')) {
            return;
        }

        if (!$this->page && !$this->object && !$this->transient) {
            return;
        }

        $wp_admin_bar->add_menu([
            'id' => 'crumbls_cache',
            'title' => __('Cache', __NAMESPACE__),
            'href' => admin_url('options-general.php?page=cache')
        ]);

        if ($this->page) {
            // Category, archive, etc?
            $wp_admin_bar->add_menu([
                'id' => 'crumbls_cache_frontpage',
                'parent' => 'crumbls_cache',
                'title' => __('Clear Frontpage', __NAMESPACE__),
                'href' => admin_url('admin.php?page=cache&action=clearFrontpage&key=' . time())
            ]);
        }

        foreach ($this->getTypes() as $k) {
            if (!$k) {
                continue;
            }
            // Category, archive, etc?
            $wp_admin_bar->add_menu([
                'id' => 'crumbls_cache_' . $k,
                'parent' => 'crumbls_cache',
                'title' => __('Clear ' . $k, __NAMESPACE__),
                'href' => admin_url('admin.php?page=cache&action=clear' . ucwords($k) . '&key=' . time())
            ]);
        }

        // Category, archive, etc?
        $wp_admin_bar->add_menu([
            'id' => 'crumbls_cache_all',
            'parent' => 'crumbls_cache',
            'title' => __('Clear all', __NAMESPACE__),
            'href' => admin_url('admin.php?page=cache&action=clearAll&key=' . time())
        ]);

    }

    /**
     * Handle option update.
     * @param $key
     * @param $new
     * @param $old
     */
    public
    function optionUpdate($new, $old, $key)
    {
        if ($key != 'crumbls_settings') {
            return $new;
        }

        @unlink($this->config_path);

        // Update as needed.
        $new = array_map('array_filter', $new);

        update_option('crumbls_log', [], false);

        set_error_handler(function ($errNo, $errStr, $errFile, $errLine) {
            // SEND TO WARNING OPTION
            global $wpdb;
            $t = get_option('crumbls_log');
            if (!$t || !is_array($t)) {
                $t = [];
            }
            $e = new \stdClass();
            $e->no = $errNo;
            $e->str = __($errStr, __NAMESPACE__);
            $e->file = $errFile;
            $e->line = $errLine;
            $t[] = $e;
            update_option('crumbls_log', $t, false);
        });

        $cm = new CacheManager();

        // Other ways to clean up
        foreach ($new as $k => &$v) {
            if (!
                array_key_exists('type', $v)
                ||
                !$v['type']
                ||
                $v['type'] == 'disabled'
            ) {
                $v['type'] = false;
            }

            if ($v['type']
                &&
                in_array($v['type'],
                    $this->getTypes())
                &&
                $v['type'] !== $k
            ) {
                $v = [
                    'type' => $v['type'],
                    'enabled' => true
                ];
            } else if ($v['type']) {
                try {
                    $config = array_intersect_key($v, array_flip(preg_grep('#^' . $v['type'] . '#', array_keys($v))));

                    if (!$config) {
                        if ($k == 'page') {
                            $v = [
                                'type' => $v['type'],
                                'enabled' => array_key_exists('enabled', $v) ? $v['enabled'] : true,
                                'minify_html' => array_key_exists('minify_html', $v) && $v['minify_html'] == 'on' ? true : false,
                                'minify_css' => array_key_exists('minify_css', $v) && $v['minify_css'] == 'on' ? true : false,
                                'minify_js' => array_key_exists('minify_js', $v) && $v['minify_js'] = 'on' ? true : false
                            ];
                        } else {
                            $v = [
                                'type' => $v['type'],
                                'enabled' => array_key_exists('enabled', $v) ? $v['enabled'] : true
                            ];
                        }
                    } else {
                        $i = strlen($v['type']) + 1;
                        foreach ($config as $ka => $va) {
                            $config[substr($ka, $i)] = $va;
                            unset($config[$ka]);
                        }

                        $temp = $cm->getInstance($v['type'], $config);

                        foreach ($config as $ka => $va) {
                            if (!$temp->isValidOption($ka, $va)) {
                                $v['enabled'] = false;
                                trigger_error('invalid_setting_for_' . $k . '_' . $ka, E_USER_ERROR);
                            }
                        }
                        $v['enabled'] = array_key_exists('enabled', $v) ? (bool)$v['enabled'] : true;
                    }
                } catch (\Exception $e) {
                    // Invalid setup. Do not enable.
                    $v['enabled'] = false;
                }
            }
        }

        restore_error_handler();
        $this->generateConfig($new);
        return $new;
    }

    /**
     * Generate static configuration file.
     * @param null $in
     */
    protected
    function generateConfig($in = null)
    {
        if (!function_exists('get_option')) {
            return;
        }

        if (!$in) {
            $in = get_option('crumbls_settings');
        }

        /**
         * We already cleaned this data on a save,
         * but need to do it again, for now.
         * That's in case a config file doesn't exist
         * and we auto generate.
         */
        $cm = new CacheManager();

        foreach ($in as $k => &$v) {
            if (!
                array_key_exists('type', $v)
                ||
                !$v['type']
                ||
                $v['type'] == 'disabled'
            ) {
                $v['type'] = false;
            }

            // Doing it all wrong.
            // Remove invalid.
            if ($v['type']) {
                try {
                    $config = array_intersect_key($v, array_flip(preg_grep('#^' . $v['type'] . '#', array_keys($v))));
                    if (!$config) {
                        if ($k == 'page') {
                            $v = [
                                'type' => $v['type'],
                                'enabled' => array_key_exists('enabled', $v) ? (bool)$v['enabled'] : true,
                                'minify_html' => array_key_exists('minify_html', $v) ? (bool)$v['minify_html'] : false,
                                'minify_css' => array_key_exists('minify_css', $v) ? (bool)$v['minify_css'] : false,
                                'minify_js' => array_key_exists('minify_js', $v) ? (bool)$v['minify_js'] : false
                            ];
                        } else {
                            $v = [
                                'type' => $v['type'],
                                'enabled' => array_key_exists('enabled', $v) ? $v['enabled'] : true
                            ];
                        }
                    } else {
                        $i = strlen($v['type']) + 1;
                        foreach ($config as $ka => $va) {
                            $config[substr($ka, $i)] = $va;
                            unset($config[$ka]);
                        }
                        $temp = $cm->getInstance($v['type'], $config);
                        foreach ($config as $ka => $va) {
                            if (!$temp->isValidOption($ka, $va)) {
                                $v['enabled'] = false;
                            }
                        }
                        $config = $temp->getConfig();
                        $config['type'] = $v['type'];
                        if (array_key_exists('enabled', $v)) {
                            $config['enabled'] = $v['enabled'];
                        }
                        $v = $config;
                        $v['enabled'] = array_key_exists('enabled', $v) ? (bool)$v['enabled'] : true;
                    }
                } catch (\Exception $e) {
                    // Invalid setup. Do not enable.
                    $v['enabled'] = false;
                }
            }
        }

        try {
            file_put_contents(dirname(__FILE__) . '/config.php', '<?php return ' . var_export($in, true) . ';');
            if (!array_key_exists('usage_statistics', $in) || $in['usage_statistics']) {
                $this->_usageStatistics();
            }
        } catch (\Exception $e) {
            new \WP_Error('crumbls_cache', $e->toString());
        }

    }

    /**
     * Get cache types
     * @return array
     */
    protected
    function getTypes()
    {
        return ['page', 'object', 'transient'];
    }

    /**
     * Send anonymous usage statistics.
     */
    private
    function _usageStatistics()
    {
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $data = array(
            'v' => 1,
            'tid' => 'UA-97272517-1',
            'aip' => 1,
            'ds' => 'crumbls_cache',
            'cn' => 'crumbls_cache',
            'cid' => $uuid,
            't' => 'event',
        );


        $data['ec'] = 'Crumbls%20Cache';
        $data['ea'] = 'product';
        $data['el'] = 'configuration';
        $data['ev'] = '1';

        $i = 1;
        foreach ($this->getTypes() as $k) {
            if ($k) {
                if ($s = get_class($this->$k)) {
                    $data['cg' . $i] = $s;
                    $i++;
                }
            }
        }

        $url = 'https://www.google-analytics.com/collect';
        $content = http_build_query($data);
        $content = utf8_encode($content);
        $user_agent = 'CrumblsCache/1.0 (http://example.com/)';


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Activate
     * Move any old advanced-cache.php and install ours.
     */
    public function activate()
    {
        try {

            // advanced-cache.php
            $path = WP_CONTENT_DIR . '/advanced-cache.php';
            if (file_exists($path)) {
                $temp = glob(WP_CONTENT_DIR . '/advanced-cach*.php');
                $index = 0;
                do {
                    $test = WP_CONTENT_DIR . '/advanced-cache-' . $index . '.php';
                    $index++;
                } while (in_array($test, $temp));
                @rename(WP_CONTENT_DIR . '/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache-' . $index . '.php');
            }
            @copy(dirname(__FILE__) . '/assets/php/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache.php');
            // object-cache.php
            $path = WP_CONTENT_DIR . '/object-cache.php';
            if (file_exists($path)) {
                $temp = glob(WP_CONTENT_DIR . '/object-cach*.php');
                $index = 0;
                do {
                    $test = WP_CONTENT_DIR . '/object-cache-' . $index . '.php';
                    $index++;
                } while (in_array($test, $temp));
                @rename(WP_CONTENT_DIR . '/object-cache.php', WP_CONTENT_DIR . '/object-cache-' . $index . '.php');
            }
            @copy(dirname(__FILE__) . '/assets/php/object-cache.php', WP_CONTENT_DIR . '/object-cache.php');
        } catch (\Exception $e) {
            file_put_contents(dirname(__FILE__) . '/log.txt', var_export($e, true));
        }
    }

    /**
     * Deactivate
     */
    public function deactivate()
    {
        file_put_contents(dirname(__FILE__) . '/a.txt', __FUNCTION__);
        @unlink(WP_CONTENT_DIR . '/advanced-cache.php');
        @unlink(WP_CONTENT_DIR . '/object-cache.php');
    }
}

require_once(dirname(__FILE__) . '/assets/php/autoload.php');

if (!$cache) {
    if (is_admin()) {
        // No admin side yet.
        require_once(dirname(__FILE__) . '/admin.php');
        $cache = new Admin();
    } else {
        $cache = new Plugin();
    }

    if (function_exists('wp_normalize_path')) {
        register_activation_hook(__FILE__, [$cache, 'activate']);
        register_deactivation_hook(__FILE__, [$cache, 'deactivate']);
    }
}

