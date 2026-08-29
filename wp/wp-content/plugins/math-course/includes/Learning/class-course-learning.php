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
        $total = 0;
        $completed = 0;

        ob_start();
        ?>
        <div class="mc-course-learning">

            <div class="mc-course-header-card">
                <h1><?php echo esc_html(get_the_title($course_id)); ?></h1>

                <div class="mc-course-desc">
                    <?php echo wp_trim_words(get_the_content(),80); ?>
                </div>

                <?php if(function_exists('tutor_utils')): ?>
                <?php
                    $topics = tutor_utils()->get_course_topics($course_id);
                    if($topics){
                        foreach($topics as $topic){
                            $items = tutor_utils()->get_course_contents_by_topic($topic->ID);
                            if($items){
                                $total += count($items);
                                foreach($items as $item){
                                    if(tutor_utils()->is_completed_lesson($item->ID,$user_id)){
                                        $completed++;
                                    }
                                }
                            }
                        }
                    }
                ?>
                <?php endif; ?>

                <div class="mc-course-progress">
                    完成进度：
                    <?php echo intval($completed); ?> /
                    <?php echo intval($total); ?> 课
                </div>
            </div>

            <div class="mc-course-outline">
                <h2>课程目录</h2>
                <?php
                if(function_exists('tutor_utils')){
                    $topics = tutor_utils()->get_course_topics($course_id);
                    if($topics){
                        foreach($topics as $topic){
                            echo '<section class="mc-topic">';
                            echo '<h3>'.esc_html($topic->post_title).'</h3>';

                            $lessons = tutor_utils()->get_course_contents_by_topic($topic->ID);
                            if($lessons){
                                foreach($lessons as $lesson){
                                    $status = Lesson_Status::get($lesson->ID,$user_id);
                                    $class = 'mc-lesson-item mc-status-'.$status['type'];

                                    echo '<div class="'.esc_attr($class).'">';
                                    echo '<span>'.esc_html($status['icon']).'</span>';

                                    if(!empty($status['allow'])){
                                        echo '<a href="'.esc_url(get_permalink($lesson->ID)).'">'.esc_html($lesson->post_title).'</a>';
                                    }else{
                                        echo '<span>'.esc_html($lesson->post_title).'</span>';
                                    }

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
