<?php
/**
 * Tests for the SignedS3Links plugin.
 *
 * @package Signed-S3-Links
 */

/**
 * Class SignedS3LinksTest
 */
class SignedS3LinksTest extends WP_UnitTestCase {

	/**
	 * Ensure module activation.
	 */
	public function setUp(): void {
		parent::setUp();
		Signed_S3_Links::plugin_activation();
	}

	/**
	 * Ensure module deactivation.
	 */
	public function tearDown(): void {
		parent::tearDown();
		Signed_S3_Links::plugin_deactivation();
	}

	/**
	 * Ensure that the plugin options are set upon initialization.
	 */
	public function test_default_options_set() {
		// phpunit calls Signed_S3_Links::init(), so we don't here.
		$options = get_option( 'ss3_settings' );

		$this->assertTrue( true );
		$this->assertEquals( true, Signed_S3_Links::$initialized );
		$this->assertEquals( 'latest', $options['aws_version'] );
		$this->assertEquals( 'us-east-2', $options['aws_region'] );
		$this->assertEquals( 'default', $options['aws_credentials_profile'] );
	}

	/**
	 * Ensure we extract the bucket from requests beginning with 's3://'.
	 */
	public function test_parse_bucket_with_s3_prefix() {
		$req = 's3://example.com/foo/bar/index.html';
		$this->assertEquals(
			'example.com',
			Signed_S3_Link_Handler::parse_bucket( $req )
		);
	}

	/**
	 * Ensure we extract the bucket from requests omitting 's3://'.
	 */
	public function test_parse_bucket_without_s3_prefix() {
		$req = 'example.com/foo/bar/index.html';
		$this->assertEquals(
			'example.com',
			Signed_S3_Link_Handler::parse_bucket( $req )
		);
	}

	/**
	 * Ensure we extract the filename from requests.
	 */
	public function test_parse_filename() {
		$req = 'example.com/foo/bar/index.html';
		$this->assertEquals(
			'index.html',
			Signed_S3_Link_Handler::parse_filename( $req )
		);
	}

	/**
	 * Ensure we extract nothing from requests containing only a bucket name.
	 */
	public function test_parse_filename_without_file() {
		$req = 'example.com';
		$this->assertEquals(
			'',
			Signed_S3_Link_Handler::parse_filename( $req )
		);
	}

	/**
	 * Ensure we extract nothing from requests containing only a bucket name
	 * starting with s3://.
	 */
	public function test_parse_filename_without_file_with_s3() {
		$req = 's://example.com';
		$this->assertEquals(
			'',
			Signed_S3_Link_Handler::parse_filename( $req )
		);
	}

	/**
	 * Ensure we extract the key from a path starting with s3://.
	 */
	public function test_parse_key_with_s3_prefix() {
		$req = 's3://example.com/foo/bar/index.html';
		$this->assertEquals(
			'foo/bar/index.html',
			Signed_S3_Link_Handler::parse_key( $req )
		);
	}

	/**
	 * Ensure we extract the key from a path omitting s3://.
	 */
	public function test_parse_key_without_s3_prefix() {
		$req = 'example.com/foo/bar/index.html';
		$this->assertEquals(
			'foo/bar/index.html',
			Signed_S3_Link_Handler::parse_key( $req )
		);
	}

	/**
	 * Ensure we extract the filename from a key.
	 */
	public function test_parse_filename_from_key() {
		$key = 'foo/bar/index.html';
		$this->assertEquals(
			'index.html',
			Signed_S3_Link_Handler::parse_filename_from_key( $key )
		);
	}

	/**
	 * Ensure href_shortcode uses filename for title in its absence.
	 */
	public function test_href_shortcode_without_title() {
		$atts = array( 's3://abc.s3.amazonaws.com/my_stuff/more_stuff/file.md' );
		$link = Signed_S3_Link_Handler::href_shortcode( $atts );
		$this->assertTrue(
			str_contains( $link, '>file.md</a>' )
		);
	}

	/**
	 * Ensure href_shortcode uses filename for title.
	 */
	public function test_href_shortcode_with_title() {
		$title = 'Some markdown';
		$atts  = array(
			's3://abc.s3.amazonaws.com/my_stuff/more_stuff/file.md',
			'title' => $title,
		);
		$link  = Signed_S3_Link_Handler::href_shortcode( $atts );
		$this->assertTrue(
			str_contains( $link, '>' . $title . '</a>' )
		);
	}
}
