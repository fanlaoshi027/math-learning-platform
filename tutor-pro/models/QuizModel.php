<?php
/**
 * Quiz Model (Tutor Pro).
 *
 * Pro-specific quiz logic; mirrors tutor free plugin naming (QuizModel).
 * Holds file handling and save logic for question types provided by Pro (e.g. draw_image).
 *
 * @package TUTOR_PRO
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

namespace TutorPro\Models;

defined( 'ABSPATH' ) || exit;

use TUTOR\Dashboard;
use Tutor\Helpers\QueryHelper;
use Tutor\Helpers\UrlHelper;
use Tutor\Models\QuizModel as TutorQuizModel;
use TUTOR_PRO\QuizImageStorage;

/**
 * Class QuizModel
 *
 * @since 4.0.0
 */
class QuizModel {
	/**
	 * Get quiz attempt details URL by context.
	 *
	 * @since 4.0.0
	 *
	 * @param int    $attempt_id Attempt ID.
	 * @param string $context    URL context (admin or frontend).
	 *
	 * @return string
	 */
	public static function get_attempt_details_url( $attempt_id, $context = 'admin' ) {
		$attempt_id = (int) $attempt_id;
		$url        = tutor_utils()->tutor_dashboard_url( 'quiz-attempts/?attempt_id=' . $attempt_id );

		if ( 'student' === $context ) {
			$url = UrlHelper::add_query_params(
				tutor_utils()->tutor_dashboard_url( Dashboard::MY_QUIZ_ATTEMPTS_SUBPAGE_SLUG ),
				array(
					'attempt_id' => $attempt_id,
					'action'     => 'view_details',
				)
			);
		}

		if ( 'admin' === $context ) {
			$url = admin_url( 'admin.php?page=tutor_quiz_attempts&view_quiz_attempt_id=' . $attempt_id );
		}

		return $url;
	}

	/**
	 * Filter callback: add draw_image attempt file paths for deletion.
	 * Registered on tutor_quiz/attempt_file_paths_for_deletion.
	 *
	 * @since 4.0.0
	 *
	 * @param string[] $file_paths  Paths collected so far.
	 * @param int[]    $attempt_ids Quiz attempt IDs being deleted.
	 *
	 * @return string[]
	 */
	public static function add_draw_image_attempt_file_paths( $file_paths, $attempt_ids ) {
		$file_paths = array_merge( (array) $file_paths, self::get_draw_image_file_paths_for_attempts( $attempt_ids ) );
		return array_merge( $file_paths, self::get_puzzle_snapshot_file_paths_for_attempts( $attempt_ids ) );
	}

	/**
	 * Filter callback: add quiz-level file paths for deletion (e.g. instructor masks).
	 * Registered on tutor_quiz_quiz_file_paths_for_deletion.
	 *
	 * @since 4.0.0
	 *
	 * @param string[] $file_paths Paths collected so far.
	 * @param int      $quiz_id    Quiz post ID.
	 *
	 * @return string[]
	 */
	public static function add_quiz_file_paths_for_deletion( $file_paths, $quiz_id ) {
		$file_paths = array_merge( (array) $file_paths, self::get_draw_image_file_paths_for_quiz( $quiz_id ) );
		$file_paths = array_merge( $file_paths, self::get_pin_image_file_paths_for_quiz( $quiz_id ) );
		return array_merge( $file_paths, self::get_puzzle_file_paths_for_quiz( $quiz_id ) );
	}

	/**
	 * Get file paths of draw_image attempt mask files for given attempt(s).
	 *
	 * @since 4.0.0
	 *
	 * @param int[] $attempt_ids Array of quiz attempt IDs.
	 *
	 * @return string[] Array of absolute file paths.
	 */
	public static function get_draw_image_file_paths_for_attempts( array $attempt_ids ) {
		$paths = array();
		if ( empty( $attempt_ids ) ) {
			return $paths;
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return $paths;
		}

		$uploads_base_url = trailingslashit( $upload_dir['baseurl'] );
		$uploads_base_dir = trailingslashit( $upload_dir['basedir'] );
		$quiz_image_url   = $uploads_base_url . QuizImageStorage::QUIZ_IMAGES_SUBDIR . '/';

		$attempt_ids = array_filter( array_map( 'absint', $attempt_ids ) );
		if ( empty( $attempt_ids ) ) {
			return $paths;
		}

		$response = QueryHelper::get_joined_data(
			'tutor_quiz_attempt_answers AS a',
			array(
				array(
					'type'  => 'INNER',
					'table' => 'tutor_quiz_questions AS q',
					'on'    => 'a.question_id = q.question_id',
				),
			),
			array( 'a.given_answer' ),
			array(
				'a.quiz_attempt_id' => $attempt_ids,
				'q.question_type'   => TutorQuizModel::QUESTION_TYPE_DRAW_IMAGE,
			),
			array(),
			'',
			-1,
			0
		);

		$rows = isset( $response['results'] ) && is_array( $response['results'] ) ? $response['results'] : array();
		if ( empty( $rows ) ) {
			return $paths;
		}

		return self::resolve_draw_image_urls_to_paths( $rows, 'given_answer', $uploads_base_url, $uploads_base_dir, $quiz_image_url );
	}

	/**
	 * Resolve tutor/quiz-images URLs from rows to absolute file paths.
	 *
	 * @since 4.0.0
	 *
	 * @param array  $rows              Rows with a URL in the given property.
	 * @param string $url_property      Property name on each row.
	 * @param string $uploads_base_url  Base URL for uploads (trailingslashit).
	 * @param string $uploads_base_dir  Base dir for uploads (trailingslashit).
	 * @param string $quiz_image_url    Quiz-image URL prefix.
	 *
	 * @return string[] Absolute file paths.
	 */
	private static function resolve_draw_image_urls_to_paths( array $rows, $url_property, $uploads_base_url, $uploads_base_dir, $quiz_image_url ) {
		unset( $uploads_base_url, $quiz_image_url );

		$paths       = array();
		$quiz_prefix = trailingslashit( trailingslashit( $uploads_base_dir ) . QuizImageStorage::QUIZ_IMAGES_SUBDIR );

		foreach ( $rows as $row ) {
			$stored = is_string( $row->$url_property ?? '' ) ? trim( $row->$url_property ) : '';
			if ( '' === $stored ) {
				continue;
			}

			$path = QuizImageStorage::quiz_image_stored_value_to_path( $stored );
			if ( '' === $path || ! is_readable( $path ) ) {
				continue;
			}

			if ( 0 !== strpos( $path, $quiz_prefix ) ) {
				continue;
			}

			$paths[] = $path;
		}
		return $paths;
	}

	/**
	 * Get file paths of draw_image instructor mask files for a quiz.
	 *
	 * @since 4.0.0
	 *
	 * @param int $quiz_id Quiz post ID.
	 *
	 * @return string[] Array of absolute file paths.
	 */
	public static function get_draw_image_file_paths_for_quiz( $quiz_id ) {
		$paths   = array();
		$quiz_id = (int) $quiz_id;
		if ( $quiz_id <= 0 ) {
			return $paths;
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return $paths;
		}

		$uploads_base_url = trailingslashit( $upload_dir['baseurl'] );
		$uploads_base_dir = trailingslashit( $upload_dir['basedir'] );
		$quiz_image_url   = $uploads_base_url . QuizImageStorage::QUIZ_IMAGES_SUBDIR . '/';

		$draw_image_questions = QueryHelper::get_all(
			'tutor_quiz_questions',
			array(
				'quiz_id'       => $quiz_id,
				'question_type' => TutorQuizModel::QUESTION_TYPE_DRAW_IMAGE,
			),
			'question_id',
			-1
		);

		if ( empty( $draw_image_questions ) ) {
			return $paths;
		}

		$question_ids = array_map(
			function ( $row ) {
				return (int) $row->question_id;
			},
			$draw_image_questions
		);

		$question_answers = QueryHelper::get_all(
			'tutor_quiz_question_answers',
			array(
				'belongs_question_id'   => $question_ids,
				'belongs_question_type' => TutorQuizModel::QUESTION_TYPE_DRAW_IMAGE,
			),
			'answer_id',
			-1
		);

		return self::resolve_draw_image_urls_to_paths( $question_answers, 'answer_two_gap_match', $uploads_base_url, $uploads_base_dir, $quiz_image_url );
	}

	/**
	 * Get file paths of pin_image instructor mask files for a quiz.
	 *
	 * @since 4.0.0
	 *
	 * @param int $quiz_id Quiz post ID.
	 *
	 * @return string[] Array of absolute file paths.
	 */
	public static function get_pin_image_file_paths_for_quiz( $quiz_id ) {
		$paths   = array();
		$quiz_id = (int) $quiz_id;
		if ( $quiz_id <= 0 ) {
			return $paths;
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return $paths;
		}

		$uploads_base_url = trailingslashit( $upload_dir['baseurl'] );
		$uploads_base_dir = trailingslashit( $upload_dir['basedir'] );
		$quiz_image_url   = $uploads_base_url . QuizImageStorage::QUIZ_IMAGES_SUBDIR . '/';

		$pin_image_questions = QueryHelper::get_all(
			'tutor_quiz_questions',
			array(
				'quiz_id'       => $quiz_id,
				'question_type' => TutorQuizModel::QUESTION_TYPE_PIN_IMAGE,
			),
			'question_id',
			-1
		);

		if ( empty( $pin_image_questions ) ) {
			return $paths;
		}

		$question_ids = array_map(
			function ( $row ) {
				return (int) $row->question_id;
			},
			$pin_image_questions
		);

		$question_answers = QueryHelper::get_all(
			'tutor_quiz_question_answers',
			array(
				'belongs_question_id'   => $question_ids,
				'belongs_question_type' => TutorQuizModel::QUESTION_TYPE_PIN_IMAGE,
			),
			'answer_id',
			-1
		);

		return self::resolve_draw_image_urls_to_paths( $question_answers, 'answer_two_gap_match', $uploads_base_url, $uploads_base_dir, $quiz_image_url );
	}

	/**
	 * Get file paths of puzzle instructor files for a quiz.
	 *
	 * @since 4.0.0
	 *
	 * @param int $quiz_id Quiz post ID.
	 *
	 * @return string[] Array of absolute file paths.
	 */
	public static function get_puzzle_file_paths_for_quiz( $quiz_id ) {
		$paths   = array();
		$quiz_id = (int) $quiz_id;
		if ( $quiz_id <= 0 ) {
			return $paths;
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return $paths;
		}

		$uploads_base_url = trailingslashit( $upload_dir['baseurl'] );
		$uploads_base_dir = trailingslashit( $upload_dir['basedir'] );
		$quiz_image_url   = $uploads_base_url . QuizImageStorage::QUIZ_IMAGES_SUBDIR . '/';

		$puzzle_questions = QueryHelper::get_all(
			'tutor_quiz_questions',
			array(
				'quiz_id'       => $quiz_id,
				'question_type' => TutorQuizModel::QUESTION_TYPE_PUZZLE,
			),
			'question_id',
			-1
		);

		if ( empty( $puzzle_questions ) ) {
			return $paths;
		}

		$question_ids = array_map(
			function ( $row ) {
				return (int) $row->question_id;
			},
			$puzzle_questions
		);

		$question_answers = QueryHelper::get_all(
			'tutor_quiz_question_answers',
			array(
				'belongs_question_id'   => $question_ids,
				'belongs_question_type' => TutorQuizModel::QUESTION_TYPE_PUZZLE,
			),
			'answer_id',
			-1
		);

		return self::resolve_draw_image_urls_to_paths( $question_answers, 'answer_two_gap_match', $uploads_base_url, $uploads_base_dir, $quiz_image_url );
	}

	/**
	 * Save draw-image or pin-image question mask to uploads and return stored value.
	 *
	 * Returns a basename (tutor/quiz-images) or uploads-relative path; no full URL.
	 *
	 * @since 4.0.0
	 *
	 * @param string $mask_value    Base64 image string or stored mask value.
	 * @param string $question_type Question type (draw_image or pin_image).
	 * @param array  $context       Optional save context (e.g. answer data status).
	 *
	 * @return string Basename, uploads-relative path, or original when not a mask save.
	 */
	public static function save_quiz_draw_image_mask( $mask_value, $question_type = null, $context = array() ) {
		if ( TutorQuizModel::QUESTION_TYPE_DRAW_IMAGE !== $question_type && TutorQuizModel::QUESTION_TYPE_PIN_IMAGE !== $question_type && TutorQuizModel::QUESTION_TYPE_PUZZLE !== $question_type ) {
			return $mask_value;
		}

		$value = is_string( $mask_value ) ? trim( $mask_value ) : '';
		if ( '' === $value ) {
			return $value;
		}
		$data_status = '';
		if ( is_array( $context ) && isset( $context['data_status'] ) ) {
			$data_status = sanitize_key( (string) $context['data_status'] );
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return $value;
		}

		// Expect data:image/*;base64,... from the canvas.
		if ( ! preg_match( '#^data:image/(\w+);base64,(.+)$#is', $value, $m ) ) {
			$stored_value = self::normalize_quiz_mask_stored_value( $value, $upload_dir );
			if ( '' !== $stored_value ) {
				if ( 'new' !== $data_status ) {
					return $stored_value;
				}

				$cloned_name = self::clone_quiz_image_file_from_stored_value( $stored_value, $question_type );
				return '' !== $cloned_name ? $cloned_name : $stored_value;
			}

			/*
			 * Keep legacy behavior for unknown values (e.g. custom external URLs)
			 * instead of persisting an empty string and losing the mask reference.
			 */
			return $value;
		}

		$subdir_path       = QuizImageStorage::QUIZ_IMAGES_SUBDIR;
		$filter_upload_dir = function ( $uploads ) use ( $subdir_path ) {
			$uploads['path']   = trailingslashit( $uploads['basedir'] ) . $subdir_path;
			$uploads['url']    = trailingslashit( $uploads['baseurl'] ) . $subdir_path;
			$uploads['subdir'] = '/' . $subdir_path;
			return $uploads;
		};

		add_filter( 'upload_dir', $filter_upload_dir, 10, 1 );
		if ( TutorQuizModel::QUESTION_TYPE_PUZZLE === $question_type ) {
			$prefix = 'puzzle-';
		} elseif ( TutorQuizModel::QUESTION_TYPE_PIN_IMAGE === $question_type ) {
			$prefix = 'pin-mask-';
		} else {
			$prefix = 'draw-mask-';
		}

		$basename = $prefix . gmdate( 'Y-m-d-His' ) . '-' . wp_rand( 1000, 9999 ) . '.png';
		try {
			$result = tutor_utils()->upload_base64_image( $value, $basename, false );
			return isset( $result->title ) ? (string) $result->title : wp_basename( (string) ( $result->url ?? '' ) );
		} catch ( \Exception $e ) {
			return $value;
		} finally {
			remove_filter( 'upload_dir', $filter_upload_dir, 10 );
		}
	}

	/**
	 * Normalize a raw mask value into stored value format used by QuizImageStorage.
	 *
	 * Supports basename, uploads-relative, uploads URL, and absolute uploads path.
	 *
	 * @since 4.0.0
	 *
	 * @param string $value      Raw mask value.
	 * @param array  $upload_dir Output from wp_upload_dir().
	 *
	 * @return string
	 */
	private static function normalize_quiz_mask_stored_value( $value, array $upload_dir ) {
		$value = is_string( $value ) ? trim( stripslashes( $value ) ) : '';
		$value = trim( $value, "\"' \t\n\r\0\x0B" );
		if ( '' === $value ) {
			return '';
		}

		$filename = QuizImageStorage::sanitize_quiz_image_filename( $value );
		if ( '' !== $filename ) {
			return $filename;
		}

		/*
		 * Keep duplication domain/protocol agnostic by extracting only basename
		 * from URL/path-like values and validating against quiz-image filename rules.
		 */
		$basename = wp_basename( str_replace( '\\', '/', $value ) );
		$filename = QuizImageStorage::sanitize_quiz_image_filename( $basename );
		if ( '' !== $filename ) {
			return $filename;
		}

		$relative = QuizImageStorage::normalize_uploads_relative_store_value( $value );
		if ( '' !== $relative ) {
			return $relative;
		}

		$base_url = trailingslashit( (string) ( $upload_dir['baseurl'] ?? '' ) );
		if ( '' !== $base_url && 0 === strpos( $value, $base_url ) ) {
			$relative = ltrim( substr( $value, strlen( $base_url ) ), '/' );
			$relative = QuizImageStorage::normalize_uploads_relative_store_value( $relative );
			if ( '' !== $relative ) {
				return $relative;
			}
		}

		$url_path = wp_parse_url( $value, PHP_URL_PATH );
		$url_path = is_string( $url_path ) ? $url_path : '';
		if ( '' !== $url_path ) {
			$uploads_marker = '/wp-content/uploads/';
			$marker_pos     = strpos( $url_path, $uploads_marker );
			if ( false !== $marker_pos ) {
				$relative = ltrim( substr( $url_path, $marker_pos + strlen( $uploads_marker ) ), '/' );
				$relative = QuizImageStorage::normalize_uploads_relative_store_value( $relative );
				if ( '' !== $relative ) {
					return $relative;
				}
			}
		}

		$base_dir = trailingslashit( str_replace( '\\', '/', (string) ( $upload_dir['basedir'] ?? '' ) ) );
		$path     = str_replace( '\\', '/', $value );
		if ( '' !== $base_dir && 0 === strpos( $path, $base_dir ) ) {
			$relative = ltrim( substr( $path, strlen( $base_dir ) ), '/' );
			$relative = QuizImageStorage::normalize_uploads_relative_store_value( $relative );
			if ( '' !== $relative ) {
				return $relative;
			}
		}

		return '';
	}

	/**
	 * Clone an existing stored mask value to a new file in tutor/quiz-images.
	 *
	 * This avoids sharing the same physical mask file between Content Bank and
	 * quiz-builder copies so deleting one does not remove the other.
	 *
	 * @since 4.0.0
	 *
	 * @param string $stored_value  Existing stored value (basename or uploads-relative).
	 * @param string $question_type Question type.
	 *
	 * @return string New basename or empty on failure.
	 */
	private static function clone_quiz_image_file_from_stored_value( $stored_value, $question_type ) {
		$stored_value = is_string( $stored_value ) ? trim( $stored_value ) : '';
		if ( '' === $stored_value ) {
			return '';
		}

		$resolved_value = $stored_value;
		if ( '' === $resolved_value ) {
			return '';
		}

		$source_path = QuizImageStorage::quiz_image_stored_value_to_path( $resolved_value );
		if ( '' === $source_path || ! is_file( $source_path ) || ! is_readable( $source_path ) ) {
			return '';
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return '';
		}

		$target_dir = trailingslashit( $upload_dir['basedir'] ) . QuizImageStorage::QUIZ_IMAGES_SUBDIR . '/';
		if ( ! wp_mkdir_p( $target_dir ) ) {
			return '';
		}

		$ext = pathinfo( $source_path, PATHINFO_EXTENSION );
		$ext = is_string( $ext ) && '' !== $ext ? strtolower( $ext ) : 'png';

		if ( TutorQuizModel::QUESTION_TYPE_PUZZLE === $question_type ) {
			$prefix = 'puzzle-';
		} elseif ( TutorQuizModel::QUESTION_TYPE_PIN_IMAGE === $question_type ) {
			$prefix = 'pin-mask-';
		} else {
			$prefix = 'draw-mask-';
		}

		$target_name = $prefix . gmdate( 'Y-m-d-His' ) . '-' . wp_rand( 1000, 9999 ) . '.' . $ext;
		$target_name = wp_unique_filename( $target_dir, $target_name );
		$target_path = $target_dir . $target_name;

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Preserve behavior if copy fails due to FS constraints.
		if ( ! @copy( $source_path, $target_path ) ) {
			return '';
		}

		return wp_basename( $target_path );
	}

	/**
	 * Get file paths of puzzle attempt snapshot files for given attempt(s).
	 *
	 * @since 4.0.0
	 *
	 * @param int[] $attempt_ids Array of quiz attempt IDs.
	 *
	 * @return string[] Array of absolute file paths.
	 */
	public static function get_puzzle_snapshot_file_paths_for_attempts( array $attempt_ids ) {
		$paths = array();
		if ( empty( $attempt_ids ) ) {
			return $paths;
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return $paths;
		}

		$uploads_base_url = trailingslashit( $upload_dir['baseurl'] );
		$uploads_base_dir = trailingslashit( $upload_dir['basedir'] );
		$quiz_image_url   = $uploads_base_url . QuizImageStorage::QUIZ_IMAGES_SUBDIR . '/';

		$attempt_ids = array_filter( array_map( 'absint', $attempt_ids ) );
		if ( empty( $attempt_ids ) ) {
			return $paths;
		}

		$response = QueryHelper::get_joined_data(
			'tutor_quiz_attempt_answers AS a',
			array(
				array(
					'type'  => 'INNER',
					'table' => 'tutor_quiz_questions AS q',
					'on'    => 'a.question_id = q.question_id',
				),
			),
			array( 'a.given_answer' ),
			array(
				'a.quiz_attempt_id' => $attempt_ids,
				'q.question_type'   => TutorQuizModel::QUESTION_TYPE_PUZZLE,
			),
			array(),
			'',
			-1,
			0
		);

		$rows = isset( $response['results'] ) && is_array( $response['results'] ) ? $response['results'] : array();
		if ( empty( $rows ) ) {
			return $paths;
		}

		return self::resolve_puzzle_snapshot_urls_to_paths( $rows, $uploads_base_url, $uploads_base_dir, $quiz_image_url );
	}

	/**
	 * Resolve puzzle snapshot URLs from JSON given_answer rows to absolute file paths.
	 *
	 * @since 4.0.0
	 *
	 * @param array  $rows              Attempt-answer rows.
	 * @param string $uploads_base_url  Base URL for uploads (trailingslashit).
	 * @param string $uploads_base_dir  Base dir for uploads (trailingslashit).
	 * @param string $quiz_image_url    Quiz-image URL prefix.
	 *
	 * @return string[] Absolute file paths.
	 */
	private static function resolve_puzzle_snapshot_urls_to_paths( array $rows, $uploads_base_url, $uploads_base_dir, $quiz_image_url ) {
		unset( $uploads_base_url, $quiz_image_url );

		$paths       = array();
		$quiz_prefix = trailingslashit( trailingslashit( $uploads_base_dir ) . QuizImageStorage::QUIZ_IMAGES_SUBDIR );

		foreach ( $rows as $row ) {
			$given_answer = is_string( $row->given_answer ?? '' ) ? trim( $row->given_answer ) : '';
			if ( '' === $given_answer ) {
				continue;
			}

			$payload = json_decode( stripslashes( $given_answer ), true );
			if ( ! is_array( $payload ) ) {
				continue;
			}

			$file = isset( $payload['playground_snapshot_file'] ) ? trim( (string) $payload['playground_snapshot_file'] ) : '';
			if ( '' === $file ) {
				continue;
			}

			$path = QuizImageStorage::quiz_image_stored_value_to_path( $file );
			if ( '' === $path || ! is_readable( $path ) ) {
				continue;
			}

			if ( 0 !== strpos( $path, $quiz_prefix ) ) {
				continue;
			}

			$paths[] = $path;
		}

		return $paths;
	}
}
