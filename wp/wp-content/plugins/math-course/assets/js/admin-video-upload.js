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
            '<div class="mathcourse-video-upload-meta"></div><div class="mathcourse-video-upload-message" role="status"></div>';
        hlsInput.parentNode.insertBefore(box, hlsInput);

        var fileInput = box.querySelector('.mathcourse-video-file');
        var button = box.querySelector('.mathcourse-video-upload-btn');
        var status = box.querySelector('.mathcourse-video-status');
        var meta = box.querySelector('.mathcourse-video-upload-meta');
        var message = box.querySelector('.mathcourse-video-upload-message');
        var timer = null;

        function setStatus(value) {
            status.textContent = statusText(value);
            status.className = 'mathcourse-video-status is-' + (value || 'none');
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
            var data = new URLSearchParams();
            data.set('action', 'mathcourse_video_status');
            data.set('nonce', mathcourseVideoAdmin.nonce);
            data.set('lesson_id', lessonId);
            fetch(mathcourseVideoAdmin.ajax_url, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:data.toString()})
                .then(function(r){return r.json();})
                .then(function(result){
                    if (!result.success) return;
                    var d = result.data || {};
                    setStatus(d.status);
                    showInfo(d.info);
                    if (d.status === 'ready' && d.hls_url) {
                        hlsInput.value = d.hls_url;
                        message.textContent = 'HLS 已生成并自动填入播放地址。保存课程即可生效。';
                        if (timer) { clearInterval(timer); timer = null; }
                    } else if (d.status === 'failed') {
                        message.textContent = d.error || '转换失败，请检查服务器日志。';
                        if (timer) { clearInterval(timer); timer = null; }
                    }
                }).catch(function(){});
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
            button.textContent = '上传中…';
            message.textContent = '正在上传，请不要关闭页面。';
            fetch(mathcourseVideoAdmin.ajax_url, {method:'POST', credentials:'same-origin', body:data})
                .then(function(r){return r.json();})
                .then(function(result){
                    if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : '上传失败');
                    setStatus(result.data.status || 'pending');
                    showInfo(result.data.info);
                    message.textContent = result.data.message || '已上传，服务器开始转换。';
                    button.textContent = '转换中…';
                    poll();
                    if (timer) clearInterval(timer);
                    timer = setInterval(poll, 3000);
                })
                .catch(function(error){ message.textContent = error.message || '上传失败。'; })
                .finally(function(){ button.disabled = false; if (button.textContent === '上传中…') button.textContent = '上传并转换'; });
        });
        poll();
    }

    function scan() {
        install(wrap.querySelector('.mathcourse-lesson-editor'));
    }
    scan();
    new MutationObserver(scan).observe(wrap, {childList:true, subtree:true});
});
