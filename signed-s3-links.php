<?php
/**
 * @package Signed-S3-links
 */

/*
Plugin Name: Signed-S3-links
Plugin URI: https://github.com/jonathanlb/signed-s3-links
Description: Present S3 documents accessible via signed links.
Version: 1.1.5
Author: Jonathan Bredin
Author URI: https://bredin.org
License: GPLv3 or later
*/

// Make sure we don't expose any info if called directly.
if ( ! function_exists( 'add_action' ) ) {
	echo 'Do not call plugins directly.';
	exit;
}

require 'vendor/autoload.php';

// define( 'SS3_DEBUG', true );
define( 'SIGNED_S3_LINKS__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
// Define the duration in seconds to memoize a shortcode operation.
define( 'SS3_SHORTCODE_TRANSIENT_SEC', 300 );

register_activation_hook( __FILE__, array( 'Signed_S3_Links', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'Signed_S3_Links', 'plugin_deactivation' ) );

require_once SIGNED_S3_LINKS__PLUGIN_DIR . 'class-signed-s3-links.php';
require_once SIGNED_S3_LINKS__PLUGIN_DIR . 'class-signed-s3-link-handler.php';

add_action( 'init', array( 'Signed_S3_Links', 'init' ) );

