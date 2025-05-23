<?php

/*
Plugin Name: WPU Options
Plugin URI: https://github.com/WordPressUtilities/wpuoptions
Update URI: https://github.com/WordPressUtilities/wpuoptions
Version: 8.1.1
Description: Friendly interface for website options
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpuoptions
Domain Path: /lang
Requires at least: 6.2
Requires PHP: 8.0
Network: Optional
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

defined('ABSPATH') or die(':(');

class WPUOptions {

    private $plugin_description;
    private $settings_update;
    private $fields;
    private $boxes;
    private $tabs;
    private $main_url;
    private $options = array(
        'plugin_name' => 'WPU Options',
        'plugin_version' => '8.1.1',
        'plugin_userlevel' => 'manage_categories',
        'plugin_menutype' => 'admin.php',
        'plugin_pageslug' => 'wpuoptions-settings'
    );
    private $fields_messages = '';

    private $default_box = array(
        'default' => array(
            'name' => ''
        )
    );

    private $current_tab = 'default';

    private $default_tab = array(
        'default' => array(
            'visibility_admin' => true,
            'visibility_network' => true,
            'name' => 'Site options'
        )
    );

    /**
     * Init plugin
     */
    public function __construct() {

        require_once __DIR__ . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
        $this->settings_update = new \wpuoptions\WPUBaseUpdate(
            'WordPressUtilities',
            'wpuoptions',
            $this->options['plugin_version']);

        if (!is_admin()) {
            return;
        }

        $this->hooks();
        $this->set_options();
        $this->admin_hooks();
    }

    public function load_plugin_textdomain() {
        $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
        if (strpos(__DIR__, 'mu-plugins') !== false) {
            load_muplugin_textdomain('wpuoptions', $lang_dir);
        } else {
            load_plugin_textdomain('wpuoptions', false, $lang_dir);
        }
        $this->plugin_description = __('Friendly interface for website options', 'wpuoptions');
        $this->options['plugin_publicname'] = __('Site options', 'wpuoptions');
    }

    /**
     * Set Options
     */
    private function set_options() {
        $this->main_url = $this->options['plugin_menutype'] . '?page=' . $this->options['plugin_pageslug'];
    }

    /**
     * Hooks
     */
    private function hooks() {
        add_action('after_setup_theme', array(&$this,
            'load_plugin_textdomain'
        ));
        add_action('init', array(&$this,
            'set_fields'
        ), 50);

        /* Multisite */
        add_filter('network_edit_site_nav_links', array(&$this,
            'network_edit_site_nav_links'
        ));
        add_action('network_admin_menu', array(&$this,
            'network_admin_menu'
        ));
        add_action('current_screen', array(&$this,
            'check_screen_access'
        ));

    }

    /**
     * Set fields values
     */
    public function set_fields() {
        $this->options = apply_filters('wpu_options_options', $this->options);
        $this->fields = apply_filters('wpu_options_fields', array());
        $this->boxes = apply_filters('wpu_options_boxes', $this->default_box);
        $this->tabs = apply_filters('wpu_options_tabs', $this->default_tab);
        $this->current_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $this->tabs) ? $_GET['tab'] : 'default';

        foreach ($this->tabs as $id => $tab) {
            if (!isset($this->tabs[$id]['name'])) {
                $this->tabs[$id]['name'] = $id;
            }
            if (!isset($this->tabs[$id]['visibility_network'])) {
                $this->tabs[$id]['visibility_network'] = false;
            }
            if (!isset($this->tabs[$id]['visibility_admin'])) {
                $this->tabs[$id]['visibility_admin'] = true;
            }
        }

        /* Default values */
        foreach ($this->boxes as $id => $box) {
            if (!isset($box['tab'])) {
                $this->boxes[$id]['tab'] = 'default';
            }
            $this->boxes[$id]['current_tab'] = $this->boxes[$id]['tab'] == $this->current_tab;
        }

        foreach ($this->fields as $id => $field) {

            /* Default value */
            $field = $this->get_field_datas($id, $field);
            if (!$this->is_item_visible($field)) {
                continue;
            }

            $this->fields[$id] = $field;

            /* Load box details */
            if (isset($this->boxes[$this->fields[$id]['box']])) {
                $box = $this->boxes[$this->fields[$id]['box']];
                $this->fields[$id]['tab'] = $box['tab'];
                $this->fields[$id]['current_tab'] = $box['current_tab'];
            }

            /* Default value */
            $default_value = '';

            if (!is_network_admin()) {
                $opt = get_option($id);
                if ($opt === false && !isset($field['noautoload'])) {
                    if (isset($field['default_value']) && $this->test_field_value($field, $field['default_value'])) {
                        $default_value = $field['default_value'];
                    }
                    update_option($id, $default_value, $field['autoload']);
                }
            }
        }
    }

    /**
     * Set admin hooks
     */
    private function admin_hooks() {

        add_filter('heartbeat_received', array(&$this,
            'add_heartbeat_data'
        ), 10, 2);

        add_action('wp_loaded', array(&$this,
            'admin_export_page_postAction'
        ));
        add_action('wp_loaded', array(&$this,
            'admin_update'
        ));
        add_action('admin_menu', array(&$this,
            'admin_menu'
        ));
        add_action('admin_bar_menu', array(&$this,
            'add_toolbar_menu_items'
        ), 100);
        add_filter('ajax_query_attachments_args', array(&$this,
            'ajax_query_attachments_args'
        ));
        add_filter("plugin_action_links_" . plugin_basename(__FILE__), array(&$this,
            'settings_link'
        ));
        if (isset($_GET['page']) && (strpos($_GET['page'], $this->options['plugin_pageslug'])) === 0) {
            add_filter('teeny_mce_buttons', array(&$this,
                'custom_editor_buttons'
            ), 10, 2);

            if (is_network_admin()) {
                switch_to_blog($this->get_current_site_id());
            }
            add_action('admin_enqueue_scripts', array(&$this,
                'add_assets_js'
            ));
            add_action('admin_print_styles', array(&$this,
                'add_assets_css'
            ));

            if (is_network_admin()) {
                restore_current_blog();
            }
        }
    }

    /**
     * Set admin menu
     */
    public function admin_menu() {
        add_menu_page($this->options['plugin_name'] . ' Settings', $this->options['plugin_publicname'], $this->options['plugin_userlevel'], $this->options['plugin_pageslug'], array(&$this,
            'admin_settings'
        ), '', 3);
        foreach ($this->tabs as $id => $tab) {
            if (!isset($tab['sidebar']) || !$tab['sidebar']) {
                continue;
            }
            $hook = add_submenu_page($this->options['plugin_pageslug'], $tab['name'], ' - ' . $tab['name'], $this->options['plugin_userlevel'], $this->options['plugin_pageslug'] . '-tab-' . $id, '__return_empty_string');
            add_action("load-{$hook}", array(&$this,
                'admin_settings_redirect_server'
            ));
        }
        add_submenu_page($this->options['plugin_pageslug'], __('Import', 'wpuoptions'), __('Import', 'wpuoptions'), apply_filters('wpu_options_level__admin_import_page', $this->options['plugin_userlevel']), $this->options['plugin_pageslug'] . '-import', array(&$this,
            'admin_import_page'
        ));
        add_submenu_page($this->options['plugin_pageslug'], __('Export', 'wpuoptions'), __('Export', 'wpuoptions'), apply_filters('wpu_options_level__admin_export_page', $this->options['plugin_userlevel']), $this->options['plugin_pageslug'] . '-export', array(&$this,
            'admin_export_page'
        ));
    }

    public function admin_settings_redirect_server() {
        $idtab = str_replace($this->options['plugin_pageslug'] . '-tab-', '', esc_html($_GET['page']));
        wp_redirect(admin_url($this->main_url . '&tab=' . $idtab));
        die;
    }

    /**
     * Settings link
     */
    public function settings_link($links) {
        $settings_link = '<a href="' . admin_url($this->main_url) . '">' . __('Options', 'wpuoptions') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Add menu items to toolbar
     *
     * @param unknown $admin_bar
     */
    public function add_toolbar_menu_items($admin_bar) {
        if (!current_user_can($this->options['plugin_userlevel'])) {
            return;
        }
        $admin_bar->add_menu(array(
            'id' => 'wpu-options-menubar-link',
            'title' => $this->options['plugin_publicname'],
            'href' => admin_url($this->main_url),
            'meta' => array(
                'title' => $this->options['plugin_publicname']
            )
        ));
    }

    /**
     * Enqueue JS
     */
    public function add_assets_js() {
        $has_media = false;
        $has_multiple = false;
        $has_wplink = false;

        foreach ($this->fields as $field) {
            /* Only on current tab */
            if (!isset($field['current_tab']) || !$field['current_tab']) {
                continue;
            }
            if (isset($field['multiple']) && $field['multiple']) {
                $has_multiple = true;
            }
            if (isset($field['type']) && ($field['type'] == 'media' || $field['type'] == 'file')) {
                $has_media = true;
            }
            if (isset($field['type']) && $field['type'] == 'wp_link') {
                $has_wplink = true;
            }
        }
        if ($has_media) {
            wp_enqueue_media();
        }
        wp_enqueue_script('wpuoptions_scripts', plugins_url('assets/events.js', __FILE__), array(
            'jquery-ui-core',
            'jquery-ui-widget',
            'jquery-ui-mouse',
            'jquery-ui-slider',
            'jquery-ui-datepicker',
            'iris'
        ), $this->options['plugin_version']);

        wp_add_inline_script('wpuoptions_scripts', 'var wpuoptions__settings=' . json_encode(array(
            'last_updated' => get_option('wpuoptions__last_updated'),
            'last_updated__text' => __('Warning : The saved options may be different than yours. Maybe someone changed them while you were editing ?', 'wpuoptions')
        )), 'before');

        if ($has_multiple) {
            wp_register_style('select2css', plugins_url('assets/select2/css/select2.min.css', __FILE__), false, $this->options['plugin_version'], 'all');
            wp_register_script('select2', plugins_url('assets/select2/js/select2.min.js', __FILE__), array('jquery'), $this->options['plugin_version'], true);
            wp_enqueue_style('select2css');
            wp_enqueue_script('select2');
        }
        if ($has_wplink) {
            wp_enqueue_script('wplink');
            wp_enqueue_style('editor-buttons');
            require_once ABSPATH . "wp-includes/class-wp-editor.php";
            _WP_Editors::wp_link_dialog();
        }
    }

    /**
     * Enqueue CSS
     */
    public function add_assets_css() {
        wp_register_style('wpuoptions_style', plugins_url('assets/style.css', __FILE__), array(), $this->options['plugin_version']);
        wp_enqueue_style('wpuoptions_style');
    }

    /**
     * Set admin page
     */
    public function admin_settings() {
        $content = '<div class="wrap">';
        $content .= '<h2>' . $this->options['plugin_publicname'] . '</h2>';
        if (!empty($this->fields)) {
            $content .= $this->fields_messages;
            $content .= $this->admin_form();
        } else {
            $content .= '<p>' . __('No fields for the moment', 'wpuoptions') . '</p>';
        }
        $content .= '</div>';

        echo $content;
    }

    /**
     * Store version in heartbeat
     */
    public function add_heartbeat_data($response, $data) {

        if (isset($data['wpuoptions__last_updated'])) {
            $wpuoptions__last_updated = get_option('wpuoptions__last_updated');
            if ($wpuoptions__last_updated) {
                $response['wpuoptions__last_updated'] = $wpuoptions__last_updated;
            }
        }

        return $response;
    }

    /**
     * Admin submenu export
     */

    public function admin_export_page_postAction() {
        if (!isset($_POST['wpu_export_options'])) {
            return;
        }

        if (!isset($_POST['wpu_export_options_field']) && wp_verify_nonce($_POST['wpu_export_options_field'], 'wpu_export_options')) {
            return;
        }

        $boxes = array();

        /* Add boxes */
        if (isset($_POST['boxes'])) {
            foreach ($_POST['boxes'] as $box) {
                if (array_key_exists($box, $this->boxes)) {
                    $boxes[] = $box;
                }
            }
        }

        /* Set JSON filename */
        $site_url = str_replace(array(
            'http://',
            'https://'
        ), '', site_url());
        $sanitized_site_url = sanitize_title_with_dashes($site_url);
        $filename = 'export-' . date_i18n('Y-m-d-his') . '-' . $sanitized_site_url . '.json';

        /* Return JSON file */

        echo $this->generate_export($boxes);

    }

    public function admin_export_page() {
        echo '<div class="wrap">';
        echo '<h2>' . __('Export', 'wpuoptions') . '</h2>';
        echo '<form action="" method="post">';
        wp_nonce_field('wpu_export_options', 'wpu_export_options_field');
        echo '<p>' . __("Click below to download a .json file containing all your website's options.", 'wpuoptions') . '</p>';
        echo '<h3>Boxes</h3>';
        $tabs = $this->tabs;
        ksort($tabs);
        $boxes_with_fields = array();
        foreach ($this->fields as $id => $field) {
            if (isset($field['box'])) {
                $boxes_with_fields[$field['box']] = $field['box'];
            }
        }

        foreach ($tabs as $tab_id => $tab) {
            echo '<div class="wpu-export-section">';
            echo '<h4 class="wpu-export-title"><label>' . $tab['name'] . ' <input type="checkbox" checked="checked" class="wpu-export-title-checkbox" /> </label></h4>';
            foreach ($this->boxes as $box_id => $box) {
                if (!isset($box['tab'])) {
                    $box['tab'] = 'default';
                }
                if ($box['tab'] == $tab_id && isset($boxes_with_fields[$box_id])) {
                    echo '<p><label><input class="wpu-export-boxes-check" type="checkbox" checked="checked" name="boxes[' . $box_id . ']" value="' . $box_id . '" /> ' . (empty($box['name']) ? __('Default box', 'wpuoptions') : $box['name']) . '</label></p>';
                }
            }
            echo '</div>';
        }
        submit_button(__('Export options', 'wpuoptions'), 'primary', 'wpu_export_options');
        echo '</form>';
        echo '</div>';
    }

    /**
     * Admin submenu import
     */
    public function admin_import_page() {
        echo '<div class="wrap">';
        echo '<h2>' . __('Import', 'wpuoptions') . '</h2>';

        if (isset($_FILES["wpu_import_options"])) {
            $import_options = $_FILES["wpu_import_options"]['tmp_name'];

            if (file_exists($import_options) && isset($_POST['wpu_import_options_field']) && wp_verify_nonce($_POST['wpu_import_options_field'], 'wpu_import_options')) {
                $import_tmp = file_get_contents($import_options);
                $import = $this->import_options($import_tmp);
                if ($import) {
                    echo '<div class="updated"><p>' . __('The file has been successfully imported.', 'wpuoptions') . '</p></div>';
                } else {
                    echo '<div class="error"><p>' . __('The file has not been imported.', 'wpuoptions') . '</p></div>';
                }
            }
        }
        echo '<p>' . __("Upload a .json file (generated by WPU Options) to import your website's options.", 'wpuoptions') . '</p>';
        echo '<form action="" method="post" enctype="multipart/form-data">';
        wp_nonce_field('wpu_import_options', 'wpu_import_options_field');
        echo '<div><input required type="file" name="wpu_import_options" />';
        submit_button(__('Import options file', 'wpuoptions'));
        echo '</form>';
        echo '</div>';
    }

    /**
     * Save new values
     *
     * @return unknown
     */
    public function admin_update() {
        $content = '';
        if (!is_admin() || !isset($_GET['page']) || $_GET['page'] != $this->options['plugin_pageslug']) {
            return;
        }
        if (!current_user_can($this->options['plugin_userlevel'])) {
            return;
        }
        if (!isset($_POST['wpuoptions_submit'])) {
            return;
        }

        if (is_network_admin()) {
            switch_to_blog($this->get_current_site_id());
        }

        if (!wp_verify_nonce($_POST['wpuoptions-noncefield'], 'wpuoptions-nonceaction')) {
            $content .= '<p>' . __("Error in the form.", 'wpuoptions') . '</p>';
        } else {
            $languages = $this->get_languages();
            $updated_options = array();
            $errors = array();
            $testfields = array();
            foreach ($this->fields as $id => $field) {
                $field = $this->get_field_datas($id, $field);
                if (!$this->is_item_visible($field)) {
                    continue;
                }
                $testfields[$id] = $field;
                if (isset($field['lang']) && !empty($languages)) {
                    foreach ($languages as $lang => $name) {
                        $newfield = $field;
                        $newfield['label'] = '[' . $lang . '] ' . $newfield['label'];
                        $testfields[$lang . '___' . $id] = $newfield;
                    }
                }
            }

            foreach ($testfields as $id => $field) {
                $idf = $this->get_field_id($id);
                $is_checkbox = $field['type'] == 'checkbox';
                $is_multiple = isset($field['multiple']) && $field['multiple'];
                if (isset($_POST[$idf]) || $is_checkbox) {
                    $old_option = get_option($id);
                    if ($is_checkbox) {
                        /* Check if control field exists before checking value */
                        $new_option = $old_option;
                        if (isset($_POST[$idf . '__check'])) {
                            $new_option = (isset($_POST[$idf]) ? '1' : '0');
                        }
                    } else {
                        if ($is_multiple && wpuoptions_is_array_of_numbers($_POST[$idf])) {
                            $new_option = $_POST[$idf];
                        } else {
                            $new_option = trim(stripslashes($_POST[$idf]));
                        }
                    }

                    $test_field = $this->test_field_value($field, $new_option);

                    $field_label = $field['label'];
                    if (isset($field['box']) && isset($this->boxes[$field['box']]['name'])) {
                        $field_label = '<em>' . $this->boxes[$field['box']]['name'] . '</em> - ' . $field['label'];
                    }

                    // Field is required and have been emptied
                    if ($new_option == '' && isset($field['required'])) {
                        $errors[] = sprintf(__('The field "%s" must not be empty', 'wpuoptions'), $field_label);
                    }

                    // If test is ok OR the field is not required
                    elseif ($test_field || ($new_option == '' && !isset($field['required']))) {
                        if ($old_option != $new_option) {
                            update_option($id, $new_option, $field['autoload']);
                            $updated_options[] = sprintf(__('The field "%s" has been updated.', 'wpuoptions'), $field_label);
                        }
                    } else {
                        $errors[] = sprintf(__('The field "%s" has not been updated, because it\'s not valid.', 'wpuoptions'), $field_label);
                    }
                }
            }
            update_option('wpuoptions__last_updated', time());
            do_action('wpuoptions__post_update', $testfields);
            wp_cache_delete('alloptions', 'options');
            if (!empty($updated_options)) {
                $content .= '<div class="updated"><p><strong>' . __('Success!', 'wpuoptions') . '</strong><br />' . implode('<br />', $updated_options) . '</p></div>';
            }
            if (!empty($errors)) {
                $content .= '<div class="error"><p><strong>' . __('Fail!', 'wpuoptions') . '</strong><br />' . implode('<br />', $errors) . '</p></div>';
            }
        }

        if (is_network_admin()) {
            restore_current_blog();
        }
        $this->fields_messages = $content;
    }

    /**
     * Returns admin form
     *
     * @return unknown
     */
    private function admin_form() {

        $content = '';
        $has_lang = false;

        if (count($this->tabs) > 1) {
            $content .= '<div id="icon-themes" class="icon32"><br></div>';
            $content .= '<h2 class="nav-tab-wrapper">';
            foreach ($this->tabs as $idtab => $tab) {
                if (!$this->is_item_visible($tab)) {
                    continue;
                }
                $current_class = ($this->current_tab == $idtab ? 'nav-tab-active' : '');
                $tab_url = '';
                if ($idtab != 'default') {
                    $tab_url = '&tab=' . $idtab;
                }
                $main_tab_url = admin_url($this->main_url . $tab_url);
                if (is_network_admin()) {
                    $main_tab_url = network_admin_url('sites.php?page=wpuoptions-settings&id=' . $this->get_current_site_id() . $tab_url);
                }
                $content .= '<a class="nav-tab ' . $current_class . '" href="' . $main_tab_url . '">' . $tab['name'] . '</a>';
            }
            $content .= '</h2><br />';
        }

        $languages = $this->get_languages();
        if (!empty($languages)) {
            $content .= '<ul class="wpu-options-lang-switcher">';
            foreach ($languages as $id => $lang) {
                $content .= '<li><a href="#" data-lang="' . esc_attr($id) . '">' . $lang . '</a></li>';
            }
            $content .= '</ul>';
        }

        foreach ($this->boxes as $idbox => $box) {
            $box_tab = isset($box['tab']) ? $box['tab'] : 'default';
            $box_usercan = isset($box['current_user_can']) ? current_user_can($box['current_user_can']) : true;
            if ($box_tab != $this->current_tab || !$box_usercan) {
                continue;
            }
            $content_tmp = '';
            foreach ($this->fields as $id => $field) {
                if ((isset($field['box']) && $field['box'] == $idbox) || ($idbox == 'default' && !isset($field['box']))) {
                    $content_tmp .= $this->admin_field($id, $field);
                    if (isset($field['lang']) && $field['lang']) {
                        $has_lang = true;
                    }
                }
            }
            if (!empty($content_tmp)) {
                $content .= '<div class="wpu-options-form__box">';

                // Adding box name if available
                if (empty($box['name'])) {
                    $box['name'] = ucfirst($idbox);
                }
                $content .= '<h3 id="box-' . $idbox . '" class="wpu-options-form__title">' . $box['name'] . '</h3>';
                $content .= '<table class="wpu-options-form__table">' . $content_tmp . '</table>';
                $content .= '</div>';
            }
        }

        $content .= '<ul><li>' . get_submit_button(__('Update', 'wpuoptions'), 'primary', 'wpuoptions_submit') . '</li></ul>';
        $content .= wp_nonce_field('wpuoptions-nonceaction', 'wpuoptions-noncefield', 1, 0);
        $content = '<form action="" method="post" class="wpu-options-form ' . ($has_lang ? 'has-lang' : '') . '">' . $content . '</form>';

        $current_admin_language = $this->get_current_admin_language();
        if ($current_admin_language) {
            echo '<script>window.wpuoptions_current_admin_language="' . esc_attr($current_admin_language) . '";</script>';
        }

        return $content;
    }

    /**
     * Return an admin field
     *
     * @param unknown $id
     * @param unknown $field (optional)
     * @return unknown
     */
    private function admin_field($id, $field = array()) {
        $languages = $this->get_languages();
        $fields_versions = array();

        if (empty($languages) || !isset($field['lang'])) {
            $fields_versions[] = array(
                'id' => $id,
                'field' => $field,
                'prefix_opt' => ''
            );
        } else {
            foreach ($languages as $idlang => $lang) {
                $fields_versions[] = array(
                    'id' => $id,
                    'field' => $field,
                    'prefix_opt' => $idlang . '___',
                    'idlang' => $idlang,
                    'lang' => $lang
                );
            }
        }
        $content = '';
        $upload_dir = wp_upload_dir();
        $wpu_posttypes = apply_filters('wputh_get_posttypes', array());
        $wpu_taxonomies = apply_filters('wputh_get_taxonomies', array());
        $main_value = get_option($id);

        foreach ($fields_versions as $field_version) {
            $idf = $this->get_field_id($field_version['prefix_opt'] . $field_version['id']);
            $field = $this->get_field_datas($field_version['id'], $field_version['field']);
            if (!$this->is_item_visible($field)) {
                continue;
            }
            $is_multiple = isset($field['multiple']) && $field['multiple'];
            $idname = ' id="' . $idf . '" name="' . $idf . ($is_multiple ? '[]' : '') . '" ';
            $originalvalue = get_option($field_version['prefix_opt'] . $field_version['id']);
            if (!$originalvalue && $originalvalue !== '' && $main_value) {
                $originalvalue = $main_value;
            }
            $field_post_type = isset($field['post_type']) ? $field['post_type'] : 'post';
            if ($originalvalue === false && isset($field['default_value']) && $this->test_field_value($field, $field['default_value'])) {
                $originalvalue = $field['default_value'];
                update_option($field_version['prefix_opt'] . $field_version['id'], $field['default_value'], $field['autoload']);
            }
            if (!isset($field['editoroptions']) || !is_array($field['editoroptions'])) {
                $field['editoroptions'] = array();
            }
            if (!isset($field['editoroptions']['textarea_rows'])) {
                $field['editoroptions']['textarea_rows'] = 7;
            }
            if (isset($field['editorbuttons'])) {
                $field['editoroptions']['quicktags'] = false;
                $field['editoroptions']['teeny'] = true;
                $field['editoroptions']['tinymce'] = true;
            }

            $value = '';
            if (!is_object($originalvalue) && !is_array($originalvalue)) {
                $value = htmlspecialchars($originalvalue, ENT_QUOTES, "UTF-8");
            }
            if (is_array($originalvalue) && wpuoptions_is_array_of_numbers($originalvalue)) {
                $value = $originalvalue;
            }

            $field_required = isset($field['required']) && $field['required'];

            /* Attributes */
            if (!isset($field['attributes']) || !is_array($field['attributes'])) {
                $field['attributes'] = array();
            }
            $pattern = '';
            if ($field['type'] == 'datetime-local') {
                $pattern = '[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}';
            }
            if (isset($field['pattern'])) {
                $field['attributes']['pattern'] = $field['pattern'];
            }

            if (!empty($field['attributes'])) {
                foreach ($field['attributes'] as $key => $var) {
                    $idname .= ' ' . $key . '="' . esc_attr($var) . '"';
                }
            }

            $placeholder = '';
            if (isset($field['placeholder'])) {
                $placeholder = ' placeholder="' . esc_attr($field['placeholder']) . '"';
            }

            $lang_attr = '';
            if (isset($field_version['idlang'])) {
                $lang_attr = 'data-lang="' . $field_version['idlang'] . '"';
            }

            $content .= '<tr class="wpu-options-box" ' . $lang_attr . '>';
            if ($field['type'] != 'title') {
                $content .= '<td class="td-label">';
                if (WP_DEBUG && current_user_can('activate_plugins')) {
                    $helper = "get_option('" . $field_version['id'] . "')";
                    if (!empty($field_version['prefix_opt'])) {
                        $helper = "wputh_l18n_get_option('" . $field_version['id'] . "')";
                    }
                    $content .= '<span class="wpu-options-helper"><span class="dashicons dashicons-editor-help"></span></span>';
                    $content .= '<div class="wpu-options-field-info" contenteditable="true">' . $helper . '</div>';
                }
                $content .= '<label for="' . $idf . '">' . $field['label'];
                if ($field_required) {
                    $content .= '<em>*</em>';
                }
                $content .= '&nbsp;: </label>';
                $content .= '</td>';
                $content .= '<td>';
            } else {
                $content .= '<td class="td-title" colspan="2"><h4>' . $field['label'] . '</h4>';

            }
            $content .= '<div class="' . ($lang_attr != '' ? 'wpufield-has-lang' : '') . '">';
            switch ($field['type']) {
            case 'editor':
                ob_start();
                wp_editor($originalvalue, $idf, $field['editoroptions']);
                $content_editor = ob_get_clean();
                if (!empty($originalvalue)) {
                    $content .= '<div class="wpuoptions-view-editor-switch">';
                    $content .= '<div class="original-view"><div class="original">' . apply_filters('the_content', $originalvalue) . '</div><a class="edit-link button button-small" href="#" role="button">' . __('Edit this text', 'wpuoptions') . '</a>' . '</div>';
                    $content .= '<div class="editor-view" data-id-editor="' . $idf . '" data-original-value="' . esc_attr(str_replace("\n", '<br />', $originalvalue)) . '">' . $content_editor . '<a class="edit-link cancel-link button button-small" href="#" role="button">' . __('Cancel edition', 'wpuoptions') . '</a>' . '</div>';
                    $content .= '</div>';
                } else {
                    $content .= '<div class="wpuoptions-view-editor-wrapper">' . $content_editor . '</div>';
                }
                break;
            case 'file':
            case 'media':
                $btn_label = __('Add a picture', 'wpuoptions');
                $btn_edit_label = __('Change this picture', 'wpuoptions');
                $btn_confirm_delete = __('Do you really want to remove this image ?', 'wpuoptions');
                if ($field['type'] == 'file') {
                    $btn_label = __('Add a file', 'wpuoptions');
                    $btn_edit_label = __('Change this file', 'wpuoptions');
                    $btn_confirm_delete = __('Do you really want to remove this file ?', 'wpuoptions');
                }
                $btn_label_display = $btn_label;
                $content_preview = '';
                if (is_numeric($value)) {
                    $image = wp_get_attachment_image_src($value, 'medium');
                    $file = wp_get_attachment_url($value);
                    if (isset($image[0])) {
                        $content_preview = '<div class="wpu-options-upload-preview"><span class="x">&times;</span><img src="' . $image[0] . '?v=' . time() . '" alt="" /></div>';
                    } else if ($file) {
                        $file = str_replace($upload_dir['baseurl'], '', $file);
                        $content_preview = '<div class="wpu-options-upload-preview"><span class="x">&times;</span><div class="wpu-options-upload-preview--file">' . $file . '</div></div>';
                    }
                    /* Check if we got a file */
                    if (!empty($content_preview)) {
                        $btn_label_display = $btn_edit_label;
                    } else {
                        $value = '';
                    }
                }

                $content .= '<div data-removethis="' . $upload_dir['baseurl'] . '" data-type="' . $field['type'] . '" data-confirm="' . $btn_confirm_delete . '" data-defaultlabel="' . esc_attr($btn_label) . '" data-label="' . esc_attr($btn_edit_label) . '" id="preview-' . $idf . '">' . $content_preview . '</div>' . '<a href="#" data-for="' . $idf . '" class="button button-small wpuoptions_add_media">' . $btn_label_display . '</a>' . '<input class="hidden-value" type="hidden" ' . $idname . ' value="' . $value . '" />';
                break;
            case 'taxonomy':
            case 'category':
                $req = array(
                    'orderby' => 'name',
                    'order' => 'ASC',
                    'taxonomy' => 'category',
                    'hide_empty' => false
                );

                if (isset($field['taxonomy'])) {
                    $req['taxonomy'] = $field['taxonomy'];
                } else {
                    $req['taxonomy'] = 'category';
                }

                if (isset($field_version['idlang']) && $field_version['idlang']) {
                    $req['lang'] = $field_version['idlang'];
                }

                $select_string = _x('Select a %s', 'male', 'wpuoptions');
                if ($req['taxonomy'] == 'category' || $req['taxonomy'] == 'product_cat' || (is_array($wpu_taxonomies) && isset($wpu_taxonomies[$req['taxonomy']], $wpu_taxonomies[$req['taxonomy']]['female']) && $wpu_taxonomies[$req['taxonomy']]['female'])) {
                    $select_string = _x('Select a %s', 'female', 'wpuoptions');
                }
                $field_taxonomy_name = $req['taxonomy'];
                $field_taxonomy_object = get_taxonomy($req['taxonomy']);
                if ($field_taxonomy_object) {
                    $field_taxonomy_name = $field_taxonomy_object->labels->singular_name;
                }

                $content .= '<select ' . $idname . '>';
                $content .= '<option value="" disabled selected style="display:none;">' . sprintf($select_string, strtolower($field_taxonomy_name)) . '</option>';
                $_terms = get_terms($req);
                foreach ($_terms as $_term) {
                    $selected = ($_term->term_taxonomy_id == $value);
                    $content .= '<option ' . ($selected ? 'selected="selected"' : '') . ' value="' . $_term->term_taxonomy_id . '">';
                    $content .= esc_html($_term->name) . ' - #' . $_term->term_taxonomy_id;
                    $content .= '</option>';
                }
                $content .= '</select>';

                break;

            case 'page':
            case 'post':
                if ($field['type'] == 'page') {
                    $field_post_type = 'page';
                }
                $req = array(
                    'posts_per_page' => -1,
                    'post_type' => $field_post_type,
                    'orderby' => 'name',
                    'order' => 'ASC'
                );
                if (isset($field['lang'], $field_version['idlang']) && $field['lang']) {
                    $req['lang'] = $field_version['idlang'];
                }
                $cache_id = 'wpuoptions_post_cache_' . md5(serialize($req));
                $wpq_post_type = wp_cache_get($cache_id);
                if (!is_array($wpq_post_type)) {
                    $wpq_post_type_raw = get_posts($req);
                    $wpq_post_type = array();
                    foreach ($wpq_post_type_raw as $item) {
                        $wpq_post_type[$item->ID] = $item->post_title;
                    }
                    unset($wpq_post_type_raw);
                    wp_cache_set($cache_id, $wpq_post_type, '', 30);
                }

                $select_string = _x('Select a %s', 'male', 'wpuoptions');
                if ($field_post_type == 'page' || (is_array($wpu_posttypes) && isset($wpu_posttypes[$field_post_type], $wpu_posttypes[$field_post_type]['female']) && $wpu_posttypes[$field_post_type]['female'])) {
                    $select_string = _x('Select a %s', 'female', 'wpuoptions');
                }

                $field_post_type_name = $field_post_type;
                $field_post_type_object = get_post_type_object($field_post_type);
                if ($field_post_type_object) {
                    $field_post_type_name = $field_post_type_object->labels->singular_name;
                    if ($is_multiple) {
                        $field_post_type_name = $field_post_type_object->labels->name;
                    }
                }

                if ($is_multiple) {
                    $select_string = __('Select some %s', 'wpuoptions');
                }

                $option_label = '<option value="" disabled selected style="display:none;">' . sprintf($select_string, strtolower($field_post_type_name)) . '</option>';
                $content .= '<select ' . ($is_multiple ? 'multiple' : '') . ' ' . $idname . '">' . ($is_multiple ? '' : $option_label);
                foreach ($wpq_post_type as $key => $wpq_post_title) {
                    $selected = ($key == $value) || (is_array($value) && in_array($key, $value));
                    $content .= '<option value="' . htmlentities($key) . '" ' . ($selected ? 'selected="selected"' : '') . '>';
                    $content .= esc_html(wp_strip_all_tags($wpq_post_title)) . ' - #' . $key;
                    $content .= '</option>';
                }
                $content .= '</select>';
                break;
            case 'select':
                $content .= '<select ' . $idname . '><option value="" disabled selected style="display:none;">' . __('Select a value', 'wpuoptions') . '</option>';
                foreach ($field['datas'] as $key => $var) {
                    $content .= '<option value="' . htmlentities($key) . '" ' . selected($key, $value, 0) . '>' . htmlentities($var) . '</option>';
                }
                $content .= '</select>';
                break;
            case 'radio':
                foreach ($field['datas'] as $key => $var) {
                    $content .= '<label class="label-radio"><input ' . ($field_required ? 'required="required"' : '') . ' type="radio" name="' . $idf . '" value="' . htmlentities($key) . '"  ' . checked($key, $value, 0) . '/> ' . htmlentities($var) . '</label>';
                }
                break;
            case 'textarea':
                $content .= '<textarea ' . $placeholder . ' ' . $idname . ' rows="5" cols="30">' . $value . '</textarea>';
                break;
            case 'checkbox':
                $content .= '<input type="hidden" name="' . $idf . '__check" value="1" />';
                $content .= '<label><input ' . ($field_required ? 'required="required"' : '') . ' type="checkbox" ' . $idname . ' value="1" ' . ($value == '1' ? 'checked="checked"' : '') . ' /> ' . $field['label_check'] . '</label>';
                break;
            case 'title':
                $content .= '';
                break;
            case 'wp_link':
                $html_preview = '';
                if ($value) {
                    $json_preview = json_decode(html_entity_decode($value));
                    if (is_object($json_preview) && isset($json_preview->text) && isset($json_preview->href)) {
                        $html_preview = '<a onclick="return false;" href="' . esc_url($json_preview->href) . '">' . htmlentities($json_preview->text) . '</a>';
                    }
                }
                $content .= '<div class="wpuoptions-type-link" ' . ($html_preview ? ' data-wpuoptions-haslink="1"' : '') . '>';
                $content .= '<p class="link-preview">' . $html_preview . '</p>';
                $content .= '<textarea ' . $placeholder . ' ' . $idname . ' rows="5" cols="30">' . $value . '</textarea>';
                $add_link_label = __('Add a link', 'wpuoptions');
                $edit_link_label = __('Edit link', 'wpuoptions');
                $content .= '<button data-label-edit-link="' . esc_attr($edit_link_label) . '" data-label-add-link="' . esc_attr($add_link_label) . '" class="button button-small" data-wpuoptions-wplink="1" class="link-button" type="button">' . ($html_preview ? $edit_link_label : $add_link_label) . '</button>';
                $content .= '<button class="button button-small delete-link" data-wpuoptions-wplinkpurge="1" class="link-button" type="button" title="' . esc_attr(__('Delete link', 'wpuoptions')) . '">&times;</button>';
                $content .= '</div>';
                break;
            /* Multiple cases */
            case 'color':
            case 'date':
            case 'datetime-local':
            case 'email':
            case 'number':
            case 'url':
                $content .= '<input ' . ($field_required ? 'required="required"' : '') . ' ' . $placeholder . ' type="' . $field['type'] . '" ' . $idname . ' value="' . $value . '" />';
                break;
            default:
                $content .= '<input ' . ($field_required ? 'required="required"' : '') . ' ' . $placeholder . ' type="text" ' . $idname . ' value="' . $value . '" />';
            }
            if (isset($field['help'])) {
                $content .= '<small class="wpuoptions-help">' . $field['help'] . '</small>';
            }
            $content .= '</div>';
            $content .= '</td>';
            $content .= '</tr>';
        }
        return $content;
    }

    /**
     * Getting all datas for a field, with default values for undefined params
     *
     * @param int     $id
     * @param unknown $field
     * @return unknown
     */
    private function get_field_datas($id, $field) {

        $default_values = array(
            'autoload' => true,
            'box' => 'default',
            'label' => $id,
            'label_check' => '',
            'type' => 'text',
            'visibility_network' => false,
            'visibility_admin' => true,
            'multiple' => false,
            'test' => '',
            'datas' => array(
                __('No', 'wpuoptions'),
                __('Yes', 'wpuoptions')
            )
        );
        foreach ($default_values as $name => $value) {
            if (!is_bool($value) && empty($field[$name]) || !isset($field[$name])) {
                $field[$name] = $value;
            }
        }

        return $field;
    }

    /**
     * Generate export URL
     *
     * @return unknown
     */
    private function generate_export($boxes = array()) {
        $languages = $this->get_languages();

        $import_all_boxes = false;
        if (!is_array($boxes) || empty($boxes)) {
            $import_all_boxes = true;
        }

        $options = array();

        // Array of fields:values
        foreach ($this->fields as $id => $field) {
            $opt_field = $field;
            if (!isset($opt_field['box']) || $opt_field['box'] == 'default' || empty($opt_field['box']) || !array_key_exists($opt_field['box'], $this->boxes)) {
                $opt_field['box'] = 'default';
            }
            if ($import_all_boxes || in_array($opt_field['box'], $boxes)) {
                $options[$id] = get_option($id);
                // If this field has i18n
                if (isset($opt_field['lang']) && !empty($languages)) {
                    foreach ($languages as $lang => $name) {
                        $options[$lang . '___' . $id] = get_option($lang . '___' . $id);
                    }
                }
            }
        }

        header('Content-disposition: attachment; filename=' . $filename);
        header('Content-type: application/json');
        echo json_encode($options);
        die;
    }

    /**
     * Import json into options
     *
     * @param string  $json
     * @return unknown
     */
    private function import_options($json) {
        $return = false;
        $options = json_decode($json);
        if (is_object($options)) {
            foreach ($options as $id => $value) {
                update_option($id, $value);
            }
            $return = true;
        }
        return $return;
    }

    /**
     * Validate a field value
     *
     * @param string  $field
     * @param unknown $value
     * @return boolean
     */
    private function test_field_value($field, $value) {

        if (!isset($field['test'])) {
            $field['test'] = false;
        }

        $return = true;
        switch ($field['test']) {
        case 'email':
            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $return = false;
            }
            break;
        case 'wp_link':
            $j_value = json_decode($value, true);
            if (!is_array($value)) {
                $return = false;
            }
            break;
        case 'taxonomy':
        case 'category':
        case 'page':
        case 'post':
            if ($field['multiple']) {
                return wpuoptions_is_array_of_numbers($value);
            } else {
                if (!ctype_digit($value)) {
                    $return = false;
                }
            }
            break;
        case 'radio':
        case 'select':
            if (!array_key_exists($value, $field['datas'])) {
                $return = false;
            }
            break;
        case 'url':
            if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                $return = false;
            }
            break;
        default:
        }
        return $return;
    }

    /**
     * Optain an admin field id
     *
     * @param string  $id
     * @return string
     */
    private function get_field_id($id) {
        return 'wpu_admin_id_' . $id;
    }

    /**
     * Add custom buttons to editor
     * @param  array    $buttons     List of TinyMCE Buttons to keep
     * @param  string   $editor_id   Targeted editor ID
     * @return array                 Final list of buttons
     */
    public function custom_editor_buttons($buttons, $editor_id) {
        foreach ($this->fields as $id => $field) {
            if (!isset($field['type'], $field['editorbuttons']) || $field['type'] != 'editor' || empty($field['editorbuttons']) || !is_array($field['editorbuttons'])) {
                continue;
            }
            $field_ids = array(
                $this->get_field_id($id)
            );
            if (isset($field['lang'])) {
                $languages = $this->get_languages();
                foreach ($languages as $lang => $name) {
                    $field_ids[] = $this->get_field_id($lang . '___' . $id);
                }
            }

            if (!in_array($editor_id, $field_ids)) {
                continue;
            }
            return $field['editorbuttons'];
        }
        return $buttons;
    }

    /**
     * Obtain a list of languages
     *
     * @return array
     */
    public function get_languages() {
        global $q_config, $polylang;
        $languages = array();

        // Obtaining from Qtranslate
        if (isset($q_config['enabled_languages'])) {
            foreach ($q_config['enabled_languages'] as $lang) {
                if (!in_array($lang, $languages) && isset($q_config['language_name'][$lang])) {
                    $languages[$lang] = $q_config['language_name'][$lang];
                }
            }
        }

        // Obtaining from Polylang
        if (function_exists('pll_the_languages') && is_object($polylang)) {
            $poly_langs = $polylang->model->get_languages_list();
            foreach ($poly_langs as $lang) {
                $languages[$lang->slug] = $lang->name;
            }
        }

        // From WPML
        if (!function_exists('pll_the_languages') && function_exists('icl_get_languages')) {
            $wpml_lang = icl_get_languages();
            foreach ($wpml_lang as $lang) {
                $languages[$lang['code']] = $lang['native_name'];
            }
        }

        return $languages;
    }

    public function get_current_admin_language() {
        global $polylang;
        $current_language = false;

        // Obtaining from Polylang
        if (function_exists('pll_the_languages') && is_object($polylang) && !is_null($polylang->pref_lang)) {
            $current_language_tmp = $polylang->pref_lang->slug;
            if ($current_language_tmp != 'all') {
                $current_language = $current_language_tmp;
            }
        }

        return $current_language;
    }

    /* ----------------------------------------------------------
      Multisite
    ---------------------------------------------------------- */

    /**
     * Handle Tabs in multisite settings
     */
    public function network_edit_site_nav_links($tabs) {
        /* Hide settings tab */
        unset($tabs['site-settings']);

        /* Add new tab */
        $tabs['site-wpuoptions'] = array(
            'label' => 'WPU Options',
            'url' => add_query_arg('page', 'wpuoptions-settings', 'sites.php'),
            'cap' => 'manage_sites'
        );
        return $tabs;
    }

    /* Create new page */
    public function network_admin_menu() {
        add_submenu_page('', $this->options['plugin_name'], $this->options['plugin_name'], 'manage_network_options', 'wpuoptions-settings', array(&$this,
            'network_admin_page'
        ));
    }

    /* Page content */
    public function network_admin_page() {
        $id = $this->get_current_site_id();

        global $title;
        echo '<div class="wrap">';
        echo '<h1 id="edit-site">' . esc_html($title) . '</h1>';

        network_edit_site_nav(
            array(
                'blog_id' => $id,
                'selected' => 'site-wpuoptions'
            )
        );

        switch_to_blog($id);
        if (!empty($this->fields)) {
            echo $this->fields_messages;
            echo $this->admin_form();
        } else {
            echo '<p>' . __('No fields for the moment', 'wpuoptions') . '</p>';
        }
        restore_current_blog();

        echo '</div>';
    }

    public function check_screen_access() {
        if (!$this->is_network_wputools_admin()) {
            return;
        }

        /* Set global options */
        global $title;
        $blog_details = get_blog_details(array(
            'blog_id' => $this->get_current_site_id()
        ));
        $title = __('Edit Site:') . ' ' . $blog_details->blogname;
    }

    public function is_network_wputools_admin() {
        if (!function_exists('get_current_screen')) {
            return false;
        }
        $screen = get_current_screen();
        if ('sites_page_wpuoptions-settings-network' !== $screen->id) {
            return false;
        }
        return true;
    }

    public function get_current_site_id($id = false) {

        if (!$id) {
            $id = isset($_REQUEST['id']) ? absint($_REQUEST['id']) : 0;
        }

        if (!$id) {
            wp_die(__('Incorrect site ID.'));
        }

        if (!get_site($id)) {
            wp_die(__('The requested site does not exist.'));
        }

        return $id;

    }

    public function is_item_visible($item) {
        if (is_network_admin() && !$item['visibility_network']) {
            return false;
        }
        if (!is_network_admin() && is_admin() && !$item['visibility_admin']) {
            return false;
        }
        return true;

    }

    /* Handle attachments in multisite */
    public function ajax_query_attachments_args($args) {
        /* Check if ajax query origin is in a network admin page */
        $referer_url = wp_get_referer();
        if (!$referer_url || strpos($referer_url, network_site_url()) === false) {
            return $args;
        }
        $referer = parse_url($referer_url);
        if (!is_array($referer) || !isset($referer['query'])) {
            return $args;
        }
        parse_str($referer['query'], $params);
        if (!isset($params['page'], $params['id']) || $params['page'] != 'wpuoptions-settings' || !ctype_digit($params['id'])) {
            return $args;
        }

        /* Ensure correct blog is available  */
        switch_to_blog($this->get_current_site_id($params['id']));
        return $args;
    }

}

$WPUOptions = new WPUOptions();

/* ----------------------------------------------------------
  Utilities
---------------------------------------------------------- */

/**
 * Get an option value with l18n
 *
 * @param string  $name
 * @return string
 */
function wputh_l18n_get_option($name, $lang = false) {
    global $q_config;

    $option = get_option($name);

    /* Define lang */

    if ($lang === false) {
        if (isset($q_config['language'])) {
            $lang = $q_config['language'];
        }
        if (function_exists('pll_current_language')) {
            $lang = pll_current_language();
        }
        if (!function_exists('pll_current_language') && function_exists('icl_get_languages') && defined('ICL_LANGUAGE_CODE')) {
            $lang = ICL_LANGUAGE_CODE;
        }
    }

    /* Get meta value */

    if ($lang !== false) {
        $option_l18n = get_option($lang . '___' . $name);
        if ($option_l18n || $option_l18n === '') {
            $option = $option_l18n;
        }
    }

    /* Use default language value */
    $default_language = '';
    if (isset($q_config['language'])) {
        $default_language = $q_config['enabled_languages'][0];
    }
    if (function_exists('pll_default_language')) {
        $default_language = pll_default_language();
    }
    if (!function_exists('pll_default_language') && function_exists('icl_get_languages') && defined('ICL_LANGUAGE_CODE')) {
        $default_language = apply_filters('wpml_default_language', NULL);
    }
    $default_language = apply_filters('wputh_l18n_get_option__defaultlang', $default_language);

    $use_default = apply_filters('wputh_l18n_get_option__usedefaultlang', true);
    if (empty($option) && $use_default && $default_language && $lang != $default_language) {
        return wputh_l18n_get_option($name, $default_language);
    }

    return $option;
}

/**
 * Get media details
 *
 * @param string  $option_name
 * @param string  $size
 * @return string
 */
function wpu_options_get_media($option_name, $size = 'thumbnail') {
    $default_attachment_details = array(
        'title' => '',
        'caption' => '',
        'alt' => '',
        'description' => '',
        'href' => '',
        'src' => '',
        'width' => 0,
        'height' => 0
    );

    $attachment_details = $default_attachment_details;

    $attachment_id = get_option($option_name);
    $attachment = get_post($attachment_id);

    if (isset($attachment->post_title)) {
        $attachment_details['title'] = trim($attachment->post_title);
        $attachment_details['caption'] = trim($attachment->post_excerpt);
        $attachment_details['description'] = $attachment->post_content;
        $attachment_details['href'] = get_permalink($attachment->ID);
        $attachment_details['src'] = $attachment->guid;
    }

    $image = wp_get_attachment_image_src($attachment_id, $size);
    if (isset($image[0])) {
        $attachment_details['src'] = $image[0];
        $attachment_details['width'] = $image[1];
        $attachment_details['height'] = $image[2];
    } else {
        $attachment_details = $default_attachment_details;
        $attachment_details['src'] = get_stylesheet_directory_uri() . '/images/options/' . $option_name . '.jpg';
    }

    return $attachment_details;
}

/**
 * Check if a variable is an array of numbers
 *
 * @param  array  $array   array to check
 * @return bool            test result
 */
function wpuoptions_is_array_of_numbers($array = array()) {
    if (!is_array($array)) {
        return false;
    }
    foreach ($array as $value) {
        if (!is_numeric($value)) {
            return false;
        }
    }
    return true;
}
