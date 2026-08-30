<?php
/**
 * Ajax request handler class
 *
 * @package TutorPro\TutorAI
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

namespace TutorPro\TutorAI;

use Throwable;
use Tutor\Helpers\HttpHelper;
use Tutor\Traits\JsonResponse;
use TUTOR\Input;
use TutorPro\TutorAI\Generator\QuizQuestion\QuestionGenerateByTopic;

/**
 * Ajax handler
 */
class AjaxHandler {

	use JsonResponse;

	/**
	 * Register hooks
	 *
	 * @since 4.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_tutor_pro_ai_generate_questions', array( $this, 'generate_questions' ) );
	}

	/**
	 * Generate quiz questions from selected topics.
	 *
	 * @since 4.0.0
	 *
	 * @return void
	 */
	public function generate_questions() {
		$this->validate_request();

		$topic_ids           = Input::post( 'topic_ids', 0 );
		$question_types      = Input::post( 'question_types', 'true_false' );
		$difficulty_level    = Input::post( 'difficulty_level', 'medium' );
		$number_of_questions = Input::post( 'number_of_questions', 10, Input::TYPE_INT );

		if ( empty( $topic_ids ) ) {
			$this->json_response( __( 'Invalid topic ID provided', 'tutor-pro' ), null, HttpHelper::STATUS_BAD_REQUEST );
		}

		$question_types = explode( ',', $question_types );
		if ( empty( $question_types ) ) {
			$this->json_response( __( 'Invalid question types', 'tutor-pro' ), null, HttpHelper::STATUS_BAD_REQUEST );
		}

		try {
			$content = QuestionGenerateByTopic::from_topics( $topic_ids )
				->with_difficulty_level( $difficulty_level )
				->with_question_types( $question_types )
				->with_number_of_questions( $number_of_questions )
				->generate();

			$content = Helper::sanitize_json( $content ?? '' );
			$content = Helper::is_valid_json( $content ) ? json_decode( $content, true ) : array();

			if ( is_array( $content ) && isset( $content['error'] ) ) {
				$this->json_response( $content['error'], null, HttpHelper::STATUS_UNPROCESSABLE_ENTITY );
			}

			if ( is_array( $content ) ) {
				$possible_keys = array( 'questions', 'items' );
				foreach ( $possible_keys as $key ) {
					if ( isset( $content[ $key ] ) && is_array( $content[ $key ] ) ) {
						$content = $content[ $key ];
						break;
					}
				}

				if ( isset( $content['title'] ) ) {
					$content = array( $content );
				}
			}

			if ( ! is_array( $content ) ) {
				$content = array();
			}

			$this->json_response( __( 'Quiz generated', 'tutor-pro' ), $content );
		} catch ( Throwable $error ) {
			$this->json_response( $error->getMessage(), null, HttpHelper::STATUS_INTERNAL_SERVER_ERROR );
		}
	}


	/**
	 * Validate request by checking nonce & user cap
	 *
	 * @since 4.0.0
	 *
	 * @param string $user_cap User capability.
	 */
	private function validate_request( $user_cap = 'manage_options' ) {
		tutor_utils()->check_nonce();
		tutor_utils()->check_current_user_capability( $user_cap );
	}
}
