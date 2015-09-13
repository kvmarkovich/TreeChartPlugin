<?php

/*
  Plugin Name: TreeChart Plugin
  Plugin URI: http://zabeba.li
  Description: Plugin for creating TreeCharts
  Version: 1.0.1
  Author: kmarkovych
  Author URI: http://zabeba.li
  License: A "Slug" license name e.g. GPL2
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
} // end if

class TreeChartPlugin
{

    private $cache_key;

    /**
     * Plugin version, used for cache-busting of style and script file references.
     *
     * @since   1.0.0
     *
     * @var     string
     */
    const VERSION = '1.0.1';

    /**
     * Unique identifier for the plugin.
     *
     * The variable name is used as the text domain when internationalizing strings
     * of text.
     *
     * @since    1.0.0
     *
     * @var      string
     */
    protected $plugin_slug = 'treechart';

    /**
     * A reference to an instance of this class.
     *
     * @since 1.0.0
     *
     * @var   TreeChart
     */
    private static $instance;

    /**
     * The array of templates that this plugin tracks.
     *
     * @var      array
     */
    static protected $templates;
    static private $config = array(
        'plugin_name' => 'treechart',
        'display_name' => 'TreeChart',
        'submenus' => array(
            'gorsovet' => array(
                'name' => 'gorsovet',
                'title' => 'Gorsovet',
                'description' => 'Dnepropetrovsk goverment structure',
                'icon' => 'dashicons-chart-pie',
                'metas' => array(
                    array('name' => 'id', 'text' => 'ID', 'type' => 'input'),
                    array('name' => 'parent_id', 'text' => 'Parrent node', 'type' => 'select'),
                    array('name' => 'name', 'text' => 'Name', 'type' => 'input'),
                    array('name' => 'position', 'text' => 'Position', 'type' => 'textarea'),
                    array('name' => 'contacts', 'text' => 'Contacts', 'type' => 'textarea')
                )
                /* 		,
                  array(
                  'name'        => 'some_name',
                  'title'       => 'Some Title',
                  'description' => 'Some description...',
                  'icon' => 'dashicons-welcome-view-site'
                  ) */
            )
        )
    );

    /**
     * Returns an instance of this class. An implementation of the singleton design pattern.
     *
     * @return   TreeChartPlugin    A reference to an instance of this class.
     * @since    1.0.0
     */
    static public function get_instance()
    {

        if (null == self::$instance) {
            self::$instance = new TreeChartPlugin();
        } // end if

        return self::$instance;
    }

// end getInstance

    /**
     * Initializes the plugin by setting localization, filters, and administration functions.
     *
     * @version        1.0.0
     * @since        1.0.0
     */
    private function __construct()
    {

        $this->templates = array();
        $this->plugin_locale = 'pte';

        wp_register_script('ajax-gorsovet', plugins_url('/js/ajax-gorsovet.js', __FILE__), array('jquery'));

        wp_register_script('getorgchart', plugins_url('/getorgchart/getorgchart.js', __FILE__), array('jquery'));
        /* Регистрируем наш стиль. */
        wp_register_style('getorgchart', plugins_url('/getorgchart/getorgchart.css', __FILE__));


        // Grab the translations for the plugin
//		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
        // Add a filter to the page attributes metabox to inject our template into the page template cache.
        add_filter('page_attributes_dropdown_pages_args', array($this, 'register_project_templates'));

        // Add a filter to the save post in order to inject out template into the page cache
        add_filter('wp_insert_post_data', array($this, 'register_project_templates'));

        // Add a filter to the template include in order to determine if the page has our template assigned and return it's path
        add_filter('template_include', array($this, 'view_project_template'));

        // Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Add your templates to this array.
        $this->templates = array(
            'page-gorsovet.php' => __('Gorsovet Page Template', $this->plugin_slug)
        );

        // adding support for theme templates to be merged and shown in dropdown
        $templates = wp_get_theme()->get_page_templates();
        $templates = array_merge($templates, $this->templates);


        add_action('admin_menu', array($this, 'set_admin_menu'));
        add_action('init', array($this, 'custom_post_type_init'));


        add_action('add_meta_boxes', array($this, 'metabox_init'));
        add_action('save_post', array($this, 'metabox_save'), 10, 3);


        add_action('wp_ajax_get_chart_data', array($this, 'get_chart_data_callback'));
        add_action('wp_ajax_nopriv_get_chart_data', array($this, 'get_chart_data_callback'));
    }

// end constructor

    function metabox_init()
    {
        foreach (self::$config['submenus'] as $s) {
            add_meta_box('metabox', $s['title'] . ' параметры поста', array($this, 'metabox_showup'), $s['name'], 'normal', 'high', $s);
        }
    }

    function metabox_showup($post, $box)
    {
        global $wpdb;
        $config = $box['args'];
        $parentids = $wpdb->get_col($wpdb->prepare(
            "
                        SELECT      key1.meta_value
                        FROM        $wpdb->postmeta key1
                        INNER JOIN  $wpdb->posts key2
                                    ON key2.id = key1.post_id
                        WHERE       key1.meta_key = %s and key2.post_type = %s
                        ORDER BY    key1.meta_value+(0) ASC
                        ", "_" . "id", $config['name']
        ));

        //print_r($parentids); 
        // скрытое поле с одноразовым кодом
        wp_nonce_field('metabox_action', 'metabox_nonce');

        echo '<table>';
        foreach ($config['metas'] as $meta_name) {
            $meta_field_name = "_" . $meta_name['name'];
            // получение существующих метаданных
            $meta = get_post_meta($post->ID, $meta_field_name, true);

            // поле с метаданными
            echo '<tr><td>' . $meta_name['text'] . '</td><td>';

            switch ($meta_name['type']) {
                case 'select':
                    $result = '<select name="' . $meta_field_name . '"><option></option>';
                    foreach ($parentids as $parent) {
                        $result .= '<option value="' . ${parent} . '" ';
                        if ($parent == $meta)
                            $result .= 'selected="selected"';
                        $result .= '>' . ${parent} . '</option>';
                    }
                    $result .= '</select>';
                    break;
                case 'textarea':
                    $result = '<textarea name="' . $meta_field_name . '" cols=55 rows=2 >' . esc_attr($meta) . '</textarea>';
                    break;
                default:
                    $result = '<input type="text" name="' . $meta_field_name . '" size=55 value="' . esc_attr($meta) . '"/>';
                    break;
            }

            echo $result . '</td></tr>';
        }
        echo '</table>';
    }

    function metabox_save($postID, $post, $update)
    {

        // пришло ли поле наших данных?
//        if (!isset($_POST['metadata_field'])) {
//            return;
//        }
        // не происходит ли автосохранение?
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // не ревизию ли сохраняем?
        if (wp_is_post_revision($postID)) {
            return;
        }

        // проверка достоверности запроса
        check_admin_referer('metabox_action', 'metabox_nonce');

        $post_type_config = self::$config['submenus'][$post->post_type];

        foreach ($post_type_config['metas'] as $meta) {

            $meta_field_name = "_" . $meta['name'];
            // коррекция данных
            $data = sanitize_text_field($_POST[$meta_field_name]);

            // запись
            if (is_null($data) || empty($data)) {
                delete_post_meta($postID, $meta_field_name);
            } else {
                update_post_meta($postID, $meta_field_name, $data);
            }
        }
    }

    /**
     *
     */
    function set_admin_menu()
    {
        /* Регистрируем страницу нашего плагина */
        $page = add_submenu_page('plugins.php', // Родительская страница меню
            __(self::$config['display_name'], self::$config['plugin_name']), // Название пункта меню
            __(self::$config['display_name'], self::$config['plugin_name']), // Заголовок страницы
            'manage_options', // Возможность, определяющая уровень доступа к пункту
            self::$config['plugin_name'] . '-options', // Ярлык (часть адреса) страницы плагина
            array($this, 'my_plugin_manage_menu') // Функция, которая выводит страницу
        );


//		//create new top-level menu
//		add_menu_page( self::$config['display_name'] . ' Plugin Settings',
//			self::$config['display_name'] . ' Settings',
//			'administrator',
//			__FILE__,
//			self::$config['plugin_name'] . '_settings_page',
//			plugins_url( '/images/icon.png', __FILE__ ),
//			6 );
    }

    /**
     * Shows settings page.
     * TBD
     */
    function my_plugin_manage_menu()
    {

    }

    /**
     *
     */
    function enqueue_scripts()
    {
        /*
         * Эта функция будет вызвана только на странице плагина, подключаем наш скрипт
         */
        wp_enqueue_script('getorgchart');
        /*
         * Эта функция будет вызвана только на странице плагина,
          поставим наш стиль в очередь здесь */
        wp_enqueue_style('getorgchart');
    }

    /**
     * Добавляем кастомный тип записи
     */
    public function custom_post_type_init()
    {
        foreach (self::$config['submenus'] as $s) {
            register_post_type($s['name'], array(
                'labels' => array(
                    'name' => _x($s['title'], $s['description']),
                ),
                'public' => true,
                'supports' => array('title', 'thumbnail', 'custom-fields', 'comments', 'revisions'),
                'menu_icon' => $s['icon']
            ));
        }
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain()
    {

        $domain = $this->plugin_slug;
        $locale = apply_filters('plugin_locale', get_locale(), $domain);

        load_textdomain($domain, trailingslashit(WP_LANG_DIR) . $domain . '/' . $domain . '-' . $locale . '.mo');
        load_plugin_textdomain($domain, false, basename(dirname(__FILE__)) . '/languages/');
    }

// end load_plugin_textdomain

    /**
     * Adds our template to the pages cache in order to trick WordPress
     * into thinking the template file exists where it doens't really exist.
     *
     * @param   array $atts The attributes for the page attributes dropdown
     *
     * @return  array    $atts    The attributes for the page attributes dropdown
     * @verison    1.0.0
     * @since    1.0.0
     */
    public function register_project_templates($atts)
    {

        // Create the key used for the themes cache
        $this->cache_key = 'page_templates-' . md5(get_theme_root() . '/' . get_stylesheet());

        // Retrieve the cache list. If it doesn't exist, or it's empty prepare an array
        $templates = wp_cache_get($this->cache_key, 'themes');
        if (empty($templates)) {
            $templates = array();
        } // end if
        // Since we've updated the cache, we need to delete the old cache
        wp_cache_delete($this->cache_key, 'themes');

        // Now add our template to the list of templates by merging our templates
        // with the existing templates array from the cache.
        $templates = array_merge($templates, $this->templates);

        // Add the modified cache to allow WordPress to pick it up for listing
        // available templates
        wp_cache_add($this->cache_key, $templates, 'themes', 1800);

        return $atts;
    }

// end register_project_templates

    /**
     * Checks if the template is assigned to the page
     *
     * @version    1.0.0
     * @since    1.0.0
     */
    public function view_project_template($template)
    {

        global $post;

        // If no posts found, return to
        // avoid "Trying to get property of non-object" error
        if (!isset($post)) {
            return $template;
        }

        if (!isset($this->templates[get_post_meta($post->ID, '_wp_page_template', true)])) {
            return $template;
        } // end if

        $file = plugin_dir_path(__FILE__) . 'templates/' . get_post_meta($post->ID, '_wp_page_template', true);

        // Just to be safe, we check if the file exist first
        if (file_exists($file)) {
            return $file;
        } // end if

        return $template;
    }

// end view_project_template

    /* --------------------------------------------*
     * deactivate the plugin
     * --------------------------------------------- */

    static function deactivate($network_wide)
    {
        foreach (self::$templates as $value) {
            self::$instance->delete_template($value);
        }
    }

// end deactivate

    /* --------------------------------------------*
     * Delete Templates from Theme
     * --------------------------------------------- */

    public function delete_template($filename)
    {
        $theme_path = get_template_directory();
        $template_path = $theme_path . '/' . $filename;
        if (file_exists($template_path)) {
            unlink($template_path);
        }

        // we should probably delete the old cache
        wp_cache_delete($this->cache_key, 'themes');
    }

    /**
     * Retrieves and returns the slug of this plugin. This function should be called on an instance
     * of the plugin outside of this class.
     *
     * @return  string    The plugin's slug used in the locale.
     * @version    1.0.0
     * @since    1.0.0
     */
    public function get_locale()
    {
        return $this->plugin_slug;
    }

// end get_locale


    function get_chart_data_callback()
    {

        $result = "[";
        $post_type = $_POST['post_type'];
        $myposts = get_posts(array(
            'post_type' => $post_type,
            'numberposts' => 100,
            'post_status' => 'publish',
//            'meta_key' => 'parent_id',
//            'orderby' => 'meta_value_num',
            'order' => 'ASC'));

        $meta_prefix = "_";
        $metas = array();
        $i = 0;
        $len = count($myposts);
        foreach ($myposts as $post) {
            $content = $post->post_content;
            $meta = get_post_meta($post->ID);
            if(is_null($meta["${meta_prefix}id"])) continue;
            $feat_image = wp_get_attachment_url(get_post_thumbnail_id($post->ID));
            if ($feat_image) {
                $meta['feat_image'] = $feat_image;
            }
            array_push($metas, $meta);
        }

//        $convertToTree = $this->treeToSortedArray($this->convertToTree($metas, "${meta_prefix}id", "${meta_prefix}parent_id"));

        foreach ($metas as $key => $row) {
            $id[$key]  = $row["${meta_prefix}id"];
            $parent_id[$key] = $row["${meta_prefix}parent_id"];
        }

        array_multisort($parent_id, SORT_ASC, $id, SORT_ASC, $metas);

        foreach ($metas as $meta) {
            if (is_null($meta)) continue;
            $id = array_key_exists($meta_prefix . 'id', $meta) ? $meta[$meta_prefix . 'id'][0] : '';
            $name = array_key_exists($meta_prefix . 'name', $meta) ? $meta[$meta_prefix . 'name'][0] : '';
            $parent_id = array_key_exists($meta_prefix . 'parent_id', $meta) ? $meta[$meta_prefix . 'parent_id'][0] : 'null';
            $position = array_key_exists($meta_prefix . 'position', $meta) ? $meta[$meta_prefix . 'position'][0] : '';
            $contacts = array_key_exists($meta_prefix . 'contacts', $meta) ? $meta[$meta_prefix . 'contacts'][0] : '';
            $feat_image = array_key_exists('feat_image', $meta) ? $meta['feat_image'] : '';

            $data_element = "{ \"id\": ${id}, \"parentId\": ${parent_id}, \"name\": \"${name}\", \"position\": \"${position}\", \"contacts\": \"${contacts}\" , \"image\": \"${feat_image}\" }";

            $result .= $data_element;

            if ($i < $len - 1) {
                $result .= ',' . PHP_EOL;
            }
            $i++;
        }

        $result .= "]";

        echo $result;

        wp_die(); // this is required to terminate immediately and return a proper response
    }

    function convertToTree(
        array $flat, $idField = 'id', $parentIdField = 'parentId'
    )
    {
        $childNodesField = 'childNodes';
        $indexed = array();
        // first pass - get the array indexed by the primary id
        foreach ($flat as $row) {
            $r_id = $row[$idField][0];
            if (is_null($r_id)) continue;
            $indexed[$r_id] = $row;
            $indexed[$r_id][$childNodesField] = array();
        }

        //second pass
        $root = null;
        foreach ($indexed as $id => $row) {
            $parrent_id = $row[$parentIdField][0];
//            if(!is_null($parrent_id) || $parrent_id != 'null')
            $indexed[$parrent_id][$childNodesField][$id] = &$indexed[$id];
            file_put_contents('/tmp/debug.log', var_export($indexed, true), FILE_APPEND);
            if (!$row[$parentIdField]) {
                $root = $id;
            }
        }

        return array($root => $indexed[$root]);
    }

    function treeToSortedArray(array $tree)
    {
//        var_dump($tree);
        $result = array();
        foreach ($tree as $item) {
            if (isset($item['childNodes'])) {
                $childTree = $item['childNodes'];
                unset($item['childNodes']);
            }
            array_push($result, $item);
            if (!is_null($childTree)) {
                $treeToSortedArray = $this->treeToSortedArray($childTree);
                foreach ($treeToSortedArray as $arr_item) {
                    array_push($result, $arr_item);
                }
            }
        }

        return $result;
    }

}

// end class


TreeChartPlugin::get_instance()
?>
