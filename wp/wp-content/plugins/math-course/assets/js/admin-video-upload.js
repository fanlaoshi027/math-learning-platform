document.addEventListener('DOMContentLoaded', function () {
    var wrap = document.querySelector('.wrap.mathcourse-editor');
    if (!wrap || typeof mathcourseVideoAdmin === 'undefined') return;

    function esc(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function formatDuration(seconds) {
        seconds = Number(seconds || 0);
        if (!Number.isFinite(seconds) || seconds <= 0) return '';
        var m = Math.floor(seconds / 60), s = Math.round(seconds % 60);
        if (s === 60) { m++; s = 0; }
        return m + ':' + String(s).padStart(2, '0');
    }

    function statusText(status) {
        return ({none:'未上传',pending:'等待转换',processing:'正在转换',ready:'转换完成',failed:'转换失败'})[status] || status || '未上传';
    }

    function statusClass(status) {
        return ({none:'is-none',pending:'is-pending',processing:'is-processing',ready:'is-ready',failed:'is-failed'})[status] || 'is-none';
    }

    function requestStatus(lessonId) {
        var data = new URLSearchParams();
        data.set('action', 'mathcourse_video_status');
        data.set('nonce', mathcourseVideoAdmin.nonce);
        data.set('lesson_id', lessonId);
        return fetch(mathcourseVideoAdmin.ajax_url, {
            method:'POST',
            credentials:'same-origin',
            headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
            body:data.toString()
        }).then(function(r){ return r.json(); }).then(function(result){
            return result && result.success ? (result.data || {}) : null;
        }).catch(function(){ return null; });
    }

    function refreshLessonRows() {
        var rows = wrap.querySelectorAll('.mathcourse-lesson-row[data-lesson-id]');
        if (!rows.length) return;
        rows.forEach(function(row){
            var lessonId = row.getAttribute('data-lesson-id');
            if (!lessonId || row.dataset.videoStatusLoading === '1') return;
            row.dataset.videoStatusLoading = '1';
            requestStatus(lessonId).then(function(data){
                row.dataset.videoStatusLoading = '0';
                if (!data) return;
                var badge = row.querySelector('.mathcourse-video-status-badge');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'mathcourse-video-status-badge';
                    var main = row.querySelector('.mathcourse-lesson-main');
                    if (main) main.appendChild(badge);
                }
                badge.textContent = statusText(data.status);
                badge.className = 'mathcourse-video-status-badge ' + statusClass(data.status);
                if (data.status === 'failed' && data.error) badge.title = data.error;
                else if (data.info) {
                    var info = data.info, parts = [];
                    if (info.width && info.height) parts.push(info.width + ' × ' + info.height);
                    if (info.fps) parts.push(info.fps + ' fps');
                    if (info.duration) parts.push(formatDuration(info.duration));
                    badge.title = parts.join(' · ');
                } else badge.removeAttribute('title');
            });
        });
    }

    function install(root) {
        if (!root || root.dataset.videoUploadBound === '1') return;
        var lessonId = root.getAttribute('data-lesson-editor-id');
        var form = document.getElementById('mathcourse-course-form');
        if (!lessonId || !form) return;
        root.dataset.videoUploadBound = '1';

        var courseId = new URLSearchParams(window.location.search).get('course_id') || '';
        var hlsInput = root.querySelector('#lesson_hls_edit');
        if (!hlsInput) return;

        var box = document.createElement('div');
        box.className = 'mathcourse-video-upload-box';
        box.innerHTML = '<div class="mathcourse-video-upload-head"><div><strong>MP4 视频</strong><span>上传后服务器自动无损切成 HLS</span></div><span class="mathcourse-video-status is-none">未上传</span></div>' +
            '<div class="mathcourse-video-upload-row"><input class="mathcourse-video-file" type="file" accept="video/mp4,.mp4"><button type="button" class="button button-primary mathcourse-video-upload-btn">上传并转换</button></div>' +
            '<div class="mathcourse-video-progress" hidden><i></i></div>' +
            '<div class="mathcourse-video-upload-meta"></div><div class="mathcourse-video-upload-message" role="status"></div>';
        hlsInput.parentNode.insertBefore(box, hlsInput);

        var fileInput = box.querySelector('.mathcourse-video-file');
        var button = box.querySelector('.mathcourse-video-upload-btn');
        var status = box.querySelector('.mathcourse-video-status');
        var progress = box.querySelector('.mathcourse-video-progress');
        var progressBar = progress.querySelector('i');
        var meta = box.querySelector('.mathcourse-video-upload-meta');
        var message = box.querySelector('.mathcourse-video-upload-message');
        var timer = null;

        function setStatus(value) {
            status.textContent = statusText(value);
            status.className = 'mathcourse-video-status ' + statusClass(value);
            if (value === 'processing' || value === 'pending') {
                progress.hidden = false;
                progress.classList.add('is-indeterminate');
            } else if (value === 'ready' || value === 'failed') {
                progress.classList.remove('is-indeterminate');
                progress.hidden = true;
            }
        }
        function showInfo(info) {
            if (!info) return;
            var parts = [];
            if (info.width && info.height) parts.push(info.width + ' × ' + info.height);
            if (info.fps) parts.push(info.fps + ' fps');
            if (info.video_codec) parts.push(String(info.video_codec).toUpperCase());
            if (info.audio_codec) parts.push(String(info.audio_codec).toUpperCase());
            if (info.duration) parts.push(formatDuration(info.duration));
            meta.textContent = parts.join('  ·  ');
        }
        function poll() {
            requestStatus(lessonId).then(function(d){
                if (!d) return;
                setStatus(d.status);
                showInfo(d.info);
                if (d.status === 'ready' && d.hls_url) {
                    hlsInput.value = d.hls_url;
                    message.textContent = 'HLS 已生成并自动填入播放地址。保存课程即可生效。';
                    button.disabled = false;
                    button.textContent = '重新上传 MP4';
                    fileInput.disabled = false;
                    if (timer) { clearInterval(timer); timer = null; }
                } else if (d.status === 'failed') {
                    message.textContent = d.error || '转换失败，请检查服务器日志。';
                    button.disabled = false;
                    fileInput.disabled = false;
                    button.textContent = '重新上传 MP4';
                    refreshLessonRows();
                    if (timer) { clearInterval(timer); timer = null; }
                } else if (d.status === 'processing') {
                    message.textContent = '服务器正在切片，视频不会重新编码，请耐心等待。';
                } else if (d.status === 'pending') {
                    message.textContent = 'MP4 已保存，正在等待服务器开始处理。';
                }
            });
        }

        button.addEventListener('click', function () {
            var file = fileInput.files && fileInput.files[0];
            if (!file) { message.textContent = '请先选择 MP4 文件。'; return; }
            if (!/\.mp4$/i.test(file.name)) { message.textContent = '这里只接受 MP4 文件。'; return; }
            var data = new FormData();
            data.append('action', 'mathcourse_upload_video');
            data.append('nonce', mathcourseVideoAdmin.nonce);
            data.append('lesson_id', lessonId);
            data.append('course_id', courseId);
            data.append('video', file);
            button.disabled = true;
            fileInput.disabled = true;
            button.textContent = '上传中… 0%';
            message.textContent = '正在上传，请不要关闭页面。';
            progress.hidden = false;
            progress.classList.remove('is-indeterminate');
            progressBar.style.width = '0%';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', mathcourseVideoAdmin.ajax_url, true);
            xhr.withCredentials = true;
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.addEventListener('progress', function (event) {
                if (!event.lengthComputable) return;
                var percent = Math.max(0, Math.min(100, Math.round(event.loaded / event.total * 100)));
                progressBar.style.width = percent + '%';
                button.textContent = '上传中… ' + percent + '%';
            });
            xhr.onload = function () {
                var result = null;
                try { result = JSON.parse(xhr.responseText); } catch (e) {}
                if (!result || !result.success) {
                    var err = result && result.data && result.data.message ? result.data.message : '';
                    if (!err && xhr.status === 413) err = '视频文件太大，服务器拒绝了上传。请提高 Nginx client_max_body_size 与 PHP upload_max_filesize / post_max_size。';
                    if (!err && xhr.status >= 500) err = '服务器处理上传时发生错误（HTTP ' + xhr.status + '），请检查 PHP 错误日志。';
                    if (!err && xhr.status === 0) err = '上传连接被服务器中断，请检查 Nginx / PHP 上传限制。';
                    if (!err && xhr.responseText) err = '服务器返回了无法识别的错误：' + xhr.responseText.slice(0, 180);
                    if (!err) err = '上传失败（HTTP ' + xhr.status + '）。';
                    message.textContent = err;
                    button.disabled = false;
                    fileInput.disabled = false;
                    button.textContent = '上传并转换';
                    progress.hidden = true;
                    return;
                }
                setStatus(result.data.status || 'pending');
                showInfo(result.data.info);
                progress.classList.add('is-indeterminate');
                progressBar.style.width = '100%';
                message.textContent = result.data.message || '已上传，服务器开始转换。';
                button.textContent = '转换中…';
                fileInput.disabled = false;
                refreshLessonRows();
                poll();
                if (timer) clearInterval(timer);
                timer = setInterval(poll, 3000);
            };
            xhr.onerror = function () {
                message.textContent = '上传连接中断，请检查服务器上传大小限制、Nginx 配置或 PHP 错误日志。';
                button.disabled = false;
                fileInput.disabled = false;
                button.textContent = '上传并转换';
                progress.hidden = true;
            };
            xhr.ontimeout = function () {
                message.textContent = '上传超时，请检查服务器上传超时设置。';
                button.disabled = false;
                fileInput.disabled = false;
                button.textContent = '上传并转换';
                progress.hidden = true;
            };
            xhr.timeout = 0;
            xhr.send(data);
        });
        poll();
    }

    function scan() {
        install(wrap.querySelector('.mathcourse-lesson-editor'));
        refreshLessonRows();
    }
    scan();
    new MutationObserver(scan).observe(wrap, {childList:true, subtree:true});
    window.setInterval(refreshLessonRows, 5000);
});
