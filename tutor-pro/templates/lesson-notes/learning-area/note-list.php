<?php
/**
 * Lesson Notes List
 *
 * @package TutorPro\Templates
 * @subpackage LearningArea
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $note_list ) ) {
	return;
}

foreach ( $note_list as $note ) :
	tutor_load_template(
		'lesson-notes.learning-area.note-card',
		array(
			'note' => $note,
		),
		true
	);
endforeach;
