<?php
namespace MathCourse\Learning;

defined('ABSPATH') || exit;

class Course_Learning {

    public function __construct(){
        add_shortcode('mathcourse_course_learning', array($this,'render'));
    }

    public function render(){
        if(!is_user_logged_in()){
            return '<p>请登录后学习</p>';
        }

        $course_id = get_the_ID();
        if(!$course_id){
            return '';
        }

        $user_id = get_current_user_id();

        ob_start();
        ?>
        <div class="mc-course-learning">
            <div class="mc-course-title">
                <h1><?php echo esc_html(get_the_title($course_id)); ?></h1>
                <div class="mc-course-desc">
                    <?php echo wp_trim_words(get_the_content(),80); ?>
                </div>
            </div>

            <div class="mc-course-outline">
                <?php
                if(function_exists('tutor_utils')){
                    $topics = tutor_utils()->get_course_topics($course_id);
                    if($topics){
                        foreach($topics as $topic){
                            echo '<section class="mc-topic">';
                            echo '<h2>'.esc_html($topic->post_title).'</h2>';
                            $lessons = tutor_utils()->get_course_contents_by_topic($topic->ID);
                            if($lessons){
                                foreach($lessons as $lesson){
                                    $status = Lesson_Status::get($lesson->ID,$user_id);
                                    echo '<div class="mc-lesson-item">';
                                    echo '<span>'.$status['icon'].'</span>';
                                    echo '<a href="'.esc_url(get_permalink($lesson->ID)).'">'.esc_html($lesson->post_title).'</a>';
                                    echo '<small>'.esc_html($status['label']).'</small>';
                                    echo '</div>';
                                }
                            }
                            echo '</section>';
                        }
                    }
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
