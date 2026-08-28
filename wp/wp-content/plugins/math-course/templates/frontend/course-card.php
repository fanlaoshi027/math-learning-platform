<?php

defined('ABSPATH') || exit;

?>


<div class="mathcourse-card">


<?php if(!empty($image)): ?>


<img

src="<?php echo esc_url($image); ?>"

class="mathcourse-cover">


<?php else: ?>


<div class="mathcourse-default-cover">

<?php echo esc_html($title); ?>

</div>


<?php endif; ?>


<h3>

<?php echo esc_html($title); ?>

</h3>


</div>