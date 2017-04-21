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

        add_action('admin_notices', [$this, 'adminNotices']);
    }

    /**
     * Administrative initializer
     */
    public function actionAdminInit()
    {
        register_setting('crumblsCache', 'crumbls_settings');

        // All of these settings.
        add_settings_section(
            'crumbls_crumblsCache_general',
            __('General Settings', __NAMESPACE__),
            [$this, 'renderSection'],
            'crumblsCache'
        );

        add_settings_field(
            'crumbls_settings',
            __('Cache Settings', __NAMESPACE__),
            [$this, 'renderFieldSettings'],
            'crumblsCache',
            'crumbls_crumblsCache_general'
        );

        // Check for advanced-cache.php and object-cache.php
        // Not yet implemented.
//        $this->_checkInstall();
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

    /**
     * Get all supported drivers.
     *
     * @author Chase C. Miller <chase@crumbls.com>
     * @return array
     */
    protected function getSupported($useCached = true)
    {
        if ($useCached && $temp = $this->read(__METHOD__)) {
            if (is_array($temp)) {
                return $temp;
            }
        }

        // Rewrite to actually check.
        $cm = new CacheManager();
        $ret = [];
        foreach ($cm->getStaticSystemDrivers() as $driver) {
            try {
                $temp = $cm->getInstance($driver, []);
                if (@$temp->driverCheck()) {
                    $d = new \stdClass();
                    if (method_exists($temp, 'getName')) {
                        $d->name = $temp->getName();
                    } else {
                        $d->name = $cm->standardizeDriverName($driver);
                    }
                    $d->name = __($d->name, __NAMESPACE__);

                    $d->fields = [];
                    $default = [];
                    if (method_exists($temp, 'getConfig')) {
                        $default = $temp->getConfig();
                    }

                    if (method_exists($temp, 'getValidOptions')) {
                        $req = [];
                        if (method_exists($temp, 'getRequiredOptions')) {
                            $req = $temp->getRequiredOptions();
                        }
                        foreach ($temp->getValidOptions() as $k) {
                            $d->fields[$k] = new \stdClass();
                            $d->fields[$k]->name = __($k, __NAMESPACE__);
                            $d->fields[$k]->callback = [&$this, 'textField'];
                            $d->fields[$k]->required = in_array($k, $req);
                            $d->fields[$k]->default = array_key_exists($k, $default) ? $default[$k] : '';
                        }
                    }
                    $ret[strtolower($driver)] = $d;
                }
            } catch (\phpFastCache\Exceptions\phpFastCacheDriverCheckException $e) {
                continue;
            } catch (\Exception $e) {
                continue;
            }
        }

        $this->add(__METHOD__, $ret, ['system', 'crumbls'], 1);
        return $ret;
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
            array_key_exists('action', $_REQUEST)
            &&
            strpos($_REQUEST['action'], 'clear') === 0
            &&
            method_exists($this, '_' . $_REQUEST['action'])
            &&
            array_key_exists('key', $_REQUEST)
            &&
            is_numeric($_REQUEST['key'])
            &&
            abs($_REQUEST['key'] - time()) < 120 // To not keep looping if you hit refresh.
        ) {
            $k = '_' . $_REQUEST['action'];
            call_user_func([$this, $k]);
        }

        ?>
        <form action='options.php' method='post'>
            <div class="wrap">
                <h1><?php _e('Crumbls Cache', __NAMESPACE__); ?></h1>
                <p>Admin rewrite from scratch.</p>
                <?php
                //            echo '<pre>'.var_export($this->object,true).'</pre>';
                $tabs = $this->getTypes();
                $supported = $this->getSupported();
                $options = get_option('crumbls_settings');
                if (in_array('page', $tabs) && $this->page) {
                    printf('<a href="%s" class="button">%s</a> ',
                        admin_url('admin.php?page=cache&action=clearFrontpage&key=' . time()),
                        __('Clear Frontpage', __NAMESPACE__)
                    );
                }
                foreach ($tabs as $k) {
                    if ($this->$k) {
                        printf('<a href="%s" class="button">%s</a> ',
                            admin_url('admin.php?page=cache&action=clear' . ucwords($k) . '&key=' . time()),
                            __('Clear ' . $k . ' Cache', __NAMESPACE__)
                        );
                    }
                }
                ?>
                <div class="crumbls-tabs">
                    <ul>
                        <?php
                        $i = 0;
                        foreach ($tabs as $k) {
                            printf('<li class="nav-tab%s"><a href="#%s">%s</a></li>',
                                $i == 0 ? ' nav-tab-active' : '',
                                $k,
                                __($k, __NAMESPACE__)
                            );
                            $i++;
                        }
                        ?>
                    </ul>
                    <?php

                    foreach ($tabs as $i => $pane) {
                        $ref = array_key_exists($pane, $options) ? $options[$pane] : [];

                        if (!array_key_exists('type', $ref)) {
                            $ref['type'] = 'disabled';
                        }
                        printf('<div id="%s" class="ui-tabs-panel">', $pane);
                        printf('<table><tr class="always-visible"><th>%s</th>', __('Cache Type', __NAMESPACE__));
                        printf('<td><select name="crumbls_settings[%s][type]" id="crumbls_settings_%s_type">',
                            $pane,
                            $pane
                        );
                        printf('<option value="disabled"%s>%s</option>',
                            $ref['type'] == 'disabled' ? ' selected' : '',
                            __('Disabled', __NAMESPACE__)
                        );
                        if ($i > 0) {
                            for ($x = 0; $x < $i; $x++) {
                                printf('<option value="%s"%s>%s</option>',
                                    $tabs[$x],
                                    $ref['type'] == $tabs[$x] ? ' selected' : '',
                                    __('Inherit from ' . $tabs[$x], __NAMESPACE__)
                                );
                            }
                        }
                        // Add inherit options.
//                        print_r($supported);
                        // End add inherit options.
                        foreach ($supported as $k => $v) {
                            printf('<option value="%s"%s>%s</option>',
                                esc_attr($k),
                                $ref['type'] == $k ? ' selected' : '',
                                $v->name
                            );
                        }
                        echo '</select></td></tr>';

                        foreach ($supported as $k => $v) {
                            foreach ($v->fields as $ka => $vb) {
                                printf('<tr class="%s"><th>%s</th><td>', $k, $vb->name);
                                $kv = '';
                                if (
                                    array_key_exists($ka, $ref)
                                    &&
                                    $ref[$ka]
                                ) {
                                    $kv = $ref[$ka];
                                } else if (array_key_exists($k . '_' . $ka, $ref)) {
                                    $kv = $ref[$k . '_' . $ka];
                                }
                                call_user_func($vb->callback, $ka, $kv, $pane, $k);
                                echo '</td>';
                            }
                        }

                        // Allow extension
                        if (method_exists($this, '_tab' . ucfirst($pane))) {
                            call_user_func([$this, '_tab' . ucfirst($pane)], $ref);
                        }

                        echo '</table>';

                        if ($this->$pane && false) {
                            $ref = $this->$pane;
                            if (method_exists($ref, 'getStats')) {
                                printf('<h2>%s</h2>',
                                    __('Statistics', __NAMESPACE__)
                                );
                                echo $ref->getStats()->getInfo();
                            }
                        }
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
     * Easy trick to output a txt field.
     * @param $key
     * @param string $value
     * @param $section
     * @param $group
     */
    public function textField($key, $value = '', $section, $group)
    {
        $key = ltrim($group . '_' . $key, '_');
        printf('<input type="text" name="%s" id="%s" class="" value="%s" />',
            'crumbls_settings[' . $section . '][' . $key . ']',
            'crumbls_settings_' . $section . '_' . $key,
            esc_attr($value)
        );
    }

    public function adminNotices()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $t = get_option('crumbls_log');
        if (!$t) {
            return;
        }
        print_r($t);
    }

    /**
     * Clear frontpage
     */
    private function _clearFrontpage()
    {
        if (!$this->page) {
            return;
        }

        return $this->page->deleteItemsByTag('/');
    }

    /**
     * Clear page cache
     */
    private function _clearPage()
    {
        if (!preg_match('#.*([A-Z].*?)$#', __METHOD__, $k)) {
            return;
        }
        $k = strtolower($k[1]);
        return $this->clearCache($k);
    }

    /**
     * Clear object cache
     */
    private function _clearObject()
    {
        if (!preg_match('#.*([A-Z].*?)$#', __METHOD__, $k)) {
            return;
        }
        $k = strtolower($k[1]);
        return $this->clearCache($k);
    }

    /**
     * Clear transient cache
     */
    private function _clearTransient()
    {
        if (!preg_match('#.*([A-Z].*?)$#', __METHOD__, $k)) {
            return;
        }
        $k = strtolower($k[1]);
        return $this->clearCache($k);
    }

    /**
     * Clear transient cache
     */
    private function _clearAll() {
        return $this->clearCache();
    }


    /**
     * Clear cache
     * @param bool $specific
     */
    private function clearCache($specific = false)
    {
        $p = $this->getTypes();


        if ($specific) {
            if (!in_array($specific, $p)) {
                return false;
            }
            if (!$this->$specific) {
                return false;
            }
            return $this->$specific->clear();
        }
        $i = 0;
        foreach ($p as $k) {
            if ($this->$k) {
                if ($this->$k->clear()) {
                    $i++;
                }
            }
        }
        return (bool)$i;

    }

    /**
     * Show extra page tab options.
     */
    public function _tabPage($options)
    {
        ?>
        <tr class="always-visible">
            <th>
                <?php _e('Minify HTML', __NAMESPACE__); ?>
            </th>
            <td><?php
                $val = array_key_exists('minify_html', $options) && $options['minify_html'] ? ' checked' : '';
                printf('<input type="checkbox" name="%s" id="%s"%s>',
                    'crumbls_settings[page][minify_html]',
                    'crumbls_settings_page_minify_html',
                    $val
                );
                ?></td>
        </tr>
        <tr class="always-visible hidden">
            <th>
                <?php _e('Minify CSS', __NAMESPACE__); ?>
            </th>
            <td><?php
                $val = array_key_exists('minify_css', $options) && $options['minify_css'] ? ' checked' : '';
                printf('<input type="checkbox" name="%s" id="%s"%s>',
                    'crumbls_settings[page][minify_css]',
                    'crumbls_settings_page_minify_css',
                    $val
                );
                ?></td>
        </tr>
        <tr class="always-visible hidden">
            <th>
                <?php _e('Minify Javascript', __NAMESPACE__); ?>
            </th>
            <td><?php
                $val = array_key_exists('minify_js', $options) && $options['minify_js'] ? ' checked' : '';
                printf('<input type="checkbox" name="%s" id="%s"%s>',
                    'crumbls_settings[page][minify_js]',
                    'crumbls_settings_page_minify_js',
                    $val
                );
                ?></td>
        </tr>
        <?php
    }

    // Check for advanced-cache.php and object-cache.php
    private function _checkInstall()
    {
        if (
        !array_key_exists('PHP_SELF', $_SERVER)
        ) {
            return;
        }
        $basename = basename($_SERVER['PHP_SELF']);

        if (!in_array($basename, [
            'plugins.php',
            'options-general.php'
        ])
        ) {
            return;
        }

        if (
            $basename == 'options-general.php'
            &&
            (
                !array_key_exists('page', $_REQUEST)
                ||
                $_REQUEST['page'] != 'cache'
            )
        ) {
            return false;
        }

        // Check it out.
        if (
            !file_exists(WP_CONTENT_DIR . '/advanced-cache.php')
            ||
            !file_exists(WP_CONTENT_DIR . '/object-cache.php')
        ) {
            // Error 1.
            echo 'issue!';
            exit;
        } else {
            $err = [
                'advanced-cache' => file_exists(WP_CONTENT_DIR . '/advanced-cache.php') ? md5_file(WP_CONTENT_DIR . '/advanced-cache.php') : false,
                'object-cache' => file_exists(WP_CONTENT_DIR . '/object-cache.php') ? md5_file(WP_CONTENT_DIR . '/object-cache.php') : false
            ];
            if ($err['advanced-cache'] == '940a1e658235ad5e749247fb59362528') {
                unset($err['advanced-cache']);
            }
            if ($err['object-cache'] == '39e72bbd6b9707a744932c33b49ff135') {
                unset($err['object-cache']);
            }
            if ($err) {
                $t = get_option('crumbls_log');
                if (!$t) {
                    $t = [];
                }
                // Cleanup similar
                $name = __FUNCTION__;
                if ($rem = array_filter($t, function ($e) use ($name) {
                    return strpos($e, $name);
                })
                ) {
                    $t = array_diff_key($t, $rem);
                }

                // Clean bad logs.
                foreach ($err as $k => $md5) {
                    if ($md5 === false) {
                        $t[] = '<div class="notice notice-error ' . __FUNCTION__ . '"><p>' . __(sprintf('Missing file: %s. Cache will not function correctly.', $k), __NAMESPACE__) . '</p></div>';
                    } else {
                        $t[] = '<div class="notice notice-error ' . __FUNCTION__ . '"><p>' . __(sprintf('Invalid checksum for %s. Cache will not function correctly.', $k), __NAMESPACE__) . '</p></div>';
                    }
                }

                // Update for notice.
                $t = array_unique(array_filter($t));
                update_option('crumbls_log', $t);
            }
        }
    }
}