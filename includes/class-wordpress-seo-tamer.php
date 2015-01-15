<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WordPress_SEO_Tamer {

	/**
	 * The single instance of WordPress_SEO_Tamer.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * Settings class object
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * The version number.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version;

	/**
	 * The token.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token;

	/**
	 * The main plugin file.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for Javascripts.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct ( $file = '', $version = '1.0.0' ) {
		$this->_version = $version;
		$this->_token = 'wordpress_seo_tamer';

		// Load plugin environment variables
		$this->file = $file;
		$this->dir = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		register_activation_hook( $this->file, array( $this, 'install' ) );

		// Hide admin columns
		add_filter( 'wpseo_use_page_analysis', array( $this, 'remove_admin_columns' ) );

		// Remove meta box
		add_action( 'add_meta_boxes', array( $this, 'remove_meta_box' ), 99 );

		// Hide taxonomy fields
		add_action( 'plugins_loaded', array( $this, 'remove_taxonomy_fields' ), 16 );

		// Load admin JS & CSS
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );

		// Load API for generic admin functions
		if( is_admin() ) {
			$this->admin = new WordPress_SEO_Tamer_Admin_API();
		}

		// Handle localisation
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );
	} // End __construct ()

	/**
	 * Remove WP SEO admin columns from post list tables
	 * @param  boolean $show_columns [description]
	 * @return [type]                [description]
	 */
	public function remove_admin_columns ( $show_columns = true ) {
		global $typenow;

		// Ensure that correct post type is set
		if( ! isset( $typenow ) || ! $typenow ) {
			$typenow = 'post';
		}

		// Hide admin columns for selected post types
		if( $this->hide_from_post_type( $typenow, 'columns' ) ) {
			$show_columns = apply_filters( $this->_token . '_show_admin_columns', false );
		}

		return $show_columns;
	} // End remove_admin_columns ()

	/**
	 * Remove WP SEO meta box from post edit screen
	 * @return void
	 */
	public function remove_meta_box () {

		// Get all registered post types
		$post_types = get_post_types();

		// Remove meta box for selected post types
		foreach( $post_types as $post_type ) {
			if( $this->hide_from_post_type( $post_type, 'metabox' ) ) {
				remove_meta_box( 'wpseo_meta', $post_type, 'normal' );
			}
		}
	} // End remove_meta_box ()

	/**
	 * Hide WP SEO fields from taxonomy edit screen
	 * @return void
	 */
	public function remove_taxonomy_fields () {
		if ( is_admin() && ( isset( $_GET['taxonomy'] ) && $_GET['taxonomy'] ) ) {

			// Hide fields for selected taxonomies
			if( $this->hide_from_taxonomy( $_GET['taxonomy'] ) ) {
				global $wpseo_taxonomy;
				remove_action( sanitize_text_field( $_GET['taxonomy'] ) . '_edit_form', array( $wpseo_taxonomy, 'term_seo_form' ), 90 );
			}

		}
	} // End remove_taxonomy_fields ()

	/**
	 * Decide whether to hide fields from a given taxonomy
	 * @param  string  $taxonomy Taxonomy to check
	 * @return boolean          True if fields must be hidden
	 */
	public function hide_from_taxonomy ( $taxonomy = '' ) {

		if( ! $taxonomy ) return false;

		$hide_taxonomies = get_option( $this->settings->base . 'taxonomies', false );

		if( ! $hide_taxonomies || ( $hide_taxonomies && is_array( $hide_taxonomies ) && in_array( $taxonomy, $hide_taxonomies ) ) ) {
			return true;
		}

		return false;
	} // End hide_from_taxonomy ()

	/**
	 * Decide whether to hide columns/meta box from a given post type
	 * @param  string  $post_type Post type to check
	 * @param  string  $context   Context of check (columns or meta box)
	 * @return boolean            True if columns/meta box must be hidden
	 */
	public function hide_from_post_type ( $post_type = '', $context = 'columns' ) {

		if( ! $post_type || ! $context ) return false;

		$hide_posttypes = get_option( $this->settings->base . 'posttypes_' . $context, false );

		if( ! $hide_posttypes || ( $hide_posttypes && is_array( $hide_posttypes ) && in_array( $post_type, $hide_posttypes ) ) ) {
			return true;
		}

		return false;
	} // End hide_from_post_type ()

	/**
	 * Load admin CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_styles ( $hook = '' ) {
		if( isset( $_GET['page'] ) && 'wpseo_tamer' == $_GET['page'] ) {
			wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
			wp_enqueue_style( $this->_token . '-admin' );
		}
	} // End admin_enqueue_styles ()

	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_localisation () {
		load_plugin_textdomain( 'wordpress-seo-tamer', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain () {
	    $domain = 'wordpress-seo-tamer';

	    $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

	    load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

	/**
	 * Main WordPress_SEO_Tamer Instance
	 *
	 * Ensures only one instance of WordPress_SEO_Tamer is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see WordPress_SEO_Tamer()
	 * @return Main WordPress_SEO_Tamer instance
	 */
	public static function instance ( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install () {
		$this->_log_version_number();
	} // End install ()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number () {
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

}
