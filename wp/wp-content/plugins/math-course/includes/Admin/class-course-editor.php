<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

class Course_Editor {

	const TOPIC_POST_TYPE = 'topics';

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to access this page.', 'mathcourse' ) ); }
		if ( ! function_exists( 'tutor' ) ) { $this->notice( 'MathCourse 需要 Tutor LMS 4.0.4。' ); return; }

		$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
		if ( ! $course_id || tutor()->course_post_type !== get_post_type( $course_id ) ) { $this->notice( '课程不存在。' ); return; }
		if ( ! current_user_can( 'edit_post', $course_id ) ) { wp_die( esc_html__( 'You do not have permission to edit this course.', 'mathcourse' ) ); }

		$message = '';
		$message_type = 'success';
		$this->handle_actions( $course_id, $message, $message_type );

		$post = get_post( $course_id );
		$type = get_post_meta( $course_id, '_mathcourse_type', true );
		$grade = get_post_meta( $course_id, '_mathcourse_grade', true );
		$cover = get_post_meta( $course_id, '_mathcourse_cover', true );
		$topics = get_posts( array( 'post_type' => self::TOPIC_POST_TYPE, 'post_parent' => $course_id, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC' ) ) );
		$lesson_post_type = tutor()->lesson_post_type;
		$edit_lesson_id = isset( $_GET['lesson_id'] ) ? absint( $_GET['lesson_id'] ) : 0;
		$edit_lesson = $edit_lesson_id ? get_post( $edit_lesson_id ) : null;
		if ( $edit_lesson && ( $lesson_post_type !== $edit_lesson->post_type || $course_id !== $this->get_course_id_from_topic( $edit_lesson->post_parent ) ) ) { $edit_lesson = null; }
		?>
		<div class="wrap">
			<div style="display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:18px;">
				<div><a href="<?php echo esc_url( admin_url( 'admin.php?page=mathcourse-courses' ) ); ?>">← 返回课程管理</a><h1 style="margin-bottom:4px;">编辑课程</h1><p style="margin-top:0;color:#646970;">课程基本信息、专题和课时统一在 MathCourse 管理，底层数据仍由 Tutor LMS 保存。</p></div>
				<div><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'create-course', 'course_id' => $course_id ), admin_url( 'admin.php' ) ) ); ?>">打开 Tutor LMS</a></div>
			</div>
			<?php if ( $message ) : ?><div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div><?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'mathcourse_edit_course_' . $course_id ); ?><input type="hidden" name="mathcourse_action" value="save_course">
				<div style="display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:20px;max-width:1180px;">
					<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:24px;">
						<p><label for="course_title"><strong>课程名称</strong></label><input id="course_title" name="course_title" type="text" class="large-text" value="<?php echo esc_attr( $post->post_title ); ?>" required></p>
						<p><label for="course_description"><strong>课程简介</strong></label><textarea id="course_description" name="course_description" rows="8" class="large-text"><?php echo esc_textarea( $post->post_content ); ?></textarea></p>
						<div style="margin-top:28px;padding-top:20px;border-top:1px solid #eee;"><h2 style="font-size:18px;margin:0;">课程内容</h2><p style="color:#646970;margin-top:6px;">按「专题 → 课时」组织。课时可以直接编辑名称、简介、试看和发布状态。</p>
						<?php if ( $edit_lesson ) { $this->render_lesson_editor( $course_id, $edit_lesson ); } ?>
						<?php if ( empty( $topics ) ) : ?><div style="padding:28px;text-align:center;background:#f6f7f7;border-radius:10px;margin-top:15px;"><strong>还没有专题</strong><div style="color:#646970;margin-top:5px;">先创建第一个专题，再添加课时。</div></div>
						<?php else : ?><div style="margin-top:15px;display:flex;flex-direction:column;gap:12px;">
						<?php foreach ( $topics as $topic ) : $lessons = get_posts( array( 'post_type' => $lesson_post_type, 'post_parent' => $topic->ID, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC' ) ) ); ?>
						<div style="border:1px solid #dcdcde;border-radius:10px;overflow:hidden;background:#fff;"><div style="padding:14px 16px;background:#f6f7f7;display:flex;align-items:center;justify-content:space-between;"><div><strong><?php echo esc_html( $topic->post_title ); ?></strong><span style="color:#646970;margin-left:8px;">（<?php echo esc_html( count( $lessons ) ); ?> 个课时）</span></div><button type="submit" name="mathcourse_action" value="delete_topic_<?php echo esc_attr( $topic->ID ); ?>" class="button-link-delete" onclick="return confirm('确定删除这个专题及其课时吗？');">删除专题</button></div>
						<div style="padding:8px 16px 14px;"><?php if ( empty( $lessons ) ) : ?><div style="padding:12px 0;color:#646970;">暂无课时。</div><?php else : ?><?php foreach ( $lessons as $index => $lesson ) : ?>
						<?php $lesson_edit_url = add_query_arg( array( 'page' => 'mathcourse-course-edit', 'course_id' => $course_id, 'lesson_id' => $lesson->ID ), admin_url( 'admin.php' ) ); $preview = get_post_meta( $lesson->ID, '_mathcourse_preview', true ); ?>
						<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #f0f0f1;"><div><span style="display:inline-block;width:28px;color:#8c8f94;"><?php echo esc_html( $index + 1 ); ?>.</span><strong><?php echo esc_html( $lesson->post_title ); ?></strong><?php if ( 'yes' === $preview ) : ?><span style="color:#2271b1;margin-left:8px;">试看</span><?php endif; ?></div><div style="display:flex;gap:10px;"><a href="<?php echo esc_url( $lesson_edit_url ); ?>">编辑</a><button type="submit" name="mathcourse_action" value="delete_lesson_<?php echo esc_attr( $lesson->ID ); ?>" class="button-link-delete" onclick="return confirm('确定删除这个课时吗？');">删除</button></div></div>
						<?php endforeach; ?><?php endif; ?><div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;"><input type="text" name="lesson_title_<?php echo esc_attr( $topic->ID ); ?>" placeholder="输入课时名称，例如：三角形基础" style="min-width:320px;"><button type="submit" name="mathcourse_action" value="add_lesson_<?php echo esc_attr( $topic->ID ); ?>" class="button">＋ 添加课时</button></div></div></div>
						<?php endforeach; ?></div><?php endif; ?>
						<div style="margin-top:15px;padding:16px;background:#f6f7f7;border-radius:10px;"><input type="text" name="topic_title" placeholder="输入专题名称，例如：第1章 三角形" style="min-width:340px;"><button type="submit" name="mathcourse_action" value="add_topic" class="button button-primary">＋ 添加专题</button></div></div>
						<div style="margin-top:24px;padding-top:18px;border-top:1px solid #eee;"><button type="submit" name="mathcourse_action" value="save_course" class="button button-primary button-large">保存课程</button></div>
					</div>
					<div><div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px;"><h2 style="font-size:16px;margin-top:0;">课程属性</h2>
						<p><label for="course_type"><strong>课程类型</strong></label><select id="course_type" name="course_type" style="width:100%;margin-top:6px;"><option value="topic" <?php selected( $type, 'topic' ); ?>>专题课程</option><option value="supplementary" <?php selected( $type, 'supplementary' ); ?>>教辅配套课</option></select></p>
						<p><label for="course_grade"><strong>年级</strong></label><select id="course_grade" name="course_grade" style="width:100%;margin-top:6px;"><option value="">请选择</option><option value="7" <?php selected( $grade, '7' ); ?>>七年级</option><option value="8" <?php selected( $grade, '8' ); ?>>八年级</option><option value="9" <?php selected( $grade, '9' ); ?>>九年级</option><option value="10" <?php selected( $grade, '10' ); ?>>高一</option><option value="11" <?php selected( $grade, '11' ); ?>>高二</option><option value="12" <?php selected( $grade, '12' ); ?>>高三</option></select></p>
						<p><label for="course_status"><strong>状态</strong></label><select id="course_status" name="course_status" style="width:100%;margin-top:6px;"><option value="draft" <?php selected( $post->post_status, 'draft' ); ?>>草稿</option><option value="publish" <?php selected( $post->post_status, 'publish' ); ?>>已发布</option><option value="private" <?php selected( $post->post_status, 'private' ); ?>>私密</option></select></p>
						<p><label for="course_cover"><strong>课程封面 URL</strong></label><input id="course_cover" name="course_cover" type="url" style="width:100%;" value="<?php echo esc_attr( $cover ); ?>" placeholder="https://..."></p></div></div>
				</div>
			</form>
		</div>
		<?php
	}

	private function render_lesson_editor( $course_id, $lesson ) {
		$preview = get_post_meta( $lesson->ID, '_mathcourse_preview', true );
		$video = get_post_meta( $lesson->ID, '_mathcourse_video', true );
		$page = get_post_meta( $lesson->ID, '_mathcourse_page', true );
		?>
		<div style="margin-top:16px;padding:20px;border:1px solid #2271b1;border-radius:10px;background:#f8fbff;"><div style="display:flex;justify-content:space-between;align-items:center;"><h3 style="margin:0;">编辑课时</h3><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'mathcourse-course-edit', 'course_id' => $course_id ), admin_url( 'admin.php' ) ) ); ?>">关闭</a></div>
		<input type="hidden" name="editing_lesson_id" value="<?php echo esc_attr( $lesson->ID ); ?>"><p><label for="lesson_title_edit"><strong>课时名称</strong></label><input id="lesson_title_edit" name="lesson_title_edit" type="text" class="large-text" value="<?php echo esc_attr( $lesson->post_title ); ?>"></p>
		<p><label for="lesson_description_edit"><strong>课时简介</strong></label><textarea id="lesson_description_edit" name="lesson_description_edit" rows="5" class="large-text"><?php echo esc_textarea( $lesson->post_content ); ?></textarea></p><p><label><input type="checkbox" name="lesson_preview_edit" value="yes" <?php checked( $preview, 'yes' ); ?>> <strong>允许试看</strong></label></p><p><label for="lesson_status_edit"><strong>状态</strong></label><select id="lesson_status_edit" name="lesson_status_edit"><option value="draft" <?php selected( $lesson->post_status, 'draft' ); ?>>草稿</option><option value="publish" <?php selected( $lesson->post_status, 'publish' ); ?>>已发布</option><option value="private" <?php selected( $lesson->post_status, 'private' ); ?>>私密</option></select></p>
		<div style="padding-top:14px;border-top:1px solid #dbe7f0;"><strong>教材信息</strong><div style="display:grid;grid-template-columns:160px minmax(0,1fr);gap:12px;margin-top:10px;"><div><label for="lesson_page_edit"><strong>教材页码</strong></label><input id="lesson_page_edit" name="lesson_page_edit" type="text" value="<?php echo esc_attr( $page ); ?>" class="regular-text" placeholder="例如：P9"></div><div><label for="lesson_video_edit"><strong>视频资源标识</strong></label><input id="lesson_video_edit" name="lesson_video_edit" type="text" value="<?php echo esc_attr( $video ); ?>" class="large-text" placeholder="视频 ID 或资源标识"></div></div><p style="color:#646970;margin:8px 0 0;">视频字段仅保存资源标识；HLS 地址和 Token 鉴权由后续播放模块处理，不在后台页面直接暴露。</p></div><p><button type="submit" name="mathcourse_action" value="save_lesson" class="button button-primary">保存课时</button></p></div>
		<?php
	}

	private function handle_actions( $course_id, &$message, &$message_type ) {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['mathcourse_action'] ) ) { return; }
		check_admin_referer( 'mathcourse_edit_course_' . $course_id );
		$action = sanitize_key( wp_unslash( $_POST['mathcourse_action'] ) );
		if ( 'save_course' === $action ) {
			$title = isset( $_POST['course_title'] ) ? sanitize_text_field( wp_unslash( $_POST['course_title'] ) ) : '新课程'; $description = isset( $_POST['course_description'] ) ? wp_kses_post( wp_unslash( $_POST['course_description'] ) ) : ''; $type = isset( $_POST['course_type'] ) ? sanitize_key( wp_unslash( $_POST['course_type'] ) ) : 'topic'; $grade = isset( $_POST['course_grade'] ) ? sanitize_key( wp_unslash( $_POST['course_grade'] ) ) : ''; $cover = isset( $_POST['course_cover'] ) ? esc_url_raw( wp_unslash( $_POST['course_cover'] ) ) : ''; $status = isset( $_POST['course_status'] ) ? sanitize_key( wp_unslash( $_POST['course_status'] ) ) : 'draft';
			if ( ! in_array( $type, array( 'topic', 'supplementary' ), true ) ) { $type = 'topic'; } if ( ! in_array( $grade, array( '', '7', '8', '9', '10', '11', '12' ), true ) ) { $grade = ''; } if ( ! in_array( $status, array( 'draft', 'publish', 'private' ), true ) ) { $status = 'draft'; }
			$result = wp_update_post( wp_slash( array( 'ID' => $course_id, 'post_title' => $title ? $title : '新课程', 'post_content' => $description, 'post_status' => $status ) ), true ); if ( is_wp_error( $result ) ) { $message = $result->get_error_message(); $message_type = 'error'; return; }
			update_post_meta( $course_id, '_mathcourse_type', $type ); update_post_meta( $course_id, '_mathcourse_grade', $grade ); update_post_meta( $course_id, '_mathcourse_cover', $cover ); $message = '课程已保存。'; return;
		}
		if ( 'save_lesson' === $action ) {
			$lesson_id = isset( $_POST['editing_lesson_id'] ) ? absint( $_POST['editing_lesson_id'] ) : 0; $lesson = $lesson_id ? get_post( $lesson_id ) : null;
			if ( ! $lesson || tutor()->lesson_post_type !== $lesson->post_type || $course_id !== $this->get_course_id_from_topic( $lesson->post_parent ) ) { $message = '课时不存在或不属于当前课程。'; $message_type = 'error'; return; }
			if ( ! current_user_can( 'edit_post', $lesson_id ) ) { $message = '没有编辑这个课时的权限。'; $message_type = 'error'; return; }
			$title = isset( $_POST['lesson_title_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['lesson_title_edit'] ) ) : $lesson->post_title; $content = isset( $_POST['lesson_description_edit'] ) ? wp_kses_post( wp_unslash( $_POST['lesson_description_edit'] ) ) : ''; $preview = isset( $_POST['lesson_preview_edit'] ) ? 'yes' : 'no'; $status = isset( $_POST['lesson_status_edit'] ) ? sanitize_key( $_POST['lesson_status_edit'] ) : 'draft'; $video = isset( $_POST['lesson_video_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['lesson_video_edit'] ) ) : ''; $page = isset( $_POST['lesson_page_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['lesson_page_edit'] ) ) : '';
			if ( ! in_array( $status, array( 'draft', 'publish', 'private' ), true ) ) { $status = 'draft'; }
			$result = wp_update_post( wp_slash( array( 'ID' => $lesson_id, 'post_title' => $title ? $title : '未命名课时', 'post_content' => $content, 'post_status' => $status ) ), true ); if ( is_wp_error( $result ) ) { $message = $result->get_error_message(); $message_type = 'error'; return; }
			update_post_meta( $lesson_id, '_mathcourse_preview', $preview ); update_post_meta( $lesson_id, '_mathcourse_video', $video ); update_post_meta( $lesson_id, '_mathcourse_page', $page ); $message = '课时已保存。'; return;
		}
		if ( 'add_topic' === $action ) { $title = isset( $_POST['topic_title'] ) ? sanitize_text_field( wp_unslash( $_POST['topic_title'] ) ) : ''; if ( ! $title ) { $message = '请输入专题名称。'; $message_type = 'error'; return; } $result = wp_insert_post( wp_slash( array( 'post_type' => self::TOPIC_POST_TYPE, 'post_title' => $title, 'post_status' => 'publish', 'post_parent' => $course_id ) ), true ); $message = is_wp_error( $result ) ? $result->get_error_message() : '专题已创建。'; $message_type = is_wp_error( $result ) ? 'error' : 'success'; return; }
		if ( 0 === strpos( $action, 'add_lesson_' ) ) { $topic_id = absint( str_replace( 'add_lesson_', '', $action ) ); $topic = get_post( $topic_id ); $title = isset( $_POST[ 'lesson_title_' . $topic_id ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'lesson_title_' . $topic_id ] ) ) : ''; if ( ! $topic || self::TOPIC_POST_TYPE !== $topic->post_type || $course_id !== $this->get_course_id_from_topic( $topic_id ) ) { $message = '专题不存在或不属于当前课程。'; $message_type = 'error'; return; } if ( ! $title ) { $message = '请输入课时名称。'; $message_type = 'error'; return; } $result = wp_insert_post( wp_slash( array( 'post_type' => tutor()->lesson_post_type, 'post_title' => $title, 'post_status' => 'draft', 'post_parent' => $topic_id, 'menu_order' => $this->next_lesson_order( $topic_id ) ) ), true ); $message = is_wp_error( $result ) ? $result->get_error_message() : '课时已创建。'; $message_type = is_wp_error( $result ) ? 'error' : 'success'; return; }
		if ( 0 === strpos( $action, 'delete_lesson_' ) ) { $lesson_id = absint( str_replace( 'delete_lesson_', '', $action ) ); $lesson = get_post( $lesson_id ); if ( ! $lesson || tutor()->lesson_post_type !== $lesson->post_type || $course_id !== $this->get_course_id_from_topic( $lesson->post_parent ) ) { $message = '课时不存在或不属于当前课程。'; $message_type = 'error'; return; } $result = wp_delete_post( $lesson_id, true ); $message = $result ? '课时已删除。' : '课时删除失败。'; $message_type = $result ? 'success' : 'error'; return; }
		if ( 0 === strpos( $action, 'delete_topic_' ) ) { $topic_id = absint( str_replace( 'delete_topic_', '', $action ) ); $topic = get_post( $topic_id ); if ( ! $topic || self::TOPIC_POST_TYPE !== $topic->post_type || $course_id !== $this->get_course_id_from_topic( $topic_id ) ) { $message = '专题不存在或不属于当前课程。'; $message_type = 'error'; return; } $lessons = get_posts( array( 'post_type' => tutor()->lesson_post_type, 'post_parent' => $topic_id, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ); foreach ( $lessons as $lesson_id ) { wp_delete_post( $lesson_id, true ); } $result = wp_delete_post( $topic_id, true ); $message = $result ? '专题及其课时已删除。' : '专题删除失败。'; $message_type = $result ? 'success' : 'error'; }
	}

	private function next_lesson_order( $topic_id ) { $lessons = get_posts( array( 'post_type' => tutor()->lesson_post_type, 'post_parent' => $topic_id, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ); $max = -1; foreach ( $lessons as $lesson_id ) { $max = max( $max, (int) get_post_field( 'menu_order', $lesson_id ) ); } return $max + 1; }
	private function get_course_id_from_topic( $topic_id ) { $topic = get_post( $topic_id ); return ( $topic && self::TOPIC_POST_TYPE === $topic->post_type ) ? (int) $topic->post_parent : 0; }
	private function notice( $message, $type = 'error' ) { echo '<div class="wrap"><h1>课程编辑</h1><div class="notice notice-' . esc_attr( $type ) . ' inline"><p>' . esc_html( $message ) . '</p></div></div>'; }
}
