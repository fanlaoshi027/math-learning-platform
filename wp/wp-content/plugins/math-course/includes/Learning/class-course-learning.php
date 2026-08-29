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

            <div class="mc-course-header-card">
                <h1><?php echo esc_html(get_the_title($course_id)); ?></h1>

                <div class="mc-course-desc">
                    <?php echo wp_trim_words(get_the_content(),80); ?>
                </div>

                <?php
                $progress = null;
                if(class_exists('MathCourse\\Progress\\Progress_Service')){
                    $service = new \MathCourse\Progress\Progress_Service();
                    if(method_exists($service,'get_course_progress')){
                        $progress = $service->get_course_progress($user_id,$course_id);
                    }
                }
                ?>

                <?php if($progress): ?>
                <div class="mc-course-progress">
                    已完成 <?php echo intval($progress['completed'] ?? 0); ?> /
                    <?php echo intval($progress['total'] ?? 0); ?> 课时
                    （<?php echo intval($progress['percent'] ?? 0); ?>%）
                </div>
                <?php endif; ?>

            </div>

            <div class="mc-course-outline">
                <h2>课程目录</h2>

                <?php
                if(function_exists('tutor_utils')){

                    $topics=tutor_utils()->get_course_topics($course_id);

                    if($topics){
                        foreach($topics as $topic){

                            echo '<section class="mc-topic">';
                            echo '<h3>'.esc_html($topic->post_title).'</h3>';

                            $lessons=tutor_utils()->get_course_contents_by_topic($topic->ID);

                            if($lessons){

                                foreach($lessons as $lesson){

                                    $completed=false;

                                    if(function_exists('tutor_utils')){
                                        $completed=tutor_utils()->is_completed_lesson($lesson->ID,$user_id);
                                    }

                                    $class=$completed?'mc-lesson-completed':'mc-lesson-normal';

                                    echo '<div class="mc-lesson-item '.esc_attr($class).'">';

                                    echo $completed ? '✓ ' : '○ ';

                                    echo '<a href="'.esc_url(get_permalink($lesson->ID)).'">';
                                    echo esc_html($lesson->post_title);
                                    echo '</a>';

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
