document.addEventListener('DOMContentLoaded', function () {
    const players = document.querySelectorAll('video.mathcourse-player, video.video-js[data-lesson-id], video[data-lesson-id]');

    function updateProgressUI(progress, courseId) {
        if (!progress) return;
        document.querySelectorAll('.mc-course-progress[data-course-id="' + String(courseId) + '"]').forEach(function (box) {
            const number = box.querySelector('[data-progress-number]');
            const bar = box.querySelector('[data-progress-bar]');
            const percent = box.querySelector('[data-progress-percent]');
            if (number) number.textContent = String(progress.completed || 0) + ' / ' + String(progress.total || 0) + ' 课时';
            if (bar) bar.style.width = String(progress.percent || 0) + '%';
            if (percent) percent.textContent = String(progress.percent || 0) + '%';
        });
    }

    function submitLessonCompletion(lessonId, courseId, storageKey, state) {
        if (state.sent || !courseId || !window.mathcoursePlayer || !mathcoursePlayer.ajax_url || !mathcoursePlayer.nonce) return;
        state.sent = true;

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
        .then(function (response) { return response.json(); })
        .then(function (result) {
            if (!result || !result.success) throw new Error('progress rejected');
            localStorage.removeItem(storageKey);
            const progress = result.data && result.data.progress ? result.data.progress : null;
            updateProgressUI(progress, courseId);
            document.dispatchEvent(new CustomEvent('mathcourse_lesson_complete', { detail: { lessonId: lessonId, courseId: courseId, progress: progress } }));
            document.dispatchEvent(new CustomEvent('mathcourse_progress_updated', { detail: { lessonId: lessonId, courseId: courseId, progress: progress } }));
        })
        .catch(function (error) {
            state.sent = false;
            console.warn('MathCourse: unable to save lesson completion.', error);
        });
    }

    players.forEach(function (element) {
        const lessonId = parseInt(element.dataset.lessonId || '0', 10);
        const courseId = parseInt(element.dataset.courseId || '0', 10);
        if (!lessonId) return;

        const storageKey = 'mathcourse_lesson_' + lessonId + '_time';
        const state = { sent: false, restorePending: true };
        let player = element;

        if (window.videojs && element.classList.contains('video-js')) {
            try { player = window.videojs(element); } catch (error) { player = element; }
        }

        function getCurrentTime() { return typeof player.currentTime === 'function' ? Number(player.currentTime()) : Number(player.currentTime); }
        function getDuration() { return typeof player.duration === 'function' ? Number(player.duration()) : Number(player.duration); }
        function on(target, eventName, callback) {
            if (target && typeof target.on === 'function') target.on(eventName, callback);
            else if (target && typeof target.addEventListener === 'function') target.addEventListener(eventName, callback);
        }
        function setCurrentTime(time) { if (typeof player.currentTime === 'function') player.currentTime(time); else player.currentTime = time; }
        function restorePosition() {
            if (!state.restorePending) return;
            const saved = localStorage.getItem(storageKey);
            const duration = getDuration();
            const time = saved ? parseInt(saved, 10) : 0;
            if (!saved || !Number.isFinite(duration) || duration <= 0 || !Number.isFinite(time) || time <= 0) return;
            if (time >= duration - 5) { localStorage.removeItem(storageKey); state.restorePending = false; return; }
            try { setCurrentTime(time); state.restorePending = false; } catch (error) {}
        }

        on(player, 'loadedmetadata', restorePosition);
        on(player, 'durationchange', restorePosition);
        on(player, 'canplay', restorePosition);
        on(player, 'timeupdate', function () {
            const currentTime = getCurrentTime();
            const duration = getDuration();
            if (currentTime > 0 && Number.isFinite(currentTime)) localStorage.setItem(storageKey, String(Math.floor(currentTime)));
            if (!state.sent && Number.isFinite(duration) && duration > 0 && currentTime >= duration - 0.5) submitLessonCompletion(lessonId, courseId, storageKey, state);
        });
        on(player, 'ended', function () { submitLessonCompletion(lessonId, courseId, storageKey, state); });
    });

    // Tutor LMS may initialize its native player after DOMContentLoaded. Capture
    // later-inserted lesson videos as well, without submitting completion twice.
    const observer = new MutationObserver(function () {
        document.querySelectorAll('video[data-lesson-id]:not([data-mathcourse-bound])').forEach(function (element) {
            element.setAttribute('data-mathcourse-bound', '1');
            const lessonId = parseInt(element.dataset.lessonId || '0', 10);
            const courseId = parseInt(element.dataset.courseId || '0', 10);
            if (!lessonId || !courseId) return;
            let sent = false;
            const submit = function () {
                if (sent || !window.mathcoursePlayer) return;
                sent = true;
                const fd = new FormData();
                fd.append('action', 'mathcourse_complete_lesson');
                fd.append('nonce', mathcoursePlayer.nonce);
                fd.append('lesson_id', String(lessonId));
                fd.append('course_id', String(courseId));
                fetch(mathcoursePlayer.ajax_url, { method: 'POST', credentials: 'same-origin', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(result){
                        if (!result || !result.success) throw new Error('progress rejected');
                        updateProgressUI(result.data.progress, courseId);
                        document.dispatchEvent(new CustomEvent('mathcourse_progress_updated', { detail: { lessonId: lessonId, courseId: courseId, progress: result.data.progress } }));
                    })
                    .catch(function(){ sent = false; });
            };
            element.addEventListener('ended', submit);
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });
});
