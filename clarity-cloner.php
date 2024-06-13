<?php
/**
 * Plugin Name: Clarity Cloner
 * Plugin URI: https://clarity.global/
 * Description: Enables the cloning of posts between subsites in a multisite network.
 * Version: 1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Clarity Global
 * Author URI: https://clarity.global/
 * License: GPLv2 or later
 * Text Domain: cty
 * Network: true
 *
 * @package cty_cloner
 */

namespace cty_cloner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Base filepath and URL constants, without a trailing slash.
define( 'CTY_CLONER_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'CTY_CLONER_URI', plugins_url( plugin_basename( __DIR__ ) ) );

/**
 * 'spl_autoload_register' callback function.
 * Autoloads all the required plugin classes, found in the /classes directory (relative to the plugin's root).
 *
 * @param string $class The name of the class being instantiated inculding its namespaces.
 */
function autoloader( $class ) {
	// $class returns the classname including any namespaces - this removes the namespace so we can locate the class's file.
	$raw_class = explode( '\\', $class );
	$filename  = str_replace( '_', '-', strtolower( end( $raw_class ) ) );
	$filepath  = __DIR__ . '/class/class-' . $filename . '.php';

	if ( file_exists( $filepath ) ) {
		include_once $filepath;
	}
}
spl_autoload_register( __NAMESPACE__ . '\autoloader' );

/**
 * Init classes.
 */
new Cloner_Setup( __FILE__ );
new Cloner_Metaboxes();
new Cloner_Post();
