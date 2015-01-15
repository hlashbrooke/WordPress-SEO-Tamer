<?php
/*
 * Plugin Name: WordPress SEO Tamer
 * Version: 1.3
 * Plugin URI: https://wordpress.org/plugins/wp-seo-tamer
 * Description: Love WordPress SEO by Yoast, but hate its dashboard clutter? Well, look no further!
 * Author: Hugh Lashbrooke
 * Author URI: http://www.hughlashbrooke.com/
 * Requires at least: 4.0
 * Tested up to: 4.1
 *
 * Text Domain: wordpress-seo-tamer
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Hugh Lashbrooke
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Load plugin class files
require_once( 'includes/class-wordpress-seo-tamer.php' );
require_once( 'includes/class-wordpress-seo-tamer-settings.php' );

// Load plugin libraries
require_once( 'includes/lib/class-wordpress-seo-tamer-admin-api.php' );

/**
 * Returns the main instance of WordPress_SEO_Tamer to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object WordPress_SEO_Tamer
 */
function WordPress_SEO_Tamer () {
	$instance = WordPress_SEO_Tamer::instance( __FILE__, '1.2.0' );

	if( is_null( $instance->settings ) ) {
		$instance->settings = WordPress_SEO_Tamer_Settings::instance( $instance );
	}

	return $instance;
}

WordPress_SEO_Tamer();