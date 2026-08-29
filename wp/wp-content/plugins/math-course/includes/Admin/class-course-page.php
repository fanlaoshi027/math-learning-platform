<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

class Course_Page {

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mathcourse' ) );
		}

		if ( ! function_exists( 'tutor' ) ) {
			$this->render_tutor_notice();
			return;
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		$type   = isset( $_GET['course_type'] ) ? sanitize_key( wp_unslash( $_GET['course_type'] ) ) : 'all';
		$grade  = isset( $_GET['grade'] ) ? sanitize_key( wp_unslash( $_GET['grade'] ) ) : 'all';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		$args = array(
			'post_type'      => tutor()->course_post_type,
			'posts_per_page' => 20,
			'paged'          => $paged,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			's'              => $search,
		);

		if ( in_array( $status, array( 'publish', 'draft', 'pending', 'future', 'private' ), true ) ) {
			$args['post_status'] = $status;
		}

		if ( in_array( $type, array( 'topic', 'supplementary' ), true ) ) {
			$args['meta_query'][] = array(
				'key'   => '_mathcourse_type',
				'value' => $type,
			);
		}

		if ( in_array( $grade, array( '7', '8', '9', '10', '11', '12' ), true ) ) {
			$args['meta_query'][] = array(
				'key'   => '_mathcourse_grade',
				'value' => $grade,
			);
		}

		$query = new \WP_Query( $args );
		$base_url = admin_url( 'admin.php?page=mathcourse-courses' );
		?>
		<div class="wrap">
			<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:8px;">
				<div>
					<h1 class="wp-heading-inline">课程管理</h1>
					<p style="color:#646970;margin-top:6px;">用简洁的界面管理课程；课程内容和课时仍由 Tutor LMS 负责。</p>
				</div>
				<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=mathcourse-courses&action=new' ), 'mathcourse_new_course' ) ); ?>">新建课程</a>
			</div>
			<hr class="wp-header-end">

			<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:20px 0;">
				<input type="hidden" name="page" value="mathcourse-courses">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="搜索课程名称" style="min-width:280px;">
				<select name="course_type">
					<option value="all" <?php selected( $type, 'all' ); ?>>全部类型</option>
					<option value="topic" <?php selected( $type, 'topic' ); ?>>专题课程</option>
					<option value="supplementary" <?php selected( $type, 'supplementary' ); ?>>教辅配套课</option>
				</select>
				<select name="grade">
					<option value="all" <?php selected( $grade, 'all' ); ?>>全部年级</option>
					<option value="7" <?php selected( $grade, '7' ); ?>>七年级</option>
					<option value="8" <?php selected( $grade, '8' ); ?>>八年级</option>
					<option value="9" <?php selected( $grade, '9' ); ?>>九年级</option>
					<option value="10" <?php selected( $grade, '10' ); ?>>高一</option>
					<option value="11" <?php selected( $grade, '11' ); ?>>高二</option>
					<option value="12" <?php selected( $grade, '12' ); ?>>高三</option>
				</select>
				<select name="status">
					<option value="all" <?php selected( $status, 'all' ); ?>>全部状态</option>
					<option value="publish" <?php selected( $status, 'publish' ); ?>>已发布</option>
					<option value="draft" <?php selected( $status, 'draft' ); ?>>草稿</option>
					<option value="private" <?php selected( $status, 'private' ); ?>>私密</option>
				</select>
				<button class="button">筛选</button>
				<?php if ( $search || 'all' !== $type || 'all' !== $grade || 'all' !== $status ) : ?><a class="button" href="<?php echo esc_url( $base_url ); ?>">清除筛选</a><?php endif; ?>
			</form>

			<div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;overflow:hidden;">
				<table class="wp-list-table widefat fixed striped">
					<thead><tr><th style="width:80px;">封面</th><th>课程</th><th style="width:110px;">类型</th><th style="width:90px;">年级</th><th style="width:90px;">内容</th><th style="width:80px;">状态</th><th style="width:105px;">时间</th><th style="width:170px;">操作</th></tr></thead>
					<tbody>
					<?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>
						<?php
						$course_id   = get_the_ID();
						$course_type = get_post_meta( $course_id, '_mathcourse_type', true );
						$grade       = get_post_meta( $course_id, '_mathcourse_grade', true );
						$cover       = get_post_meta( $course_id, '_mathcourse_cover', true );
						$editor_url  = add_query_arg( array( 'page' => 'mathcourse-course-edit', 'course_id' => $course_id ), admin_url( 'admin.php' ) );
						$topics      = get_posts( array( 'post_type' => 'topics', 'post_parent' => $course_id, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
						$lesson_count = 0;
						foreach ( $topics as $topic_id ) {
							$lesson_count += count( get_posts( array( 'post_type' => tutor()->lesson_post_type, 'post_parent' => $topic_id, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
						}
						$view_url = get_permalink( $course_id );
						?>
						<tr>
							<td><?php if ( $cover ) : ?><img src="<?php echo esc_url( $cover ); ?>" alt="" width="64" height="42" style="object-fit:cover;border-radius:6px;"><?php else : ?><div style="width:64px;height:42px;border-radius:6px;background:#eef3f8;display:flex;align-items:center;justify-content:center;color:#36506b;font-size:12px;">数学</div><?php endif; ?></td>
							<td><strong><a href="<?php echo esc_url( $editor_url ); ?>"><?php echo esc_html( get_the_title() ); ?></a></strong><?php if ( get_the_excerpt() ) : ?><div style="color:#646970;margin-top:4px;"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 16 ) ); ?></div><?php endif; ?></td>
							<td><?php echo esc_html( $this->type_label( $course_type ) ); ?></td>
							<td><?php echo esc_html( $this->grade_label( $grade ) ); ?></td>
							<td><?php echo esc_html( count( $topics ) ); ?> 个专题<br><span style="color:#646970;"><?php echo esc_html( $lesson_count ); ?> 个课时</span></td>
							<td><?php echo esc_html( $this->status_label( get_post_status() ) ); ?></td>
							<td><?php echo esc_html( get_the_date( 'Y-m-d' ) ); ?></td>
							<td><a class="button button-small" href="<?php echo esc_url( $editor_url ); ?>">编辑课程</a><?php if ( $view_url ) : ?> <a class="button button-small" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener">查看前台</a><?php endif; ?><br><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=mathcourse-courses&action=trash&course_id=' . $course_id ), 'mathcourse_trash_course_' . $course_id ) ); ?>">移至回收站</a></td>
						</tr>
					<?php endwhile; else : ?><tr><td colspan="8" style="padding:32px;text-align:center;">没有找到符合条件的课程。</td></tr><?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php
			$total_pages = (int) $query->max_num_pages;
			if ( $total_pages > 1 ) :
				$pagination_base = add_query_arg( array( 'paged' => '%#%', 's' => $search, 'course_type' => $type, 'grade' => $grade, 'status' => $status ), $base_url );
				?>
				<div style="padding:18px 0;"> <?php echo wp_kses_post( paginate_links( array( 'base' => $pagination_base, 'format' => '', 'current' => $paged, 'total' => $total_pages, 'type' => 'list', 'prev_text' => '‹', 'next_text' => '›' ) ) ); ?> </div>
			<?php endif; ?>
		</div>
		<?php
		wp_reset_postdata();
	}

	private function render_tutor_notice() {
		?>
		<div class="wrap"><h1>课程管理</h1><div class="notice notice-error inline"><p><strong>MathCourse 需要 Tutor LMS。</strong> 请先启用 Tutor LMS 4.0.4，再使用课程管理。</p></div></div>
		<?php
	}

	private function type_label( $type ) {
		$labels = array( 'topic' => '专题课程', 'supplementary' => '教辅配套课' );
		return isset( $labels[ $type ] ) ? $labels[ $type ] : '未设置';
	}

	private function grade_label( $grade ) {
		$labels = array( '7' => '七年级', '8' => '八年级', '9' => '九年级', '10' => '高一', '11' => '高二', '12' => '高三' );
		return isset( $labels[ $grade ] ) ? $labels[ $grade ] : '未设置';
	}

	private function status_label( $status ) {
		$labels = array( 'publish' => '已发布', 'draft' => '草稿', 'pending' => '待审核', 'future' => '定时发布', 'private' => '私密' );
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
