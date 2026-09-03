document.addEventListener('DOMContentLoaded', function () {
    if (!window.Artplayer) return;

    window.Artplayer.CONTEXTMENU = false;

    // 护眼反色：先轻微压低亮度，再反色并旋转色相，避免彩色内容出现传统反色的刺眼互补色。
    var INVERT_FILTER = 'brightness(0.9) invert(1) hue-rotate(180deg)';

    function installInvertStyle() {
        if (document.getElementById('mathcourse-invert-style')) return;
        var s = document.createElement('style');
        s.id = 'mathcourse-invert-style';
        s.textContent = '.mathcourse-artplayer.mathcourse-inverted video{filter:' + INVERT_FILTER + '!important;}';
        document.head.appendChild(s);
    }

    function applyInvert(container, on) {
        var video = container.querySelector('video');
        container.classList.toggle('mathcourse-inverted', !!on);
        if (video) {
            if (on) video.style.setProperty('filter', INVERT_FILTER, 'important');
            else video.style.removeProperty('filter');
        }
    }

    // 微信/部分安卓浏览器可能暂时拿不到首选 CDN 的 hls.js。
    // 首选脚本仍由 WordPress 负责加载；仅在 window.Hls 不存在时尝试备用 CDN。
    var hlsLoaderPromise = null;
    function ensureHlsLibrary() {
        if (window.Hls) return Promise.resolve(window.Hls);
        if (hlsLoaderPromise) return hlsLoaderPromise;
        hlsLoaderPromise = new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[data-mathcourse-hls-fallback]');
            if (existing) {
                existing.addEventListener('load', function () { window.Hls ? resolve(window.Hls) : reject(new Error('Hls unavailable')); });
                existing.addEventListener('error', reject);
                return;
            }
            var script = document.createElement('script');
            script.src = 'https://unpkg.com/hls.js@1.7.1/dist/hls.min.js';
            script.async = true;
            script.setAttribute('data-mathcourse-hls-fallback', '1');
            script.onload = function () { window.Hls ? resolve(window.Hls) : reject(new Error('Hls unavailable')); };
            script.onerror = function () { reject(new Error('Hls fallback CDN failed')); };
            document.head.appendChild(script);
        });
        return hlsLoaderPromise;
    }

    function updateProgressUI(progress, lessonId) {
        if (!progress) return;
        document.querySelectorAll('.mc-course-player__progress span').forEach(function (el) { el.textContent = '学习进度 ' + Number(progress.percent || 0) + '%'; });
        document.querySelectorAll('.mc-course-player__progress strong').forEach(function (el) { el.textContent = Number(progress.completed || 0) + ' / ' + Number(progress.total || 0) + ' 课时'; });
        document.querySelectorAll('.mc-course-player__progress i').forEach(function (el) { el.style.width = Number(progress.percent || 0) + '%'; });
        document.querySelectorAll('.mc-course-player__sidebar-head span').forEach(function (el) { el.textContent = Number(progress.completed || 0) + '/' + Number(progress.total || 0); });
        if (lessonId) {
            document.querySelectorAll('.mc-course-player__item').forEach(function (el) {
                if ((el.getAttribute('href') || '').indexOf('lesson_id=' + lessonId) !== -1) {
                    el.classList.add('is-complete');
                    var check = el.querySelector('.mc-course-player__check');
                    if (check) check.textContent = '✓';
                }
            });
        }
    }

    document.querySelectorAll('.mathcourse-artplayer[data-video-url]').forEach(function (container) {
        var lessonId = parseInt(container.dataset.lessonId || '0', 10);
        var courseId = parseInt(container.dataset.courseId || '0', 10);
        var url = container.dataset.videoUrl || '';
        var videoType = (container.dataset.videoType || '').toLowerCase();
        if (!lessonId || !url || (videoType !== 'm3u8' && videoType !== 'mp4')) return;

        installInvertStyle();
        var storageKey = 'mathcourse_lesson_' + lessonId + '_time';
        var completionSent = false;
        var completedAt = 0;
        var restorePending = true;
        var lastSavedSecond = -1;
        var hls = null;
        var invertEnabled = false;

        function getSavedTime() {
            try {
                var raw = localStorage.getItem(storageKey);
                var time = raw === null ? 0 : parseFloat(raw);
                return Number.isFinite(time) && time > 0 ? time : 0;
            } catch (e) { return 0; }
        }
        function clearSavedTime() { try { localStorage.removeItem(storageKey); } catch (e) {} }
        function savePosition(player, force) {
            var time = Number(player.currentTime || 0);
            var duration = Number(player.duration || 0);
            if (!Number.isFinite(time) || time <= 0) return;
            if (duration > 0 && time >= duration - 0.5) return;
            var value = Math.floor(time * 10) / 10;
            if (!force && Math.floor(value) === lastSavedSecond) return;
            lastSavedSecond = Math.floor(value);
            try { localStorage.setItem(storageKey, String(value)); } catch (e) {}
        }
        function restorePosition(player) {
            if (!restorePending) return;
            var saved = getSavedTime();
            var duration = Number(player.duration || 0);
            if (!saved) { restorePending = false; return; }
            if (!Number.isFinite(duration) || duration <= 0) return;
            if (saved >= duration - 5) { clearSavedTime(); restorePending = false; return; }
            try { player.currentTime = Math.min(saved, Math.max(0, duration - 1)); restorePending = false; } catch (e) {}
        }
        function showCompletionPanel() {
            var panel = document.querySelector('.mc-course-player__completion');
            if (!panel) return;
            panel.hidden = false;
            panel.classList.add('is-visible');
        }
        function submitCompletion(player) {
            if (completionSent || completedAt || !window.mathcoursePlayer || !mathcoursePlayer.ajax_url || !mathcoursePlayer.nonce) return;
            completionSent = true;
            var form = new FormData();
            form.append('action', 'mathcourse_complete_lesson');
            form.append('nonce', mathcoursePlayer.nonce);
            form.append('lesson_id', String(lessonId));
            fetch(mathcoursePlayer.ajax_url, { method: 'POST', credentials: 'same-origin', body: form, keepalive: true })
                .then(function (response) { if (!response.ok) throw new Error('HTTP ' + response.status); return response.json(); })
                .then(function (response) {
                    if (!response || !response.success) throw new Error('progress rejected');
                    completedAt = Date.now();
                    clearSavedTime();
                    var data = response.data || {};
                    var cid = Number(data.course_id || courseId || 0);
                    updateProgressUI(data.progress || null, lessonId);
                    showCompletionPanel();
                    document.dispatchEvent(new CustomEvent('mathcourse_lesson_complete', { detail: { lessonId: lessonId, courseId: cid, progress: data.progress || null } }));
                    document.dispatchEvent(new CustomEvent('mathcourse_progress_updated', { detail: { lessonId: lessonId, courseId: cid, progress: data.progress || null } }));
                })
                .catch(function (error) { completionSent = false; console.warn('MathCourse: unable to save lesson completion.', error); });
        }
        function maybeCompleteAtEnd(player) {
            if (completionSent || completedAt) return;
            var time = Number(player.currentTime || 0);
            var duration = Number(player.duration || 0);
            if (Number.isFinite(time) && Number.isFinite(duration) && duration > 0 && time >= duration - Math.max(1, Math.min(5, duration * 0.01))) submitCompletion(player);
        }

        var options = {
            container: container,
            url: url,
            id: 'mathcourse-lesson-' + lessonId,
            type: videoType,
            lang: 'zh-cn',
            theme: '#1677ff',
            volume: 0.7,
            muted: false,
            autoplay: false,
            autoPlayback: false,
            playsInline: true,
            fullscreen: true,
            fullscreenWeb: true,
            setting: true,
            playbackRate: true,
            flip: false,
            aspectRatio: false,
            screenshot: false,
            pip: false,
            fastForward: true,
            autoOrientation: true,
            mutex: true,
            moreVideoAttr: { playsInline: true, 'webkit-playsinline': true, preload: 'auto' }
        };

        if (videoType === 'm3u8') {
            options.customType = {
                m3u8: function (video, sourceUrl) {
                    // iPhone/iPad Safari 优先使用系统原生 HLS。
                    if (video.canPlayType && video.canPlayType('application/vnd.apple.mpegurl')) {
                        video.src = sourceUrl;
                        video.load();
                        return;
                    }
                    function attachHls(HlsClass) {
                        if (!HlsClass || !HlsClass.isSupported()) return false;
                        if (hls) hls.destroy();
                        hls = new HlsClass({ enableWorker: false, lowLatencyMode: false, backBufferLength: 90, maxBufferLength: 30, capLevelToPlayerSize: true, startLevel: -1, debug: false });
                        hls.on(HlsClass.Events.ERROR, function (event, data) {
                            if (!data || !data.fatal) return;
                            if (data.type === HlsClass.ErrorTypes.NETWORK_ERROR) hls.startLoad();
                            else if (data.type === HlsClass.ErrorTypes.MEDIA_ERROR) hls.recoverMediaError();
                            else { hls.destroy(); hls = null; }
                        });
                        hls.loadSource(sourceUrl);
                        hls.attachMedia(video);
                        return true;
                    }
                    if (window.Hls && attachHls(window.Hls)) return;
                    ensureHlsLibrary().then(function (HlsClass) {
                        if (!attachHls(HlsClass)) console.warn('MathCourse: current browser does not support HLS playback.');
                    }).catch(function (error) { console.warn('MathCourse: hls.js failed to load.', error); });
                }
            };
        }

        var art = new Artplayer(options);
        window.mathcourseArtPlayers = window.mathcourseArtPlayers || {};
        window.mathcourseArtPlayers[lessonId] = art;

        art.on('ready', function () {
            restorePosition(art);
            art.controls.add({ name: 'mathcourse-invert', position: 'right', html: '☯ 反色', tooltip: '反色播放', click: function () { invertEnabled = !invertEnabled; applyInvert(container, invertEnabled); } });
            applyInvert(container, invertEnabled);
        });
        art.on('fullscreen', function () { if (invertEnabled) { setTimeout(function () { applyInvert(container, true); }, 0); setTimeout(function () { applyInvert(container, true); }, 120); } });
        art.on('fullscreenWeb', function () { if (invertEnabled) { setTimeout(function () { applyInvert(container, true); }, 0); setTimeout(function () { applyInvert(container, true); }, 120); } });
        art.on('resize', function () { if (invertEnabled) applyInvert(container, true); });
        art.on('video:loadedmetadata', function () { restorePosition(art); if (invertEnabled) applyInvert(container, true); });
        art.on('video:durationchange', function () { restorePosition(art); });
        art.on('video:timeupdate', function () { savePosition(art, false); maybeCompleteAtEnd(art); });
        art.on('video:pause', function () { savePosition(art, true); });
        art.on('video:seeking', function () { savePosition(art, true); });
        art.on('video:seeked', function () { maybeCompleteAtEnd(art); });
        art.on('video:ended', function () { submitCompletion(art); });
        window.addEventListener('pagehide', function () { savePosition(art, true); });
        window.addEventListener('beforeunload', function () { savePosition(art, true); });
    });
});
