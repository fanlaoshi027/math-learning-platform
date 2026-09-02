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
        let fullscreenTarget = null;
        let fullscreenInvertTargets = [];

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

        function clearFullscreenInvertTargets() {
            fullscreenInvertTargets.forEach(function (target) {
                if (!target) return;
                target.classList.remove('math-video-invert');
                target.classList.remove('is-inverted');
                target.style.filter = '';
            });
            fullscreenInvertTargets = [];
            fullscreenTarget = null;
        }

        function addFullscreenInvertTarget(target) {
            if (!target || fullscreenInvertTargets.indexOf(target) !== -1) return;
            target.classList.add('math-video-invert');
            target.classList.add('is-inverted');
            target.style.filter = 'invert(1) hue-rotate(180deg)';
            fullscreenInvertTargets.push(target);
        }

        function syncInvertState() {
            container.classList.toggle('math-video-invert', invertEnabled);
            container.classList.toggle('is-inverted', invertEnabled);

            clearFullscreenInvertTargets();

            const activeFullscreen = document.fullscreenElement || document.webkitFullscreenElement || null;
            if (invertEnabled && activeFullscreen) {
                fullscreenTarget = activeFullscreen;

                // ArtPlayer web fullscreen can expose either the root container or the
                // internal player as the fullscreen element. Apply the native filter to
                // the actual fullscreen element and its ArtPlayer/video child so both
                // browser fullscreen implementations keep the video inverted.
                if (activeFullscreen === container || container.contains(activeFullscreen)) {
                    addFullscreenInvertTarget(activeFullscreen);
                } else if (activeFullscreen.contains(container)) {
                    addFullscreenInvertTarget(activeFullscreen);
                }

                const playerRoot = container.querySelector('.art-video-player');
                const video = art && art.video ? art.video : container.querySelector('video');
                if (playerRoot && (activeFullscreen === container || activeFullscreen.contains(playerRoot) || playerRoot === activeFullscreen)) {
                    addFullscreenInvertTarget(playerRoot);
                }
                if (video && (activeFullscreen === container || activeFullscreen.contains(video) || video === activeFullscreen)) {
                    addFullscreenInvertTarget(video);
                }
            }

            if (art && art.video) {
                art.video.classList.toggle('math-video-invert', invertEnabled);
            }
        }

        function onFullscreenChange() {
            window.requestAnimationFrame(function () {
                syncInvertState();
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
            clearFullscreenInvertTargets();
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
                theme: '#1769e0',
                volume: 0.8,
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
                lock: true,
                backdrop: true,
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

            container.addEventListener('contextmenu', preventContextMenu, true);
            if (art.contextmenu) art.contextmenu.show = false;
            bindFullscreenEvents(art.video);

            if (art.video && window.matchMedia && window.matchMedia('(max-width: 640px)').matches) {
                art.video.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    try { art.toggle(); } catch (e) {}
                }, true);
            }

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
