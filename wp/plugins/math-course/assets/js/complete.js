jQuery(document).ready(function($){


$('.mathcourse-complete').on(
'click',
function(){


$.post(
ajaxurl,
{

action:'mathcourse_complete',

lesson_id:
$(this).data('lesson'),

course_id:
$(this).data('course')

}

);


});


});