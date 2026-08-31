document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.mc-course-player__topics').forEach(function (topics) {
        var topicList = Array.prototype.slice.call(topics.querySelectorAll(':scope > .mc-course-player__topic'));
        if (!topicList.length) return;

        topicList.forEach(function (topic) {
            var heading = topic.querySelector(':scope > h3');
            var lessons = topic.querySelector(':scope > .mc-course-player__lessons');
            if (!heading || !lessons) return;

            heading.setAttribute('role', 'button');
            heading.setAttribute('tabindex', '0');
            heading.setAttribute('aria-expanded', 'false');
            topic.classList.remove('is-open');

            function toggle(force) {
                var open = typeof force === 'boolean' ? force : !topic.classList.contains('is-open');
                topic.classList.toggle('is-open', open);
                heading.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            heading.addEventListener('click', function () { toggle(); });
            heading.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggle();
                }
            });

            if (topic.querySelector('.mc-course-player__item.is-active')) toggle(true);
        });
    });
});
