document.addEventListener('DOMContentLoaded', function () {
    const players = document.querySelectorAll('.mathcourse-player');

    players.forEach(function (player) {
        player.controls = true;

        const wrap = player.closest('.mathcourse-video-wrap');
        const configNode = wrap ? wrap.nextElementSibling : null;
        const message = wrap ? wrap.querySelector('.mathcourse-player-message') : null;

        if (!configNode || !configNode.classList.contains('mathcourse-player-config')) {
            return;
        }

        let config;
        try {
            config = JSON.parse(configNode.textContent || '{}');
        } catch (e) {
            if (message) message.textContent = '播放器配置无效。';
            return;
        }

        if (!config.tokenUrl || !config.videoId) {
            return;
        }

        const url = new URL(config.tokenUrl, window.location.origin);
        if (config.lessonId) {
            url.searchParams.set('lesson_id', String(config.lessonId));
        }

        fetch(url.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    return response.json().catch(function () { return {}; }).then(function (data) {
                        throw new Error(data.message || '视频授权失败。');
                    });
                }
                return response.json();
            })
            .then(function (data) {
                if (!data.token) {
                    throw new Error('服务器未返回播放令牌。');
                }

                // Keep the token out of HTML source URLs. The HLS integration
                // can consume this value when the protected media endpoint is added.
                player.dataset.mathcourseToken = data.token;
                player.dataset.mathcourseTokenExpiresIn = String(data.expires_in || 1800);

                player.dispatchEvent(new CustomEvent('mathcourse:token-ready', {
                    detail: data
                }));
            })
            .catch(function (error) {
                if (message) {
                    message.textContent = error.message || '无法取得视频播放授权。';
                }
                player.setAttribute('data-mathcourse-error', '1');
            });
    });
});
