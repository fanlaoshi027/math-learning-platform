document.addEventListener('DOMContentLoaded', function () {
    if (!window.Artplayer) return;

    const players = document.querySelectorAll('.mathcourse-artplayer[data-video-url]');
    const speeds = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
    const logoUrl = 'https://fanlaoshishu.com/wp-content/uploads/2026/08/video_logo.png';

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
                if ((item.getAttribute('href') || '').indexOf('lesson_id=' + lessonId) !== -1) {
                    item.classList.add('is-complete');
                    const check = item.querySelector('.mc-course-player__check');
                    if (check) check.textContent = '✓';
                }
            });
        }
    }

    function showCompletionPanel() {
        const panel = document.querySelector('.mc-course-player__completion');
        if (!panel) return;
        panel.hidden = false;
        panel.classList.add('is-visible');
    }

    players.forEach(function (container) {
        const lessonId = parseInt(container.dataset.lessonId || '0', 10);
        const courseId = parseInt(container.dataset.courseId || '0', 10);
        const url = container.dataset.videoUrl || '';
        if (!lessonId || !url) return;

        const storageKey = 'mathcourse_lesson_' + lessonId + '_time';
        let completionSent = false;
        let restorePending = true;
        let lastSavedSecond = -1;

        function savedTime() {
            try {
                const value = parseFloat(localStorage.getItem(storageKey) || '0');
                return Number.isFinite(value) && value > 0 ? value : 0;
            } catch (e) {
                return 0;
            }
        }

        function clearSavedTime() {
            try { localStorage.removeItem(storageKey); } catch (e) {}
        }

        function submitCompletion() {
            if (completionSent || !window.mathcoursePlayer || !mathcoursePlayer.ajax_url || !mathcoursePlayer.nonce) return;
            completionSent = true;

            const formData = new FormData();
            formData.append('action', 'mathcourse_complete_lesson');
            formData.append('nonce', mathcoursePlayer.nonce);
            formData.append('lesson_id', String(lessonId));

            fetch(mathcoursePlayer.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                keepalive: true
            }).then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            }).then(function (result) {
                if (!result || !result.success) throw new Error('progress rejected');
                clearSavedTime();
                const data = result.data || {};
                updateProgressUI(data.progress || null, lessonId);
                showCompletionPanel();
                document.dispatchEvent(new CustomEvent('mathcourse_lesson_complete', {
                    detail: { lessonId: lessonId, courseId: Number(data.course_id || courseId || 0), progress: data.progress || null }
                }));
                document.dispatchEvent(new CustomEvent('mathcourse_progress_updated', {
                    detail: { lessonId: lessonId, courseId: Number(data.course_id || courseId || 0), progress: data.progress || null }
                }));
            }).catch(function (error) {
                completionSent = false;
                console.warn('MathCourse: unable to save lesson completion.', error);
            });
        }

        function createPlayer() {
            const art = new Artplayer({
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
                playbackRate: true,
                aspectRatio: false,
                flip: false,
                lock: true,
                moreVideoAttr: {
                    'webkit-playsinline': true,
                    playsInline: true,
                    controlsList: 'nodownload noplaybackrate',
                    disablePictureInPicture: true,
                },
                customType: {
                    m3u8: function (video, sourceUrl) {
                        if (window.Hls && Hls.isSupported()) {
                            const hls = new Hls({
                                enableWorker: true,
                                lowLatencyMode: false,
                            });
                            hls.loadSource(sourceUrl);
                            hls.attachMedia(video);
                            art._mathcourseHls = hls;
                        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                            video.src = sourceUrl;
                        } else {
                            video.src = sourceUrl;
                        }
                    }
                },
                controls: [
                    {
                        name: 'math-logo',
                        position: 'left',
                        index: 1,
                        html: '<img class="mc-art-logo" src="' + logoUrl + '" alt="樊老师数学">',
                        tooltip: '樊老师数学',
                    },
                    {
                        name: 'skip-back',
                        position: 'left',
                        index: 2,
                        html: '<button type="button" class="mc-art-skip" aria-label="后退10秒">↶ 10</button>',
                        tooltip: '后退 10 秒',
                        click: function () { art.currentTime = Math.max(0, art.currentTime - 10); }
                    },
                    {
                        name: 'skip-forward',
                        position: 'left',
                        index: 3,
                        html: '<button type="button" class="mc-art-skip" aria-label="前进10秒">10 ↷</button>',
                        tooltip: '前进 10 秒',
                        click: function () { art.currentTime = Math.min(art.duration || Infinity, art.currentTime + 10); }
                    },
                    {
                        name: 'invert',
                        position: 'right',
                        index: 97,
                        html: '<button type="button" class="mc-art-skip mc-art-invert" aria-label="反色">◐</button>',
                        tooltip: '画面反色',
                        click: function () {
                            container.classList.toggle('is-inverted');
                        }
                    },
                    {
                        name: 'speed',
                        position: 'right',
                        index: 98,
                        html: '<button type="button" class="mc-art-skip mc-art-speed" aria-label="播放速度">1×</button>',
                        tooltip: '播放速度',
                        selector: speeds.map(function (speed) {
                            return { default: speed === 1, html: speed + '×', value: speed };
                        }),
                        onSelect: function (item) {
                            const speed = Number(item.value || String(item.html).replace('×', ''));
                            if (Number.isFinite(speed)) art.playbackRate = speed;
                            return item.html;
                        }
                    }
                ]
            });

            art.on('ready', function () {
                const saved = savedTime();
                if (saved > 0 && art.duration > 0 && saved < art.duration - 5) {
                    try { art.currentTime = Math.min(saved, art.duration - 1); } catch (e) {}
                } else if (saved >= art.duration - 5) {
                    clearSavedTime();
                }
                restorePending = false;
            });

            art.on('timeupdate', function () {
                const time = Number(art.currentTime || 0);
                const duration = Number(art.duration || 0);
                if (!Number.isFinite(time) || time <= 0) return;
                if (duration > 0 && time >= duration - 0.5) return;
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

            art.on('ended', function () {
                submitCompletion();
            });

            window.addEventListener('pagehide', function () {
                const time = Number(art.currentTime || 0);
                if (Number.isFinite(time) && time > 0) {
                    try { localStorage.setItem(storageKey, String(Math.floor(time * 10) / 10)); } catch (e) {}
                }
            });

            container._mathcourseArt = art;
        }

        if (window.Hls || !url) {
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
