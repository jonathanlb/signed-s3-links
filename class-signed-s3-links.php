<?php
/**
 * Signed_S3_Links install, init, admin
 *
 * @package    Signed-S3-links
 * @author     Jonathan Bredin <bredin@acm.org>
 * @license    https://www.gnu.org/licenses/gpl-3.0.txt GNU/GPLv3
 * @link       https://github.com/jonathanlb/signed-s3-links
 * @since      0.1.0
 */

use Aws\Credentials\CredentialProvider;

/**
 * Signed_S3_Links
 */
class Signed_S3_Links {
	/**
	 * Signal loaded.
	 *
	 * @var $initialized Flag that the plugin has completed loading.
	 */
	public static $initialized = false;

	/**
	 * Respond to admin_init events.
	 */
	public static function admin_init() {
		/** Callback to print the header of settings pane. */
		function aws_settings_callback() {
			esc_html_e( 'AWS Settings' );
		}

		/**
		 * Retrieve an option value and populate it to input field.
		 *
		 * @param array $text_input Text-input attributes.
		 */
		function text_input_callback( $text_input ) {
			$option_group = $text_input['option_group'];
			$option_id    = $text_input['option_id'];
			$option_name  = "{$option_group}[{$option_id}]";
			$options      = get_option( $option_group );
			$option_value = $options[ $option_id ] ?? '';
			?>
			<input type="text" size="32" id="<?php echo esc_attr( $option_id ); ?>"
		name="<?php echo esc_attr( $option_name ); ?>"
		value="<?php echo esc_attr( $option_value ); ?>" />
			<?php
		}

		add_settings_section(
			'aws_settings_section',
			__( 'AWS Settings' ),
			'aws_settings_callback',
			'ss3_settings'
		);
		add_settings_field(
			'aws_credentials_path',
			__( 'Path to credentials file' ),
			'text_input_callback',
			'ss3_settings',
			'aws_settings_section',
			array(
				'label_for'    => 'aws_credentials_path',
				'option_group' => 'ss3_settings',
				'option_id'    => 'aws_credentials_path',
			)
		);
		add_settings_field(
			'aws_credentials_profile',
			__( 'Credentials profile' ),
			'text_input_callback',
			'ss3_settings',
			'aws_settings_section',
			array(
				'label_for'    => 'aws_credentials_profile',
				'option_group' => 'ss3_settings',
				'option_id'    => 'aws_credentials_profile',
			)
		);
		add_settings_field(
			'link_timeout',
			__( 'Link Timeout' ),
			'text_input_callback',
			'ss3_settings',
			'aws_settings_section',
			array(
				'label_for'    => 'link_timeout',
				'option_group' => 'ss3_settings',
				'option_id'    => 'link_timeout',
			)
		);
		add_settings_field(
			'aws_region',
			__( 'Region' ),
			'text_input_callback',
			'ss3_settings',
			'aws_settings_section',
			array(
				'label_for'    => 'aws_region',
				'option_group' => 'ss3_settings',
				'option_id'    => 'aws_region',
			)
		);
		add_settings_field(
			'aws_version',
			__( 'Version' ),
			'text_input_callback',
			'ss3_settings',
			'aws_settings_section',
			array(
				'label_for'    => 'aws_version',
				'option_group' => 'ss3_settings',
				'option_id'    => 'aws_version',
			)
		);

		register_setting( 'ss3_settings', 'ss3_settings' );
	}

	/**
	 * Respond to admin_menu events.
	 */
	public static function admin_menu() {
		add_submenu_page(
			'options-general.php',
			__( 'Signed S3 Links Settings' ),
			__( 'Signed S3 Links' ),
			'manage_options',
			'ss3_settings',
			array( 'Signed_S3_Links', 'render_settings_page' )
		);
	}

	/**
	 * Build an AWS SDK object from plugin options.
	 *
	 * @param array $aws_opts configuration to override global options.
	 */
	private static function create_aws_sdk( $aws_opts ) {
		$options    = get_option( 'ss3_settings' );
		$aws_config = array(
			'region'  => isset( $aws_opts['region'] ) ? $aws_opts['region'] : $options['aws_region'],
			'version' => $options['aws_version'],
		);

		$profile          = $options['aws_credentials_profile'];
		$credentials_path = $options['aws_credentials_path'];

		if ( $credentials_path ) {
			if ( ! str_starts_with( $credentials_path, '/' ) ) {
				$credentials_path = SIGNED_S3_LINKS__PLUGIN_DIR . $credentials_path;
			}
			$provider = CredentialProvider::ini( $profile, $credentials_path );
			$provider = CredentialProvider::memoize( $provider );

			self::log( array( 'new AWS SDK...', $aws_config ) );
			self::log( array( 'reading credentials from', $options['aws_credentials_path'], $credentials_path, $profile ) );
			$aws_config['credentials'] = $provider;
		} else {
			$aws_config['profile'] = $profile;
			self::log( array( 'new AWS SDK', $aws_config ) );
		}

		$sdk = new Aws\Sdk( $aws_config );
		return $sdk;
	}

	/**
	 * Setup hooks if necessary
	 */
	public static function init() {
		if ( self::$initialized ) {
			return;
		}

		self::log( 'init_hooks' );
		self::$initialized = true;
		add_action( 'admin_init', array( 'Signed_S3_Links', 'admin_init' ) );
		add_action( 'admin_menu', array( 'Signed_S3_Links', 'admin_menu' ) );
		add_action( 'wp_enqueue_scripts', array( 'Signed_S3_Links', 'wp_enqueue_scripts' ) );

		add_shortcode( 'ss3_dir', array( 'Signed_S3_Link_Handler', 'list_dir_shortcode' ) );
		add_shortcode( 'ss3_ref', array( 'Signed_S3_Link_Handler', 'href_shortcode' ) );
	}

	/**
	 * Styled after Akismet::log.
	 *
	 * @param mixed $debug_msg  String or object to print out during debug.
	 */
	public static function log( $debug_msg ) {
		// call during unit-test debug fwrite(STDERR, print_r( $debug_msg, true ) . "\n" ).
		if ( apply_filters( 'ss3_debug_log', defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && defined( 'SS3_DEBUG' ) && SS3_DEBUG ) ) {
			error_log( print_r( compact( 'debug_msg' ), true ) );
		}
	}

	/**
	 * Respond to plugin_activation events.
	 */
	public static function plugin_activation() {
		self::log( 'plugin_activation' );

		// Write default settings.
		$options = get_option( 'ss3_settings' );
		if ( ! isset( $options['aws_credentials_path'] ) ) {
			$options['aws_credentials_path'] = '';
		}
		if ( ! isset( $options['aws_credentials_profile'] ) ) {
			$options['aws_credentials_profile'] = 'default';
		}
		if ( ! isset( $options['aws_region'] ) ) {
			$options['aws_region'] = 'us-east-2';
		}
		if ( ! isset( $options['aws_version'] ) ) {
			$options['aws_version'] = 'latest';
		}
		if ( ! isset( $options['link_timeout'] ) ) {
			$options['link_timeout'] = '+60 minutes';
		}

		add_option( 'ss3_settings', $options );
	}

	/**
	 * Respond to plugin_deactivation events.
	 */
	public static function plugin_deactivation() {
		self::log( 'plugin_deactivation' );
		delete_option( 'ss3_settings' );
	}

	/**
	 * Build the plugin settings page.
	 */
	public static function render_settings_page() {
		?>
<div class="wrap">
	<h2><?php esc_html_e( 'Signed S3 Links Settings' ); ?></h2>

	<form method="post" action="options.php">
		<?php
			settings_fields( 'ss3_settings' );
			do_settings_sections( 'ss3_settings' );
			submit_button();
		?>
	</form>

</div><!-- /.wrap -->
		<?php
	}

	/**
	 * Provide an S3 client.
	 *
	 * @param array $aws_opts configuration to override global options.
	 */
	public static function s3( $aws_opts ) {
		$aws_sdk   = self::create_aws_sdk( $aws_opts );
		$s3_client = $aws_sdk->createS3();
		return $s3_client;
	}

	/**
	 * Respond to wp_enqueue_scripts events.
	 */
	public static function wp_enqueue_scripts() {
		self::log( 'enqueue_scripts' );
		wp_register_style( 'signed-s3-links', plugins_url( 'style.css', __FILE__ ), array(), '0.1.0' );
		wp_enqueue_style( 'signed-s3-links' );
	}

}
?>
