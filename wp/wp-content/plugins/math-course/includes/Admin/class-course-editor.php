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
		<div class="wrap mathcourse-editor">
			<div class="mathcourse-editor-header">
				<div>
					<a class="mathcourse-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=mathcourse-courses' ) ); ?>">← 返回课程管理</a>
					<h1>编辑课程</h1>
					<p>课程内容与课时在同一个工作区连续编辑，保存后仍留在当前课程。</p>
				</div>
				<div class="mathcourse-editor-head-actions">
					<a class="button" href="<?php echo esc_url( add_query_arg( array( 'post' => $course_id, 'action' => 'edit' ), admin_url( 'post.php' ) ) ); ?>">打开 Tutor LMS</a>
					<button type="submit" form="mathcourse-course-form" name="mathcourse_action" value="freeze_course" class="button mathcourse-freeze-button" onclick="return confirm('冻结后课程将变为私密状态，前台不再公开显示。确定继续吗？');">冻结课程</button>
				</div>
			</div>
			<?php if ( $message ) : ?><div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div><?php endif; ?>

			<form id="mathcourse-course-form" method="post">
				<?php wp_nonce_field( 'mathcourse_edit_course_' . $course_id ); ?>
				<input type="hidden" name="mathcourse_action" value="save_course">
				<div class="mathcourse-editor-workspace">
					<section class="mathcourse-content-panel">
						<div class="mathcourse-course-intro">
							<label for="course_title"><strong>课程名称</strong></label>
							<input id="course_title" name="course_title" type="text" class="large-text" value="<?php echo esc_attr( $post->post_title ); ?>" required>
							<label for="course_description"><strong>课程简介</strong></label>
							<textarea id="course_description" name="course_description" rows="5" class="large-text"><?php echo esc_textarea( $post->post_content ); ?></textarea>
						</div>

						<div class="mathcourse-content-workspace">
							<div class="mathcourse-section-heading">
								<div><h2>课程内容</h2><p>按「专题 → 课时」管理。点击任意课时的「编辑」，右侧会直接切换编辑属性。</p></div>
								<span class="mathcourse-section-hint">连续编辑</span>
							</div>
							<?php if ( empty( $topics ) ) : ?>
								<div class="mathcourse-empty-content"><strong>还没有专题</strong><span>先创建第一个专题，再添加课时。</span></div>
							<?php else : ?>
								<div class="mathcourse-topic-list">
								<?php foreach ( $topics as $topic ) : $lessons = $this->tutor->get_lessons( $topic->ID, true ); ?>
									<div class="mathcourse-topic-card">
										<div class="mathcourse-topic-header">
											<div><strong><?php echo esc_html( $topic->post_title ); ?></strong><span>（<?php echo esc_html( count( $lessons ) ); ?> 个课时）</span></div>
											<button type="submit" name="mathcourse_action" value="freeze_topic_<?php echo esc_attr( $topic->ID ); ?>" class="button-link mathcourse-freeze-link" onclick="return confirm('冻结后该专题及其课时将变为私密状态。确定继续吗？');">冻结专题</button>
										</div>
										<div class="mathcourse-topic-body">
											<?php if ( empty( $lessons ) ) : ?><div class="mathcourse-no-lessons">暂无课时。</div><?php else : ?>
												<?php foreach ( $lessons as $index => $lesson ) : ?>
													<?php
													$lesson_edit_url = add_query_arg( array( 'page' => 'mathcourse-course-edit', 'course_id' => $course_id, 'lesson_id' => $lesson->ID ), admin_url( 'admin.php' ) );
													$preview = get_post_meta( $lesson->ID, '_mathcourse_preview', true );
													$status_class = 'publish' === $lesson->post_status ? 'is-published' : ( 'private' === $lesson->post_status ? 'is-private' : 'is-draft' );
													$status_text = 'publish' === $lesson->post_status ? '已发布' : ( 'private' === $lesson->post_status ? '已冻结' : '草稿' );
													?>
													<div class="mathcourse-lesson-row <?php echo esc_attr( $edit_lesson_id === $lesson->ID ? 'is-selected' : '' ); ?>" data-lesson-id="<?php echo esc_attr( $lesson->ID ); ?>">
														<div class="mathcourse-lesson-main"><span class="mathcourse-lesson-index"><?php echo esc_html( $index + 1 ); ?>.</span><strong><?php echo esc_html( $lesson->post_title ); ?></strong><?php if ( 'yes' === $preview ) : ?><span class="mathcourse-preview-badge">试看</span><?php endif; ?><span class="mathcourse-status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span></div>
														<div class="mathcourse-lesson-actions"><a class="mathcourse-lesson-edit" href="<?php echo esc_url( $lesson_edit_url ); ?>" data-lesson-id="<?php echo esc_attr( $lesson->ID ); ?>">编辑</a><button type="submit" name="mathcourse_action" value="freeze_lesson_<?php echo esc_attr( $lesson->ID ); ?>" class="button-link mathcourse-freeze-link" onclick="return confirm('冻结后该课时将变为私密状态。确定继续吗？');">冻结</button></div>
													</div>
												<?php endforeach; ?>
											<?php endif; ?>
											<div class="mathcourse-add-lesson"><input type="text" name="lesson_title_<?php echo esc_attr( $topic->ID ); ?>" placeholder="输入课时名称，例如：三角形基础"><button type="submit" name="mathcourse_action" value="add_lesson_<?php echo esc_attr( $topic->ID ); ?>" class="button">＋ 添加课时</button></div>
						</div>
					</div>
				<?php endforeach; ?></div>
							<?php endif; ?>
							<div class="mathcourse-add-topic"><input type="text" name="topic_title" placeholder="输入专题名称，例如：第1章 三角形"><button type="submit" name="mathcourse_action" value="add_topic" class="button button-primary">＋ 添加专题</button></div>
						</div>
					</section>

					<aside class="mathcourse-lesson-side" id="mathcourse-lesson-side" aria-live="polite">
						<?php if ( $edit_lesson ) : $this->render_lesson_editor( $course_id, $edit_lesson ); ?><?php else : ?>
							<div class="mathcourse-lesson-placeholder"><div class="mathcourse-placeholder-icon">✎</div><strong>选择一个课时</strong><span>点击左侧课时的「编辑」，这里会直接显示课时属性。</span></div>
						<?php endif; ?>
					</aside>
				</div>

				<section class="mathcourse-course-properties">
					<div class="mathcourse-properties-heading"><div><h2>课程属性</h2><p>课程基本设置不常修改，统一放在课时列表下面。</p></div><span>课程设置</span></div>
					<div class="mathcourse-properties-grid">
						<div><label for="course_type"><strong>课程类型</strong></label><select id="course_type" name="course_type"><option value="topic" <?php selected( $type, 'topic' ); ?>>专题课程</option><option value="supplementary" <?php selected( $type, 'supplementary' ); ?>>教辅配套课</option></select></div>
						<div><label for="course_grade"><strong>年级</strong></label><select id="course_grade" name="course_grade"><option value="">请选择</option><option value="7" <?php selected( $grade, '7' ); ?>>七年级</option><option value="8" <?php selected( $grade, '8' ); ?>>八年级</option><option value="9" <?php selected( $grade, '9' ); ?>>九年级</option><option value="10" <?php selected( $grade, '10' ); ?>>高一</option><option value="11" <?php selected( $grade, '11' ); ?>>高二</option><option value="12" <?php selected( $grade, '12' ); ?>>高三</option></select></div>
						<div><label for="course_status"><strong>状态</strong></label><select id="course_status" name="course_status"><option value="draft" <?php selected( $post->post_status, 'draft' ); ?>>草稿</option><option value="publish" <?php selected( $post->post_status, 'publish' ); ?>>已发布</option><option value="private" <?php selected( $post->post_status, 'private' ); ?>>私密 / 已冻结</option></select></div>
						<div class="mathcourse-cover-field"><label for="course_cover"><strong>课程封面</strong></label><input id="course_cover" name="course_cover" type="url" value="<?php echo esc_attr( $cover ); ?>" placeholder="课程封面图片地址"><small>可从媒体库选择，或直接填写图片地址。</small></div>
					</div>
					<div class="mathcourse-course-savebar"><span>课程名称、简介和课程属性修改后，点击右侧保存。</span><button type="submit" name="mathcourse_action" value="save_course" class="button button-primary button-large">保存课程</button></div>
				</section>
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
		$status = $lesson->post_status;
		?>
		<div class="mathcourse-lesson-editor" data-lesson-editor-id="<?php echo esc_attr( $lesson->ID ); ?>">
			<div class="mathcourse-lesson-editor-head"><div><span class="mathcourse-editor-eyebrow">课时属性</span><h2>编辑课时</h2><p><?php echo esc_html( $lesson->post_title ); ?></p></div><a class="mathcourse-lesson-close" href="<?php echo esc_url( add_query_arg( array( 'page' => 'mathcourse-course-edit', 'course_id' => $course_id ), admin_url( 'admin.php' ) ) ); ?>">关闭</a></div>
			<input type="hidden" name="editing_lesson_id" value="<?php echo esc_attr( $lesson->ID ); ?>">
			<div class="mathcourse-lesson-fields">
				<div><label for="lesson_title_edit"><strong>课时名称</strong></label><input id="lesson_title_edit" name="lesson_title_edit" type="text" class="large-text" value="<?php echo esc_attr( $lesson->post_title ); ?>"></div>
				<div><label for="lesson_description_edit"><strong>课时简介</strong></label><textarea id="lesson_description_edit" name="lesson_description_edit" rows="4" class="large-text"><?php echo esc_textarea( $lesson->post_content ); ?></textarea></div>
				<div class="mathcourse-lesson-inline"><label class="mathcourse-check"><input type="checkbox" name="lesson_preview_edit" value="yes" <?php checked( $preview, 'yes' ); ?>> <strong>允许试看</strong></label><label><strong>状态</strong><select id="lesson_status_edit" name="lesson_status_edit"><option value="draft" <?php selected( $status, 'draft' ); ?>>草稿</option><option value="publish" <?php selected( $status, 'publish' ); ?>>已发布</option><option value="private" <?php selected( $status, 'private' ); ?>>私密 / 已冻结</option></select></label></div>
				<div class="mathcourse-media-settings"><div class="mathcourse-subheading"><strong>教材与视频</strong><span>播放资源设置</span></div><div class="mathcourse-media-grid"><div><label for="lesson_page_edit"><strong>教材页码</strong></label><input id="lesson_page_edit" name="lesson_page_edit" type="text" value="<?php echo esc_attr( $page ); ?>" placeholder="例如：P9"></div><div><label for="lesson_video_edit"><strong>视频资源标识</strong></label><input id="lesson_video_edit" name="lesson_video_edit" type="text" value="<?php echo esc_attr( $video ); ?>" placeholder="视频 ID 或资源标识"></div></div><div><label for="lesson_hls_edit"><strong>HLS 流媒体地址</strong></label><input id="lesson_hls_edit" name="lesson_hls_edit" type="url" inputmode="url" autocomplete="off" value="<?php echo esc_attr( $hls ); ?>" placeholder="https://.../index.m3u8"><small>仅服务器端保存；前台使用受保护的短时地址。留空将删除现有 HLS 地址。</small></div></div>
			</div>
			<div class="mathcourse-lesson-savebar"><button type="submit" name="mathcourse_action" value="save_lesson" class="button button-primary button-large">保存课时</button><span>保存后仍停留在当前课程编辑页。</span></div>
		</div>
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
			wp_update_post( array( 'ID' => $course_id, 'post_title' => $title, 'post_content' => $description, 'post_status' => $status ) );
			update_post_meta( $course_id, '_mathcourse_type', $type );
			update_post_meta( $course_id, '_mathcourse_grade', $grade );
			update_post_meta( $course_id, '_mathcourse_cover', $cover );
			$message = '课程已保存。';
		} elseif ( 'freeze_course' === $action ) {
			wp_update_post( array( 'ID' => $course_id, 'post_status' => 'private' ) );
			$message = '课程已冻结，当前为私密状态。';
		} elseif ( 'add_topic' === $action ) {
			$title = isset( $_POST['topic_title'] ) ? sanitize_text_field( wp_unslash( $_POST['topic_title'] ) ) : '';
			if ( ! $title ) { $message = '请输入专题名称。'; $message_type = 'warning'; return; }
			$topic_id = $this->tutor->create_topic( $course_id, $title );
			$message = $topic_id ? '专题已添加。' : '专题添加失败。';
			$message_type = $topic_id ? 'success' : 'error';
		} elseif ( 0 === strpos( $action, 'add_lesson_' ) ) {
			$topic_id = absint( substr( $action, 11 ) );
			$field = 'lesson_title_' . $topic_id;
			$title = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			if ( ! $title ) { $message = '请输入课时名称。'; $message_type = 'warning'; return; }
			$lesson_id = $this->tutor->create_lesson( $topic_id, $title );
			$message = $lesson_id ? '课时已添加。现在可以直接在右侧编辑它。' : '课时添加失败。';
			$message_type = $lesson_id ? 'success' : 'error';
		} elseif ( 'save_lesson' === $action ) {
			$lesson_id = isset( $_POST['editing_lesson_id'] ) ? absint( $_POST['editing_lesson_id'] ) : 0;
			$lesson = $lesson_id ? $this->tutor->get_lesson( $lesson_id ) : null;
			if ( ! $lesson || $course_id !== $this->tutor->get_lesson_course_id( $lesson_id ) ) { $message = '课时不存在。'; $message_type = 'error'; return; }
			$title = isset( $_POST['lesson_title_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['lesson_title_edit'] ) ) : '';
			$description = isset( $_POST['lesson_description_edit'] ) ? wp_kses_post( wp_unslash( $_POST['lesson_description_edit'] ) ) : '';
			$preview = ! empty( $_POST['lesson_preview_edit'] ) ? 'yes' : 'no';
			$status = isset( $_POST['lesson_status_edit'] ) ? sanitize_key( wp_unslash( $_POST['lesson_status_edit'] ) ) : 'draft';
			$page = isset( $_POST['lesson_page_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['lesson_page_edit'] ) ) : '';
			$video = isset( $_POST['lesson_video_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['lesson_video_edit'] ) ) : '';
			$hls = isset( $_POST['lesson_hls_edit'] ) ? esc_url_raw( wp_unslash( $_POST['lesson_hls_edit'] ) ) : '';
			if ( ! in_array( $status, array( 'draft', 'publish', 'private' ), true ) ) { $status = 'draft'; }
			wp_update_post( array( 'ID' => $lesson_id, 'post_title' => $title, 'post_content' => $description, 'post_status' => $status ) );
			update_post_meta( $lesson_id, '_mathcourse_preview', $preview );
			update_post_meta( $lesson_id, '_mathcourse_page_number', $page );
			update_post_meta( $lesson_id, '_mathcourse_video_id', $video );
			if ( '' !== $hls ) {
				update_post_meta( $lesson_id, '_mathcourse_hls_url', $hls );
				$video_status = get_post_meta( $lesson_id, '_mathcourse_video_status', true );
				if ( ! in_array( $video_status, array( 'pending', 'processing' ), true ) ) {
					update_post_meta( $lesson_id, '_mathcourse_video_status', 'ready' );
					delete_post_meta( $lesson_id, '_mathcourse_video_error' );
				}
			} else {
				delete_post_meta( $lesson_id, '_mathcourse_hls_url' );
				$video_status = get_post_meta( $lesson_id, '_mathcourse_video_status', true );
				if ( 'ready' === $video_status ) {
					update_post_meta( $lesson_id, '_mathcourse_video_status', 'none' );
				}
			}
			$message = '课时已保存。';
		} elseif ( 0 === strpos( $action, 'freeze_topic_' ) ) {
			$topic_id = absint( substr( $action, 13 ) );
			if ( $topic_id ) {
				$lessons = $this->tutor->get_lessons( $topic_id, true );
				foreach ( $lessons as $lesson ) { wp_update_post( array( 'ID' => $lesson->ID, 'post_status' => 'private' ) ); }
				wp_update_post( array( 'ID' => $topic_id, 'post_status' => 'private' ) );
				$message = '专题已冻结。';
			}
		} elseif ( 0 === strpos( $action, 'freeze_lesson_' ) ) {
			$lesson_id = absint( substr( $action, 14 ) );
			$lesson = $lesson_id ? $this->tutor->get_lesson( $lesson_id ) : null;
			if ( $lesson && $course_id === $this->tutor->get_lesson_course_id( $lesson_id ) ) { wp_update_post( array( 'ID' => $lesson_id, 'post_status' => 'private' ) ); $message = '课时已冻结。'; }
		}
	}

	private function notice( $text ) { echo '<div class="wrap"><div class="notice notice-warning"><p>' . esc_html( $text ) . '</p></div></div>'; }
}
