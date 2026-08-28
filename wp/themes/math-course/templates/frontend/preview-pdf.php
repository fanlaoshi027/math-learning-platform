<?php

defined('ABSPATH') || exit;


if(
    empty($pdf_url)
){

    return;

}

?>


<div class="mathcourse-preview-pdf">


<a

href="<?php echo esc_url($pdf_url); ?>"

target="_blank">


查看试听讲义 PDF


</a>


</div>