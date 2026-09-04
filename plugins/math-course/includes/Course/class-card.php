<?php
namespace MathCourse\Course;
defined('ABSPATH') || exit;

class Card {
    public function render($course_id){
        $course_id=absint($course_id);
        $title=get_the_title($course_id);
        $image=get_post_meta($course_id,'_mathcourse_cover',true);
        $grade=get_post_meta($course_id,'_mathcourse_grade',true);
        $type=get_post_meta($course_id,'_mathcourse_type',true);
        $color=get_post_meta($course_id,'_mathcourse_cover_color',true) ?: '#12346b';
        $text=get_post_meta($course_id,'_mathcourse_cover_text',true) ?: $title;
        $grade_names=array('7'=>'七年级','8'=>'八年级','9'=>'九年级','10'=>'高一','11'=>'高二','12'=>'高三');
        $type_name='supplementary'===$type?'教辅配套':'专题课程';
        ob_start(); ?>
        <article class="mathcourse-card">
            <?php if($image): ?>
                <div class="mathcourse-cover-wrap"><img src="<?php echo esc_url($image); ?>" class="mathcourse-cover" alt="<?php echo esc_attr($title); ?>" loading="lazy"></div>
            <?php else: ?>
                <div class="mathcourse-color-cover" style="--mc-cover-color:<?php echo esc_attr($color); ?>">
                    <span class="mathcourse-color-cover__tag"><?php echo esc_html($grade_names[$grade] ?? '初中数学'); ?></span>
                    <strong><?php echo esc_html($text); ?></strong>
                    <small><?php echo esc_html($type_name); ?></small>
                    <i aria-hidden="true">∑</i>
                </div>
            <?php endif; ?>
            <h3><?php echo esc_html($title); ?></h3>
        </article>
        <?php return ob_get_clean();
    }
}
