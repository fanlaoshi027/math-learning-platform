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

        // Playback position belongs to this browser/device only.
        // Completion is saved to the server after the lesson really finishes.
        const storageKey = 'mathcourse_lesson_' + lessonId + '_time';
        let player = element;
        let completionSent = false;
        let restorePending = true;
        let lastSavedSecond = -1;

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
            const raw = localStorage.getItem(storageKey);
            const time = raw === null ? 0 : parseInt(raw, 10);
            return Number.isFinite(time) && time > 0 ? time : 0;
        }

        function restore() {
            if (!restorePending) return;

            const saved = getSavedTime();
            const duration = val('duration');

            if (!saved) {
                restorePending = false;
                return;
            }

            if (!Number.isFinite(duration) || duration <= 0) return;

            // A stale position at the very end should never make the lesson
            // appear to resume from a completed state.
            if (saved >= duration - 5) {
                localStorage.removeItem(storageKey);
                restorePending = false;
                return;
            }

            try {
                setTime(Math.min(saved, Math.max(0, duration - 1)));
                restorePending = false;
            } catch (e) {
                // Wait for the next media event and try again.
            }
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
            } catch (e) {
                // Ignore storage quota/private-mode failures; playback continues.
            }
        }

        ['loadedmetadata', 'durationchange', 'canplay'].forEach(function (eventName) {
            on(player, eventName, restore);
        });

        on(player, 'timeupdate', function () {
            // localStorage is intentionally used for the resume position so a
            // network request is not needed every time the student watches.
            savePosition(false);

            const time = val('currentTime');
            const duration = val('duration');
            if (!completionSent && Number.isFinite(duration) && duration > 0 && time >= duration - 0.5) {
                submitCompletion();
            }
        });

        on(player, 'pause', function () {
            savePosition(true);
        });

        on(player, 'ended', function () {
            localStorage.removeItem(storageKey);
            submitCompletion();
        });

        window.addEventListener('pagehide', function () {
            savePosition(true);
        });

        window.addEventListener('beforeunload', function () {
            savePosition(true);
        });

        function submitCompletion() {
            if (completionSent || !courseId || !window.mathcoursePlayer || !mathcoursePlayer.ajax_url || !mathcoursePlayer.nonce) {
                return;
            }

            completionSent = true;

            const formData = new FormData();
            formData.append('action', 'mathcourse_complete_lesson');
            formData.append('nonce', mathcoursePlayer.nonce);
            formData.append('lesson_id', String(lessonId));
            formData.append('course_id', String(courseId));

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

                    // Only remove the local resume point after the server has
                    // confirmed that this lesson is completed.
                    localStorage.removeItem(storageKey);

                    const progress = result.data && result.data.progress ? result.data.progress : null;
                    updateProgressUI(progress, lessonId);

                    document.dispatchEvent(new CustomEvent('mathcourse_lesson_complete', {
                        detail: {
                            lessonId: lessonId,
                            courseId: courseId,
                            progress: progress
                        }
                    }));

                    document.dispatchEvent(new CustomEvent('mathcourse_progress_updated', {
                        detail: {
                            lessonId: lessonId,
                            courseId: courseId,
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
