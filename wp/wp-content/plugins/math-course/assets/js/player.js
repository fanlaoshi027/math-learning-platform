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

        // Video.js 接管播放器后，事件仍绑定到同一个 video 元素；原生 HLS
        // 在 Safari 等浏览器中也可以直接工作。
        if (window.videojs && element.classList.contains('video-js')) {
            try {
                player = window.videojs(element);
            } catch (error) {
                player = element;
            }
        }

        function getCurrentTime() {
            return typeof player.currentTime === 'function'
                ? Number(player.currentTime())
                : Number(player.currentTime);
        }

        function getDuration() {
            return typeof player.duration === 'function'
                ? Number(player.duration())
                : Number(player.duration);
        }

        function on(target, eventName, callback) {
            if (target && typeof target.on === 'function') {
                target.on(eventName, callback);
            } else if (target && typeof target.addEventListener === 'function') {
                target.addEventListener(eventName, callback);
            }
        }

        on(player, 'loadedmetadata', function () {
            const saved = localStorage.getItem(storageKey);
            const duration = getDuration();

            if (!saved || !Number.isFinite(duration) || duration <= 0) {
                return;
            }

            const time = parseInt(saved, 10);
            if (time > 0 && time < duration - 5) {
                try {
                    if (typeof player.currentTime === 'function') {
                        player.currentTime(time);
                    } else {
                        player.currentTime = time;
                    }
                } catch (error) {
                    // 媒体尚未准备好时，部分浏览器会拒绝设置 currentTime。
                }
            }
        });

        // 播放位置只保存在当前浏览器，不上传服务器。
        on(player, 'timeupdate', function () {
            const currentTime = getCurrentTime();
            if (currentTime > 0 && Number.isFinite(currentTime)) {
                localStorage.setItem(storageKey, String(Math.floor(currentTime)));
            }
        });

        on(player, 'ended', function () {
            if (completionSent) {
                return;
            }

            // 没有课程 ID 时不能安全提交完成记录。
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

                    // 只有服务器确认完成后才清除本地播放位置。
                    localStorage.removeItem(storageKey);

                    document.dispatchEvent(new CustomEvent('mathcourse_lesson_complete', {
                        detail: {
                            lessonId: lessonId,
                            courseId: courseId
                        }
                    }));

                    document.dispatchEvent(new CustomEvent('mathcourse_progress_updated', {
                        detail: {
                            lessonId: lessonId,
                            courseId: courseId
                        }
                    }));
                })
                .catch(function (error) {
                    completionSent = false;
                    console.warn('MathCourse: unable to save lesson completion.', error);
                });
        });
    });
});
