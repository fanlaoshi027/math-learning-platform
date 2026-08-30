<?php
/**
 * Shared mask path and normalization helpers.
 *
 * @package TutorPro\Traits
 * @since 4.0.0
 */

namespace TUTOR_PRO\Traits;

use TUTOR_PRO\QuizImageStorage;

defined( 'ABSPATH' ) || exit;

/**
 * Trait QuizMaskPathHelper
 *
 * @since 4.0.0
 */
trait QuizMaskPathHelper {
	/**
	 * Normalize mask value from DB/export payload.
	 *
	 * @since 4.0.0
	 *
	 * @param string $mask Raw mask value.
	 *
	 * @return string
	 */
	protected static function normalize_quiz_mask_value( string $mask ): string {
		$mask = trim( stripslashes( $mask ) );
		$mask = trim( $mask, "\"' \t\n\r\0\x0B" );
		return str_replace( '\\/', '/', $mask );
	}

	/**
	 * Resolve stored local quiz mask value/path to absolute file path.
	 *
	 * @since 4.0.0
	 *
	 * @param string $mask_value Stored mask value/path.
	 *
	 * @return string
	 */
	protected static function resolve_quiz_mask_file_path( string $mask_value ): string {
		$mask_value = self::normalize_quiz_mask_value( $mask_value );
		if ( '' === $mask_value ) {
			return '';
		}

		$filename = QuizImageStorage::sanitize_quiz_image_filename( wp_basename( str_replace( '\\', '/', $mask_value ) ) );
		if ( '' !== $filename ) {
			$core_path = QuizImageStorage::quiz_image_stored_value_to_path( $filename );
			if ( '' !== $core_path && is_file( $core_path ) && is_readable( $core_path ) ) {
				return $core_path;
			}
		}

		$core_path = QuizImageStorage::quiz_image_stored_value_to_path( $mask_value );
		if ( '' !== $core_path && is_file( $core_path ) && is_readable( $core_path ) ) {
			return $core_path;
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return '';
		}

		$uploads_base_dir = trailingslashit( str_replace( '\\', '/', $upload_dir['basedir'] ) );

		if ( is_file( $mask_value ) && is_readable( $mask_value ) ) {
			return $mask_value;
		}

		$url_path       = wp_parse_url( $mask_value, PHP_URL_PATH );
		$url_path       = is_string( $url_path ) ? $url_path : '';
		$uploads_marker = '/wp-content/uploads/';
		$marker_pos     = '' !== $url_path ? strpos( $url_path, $uploads_marker ) : false;
		if ( false !== $marker_pos ) {
			$relative = ltrim( substr( $url_path, $marker_pos + strlen( $uploads_marker ) ), '/' );
			$relative = QuizImageStorage::normalize_uploads_relative_store_value( $relative );
			if ( '' !== $relative ) {
				$resolved = $uploads_base_dir . $relative;
				if ( is_file( $resolved ) && is_readable( $resolved ) ) {
					return $resolved;
				}
			}
		}

		$mask_path = '/' === substr( $mask_value, 0, 1 ) ? $mask_value : '';

		if ( '' === $mask_path ) {
			return '';
		}

		$uploads_marker = '/wp-content/uploads/';
		$marker_pos     = strpos( $mask_path, $uploads_marker );
		if ( false === $marker_pos ) {
			return '';
		}

		$relative = ltrim( substr( $mask_path, $marker_pos + strlen( $uploads_marker ) ), '/' );
		if ( 0 !== strpos( $relative, 'tutor/quiz-images/' ) ) {
			return '';
		}

		return $uploads_base_dir . $relative;
	}
}
