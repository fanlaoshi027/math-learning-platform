document.addEventListener('DOMContentLoaded', function () {
    const players = document.querySelectorAll('video.mathcourse-player, video.video-js[data-lesson-id]');

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
                const link = item.getAttribute('href') || '';
                if (link.indexOf('lesson_id=' + lessonId) !== -1) {
                    item.classList.add('is-complete');
                    const check = item.querySelector('.mc-course-player__check');
                    if (check) check.textContent = '✓';
                }
            });
        }
    }

    players.forEach(function (element) {
        const lessonId = parseInt(element.dataset.lessonId || '0', 10);
        const courseId = parseInt(element.dataset.courseId || '0', 10);
        if (!lessonId) return;

        // Playback position is local to this browser/device.
        // The server receives only the final lesson-completion event.
        const storageKey = 'mathcourse_lesson_' + lessonId + '_time';
        let player = element;
        let completionSent = false;
        let restorePending = true;
        let lastSavedSecond = -1;
        let seeking = false;
        let lastTime = 0;
        let lastWallClock = 0;
        let playedSinceSeek = 0;

        if (window.videojs && element.classList.contains('video-js')) {
            try {
                player = window.videojs(element);
            } catch (e) {
                player = element;
            }
        }

        function val(name) {
            return typeof player[name] === 'function' ? Number(player[name]()) : Number(player[name]);
        }

        function on(target, event, callback) {
            if (target && typeof target.on === 'function') {
                target.on(event, callback);
            } else if (target && target.addEventListener) {
                target.addEventListener(event, callback);
            }
        }

        function setTime(time) {
            if (typeof player.currentTime === 'function') {
                player.currentTime(time);
            } else {
                player.currentTime = time;
            }
        }

        function getSavedTime() {
            try {
                const raw = localStorage.getItem(storageKey);
                const time = raw === null ? 0 : parseInt(raw, 10);
                return Number.isFinite(time) && time > 0 ? time : 0;
            } catch (e) {
                return 0;
            }
        }

        function restore() {
            if (!restorePending) return;

            const saved = getSavedTime();
            const duration = val('duration');

            if (!saved) {
                restorePending = false;
                lastTime = 0;
                return;
            }

            if (!Number.isFinite(duration) || duration <= 0) return;

            // Do not resume from a stale position at the very end of a video.
            if (saved >= duration - 5) {
                try { localStorage.removeItem(storageKey); } catch (e) {}
                restorePending = false;
                lastTime = 0;
                return;
            }

            try {
                setTime(Math.min(saved, Math.max(0, duration - 1)));
                lastTime = saved;
                restorePending = false;
            } catch (e) {}
        }

        function savePosition(force) {
            const time = val('currentTime');
            const duration = val('duration');

            if (!Number.isFinite(time) || time <= 0) return;
            if (Number.isFinite(duration) && duration > 0 && time >= duration - 0.5) return;

            const second = Math.floor(time);
            if (!force && second === lastSavedSecond) return;

            lastSavedSecond = second;
            try {
                localStorage.setItem(storageKey, String(second));
            } catch (e) {}
        }

        ['loadedmetadata', 'durationchange', 'canplay'].forEach(function (eventName) {
            on(player, eventName, restore);
        });

        on(player, 'play', function () {
            lastTime = val('currentTime');
            lastWallClock = Date.now();
        });

        on(player, 'timeupdate', function () {
            const time = val('currentTime');
            const duration = val('duration');
            if (!Number.isFinite(time)) return;

            savePosition(false);

            const now = Date.now();
            const elapsed = lastWallClock ? (now - lastWallClock) / 1000 : 0;
            const delta = time - lastTime;

            // Normal playback is allowed to advance freely. A seek jump is not
            // counted as watched time, but we do not restrict or undo the seek.
            if (!seeking && delta >= 0 && delta <= Math.max(2.5, elapsed + 1.5)) {
                if (delta > 0) playedSinceSeek += Math.min(delta, 2.5);
            }

            lastTime = time;
            lastWallClock = now;

            // Completion is intentionally lenient. The student may freely
            // fast-forward; only the final few seconds must actually play.
            // A seek to the end alone therefore does not complete the lesson.
            if (!completionSent && !seeking && Number.isFinite(duration) && duration > 0 && time >= duration - 5 && playedSinceSeek >= 1.2) {
                submitCompletion();
            }
        });

        on(player, 'pause', function () {
            savePosition(true);
            lastTime = val('currentTime');
            lastWallClock = Date.now();
        });

        on(player, 'seeking', function () {
            seeking = true;
            playedSinceSeek = 0;
            lastTime = val('currentTime');
            lastWallClock = Date.now();
        });

        on(player, 'seeked', function () {
            seeking = false;
            lastTime = val('currentTime');
            lastWallClock = Date.now();
        });

        on(player, 'ended', function () {
            try { localStorage.removeItem(storageKey); } catch (e) {}
            submitCompletion();
        });

        window.addEventListener('pagehide', function () {
            savePosition(true);
        });

        window.addEventListener('beforeunload', function () {
            savePosition(true);
        });

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
                body: formData
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(function (result) {
                    if (!result || !result.success) throw new Error('progress rejected');

                    try { localStorage.removeItem(storageKey); } catch (e) {}

                    const data = result.data || {};
                    const serverCourseId = Number(data.course_id || courseId || 0);
                    const progress = data.progress || null;
                    updateProgressUI(progress, lessonId);

                    document.dispatchEvent(new CustomEvent('mathcourse_lesson_complete', {
                        detail: {
                            lessonId: lessonId,
                            courseId: serverCourseId,
                            progress: progress
                        }
                    }));

                    document.dispatchEvent(new CustomEvent('mathcourse_progress_updated', {
                        detail: {
                            lessonId: lessonId,
                            courseId: serverCourseId,
                            progress: progress
                        }
                    }));
                })
                .catch(function (error) {
                    completionSent = false;
                    console.warn('MathCourse: unable to save lesson completion.', error);
                });
        }
    });
});
