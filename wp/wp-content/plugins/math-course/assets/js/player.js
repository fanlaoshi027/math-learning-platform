document.addEventListener('DOMContentLoaded', function () {
    if (!window.Artplayer) return;

    // 保留 ArtPlayer 官方播放器菜单/控制器，只关闭 ArtPlayer 自带右键菜单。
    window.Artplayer.CONTEXTMENU = false;

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

        var storageKey = 'mathcourse_lesson_' + lessonId + '_time';
        var completionSent = false;
        var completedAt = 0;
        var restorePending = true;
        var lastSavedSecond = -1;
        var hls = null;

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

            fetch(mathcoursePlayer.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                keepalive: true
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(function (result) {
                    if (!result || !result.success) throw new Error('progress rejected');
                    completedAt = Date.now();
                    clearSavedTime();
                    var data = result.data || {};
                    var serverCourseId = Number(data.course_id || courseId || 0);
                    updateProgressUI(data.progress || null, lessonId);
                    showCompletionPanel();
                    document.dispatchEvent(new CustomEvent('mathcourse_lesson_complete', {
                        detail: { lessonId: lessonId, courseId: serverCourseId, progress: data.progress || null }
                    }));
                    document.dispatchEvent(new CustomEvent('mathcourse_progress_updated', {
                        detail: { lessonId: lessonId, courseId: serverCourseId, progress: data.progress || null }
                    }));
                })
                .catch(function (error) {
                    completionSent = false;
                    console.warn('MathCourse: unable to save lesson completion.', error);
                });
        }

        function maybeCompleteAtEnd(art) {
            if (completionSent || completedAt) return;
            var time = Number(art.currentTime || 0);
            var duration = Number(art.duration || 0);
            if (!Number.isFinite(time) || !Number.isFinite(duration) || duration <= 0) return;
            // 保留原有业务规则：用户主动拖到视频结尾，也视为完成课时。
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
            setting: true,
            playbackRate: true,
            fastForward: true,
            autoOrientation: true,
            mutex: true,
            moreVideoAttr: {
                playsInline: true,
                'webkit-playsinline': true,
                preload: 'metadata'
            }
        };

        // 只有 HLS 需要 hls.js；MP4 直接使用 ArtPlayer/HTML5 video 原生能力。
        if (videoType === 'm3u8') {
            options.customType = {
                m3u8: function (video, sourceUrl) {
                    if (window.Hls && Hls.isSupported()) {
                        if (hls) hls.destroy();
                        hls = new Hls({ enableWorker: true });
                        hls.loadSource(sourceUrl);
                        hls.attachMedia(video);
                        hls.on(Hls.Events.ERROR, function (event, data) {
                            if (data && data.fatal) console.warn('MathCourse HLS fatal error:', data.type, data.details);
                        });
                    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                        video.src = sourceUrl;
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
        });
        art.on('video:loadedmetadata', function () {
            restorePosition(art);
        });
        art.on('video:durationchange', function () {
            restorePosition(art);
        });
        art.on('video:timeupdate', function () {
            savePosition(art, false);
            maybeCompleteAtEnd(art);
        });
        art.on('video:pause', function () {
            savePosition(art, true);
        });
        art.on('video:seeking', function () {
            savePosition(art, true);
        });
        art.on('video:seeked', function () {
            maybeCompleteAtEnd(art);
        });
        art.on('video:ended', function () {
            submitCompletion(art);
        });

        window.addEventListener('pagehide', function () { savePosition(art, true); });
        window.addEventListener('beforeunload', function () { savePosition(art, true); });
    });
});
