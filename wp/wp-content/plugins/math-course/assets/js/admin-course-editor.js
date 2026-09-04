document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('lesson_hls_edit');
    if (input) {
        // HLS 支持相对路径，例如 /wp-content/uploads/.../index.m3u8。
        // 不要改成 type=url，否则浏览器会阻止相对地址提交。
        input.type = 'text';
        input.setAttribute('inputmode', 'url');
        input.removeAttribute('pattern');
        input.removeAttribute('required');
    }

    /* 给旧版 PHP 页面补充语义 class，避免改动后台数据/业务逻辑。 */
    var params = new URLSearchParams(window.location.search);
    var page = params.get('page');
    var wrap = document.querySelector('.wrap');
    if (wrap && page === 'mathcourse-course-edit') {
        wrap.classList.add('mathcourse-editor');
    }
    if (wrap && page === 'mathcourse-batch') {
        wrap.classList.add('mathcourse-batch-page');
    }

    /* 课程封面：复用 WordPress 媒体库，仍然只把图片 URL 保存到原有字段。 */
    var coverInput = document.getElementById('course_cover');
    if (coverInput && window.wp && wp.media) {
        var coverRow = coverInput.parentNode;
        var tools = document.createElement('div');
        tools.className = 'mathcourse-cover-tools';

        var chooseButton = document.createElement('button');
        chooseButton.type = 'button';
        chooseButton.className = 'button mathcourse-cover-choose';
        chooseButton.textContent = '从媒体库选择';

        var clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.className = 'button mathcourse-cover-clear';
        clearButton.textContent = '清除封面';

        var preview = document.createElement('div');
        preview.className = 'mathcourse-cover-preview';
        preview.setAttribute('aria-live', 'polite');

        var updatePreview = function () {
            var url = (coverInput.value || '').trim();
            preview.innerHTML = '';
            if (!url) {
                preview.classList.remove('has-image');
                return;
            }
            var img = document.createElement('img');
            img.alt = '课程封面预览';
            img.loading = 'lazy';
            img.src = url;
            img.addEventListener('error', function () {
                preview.classList.remove('has-image');
            });
            img.addEventListener('load', function () {
                preview.classList.add('has-image');
            });
            preview.appendChild(img);
        };

        var frame = null;
        chooseButton.addEventListener('click', function (event) {
            event.preventDefault();
            if (frame) {
                frame.open();
                return;
            }
            frame = wp.media({
                title: '选择课程封面',
                button: { text: '使用此封面' },
                library: { type: 'image' },
                multiple: false
            });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                if (!attachment || !attachment.url) return;
                coverInput.value = attachment.url;
                coverInput.dispatchEvent(new Event('change', { bubbles: true }));
                coverInput.focus();
            });
            frame.open();
        });

        clearButton.addEventListener('click', function (event) {
            event.preventDefault();
            coverInput.value = '';
            coverInput.dispatchEvent(new Event('change', { bubbles: true }));
            coverInput.focus();
        });

        coverInput.addEventListener('input', updatePreview);
        coverInput.addEventListener('change', updatePreview);
        tools.appendChild(chooseButton);
        tools.appendChild(clearButton);
        coverRow.appendChild(tools);
        coverRow.appendChild(preview);
        updatePreview();
    }

    /* 激活码生成后：一键复制全部，并支持下载为 TXT。明文只存在当前页面。 */
    var codeBox = document.querySelector('.mathcourse-code-output');
    if (codeBox) {
        var actions = document.createElement('div');
        actions.className = 'mathcourse-code-actions';
        var copyButton = document.createElement('button');
        copyButton.type = 'button';
        copyButton.className = 'button button-primary';
        copyButton.textContent = '复制全部激活码';
        copyButton.addEventListener('click', function () {
            var text = codeBox.value || '';
            var done = function () {
                copyButton.textContent = '已复制';
                window.setTimeout(function () { copyButton.textContent = '复制全部激活码'; }, 1800);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done).catch(function () {
                    codeBox.focus(); codeBox.select(); document.execCommand('copy'); done();
                });
            } else {
                codeBox.focus(); codeBox.select(); document.execCommand('copy'); done();
            }
        });
        var downloadButton = document.createElement('button');
        downloadButton.type = 'button';
        downloadButton.className = 'button';
        downloadButton.textContent = '保存为 TXT';
        downloadButton.addEventListener('click', function () {
            var blob = new Blob([codeBox.value || ''], {type: 'text/plain;charset=utf-8'});
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = '课程激活码.txt';
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        });
        actions.appendChild(copyButton);
        actions.appendChild(downloadButton);
        codeBox.parentNode.insertBefore(actions, codeBox.nextSibling);
    }
});
