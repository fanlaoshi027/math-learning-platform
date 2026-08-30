document.addEventListener('DOMContentLoaded', function () {
    const players = document.querySelectorAll('video.mathcourse-player, video.video-js[data-lesson-id]');

    function updateProgressUI(progress, lessonId) {
        if (!progress) return;
        document.querySelectorAll('.mc-course-player__progress span').forEach(function (el) { el.textContent = '学习进度 ' + Number(progress.percent || 0) + '%'; });
        document.querySelectorAll('.mc-course-player__progress strong').forEach(function (el) { el.textContent = Number(progress.completed || 0) + ' / ' + Number(progress.total || 0) + ' 课时'; });
        document.querySelectorAll('.mc-course-player__progress i').forEach(function (el) { el.style.width = Number(progress.percent || 0) + '%'; });
        document.querySelectorAll('.mc-course-player__sidebar-head span').forEach(function (el) { el.textContent = Number(progress.completed || 0) + '/' + Number(progress.total || 0); });
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

        const storageKey = 'mathcourse_lesson_' + lessonId + '_time';
        let player = element;
        let completionSent = false;
        let restorePending = true;
        let lastSavedSecond = -1;
        let seeking = false;
        let lastTime = 0;
        let lastWallClock = 0;
        let playedSinceSeek = 0;
        let completedAt = 0;

        if (window.videojs && element.classList.contains('video-js')) {
            try { player = window.videojs(element); } catch (e) { player = element; }
        }

        function val(name) {
            return typeof player[name] === 'function' ? Number(player[name]()) : Number(player[name]);
        }
        function on(target, event, callback) {
            if (target && typeof target.on === 'function') target.on(event, callback);
            else if (target && target.addEventListener) target.addEventListener(event, callback);
        }
        function setTime(time) {
            if (typeof player.currentTime === 'function') player.currentTime(time);
            else player.currentTime = time;
        }
        function getSavedTime() {
            try {
                const raw = localStorage.getItem(storageKey);
                const time = raw === null ? 0 : parseFloat(raw);
                return Number.isFinite(time) && time > 0 ? time : 0;
            } catch (e) { return 0; }
        }
        function clearSavedTime() {
            try { localStorage.removeItem(storageKey); } catch (e) {}
        }
        function restore() {
            if (!restorePending) return;
            const saved = getSavedTime();
            const duration = val('duration');
            if (!saved) { restorePending = false; lastTime = 0; return; }
            if (!Number.isFinite(duration) || duration <= 0) return;
            if (saved >= duration - 5) {
                clearSavedTime();
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
            const value = Math.floor(time * 10) / 10;
            if (!force && Math.floor(value) === lastSavedSecond) return;
            lastSavedSecond = Math.floor(value);
            try { localStorage.setItem(storageKey, String(value)); } catch (e) {}
        }
        function resetWatchWindow() {
            playedSinceSeek = 0;
            lastTime = val('currentTime');
            lastWallClock = Date.now();
        }
        function markPlayingInterval() {
            lastTime = val('currentTime');
            lastWallClock = Date.now();
        }
        function submitCompletion() {
            if (completionSent || completedAt || !window.mathcoursePlayer || !mathcoursePlayer.ajax_url || !mathcoursePlayer.nonce) return;
            completionSent = true;
            const formData = new FormData();
            formData.append('action', 'mathcourse_complete_lesson');
            formData.append('nonce', mathcoursePlayer.nonce);
            formData.append('lesson_id', String(lessonId));

            fetch(mathcoursePlayer.ajax_url, { method: 'POST', credentials: 'same-origin', body: formData, keepalive: true })
                .then(function (response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(function (result) {
                    if (!result || !result.success) throw new Error('progress rejected');
                    completedAt = Date.now();
                    clearSavedTime();
                    const data = result.data || {};
                    const serverCourseId = Number(data.course_id || courseId || 0);
                    updateProgressUI(data.progress || null, lessonId);
                    document.dispatchEvent(new CustomEvent('mathcourse_lesson_complete', { detail: { lessonId: lessonId, courseId: serverCourseId, progress: data.progress || null } }));
                    document.dispatchEvent(new CustomEvent('mathcourse_progress_updated', { detail: { lessonId: lessonId, courseId: serverCourseId, progress: data.progress || null } }));
                })
                .catch(function (error) {
                    completionSent = false;
                    console.warn('MathCourse: unable to save lesson completion.', error);
                });
        }

        ['loadedmetadata', 'durationchange', 'canplay'].forEach(function (eventName) { on(player, eventName, restore); });
        on(player, 'play', markPlayingInterval);
        on(player, 'timeupdate', function () {
            const time = val('currentTime');
            const duration = val('duration');
            if (!Number.isFinite(time)) return;
            savePosition(false);

            const now = Date.now();
            const elapsed = lastWallClock ? (now - lastWallClock) / 1000 : 0;
            const delta = time - lastTime;
            if (!seeking && delta >= 0 && delta <= Math.max(2.5, elapsed + 1.5)) {
                if (delta > 0) playedSinceSeek += Math.min(delta, 2.5);
            }
            lastTime = time;
            lastWallClock = now;

            if (!completionSent && !completedAt && !seeking && Number.isFinite(duration) && duration > 0 && time >= duration - 5 && playedSinceSeek >= 1.2) {
                submitCompletion();
            }
        });
        on(player, 'pause', function () { savePosition(true); resetWatchWindow(); });
        on(player, 'seeking', function () { seeking = true; resetWatchWindow(); });
        on(player, 'seeked', function () { seeking = false; resetWatchWindow(); });
        on(player, 'ended', function () { clearSavedTime(); submitCompletion(); });

        function persistBeforeLeave() { savePosition(true); }
        window.addEventListener('pagehide', persistBeforeLeave);
        window.addEventListener('beforeunload', persistBeforeLeave);
    });
});
