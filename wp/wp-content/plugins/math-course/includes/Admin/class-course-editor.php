<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

use MathCourse\Tutor\Adapter;

class Course_Editor {

	private $tutor;

	public function __construct() {
		$this->tutor = new Adapter();
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to access this page.', 'mathcourse' ) ); }
		if ( ! $this->tutor->is_available() ) { $this->notice( 'MathCourse 需要 Tutor LMS 4.0.4。' ); return; }

		$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
		$post = $course_id ? $this->tutor->get_course( $course_id ) : null;
		if ( ! $post ) { $this->notice( '课程不存在。' ); return; }
		if ( ! current_user_can( 'edit_post', $course_id ) ) { wp_die( esc_html__( 'You do not have permission to edit this course.', 'mathcourse' ) ); }

		$message = '';
		$message_type = 'success';
		$this->handle_actions( $course_id, $message, $message_type );

		$post = $this->tutor->get_course( $course_id );
		$type = get_post_meta( $course_id, '_mathcourse_type', true );
		$grade = get_post_meta( $course_id, '_mathcourse_grade', true );
		$cover = get_post_meta( $course_id, '_mathcourse_cover', true );
		$topics = $this->tutor->get_topics( $course_id, true );
		$edit_lesson_id = isset( $_GET['lesson_id'] ) ? absint( $_GET['lesson_id'] ) : 0;
		$edit_lesson = $edit_lesson_id ? $this->tutor->get_lesson( $edit_lesson_id ) : null;
		if ( $edit_lesson && $course_id !== $this->tutor->get_lesson_course_id( $edit_lesson->ID ) ) { $edit_lesson = null; }
		?>
		<div class="wrap">
			<div style="display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:18px;">
				<div><a href="<?php echo esc_url( admin_url( 'admin.php?page=mathcourse-courses' ) ); ?>">← 返回课程管理</a><h1 style="margin-bottom:4px;">编辑课程</h1><p style="margin-top:0;color:#646970;">课程基本信息、专题和课时统一在 MathCourse 管理，底层数据仍由 Tutor LMS 保存。</p></div>
				<div><a class="button" href="<?php echo esc_url( add_query_arg( array( 'post' => $course_id, 'action' => 'edit' ), admin_url( 'post.php' ) ) ); ?>">打开 Tutor LMS</a></div>
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
						<?php foreach ( $topics as $topic ) : $lessons = $this->tutor->get_lessons( $topic->ID, true ); ?>
						<div style="border:1px solid #dcdcde;border-radius:10px;overflow:hidden;background:#fff;"><div style="padding:14px 16px;background:#f6f7f7;display:flex;align-items:center;justify-content:space-between;"><div><strong><?php echo esc_html( $topic->post_title ); ?></strong><span style="color:#646970;margin-left:8px;">（<?php echo esc_html( count( $lessons ) ); ?> 个课时）</span></div><button type="submit" name="mathcourse_action" value="delete_topic_<?php echo esc_attr( $topic->ID ); ?>" class="button-link-delete" onclick="return confirm('确定删除这个专题及其课时吗？');">删除专题</button></div>
						<div style="padding:8px 16px 14px;"><?php if ( empty( $lessons ) ) : ?><div style="padding:12px 0;color:#646970;">暂无课时。</div><?php else : ?><?php foreach ( $lessons as $index => $lesson ) : ?>
						<?php $lesson_edit_url = add_query_arg( array( 'page' => 'mathcourse-course-edit', 'course_id' => $course_id, 'lesson_id' => $lesson->ID ), admin_url( 'admin.php' ) ); $preview = get_post_meta( $lesson->ID, '_mathcourse_preview', true ); ?>
						<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #f0f0f1;"><div><span style="display:inline-block;width:28px;color:#8c8f94;"><?php echo esc_html( $index + 1 ); ?>.</span><strong><?php echo esc_html( $lesson->post_title ); ?></strong><?php if ( 'yes' === $preview ) : ?><span style="color:#2271b1;margin-left:8px;">试看</span><?php else : ?><span style="color:#8c8f94;margin-left:8px;">需授权</span><?php endif; ?></div><div style="display:flex;gap:10px;"><a href="<?php echo esc_url( $lesson_edit_url ); ?>">编辑</a><button type="submit" name="mathcourse_action" value="delete_lesson_<?php echo esc_attr( $lesson->ID ); ?>" class="button-link-delete" onclick="return confirm('确定删除这个课时吗？');">删除</button></div></div>
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
		$page = get_post_meta( $lesson->ID, '_mathcourse_page_number', true );
		if ( '' === $page ) { $page = get_post_meta( $lesson->ID, '_mathcourse_page', true ); }
		$video = get_post_meta( $lesson->ID, '_mathcourse_video_id', true );
		if ( '' === $video ) { $video = get_post_meta( $lesson->ID, '_mathcourse_video', true ); }
		$hls = get_post_meta( $lesson->ID, '_mathcourse_hls_url', true );
		?>
		<div style="margin-top:16px;padding:20px;border:1px solid #2271b1;border-radius:10px;background:#f8fbff;"><div style="display:flex;justify-content:space-between;align-items:center;"><h3 style="margin:0;">编辑课时</h3><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'mathcourse-course-edit', 'course_id' => $course_id ), admin_url( 'admin.php' ) ) ); ?>">关闭</a></div>
		<input type="hidden" name="editing_lesson_id" value="<?php echo esc_attr( $lesson->ID ); ?>"><p><label for="lesson_title_edit"><strong>课时名称</strong></label><input id="lesson_title_edit" name="lesson_title_edit" type="text" class="large-text" value="<?php echo esc_attr( $lesson->post_title ); ?>"></p>
		<p><label for="lesson_description_edit"><strong>课时简介</strong></label><textarea id="lesson_description_edit" name="lesson_description_edit" rows="5" class="large-text"><?php echo esc_textarea( $lesson->post_content ); ?></textarea></p><p><label><input type="checkbox" name="lesson_preview_edit" value="yes" <?php checked( $preview, 'yes' ); ?>> <strong>允许试看</strong></label></p><p><label for="lesson_status_edit"><strong>状态</strong></label><select id="lesson_status_edit" name="lesson_status_edit"><option value="draft" <?php selected( $lesson->post_status, 'draft' ); ?>>草稿</option><option value="publish" <?php selected( $lesson->post_status, 'publish' ); ?>>已发布</option><option value="private" <?php selected( $lesson->post_status, 'private' ); ?>>私密</option></select></p>
		<div style="padding-top:14px;border-top:1px solid #dbe7f0;"><strong>教材与视频</strong><div style="display:grid;grid-template-columns:160px minmax(0,1fr);gap:12px;margin-top:10px;"><div><label for="lesson_page_edit"><strong>教材页码</strong></label><input id="lesson_page_edit" name="lesson_page_edit" type="text" value="<?php echo esc_attr( $page ); ?>" class="regular-text" placeholder="例如：P9"></div><div><label for="lesson_video_edit"><strong>视频资源标识</strong></label><input id="lesson_video_edit" name="lesson_video_edit" type="text" value="<?php echo esc_attr( $video ); ?>" class="large-text" placeholder="视频 ID 或资源标识"></div></div>
		<p><label for="lesson_hls_edit"><strong>HLS 流媒体地址</strong></label><input id="lesson_hls_edit" name="lesson_hls_edit" type="password" autocomplete="off" value="<?php echo esc_attr( $hls ); ?>" class="large-text" placeholder="https://.../index.m3u8"><br><small style="color:#646970;">仅服务器端保存。前台只使用短时受保护地址。留空表示保持原地址不变。</small></p></div><p><button type="submit" name="mathcourse_action" value="save_lesson" class="button button-primary">保存课时</button></p></div>
		<?php
	}

	private function handle_actions( $course_id, &$message, &$message_type ) {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['mathcourse_action'] ) ) { return; }
		check_admin_referer( 'mathcourse_edit_course_' . $course_id );
		$action = sanitize_key( wp_unslash( $_POST['mathcourse_action'] ) );
		if ( 'save_course' === $action ) {
			$title = isset( $_POST['course_title'] ) ? sanitize_text_field( wp_unslash( $_POST['course_title'] ) ) : '新课程';
			$description = isset( $_POST['course_description'] ) ? wp_kses_post( wp_unslash( $_POST['course_description'] ) ) : '';
			$type = isset( $_POST['course_type'] ) ? sanitize_key( wp_unslash( $_POST['course_type'] ) ) : 'topic';
			$grade = isset( $_POST['course_grade'] ) ? sanitize_key( wp_unslash( $_POST['course_grade'] ) ) : '';
			$cover = isset( $_POST['course_cover'] ) ? esc_url_raw( wp_unslash( $_POST['course_cover'] ) ) : '';
			$status = isset( $_POST['course_status'] ) ? sanitize_key( wp_unslash( $_POST['course_status'] ) ) : 'draft';
			if ( ! in_array( $type, array( 'topic', 'supplementary' ), true ) ) { $type = 'topic'; }
			if ( ! in_array( $status, array( 'draft', 'publish', 'private' ), true ) ) { $status = 'draft'; }
			$result = wp_update_post( array( 'ID' => $course_id, 'post_title' => $title, 'post_content' => $description, 'post_status' => $status ), true );
			if ( is_wp_error( $result ) ) { $message = '课程保存失败：' . $result->get_error_message(); $message_type = 'error'; return; }
			update_post_meta( $course_id, '_mathcourse_type', $type );
			update_post_meta( $course_id, '_mathcourse_grade', $grade );
			update_post_meta( $course_id, '_mathcourse_cover', $cover );
			$message='课程已保存。';
		} elseif ( 'add_topic' === $action ) {
			$title = isset( $_POST['topic_title'] ) ? sanitize_text_field( wp_unslash( $_POST['topic_title'] ) ) : '';
			if ( ! $title ) { $message='请输入专题名称。'; $message_type='warning'; return; }
			$topic_id = $this->tutor->create_topic( $course_id, $title );
			$message = $topic_id ? '专题已添加。' : '专题添加失败。';
			$message_type = $topic_id ? 'success' : 'error';
		} elseif ( 0 === strpos( $action, 'add_lesson_' ) ) {
			$topic_id = absint( substr( $action, 11 ) );
			if ( ! $topic_id || (int) get_post_field( 'post_parent', $topic_id ) !== (int) $course_id || get_post_type( $topic_id ) !== $this->tutor->get_topic_post_type() ) { $message='专题不存在或不属于当前课程。'; $message_type='error'; return; }
			$field = 'lesson_title_' . $topic_id;
			$title = isset( $_POST[$field] ) ? sanitize_text_field( wp_unslash( $_POST[$field] ) ) : '';
			if ( ! $title ) { $message='请输入课时名称。'; $message_type='warning'; return; }
			$lesson_id = wp_insert_post( array( 'post_title' => $title, 'post_type' => $this->tutor->get_lesson_post_type(), 'post_status' => 'publish', 'post_author' => get_current_user_id(), 'post_parent' => $topic_id, 'menu_order' => count( $this->tutor->get_lessons( $topic_id, true ) ), 'post_content' => '' ), true );
			if ( is_wp_error( $lesson_id ) ) { $message='课时添加失败：' . $lesson_id->get_error_message(); $message_type='error'; return; }
			$lesson_id = absint( $lesson_id );
			update_post_meta( $lesson_id, '_mathcourse_preview', 'no' );
			update_post_meta( $lesson_id, '_is_preview', 'no' );
			update_post_meta( $lesson_id, '_mathcourse_page_number', '' );
			update_post_meta( $lesson_id, '_mathcourse_video_id', '' );
			update_post_meta( $lesson_id, '_mathcourse_permission_mode', 'authorization' );
			$message = $lesson_id ? '课时已添加。' : '课时添加失败。';
			$message_type = $lesson_id ? 'success' : 'error';
		} elseif ( 'save_lesson' === $action ) {
			$lesson_id=isset($_POST['editing_lesson_id'])?absint($_POST['editing_lesson_id']):0; $lesson=$lesson_id?$this->tutor->get_lesson($lesson_id):null; if(!$lesson || $course_id!==$this->tutor->get_lesson_course_id($lesson_id)){ $message='课时不存在。'; $message_type='error'; return; } $title=isset($_POST['lesson_title_edit'])?sanitize_text_field(wp_unslash($_POST['lesson_title_edit'])):''; $description=isset($_POST['lesson_description_edit'])?wp_kses_post(wp_unslash($_POST['lesson_description_edit'])):''; $preview=!empty($_POST['lesson_preview_edit'])?'yes':'no'; $status=isset($_POST['lesson_status_edit'])?sanitize_key(wp_unslash($_POST['lesson_status_edit'])):'draft'; $page=isset($_POST['lesson_page_edit'])?sanitize_text_field(wp_unslash($_POST['lesson_page_edit'])):''; $video=isset($_POST['lesson_video_edit'])?sanitize_text_field(wp_unslash($_POST['lesson_video_edit'])):''; if(!in_array($status,array('draft','publish','private'),true))$status='draft'; $updated=wp_update_post(array('ID'=>$lesson_id,'post_title'=>$title,'post_content'=>$description,'post_status'=>$status),true); if(is_wp_error($updated)){ $message='课时保存失败：'.$updated->get_error_message(); $message_type='error'; return; } update_post_meta($lesson_id,'_mathcourse_preview',$preview); update_post_meta($lesson_id,'_is_preview',$preview); update_post_meta($lesson_id,'_mathcourse_page_number',$page); update_post_meta($lesson_id,'_mathcourse_video_id',$video); if ( isset( $_POST['lesson_hls_edit'] ) && '' !== trim( (string) wp_unslash( $_POST['lesson_hls_edit'] ) ) ) { update_post_meta( $lesson_id, '_mathcourse_hls_url', esc_url_raw( wp_unslash( $_POST['lesson_hls_edit'] ) ) ); } $message='课时已保存。';
		} elseif ( 0 === strpos( $action, 'delete_topic_' ) ) {
			$topic_id=absint(substr($action,13)); if(!$topic_id || get_post_type($topic_id)!==$this->tutor->get_topic_post_type() || (int)get_post_field('post_parent',$topic_id)!==(int)$course_id){ $message='专题不存在或不属于当前课程。'; $message_type='error'; return; } foreach($this->tutor->get_lessons($topic_id,true) as $lesson){ wp_delete_post($lesson->ID,true); } $deleted=wp_delete_post($topic_id,true); $message=$deleted?'专题已删除。':'专题删除失败。'; $message_type=$deleted?'success':'error';
		} elseif ( 0 === strpos( $action, 'delete_lesson_' ) ) {
			$lesson_id=absint(substr($action,14)); $lesson=$lesson_id?$this->tutor->get_lesson($lesson_id):null; if(!$lesson || $course_id!==$this->tutor->get_lesson_course_id($lesson_id)){ $message='课时不存在。'; $message_type='error'; return; } $deleted=wp_delete_post($lesson_id,true); $message=$deleted?'课时已删除。':'课时删除失败。'; $message_type=$deleted?'success':'error';
		}
	}

	private function notice( $text ) { echo '<div class="wrap"><div class="notice notice-warning"><p>'.esc_html($text).'</p></div></div>'; }
}