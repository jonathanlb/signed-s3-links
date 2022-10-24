<?php
/**
 * Signed_S3_Link_Handler query S3 for objects and format results
 *
 * @package    Signed-S3-links
 * @author     Jonathan Bredin <bredin@acm.org>
 * @license    https://www.gnu.org/licenses/gpl-3.0.txt GNU/GPLv3
 * @link       https://github.com/jonathanlb/signed-s3-links
 * @since      0.1.0
 */

/**
 * Class Signed_S3_Link_Handler
 */
class Signed_S3_Link_Handler {

	/**
	 * Build a string of the HTML list for a directory listing.
	 *
	 * @param array $urls an array of (url, name) entries.
	 * @param array $titles a map from filename to printable titles.
	 */
	public static function build_dir_listing( $urls, $titles ) {
		$result = '<ul>';
		foreach ( $urls as $e ) {
			$result .= '<li><a href="' . $e['url'] . '">' .
				( $titles[ $e['name'] ] ?? $e['name'] ) .
				'</a></li>';
		}

		$result .= '</ul>';
		return $result;
	}

	/**
	 * Print a signed link to an S3 file.
	 *
	 * @param array $atts The shortcode attributes.  The first (unnamed)
	 * parameter should be the S3 key to list objects under.
	 * title is an optional key to be used as the href text.
	 */
	public static function href_shortcode( $atts ) {
		$ref    = wp_strip_all_tags( $atts[0] );
		$bucket = self::parse_bucket( $ref );
		$key    = self::parse_key( $ref );

		$title = isset( $atts['title'] )
		? wp_strip_all_tags( $atts['title'] )
		: self::parse_filename( $ref );

		$aws_opts = self::parse_aws_options( $atts );

		$s3  = Signed_S3_Links::s3( $aws_opts );
		$url = self::sign_entry( $s3, $bucket, $key );
		return '<a href="' . $url . '">' . $title . '</a>';
	}

	/**
	 * Read and parse an S3 object containing a JSON file representing a
	 * dictionary mapping filenames to titles.
	 *
	 * @param array  $s3 @see Aws\AwsClient.
	 * @param string $bucket the bucket containing the dictionary key.
	 * @param string $key The key under the bucket pointing to the title
	 * dictionary.
	 */
	public static function fetch_titles( $s3, $bucket, $key ) {
		Signed_S3_Links::log( array( 'fetching titles ', $bucket, $key ) );
		$result = $s3->getObject(
			array(
				'Bucket' => $bucket,
				'Key'    => $key,
			)
		);
		$str    = $result['Body']->getContents();
		Signed_S3_Links::log( array( 'fetched titles ', $bucket, $key, $str ) );
		return json_decode( $str, true );
	}

	/**
	 * Filter the objects to display by omitting directory/keys-without-content
	 * and keys more than one level beneath $key_prefix.
	 *
	 * @param string $key_prefix the key to list objects under.
	 * @param array  $listing an array of object entries from @see Aws\S3Client\ListEntries().
	 * @param string $titles_key filename containing json filename to titles map.
	 */
	public static function filter_listing( $key_prefix, $listing, $titles_key = null ) {
		Signed_S3_Links::log( 'filter ' . $key_prefix );
		if ( ! $listing ) {
			return array();
		}

		// Remove directory keys.
		$contents = array_filter(
			$listing,
			fn( $e ) => $e['Size'] > 0 && $e['Key'] !== $titles_key
		);

		// Remove deeper objects than the key_prefix.
		$prefix   = $key_prefix . (
			strlen( $key_prefix ) > 0 && ! str_ends_with( $key_prefix, '/' ) ?
			'/' :
			'' );
		$contents = array_filter(
			$contents,
			fn( $e ) => ! str_contains( str_replace( $prefix, '', $e['Key'] ), '/' )
		);

		return $contents;
	}


	/**
	 * Print a directory listing with signed links to S3 files.
	 *
	 * @param array $atts The shortcode attributes.
	 *  The first (unnamed) parameter should be the S3 key to list objects under.
	 *  An optional parameter "titles" refers to an S3 link to a JSON file
	 *  containing of a dictionary mapping filenames to titles to display.
	 */
	public static function list_dir_shortcode( $atts ) {
		try {
			$dir = wp_strip_all_tags( $atts[0] );
			Signed_S3_Links::log( 'list ' . $dir );
			$aws_opts  = self::parse_aws_options( $atts );
			$bucket    = self::parse_bucket( $dir );
			$key       = self::parse_key( $dir );
			$s3        = Signed_S3_Links::s3( $aws_opts );
			$title_key = isset( $atts['titles'] ) ?
				$key . '/' . $atts['titles'] :
				'';
			$listing   = $s3->listObjects(
				array(
					'Bucket' => $bucket,
					'Prefix' => $key,
				)
			);
			Signed_S3_Links::log( array( 'listing ', $bucket, $key, $listing['Contents'] ?? null ) );

			$contents = self::filter_listing(
				$key,
				$listing['Contents'],
				$title_key
			);

			if ( count( $contents ) > 0 ) {
				$urls = array_map(
					fn( $e ) => array(
						'name' => self::parse_filename_from_key( $e['Key'] ),
						'url'  => self::sign_entry( $s3, $bucket, $e['Key'] ),
					),
					$contents
				);

				$titles = $title_key ?
					self::fetch_titles( $s3, $bucket, $title_key ) :
					array();
				Signed_S3_Links::log( array( 'titles ', $titles ) );

				return self::build_dir_listing( $urls, $titles );
			} else {
				return 'no listing for ' . $dir;
			}
		} catch ( Exception $e ) {
			Signed_S3_Links::log( 'cannot list ' . $dir . ' : ' . $e );
			return '<b>Error: </b><tt>' . $e->getMessage() . '</tt>';
		}
	}

	/**
	 * Extract AWS parameters overriding the global defaults, such as region
	 * or timeout.
	 *
	 * @param array $atts Attributes passed into the shortcodes.
	 */
	private static function parse_aws_options( $atts ) {
		$region   = isset( $atts['region'] )
		? wp_strip_all_tags( $atts['region'] )
		: null;
		$aws_opts = array( 'region' => $region );

		return $aws_opts;
	}

	/**
	 * Extract the bucket from an href or directory listing request.
	 *
	 * @param string $request Request in the form of [s3://?]<bucket_name>/<key_name>.
	 */
	public static function parse_bucket( $request ) {
		$m = array();
		preg_match( '/^(s3:\/\/)?([^\/]*)/', $request, $m );
		if ( count( $m ) !== 3 ) {
			return '';
		} else {
			return $m[2];
		}
	}

	/**
	 * Extract the filename from an href request.
	 *
	 * @param string $request Request in the form of [s3://?]<bucket_name>/<key_name>.
	 */
	public static function parse_filename( $request ) {
		$m = array();
		if ( str_starts_with( $request, 's://' ) ) {
				preg_match( '/^s3:\/\/.*\/([^\/]*)$/', $request, $m );
			if ( count( $m ) !== 2 ) {
				return '';
			} else {
				return $m[1];
			}
		} else {
			preg_match( '/\/([^\/]*)$/', $request, $m );
			if ( count( $m ) !== 2 ) {
				return '';
			} else {
				return $m[1];
			}
		}
	}

	/**
	 * Extract the filename from an S3 key.
	 *
	 * @param string $key S3 object key.
	 */
	public static function parse_filename_from_key( $key ) {
		$m = array();
		preg_match( '/([^\/]*)$/', $key, $m );
		if ( count( $m ) !== 2 ) {
			return '';
		} else {
			return $m[1];
		}
	}

	/**
	 * Extract the key from an href or directory listing request.
	 *
	 * @param string $request Request in the form of [s3://?]<bucket_name>/<key_name>.
	 */
	public static function parse_key( $request ) {
		$m = array();
		preg_match( '/^(s3:\/\/)?[^\/]*\/(.*)/', $request, $m );
		if ( count( $m ) !== 3 ) {
			return '';
		} else {
			return preg_replace( '/\/$/', '', $m[2] );
		}
	}

	/**
	 * Format a signed URI for the S3 object.
	 *
	 * @param array  $s3 @see Aws\AwsClient.
	 * @param string $bucket the bucket containing the object.
	 * @param string $key the key string for the object.
	 */
	public static function sign_entry( $s3, $bucket, $key ) {
		$cmd = $s3->getCommand(
			'GetObject',
			array(
				'Bucket' => $bucket,
				'Key'    => $key,
			)
		);

		$options      = get_option( 'ss3_settings' );
		$link_timeout = $options['link_timeout'] || '+60 minutes';
		$request      = $s3->createPresignedRequest( $cmd, $link_timeout );
		$signed_url   = (string) $request->getUri();
		return $signed_url;
	}
}

