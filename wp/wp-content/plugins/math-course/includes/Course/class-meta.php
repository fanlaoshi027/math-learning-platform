<?php

namespace MathCourse\Course;

defined( 'ABSPATH' ) || exit;

class Meta {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	public function add() {
		if ( ! function_exists( 'tutor' ) ) {
			return;
		}

		add_meta_box(
			'mathcourse_course_settings',
			'MathCourse 课程信息',
			array( $this, 'box' ),
			tutor()->course_post_type,
			'side',
			'high'
		);

		add_meta_box(
			'mathcourse_cover',
			'课程封面',
			array( $this, 'cover_box' ),
			tutor()->course_post_type,
			'side',
			'default'
		);
	}

	public function box( $post ) {
		wp_nonce_field( 'mathcourse_course_meta', 'mathcourse_course_meta_nonce' );

		$type  = get_post_meta( $post->ID, '_mathcourse_type', true );
		$grade = get_post_meta( $post->ID, '_mathcourse_grade', true );
		?>
		<p>
			<label for="mathcourse_type"><strong>课程类型</strong></label>
			<select id="mathcourse_type" name="mathcourse_type" style="width:100%;margin-top:6px;">
				<option value="topic" <?php selected( $type, 'topic' ); ?>>专题课程</option>
				<option value="supplementary" <?php selected( $type, 'supplementary' ); ?>>大培优配套</option>
			</select>
		</p>
		<p>
			<label for="mathcourse_grade"><strong>年级</strong></label>
			<select id="mathcourse_grade" name="mathcourse_grade" style="width:100%;margin-top:6px;">
				<option value="">请选择</option>
				<option value="7" <?php selected( $grade, '7' ); ?>>七年级</option>
				<option value="8" <?php selected( $grade, '8' ); ?>>八年级</option>
				<option value="9" <?php selected( $grade, '9' ); ?>>九年级</option>
				<option value="10" <?php selected( $grade, '10' ); ?>>高一</option>
				<option value="11" <?php selected( $grade, '11' ); ?>>高二</option>
				<option value="12" <?php selected( $grade, '12' ); ?>>高三</option>
			</select>
		</p>
		<?php
	}

	public function cover_box( $post ) {
		$value = get_post_meta( $post->ID, '_mathcourse_cover', true );
		?>
		<input
			type="url"
			name="mathcourse_cover"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="https://..."
			style="width:100%;"
		>
		<p class="description">第一版先填写图片 URL，后续接入 WordPress 媒体库选择器。</p>
		<?php
	}

	public function save( $post_id, $post ) {
		if ( ! function_exists( 'tutor' ) || ! is_object( $post ) ) {
			return;
		}

		if ( tutor()->course_post_type !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if (
			! isset( $_POST['mathcourse_course_meta_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['mathcourse_course_meta_nonce'] ) ),
				'mathcourse_course_meta'
			)
		) {
			return;
		}

		if ( isset( $_POST['mathcourse_type'] ) ) {
			$type = sanitize_key( wp_unslash( $_POST['mathcourse_type'] ) );
			if ( in_array( $type, array( 'topic', 'supplementary' ), true ) ) {
				update_post_meta( $post_id, '_mathcourse_type', $type );
			}
		}

		if ( isset( $_POST['mathcourse_grade'] ) ) {
			$grade = sanitize_key( wp_unslash( $_POST['mathcourse_grade'] ) );
			if ( in_array( $grade, array( '', '7', '8', '9', '10', '11', '12' ), true ) ) {
				update_post_meta( $post_id, '_mathcourse_grade', $grade );
			}
		}

		if ( isset( $_POST['mathcourse_cover'] ) ) {
			update_post_meta(
				$post_id,
				'_mathcourse_cover',
				esc_url_raw( wp_unslash( $_POST['mathcourse_cover'] ) )
			);
		}
	}
}
