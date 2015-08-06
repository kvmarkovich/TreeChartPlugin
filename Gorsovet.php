<?php

/*
Plugin Name: Gorsovet TPL
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: 1.0
Author: kmarkovych
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
} // end if

class Gorsovet_Plugin {
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
	protected $plugin_slug;

	/**
	 * A reference to an instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @var   Gorsovet_Plugin
	 */
	private static $instance;

	/**
	 * The array of templates that this plugin tracks.
	 *
	 * @var      array
	 */
	static protected $templates;

	static private $config = array(
		'plugin_name'  => 'gorsovet',
		'display_name' => 'Gorsovet',
		'submenus'     => array(
			array(
				'name'        => 'gorsovet',
				'title'       => 'Gorsovet',
				'description' => 'Dnepropetrovsk goverment structure',
				'icon'        => 'dashicons-chart-pie',
				'metas'       => array( 'id', 'parent_id', 'name', 'position', 'contacts' )
			)
			/*		,
						array(
							'name'        => 'some_name',
							'title'       => 'Some Title',
							'description' => 'Some description...',
							'icon' => 'dashicons-welcome-view-site'
						)*/
		)
	);


	/**
	 * Returns an instance of this class. An implementation of the singleton design pattern.
	 *
	 * @return   Gorsovet_Plugin    A reference to an instance of this class.
	 * @since    1.0.0
	 */
	static public function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new Gorsovet_Plugin();
		} // end if

		return self::$instance;

	} // end getInstance

	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 *
	 * @version        1.0.0
	 * @since        1.0.0
	 */
	private function __construct() {

		$this->templates     = array();
		$this->plugin_locale = 'pte';

		wp_register_script( 'getorgchart', plugins_url( '/getorgchart/getorgchart.js', __FILE__ ), array( 'jquery' ) );
		/* Регистрируем наш стиль. */
		wp_register_style( 'getorgchart', plugins_url( '/getorgchart/getorgchart.css', __FILE__ ) );


		// Grab the translations for the plugin
//		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Add a filter to the page attributes metabox to inject our template into the page template cache.
		add_filter( 'page_attributes_dropdown_pages_args', array( $this, 'register_project_templates' ) );

		// Add a filter to the save post in order to inject out template into the page cache
		add_filter( 'wp_insert_post_data', array( $this, 'register_project_templates' ) );

		// Add a filter to the template include in order to determine if the page has our template assigned and return it's path
		add_filter( 'template_include', array( $this, 'view_project_template' ) );

		// Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Add your templates to this array.
		$this->templates = array(
			'page-gorsovet.php' => __( 'Gorsovet Page Template', $this->plugin_slug )
		);

		// adding support for theme templates to be merged and shown in dropdown
		$templates = wp_get_theme()->get_page_templates();
		$templates = array_merge( $templates, $this->templates );


		add_action( 'admin_menu', array( $this, 'set_admin_menu' ) );
		add_action( 'init', array( $this, 'custom_post_type_init' ) );


		add_action( 'add_meta_boxes', array( $this, 'metabox_init' ) );
		add_action( 'save_post', array( $this, 'metabox_save' ) );

	} // end constructor


	function metabox_init() {
		foreach ( self::$config['submenus'] as $s ) {
			add_meta_box( 'metabox', $s['title'] . ' параметры поста',
				array( $this, 'metabox_showup' ), $s['name'], 'normal', 'high' );
		}

	}

	function metabox_showup( $post, $box ) {
		// получение существующих метаданных
		$data = get_post_meta( $post->ID, 'metabox_data', true );

		// скрытое поле с одноразовым кодом
		wp_nonce_field( 'metabox_action', 'metabox_nonce' );

		// поле с метаданными
		echo '<p>Метаданные: <input type="text" name="metadata_field" value="'
		     . esc_attr( $data ) . '"/></p>';
	}

	function metabox_save( $postID ) {

		// пришло ли поле наших данных?
		if ( ! isset( $_POST['metadata_field'] ) ) {
			return;
		}

		// не происходит ли автосохранение?
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// не ревизию ли сохраняем?
		if ( wp_is_post_revision( $postID ) ) {
			return;
		}

		// проверка достоверности запроса
		check_admin_referer( 'metabox_action', 'metabox_nonce' );

		// коррекция данных
		$data = sanitize_text_field( $_POST['metadata_field'] );

		// запись
		update_post_meta( $postID, 'metabox_data', $data );

	}


	/**
	 *
	 */
	function set_admin_menu() {
		/* Регистрируем страницу нашего плагина */
		$page = add_submenu_page( 'plugins.php', // Родительская страница меню
			__( self::$config['display_name'], self::$config['plugin_name'] ), // Название пункта меню
			__( self::$config['display_name'], self::$config['plugin_name'] ), // Заголовок страницы
			'manage_options', // Возможность, определяющая уровень доступа к пункту
			self::$config['plugin_name'] . '-options', // Ярлык (часть адреса) страницы плагина
			array( $this, 'my_plugin_manage_menu' ) // Функция, которая выводит страницу
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
	function my_plugin_manage_menu() {

	}

	/**
	 *
	 */
	function enqueue_scripts() {
		/*
		 * Эта функция будет вызвана только на странице плагина, подключаем наш скрипт
		 */
		wp_enqueue_script( 'getorgchart' );
		/*
			 * Эта функция будет вызвана только на странице плагина,
			   поставим наш стиль в очередь здесь */
		wp_enqueue_style( 'getorgchart' );
	}

	/**
	 * Добавляем кастомный тип записи
	 */
	public function custom_post_type_init() {
		foreach ( self::$config['submenus'] as $s ) {
			register_post_type( $s['name'], array(
				'labels'    => array(
					'name' => _x( $s['title'], $s['description'] ),
				),
				'public'    => true,
				'supports'  => array( 'title', 'thumbnail', 'custom-fields', 'comments', 'revisions' ),
				'menu_icon' => $s['icon']
			) );
		}

	}


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, basename( dirname( __FILE__ ) ) . '/languages/' );

	} // end load_plugin_textdomain

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
	public function register_project_templates( $atts ) {

		// Create the key used for the themes cache
		$this->cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

		// Retrieve the cache list. If it doesn't exist, or it's empty prepare an array
		$templates = wp_cache_get( $this->cache_key, 'themes' );
		if ( empty( $templates ) ) {
			$templates = array();
		} // end if

		// Since we've updated the cache, we need to delete the old cache
		wp_cache_delete( $this->cache_key, 'themes' );

		// Now add our template to the list of templates by merging our templates
		// with the existing templates array from the cache.
		$templates = array_merge( $templates, $this->templates );

		// Add the modified cache to allow WordPress to pick it up for listing
		// available templates
		wp_cache_add( $this->cache_key, $templates, 'themes', 1800 );

		return $atts;

	} // end register_project_templates

	/**
	 * Checks if the template is assigned to the page
	 *
	 * @version    1.0.0
	 * @since    1.0.0
	 */
	public function view_project_template( $template ) {

		global $post;

		// If no posts found, return to
		// avoid "Trying to get property of non-object" error
		if ( ! isset( $post ) ) {
			return $template;
		}

		if ( ! isset( $this->templates[ get_post_meta( $post->ID, '_wp_page_template', true ) ] ) ) {
			return $template;
		} // end if

		$file = plugin_dir_path( __FILE__ ) . 'templates/' . get_post_meta( $post->ID, '_wp_page_template', true );

		// Just to be safe, we check if the file exist first
		if ( file_exists( $file ) ) {
			return $file;
		} // end if

		return $template;

	} // end view_project_template

	/*--------------------------------------------*
	 * deactivate the plugin
	*---------------------------------------------*/
	static function deactivate( $network_wide ) {
		foreach ( self::$templates as $value ) {
			self::$instance->delete_template( $value );
		}

	} // end deactivate

	/*--------------------------------------------*
	 * Delete Templates from Theme
	*---------------------------------------------*/
	public function delete_template( $filename ) {
		$theme_path    = get_template_directory();
		$template_path = $theme_path . '/' . $filename;
		if ( file_exists( $template_path ) ) {
			unlink( $template_path );
		}

		// we should probably delete the old cache
		wp_cache_delete( $this->cache_key, 'themes' );
	}

	/**
	 * Retrieves and returns the slug of this plugin. This function should be called on an instance
	 * of the plugin outside of this class.
	 *
	 * @return  string    The plugin's slug used in the locale.
	 * @version    1.0.0
	 * @since    1.0.0
	 */
	public function get_locale() {
		return $this->plugin_slug;
	} // end get_locale

} // end class


Gorsovet_Plugin::get_instance()
?>
