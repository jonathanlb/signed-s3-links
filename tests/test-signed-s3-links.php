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
		$this->assertEquals( '+60 minutes', $options['link_timeout'] );
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
	 * Ensure parsed key does not end with a slash.
	 */
	public function test_parse_key_contains_no_slash() {
		$req = 's3://example.com/foo/bar/';
		$this->assertEquals(
			'foo/bar',
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
	 * Ensure audio_shortcode uses filename for title in its absence.
	 */
	public function test_audio_shortcode_without_title() {
		$link = do_shortcode(
			'[ss3_audio s3://abc.s3.amazonaws.com/my_stuff/more_stuff/file.mp3]'
		);
		$this->assertTrue(
			str_contains( $link, 'file.mp3' )
		);
	}


	/**
	 * Ensure href_shortcode uses filename for title in its absence.
	 */
	public function test_href_shortcode_without_title() {
		$link = do_shortcode(
			'[ss3_ref s3://abc.s3.amazonaws.com/my_stuff/more_stuff/file.md]'
		);
		$this->assertTrue(
			str_contains( $link, '>file.md</a>' )
		);
	}

	/**
	 * Ensure audio_shortcode uses title.
	 */
	public function test_audio_shortcode_with_title() {
		$title = 'Sing along';
		$id    = 'my-media-player';
		$class = 'media-player-class';

		$player = do_shortcode(
			"[ss3_audio s3://abc.s3.amazonaws.com/my_stuff/more_stuff/file.mp3 title=\"$title\" id=$id class=$class]"
		);

		$this->assertTrue(
			str_contains( $player, '<figure><figcaption>' . $title . '</figcaption>' )
		);
		$this->assertTrue(
			str_contains( $player, ' id="' . $id . '"' )
		);
		$this->assertTrue(
			str_contains( $player, ' class="' . $class . '"' )
		);
	}

	/**
	 * Ensure href_shortcode uses title.
	 */
	public function test_href_shortcode_with_title() {
		$title = 'Some markdown';
		$id    = 'my-signed-link';
		$class = 'flashy-links';

		$link = do_shortcode(
			"[ss3_ref s3://abc.s3.amazonaws.com/my_stuff/more_stuff/file.md title=\"$title\" id=$id class=$class]"
		);

		$this->assertTrue(
			str_contains( $link, '>' . $title . '</a>' )
		);
		$this->assertTrue(
			str_contains( $link, ' id="' . $id . '"' )
		);
		$this->assertTrue(
			str_contains( $link, ' class="' . $class . '"' )
		);
	}

	/** Ensure that we filter directory listings to top level. */
	public function test_directory_filter_at_top() {
		$key_prefix = '';
		$s3_listing = array(
			array(
				'Size' => 0,
				'Key'  => 'some/dir/',
			),
			array(
				'Size' => 64,
				'Key'  => 'index.html',
			),
			array(
				'Size' => 128,
				'Key'  => 'some/dir/file.txt',
			),
			array(
				'Size' => 256,
				'Key'  => 'some/dir/another_file.txt',
			),
			array(
				'Size' => 0,
				'Key'  => 'some/dir/subdir/',
			),
			array(
				'Size' => 192,
				'Key'  => 'some/dir/subdir/another_file.txt',
			),
		);
		$listing    = Signed_S3_Link_Handler::filter_listing( $key_prefix, $s3_listing );
		$expected   = array(
			array(
				'Size' => 64,
				'Key'  => 'index.html',
			),
		);
		$this->assertEqualsCanonicalizing( $expected, $listing );
	}

	/** Ensure that we filter directory listings to the key. */
	public function test_directory_filter_with_key() {
		$key_prefix = 'some/dir';
		$s3_listing = array(
			array(
				'Size' => 0,
				'Key'  => 'some/dir/',
			),
			array(
				'Size' => 128,
				'Key'  => 'some/dir/file.txt',
			),
			array(
				'Size' => 256,
				'Key'  => 'some/dir/another_file.txt',
			),
			array(
				'Size' => 0,
				'Key'  => 'some/dir/subdir/',
			),
			array(
				'Size' => 192,
				'Key'  => 'some/dir/subdir/another_file.txt',
			),
		);
		$listing    = Signed_S3_Link_Handler::filter_listing( $key_prefix, $s3_listing );
		$this->assertEquals( 2, count( $listing ) );
	}

	/** Ensure that we can filter empty directory. */
	public function test_empty_directory_filter() {
		$listing = Signed_S3_Link_Handler::filter_listing( 'some/dir', array() );
		$this->assertEquals( array(), $listing );
	}

	/** Ensure we can print a directory listing. */
	public function test_build_directory_listing() {
		$urls       = array(
			0 => array(
				'name' => 'program_notes.pdf',
				'url'  => 'https://example.s2.amazonaws.com/abcdefg',
			),
			1 => array(
				'name' => 'example.pdf',
				'url'  => 'https://example.s2.amazonaws.com/bcdefgh',
			),
		);
		$titles     = array(
			'program_notes.pdf' => 'The Program',
		);
		$ul_class   = ' class="myList" ';
		$li_class   = ' class="someElement" ';
		$href_class = '';

		$listing = Signed_S3_Link_Handler::build_dir_listing(
			$urls,
			$titles,
			$ul_class,
			$li_class,
			$href_class
		);

		$this->assertTrue( str_contains( $listing, 'example.pdf' ) );
		$this->assertTrue( str_contains( $listing, 'The Program' ) );
	}
}
