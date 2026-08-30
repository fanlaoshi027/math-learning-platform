<?php

namespace MathCourse\Video;

defined( 'ABSPATH' ) || exit;

use MathCourse\Course\Course_Service;

class Player {

	public function __construct() {
		add_shortcode( 'mathcourse_video', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function assets() {
		wp_enqueue_style( 'video-js', 'https://vjs.zencdn.net/8.10.0/video-js.css', array(), '8.10.0' );
		wp_enqueue_script( 'video-js', 'https://vjs.zencdn.net/8.10.0/video.min.js', array(), '8.10.0', true );
		wp_enqueue_script( 'mathcourse-player', MATHCOURSE_URL . 'assets/js/player.js', array( 'video-js' ), MATHCOURSE_VERSION, true );
		wp_localize_script( 'mathcourse-player', 'mathcoursePlayer', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'mathcourse_progress_nonce' ),
		) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'url'       => '',
				'lesson_id' => 0,
				'course_id' => 0,
			),
			$atts
		);

		$lesson_id = absint( $atts['lesson_id'] );
		$course_id = absint( $atts['course_id'] );
		$is_demo   = false;

		if ( $lesson_id ) {
			$service = new Course_Service();
			$video   = $service->get_lesson_video( $lesson_id, get_current_user_id() );

			if ( empty( $video['accessible'] ) ) {
				return '<div class="mc-video-locked">该课时需要课程授权后才能观看。</div>';
			}

			// HLS is preferred after migration. Existing Tutor LMS videos remain
			// playable through the native Tutor LMS player until migrated.
			$atts['url'] = ! empty( $video['hls_url'] ) ? $video['hls_url'] : '';
			$course_id  = absint( $video['course_id'] );
			$is_demo    = 'yes' === get_post_meta( $lesson_id, '_mathcourse_demo', true );
		}

		if ( $is_demo && empty( $atts['url'] ) ) {
			ob_start();
			?>
			<div class="mc-demo-player" data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>">
				<div class="mc-demo-player__grid" aria-hidden="true"></div>
				<div class="mc-demo-player__equation">△ABC ≌ △A′B′C′</div>
				<div class="mc-demo-player__play" aria-hidden="true">▶</div>
				<div class="mc-demo-player__label">演示播放器 · 正式课程上传 HLS 视频后自动播放</div>
				<div class="mc-demo-player__controls"><span>▶</span><span class="mc-demo-player__line"><i></i></span><span>05:12 / 28:45</span><span>1.0×</span><span>⌗</span><span>⛶</span></div>
			</div>
			<?php
			return ob_get_clean();
		}

		if ( ! empty( $atts['url'] ) ) {
			ob_start();
			?>
			<video
				id="mathcourse-player-<?php echo esc_attr( $lesson_id ); ?>"
				class="video-js vjs-big-play-centered mathcourse-player"
				controls
				preload="metadata"
				playsinline
				data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>"
				data-course-id="<?php echo esc_attr( $course_id ); ?>">
				<source src="<?php echo esc_url( $atts['url'] ); ?>" type="application/x-mpegURL">
			</video>
			<?php
			return ob_get_clean();
		}

		// Backward compatibility: videos previously uploaded in Tutor LMS use
		// the _video metadata and should not require re-uploading or an HLS URL.
		$tutor_player = $this->render_tutor_video( $lesson_id );
		if ( $tutor_player ) {
			return $tutor_player;
		}

		return '<div class="mc-video-missing">本课时暂未配置可播放的视频资源。</div>';
	}

	private function render_tutor_video( $lesson_id ) {
		if ( ! $lesson_id || ! function_exists( 'tutor_lesson_video' ) || ! function_exists( 'tutor_utils' ) ) {
			return '';
		}

		$lesson = get_post( $lesson_id );
		if ( ! $lesson || empty( get_post_meta( $lesson_id, '_video', true ) ) ) {
			return '';
		}

		global $post;
		$previous_post = $post;
		$post = $lesson;
		setup_postdata( $lesson );

		try {
			$video_info = tutor_utils()->get_video_info();
			if ( ! $video_info ) {
				return '';
			}
			$html = tutor_lesson_video( false );
		} finally {
			wp_reset_postdata();
			$post = $previous_post;
		}

		return is_string( $html ) ? $html : '';
	}
}
