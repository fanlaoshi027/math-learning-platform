<?php
/**
 * Reusable quiz mask file helpers for duplication flows.
 *
 * @package TutorPro\Traits
 * @since 4.0.0
 */

namespace TUTOR_PRO\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Trait QuizMaskDuplicator
 *
 * @since 4.0.0
 */
trait QuizMaskDuplicator {
	use QuizMaskPathHelper;

	/**
	 * Clone local quiz-images stored mask file to new file.
	 *
	 * @since 4.0.0
	 *
	 * @param string $mask_value Existing stored mask value/path.
	 * @param string $question_type Question type.
	 *
	 * @return string
	 */
	private function clone_local_quiz_mask_file( string $mask_value, string $question_type ): string {
		$source_path = self::resolve_quiz_mask_file_path( $mask_value );
		if ( '' === $source_path || ! is_file( $source_path ) || ! is_readable( $source_path ) ) {
			return '';
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return '';
		}

		$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'tutor/quiz-images/';
		if ( ! wp_mkdir_p( $target_dir ) ) {
			return '';
		}

		$ext = pathinfo( $source_path, PATHINFO_EXTENSION );
		$ext = is_string( $ext ) && '' !== $ext ? strtolower( $ext ) : 'png';

		$prefix = 'draw-mask-';
		if ( 'puzzle' === $question_type ) {
			$prefix = 'puzzle-';
		} elseif ( 'pin_image' === $question_type ) {
			$prefix = 'pin-mask-';
		}

		$target_name = $prefix . gmdate( 'Y-m-d-His' ) . '-' . wp_rand( 1000, 9999 ) . '.' . $ext;
		$target_name = wp_unique_filename( $target_dir, $target_name );
		$target_path = $target_dir . $target_name;

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Keep parity with existing duplicator behavior.
		if ( ! @copy( $source_path, $target_path ) ) {
			return '';
		}

		return wp_basename( $target_path );
	}

}
