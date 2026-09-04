<?php
/**
 * Math Course Theme - Legacy Course Route.
 *
 * Course cards use the dedicated learning page directly. Keep the legacy
 * single-course route as a compatibility entry point, but never render a
 * separate course-detail UI here.
 */
defined( 'ABSPATH' ) || exit;

$course_id = absint( get_the_ID() );

if ( $course_id ) {
	$learning_url = add_query_arg(
		array( 'course_id' => $course_id ),
		home_url( '/learning/' )
	);
	wp_safe_redirect( $learning_url, 302 );
	exit;
}

wp_safe_redirect( home_url( '/course-center/' ), 302 );
exit;
