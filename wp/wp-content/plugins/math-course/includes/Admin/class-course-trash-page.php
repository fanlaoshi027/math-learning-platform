<?php
namespace MathCourse\Admin;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;

/**
 * 已冻结课程（WordPress 回收站）管理。
 * 冻结不会立即删除数据；彻底删除时才级联清理课程、专题、课时、授权、激活码、进度与视频文件。
 */
class Course_Trash_Page {

    public function render() {
        if ( ! current_user_can('manage_options') ) return;

        $adapter = new Adapter();
        $message = '';
        $message_type = 'success';

        if ( 'POST' === ($_SERVER['REQUEST_METHOD'] ?? '') ) {
            $action = isset($_POST['mathcourse_trash_action']) ? sanitize_key(wp_unslash($_POST['mathcourse_trash_action'])) : '';
            $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
            $nonce = isset($_POST['mathcourse_trash_nonce']) ? sanitize_text_field(wp_unslash($_POST['mathcourse_trash_nonce'])) : '';

            if ( ! $course_id || ! wp_verify_nonce($nonce, 'mathcourse_trash_action_' . $course_id) ) {
                $message = '操作验证失败，请刷新页面后重试。';
                $message_type = 'error';
            } elseif ( 'restore' === $action ) {
                $course = get_post($course_id);
                if ( ! $course || ! $adapter->is_course_post_type($course->post_type) || 'trash' !== $course->post_status ) {
                    $message = '找不到需要恢复的冻结课程。';
                    $message_type = 'error';
                } elseif ( ! current_user_can('delete_post', $course_id) ) {
                    $message = '没有恢复该课程的权限。';
                    $message_type = 'error';
                } elseif ( false === wp_untrash_post($course_id) ) {
                    $message = '课程恢复失败。';
                    $message_type = 'error';
                } else {
                    $message = '课程已恢复到课程管理。';
                }
            } elseif ( 'delete_permanently' === $action ) {
                $course = get_post($course_id);
                if ( ! $course || ! $adapter->is_course_post_type($course->post_type) || 'trash' !== $course->post_status ) {
                    $message = '只能彻底删除已冻结的课程。';
                    $message_type = 'error';
                } elseif ( ! current_user_can('delete_post', $course_id) ) {
                    $message = '没有彻底删除该课程的权限。';
                    $message_type = 'error';
                } elseif ( $this->delete_course_permanently($course_id, $adapter) ) {
                    $message = '课程及其全部关联数据已彻底删除。';
                } else {
                    $message = '课程删除失败，请检查服务器日志。';
                    $message_type = 'error';
                }
            }
        }

        $courses = $this->get_trashed_courses($adapter);
        ?>
        <div class="wrap mathcourse-admin-wrap mathcourse-course-trash-page">
            <div class="mathcourse-admin-header">
                <div>
                    <h1>已冻结课程</h1>
                    <p>冻结后的课程统一放在这里。恢复可继续使用，彻底删除会同时清理课程结构、授权、进度和视频文件。</p>
                </div>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-courses')); ?>">← 返回课程管理</a>
            </div>

            <?php if ( $message ) : ?>
                <div class="notice <?php echo 'error' === $message_type ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>

            <div class="mathcourse-card mathcourse-trash-card">
                <div class="mathcourse-card-title">
                    <div>
                        <h2>冻结课程</h2>
                        <span class="mathcourse-card-subtitle">共 <?php echo esc_html(count($courses)); ?> 门</span>
                    </div>
                </div>

                <?php if ( empty($courses) ) : ?>
                    <div class="mathcourse-empty-state">
                        <div class="mathcourse-empty-icon">✓</div>
                        <strong>这里还没有冻结课程</strong>
                        <p>课程管理中的“冻结课程”会统一出现在这里。</p>
                    </div>
                <?php else : ?>
                    <div class="mathcourse-trash-list">
                        <?php foreach ( $courses as $course ) :
                            $id = (int) $course->ID;
                            $grade = get_post_meta($id, '_mathcourse_grade', true);
                            $type = get_post_meta($id, '_mathcourse_type', true);
                            $lessons = $adapter->get_course_lessons($id, true);
                            $delete_nonce = wp_create_nonce('mathcourse_trash_action_' . $id);
                        ?>
                            <article class="mathcourse-trash-item">
                                <div class="mathcourse-trash-main">
                                    <div class="mathcourse-trash-icon">▣</div>
                                    <div>
                                        <h3><?php echo esc_html($course->post_title); ?></h3>
                                        <div class="mathcourse-trash-meta">
                                            <span>ID <?php echo esc_html($id); ?></span>
                                            <?php if ($grade) : ?><span><?php echo esc_html($grade); ?>年级</span><?php endif; ?>
                                            <span><?php echo esc_html('supplementary' === $type ? '教辅配套课' : '专题课程'); ?></span>
                                            <span><?php echo esc_html(count($lessons)); ?> 个课时</span>
                                            <span>冻结于 <?php echo esc_html(get_the_modified_date('Y-m-d H:i', $id)); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mathcourse-trash-actions">
                                    <form method="post">
                                        <input type="hidden" name="mathcourse_trash_action" value="restore">
                                        <input type="hidden" name="course_id" value="<?php echo esc_attr($id); ?>">
                                        <input type="hidden" name="mathcourse_trash_nonce" value="<?php echo esc_attr($delete_nonce); ?>">
                                        <button type="submit" class="button">恢复课程</button>
                                    </form>
                                    <form method="post" class="mathcourse-trash-delete-form" onsubmit="return confirm('彻底删除后将无法恢复，并会删除课程、专题、课时、授权、进度、激活码以及该课程的视频文件。确定继续吗？');">
                                        <input type="hidden" name="mathcourse_trash_action" value="delete_permanently">
                                        <input type="hidden" name="course_id" value="<?php echo esc_attr($id); ?>">
                                        <input type="hidden" name="mathcourse_trash_nonce" value="<?php echo esc_attr($delete_nonce); ?>">
                                        <button type="submit" class="button mathcourse-danger">彻底删除</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function get_trashed_courses(Adapter $adapter) {
        return get_posts(array(
            'post_type' => $adapter->get_course_post_type(),
            'post_status' => 'trash',
            'posts_per_page' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
        ));
    }

    private function delete_course_permanently($course_id, Adapter $adapter) {
        $course_id = absint($course_id);
        if ( ! $course_id ) return false;

        $topics = $adapter->get_topics($course_id, true);
        $lesson_ids = array();
        foreach ( $topics as $topic ) {
            foreach ( $adapter->get_lessons($topic->ID, true) as $lesson ) {
                $lesson_ids[] = (int) $lesson->ID;
            }
        }

        // 停止冻结课程仍在等待执行的转换任务，并删除其本地媒体目录/待转换 MP4。
        foreach ( $lesson_ids as $lesson_id ) {
            wp_clear_scheduled_hook('mathcourse_convert_video', array($lesson_id));
        }
        $this->remove_course_media($course_id);
        $this->cleanup_database_records($course_id, $lesson_ids);
        $this->cleanup_completion_meta($lesson_ids);

        foreach ( $lesson_ids as $lesson_id ) {
            wp_delete_post($lesson_id, true);
        }
        foreach ( $topics as $topic ) {
            wp_delete_post((int) $topic->ID, true);
        }

        return (bool) wp_delete_post($course_id, true);
    }

    private function cleanup_database_records($course_id, $lesson_ids) {
        global $wpdb;
        $course_id = absint($course_id);

        $tables = array(
            $wpdb->prefix . 'mathcourse_access',
            $wpdb->prefix . 'mathcourse_activation_codes',
        );
        foreach ( $tables as $table ) {
            if ( $this->table_exists($table) ) {
                $wpdb->delete($table, array('course_id' => $course_id), array('%d'));
            }
        }

        $video_table = $wpdb->prefix . 'mathcourse_videos';
        if ( $this->table_exists($video_table) && ! empty($lesson_ids) ) {
            foreach ( $lesson_ids as $lesson_id ) {
                $wpdb->delete($video_table, array('lesson_id' => absint($lesson_id)), array('%d'));
            }
        }

        $learning_table = $wpdb->prefix . 'mathcourse_learning';
        if ( $this->table_exists($learning_table) ) {
            $wpdb->delete($learning_table, array('course_id' => $course_id), array('%d'));
        }
    }

    private function cleanup_completion_meta($lesson_ids) {
        $lesson_ids = array_values(array_unique(array_map('absint', $lesson_ids)));
        if ( empty($lesson_ids) ) return;

        $users = get_users(array('fields' => array('ID'), 'number' => -1));
        foreach ( $users as $user ) {
            $completed = get_user_meta($user->ID, 'mc_completed_lessons', true);
            if ( ! is_array($completed) ) continue;
            $original = array_values(array_unique(array_map('intval', $completed)));
            $filtered = array_values(array_diff($original, $lesson_ids));
            if ( $filtered !== $original ) {
                update_user_meta($user->ID, 'mc_completed_lessons', $filtered);
            }
        }
    }

    private function table_exists($table) {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        return $found === $table;
    }

    private function get_course_media_slug($course_id) {
        $course_id = absint($course_id);
        $title = $course_id ? get_the_title($course_id) : '';
        $slug = '';
        if ( $title && class_exists('\\Transliterator') ) {
            $transliterator = \\Transliterator::create('Han-Latin; Latin-ASCII; Lower()');
            if ( $transliterator ) $slug = $transliterator->transliterate($title);
        }
        $slug = sanitize_title($slug);
        if ( ! $slug && $course_id ) $slug = sanitize_title(get_post_field('post_name', $course_id));
        if ( ! $slug ) $slug = 'course-' . $course_id;
        return $slug;
    }

    private function remove_course_media($course_id) {
        $course_id = absint($course_id);
        $roots = array();
        $upload_roots = array();

        if ( defined('MATHCOURSE_MEDIA_ROOT') && MATHCOURSE_MEDIA_ROOT ) {
            $roots[] = untrailingslashit(MATHCOURSE_MEDIA_ROOT);
            $upload_roots[] = dirname(untrailingslashit(MATHCOURSE_MEDIA_ROOT)) . '/uploads';
        }
        if ( defined('MATHCOURSE_VIDEO_UPLOAD_ROOT') && MATHCOURSE_VIDEO_UPLOAD_ROOT ) {
            $upload_roots[] = untrailingslashit(MATHCOURSE_VIDEO_UPLOAD_ROOT);
        }
        $roots = array_unique($roots);
        $upload_roots = array_unique($upload_roots);

        foreach ( $roots as $root ) {
            $root = untrailingslashit($root);
            if ( ! $root || ! is_dir($root) ) continue;
            // 新格式：课程名称全拼目录；同时兼容旧版 course-{ID} 目录。
            $media_dirs = array_unique(array(
                $root . '/' . $this->get_course_media_slug($course_id),
                $root . '/course-' . $course_id,
            ));
            foreach ( $media_dirs as $course_dir ) {
                $this->remove_dir($course_dir, $root);
            }
        }

        // 转换成功后原 MP4 会自动删除；如果课程在转换前被冻结，这里清理遗留源文件。
        foreach ( $upload_roots as $upload_root ) {
            $upload_root = untrailingslashit($upload_root);
            if ( ! $upload_root || ! is_dir($upload_root) ) continue;
            $files = glob($upload_root . '/course-' . $course_id . '-lesson-*.mp4');
            if ( is_array($files) ) {
                foreach ( $files as $file ) {
                    if ( is_file($file) || is_link($file) ) @unlink($file);
                }
            }
        }
    }

    private function remove_dir($dir, $allowed_root) {
        $dir = realpath($dir);
        $allowed_root = realpath($allowed_root);
        if ( ! $dir || ! $allowed_root || 0 !== strpos($dir, $allowed_root . DIRECTORY_SEPARATOR) ) return;
        if ( ! is_dir($dir) ) return;

        $items = scandir($dir);
        if ( ! is_array($items) ) return;
        foreach ( $items as $item ) {
            if ( '.' === $item || '..' === $item ) continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if ( is_dir($path) && ! is_link($path) ) {
                $this->remove_dir($path, $allowed_root);
            } elseif ( is_file($path) || is_link($path) ) {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
