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

        add_action('admin_init', [$this, 'actionAdminInit']);
        add_action('admin_menu', [&$this, 'actionAdminMenu'], PHP_INT_MAX - 1);

        add_action('admin_enqueue_scripts', [$this, 'actionAdminEnqueue']);
    }

    /**
     * Administrative initializer
     */
    public function actionAdminInit()
    {

        // If wea re

        register_setting('crumblsCache', 'crumbls_settings');

        // All of these settings.
        add_settings_section(
            'crumbls_crumblsCache_general',
            __('General Settings', __NAMESPACE__),
            [$this, 'renderSection'],
            'crumblsCache'
        );

        add_settings_field(
            'crumbls_cache_type_page',
            __('Page Cache Type', __NAMESPACE__),
            [$this, 'renderFieldCacheType'],
            'crumblsCache',
            'crumbls_crumblsCache_general',
            [
                'field' => 'crumbls_cache_type_page',
                'show_tab' => true
            ]
        );

        add_settings_field(
            'crumbls_cache_type_object',
            __('Object Cache Type', __NAMESPACE__),
            [$this, 'renderFieldCacheType'],
            'crumblsCache',
            'crumbls_crumblsCache_general',
            [
                'field' => 'crumbls_cache_type_object',
                'show_tab' => true
            ]
        );

        add_settings_field(
            'crumbls_cache_type_transients',
            __('Transient Cache Type', __NAMESPACE__),
            [$this, 'renderFieldCacheType'],
            'crumblsCache',
            'crumbls_crumblsCache_general',
            [
                'field' => 'crumbls_cache_type_transients',
                'show_tab' => true
            ]
        );


        // Ugly, but it works for now.
        $possible = $this->getSupported();
        unset($possible['']);
        foreach ($possible as $k => $v) {
            add_settings_section(
                'crumbls_crumblsCache_' . $k,
                $v,
                [$this, 'renderSection'],
                'crumblsCache'
            );
//            echo 'crumbls_crumblsCache_'.$k."<br />\r\n";
        }

        /*
        crumbls_crumblsCache_apc
        crumbls_crumblsCache_apcu
        crumbls_crumblsCache_couchbase
        crumbls_crumblsCache_devfalse
        crumbls_crumblsCache_devnull
        crumbls_crumblsCache_devtrue
        crumbls_crumblsCache_files
        crumbls_crumblsCache_leveldb
        crumbls_crumblsCache_memcache
        crumbls_crumblsCache_memcached
        crumbls_crumblsCache_mongodb
        crumbls_crumblsCache_predix
        crumbls_crumblsCache_redis
        crumbls_crumblsCache_sqlite
        crumbls_crumblsCache_ssdb
        crumbls_crumblsCache_wincache
        crumbls_crumblsCache_xcache
        crumbls_crumblsCache_zenddisk
        crumbls_crumblsCache_zendshm
        */

        // File settings
        add_settings_field(
            'crumbls_crumblsCache_files',
            __('File Settings', __NAMESPACE__),
            [$this, 'renderFieldTextDump'],
            'crumblsCache',
            'crumbls_crumblsCache_files',
            [
                'field' => 'crumbls_crumblsCache_files',
                'default' => [
                    'path' => WP_CONTENT_DIR . '/cache/crumbls/'
                ]
            ]
        );

        // Memcache
        add_settings_field(
            'crumbls_crumblsCache_memcache',
            __('Settings field description', __NAMESPACE__),
            [$this, 'renderFieldTextDump'],
            'crumblsCache',
            'crumbls_crumblsCache_memcache',
            [
                'field' => 'crumbls_crumblsCache_memcache',
                'default' => [
                    'host' => '',
                    'port' => ''
                ]
            ]
        );

        // Memcached
        // Memcache
        add_settings_field(
            'crumbls_crumblsCache_memcached',
            __('Settings field description', __NAMESPACE__),
            [$this, 'renderFieldTextDump'],
            'crumblsCache',
            'crumbls_crumblsCache_memcached',
            [
                'field' => 'crumbls_crumblsCache_memcached',
                'default' => [
                    'user' => '',
                    'host' => '',
                    'port' => ''
                ]
            ]
        );

// crumbls_crumblsCache_memcache

    }

    public function actionAdminEnqueue()
    {
        global $current_screen;
        wp_register_style('crumbls-admin', plugins_url('/assets/css/plugin.css', __FILE__));
        wp_register_script('crumbls-admin', plugins_url('/assets/js/plugin.js', __FILE__), ['jquery-ui-tabs']);

        if (!$current_screen
            ||
            !in_array($current_screen->base, [
                'settings_page_cache'
            ])
        ) {
            return;
        }

        wp_enqueue_script('crumbls-admin');
        wp_enqueue_style('crumbls-admin');
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

    protected function getSupported()
    {
        $possible = [
            false => __('Disabled', __NAMESPACE__),
            'apc' => __('APC', __NAMESPACE__),
            'apcu' => __('APCU', __NAMESPACE__),
            'couchbase' => __('Couchbase', __NAMESPACE__),
            'devfalse' => __('DevFalse', __NAMESPACE__),
            'devnull' => __('DevNull', __NAMESPACE__),
            'devtrue' => __('DevTrue', __NAMESPACE__),
            'files' => __('File (Recommended)', __NAMESPACE__),
            'leveldb' => __('Leveldb', __NAMESPACE__),
            'memcache' => __('Memcache', __NAMESPACE__),
            'memcached' => __('Memcached', __NAMESPACE__),
            'mongodb' => __('MongoDB', __NAMESPACE__),
            'predix' => __('Predis', __NAMESPACE__),
            'redis' => __('Redis', __NAMESPACE__),
            'sqlite' => __('Sqlite', __NAMESPACE__),
            'ssdb' => __('Ssdb', __NAMESPACE__),
            'wincache' => __('Wincache', __NAMESPACE__),
            'xcache' => __('Xcache', __NAMESPACE__),
            'zenddisk' => __('Zendisk', __NAMESPACE__),
            'zendshm' => __('Zendshm', __NAMESPACE__)
        ];
        return $possible;
    }

    /**
     * Admin Page - Cache
     */
    public function pageCache()
    {
        // Yeah, we do it wrong.
        global $wp_settings_fields, $wp_settings_sections;

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
            in_array($_REQUEST['action'], ['clearAll', 'clearFrontpage'])
        ) {
            // How to stop someone from just hitting refresh and retriggering?
            switch ($_REQUEST['action']) {
                case 'clearAll':
                    if ($this->instance) {
                        $this->instance->clear();
                    }
                    break;
                case 'clearFrontpage':
                    $this->delete('/', ['/']);
                    break;
                default:
            }
        }

        $path = false;

//        print_r($this->getStats());

//print_r();
/*
        $files = ($this->instance) ? $files = $this->getStats()->getInfo() : '';

        if (preg_match('#: (\d+)#', $files, $m)) {
            $files = $m[1];
        }
*/
        ?>
        <div class="wrap">
            <h1><?php _e('Cache', __NAMESPACE__); ?></h1>
            <p>Thanks for trying this plugin out.</p>
            <p>It's important to remember that it is in early alpha stages and is designed to provided a platform to
                grow on.</p>
            <p>It's only going to do it's job. If you want to merge, minify, etc, that's not this plugin's job.</p>
            <p>Please send any feedback to chase@crumbls.com or https://github.com/chasecmiller/Crumbls-Cache</p>


            <?php
            printf('<p>Cached entries: %d</p>', $files);

            $url = admin_url('admin.php?page=cache&action=clearAll&key=' . time());
            if ($files) {
                // Exists
                printf('<a href="%s" class="button button-primary">%s</a>', $url, __('Clear Cache', __NAMESPACE__));
            } else {
                printf('<a href="%s" class="button button-secondary disabled button-disabled" disabled>%s</a>', $url, __('Clear Cache', __NAMESPACE__));
            }

            echo '<br />';

            $url = admin_url('admin.php?page=cache&action=clearFrontpage&key=' . time());
/*
            if ($this->instance && $this->instance->hasItem('/')) {
                // Exists
                printf('<a href="%s" class="button button-primary">%s</a>', $url, __('Clear Front Page', __NAMESPACE__));
            } else {
                printf('<a href="%s" class="button button-secondary disabled button-disabled" disabled>%s</a>', $url, __('Clear Front Page', __NAMESPACE__));
            }
*/
            ?>
        </div>
        <?php

        $fields = array_filter($wp_settings_fields['crumblsCache']['crumbls_crumblsCache_general'],
            function ($e) {
                return array_key_exists('args', $e)
                && array_key_exists('show_tab', $e['args'])
                && $e['args']['show_tab'];
            });

        ?>
        <form action='options.php' method='post'>

            <h2>Crumbls Cache</h2>

            <div class="crumbls-tabs">
                <ul>
                    <?php
                    $i = 0;
                    foreach ($fields as $k => $v) {
                        printf('<li class="nav-tab%s"><a href="#%s">%s</a></li>',
                            $i == 0 ? ' nav-tab-active' : '',
                            $k,
                            $v['title']
                        );
                        $i++;
                    }
                    ?>
                </ul>
                <?php
                $possible = $this->getSupported();
                $options = get_option('crumbls_settings');

                //                print_r($options);

                foreach ($fields as $k => $v) {
                    printf('<div id="%s" class="ui-tabs-panel">', $k);

                    echo '<table class="form-table"><tbody>';
                    echo '<tr>';
                    printf('<th>%s</th>', $v['title']);
                    echo '<td>';
                    // Show all
                    printf('<select name="crumbls_settings[%s][type]" id="%s">',
                        $k,
                        $k
                    );

                    if ($k == 'crumbls_cache_type_object') {
                        if (
                            !array_key_exists($k, $options)
                            ||
                            !array_key_exists('type', $options[$k])
                        ) {
                            $options[$k] = [
                                'value' => 'crumbls_cache_type_page'
                            ];
                        }
                        printf('<option value="crumbls_cache_type_page" %s>%s</option>',
                            selected($options[$k]['type'], 'crumbls_cache_type_page', false),
                            __('Inherit from Page Cache', __NAMESPACE__)
                        );
                    } else if ($k == 'crumbls_cache_type_transients') {
                        if (
                            !array_key_exists($k, $options)
                            ||
                            !array_key_exists('type', $options[$k])
                        ) {
                            $options[$k] = [
                                'value' => 'crumbls_cache_type_object'
                            ];
                        }
                        printf('<option value="crumbls_cache_type_page" %s>%s</option>',
                            selected($options[$k]['type'], 'crumbls_cache_type_page', false),
                            __('Inherit from Page Cache', __NAMESPACE__)
                        );
                        printf('<option value="crumbls_cache_type_object" %s>%s</option>',
                            selected($options[$k]['type'], 'crumbls_cache_type_object', false),
                            __('Inherit from Object Cache', __NAMESPACE__)
                        );
                    }

                    foreach ($possible as $key => $val) {
                        printf('<option value="%s" %s>%s</option>',
                            $key,
                            selected($options[$k]['type'], $key, false),
                            $val
                        );
                    }

                    for ($i = 0; $i < 10; $i++) {
                    }
                    echo '</select>';

                    echo '</td>';
                    echo '</tr>';

                    call_user_func($v['callback'], $v['args']);

                    echo '</tbody></table>';
                    echo '</div>';
                }
                ?>
            </div>
            <?php
            settings_fields('crumblsCache');
            //            do_settings_sections('crumblsCache');
            submit_button();
            ?>

        </form>
        <?php
    }

    /**
     * Setting section handler.
     * Eventually, I'd like to make these an accordion
     * when it looks right.
     * @param null $a
     */
    public function renderSection($a = null)
    {
        $options = get_option('crumbls_settings');
        echo '<hr>';
        $method = preg_replace('#^crumbls#', '', $a['id']);

        if (method_exists($this, $method)) {
            call_user_func([$this, $method], $a);
        } else {
            printf('<p>%s</p>', __('Not yet supported', __NAMESPACE__));
        }
    }

    /**
     *
     * @param null $a
     */
    public function renderFieldCacheType($a = null)
    {
        if (!$a || !array_key_exists('field', $a)) {
            return;
        }

        $options = get_option('crumbls_settings');

        if (!array_key_exists($a['field'], $options)) {
            $options[$a['field']] = [];
        }

        $ref = $options[$a['field']];

        if (
            !array_key_exists('path', $ref)
        ||
            !$ref['path']
        ) {
            $ref['path'] = WP_CONTENT_DIR . '/cache/crumbls/';
        }

        foreach ([
                     'compress_data' => 'memcache',
                     'ip' => 'memcache memcached',
                     'path' => 'files',
                     'sasl_user' => 'memcache memcached',
                     'sasl_password' => 'memcache memcached',
                     'host' => 'memcache memcached mongodb',
                     'port' => 'memcache memcached mongodb',
                     'username' => 'mongodb',
                     'password' => 'mongodb',
                     'timeout' => 'mongodb',

                 ] as $field => $class) {
            printf('<tr class="field hidden %s"><th>%s</th><td><input type="text" name="%s" value="%s" /></td></tr>',
                $class,
                __($field, __NAMESPACE__),
                $field,
                array_key_exists($field, $ref) ? esc_attr($ref[$field]) : ''
            );
        }
        return;
    }

    public function renderFieldTextDump($a = null)
    {
        $options = get_option('crumbls_settings');
        if (!array_key_exists($a['field'], $options)) {
            $options[$a['field']] = [];
        }
        if (!array_key_exists('default', $a)) {
            $a['default'] = [];
        }
        $options[$a['field']] = array_merge(
            $a['default'],
            array_filter($options[$a['field']])
        );

        ?>
        <div id="list-A">
            <ul class="sortable">
                <li>item 1</li>
                <li>item 2</li>
                <li>item 3</li>
            </ul>
        </div>
        <br/>
        <div id="list-B">
            <ul class="sortable">
                <li>item 4</li>
                <li>item 5</li>
                <li>item 6</li>
            </ul>
        </div>
        <?php

        echo '<table class="form-table"><tbody>';

        foreach ($options[$a['field']] as $k => $v) {
            printf('<tr><th scope="row">%s</th><td><input name="crumbls_settings[%s][%s]" value="%s" placeholder="%s" /></td></tr>',
                __($k, __NAMESPACE__),
                $a['field'],
                $k,
                esc_attr($v),
                esc_attr(__($k, __NAMESPACE__))
            );
        }

        echo '</tbody></table>';
    }


    /**
     * General cache settings header.
     * @param $a
     */
    protected function _crumblsCache_general($a)
    {
        printf('<p>%s</p>', __('General cache settings', __NAMESPACE__));
    }

    /**
     * Cache files settings header.
     * @param $a
     */
    protected function _crumblsCache_files($a)
    {
        printf('<p>%s</p>', __('A file driver that use serialization for storing data for regular performances. A path must be specified, else the system temporary directory will be used.', __NAMESPACE__));
        printf('<p>%s</p>', __('The path is not yet verified.  You need to verify it right now for security reasons.', __NAMESPACE__));
    }

    /**
     * Cache memcache settings header.
     * @param $a
     */
    protected function _crumblsCache_memcache($a)
    {
        printf('<p>%s</p>', __('Please provide feedback on this section.', __NAMESPACE__));
    }

    /**
     * Cache memcache settings header.
     * @param $a
     */
    protected function _crumblsCache_memcached($a)
    {
        printf('<p>%s</p>', __('Please provide feedback on this section.', __NAMESPACE__));
    }


}