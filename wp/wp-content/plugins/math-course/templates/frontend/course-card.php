<?php
defined('ABSPATH') || exit;
$course_id = isset($course_id) ? absint($course_id) : get_the_ID();
$title = isset($title) ? $title : get_the_title($course_id);
$image = isset($image) ? $image : get_post_meta($course_id,'_mathcourse_cover',true);
$grade = get_post_meta($course_id,'_mathcourse_grade',true);
$type = get_post_meta($course_id,'_mathcourse_type',true);
$color = get_post_meta($course_id,'_mathcourse_cover_color',true) ?: '#12346b';
$cover_text = get_post_meta($course_id,'_mathcourse_cover_text',true) ?: $title;
$grades = array('7'=>'七年级','8'=>'八年级','9'=>'九年级','10'=>'高一','11'=>'高二','12'=>'高三');
?>
<article class="mathcourse-card">
    <?php if(!empty($image)): ?>
        <div class="mathcourse-cover-wrap"><img src="<?php echo esc_url($image); ?>" class="mathcourse-cover" alt="<?php echo esc_attr($title); ?>" loading="lazy"></div>
    <?php else: ?>
        <div class="mathcourse-color-cover" style="--mc-cover-color:<?php echo esc_attr($color); ?>">
            <span class="mathcourse-color-cover__tag"><?php echo esc_html($grades[$grade] ?? '初中数学'); ?></span>
            <strong><?php echo esc_html($cover_text); ?></strong>
            <small><?php echo esc_html('supplementary'===$type?'教辅配套':'专题课程'); ?></small>
            <i aria-hidden="true">∑</i>
        </div>
    <?php endif; ?>
    <h3><?php echo esc_html($title); ?></h3>
</article>
