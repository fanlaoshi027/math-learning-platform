document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.mc-course-player__topics').forEach(function (topics) {
        var topicList = Array.prototype.slice.call(topics.querySelectorAll(':scope > .mc-course-player__topic'));
        if (!topicList.length) return;

        topicList.forEach(function (topic, index) {
            var heading = topic.querySelector(':scope > h3');
            var lessons = topic.querySelector(':scope > .mc-course-player__lessons');
            if (!heading || !lessons) return;

            function setOpen(open) {
                topic.classList.toggle('is-open', open);
                heading.setAttribute('aria-expanded', open ? 'true' : 'false');
                lessons.hidden = !open;
            }

            function toggle() { setOpen(!topic.classList.contains('is-open')); }

            var id = 'mc-lessons-' + index + '-' + Math.random().toString(36).slice(2, 8);
            lessons.id = id;
            heading.setAttribute('role', 'button');
            heading.setAttribute('tabindex', '0');
            heading.setAttribute('aria-controls', id);

            heading.addEventListener('click', toggle);
            heading.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggle();
                }
            });

            setOpen(topic.classList.contains('is-open') || !!topic.querySelector('.mc-course-player__item.is-active'));
        });
    });
});