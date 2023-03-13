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
	 * Print audio controls with source to the signed link.
	 *
	 * @param array $atts The shortcode attributes.  The first (unnamed)
	 * parameter should be the S3 key to list objects under.
	 * class is an optional key to be used to style the audio controls.
	 * title is an optional key to be used as the href text.
	 */
	public static function audio_shortcode( $atts ) {
		$transient_name = 'ss3_audio_' . hash( 'sha512', __FUNCTION__ . json_encode( $atts ) );
		$player         = get_transient( $transient_name );
		if ( $player ) {
			return $player;
		}

		$ref    = wp_strip_all_tags( $atts[0] );
		$bucket = self::parse_bucket( $ref );
		$key    = self::parse_key( $ref );

		$class = self::get_class_attr( $atts );
		$id    = self::get_id_attr( $atts );
		$title = self::get_title_attr( $atts );
		$style = self::get_style_attr( $atts );

		try {
			$aws_opts = self::parse_aws_options( $atts );
			$s3       = Signed_S3_Links::s3( $aws_opts );
			$url      = self::sign_entry( $s3, $bucket, $key );

			$player = '<audio controls preload="none" ' . $id . $style . $class . ' src="' . $url . '"' .
			'><a href="' . $url .
			'" target="_blank" rel="noopener noreferrer">Download audio</a></audio>';

			if ( $title ) {
				$player = '<figure><figcaption>' . $title . '</figcaption>' . $player . '</figure>';
			}

			set_transient( $transient_name, $player, SS3_SHORTCODE_TRANSIENT_SEC );
			return $player;
		} catch ( Exception $e ) {
			error_log( 'audio_shortcode error: ' . $e->getMessage() );
			return '<div><b>Cannot sign audio href. Error:</b> <tt>' . $e->getMessage() . '</tt></div>';
		}
	}

	/**
	 * Build a string of the HTML list for a directory listing.
	 *
	 * @param array $urls an array of (url, name) entries.
	 * @param array $titles a map from filename to printable titles.
	 * @param string $ul_class the list class specifer string, e.g ' class = "..." '.
	 * @param string $li_class the list-element class specifer string, e.g ' class = "..." '.
	 * @param string $href_class the link class specifer string, e.g ' class = "..." '.
	 */
	public static function build_dir_listing( $urls, $titles, $ul_class, $li_class, $href_class ) {
		$result = '<ul' . $ul_class . '>';
		foreach ( $urls as $e ) {
			$result .= '<li' . $li_class . '><a href="' . $e['url'] . '"' .
				$href_class . ' target="_blank" rel="noopener noreferrer">' .
				( $titles[ $e['name'] ] ?? $e['name'] ) .
				'</a></li>';
		}

		$result .= '</ul>';
		return $result;
	}

	/**
	 * Return the optional class specifier for an HTML element, e.g.
	 * ' class="foo bar baz" ' or '' if the attribute is missing from $atts.
	 *
	 * @param array $atts The shortcode attributes.
	 * @param string $class_key The key to lookup, defaults to "class".
	 */
	static function get_class_attr( $atts, $class_key = 'class' ) {
		if ( isset( $atts[ $class_key ] ) ) {
			return ' class="' . wp_strip_all_tags( $atts[ $class_key ] ) . '" ';
		} else {
			return '';
		}
	}

	/**
	 * Return the optional element id specifier for an HTML element, e.g.
	 * ' id="obiwan" ' or '' if the attribute is missing from $atts.
	 *
	 * @param array $atts The shortcode attributes.
	 */
	static function get_id_attr( $atts ) {
		if ( isset( $atts['id'] ) ) {
			return ' id="' . wp_strip_all_tags( $atts['id'] ) . '" ';
		} else {
			return '';
		}
	}

	/**
	 * Return the optional style from the attributes or '' if style is missing
	 * from $atts.
	 *
	 * @param array $atts The shortcode attributes.
	 * @param string $style_key The key to lookup, defaults to "style".
	 */
	static function get_style_attr( $atts, $style_key = 'style' ) {
		if ( isset( $atts[ $style_key ] ) ) {
			return ' style="' . wp_strip_all_tags( $atts[ $style_key ] . '" ' );
		} else {
			return '';
		}
	}

	/**
	 * Return the optional title from the attributes or '' if title is missing
	 * from $atts.
	 *
	 * @param array $atts The shortcode attributes.
	 */
	static function get_title_attr( $atts ) {
		if ( isset( $atts['title'] ) ) {
			return wp_strip_all_tags( $atts['title'] );
		} else {
			return '';
		}
	}

	/**
	 * Print a signed link to an S3 file.
	 *
	 * @param array $atts The shortcode attributes.  The first (unnamed)
	 * parameter should be the S3 key to list objects under.
	 * title is an optional key to be used as the href text.
	 * class is an optional key to style the href.
	 * div-class is an optional key to enclose the href with a div/ala-button with class styling options.
	 * id is an optional key to reference the href.
	 */
	public static function href_shortcode( $atts ) {
		$transient_name = 'ss3_href_' . hash( 'sha512', __FUNCTION__ . json_encode( $atts ) );
		$result         = get_transient( $transient_name );
		if ( $result ) {
			return $result;
		}

		$ref    = wp_strip_all_tags( $atts[0] );
		$bucket = self::parse_bucket( $ref );
		$key    = self::parse_key( $ref );

		$class     = self::get_class_attr( $atts );
		$div_class = self::get_class_attr( $atts, 'div_class' );
		$div_style = self::get_style_attr( $atts, 'div_style' );
		$id        = self::get_id_attr( $atts );
		$style     = self::get_style_attr( $atts );

		$title = isset( $atts['title'] )
		? wp_strip_all_tags( $atts['title'] )
		: self::parse_filename( $ref );

		try {
			$aws_opts = self::parse_aws_options( $atts );
			$s3       = Signed_S3_Links::s3( $aws_opts );
			$url      = self::sign_entry( $s3, $bucket, $key );
			$result   = '<a ' . $id . $style . $class . ' href="' . $url . '"' .
			' target="_blank" rel="noopener noreferrer">' . $title . '</a>';

			if ( $div_class || $div_style ) {
				$result = '<div ' . $div_style . $div_class . '>' . $result . '</div>';
			}

			set_transient( $transient_name, $result, SS3_SHORTCODE_TRANSIENT_SEC );
			return $result;
		} catch ( Exception $e ) {
			error_log( 'href_shortcode error: ' . $e->getMessage() );
			return '<div><b>Error signing href:</b> <tt>' . $e->getMessage() . '</tt></div>';
		}
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
		$result = $s3->getObject(
			array(
				'Bucket' => $bucket,
				'Key'    => $key,
			)
		);
		$str    = $result['Body']->getContents();
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
	 *  Other optional parameters include "div_class", "li_class", "ul_class",
	 *  and "a_class" used to style the output elements.  If no "div_class"
	 *  parameter is specified then the output will not be enclosed in a div.
	 *  The optional "id" parameter can be used to reference the resulting
	 *  list.
	 */
	public static function list_dir_shortcode( $atts ) {
		$transient_name = 'ss3_list_dir_' . hash( 'sha512', __FUNCTION__ . json_encode( $atts ) );
		$result         = get_transient( $transient_name );
		if ( $result ) {
			return $result;
		}

		try {
			$dir = wp_strip_all_tags( $atts[0] );

			$aws_opts = self::parse_aws_options( $atts );
			$bucket   = self::parse_bucket( $dir );
			$key      = self::parse_key( $dir );
			$s3       = Signed_S3_Links::s3( $aws_opts );

			$id         = self::get_id_attr( $atts );
			$div_class  = self::get_class_attr( $atts, 'div_class' );
			$ul_class   = self::get_class_attr( $atts, 'ul_class' );
			$li_class   = self::get_class_attr( $atts, 'li_class' );
			$href_class = self::get_class_attr( $atts, 'a_class' );

			$title_key = isset( $atts['titles'] ) ?
				$key . '/' . $atts['titles'] :
				'';
			$listing   = $s3->listObjects(
				array(
					'Bucket' => $bucket,
					'Prefix' => $key,
				)
			);

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

				$result = self::build_dir_listing(
					$urls,
					$titles,
					$ul_class,
					$li_class,
					$href_class
				);

				if ( $div_class ) {
					$result = '<div ' . $div_class . '>' . $result . '</div>';
				}

				set_transient( $transient_name, $result, SS3_SHORTCODE_TRANSIENT_SEC );
				return $result;
			} else {
				return 'no listing for ' . $dir;
			}
		} catch ( Exception $e ) {
			error_log( 'list_dir_shortcode "' . $dir . '" error: ' . $e->getMessage() );
			return '<b>Cannot build listing. Error: </b><tt>' . $e->getMessage() . '</tt>';
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
		$link_timeout = $options['link_timeout'];
		$request      = $s3->createPresignedRequest( $cmd, $link_timeout );
		$signed_url   = (string) $request->getUri();
		return $signed_url;
	}
}

