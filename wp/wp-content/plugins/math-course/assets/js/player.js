document.addEventListener('DOMContentLoaded', function () {
    const players = document.querySelectorAll('video.mathcourse-player, video.video-js[data-lesson-id]');

    players.forEach(function (player) {
        const lessonId = parseInt(player.dataset.lessonId || '0', 10);
        const courseId = parseInt(player.dataset.courseId || '0', 10);

        if (!lessonId) {
            return;
        }

        const storageKey = 'mathcourse_lesson_' + lessonId + '_time';
        let completionSent = false;

        player.addEventListener('loadedmetadata', function () {
            const saved = localStorage.getItem(storageKey);
            const duration = Number(player.duration);

            if (!saved || !Number.isFinite(duration) || duration <= 0) {
                return;
            }

            const time = parseInt(saved, 10);
            if (time > 0 && time < duration - 5) {
                try {
                    player.currentTime = time;
                } catch (error) {
                    // 某些浏览器在媒体尚未准备好时禁止设置 currentTime。
                }
            }
        });

        // 播放位置只保存在当前浏览器，不上传服务器。
        player.addEventListener('timeupdate', function () {
            if (!player.ended && Number.isFinite(player.currentTime)) {
                localStorage.setItem(storageKey, String(Math.floor(player.currentTime)));
            }
        });

        player.addEventListener('ended', function () {
            if (completionSent) {
                return;
            }

            completionSent = true;
            localStorage.removeItem(storageKey);

            document.dispatchEvent(new CustomEvent('mathcourse_lesson_complete', {
                detail: {
                    lessonId: lessonId,
                    courseId: courseId
                }
            }));

            if (!courseId || !window.mathcoursePlayer || !mathcoursePlayer.ajax_url || !mathcoursePlayer.nonce) {
                return;
            }

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
                    if (result && result.success) {
                        document.dispatchEvent(new CustomEvent('mathcourse_progress_updated', {
                            detail: {
                                lessonId: lessonId,
                                courseId: courseId
                            }
                        }));
                    }
                })
                .catch(function (error) {
                    console.warn('MathCourse: unable to save lesson completion.', error);
                });
        });
    });
});
