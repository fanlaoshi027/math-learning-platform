document.addEventListener('DOMContentLoaded', function () {
    if (!window.Artplayer) return;

    window.Artplayer.CONTEXTMENU = false;

    function installInvertStyle() {
        if (document.getElementById('mathcourse-invert-style')) return;
        var style = document.createElement('style');
        style.id = 'mathcourse-invert-style';
        style.textContent = '.mathcourse-artplayer.mathcourse-inverted video{filter:invert(1)!important;}';
        document.head.appendChild(style);
    }

    function applyInvert(art, enabled) {
        var container = art && art.container ? art.container : null;
        var video = art && art.video ? art.video : (container ? container.querySelector('video') : null);
        if (container) container.classList.toggle('mathcourse-inverted', !!enabled);
        if (video) {
            if (enabled) video.style.setProperty('filter', 'invert(1)', 'important');
            else video.style.removeProperty('filter');
        }
    }

    function updateProgressUI(progress, lessonId) {
        if (!progress) return;
        document.querySelectorAll('.mc-course-player__progress span').forEach(function (el) {
            el.textContent = '学习进度 ' + Number(progress.percent || 0) + '%';
        });
        document.querySelectorAll('.mc-course-player__progress strong').forEach(function (el) {
            el.textContent = Number(progress.completed || 0) + ' / ' + Number(progress.total || 0) + ' 课时';
        });
        document.querySelectorAll('.mc-course-player__progress i').forEach(function (el) {
            el.style.width = Number(progress.percent || 0) + '%';
        });
        document.querySelectorAll('.mc-course-player__sidebar-head span').forEach(function (el) {
            el.textContent = Number(progress.completed || 0) + '/' + Number(progress.total || 0);
        });
        if (lessonId) {
            document.querySelectorAll('.mc-course-player__item').forEach(function (item) {
                var link = item.getAttribute('href') || '';
                if (link.indexOf('lesson_id=' + lessonId) !== -1) {
                    item.classList.add('is-complete');
                    var check = item.querySelector('.mc-course-player__check');
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

        function clearSavedTime() {
            try { localStorage.removeItem(storageKey); } catch (e) {}
        }

        function savePosition(art, force) {
            var time = Number(art.currentTime || 0);
            var duration = Number(art.duration || 0);
            if (!Number.isFinite(time) || time <= 0) return;
            if (duration > 0 && time >= duration - 0.5) return;
            var value = Math.floor(time * 10) / 10;
            if (!force && Math.floor(value) === lastSavedSecond) return;
            lastSavedSecond = Math.floor(value);
            try { localStorage.setItem(storageKey, String(value)); } catch (e) {}
        }

        function restorePosition(art) {
            if (!restorePending) return;
            var saved = getSavedTime();
            var duration = Number(art.duration || 0);
            if (!saved) { restorePending = false; return; }
            if (!Number.isFinite(duration) || duration <= 0) return;
            if (saved >= duration - 5) {
                clearSavedTime();
                restorePending = false;
                return;
            }
            try {
                art.currentTime = Math.min(saved, Math.max(0, duration - 1));
                restorePending = false;
            } catch (e) {}
        }

        function showCompletionPanel() {
            var panel = document.querySelector('.mc-course-player__completion');
            if (!panel) return;
            panel.hidden = false;
            panel.classList.add('is-visible');
        }

        function submitCompletion(art) {
            if (completionSent || completedAt || !window.mathcoursePlayer || !mathcoursePlayer.ajax_url || !mathcoursePlayer.nonce) return;
            completionSent = true;
            var formData = new FormData();
            formData.append('action', 'mathcourse_complete_lesson');
            formData.append('nonce', mathcoursePlayer.nonce);
            formData.append('lesson_id', String(lessonId));
            fetch(mathcoursePlayer.ajax_url, { method: 'POST', credentials: 'same-origin', body: formData, keepalive: true })
                .then(function (response) { if (!response.ok) throw new Error('HTTP ' + response.status); return response.json(); })
                .then(function (result) {
                    if (!result || !result.success) throw new Error('progress rejected');
                    completedAt = Date.now();
                    clearSavedTime();
                    var data = result.data || {};
                    var serverCourseId = Number(data.course_id || courseId || 0);
                    updateProgressUI(data.progress || null, lessonId);
                    showCompletionPanel();
                    document.dispatchEvent(new CustomEvent('mathcourse_lesson_complete', { detail: { lessonId: lessonId, courseId: serverCourseId, progress: data.progress || null } }));
                    document.dispatchEvent(new CustomEvent('mathcourse_progress_updated', { detail: { lessonId: lessonId, courseId: serverCourseId, progress: data.progress || null } }));
                })
                .catch(function (error) { completionSent = false; console.warn('MathCourse: unable to save lesson completion.', error); });
        }

        function maybeCompleteAtEnd(art) {
            if (completionSent || completedAt) return;
            var time = Number(art.currentTime || 0);
            var duration = Number(art.duration || 0);
            if (!Number.isFinite(time) || !Number.isFinite(duration) || duration <= 0) return;
            if (time >= duration - Math.max(1, Math.min(5, duration * 0.01))) submitCompletion(art);
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
            fullscreen: true,
            fullscreenWeb: true,
            // 保留官方设置组件作为基础能力，但不显示官方齿轮控制按钮。
            setting: true,
            playbackRate: true,
            flip: false,
            fastForward: true,
            autoOrientation: true,
            mutex: true,
            moreVideoAttr: {
                playsInline: true,
                'webkit-playsinline': true,
                preload: 'auto'
            }
        };

        if (videoType === 'm3u8') {
            options.customType = {
                m3u8: function (video, sourceUrl) {
                    if (video.canPlayType && video.canPlayType('application/vnd.apple.mpegurl')) {
                        video.src = sourceUrl;
                        video.load();
                        return;
                    }
                    if (window.Hls && Hls.isSupported()) {
                        if (hls) hls.destroy();
                        hls = new Hls({
                            enableWorker: false,
                            lowLatencyMode: false,
                            backBufferLength: 90,
                            maxBufferLength: 30,
                            capLevelToPlayerSize: true,
                            startLevel: -1,
                            debug: false
                        });
                        hls.on(Hls.Events.ERROR, function (event, data) {
                            if (!data || !data.fatal) return;
                            console.warn('MathCourse HLS fatal error:', data.type, data.details);
                            if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                                hls.startLoad();
                            } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                                hls.recoverMediaError();
                            } else {
                                hls.destroy();
                                hls = null;
                            }
                        });
                        hls.loadSource(sourceUrl);
                        hls.attachMedia(video);
                        return;
                    }
                    if (video.canPlayType && video.canPlayType('application/x-mpegURL')) {
                        video.src = sourceUrl;
                        video.load();
                    } else {
                        console.warn('MathCourse: current browser does not support HLS playback.');
                    }
                }
            };
        }

        var art = new Artplayer(options);
        window.mathcourseArtPlayers = window.mathcourseArtPlayers || {};
        window.mathcourseArtPlayers[lessonId] = art;

        art.on('ready', function () {
            restorePosition(art);

            // 不删除 setting 组件，否则官方设置面板也会被一起销毁，导致倍速无法打开。
            // 仅隐藏官方齿轮控制按钮，倍速改用官方 selector 控制器实现。
            var settingControl = container.querySelector('.art-control-setting');
            if (settingControl) settingControl.style.display = 'none';

            art.controls.add({
                name: 'mathcourse-invert',
                position: 'right',
                html: '☯',
                tooltip: '反色播放',
                click: function () {
                    invertEnabled = !invertEnabled;
                    applyInvert(art, invertEnabled);
                }
            });

            art.controls.add({
                name: 'mathcourse-speed',
                position: 'right',
                html: '倍速',
                tooltip: '播放速度',
                selector: [
                    { html: '0.5x', value: 0.5 },
                    { html: '0.75x', value: 0.75 },
                    { html: '正常', value: 1 },
                    { html: '1.25x', value: 1.25 },
                    { html: '1.5x', value: 1.5 },
                    { html: '2x', value: 2 }
                ],
                onSelect: function (item) {
                    var rate = Number(item.value);
                    if (Number.isFinite(rate) && rate > 0) art.playbackRate = rate;
                }
            });

            applyInvert(art, invertEnabled);
        });

        // 窗口全屏 / 网页全屏都会改变播放器容器或 DOM 挂载位置，统一重新应用反色。
        art.on('fullscreen', function () {
            if (!invertEnabled) return;
            setTimeout(function () { applyInvert(art, true); }, 0);
            setTimeout(function () { applyInvert(art, true); }, 120);
        });
        art.on('fullscreenWeb', function () {
            if (!invertEnabled) return;
            setTimeout(function () { applyInvert(art, true); }, 0);
            setTimeout(function () { applyInvert(art, true); }, 120);
        });
        art.on('resize', function () {
            if (!invertEnabled) return;
            applyInvert(art, true);
        });
        art.on('video:loadedmetadata', function () {
            restorePosition(art);
            if (invertEnabled) applyInvert(art, true);
        });
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
