<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WordPress_SEO_Tamer_Settings {

	/**
	 * The single instance of WordPress_SEO_Tamer_Settings.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * The main plugin object.
	 * @var 	object
	 * @access  public
	 * @since 	1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = array();

	public function __construct ( $parent ) {
		$this->parent = $parent;

		$this->base = 'wpseotamer_';

		// Initialise settings
		add_action( 'init', array( $this, 'init_settings' ), 11 );

		// Register plugin settings
		add_action( 'admin_init' , array( $this, 'register_settings' ) );

		// Add settings page to menu
		add_action( 'admin_menu' , array( $this, 'add_menu_item' ) );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( $this->parent->file ) , array( $this, 'add_settings_link' ) );
	}

	/**
	 * Initialise settings
	 * @return void
	 */
	public function init_settings () {
		$this->settings = $this->settings_fields();
	}

	/**
	 * Add settings page to admin menu
	 * @return void
	 */
	public function add_menu_item () {
		$manage_options_cap = apply_filters( 'wpseo_manage_options_capability', 'manage_options' );
		$page = add_submenu_page( 'wpseo_dashboard', __( 'WordPress SEO Tamer', 'wordpress-seo-tamer' ), __( 'Tamer', 'wordpress-seo-tamer' ), $manage_options_cap, 'wpseo_tamer', array( $this, 'settings_page' ) );
	}

	/**
	 * Add settings link to plugin list table
	 * @param  array $links Existing links
	 * @return array 		Modified links
	 */
	public function add_settings_link ( $links ) {
		$settings_link = '<a href="admin.php?page=wpseo_tamer">' . __( 'Settings', 'wordpress-seo-tamer' ) . '</a>';
  		array_push( $links, $settings_link );
  		return $links;
	}

	/**
	 * Build settings fields
	 * @return array Fields to be displayed on settings page
	 */
	private function settings_fields () {

		$post_types = get_post_types();
		$types = array();
		foreach( $post_types as $post_type ) {

			if( in_array( $post_type, array( 'attachment', 'revision', 'nav_menu_item' ) ) ) {
				continue;
			}

			if( ! post_type_exists( $post_type ) ) {
				continue;
			}

			$type = get_post_type_object( $post_type );

			if( isset( $type->labels->name ) && $type->labels->name ) {
				$types[ $post_type ] = $type->labels->name;
			}

		}

		$taxonomies = get_taxonomies( array(), 'objects' );
		$tax_array = array();
		foreach( $taxonomies as $tax => $taxonomy ) {

			if( in_array( $tax, array( 'nav_menu', 'post_format' ) ) ) {
				continue;
			}

			if( ! taxonomy_exists( $tax ) ) {
				continue;
			}

			if( isset( $taxonomy->labels->name ) && $taxonomy->labels->name ) {
				$tax_array[ $tax ] = $taxonomy->labels->name;
			}
		}

		$settings['general'] = array(
			'title'					=> '',
			'description'			=> '',
			'fields'				=> array(
				array(
					'id' 			=> 'posttypes_columns',
					'label'			=> __( 'Hide columns for these post types:', 'wordpress-seo-tamer' ),
					'description'	=> __( 'The WordPress SEO admin columns will be hidden for each of the selected post types.', 'wordpress-seo-tamer' ),
					'type'			=> 'checkbox_multi',
					'options'		=> $types,
					'default'		=> array_keys( $types ),
				),
				array(
					'id' 			=> 'posttypes_metabox',
					'label'			=> __( 'Hide meta box for these post types:', 'wordpress-seo-tamer' ),
					'description'	=> __( 'The WordPress SEO meta box will be hidden for each of the selected post types.', 'wordpress-seo-tamer' ),
					'type'			=> 'checkbox_multi',
					'options'		=> $types,
					'default'		=> array_keys( $types ),
				),
				array(
					'id' 			=> 'taxonomies',
					'label'			=> __( 'Hide fields for these taxonomies:', 'wordpress-seo-tamer' ),
					'description'	=> __( 'The WordPress SEO fields will be hidden for each of the selected taxonomies.', 'wordpress-seo-tamer' ),
					'type'			=> 'checkbox_multi',
					'options'		=> $tax_array,
					'default'		=> array_keys( $tax_array ),
				),
			),
		);

		$settings = apply_filters( $this->parent->_token . '_settings_fields', $settings );

		return $settings;
	}

	/**
	 * Register plugin settings
	 * @return void
	 */
	public function register_settings () {
		if( is_array( $this->settings ) ) {

			// Check posted/selected tab
			$current_section = '';
			if( isset( $_POST['tab'] ) && $_POST['tab'] ) {
				$current_section = $_POST['tab'];
			} else {
				if( isset( $_GET['tab'] ) && $_GET['tab'] ) {
					$current_section = $_GET['tab'];
				}
			}

			foreach( $this->settings as $section => $data ) {

				if( $current_section && $current_section != $section ) continue;

				// Add section to page
				add_settings_section( $section, $data['title'], array( $this, 'settings_section' ), $this->parent->_token . '_settings' );

				foreach( $data['fields'] as $field ) {

					// Validation callback for field
					$validation = '';
					if( isset( $field['callback'] ) ) {
						$validation = $field['callback'];
					}

					// Register field
					$option_name = $this->base . $field['id'];
					register_setting( $this->parent->_token . '_settings', $option_name, $validation );

					// Add field to page
					add_settings_field( $field['id'], $field['label'], array( $this->parent->admin, 'display_field' ), $this->parent->_token . '_settings', $section, array( 'field' => $field, 'prefix' => $this->base ) );
				}

				if( ! $current_section ) break;
			}
		}
	}

	public function settings_section ( $section ) {
		$html = '<p> ' . $this->settings[ $section['id'] ]['description'] . '</p>' . "\n";
		echo $html;
	}

	/**
	 * Load settings page content
	 * @return void
	 */
	public function settings_page () {

		// Build page HTML
		$html = '<div class="wrap" id="wpseo_tamer">' . "\n";
			$html .= '<h2>' . __( 'WordPress SEO Tamer Settings' , 'wordpress-seo-tamer' ) . '</h2>' . "\n";

			$tab = '';
			if( isset( $_GET['tab'] ) && $_GET['tab'] ) {
				$tab .= $_GET['tab'];
			}

			// Show page tabs
			if( is_array( $this->settings ) && 1 < count( $this->settings ) ) {

				$html .= '<h2 class="nav-tab-wrapper">' . "\n";

				$c = 0;
				foreach( $this->settings as $section => $data ) {

					// Set tab class
					$class = 'nav-tab';
					if( ! isset( $_GET['tab'] ) ) {
						if( 0 == $c ) {
							$class .= ' nav-tab-active';
						}
					} else {
						if( isset( $_GET['tab'] ) && $section == $_GET['tab'] ) {
							$class .= ' nav-tab-active';
						}
					}

					// Set tab link
					$tab_link = add_query_arg( array( 'tab' => $section ) );
					if( isset( $_GET['settings-updated'] ) ) {
						$tab_link = remove_query_arg( 'settings-updated', $tab_link );
					}

					// Output tab
					$html .= '<a href="' . $tab_link . '" class="' . esc_attr( $class ) . '">' . esc_html( $data['title'] ) . '</a>' . "\n";

					++$c;
				}

				$html .= '</h2>' . "\n";
			}

			$html .= '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";

				// Get settings fields
				ob_start();
				settings_fields( $this->parent->_token . '_settings' );
				do_settings_sections( $this->parent->_token . '_settings' );
				$html .= ob_get_clean();

				$html .= '<p class="submit">' . "\n";
					$html .= '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />' . "\n";
					$html .= '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr( __( 'Save Settings' , 'wordpress-seo-tamer' ) ) . '" />' . "\n";
				$html .= '</p>' . "\n";
			$html .= '</form>' . "\n";
		$html .= '</div>' . "\n";

		echo $html;
	}

	/**
	 * Main WordPress_SEO_Tamer_Settings Instance
	 *
	 * Ensures only one instance of WordPress_SEO_Tamer_Settings is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see WordPress_SEO_Tamer()
	 * @return Main WordPress_SEO_Tamer_Settings instance
	 */
	public static function instance ( $parent ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $parent );
		}
		return self::$_instance;
	} // End instance()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->parent->_version );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->parent->_version );
	} // End __wakeup()

}