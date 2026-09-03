document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.mc-course-player__mobile-topic-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            var topic = button.closest('.mc-course-player__mobile-topic');
            if (!topic) return;
            var lessons = topic.querySelector('.mc-course-player__mobile-lessons');
            var open = topic.classList.toggle('is-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (lessons) lessons.hidden = !open;
        });
    });
});
