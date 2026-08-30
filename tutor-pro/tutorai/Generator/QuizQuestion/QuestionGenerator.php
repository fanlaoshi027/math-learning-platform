<?php
/**
 * Abstract quiz question generator class
 *
 * @package TutorPro\TutorAI
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

namespace TutorPro\TutorAI\Generator\QuizQuestion;

defined( 'ABSPATH' ) || exit;

/**
 * Class to manage AI question generation.
 *
 * @since 4.0.0
 */
abstract class QuestionGenerator {

	/**
	 * Question difficulty level
	 *
	 * @since 4.0.0
	 *
	 * @var string
	 */
	const DIFFICULTY_LEVEL_EASY   = 'easy';
	const DIFFICULTY_LEVEL_MEDIUM = 'medium';
	const DIFFICULTY_LEVEL_HARD   = 'hard';

	/**
	 * Requested quiz question types.
	 *
	 * @since 4.0.0
	 *
	 * @var string|array
	 */
	protected $question_types = 'true_false';

	/**
	 * Requested quiz difficulty level.
	 *
	 * @since 4.0.0
	 *
	 * @var string
	 */
	protected $difficulty_level = 'medium';

	/**
	 * Requested number of quiz questions.
	 *
	 * @since 4.0.0
	 *
	 * @var int
	 */
	protected $number_of_questions = 10;

	/**
	 * Set the requested question types.
	 *
	 * @since 4.0.0
	 *
	 * @param string|array $question_types Question type slug or list of slugs.
	 *
	 * @return self
	 */
	public function with_question_types( $question_types ) {

		$question_types = is_array( $question_types ) ? $question_types : explode( ',', $question_types );

		$question_types = array_filter( array_map( 'sanitize_key', $question_types ) );

		$supported      = array_values( $this->get_supported_question_types() );
		$question_types = array_intersect( $question_types, $supported );

		if ( empty( $question_types ) ) {
			return 'true_false';
		}

		$this->question_types = array_values( array_unique( $question_types ) );

		return $this;
	}

	/**
	 * Set the requested quiz difficulty level.
	 *
	 * @since 4.0.0
	 *
	 * @param string $difficulty_level Difficulty level.
	 *
	 * @return self
	 */
	public function with_difficulty_level( $difficulty_level ) {
		$this->difficulty_level = sanitize_text_field( $difficulty_level );
		return $this;
	}

	/**
	 * Set the requested number of quiz questions.
	 *
	 * @since 4.0.0
	 *
	 * @param int $number Number of questions to generate.
	 *
	 * @return self
	 */
	public function with_number_of_questions( $number ) {
		$this->number_of_questions = max( 1, absint( $number ) );

		return $this;
	}

	/**
	 * Main method for generating quiz question
	 *
	 * @since 4.0.0
	 *
	 * @return string|null
	 */
	abstract public function generate();

	/**
	 * Generate quiz questions from the configured source content.
	 *
	 * Implement this method in subclasses to create and return structured quiz data.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	protected function get_supported_question_types() {
		return array(
			'true_false',
			'single_choice',
			'multiple_choice',
			'open_ended',
			'short_answer',
		);
	}

	/**
	 * Get difficulty levels
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	protected function get_difficulty_levels() {
		return array(
			self::DIFFICULTY_LEVEL_EASY,
			self::DIFFICULTY_LEVEL_MEDIUM,
			self::DIFFICULTY_LEVEL_HARD,
		);
	}

	/**
	 * Prepare post content for the AI prompt.
	 *
	 * @since 4.0.0
	 *
	 * @param string $content Raw post content.
	 *
	 * @return string Clean content.
	 */
	protected function prepare_content_for_prompt( $content ) {
		$content = strip_shortcodes( $content );
		$content = wp_strip_all_tags( $content, true );
		$content = html_entity_decode( $content, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$content = preg_replace( '/\s+/', ' ', $content );

		return trim( $content );
	}
}
