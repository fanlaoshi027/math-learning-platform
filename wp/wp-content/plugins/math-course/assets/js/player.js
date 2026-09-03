document.addEventListener('DOMContentLoaded', function () {
    if (!window.Artplayer) return;

    const userAgent = navigator.userAgent || '';
    const isWeChat = /MicroMessenger/i.test(userAgent);
    const isAndroid = /Android/i.test(userAgent);
    const isIOS = /iPhone|iPad|iPod/i.test(userAgent);
    document.body.classList.add('math-learning-page');
    if (isWeChat) document.body.classList.add('math-wechat');
    if (isWeChat && isAndroid) document.body.classList.add('math-wechat-android');
    if (isWeChat && isIOS) document.body.classList.add('math-wechat-ios');

    if ('FULLSCREEN_WEB_IN_BODY' in window.Artplayer) {
        window.Artplayer.FULLSCREEN_WEB_IN_BODY = true;
    }

    const players = document.querySelectorAll('.mathcourse-artplayer[data-video-url]');
    const speeds = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];

    function updateProgressUI(progress) {
        if (!progress) return;
        document.querySelectorAll('.mc-course-player__progress span').forEach(el => el.textContent = '学习进度');
        document.querySelectorAll('.mc-course-player__progress strong').forEach(el => el.textContent = Number(progress.percent || 0) + '%');
        document.querySelectorAll('.mc-course-player__progress b').forEach(el => el.style.width = Number(progress.percent || 0) + '%');
        document.querySelectorAll('.mc-course-player__sidebar-head strong').forEach(el => el.textContent = Number(progress.completed || 0) + ' / ' + Number(progress.total || 0) + ' 课时');
    }

    function showCompletionPanel() {
        const panel = document.querySelector('.mc-course-player__completion');
        if (panel) {
            panel.hidden = false;
            panel.classList.add('is-visible');
        }
    }

    function initializePlayers() {
        if (!window.Hls || typeof window.Hls.isSupported !== 'function') {
            console.warn('MathCourse: self-hosted HLS library is unavailable.');
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
                if (!video) return;
                video.classList.toggle('mathcourse-invert-video', invertEnabled);
                if (invertEnabled) {
                    video.style.setProperty('filter', 'invert(1) hue-rotate(180deg)', 'important');
                    video.style.setProperty('-webkit-filter', 'invert(1) hue-rotate(180deg)', 'important');
                } else {
                    video.style.removeProperty('filter');
                    video.style.removeProperty('-webkit-filter');
                }
                container.classList.toggle('is-inverted', invertEnabled);
            }

            function setFullscreenState() {
                const webActive = !!(art && art.fullscreenWeb);
                const windowActive = !!(art && art.fullscreen);
                document.body.classList.toggle('mathcourse-web-fullscreen', webActive);
                container.classList.toggle('is-web-fullscreen', webActive);
                container.classList.toggle('is-window-fullscreen', windowActive);
                window.requestAnimationFrame(function () {
                    if (art && typeof art.resize === 'function') art.resize();
                    syncInvertState();
                    window.requestAnimationFrame(function () {
                        if (art && typeof art.resize === 'function') art.resize();
                        syncInvertState();
                    });
                });
            }

            function onFullscreenChange() { setFullscreenState(); }

            function bindFullscreenEvents(video) {
                document.addEventListener('fullscreenchange', onFullscreenChange);
                document.addEventListener('webkitfullscreenchange', onFullscreenChange);
                window.addEventListener('orientationchange', onFullscreenChange, { passive: true });
                window.addEventListener('resize', onFullscreenChange, { passive: true });
                window.addEventListener('pageshow', onFullscreenChange, { passive: true });
                document.addEventListener('visibilitychange', onFullscreenChange, { passive: true });
                if (video) {
                    video.addEventListener('webkitbeginfullscreen', onFullscreenChange);
                    video.addEventListener('webkitendfullscreen', onFullscreenChange);
                    video.addEventListener('x5videoenterfullscreen', onFullscreenChange);
                    video.addEventListener('x5videoexitfullscreen', onFullscreenChange);
                }
            }

            function unbindFullscreenEvents(video) {
                document.removeEventListener('fullscreenchange', onFullscreenChange);
                document.removeEventListener('webkitfullscreenchange', onFullscreenChange);
                window.removeEventListener('orientationchange', onFullscreenChange);
                window.removeEventListener('resize', onFullscreenChange);
                window.removeEventListener('pageshow', onFullscreenChange);
                document.removeEventListener('visibilitychange', onFullscreenChange);
                if (video) {
                    video.removeEventListener('webkitbeginfullscreen', onFullscreenChange);
                    video.removeEventListener('webkitendfullscreen', onFullscreenChange);
                    video.removeEventListener('x5videoenterfullscreen', onFullscreenChange);
                    video.removeEventListener('x5videoexitfullscreen', onFullscreenChange);
                }
            }

            function createPlayer() {
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
                    airplay: true,
                    playsInline: true,
                    autoOrientation: true,
                    setting: false,
                    playbackRate: false,
                    flip: false,
                    aspectRatio: false,
                    subtitleOffset: false,
                    contextmenu: [],
                    controls: [
                        {
                            name: 'math-speed',
                            index: 10,
                            position: 'right',
                            html: '1×',
                            tooltip: '播放速度',
                            selector: speeds.map(function (speed) {
                                return {
                                    html: speed + '×',
                                    value: speed,
                                    default: speed === 1,
                                };
                            }),
                            onSelect: function (item) {
                                const speed = Number(item.value);
                                if (Number.isFinite(speed) && art) {
                                    art.playbackRate = speed;
                                }
                                return item.html;
                            },
                        },
                        {
                            name: 'math-invert',
                            index: 20,
                            position: 'right',
                            html: '反色',
                            tooltip: '反色播放',
                            click: function () {
                                invertEnabled = !invertEnabled;
                                syncInvertState();
                            },
                        },
                    ],
                    moreVideoAttr: {
                        'webkit-playsinline': 'true',
                        playsinline: 'true',
                        'x5-playsinline': 'true',
                        'x5-video-player-type': isWeChat && isAndroid ? 'h5' : '',
                        'x5-video-player-fullscreen': isWeChat && isAndroid ? 'true' : '',
                        'x-webkit-airplay': 'allow',
                        controlsList: 'nodownload noplaybackrate',
                        disablePictureInPicture: true,
                    },
                    customType: {
                        m3u8: function (video, sourceUrl, instance) {
                            // Course media remains on the user's own server.
                            if (window.Hls && typeof window.Hls.isSupported === 'function' && window.Hls.isSupported()) {
                                const hls = new window.Hls({
                                    enableWorker: true,
                                    lowLatencyMode: false,
                                    backBufferLength: 30,
                                    maxBufferLength: 30,
                                    maxMaxBufferLength: 60,
                                });
                                instance._mathcourseHls = hls;
                                hls.on(window.Hls.Events.ERROR, function (_event, data) {
                                    if (!data) return;
                                    console.warn('MathCourse HLS error:', data.type, data.details, data.fatal ? '(fatal)' : '');
                                    if (!data.fatal) return;
                                    if (data.type === window.Hls.ErrorTypes.NETWORK_ERROR) {
                                        try { hls.startLoad(); } catch (e) {}
                                    } else if (data.type === window.Hls.ErrorTypes.MEDIA_ERROR) {
                                        try { hls.recoverMediaError(); } catch (e) {}
                                    }
                                });
                                hls.attachMedia(video);
                                hls.on(window.Hls.Events.MEDIA_ATTACHED, function () {
                                    hls.loadSource(sourceUrl);
                                });
                            } else {
                                // Safari/iOS can play the same self-hosted HLS URL natively.
                                video.src = sourceUrl;
                                video.load();
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

                art.on('fullscreen', setFullscreenState);
                art.on('fullscreenWeb', setFullscreenState);
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
                    setFullscreenState();
                    window.setTimeout(onFullscreenChange, isWeChat ? 80 : 0);
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
                    setFullscreenState();
                    document.body.classList.remove('mathcourse-web-fullscreen');
                    container.classList.remove('is-web-fullscreen', 'is-window-fullscreen', 'is-inverted');
                    unbindFullscreenEvents(art.video);
                    container.removeEventListener('contextmenu', preventContextMenu, true);
                    if (art._mathcourseHls) {
                        try { art._mathcourseHls.destroy(); } catch (e) {}
                    }
                });

                art.on('ended', submitCompletion);
                container._mathcourseArt = art;
            }

            createPlayer();
        });
    }

    initializePlayers();
});
