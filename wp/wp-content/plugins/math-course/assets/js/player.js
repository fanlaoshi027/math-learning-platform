document.addEventListener('DOMContentLoaded', function () {
    if (!window.Artplayer) return;
    document.body.classList.add('math-learning-page');

    // Keep web fullscreen inside the player container so the invert state and UI stay together.
    if ('FULLSCREEN_WEB_IN_BODY' in window.Artplayer) {
        window.Artplayer.FULLSCREEN_WEB_IN_BODY = false;
    }

    const players = document.querySelectorAll('.mathcourse-artplayer[data-video-url]');
    const speeds = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];

    function updateProgressUI(progress) {
        if (!progress) return;
        document.querySelectorAll('.mc-course-player__progress span').forEach(el => el.textContent = '学习进度 ' + Number(progress.percent || 0) + '%');
        document.querySelectorAll('.mc-course-player__progress strong').forEach(el => el.textContent = Number(progress.completed || 0) + ' / ' + Number(progress.total || 0) + ' 课时');
        document.querySelectorAll('.mc-course-player__progress i').forEach(el => el.style.width = Number(progress.percent || 0) + '%');
        document.querySelectorAll('.mc-course-player__sidebar-head span').forEach(el => el.textContent = Number(progress.completed || 0) + '/' + Number(progress.total || 0));
    }

    function showCompletionPanel() {
        const panel = document.querySelector('.mc-course-player__completion');
        if (panel) {
            panel.hidden = false;
            panel.classList.add('is-visible');
        }
    }

    players.forEach(function (container) {
        const lessonId = parseInt(container.dataset.lessonId || '0', 10);
        const url = container.dataset.videoUrl || '';
        if (!lessonId || !url) return;

        const storageKey = 'mathcourse_lesson_' + lessonId + '_time';
        let completionSent = false;
        let lastSavedSecond = -1;
        let invertEnabled = false;
        let art = null;

        function savedTime() {
            try {
                const value = parseFloat(localStorage.getItem(storageKey) || '0');
                return Number.isFinite(value) && value > 0 ? value : 0;
            } catch (e) { return 0; }
        }

        function clearSavedTime() {
            try { localStorage.removeItem(storageKey); } catch (e) {}
        }

        function submitCompletion() {
            if (completionSent || !window.mathcoursePlayer || !mathcoursePlayer.ajax_url || !mathcoursePlayer.nonce) return;
            completionSent = true;
            const fd = new FormData();
            fd.append('action', 'mathcourse_complete_lesson');
            fd.append('nonce', mathcoursePlayer.nonce);
            fd.append('lesson_id', String(lessonId));
            fetch(mathcoursePlayer.ajax_url, { method: 'POST', credentials: 'same-origin', body: fd, keepalive: true })
                .then(r => r.json())
                .then(result => {
                    if (!result || !result.success) throw new Error('progress rejected');
                    clearSavedTime();
                    const data = result.data || {};
                    updateProgressUI(data.progress || null);
                    showCompletionPanel();
                })
                .catch(error => {
                    completionSent = false;
                    console.warn('MathCourse: unable to save lesson completion.', error);
                });
        }

        function syncInvertState() {
            const video = art && art.video ? art.video : container.querySelector('video');
            const playerRoot = container.querySelector('.art-video-player');

            // Never invert the player/container/root: controls and icons must stay normal.
            container.classList.remove('math-video-invert', 'is-inverted');
            if (playerRoot) playerRoot.classList.remove('math-video-invert', 'is-inverted');

            // The video frame is the only element that may be inverted.
            if (!video) return;
            video.classList.toggle('math-video-invert', invertEnabled);
            video.classList.toggle('is-inverted', invertEnabled);
            if (invertEnabled) {
                video.style.setProperty('filter', 'invert(1) hue-rotate(180deg)', 'important');
            } else {
                video.style.removeProperty('filter');
            }
        }

        function onFullscreenChange() {
            window.requestAnimationFrame(function () {
                syncInvertState();
                window.requestAnimationFrame(syncInvertState);
            });
        }

        function bindFullscreenEvents(video) {
            document.addEventListener('fullscreenchange', onFullscreenChange);
            document.addEventListener('webkitfullscreenchange', onFullscreenChange);
            if (video) {
                video.addEventListener('webkitbeginfullscreen', onFullscreenChange);
                video.addEventListener('webkitendfullscreen', onFullscreenChange);
            }
        }

        function unbindFullscreenEvents(video) {
            document.removeEventListener('fullscreenchange', onFullscreenChange);
            document.removeEventListener('webkitfullscreenchange', onFullscreenChange);
            if (video) {
                video.removeEventListener('webkitbeginfullscreen', onFullscreenChange);
                video.removeEventListener('webkitendfullscreen', onFullscreenChange);
            }
        }

        function createPlayer() {
            const castSupported = typeof HTMLVideoElement !== 'undefined' && 'remote' in HTMLVideoElement.prototype;
            const settings = [
                {
                    name: 'math-speed',
                    html: '倍速',
                    tooltip: '1×',
                    selector: speeds.map(function (speed) {
                        return { html: speed + '×', value: speed, default: speed === 1 };
                    }),
                    onSelect: function (item) {
                        const speed = Number(item.value);
                        if (Number.isFinite(speed) && art) art.playbackRate = speed;
                        return item.html;
                    },
                },
            ];

            if (castSupported) {
                settings.push({
                    name: 'math-cast',
                    html: '投屏',
                    tooltip: '投屏',
                    onSelect: function () {
                        if (!art || !art.video || !art.video.remote || typeof art.video.remote.prompt !== 'function') return '不支持投屏';
                        try { art.video.remote.prompt().catch(function () {}); } catch (e) {}
                        return '投屏';
                    },
                });
            }

            settings.push({
                name: 'math-invert',
                html: '反色',
                tooltip: '关闭',
                switch: false,
                onSwitch: function (item) {
                    invertEnabled = !item.switch;
                    item.tooltip = invertEnabled ? '开启' : '关闭';
                    syncInvertState();
                    return invertEnabled;
                },
            });

            art = new Artplayer({
                container: container,
                url: url,
                type: 'm3u8',
                lang: 'zh-cn',
                autoplay: false,
                muted: false,
                pip: false,
                fullscreen: true,
                fullscreenWeb: true,
                playsInline: true,
                autoOrientation: true,
                setting: true,
                playbackRate: false,
                flip: false,
                aspectRatio: false,
                subtitleOffset: false,
                contextmenu: [],
                settings: settings,
                moreVideoAttr: {
                    'webkit-playsinline': true,
                    playsInline: true,
                    controlsList: 'nodownload noplaybackrate',
                    disablePictureInPicture: true,
                },
                customType: {
                    m3u8: function (video, sourceUrl, instance) {
                        if (window.Hls && Hls.isSupported()) {
                            const hls = new Hls({ enableWorker: true, lowLatencyMode: false });
                            hls.loadSource(sourceUrl);
                            hls.attachMedia(video);
                            instance._mathcourseHls = hls;
                        } else {
                            video.src = sourceUrl;
                        }
                    },
                },
            });

            function preventContextMenu(event) {
                event.preventDefault();
                event.stopPropagation();
            }

            // Disable the browser/player right-click menu without changing ArtPlayer's visual UI.
            container.addEventListener('contextmenu', preventContextMenu, true);
            if (art.contextmenu) art.contextmenu.show = false;
            bindFullscreenEvents(art.video);

            art.on('fullscreen', onFullscreenChange);
            art.on('fullscreenWeb', onFullscreenChange);
            art.on('fullscreenError', onFullscreenChange);

            art.on('ready', function () {
                if (art.contextmenu) art.contextmenu.show = false;
                const saved = savedTime();
                if (saved > 0 && art.duration > 0 && saved < art.duration - 5) {
                    try { art.currentTime = Math.min(saved, art.duration - 1); } catch (e) {}
                } else if (saved >= art.duration - 5) {
                    clearSavedTime();
                }
                syncInvertState();
            });

            art.on('timeupdate', function () {
                const time = Number(art.currentTime || 0);
                const duration = Number(art.duration || 0);
                if (!Number.isFinite(time) || time <= 0 || (duration > 0 && time >= duration - 0.5)) return;
                const value = Math.floor(time * 10) / 10;
                if (Math.floor(value) === lastSavedSecond) return;
                lastSavedSecond = Math.floor(value);
                try { localStorage.setItem(storageKey, String(value)); } catch (e) {}
            });

            art.on('pause', function () {
                const time = Number(art.currentTime || 0);
                if (Number.isFinite(time) && time > 0) {
                    try { localStorage.setItem(storageKey, String(Math.floor(time * 10) / 10)); } catch (e) {}
                }
            });

            art.on('destroy', function () {
                unbindFullscreenEvents(art.video);
                container.removeEventListener('contextmenu', preventContextMenu, true);
                if (art._mathcourseHls) {
                    try { art._mathcourseHls.destroy(); } catch (e) {}
                }
            });

            art.on('ended', submitCompletion);
            container._mathcourseArt = art;
        }

        if (window.Hls) {
            createPlayer();
        } else {
            const wait = setInterval(function () {
                if (window.Hls) {
                    clearInterval(wait);
                    createPlayer();
                }
            }, 50);
            setTimeout(function () { clearInterval(wait); }, 5000);
        }
    });
});
