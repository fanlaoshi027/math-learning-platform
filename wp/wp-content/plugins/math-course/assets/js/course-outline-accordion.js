document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.mc-course-player__topics').forEach(function (topics) {
        var topicList = Array.prototype.slice.call(topics.querySelectorAll(':scope > .mc-course-player__topic'));
        if (!topicList.length) return;

        topicList.forEach(function (topic, index) {
            var heading = topic.querySelector(':scope > h3');
            var lessons = topic.querySelector(':scope > .mc-course-player__lessons');
            if (!heading || !lessons) return;

            function closeOtherTopics() {
                topicList.forEach(function (other) {
                    if (other !== topic) {
                        other.classList.remove('is-open');
                        var otherHeading = other.querySelector(':scope > h3');
                        var otherLessons = other.querySelector(':scope > .mc-course-player__lessons');
                        if (otherHeading) otherHeading.setAttribute('aria-expanded', 'false');
                        if (otherLessons) otherLessons.hidden = true;
                    }
                });
            }

            function setOpen(open) {
                if (open) closeOtherTopics();
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
        });

        var initialOpen = topicList.find(function (topic) {
            return topic.classList.contains('is-open') || !!topic.querySelector('.mc-course-player__item.is-active');
        }) || topicList[0];

        topicList.forEach(function (topic) {
            var heading = topic.querySelector(':scope > h3');
            var lessons = topic.querySelector(':scope > .mc-course-player__lessons');
            if (!heading || !lessons) return;
            var shouldOpen = topic === initialOpen;
            topic.classList.toggle('is-open', shouldOpen);
            heading.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            lessons.hidden = !shouldOpen;
        });

        var player = topics.closest('.mc-course-player');
        var toggleButton = player ? player.querySelector('.mc-learning-directory-toggle') : null;
        if (toggleButton) {
            toggleButton.addEventListener('click', function () {
                var collapsed = topics.hasAttribute('hidden');
                topics.hidden = !collapsed;
                toggleButton.setAttribute('aria-expanded', collapsed ? 'true' : 'false');
                toggleButton.textContent = collapsed ? '目录' : '收起';
            });
        }
    });
});
