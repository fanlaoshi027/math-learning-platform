document.addEventListener('DOMContentLoaded', function () {
    var wrap = document.querySelector('.wrap.mathcourse-editor');
    var params = new URLSearchParams(window.location.search);
    var page = params.get('page');

    function normalizeHlsInput(root) {
        var input = (root || document).querySelector('#lesson_hls_edit');
        if (input) {
            input.type = 'text';
            input.setAttribute('inputmode', 'url');
            input.removeAttribute('pattern');
            input.removeAttribute('required');
        }
    }

    function setSelectedLesson(lessonId) {
        if (!wrap) return;
        wrap.querySelectorAll('.mathcourse-lesson-row').forEach(function (row) {
            row.classList.toggle('is-selected', String(row.getAttribute('data-lesson-id')) === String(lessonId));
        });
    }

    function placeholder(title, text) {
        return '<div class="mathcourse-lesson-placeholder"><div class="mathcourse-placeholder-icon">…</div><strong>' + title + '</strong><span>' + text + '</span></div>';
    }

    function bindLessonPanel(root) {
        normalizeHlsInput(root || document);
        if (!root) return;
        var close = root.querySelector('.mathcourse-lesson-close');
        if (close) {
            close.addEventListener('click', function (event) {
                event.preventDefault();
                var side = document.getElementById('mathcourse-lesson-side');
                if (side) side.innerHTML = '<div class="mathcourse-lesson-placeholder"><div class="mathcourse-placeholder-icon">✎</div><strong>选择一个课时</strong><span>点击左侧课时的「编辑」，这里会直接显示课时属性。</span></div>';
                wrap.querySelectorAll('.mathcourse-lesson-row').forEach(function (row) { row.classList.remove('is-selected'); });
                var url = new URL(window.location.href);
                url.searchParams.delete('lesson_id');
                window.history.replaceState({}, '', url.toString());
            });
        }
    }

    function openLessonEditor(link) {
        if (!wrap || !link) return;
        var side = document.getElementById('mathcourse-lesson-side');
        if (!side) return;
        var url = link.href;
        var lessonId = link.getAttribute('data-lesson-id');
        side.classList.add('is-loading');
        side.setAttribute('aria-busy', 'true');
        side.innerHTML = placeholder('正在加载课时', '正在读取课时属性，请稍候。');
        fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var editor = doc.querySelector('.mathcourse-lesson-editor');
                if (!editor) throw new Error('lesson editor not found');
                side.innerHTML = editor.outerHTML;
                side.classList.remove('is-loading');
                side.setAttribute('aria-busy', 'false');
                setSelectedLesson(lessonId);
                bindLessonPanel(side);
                var stateUrl = new URL(url, window.location.href);
                window.history.replaceState({ lessonId: lessonId }, '', stateUrl.toString());
                side.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            })
            .catch(function () {
                window.location.href = url;
            });
    }

    function refreshFromResponse(html, lessonId) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var newList = doc.querySelector('.mathcourse-topic-list');
        var currentList = wrap.querySelector('.mathcourse-topic-list');
        if (newList && currentList) currentList.replaceWith(newList);

        var newSide = doc.querySelector('#mathcourse-lesson-side');
        var side = document.getElementById('mathcourse-lesson-side');
        if (newSide && side) side.innerHTML = newSide.innerHTML;

        bindLessonPanel(side);
        bindLessonLinks();
        normalizeHlsInput(document);

        if (lessonId) {
            var link = wrap.querySelector('.mathcourse-lesson-edit[data-lesson-id="' + String(lessonId).replace(/"/g, '') + '"]');
            if (link) openLessonEditor(link);
        }
    }

    function asyncLessonSubmit(form, action, extra) {
        var side = document.getElementById('mathcourse-lesson-side');
        var data = new FormData(form);
        data.set('mathcourse_action', action);
        if (extra) Object.keys(extra).forEach(function (key) { data.set(key, extra[key]); });
        if (side) {
            side.classList.add('is-loading');
            side.innerHTML = placeholder(action === 'save_lesson' ? '正在保存课时' : '正在创建课时', '保存完成后会继续留在当前课程。');
        }
        return fetch(window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
        }).then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.text();
        });
    }

    function bindLessonLinks() {
        if (!wrap) return;
        wrap.querySelectorAll('.mathcourse-lesson-edit').forEach(function (link) {
            if (link.dataset.mcBound === '1') return;
            link.dataset.mcBound = '1';
            link.addEventListener('click', function (event) {
                event.preventDefault();
                openLessonEditor(link);
            });
        });
    }

    function bindContinuousLessonForms() {
        if (!wrap) return;
        var form = document.getElementById('mathcourse-course-form');
        if (!form || form.dataset.mcContinuous === '1') return;
        form.dataset.mcContinuous = '1';

        form.addEventListener('submit', function (event) {
            var submitter = event.submitter;
            if (!submitter) return;
            var action = submitter.value || '';
            var isAdd = action.indexOf('add_lesson_') === 0;
            var isSave = action === 'save_lesson';
            if (!isAdd && !isSave) return;

            event.preventDefault();
            var oldText = submitter.textContent;
            submitter.disabled = true;
            submitter.textContent = isSave ? '保存中…' : '添加中…';

            var extra = {};
            var lessonId = '';
            if (isSave) {
                var editing = form.querySelector('[name="editing_lesson_id"]');
                lessonId = editing ? editing.value : '';
            }

            asyncLessonSubmit(form, action, extra)
                .then(function (html) {
                    refreshFromResponse(html, lessonId);
                    if (isAdd) {
                        var topicId = action.substring('add_lesson_'.length);
                        var titleInput = form.querySelector('[name="lesson_title_' + topicId + '"]');
                        var title = titleInput ? titleInput.value.trim() : '';
                        var found = null;
                        if (title) {
                            wrap.querySelectorAll('.mathcourse-lesson-row').forEach(function (row) {
                                var name = row.querySelector('.mathcourse-lesson-main strong');
                                if (!found && name && name.textContent.trim() === title) found = row;
                            });
                        }
                        if (found) {
                            var link = found.querySelector('.mathcourse-lesson-edit');
                            if (link) openLessonEditor(link);
                        }
                        if (titleInput) titleInput.value = '';
                    }
                })
                .catch(function () {
                    /* 连续编辑失败时回退到浏览器原生提交，确保原功能仍可用。 */
                    form.dataset.mcContinuous = '0';
                    submitter.disabled = false;
                    submitter.textContent = oldText;
                    form.submit();
                    return;
                })
                .finally(function () {
                    submitter.disabled = false;
                    submitter.textContent = oldText;
                });
        });
    }

    if (wrap && page === 'mathcourse-course-edit') {
        bindLessonLinks();
        bindContinuousLessonForms();
        var initialEditor = wrap.querySelector('.mathcourse-lesson-editor');
        if (initialEditor) bindLessonPanel(wrap.querySelector('#mathcourse-lesson-side'));
        normalizeHlsInput(document);
    }

    if (wrap && page === 'mathcourse-batch') wrap.classList.add('mathcourse-batch-page');

    var coverInput = document.getElementById('course_cover');
    if (coverInput && window.wp && wp.media) {
        var coverRow = coverInput.parentNode;
        var tools = document.createElement('div');
        tools.className = 'mathcourse-cover-tools';
        var chooseButton = document.createElement('button');
        chooseButton.type = 'button'; chooseButton.className = 'button mathcourse-cover-choose'; chooseButton.textContent = '从媒体库选择';
        var clearButton = document.createElement('button');
        clearButton.type = 'button'; clearButton.className = 'button mathcourse-cover-clear'; clearButton.textContent = '清除封面';
        var preview = document.createElement('div');
        preview.className = 'mathcourse-cover-preview'; preview.setAttribute('aria-live', 'polite');
        var updatePreview = function () {
            var url = (coverInput.value || '').trim(); preview.innerHTML = '';
            if (!url) { preview.classList.remove('has-image'); return; }
            var img = document.createElement('img'); img.alt = '课程封面预览'; img.loading = 'lazy'; img.src = url;
            img.addEventListener('error', function () { preview.classList.remove('has-image'); });
            img.addEventListener('load', function () { preview.classList.add('has-image'); }); preview.appendChild(img);
        };
        var frame = null;
        chooseButton.addEventListener('click', function (event) {
            event.preventDefault(); if (frame) { frame.open(); return; }
            frame = wp.media({ title: '选择课程封面', button: { text: '使用此封面' }, library: { type: 'image' }, multiple: false });
            frame.on('select', function () { var attachment = frame.state().get('selection').first().toJSON(); if (!attachment || !attachment.url) return; coverInput.value = attachment.url; coverInput.dispatchEvent(new Event('change', { bubbles: true })); coverInput.focus(); });
            frame.open();
        });
        clearButton.addEventListener('click', function (event) { event.preventDefault(); coverInput.value = ''; coverInput.dispatchEvent(new Event('change', { bubbles: true })); coverInput.focus(); });
        coverInput.addEventListener('input', updatePreview); coverInput.addEventListener('change', updatePreview);
        tools.appendChild(chooseButton); tools.appendChild(clearButton); coverRow.appendChild(tools); coverRow.appendChild(preview); updatePreview();
    }

    var codeBox = document.querySelector('.mathcourse-code-output');
    if (codeBox) {
        var actions = document.createElement('div'); actions.className = 'mathcourse-code-actions';
        var copyButton = document.createElement('button'); copyButton.type = 'button'; copyButton.className = 'button button-primary'; copyButton.textContent = '复制全部激活码';
        copyButton.addEventListener('click', function () { var text = codeBox.value || ''; var done = function () { copyButton.textContent = '已复制'; window.setTimeout(function () { copyButton.textContent = '复制全部激活码'; }, 1800); }; if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done).catch(function () { codeBox.focus(); codeBox.select(); document.execCommand('copy'); done(); }); else { codeBox.focus(); codeBox.select(); document.execCommand('copy'); done(); } });
        var downloadButton = document.createElement('button'); downloadButton.type = 'button'; downloadButton.className = 'button'; downloadButton.textContent = '保存为 TXT';
        downloadButton.addEventListener('click', function () { var blob = new Blob([codeBox.value || ''], {type: 'text/plain;charset=utf-8'}); var url = URL.createObjectURL(blob); var link = document.createElement('a'); link.href = url; link.download = '课程激活码.txt'; document.body.appendChild(link); link.click(); link.remove(); window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000); });
        actions.appendChild(copyButton); actions.appendChild(downloadButton); codeBox.parentNode.insertBefore(actions, codeBox.nextSibling);
    }
});
