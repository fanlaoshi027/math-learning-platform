<?php

defined('ABSPATH') || exit;


if(
    empty($video_url)
){

    return;

}

?>


<div class="mathcourse-video">


<video

controls

preload="metadata"

class="mathcourse-player">


<source

src="<?php echo esc_url($video_url); ?>"

type="application/x-mpegURL">


</video>


</div>