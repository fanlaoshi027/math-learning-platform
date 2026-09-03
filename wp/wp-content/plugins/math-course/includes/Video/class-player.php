<?php
namespace MathCourse\Video;
defined( 'ABSPATH' ) || exit;
use MathCourse\Course\Course_Service;
use MathCourse\Tutor\Adapter;

class Player {
    private $tutor;
    public function __construct() { $this->tutor = new Adapter(); add_shortcode( 'mathcourse_video', array( $this, 'render' ) ); }
    public function assets() {
        // ArtPlayer 官方 UI：播放器本体使用项目内固定版本，不依赖 CDN。
        wp_enqueue_script( 'mathcourse-hls', 'https://cdn.jsdelivr.net/npm/hls.js@1.7.0/dist/hls.min.js', array(), '1.7.0', true );
        wp_enqueue_script( 'mathcourse-artplayer', MATHCOURSE_URL . 'assets/js/artplayer.js', array(), '5.4.1', true );
        wp_enqueue_style( 'mathcourse-artplayer-ui', MATHCOURSE_URL . 'assets/css/artplayer-ui.css', array(), MATHCOURSE_VERSION );
        wp_enqueue_script( 'mathcourse-player', MATHCOURSE_URL . 'assets/js/player.js', array( 'mathcourse-hls', 'mathcourse-artplayer' ), MATHCOURSE_VERSION, true );
        wp_localize_script( 'mathcourse-player', 'mathcoursePlayer', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'mathcourse_progress_nonce' ) ) );
    }
    private function missing_player_markup( $message = '本节视频暂未上传' ) {
        return '<div class="mathcourse-player-placeholder" role="status" aria-label="' . esc_attr( $message ) . '"><div class="mathcourse-player-placeholder__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"></rect><path d="m10 9 5 3-5 3V9Z"></path></svg></div><div class="mathcourse-player-placeholder__title">' . esc_html( $message ) . '</div><div class="mathcourse-player-placeholder__text">老师正在准备课程内容，请稍后再来学习</div></div>';
    }
    public function render( $atts ) {
        $atts = shortcode_atts( array( 'url' => '', 'lesson_id' => 0, 'course_id' => 0 ), $atts );
        $lesson_id = absint( $atts['lesson_id'] ); $course_id = absint( $atts['course_id'] ); $user_id = get_current_user_id();
        if ( ! $lesson_id ) return '<div class="mc-video-missing">未指定课时。</div>';
        $service = new Course_Service(); $video = $service->get_lesson_video( $lesson_id, $user_id );
        if ( empty( $video['id'] ) || empty( $video['accessible'] ) ) return '<div class="mc-video-locked">该课时需要课程授权或试看权限后才能观看。</div>';
        $course_id = absint( $video['course_id'] ?: $course_id );
        if ( ! empty( $video['hls_url'] ) ) {
            $this->assets(); ob_start(); ?>
            <div id="mathcourse-player-<?php echo esc_attr( $lesson_id ); ?>" class="mathcourse-artplayer" data-video-url="<?php echo esc_attr( $video['hls_url'] ); ?>" data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>" data-course-id="<?php echo esc_attr( $course_id ); ?>" aria-label="课程视频播放器"></div>
            <?php return ob_get_clean();
        }
        $tutor_player = $this->tutor->render_lesson_video( $lesson_id );
        if ( $tutor_player ) return $tutor_player;
        return $this->missing_player_markup();
    }
}
