<?php
/**
 * Generate quize question from topics using AI.
 *
 * @package TutorPro\TutorAI
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

namespace TutorPro\TutorAI\Generator\QuizQuestion;

use Exception;
use Throwable;
use TutorPro\TutorAI\Helper;
use TutorPro\TutorAI\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Concrete quiz generator for topic-based quiz creation.
 *
 * Example usage:
 *
 * ```
 * QuestionGenerateByTopic::from_topics( $topic_ids )
 *  ->with_difficulty_level( self::DIFFICULTY_LEVEL_MEDIUM )
 *  ->with_question_types( 'single_choice,true_false,abc' )
 *  ->with_number_of_questions( 10 )
 *  ->generate();
 * ```
 *
 * @since 4.0.0
 */
class QuestionGenerateByTopic extends QuestionGenerator {

	/**
	 * Topic post type identifier.
	 *
	 * @since 4.0.0
	 *
	 * @var string
	 */
	const POST_TYPE = 'topics';

	/**
	 * Topic IDs to use for collecting quiz source content.
	 *
	 * @since 4.0.0
	 *
	 * @var array
	 */
	private $topic_ids_arr = array();

	/**
	 * Create a quiz generator instance using topics as the content source.
	 *
	 * @since 4.0.0
	 *
	 * @param array|string $topic_ids Topic IDs as an array or comma-separated string.
	 *
	 * @return self
	 */
	public static function from_topics( $topic_ids ) {
		$generator                = new static();
		$generator->topic_ids_arr = array_filter(
			array_map(
				'absint',
				is_array( $topic_ids ) ? $topic_ids : explode( ',', $topic_ids )
			)
		);

		return $generator;
	}

	/**
	 * Generate quiz questions from the configured content source.
	 *
	 * @since 4.0.0
	 *
	 * @throws Throwable If the AI client request fails.
	 * @throws Exception If required quiz generation inputs are invalid.
	 *
	 * @return string|null JSON quiz content or null when no response is returned.
	 */
	public function generate() {
		if ( empty( $this->topic_ids_arr ) ) {
			throw new Exception( esc_html__( 'Invalid topic ID provided', 'tutor-pro' ) );
		}

		if ( ! in_array( $this->difficulty_level, $this->get_difficulty_levels(), true ) ) {
			throw new Exception( esc_html__( 'Invalid difficulty level provided', 'tutor-pro' ) );
		}

		$contents = $this->get_structured_contents();

		if ( empty( $contents ) ) {
			throw new Exception( esc_html__( 'No content found for quiz generation', 'tutor-pro' ) );
		}

		$prompt = Prompts::prepare_question_generate_prompt( $contents, $this->question_types, $this->number_of_questions, $this->difficulty_level );

		try {
			$client   = Helper::get_openai_client();
			$response = $client->chat()->create(
				Helper::create_openai_chat_input(
					$prompt,
					array(
						'response_format' => array(
							'type' => 'json_object',
						),
					)
				)
			);

			$response = Helper::check_openai_response( $response );

			if ( ! empty( $response->choices ) ) {
				return $response->choices[0]->message->content;
			}

			return null;
		} catch ( Throwable $error ) {
			throw $error;
		}
	}

	/**
	 * Get structured topic content with child lessons.
	 *
	 * @since 4.0.0
	 *
	 * @return array Topic content array.
	 *
	 * @throws Exception If topic IDs are missing.
	 */
	protected function get_structured_contents() {

		$topics = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'post__in'       => $this->topic_ids_arr,
				'posts_per_page' => -1,
				'orderby'        => 'post__in',
			)
		);

		$structured_content = array();

		foreach ( $topics as $topic ) {
			$lessons = $this->get_topic_lessons( $topic->ID );
			$content = array(
				'id'          => absint( $topic->ID ),
				'type'        => self::POST_TYPE,
				'title'       => get_the_title( $topic ),
				'description' => $this->prepare_content_for_prompt( $topic->post_content ),
				'lessons'     => $lessons,
			);

			if ( ! empty( $content['description'] ) || ! empty( $lessons ) ) {
				$structured_content[] = $content;
			}
		}

		return $structured_content;
	}

	/**
	 * Get structured lesson contents for a topic.
	 *
	 * @since 4.0.0
	 *
	 * @param int $topic_id Topic ID.
	 *
	 * @return array Lesson content array.
	 */
	private function get_topic_lessons( $topic_id ) {
		$posts = get_posts(
			array(
				'post_type'      => tutor()->lesson_post_type,
				'post_status'    => 'publish',
				'post_parent'    => absint( $topic_id ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		$lessons = array();

		foreach ( $posts as $post ) {
			$lessons[] = array(
				'id'          => absint( $post->ID ),
				'type'        => tutor()->lesson_post_type,
				'title'       => get_the_title( $post ),
				'description' => $this->prepare_content_for_prompt( $post->post_content ),
			);
		}

		return $lessons;
	}
}
