document.addEventListener('DOMContentLoaded', function () {
    var wrap = document.querySelector('.wrap.mathcourse-editor');
    if (!wrap) return;

    function getTopics() {
        var topics = [];
        wrap.querySelectorAll('.mathcourse-topic-card[data-topic-id]').forEach(function (card) {
            var title = card.querySelector('.mathcourse-topic-header strong');
            if (!title) return;
            topics.push({ id: card.getAttribute('data-topic-id'), title: title.textContent.trim() });
        });
        return topics;
    }

    function currentTopicId(lessonId) {
        var row = lessonId ? wrap.querySelector('.mathcourse-lesson-row[data-lesson-id="' + String(lessonId).replace(/"/g, '') + '"]') : null;
        var card = row ? row.closest('.mathcourse-topic-card') : null;
        return card ? card.getAttribute('data-topic-id') : '';
    }

    function install(root) {
        if (!root) return;
        var lessonId = root.getAttribute('data-lesson-editor-id');
        if (!lessonId || root.querySelector('.mathcourse-lesson-topic-field')) return;
        var topics = getTopics();
        if (!topics.length) return;

        var current = currentTopicId(lessonId);
        var field = document.createElement('div');
        field.className = 'mathcourse-lesson-topic-field';
        field.innerHTML = '<label for="lesson_target_topic"><strong>归属专题</strong></label><select id="lesson_target_topic" name="lesson_target_topic"></select><small>可把本课时移动到当前课程的其他专题，保存后立即生效。</small>';
        var select = field.querySelector('select');
        topics.forEach(function (topic) {
            var option = document.createElement('option');
            option.value = topic.id;
            option.textContent = topic.title;
            option.selected = String(topic.id) === String(current);
            select.appendChild(option);
        });

        var media = root.querySelector('.mathcourse-media-settings');
        var fields = root.querySelector('.mathcourse-lesson-fields');
        if (media && media.parentNode) media.parentNode.insertBefore(field, media);
        else if (fields) fields.appendChild(field);
    }

    function scan() {
        install(wrap.querySelector('.mathcourse-lesson-editor'));
    }

    scan();
    new MutationObserver(scan).observe(wrap, { childList: true, subtree: true });
});
