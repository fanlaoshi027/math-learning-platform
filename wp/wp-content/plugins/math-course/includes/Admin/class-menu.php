<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

class Menu {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }

    public function register_menu() {
        add_menu_page( 'MathCourse', '数学课程', 'manage_options', 'mathcourse', array( $this, 'dashboard' ), 'dashicons-welcome-learn-more', 30 );
        add_submenu_page( 'mathcourse', '课程管理', '课程管理', 'manage_options', 'mathcourse-courses', array( $this, 'courses_page' ) );
        add_submenu_page( 'mathcourse', '批量创建课时', '批量创建课时', 'manage_options', 'mathcourse-batch', array( $this, 'batch_page' ) );
        add_submenu_page( 'mathcourse', '课程授权', '学员授权', 'manage_options', 'mathcourse-access', array( $this, 'access_page' ) );
        add_submenu_page( 'mathcourse', '学习进度', '学习进度', 'manage_options', 'mathcourse-progress', array( $this, 'progress_page' ) );
        add_submenu_page( 'mathcourse', '演示数据', '一键导入演示数据', 'manage_options', 'mathcourse-demo', array( $this, 'demo_page' ) );
        add_submenu_page( 'mathcourse', '设置', '系统设置', 'manage_options', 'mathcourse-settings', array( $this, 'settings_page' ) );
        add_submenu_page( null, '编辑课程', '编辑课程', 'manage_options', 'mathcourse-course-edit', array( $this, 'course_edit_page' ) );
    }

    public function dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $course_count = 0;
        $published_courses = 0;
        $lesson_count = 0;
        $topic_courses = 0;
        $supplementary_courses = 0;

        if ( function_exists( 'tutor' ) ) {
            $all_course_ids = get_posts( array(
                'post_type'      => tutor()->course_post_type,
                'post_status'    => array( 'publish', 'draft', 'private' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ) );
            $course_count = count( $all_course_ids );
            $published_courses = count( get_posts( array(
                'post_type'      => tutor()->course_post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ) ) );
            $lesson_count = count( get_posts( array(
                'post_type'      => tutor()->lesson_post_type,
                'post_status'    => array( 'publish', 'draft', 'private' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ) ) );
            foreach ( $all_course_ids as $course_id ) {
                if ( 'supplementary' === get_post_meta( $course_id, '_mathcourse_type', true ) ) {
                    $supplementary_courses++;
                } else {
                    $topic_courses++;
                }
            }
        }

        $user_count = count( get_users( array( 'fields' => 'ids', 'number' => 9999 ) ) );
        $course_url = admin_url( 'admin.php?page=mathcourse-courses' );
        $demo_url = admin_url( 'admin.php?page=mathcourse-demo' );
        $batch_url = admin_url( 'admin.php?page=mathcourse-batch' );
        $access_url = admin_url( 'admin.php?page=mathcourse-access' );
        $progress_url = admin_url( 'admin.php?page=mathcourse-progress' );
        $settings_url = admin_url( 'admin.php?page=mathcourse-settings' );
        ?>
        <div class="wrap mathcourse-admin-wrap">
            <div class="mathcourse-admin-header mathcourse-admin-hero">
                <div>
                    <span class="mathcourse-eyebrow">MATHCOURSE · TEACHER WORKSPACE</span>
                    <h1>樊老师数学 · 课程工作台</h1>
                    <p>课程、课时、学员授权与学习进度统一管理。日常操作优先使用 MathCourse，Tutor LMS 保留为高级维护入口。</p>
                </div>
                <div class="mathcourse-hero-actions">
                    <a class="button mathcourse-primary" href="<?php echo esc_url( $course_url ); ?>">课程管理</a>
                    <a class="button" href="<?php echo esc_url( $demo_url ); ?>">演示数据</a>
                </div>
            </div>

            <div class="mathcourse-stat-grid mathcourse-stat-grid--dashboard">
                <div class="mathcourse-stat-card mathcourse-stat-card--blue"><div class="mathcourse-stat-icon">课</div><div><div class="mathcourse-stat-title">课程总数</div><div class="mathcourse-stat-num"><?php echo esc_html( $course_count ); ?></div><div class="mathcourse-stat-meta"><?php echo esc_html( $topic_courses ); ?> 个专题 · <?php echo esc_html( $supplementary_courses ); ?> 个配套</div></div></div>
                <div class="mathcourse-stat-card mathcourse-stat-card--green"><div class="mathcourse-stat-icon">发</div><div><div class="mathcourse-stat-title">已发布课程</div><div class="mathcourse-stat-num"><?php echo esc_html( $published_courses ); ?></div><div class="mathcourse-stat-meta">可在前台展示</div></div></div>
                <div class="mathcourse-stat-card mathcourse-stat-card--purple"><div class="mathcourse-stat-icon">课</div><div><div class="mathcourse-stat-title">课时总数</div><div class="mathcourse-stat-num"><?php echo esc_html( $lesson_count ); ?></div><div class="mathcourse-stat-meta">Tutor LMS Lesson</div></div></div>
                <div class="mathcourse-stat-card mathcourse-stat-card--orange"><div class="mathcourse-stat-icon">学</div><div><div class="mathcourse-stat-title">学员账号</div><div class="mathcourse-stat-num"><?php echo esc_html( $user_count ); ?></div><div class="mathcourse-stat-meta">WordPress 用户</div></div></div>
            </div>

            <div class="mathcourse-admin-grid">
                <div class="mathcourse-card">
                    <div class="mathcourse-card-title">
                        <div><h2>日常工具</h2><p class="mathcourse-card-subtitle">把高频操作集中到一个页面</p></div>
                    </div>
                    <div class="mathcourse-tool-grid">
                        <a class="mathcourse-tool-card" href="<?php echo esc_url( $course_url ); ?>"><span class="mathcourse-tool-icon">▣</span><strong>课程管理</strong><small>课程、类型、年级、课时</small></a>
                        <a class="mathcourse-tool-card" href="<?php echo esc_url( $batch_url ); ?>"><span class="mathcourse-tool-icon">＋</span><strong>批量创建课时</strong><small>适合大培优页码课程</small></a>
                        <a class="mathcourse-tool-card" href="<?php echo esc_url( $access_url ); ?>"><span class="mathcourse-tool-icon">✓</span><strong>学员授权</strong><small>按课程开通与撤销权限</small></a>
                        <a class="mathcourse-tool-card" href="<?php echo esc_url( $progress_url ); ?>"><span class="mathcourse-tool-icon">◷</span><strong>学习进度</strong><small>查看课程完成情况</small></a>
                        <a class="mathcourse-tool-card" href="<?php echo esc_url( $demo_url ); ?>"><span class="mathcourse-tool-icon">◆</span><strong>一键演示数据</strong><small>快速验收整套前台 UI</small></a>
                        <a class="mathcourse-tool-card" href="<?php echo esc_url( $settings_url ); ?>"><span class="mathcourse-tool-icon">⚙</span><strong>系统设置</strong><small>播放与平台基础配置</small></a>
                    </div>
                </div>

                <div class="mathcourse-card mathcourse-quick-card">
                    <div class="mathcourse-card-title"><div><h2>课程结构</h2><p class="mathcourse-card-subtitle">按照产品数据模型查看</p></div></div>
                    <div class="mathcourse-structure">
                        <div class="mathcourse-structure-row"><span class="mathcourse-structure-dot is-blue"></span><div><strong>专题课程</strong><small>Course → Topic → Lesson</small></div><b><?php echo esc_html( $topic_courses ); ?></b></div>
                        <div class="mathcourse-structure-row"><span class="mathcourse-structure-dot is-green"></span><div><strong>大培优配套</strong><small>Course → 页码 Lesson</small></div><b><?php echo esc_html( $supplementary_courses ); ?></b></div>
                        <div class="mathcourse-structure-row"><span class="mathcourse-structure-dot is-purple"></span><div><strong>授权体系</strong><small>用户 → 课程授权</small></div><b>独立</b></div>
                        <div class="mathcourse-structure-row"><span class="mathcourse-structure-dot is-orange"></span><div><strong>视频播放</strong><small>权限 → HLS → Player</small></div><b>安全</b></div>
                    </div>
                    <div class="mathcourse-tip"><strong>开发原则</strong><span>MathCourse 负责业务与体验，Tutor LMS 负责底层 LMS 能力。</span></div>
                </div>
            </div>

            <div class="mathcourse-card">
                <div class="mathcourse-card-title">
                    <div><h2>最近课程</h2><p class="mathcourse-card-subtitle">快速进入日常维护</p></div>
                    <a href="<?php echo esc_url( $course_url ); ?>">查看全部 →</a>
                </div>
                <?php if ( function_exists( 'tutor' ) ) : ?>
                    <table class="mathcourse-table mathcourse-table--dashboard">
                        <thead><tr><th>课程名称</th><th>类型</th><th>年级</th><th>课时</th><th>状态</th><th>操作</th></tr></thead>
                        <tbody>
                        <?php
                        $courses = get_posts( array(
                            'post_type'      => tutor()->course_post_type,
                            'post_status'    => array( 'publish', 'draft', 'private' ),
                            'posts_per_page' => 8,
                            'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
                        ) );
                        $grade_labels = array( '7' => '七年级', '8' => '八年级', '9' => '九年级', '10' => '高一', '11' => '高二', '12' => '高三' );
                        foreach ( $courses as $course ) :
                            $edit_url = add_query_arg( array( 'page' => 'mathcourse-course-edit', 'course_id' => $course->ID ), admin_url( 'admin.php' ) );
                            $type = get_post_meta( $course->ID, '_mathcourse_type', true );
                            $grade = get_post_meta( $course->ID, '_mathcourse_grade', true );
                            $status_class = 'publish' === $course->post_status ? 'is-published' : 'is-draft';
                            $status_label = 'publish' === $course->post_status ? '已发布' : ( 'private' === $course->post_status ? '私密' : '草稿' );
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $course->post_title ); ?></strong></td>
                                <td><span class="mathcourse-type-chip <?php echo 'supplementary' === $type ? 'is-supplementary' : 'is-topic'; ?>"><?php echo 'supplementary' === $type ? '大培优配套' : '专题课程'; ?></span></td>
                                <td><?php echo esc_html( isset( $grade_labels[ $grade ] ) ? $grade_labels[ $grade ] : ( $grade ? '年级 ' . $grade : '—' ) ); ?></td>
                                <td><?php echo esc_html( ( new \MathCourse\Tutor\Adapter() )->get_course_lesson_count( $course->ID ) ); ?></td>
                                <td><span class="mathcourse-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
                                <td><a class="mathcourse-row-action" href="<?php echo esc_url( $edit_url ); ?>">编辑 →</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ( empty( $courses ) ) : ?><tr><td colspan="6"><div class="mathcourse-empty">暂无课程。可以先使用“一键导入演示数据”生成完整测试环境。</div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <div class="mathcourse-empty">Tutor LMS 尚未启用。启用 Tutor LMS 4.0.4 后，MathCourse 才能接管课程数据。</div>
                <?php endif; ?>
            </div>

            <div class="mathcourse-admin-footer-tip">
                <span>前台参考 UI · 课程中心 · 学习中心 · 大培优页码体系 · 课程授权 · 视频安全</span>
                <a href="<?php echo esc_url( $demo_url ); ?>">导入演示数据开始验收 →</a>
            </div>
        </div>
        <?php
    }

    public function courses_page() { if ( class_exists( 'MathCourse\\Admin\\Course_Page' ) ) ( new Course_Page() )->render(); }
    public function batch_page() { if ( class_exists( 'MathCourse\\Admin\\Batch_Manager' ) ) ( new Batch_Manager() )->render(); }
    public function demo_page() { if ( class_exists( 'MathCourse\\Admin\\Demo_Importer' ) ) ( new Demo_Importer() )->render(); }
    public function course_edit_page() { if ( class_exists( 'MathCourse\\Admin\\Course_Editor' ) ) ( new Course_Editor() )->render(); }
    public function access_page() { if ( class_exists( 'MathCourse\\Admin\\Access_Page' ) ) ( new Access_Page() )->render(); }
    public function progress_page() { if ( class_exists( 'MathCourse\\Admin\\Progress_Page' ) ) ( new Progress_Page() )->render(); }
    public function settings_page() { if ( class_exists( 'MathCourse\\Admin\\Settings' ) ) ( new Settings() )->render(); }
}
