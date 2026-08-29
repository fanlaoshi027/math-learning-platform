<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal MathCourse course editor.
 * Tutor LMS remains the source of truth for the course post and its content.
 */
class Course_Editor {

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mathcourse' ) );
		}

		if ( ! function_exists( 'tutor' ) ) {
			$this->notice( 'MathCourse 需要 Tutor LMS 4.0.4。' );
			return;
		}

		$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
		if ( ! $course_id || tutor()->course_post_type !== get_post_type( $course_id ) ) {
			$this->notice( '课程不存在。' );
			return;
		}

		if ( ! current_user_can( 'edit_post', $course_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this course.', 'mathcourse' ) );
		}

		$post = get_post( $course_id );
		$type = get_post_meta( $course_id, '_mathcourse_type', true );
		$grade = get_post_meta( $course_id, '_mathcourse_grade', true );
		$cover = get_post_meta( $course_id, '_mathcourse_cover', true );
		$saved = false;

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['mathcourse_save_course'] ) ) {
			check_admin_referer( 'mathcourse_edit_course_' . $course_id );

			$title = isset( $_POST['course_title'] ) ? sanitize_text_field( wp_unslash( $_POST['course_title'] ) ) : '';
			$description = isset( $_POST['course_description'] ) ? wp_kses_post( wp_unslash( $_POST['course_description'] ) ) : '';
			$new_type = isset( $_POST['course_type'] ) ? sanitize_key( wp_unslash( $_POST['course_type'] ) ) : 'topic';
			$new_grade = isset( $_POST['course_grade'] ) ? sanitize_key( wp_unslash( $_POST['course_grade'] ) ) : '';
			$new_cover = isset( $_POST['course_cover'] ) ? esc_url_raw( wp_unslash( $_POST['course_cover'] ) ) : '';
			$new_status = isset( $_POST['course_status'] ) ? sanitize_key( wp_unslash( $_POST['course_status'] ) ) : 'draft';

			if ( '' === $title ) {
				$title = '新课程';
			}
			if ( ! in_array( $new_type, array( 'topic', 'supplementary' ), true ) ) {
				$new_type = 'topic';
			}
			if ( ! in_array( $new_grade, array( '', '7', '8', '9', '10', '11', '12' ), true ) ) {
				$new_grade = '';
			}
			if ( ! in_array( $new_status, array( 'draft', 'publish', 'private' ), true ) ) {
				$new_status = 'draft';
			}

			$result = wp_update_post(
				wp_slash(
					array(
						'ID'           => $course_id,
						'post_title'   => $title,
						'post_content' => $description,
						'post_status'  => $new_status,
					)
				),
				true
			);

			if ( is_wp_error( $result ) ) {
				$this->notice( $result->get_error_message(), 'error' );
				return;
			}

			update_post_meta( $course_id, '_mathcourse_type', $new_type );
			update_post_meta( $course_id, '_mathcourse_grade', $new_grade );
			update_post_meta( $course_id, '_mathcourse_cover', $new_cover );

			$type = $new_type;
			$grade = $new_grade;
			$cover = $new_cover;
			$post = get_post( $course_id );
			$saved = true;
		}

		$back_url = admin_url( 'admin.php?page=mathcourse-courses' );
		?>
		<div class="wrap">
			<div style="display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:18px;">
				<div>
					<a href="<?php echo esc_url( $back_url ); ?>">← 返回课程管理</a>
					<h1 style="margin-bottom:4px;">编辑课程</h1>
					<p style="margin-top:0;color:#646970;">先管理课程基本信息；课程章节和课时将在下一阶段接入。</p>
				</div>
			</div>

			<?php if ( $saved ) : ?><div class="notice notice-success is-dismissible"><p>课程已保存。</p></div><?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'mathcourse_edit_course_' . $course_id ); ?>
				<input type="hidden" name="mathcourse_save_course" value="1">

				<div style="display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:20px;max-width:1100px;">
					<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:24px;">
						<p><label for="course_title"><strong>课程名称</strong></label>
						<input id="course_title" name="course_title" type="text" class="large-text" value="<?php echo esc_attr( $post->post_title ); ?>" required></p>

						<p><label for="course_description"><strong>课程简介</strong></label>
						<textarea id="course_description" name="course_description" rows="10" class="large-text" placeholder="介绍课程内容、适用年级和学习目标……"><?php echo esc_textarea( $post->post_content ); ?></textarea></p>

						<div style="margin-top:28px;padding-top:20px;border-top:1px solid #eee;">
							<h2 style="font-size:16px;margin-top:0;">课程内容</h2>
							<div style="padding:18px;background:#f6f7f7;border-radius:8px;">
								<strong>章节与课时管理</strong>
								<p style="color:#646970;margin-bottom:0;">下一阶段将在这里建立专题 → 课时 → 视频的极简管理界面，并继续由 Tutor LMS 保存底层数据。</p>
							</div>
							<p style="margin-top:14px;"><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'create-course', 'course_id' => $course_id ), admin_url( 'admin.php' ) ) ); ?>">临时进入 Tutor LMS 内容编辑</a></p>
						</div>
					</div>

					<div>
						<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px;margin-bottom:16px;">
							<h2 style="font-size:16px;margin-top:0;">课程属性</h2>
							<p><label for="course_type"><strong>课程类型</strong></label>
							<select id="course_type" name="course_type" style="width:100%;margin-top:6px;">
								<option value="topic" <?php selected( $type, 'topic' ); ?>>专题课程</option>
								<option value="supplementary" <?php selected( $type, 'supplementary' ); ?>>大培优配套</option>
							</select></p>

							<p><label for="course_grade"><strong>年级</strong></label>
							<select id="course_grade" name="course_grade" style="width:100%;margin-top:6px;">
								<option value="">请选择</option>
								<option value="7" <?php selected( $grade, '7' ); ?>>七年级</option>
								<option value="8" <?php selected( $grade, '8' ); ?>>八年级</option>
								<option value="9" <?php selected( $grade, '9' ); ?>>九年级</option>
								<option value="10" <?php selected( $grade, '10' ); ?>>高一</option>
								<option value="11" <?php selected( $grade, '11' ); ?>>高二</option>
								<option value="12" <?php selected( $grade, '12' ); ?>>高三</option>
							</select></p>

							<p><label for="course_status"><strong>状态</strong></label>
							<select id="course_status" name="course_status" style="width:100%;margin-top:6px;">
								<option value="draft" <?php selected( $post->post_status, 'draft' ); ?>>草稿</option>
								<option value="publish" <?php selected( $post->post_status, 'publish' ); ?>>已发布</option>
								<option value="private" <?php selected( $post->post_status, 'private' ); ?>>私密</option>
							</select></p>

							<p><label for="course_cover"><strong>课程封面 URL</strong></label>
							<input id="course_cover" name="course_cover" type="url" class="regular-text" style="width:100%;" value="<?php echo esc_attr( $cover ); ?>" placeholder="https://..."></p>

							<p style="margin-bottom:0;"><button type="submit" class="button button-primary button-large">保存课程</button></p>
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	private function notice( $message, $type = 'error' ) {
		$class = 'notice notice-' . sanitize_html_class( $type ) . ' inline';
		echo '<div class="wrap"><h1>课程编辑</h1><div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div></div>';
	}
}
