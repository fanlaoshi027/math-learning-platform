<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * MathCourse course management page.
 *
 * Keeps the daily course workflow simple while Tutor LMS remains the
 * underlying LMS engine.
 */
class Course_Page {

	/**
	 * Render the course management screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mathcourse' ) );
		}

		if ( ! function_exists( 'tutor' ) ) {
			$this->render_tutor_notice();
			return;
		}

		$this->handle_actions();

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';

		$args = array(
			'post_type'      => tutor()->course_post_type,
			'posts_per_page' => 20,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			's'              => $search,
		);

		if ( in_array( $status, array( 'publish', 'draft', 'pending', 'future', 'private' ), true ) ) {
			$args['post_status'] = $status;
		}

		$query = new \WP_Query( $args );
		?>
		<div class="wrap mathcourse-admin">
			<div class="mathcourse-page-header">
				<div>
					<h1 class="wp-heading-inline"><?php esc_html_e( '课程管理', 'mathcourse' ); ?></h1>
					<p class="mathcourse-description"><?php esc_html_e( '用简洁的界面管理课程；课程内容和课时仍由 Tutor LMS 负责。', 'mathcourse' ); ?></p>
				</div>
				<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=mathcourse-courses&action=new' ), 'mathcourse_new_course' ) ); ?>">
					<?php esc_html_e( '新建课程', 'mathcourse' ); ?>
				</a>
			</div>

			<hr class="wp-header-end">

			<form method="get" class="mathcourse-toolbar">
				<input type="hidden" name="page" value="mathcourse-courses">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="搜索课程名称">
				<select name="status">
					<option value="all" <?php selected( $status, 'all' ); ?>>全部状态</option>
					<option value="publish" <?php selected( $status, 'publish' ); ?>>已发布</option>
					<option value="draft" <?php selected( $status, 'draft' ); ?>>草稿</option>
				</select>
				<button class="button">筛选</button>
			</form>

			<div class="mathcourse-card">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th class="column-cover">封面</th>
							<th>课程</th>
							<th>类型</th>
							<th>年级</th>
							<th>状态</th>
							<th>时间</th>
							<th>操作</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $query->have_posts() ) : ?>
							<?php while ( $query->have_posts() ) : $query->the_post(); ?>
								<?php
								$course_id   = get_the_ID();
								$course_type = get_post_meta( $course_id, '_mathcourse_type', true );
								$grade       = get_post_meta( $course_id, '_mathcourse_grade', true );
								$cover       = get_post_meta( $course_id, '_mathcourse_cover', true );
								$builder_url = add_query_arg(
									array(
										'page'      => 'create-course',
										'course_id' => $course_id,
									),
									admin_url( 'admin.php' )
								);
								?>
								<tr>
									<td class="column-cover">
										<?php if ( $cover ) : ?>
											<img src="<?php echo esc_url( $cover ); ?>" alt="" width="64" height="42" style="object-fit:cover;border-radius:6px;">
										<?php else : ?>
											<div class="mathcourse-cover-placeholder">数学</div>
										<?php endif; ?>
									</td>
									<td>
										<strong><a href="<?php echo esc_url( $builder_url ); ?>"><?php echo esc_html( get_the_title() ); ?></a></strong>
										<?php if ( get_the_excerpt() ) : ?>
											<div class="description"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 16 ) ); ?></div>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $this->type_label( $course_type ) ); ?></td>
									<td><?php echo esc_html( $this->grade_label( $grade ) ); ?></td>
									<td><?php echo esc_html( $this->status_label( get_post_status() ) ); ?></td>
									<td><?php echo esc_html( get_the_date( 'Y-m-d' ) ); ?></td>
									<td>
										<a class="button button-small" href="<?php echo esc_url( $builder_url ); ?>">编辑课程</a>
										<a class="button-link-delete" style="margin-left:8px;" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=mathcourse-courses&action=trash&course_id=' . $course_id ), 'mathcourse_trash_course_' . $course_id ) ); ?>">移至回收站</a>
									</td>
								</tr>
							<?php endwhile; ?>
						<?php else : ?>
							<tr>
								<td colspan="7" style="padding:32px;text-align:center;">还没有课程。点击右上角“新建课程”开始。</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Handle create and trash actions.
	 *
	 * @return void
	 */
	private function handle_actions() {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'new' === $action ) {
			check_admin_referer( 'mathcourse_new_course' );

			$course_id = wp_insert_post(
				array(
					'post_title'  => '新课程',
					'post_type'   => tutor()->course_post_type,
					'post_status' => 'draft',
					'post_author' => get_current_user_id(),
				),
				true
			);

			if ( is_wp_error( $course_id ) ) {
				add_settings_error( 'mathcourse', 'create_failed', $course_id->get_error_message(), 'error' );
				return;
			}

			update_post_meta( $course_id, '_mathcourse_type', 'topic' );
			update_post_meta( $course_id, '_mathcourse_grade', '' );

			$builder_url = add_query_arg(
				array(
					'page'      => 'create-course',
					'course_id' => $course_id,
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $builder_url );
			exit;
		}

		if ( 'trash' === $action ) {
			$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;

			if ( ! $course_id ) {
				return;
			}

			check_admin_referer( 'mathcourse_trash_course_' . $course_id );

			if ( tutor()->course_post_type !== get_post_type( $course_id ) ) {
				return;
			}

			wp_trash_post( $course_id );
		}
	}

	/**
	 * Render Tutor LMS missing notice.
	 *
	 * @return void
	 */
	private function render_tutor_notice() {
		?>
		<div class="wrap">
			<h1>课程管理</h1>
			<div class="notice notice-error inline">
				<p><strong>MathCourse 需要 Tutor LMS。</strong> 请先启用 Tutor LMS 4.0.4，再使用课程管理。</p>
			</div>
		</div>
		<?php
	}

	/**
	 * @param string $type Course type.
	 * @return string
	 */
	private function type_label( $type ) {
		$labels = array(
			'topic'         => '专题课程',
			'supplementary' => '大培优配套',
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : '未设置';
	}

	/**
	 * @param string $grade Grade code.
	 * @return string
	 */
	private function grade_label( $grade ) {
		$labels = array(
			'7' => '七年级',
			'8' => '八年级',
			'9' => '九年级',
			'10' => '高一',
			'11' => '高二',
			'12' => '高三',
		);

		return isset( $labels[ $grade ] ) ? $labels[ $grade ] : '未设置';
	}

	/**
	 * @param string $status Post status.
	 * @return string
	 */
	private function status_label( $status ) {
		$labels = array(
			'publish' => '已发布',
			'draft'   => '草稿',
			'pending' => '待审核',
			'future'  => '定时发布',
			'private' => '私密',
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
