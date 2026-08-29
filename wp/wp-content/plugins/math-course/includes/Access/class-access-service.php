<?php
namespace MathCourse\Access;

defined('ABSPATH') || exit;

class Access_Service {

    /**
     * 授权数据表
     */
    private function table() {

        global $wpdb;

        return $wpdb->prefix . 'mathcourse_access';
    }


    /**
     * 判断用户是否拥有课程权限
     */
    public function has_access($user_id, $course_id) {

        $info = $this->get_access_info($user_id, $course_id);

        return !empty($info['access']);
    }


    /**
     * 获取授权详细信息
     */
    public function get_access_info($user_id, $course_id) {


        $user_id  = absint($user_id);
        $course_id = absint($course_id);


        $result = array(
            'access'     => false,
            'status'     => 'none',
            'expires_at' => null,
        );


        if (!$user_id || !$course_id) {

            return $result;

        }


        global $wpdb;


        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT status, expires_at 
                 FROM {$this->table()} 
                 WHERE user_id=%d 
                 AND course_id=%d 
                 LIMIT 1",
                $user_id,
                $course_id
            )
        );


        if (!$row) {

            return $result;

        }


        $result['status'] = $row->status;
        $result['expires_at'] = $row->expires_at;


        if ($row->status !== 'active') {

            return $result;

        }


        if (!empty($row->expires_at)) {


            if (strtotime($row->expires_at) <= current_time('timestamp')) {

                $result['status'] = 'expired';

                return $result;

            }

        }


        $result['access'] = true;


        return $result;

    }



    /**
     * 授权课程
     */
    public function grant($user_id, $course_id, $expires_at = null) {


        $user_id = absint($user_id);
        $course_id = absint($course_id);


        if (!$user_id || !$course_id) {

            return false;

        }


        global $wpdb;


        return false !== $wpdb->replace(

            $this->table(),

            array(

                'user_id'    => $user_id,

                'course_id'  => $course_id,

                'status'     => 'active',

                'granted_at' => current_time('mysql'),

                'expires_at' => $expires_at
                    ? sanitize_text_field($expires_at)
                    : null,

            ),

            array(
                '%d',
                '%d',
                '%s',
                '%s',
                '%s'
            )

        );


    }



    /**
     * 取消授权
     */
    public function revoke($user_id, $course_id) {


        global $wpdb;


        return false !== $wpdb->update(

            $this->table(),

            array(

                'status'=>'revoked'

            ),

            array(

                'user_id'=>absint($user_id),

                'course_id'=>absint($course_id)

            ),

            array('%s'),

            array('%d','%d')

        );


    }



    /**
     * 获取用户所有课程授权
     *
     * 用于：
     * 学员中心
     * 我的课程
     */
    public function get_user_courses($user_id) {


        $user_id = absint($user_id);


        if (!$user_id) {

            return array();

        }


        global $wpdb;


        $rows = $wpdb->get_results(

            $wpdb->prepare(

                "SELECT *
                 FROM {$this->table()}
                 WHERE user_id=%d
                 AND status='active'
                 ORDER BY granted_at DESC",

                 $user_id

            )

        );


        return $rows ?: array();

    }




    /**
     * 判断课程是否允许试看
     *
     * 后续连接 Lesson Meta
     */
    public function can_preview($course_id, $lesson_id) {


        $lesson_id = absint($lesson_id);


        if (!$lesson_id) {

            return false;

        }


        $preview = get_post_meta(

            $lesson_id,

            '_mathcourse_preview',

            true

        );


        return $preview === 'yes';

    }





    /**
     * 播放器统一权限入口
     *
     * 返回：
     * true  可以播放
     * false 禁止播放
     */
    public function can_watch_lesson($user_id, $course_id, $lesson_id) {


        // 登录用户已购买

        if ($this->has_access($user_id, $course_id)) {

            return true;

        }


        // 免费试看

        if ($this->can_preview($course_id, $lesson_id)) {

            return true;

        }


        return false;

    }


}
