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

		// Lesson playback must resolve through the access layer. A visitor cannot
		// bypass course permission by supplying an arbitrary HLS URL in the shortcode.
		if ( $lesson_id ) {
			$service = new Course_Service();
			$video   = $service->get_lesson_video( $lesson_id, get_current_user_id() );

			if ( empty( $video['accessible'] ) ) {
				return '<div class="mc-video-locked">该课时需要课程授权后才能观看。</div>';
			}

			$atts['url'] = $video['hls_url'];
			$course_id  = absint( $video['course_id'] );
			$is_demo    = 'yes' === get_post_meta( $lesson_id, '_mathcourse_demo', true );
		}

		// Demo lessons intentionally have no real media URL. Render a visual player
		// placeholder so the one-click demo can validate the learning-page layout
		// without introducing a third-party or permanent media dependency.
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

		if ( empty( $atts['url'] ) ) {
			return '<div class="mc-video-locked">播放器暂不可用，请先配置本课时的视频地址。</div>';
		}

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
}
