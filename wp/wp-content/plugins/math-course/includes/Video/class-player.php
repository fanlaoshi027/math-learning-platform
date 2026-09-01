<?php
namespace MathCourse\Video;

defined( 'ABSPATH' ) || exit;

use MathCourse\Course\Course_Service;
use MathCourse\Tutor\Adapter;

class Player {
    private $tutor;

    public function __construct() {
        $this->tutor = new Adapter();
        add_shortcode( 'mathcourse_video', array( $this, 'render' ) );
    }

    public function assets() {
        wp_enqueue_style( 'artplayer', 'https://cdn.jsdelivr.net/npm/artplayer/dist/artplayer.css', array(), null );
        wp_enqueue_style( 'mathcourse-course-player-large', MATHCOURSE_URL . 'assets/course-player-large.css', array( 'artplayer' ), MATHCOURSE_VERSION );
        wp_enqueue_style( 'mathcourse-reference-player-ui', MATHCOURSE_URL . 'assets/reference-player-ui.css', array( 'mathcourse-course-player-large' ), MATHCOURSE_VERSION );
        wp_enqueue_style( 'mathcourse-player-ui-v2', MATHCOURSE_URL . 'assets/player-ui-v2.css', array( 'mathcourse-reference-player-ui' ), MATHCOURSE_VERSION );
        wp_enqueue_style( 'mathcourse-artplayer-ui', MATHCOURSE_URL . 'assets/artplayer-ui.css', array( 'artplayer', 'mathcourse-player-ui-v2' ), MATHCOURSE_VERSION );

        wp_enqueue_script( 'hls-js', 'https://cdn.jsdelivr.net/npm/hls.js@1.6.2/dist/hls.min.js', array(), '1.6.2', true );
        wp_enqueue_script( 'artplayer', 'https://cdn.jsdelivr.net/npm/artplayer/dist/artplayer.js', array( 'hls-js' ), null, true );
        wp_enqueue_script( 'mathcourse-player', MATHCOURSE_URL . 'assets/js/player.js', array( 'artplayer', 'hls-js' ), MATHCOURSE_VERSION, true );
        wp_localize_script( 'mathcourse-player', 'mathcoursePlayer', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mathcourse_progress_nonce' ),
        ) );
    }

    private function missing_player_markup( $message = '本节视频暂未上传' ) {
        return '<div class="mathcourse-player-placeholder" role="status" aria-label="' . esc_attr( $message ) . '"><div class="mathcourse-player-placeholder__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"></rect><path d="m10 9 5 3-5 3V9Z"></path></svg></div><div class="mathcourse-player-placeholder__title">' . esc_html( $message ) . '</div><div class="mathcourse-player-placeholder__text">老师正在准备课程内容，请稍后再来学习</div></div>';
    }

    public function render( $atts ) {
        $atts      = shortcode_atts( array( 'url' => '', 'lesson_id' => 0, 'course_id' => 0 ), $atts );
        $lesson_id = absint( $atts['lesson_id'] );
        $course_id = absint( $atts['course_id'] );
        $user_id   = get_current_user_id();

        if ( ! $lesson_id ) {
            return '<div class="mc-video-missing">未指定课时。</div>';
        }

        $service = new Course_Service();
        $video   = $service->get_lesson_video( $lesson_id, $user_id );

        if ( empty( $video['id'] ) || empty( $video['accessible'] ) ) {
            return '<div class="mc-video-locked">该课时需要课程授权或试看权限后才能观看。</div>';
        }

        $course_id = absint( $video['course_id'] ?: $course_id );

        if ( ! empty( $video['hls_url'] ) ) {
            $this->assets();
            $player_id = 'mathcourse-artplayer-' . $lesson_id;

            ob_start();
            ?>
            <div
                id="<?php echo esc_attr( $player_id ); ?>"
                class="artplayer-app mathcourse-artplayer"
                data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>"
                data-course-id="<?php echo esc_attr( $course_id ); ?>"
                data-video-url="<?php echo esc_url( $video['hls_url'] ); ?>"
                aria-label="课程视频播放器"
            ></div>
            <?php
            return ob_get_clean();
        }

        $tutor_player = $this->tutor->render_lesson_video( $lesson_id );
        if ( $tutor_player ) {
            return $tutor_player;
        }

        return $this->missing_player_markup();
    }
}
