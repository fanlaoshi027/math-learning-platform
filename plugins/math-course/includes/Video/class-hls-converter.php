<?php
namespace MathCourse\Video;

defined( 'ABSPATH' ) || exit;

use MathCourse\Tutor\Adapter;

/**
 * 后台 MP4 → HLS 转换器。
 * 上传 MP4 后保存到临时源目录，FFmpeg 无重新编码切成 HLS，
 * HLS 按「课程全拼 / lesson-ID」写入独立媒体目录，与播放器的媒体路由保持一致。
 */
class Hls_Converter {
    const META_STATUS = '_mathcourse_video_status';
    const META_SOURCE = '_mathcourse_video_source';
    const META_HLS = '_mathcourse_hls_url';
    const META_ERROR = '_mathcourse_video_error';
    const META_INFO = '_mathcourse_video_info';
    private $adapter;

    public function __construct() {
        $this->adapter = new Adapter();
        add_action( 'wp_ajax_mathcourse_upload_video', array( $this, 'ajax_upload' ) );
        add_action( 'wp_ajax_mathcourse_video_status', array( $this, 'ajax_status' ) );
        add_action( 'wp_ajax_mathcourse_video_upload_config', array( $this, 'ajax_upload_config' ) );
        add_action( 'mathcourse_convert_video', array( $this, 'convert' ), 10, 1 );
    }

    public function ajax_upload_config() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => '没有查看权限。' ), 403 );
        check_ajax_referer( 'mathcourse_video_upload', 'nonce' );
        $root = $this->get_upload_root();
        $hls_root = defined( 'MATHCOURSE_MEDIA_ROOT' ) ? untrailingslashit( MATHCOURSE_MEDIA_ROOT ) : WP_CONTENT_DIR . '/uploads/mathcourse-hls';
        $ffmpeg = defined( 'MATHCOURSE_FFMPEG_PATH' ) ? MATHCOURSE_FFMPEG_PATH : '/usr/bin/ffmpeg';
        $ffprobe = defined( 'MATHCOURSE_FFPROBE_PATH' ) ? MATHCOURSE_FFPROBE_PATH : '/usr/bin/ffprobe';
        wp_send_json_success( array(
            'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
            'post_max_size' => ini_get( 'post_max_size' ),
            'max_file_uploads' => ini_get( 'max_file_uploads' ),
            'max_input_time' => ini_get( 'max_input_time' ),
            'max_execution_time' => ini_get( 'max_execution_time' ),
            'memory_limit' => ini_get( 'memory_limit' ),
            'upload_root' => $root,
            'upload_root_writable' => is_dir( $root ) ? is_writable( $root ) : wp_mkdir_p( $root ) && is_writable( $root ),
            'hls_root' => $hls_root,
            'hls_root_writable' => is_dir( $hls_root ) ? is_writable( $hls_root ) : wp_mkdir_p( $hls_root ) && is_writable( $hls_root ),
            'ffmpeg' => $ffmpeg,
            'ffmpeg_executable' => is_executable( $ffmpeg ),
            'ffprobe' => $ffprobe,
            'ffprobe_executable' => is_executable( $ffprobe ),
        ) );
    }

    public function ajax_upload() {
        $this->register_upload_fatal_handler();
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => '没有上传权限。' ), 403 );
        check_ajax_referer( 'mathcourse_video_upload', 'nonce' );
        $lesson_id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0;
        $course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
        $lesson = $lesson_id ? $this->adapter->get_lesson( $lesson_id ) : null;
        if ( ! $lesson_id || ! $course_id || ! $lesson ) wp_send_json_error( array( 'message' => '课时信息无效。' ), 400 );
        if ( $course_id !== $this->adapter->get_lesson_course_id( $lesson_id ) ) wp_send_json_error( array( 'message' => '课时不属于当前课程。' ), 400 );
        if ( ! current_user_can( 'edit_post', $lesson_id ) ) wp_send_json_error( array( 'message' => '没有编辑该课时的权限。' ), 403 );
        if ( empty( $_FILES['video'] ) || empty( $_FILES['video']['tmp_name'] ) ) {
            $upload_error = isset( $_FILES['video']['error'] ) ? absint( $_FILES['video']['error'] ) : 0;
            if ( $upload_error ) wp_send_json_error( array( 'message' => '文件上传失败，错误代码：' . $upload_error . '。当前 PHP upload_max_filesize=' . ini_get( 'upload_max_filesize' ) . '，post_max_size=' . ini_get( 'post_max_size' ) . '。' ), 400 );
            wp_send_json_error( array( 'message' => '请选择 MP4 视频文件。' ), 400 );
        }
        $file = $_FILES['video'];
        if ( ! empty( $file['error'] ) ) wp_send_json_error( array( 'message' => '文件上传失败，错误代码：' . absint( $file['error'] ) . '。PHP upload_max_filesize=' . ini_get( 'upload_max_filesize' ) . '，post_max_size=' . ini_get( 'post_max_size' ) . '。' ), 400 );
        if ( ! is_uploaded_file( $file['tmp_name'] ) ) wp_send_json_error( array( 'message' => '上传文件无效。' ), 400 );
        if ( strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) !== 'mp4' ) wp_send_json_error( array( 'message' => '这里只接受 MP4 文件。' ), 400 );

        $root = $this->get_upload_root();
        if ( ! wp_mkdir_p( $root ) || ! is_writable( $root ) ) wp_send_json_error( array( 'message' => '视频上传目录不可写，请检查服务器权限。' ), 500 );
        $safe_name = sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) );
        $safe_name = $safe_name ? $safe_name : 'video';
        $source = trailingslashit( $root ) . 'course-' . $course_id . '-lesson-' . $lesson_id . '-' . $safe_name . '-' . wp_generate_password( 8, false, false ) . '.mp4';
        if ( ! move_uploaded_file( $file['tmp_name'], $source ) ) wp_send_json_error( array( 'message' => '服务器保存 MP4 失败。' ), 500 );

        $info = $this->probe( $source );
        if ( is_wp_error( $info ) ) { @unlink( $source ); wp_send_json_error( array( 'message' => $info->get_error_message() ), 400 ); }
        update_post_meta( $lesson_id, self::META_STATUS, 'pending' );
        update_post_meta( $lesson_id, self::META_SOURCE, $source );
        update_post_meta( $lesson_id, self::META_INFO, $info );
        delete_post_meta( $lesson_id, self::META_ERROR );
        delete_post_meta( $lesson_id, self::META_HLS );

        $hls_dir = $this->get_hls_dir( $course_id, $lesson_id );
        $this->remove_dir( $hls_dir );
        if ( ! wp_mkdir_p( $hls_dir ) ) {
            @unlink( $source );
            $this->fail( $lesson_id, '无法创建 HLS 输出目录。' );
            wp_send_json_error( array( 'message' => '无法创建 HLS 输出目录。' ), 500 );
        }
        wp_clear_scheduled_hook( 'mathcourse_convert_video', array( $lesson_id ) );
        wp_schedule_single_event( time() + 2, 'mathcourse_convert_video', array( $lesson_id ) );
        if ( function_exists( 'spawn_cron' ) ) spawn_cron( time() );
        wp_send_json_success( array( 'status' => 'pending', 'message' => 'MP4 已上传，服务器开始准备 HLS 转换。', 'info' => $info ) );
    }

    private function register_upload_fatal_handler() {
        static $registered = false;
        if ( $registered ) return;
        $registered = true;
        register_shutdown_function( function () {
            $error = error_get_last();
            if ( ! is_array( $error ) ) return;
            $fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );
            if ( ! in_array( $error['type'], $fatal_types, true ) ) return;
            while ( ob_get_level() ) @ob_end_clean();
            status_header( 500 );
            nocache_headers();
            header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
            echo wp_json_encode( array( 'success' => false, 'data' => array( 'message' => 'PHP 在处理视频上传时发生致命错误：' . sanitize_text_field( $error['message'] ) . '（文件：' . wp_basename( $error['file'] ) . '，第 ' . absint( $error['line'] ) . ' 行）' ) ) );
        } );
    }

    public function ajax_status() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => '没有查看权限。' ), 403 );
        check_ajax_referer( 'mathcourse_video_upload', 'nonce' );
        $lesson_id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0;
        if ( ! $lesson_id ) wp_send_json_error( array( 'message' => '课时无效。' ), 400 );
        if ( ! current_user_can( 'edit_post', $lesson_id ) ) wp_send_json_error( array( 'message' => '没有查看该课时的权限。' ), 403 );
        wp_send_json_success( array( 'status' => get_post_meta( $lesson_id, self::META_STATUS, true ) ?: 'none', 'hls_url' => get_post_meta( $lesson_id, self::META_HLS, true ), 'error' => get_post_meta( $lesson_id, self::META_ERROR, true ), 'info' => get_post_meta( $lesson_id, self::META_INFO, true ) ) );
    }

    public function convert( $lesson_id ) {
        $lesson_id = absint( $lesson_id );
        if ( ! $lesson_id ) return;
        $source = get_post_meta( $lesson_id, self::META_SOURCE, true );
        $course_id = $this->adapter->get_lesson_course_id( $lesson_id );
        if ( ! $source || ! $course_id || ! is_file( $source ) ) { $this->fail( $lesson_id, '找不到上传的 MP4 文件。' ); return; }
        update_post_meta( $lesson_id, self::META_STATUS, 'processing' );
        $info = $this->probe( $source );
        if ( is_wp_error( $info ) ) { $this->fail( $lesson_id, $info->get_error_message() ); return; }
        update_post_meta( $lesson_id, self::META_INFO, $info );
        $video_codec = strtolower( (string) ( $info['video_codec'] ?? '' ) );
        $audio_codec = strtolower( (string) ( $info['audio_codec'] ?? '' ) );
        if ( 'h264' !== $video_codec ) { $this->fail( $lesson_id, '该 MP4 的视频编码为 ' . ( $video_codec ?: '未知' ) . '。请使用 H.264 视频编码后再上传。' ); return; }
        if ( $audio_codec && 'aac' !== $audio_codec ) { $this->fail( $lesson_id, '该 MP4 的音频编码为 ' . $audio_codec . '。请使用 AAC 音频后再上传。' ); return; }

        $hls_dir = $this->get_hls_dir( $course_id, $lesson_id );
        if ( ! wp_mkdir_p( $hls_dir ) || ! is_writable( $hls_dir ) ) { $this->fail( $lesson_id, 'HLS 输出目录不可写。' ); return; }
        $playlist = trailingslashit( $hls_dir ) . 'index.m3u8';
        $pattern = trailingslashit( $hls_dir ) . 'segment-%05d.ts';
        $ffmpeg = defined( 'MATHCOURSE_FFMPEG_PATH' ) ? MATHCOURSE_FFMPEG_PATH : '/usr/bin/ffmpeg';
        if ( ! is_executable( $ffmpeg ) ) { $this->fail( $lesson_id, '找不到可执行的 FFmpeg：' . $ffmpeg ); return; }
        $cmd = escapeshellarg( $ffmpeg ) . ' -hide_banner -loglevel error -y -i ' . escapeshellarg( $source ) . ' -map 0:v:0 -map 0:a? -c copy -start_number 0 -hls_time 8 -hls_list_size 0 -hls_segment_type mpegts -hls_segment_filename ' . escapeshellarg( $pattern ) . ' -f hls ' . escapeshellarg( $playlist ) . ' 2>&1';
        $output = array(); $exit = 0; @set_time_limit( 0 ); exec( $cmd, $output, $exit );
        if ( 0 !== $exit || ! is_file( $playlist ) || filesize( $playlist ) < 20 ) { $this->fail( $lesson_id, 'FFmpeg HLS 转换失败：' . trim( implode( "\n", array_slice( $output, -8 ) ) ) ); return; }
        $has_segment = false; $files = glob( trailingslashit( $hls_dir ) . 'segment-*.ts' );
        if ( is_array( $files ) ) foreach ( $files as $segment ) { if ( is_file( $segment ) && filesize( $segment ) > 0 ) { $has_segment = true; break; } }
        if ( ! $has_segment ) { $this->fail( $lesson_id, 'HLS 播放列表已生成，但没有找到有效视频分片。' ); return; }

        $relative = $this->get_hls_relative_path( $course_id, $lesson_id );
        if ( ! $relative ) { $this->fail( $lesson_id, '无法确定课程的视频目录。请检查课程名称/课时信息。' ); return; }
        update_post_meta( $lesson_id, self::META_HLS, '/__mathcourse_hls/' . $relative . '/index.m3u8' );
        update_post_meta( $lesson_id, self::META_STATUS, 'ready' );
        delete_post_meta( $lesson_id, self::META_ERROR );
        @unlink( $source );
        delete_post_meta( $lesson_id, self::META_SOURCE );
    }

    private function fail( $lesson_id, $message ) { update_post_meta( $lesson_id, self::META_STATUS, 'failed' ); update_post_meta( $lesson_id, self::META_ERROR, sanitize_textarea_field( (string) $message ) ); }

    /**
     * HLS 目录固定为：
     *   {媒体根目录}/{课程全拼}/lesson-{课时ID}/index.m3u8
     * 课程目录根据课程名称自动生成，不再使用 WordPress 的 post_name/course-ID。
     * 例如：八年级上册大培优2026 → 8shang-dapeiyou-2026
     */
    private function get_hls_relative_path( $course_id, $lesson_id ) {
        $course = $this->adapter->get_course( $course_id );
        if ( ! $course ) return '';
        $directory = $this->course_directory_name( $course->post_title );
        if ( '' === $directory ) $directory = 'course-' . absint( $course_id );
        return trim( $directory . '/lesson-' . absint( $lesson_id ), '/' );
    }

    /**
     * 根据课程名称生成稳定的英文/数字目录名。
     * 教材课程名称中的「X年级上/下册」按站点目录规则压缩为「Xshang/xia」。
     * 优先使用 PHP Intl 的 Han-Latin 转换；没有 Intl 时，对常用数学课程词汇提供兜底转换。
     */
    private function course_directory_name( $title ) {
        $title = trim( wp_strip_all_tags( (string) $title ) );
        if ( '' === $title ) return '';

        $grade_map = array(
            '一' => '1', '二' => '2', '三' => '3', '四' => '4', '五' => '5',
            '六' => '6', '七' => '7', '八' => '8', '九' => '9', '十' => '10',
        );
        $title = preg_replace_callback( '/([一二三四五六七八九十0-9]+)年级(上册|下册)/u', function ( $m ) use ( $grade_map ) {
            $grade = $m[1];
            if ( isset( $grade_map[ $grade ] ) ) $grade = $grade_map[ $grade ];
            return $grade . ( '上册' === $m[2] ? 'shang' : 'xia' );
        }, $title );

        $title = str_replace( array( '上册', '下册' ), array( 'shang', 'xia' ), $title );

        if ( class_exists( '\Transliterator' ) ) {
            $transliterator = \Transliterator::create( 'Han-Latin; Latin-ASCII' );
            if ( $transliterator ) $title = $transliterator->transliterate( $title );
        } else {
            $fallback = array(
                '大培优' => 'dapeiyou', '培优' => 'peiyou', '数学' => 'shuxue', '系统课' => 'xitongke',
                '专题' => 'zhuanti', '基础' => 'jichu', '提高' => 'tigao', '入门' => 'rumen',
                '全等' => 'quandeng', '三角形' => 'sanjiaoxing', '一次函数' => 'yicihanshu',
                '二次函数' => 'ercihanshu', '一元一次方程' => 'yiyuanyicifangcheng',
                '一元二次方程' => 'yiyuanercifangcheng', '勾股定理' => 'gougudingli',
                '因式分解' => 'yinshifenjie', '整式' => 'zhengshi', '分式' => 'fenshi',
                '实数' => 'shishu', '几何' => 'jihe', '代数' => 'daishu',
            );
            uksort( $fallback, function ( $a, $b ) { return mb_strlen( $b, 'UTF-8' ) - mb_strlen( $a, 'UTF-8' ); } );
            $title = str_replace( array_keys( $fallback ), array_values( $fallback ), $title );
        }

        $title = strtolower( $title );
        $title = preg_replace( '/[\x{3000}\s]+/u', '', $title );
        $title = preg_replace( '/[^a-z0-9]+/i', '-', $title );
        $title = trim( $title, '-' );
        return $title;
    }

    private function probe( $source ) {
        $ffprobe = defined( 'MATHCOURSE_FFPROBE_PATH' ) ? MATHCOURSE_FFPROBE_PATH : '/usr/bin/ffprobe';
        if ( ! is_executable( $ffprobe ) ) return new \WP_Error( 'ffprobe_missing', '找不到 ffprobe：' . $ffprobe );
        $cmd = escapeshellarg( $ffprobe ) . ' -v error -show_entries format=duration:stream=index,codec_type,codec_name,width,height,r_frame_rate -of json ' . escapeshellarg( $source );
        $data = json_decode( (string) @shell_exec( $cmd ), true );
        if ( empty( $data ) || empty( $data['streams'] ) ) return new \WP_Error( 'ffprobe_failed', '无法读取 MP4 视频信息。' );
        $info = array( 'duration' => isset( $data['format']['duration'] ) ? round( (float) $data['format']['duration'], 2 ) : 0, 'video_codec' => '', 'audio_codec' => '', 'width' => 0, 'height' => 0, 'fps' => '' );
        foreach ( $data['streams'] as $stream ) {
            if ( 'video' === ( $stream['codec_type'] ?? '' ) && ! $info['video_codec'] ) { $info['video_codec'] = $stream['codec_name'] ?? ''; $info['width'] = absint( $stream['width'] ?? 0 ); $info['height'] = absint( $stream['height'] ?? 0 ); $info['fps'] = $this->fps( $stream['r_frame_rate'] ?? '' ); }
            if ( 'audio' === ( $stream['codec_type'] ?? '' ) && ! $info['audio_codec'] ) $info['audio_codec'] = $stream['codec_name'] ?? '';
        }
        return $info;
    }
    private function fps( $rate ) { if ( ! $rate || strpos( $rate, '/' ) === false ) return $rate; list( $n, $d ) = array_map( 'floatval', explode( '/', $rate, 2 ) ); return $d > 0 ? rtrim( rtrim( number_format( $n / $d, 2, '.', '' ), '0' ), '.' ) : ''; }
    private function get_upload_root() { return defined( 'MATHCOURSE_VIDEO_UPLOAD_ROOT' ) ? untrailingslashit( MATHCOURSE_VIDEO_UPLOAD_ROOT ) : WP_CONTENT_DIR . '/uploads/mathcourse-source'; }
    private function get_hls_dir( $course_id, $lesson_id ) { $root = defined( 'MATHCOURSE_MEDIA_ROOT' ) ? untrailingslashit( MATHCOURSE_MEDIA_ROOT ) : WP_CONTENT_DIR . '/uploads/mathcourse-hls'; $relative = $this->get_hls_relative_path( $course_id, $lesson_id ); return trailingslashit( $root ) . $relative; }
    private function remove_dir( $dir ) { if ( ! is_dir( $dir ) ) return; $items = scandir( $dir ); if ( ! is_array( $items ) ) return; foreach ( $items as $item ) { if ( '.' === $item || '..' === $item ) continue; $path = trailingslashit( $dir ) . $item; if ( is_dir( $path ) ) $this->remove_dir( $path ); else @unlink( $path ); } @rmdir( $dir ); }
}
