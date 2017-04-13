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
                    $d->name = __($cm->standardizeDriverName($driver), __NAMESPACE__);
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

        ?>
        <form action='options.php' method='post'>
            <div class="wrap">
                <h1><?php _e('Crumbls Cache', __NAMESPACE__); ?></h1>
                <p>Admin rewrite from scratch.</p>
                <?php
                //            echo '<pre>'.var_export($this->object,true).'</pre>';
                $tabs = ['page', 'object', 'transient'];
                $supported = $this->getSupported();
                $options = get_option('crumbls_settings');
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
                        echo '</table>';
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
}