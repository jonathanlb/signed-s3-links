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
	 * Print a signed link to an S3 file.
	 *
	 * @param $atts The shortcode attributes.  The first (unnamed) parameter
	 * should be the S3 key to list objects under.  title is an optional key
	 * to be used as the href text.
	 */
	public static function href_shortcode( $atts ) {
		$ref    = $atts[0];
		$bucket = self::parse_bucket( $ref );
		$key    = self::parse_key( $ref );

		$title = array_key_exists( 'title', $atts )
		? $atts['title']
		: self::parse_filename( $ref );

		$s3  = Signed_S3_Links::s3();
		$url = self::sign_entry( $s3, $bucket, $key );
		return '<a href="' . $url . '">' . $title . '</a>';
	}

	/**
	 * Print a directory listing with signed links to S3 files.
	 *
	 * @param $atts The shortcode attributes.  The first (unnamed) parameter
	 * should be the S3 key to list objects under.
	 */
	public static function list_dir_shortcode( $atts ) {
		try {
			$dir = $atts[0];
			Signed_S3_Links::log( 'list ' . $dir );
			$bucket  = self::parse_bucket( $dir );
			$key     = self::parse_key( $dir );
			$s3      = Signed_S3_Links::s3();
			$listing = $s3->listObjects(
				array(
					'Bucket' => $bucket,
					'Prefix' => $key,
				)
			);
			Signed_S3_Links::log( 'listing ' . $listing );

			$contents = Signed_S3_Link_Handler::filter_listing(
				$key,
				$listing['Contents']
			);

			if ( count( $contents ) > 0 ) {
				$urls = array_map(
					fn( $e ) => array(
						'name' => self::parse_filename_from_key( $e['Key'] ),
						'url'  => self::sign_entry( $s3, $dir, $e['Key'] ),
					),
					$contents
				);

				$result = '<ul>';
				foreach ( $urls as $e ) {
					// TODO title better than link
					$result .= '<li><a href="' . $e['url'] . '">' . $e['name'] . '</a>';
				}

				$result .= '</ul>';
				return $result;
			} else {
				return 'no listing for ' . $dir;
			}
		} catch ( Exception $e ) {
			Signed_S3_Links::log( 'cannot list ' . $dir . ' : ' . $e );
			return '<b>Error: </b><tt>' . $e->getMessage() . '</tt>';
		}
	}

	public static function filter_listing( $key_prefix, $listing ) {
		if ( ! $listing ) {
			return array();
		}

		// Remove directory keys.
		$contents = array_filter(
			$listing,
			fn( $e ) => $e['Size'] > 0
		);

		// Remove deeper objects than the key_prefix.
		$prefix   = $key_prefix . '/';
		$contents = array_filter(
			$contents,
			fn( $e ) => ! str_contains( str_replace( $prefix, '', $e['Key'] ), '/' )
		);

		return $contents;
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

	public static function sign_entry( $s3, $bucket, $key ) {
		$cmd = $s3->getCommand(
			'GetObject',
			array(
				'Bucket' => $bucket,
				'Key'    => $key,
			)
		);
		// TODO Get duration from option
		$request    = $s3->createPresignedRequest( $cmd, '+20 minutes' );
		$signed_url = (string) $request->getUri();
		return $signed_url;
	}
}

