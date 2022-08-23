<?php
/**
 * @package Signed-S3-links
 */

/*
Plugin Name: Signed-S3-links
Plugin URI: https://github.com/jonathanlb/signed-s3-links
Description: Present S3 documents accessible via signed links.
Version: 0.1.0
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

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

define( 'SS3_DEBUG', true );
define( 'SIGNED_S3_LINKS__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, array( 'Signed_S3_Links', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'Signed_S3_Links', 'plugin_deactivation' ) );

require_once SIGNED_S3_LINKS__PLUGIN_DIR . 'class-signed-s3-links.php';

add_action( 'init', array( 'Signed_S3_Links', 'init' ) );

