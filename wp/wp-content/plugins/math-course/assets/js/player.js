document.addEventListener('DOMContentLoaded', function () {
    const players = document.querySelectorAll('video.mathcourse-player, video.video-js[data-lesson-id]');

    players.forEach(function (element) {
        const lessonId = parseInt(element.dataset.lessonId || '0', 10);
        const courseId = parseInt(element.dataset.courseId || '0', 10);

        if (!lessonId) {
            return;
        }

        const storageKey = 'mathcourse_lesson_' + lessonId + '_time';
        let player = element;
        let completionSent = false;
        let restorePending = true;

        if (window.videojs && element.classList.contains('video-js')) {
            try {
                player = window.videojs(element);
            } catch (error) {
                player = element;
            }
        }

        function getCurrentTime() {
            return typeof player.currentTime === 'function' ? Number(player.currentTime()) : Number(player.currentTime);
        }

        function getDuration() {
            return typeof player.duration === 'function' ? Number(player.duration()) : Number(player.duration);
        }

        function on(target, eventName, callback) {
            if (target && typeof target.on === 'function') {
                target.on(eventName, callback);
            } else if (target && typeof target.addEventListener === 'function') {
                target.addEventListener(eventName, callback);
            }
        }

        function setCurrentTime(time) {
            if (typeof player.currentTime === 'function') {
                player.currentTime(time);
            } else {
                player.currentTime = time;
            }
        }

        function restorePosition() {
            if (!restorePending) {
                return;
            }

            const saved = localStorage.getItem(storageKey);
            const duration = getDuration();
            const time = saved ? parseInt(saved, 10) : 0;

            if (!saved || !Number.isFinite(duration) || duration <= 0 || !Number.isFinite(time) || time <= 0) {
                return;
            }

            // 留出最后 5 秒，避免上次异常退出已经接近结束却被当成未完成。
            if (time >= duration - 5) {
                localStorage.removeItem(storageKey);
                restorePending = false;
                return;
            }

            try {
                setCurrentTime(time);
                restorePending = false;
            } catch (error) {
                // 某些浏览器需要等媒体真正可 seek 后再恢复。
            }
        }

        // Safari/HLS 有时 loadedmetadata 时 duration 尚未稳定，因此同时监听 durationchange/canplay。
        on(player, 'loadedmetadata', restorePosition);
        on(player, 'durationchange', restorePosition);
        on(player, 'canplay', restorePosition);

        // 播放位置只保存在当前浏览器，不上传服务器。
        on(player, 'timeupdate', function () {
            const currentTime = getCurrentTime();
            const duration = getDuration();

            if (currentTime > 0 && Number.isFinite(currentTime)) {
                localStorage.setItem(storageKey, String(Math.floor(currentTime)));
            }

            // 防止部分浏览器没有可靠触发 ended 时永远无法完成。
            // 只有明确达到视频末尾才提交，且仍由服务器最终决定是否成功。
            if (!completionSent && Number.isFinite(duration) && duration > 0 && currentTime >= duration - 0.5) {
                submitCompletion();
            }
        });

        on(player, 'ended', submitCompletion);

        function submitCompletion() {
            if (completionSent) {
                return;
            }

            if (!courseId || !window.mathcoursePlayer || !mathcoursePlayer.ajax_url || !mathcoursePlayer.nonce) {
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
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function (result) {
                    if (!result || !result.success) {
                        throw new Error('progress rejected');
                    }

                    localStorage.removeItem(storageKey);

                    document.dispatchEvent(new CustomEvent('mathcourse_lesson_complete', {
                        detail: {
                            lessonId: lessonId,
                            courseId: courseId,
                            progress: result.data && result.data.progress ? result.data.progress : null
                        }
                    }));

                    document.dispatchEvent(new CustomEvent('mathcourse_progress_updated', {
                        detail: {
                            lessonId: lessonId,
                            courseId: courseId,
                            progress: result.data && result.data.progress ? result.data.progress : null
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
