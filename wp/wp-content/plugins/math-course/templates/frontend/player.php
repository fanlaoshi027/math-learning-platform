<?php

defined('ABSPATH') || exit;

?>

<div class="mathcourse-video">

<?php if ( empty($video_url) ) : ?>

    <div class="mathcourse-player-placeholder" role="status" aria-label="本节视频暂未上传">
        <div class="mathcourse-player-placeholder__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="5" width="18" height="14" rx="3"></rect>
                <path d="m10 9 5 3-5 3V9Z"></path>
            </svg>
        </div>
        <div class="mathcourse-player-placeholder__title">本节视频暂未上传</div>
        <div class="mathcourse-player-placeholder__text">老师正在准备课程内容，请稍后再来学习</div>
    </div>

<?php else : ?>

    <video
        controls
        preload="metadata"
        class="mathcourse-player">

        <source
            src="<?php echo esc_url($video_url); ?>"
            type="application/x-mpegURL">

    </video>

<?php endif; ?>

</div>
