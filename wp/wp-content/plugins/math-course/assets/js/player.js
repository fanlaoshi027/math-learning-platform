document.addEventListener('DOMContentLoaded', function () {
    const players = document.querySelectorAll('.mathcourse-player');

    players.forEach(function (player) {
        const wrap = player.closest('.mathcourse-video-wrap');
        const configNode = wrap ? wrap.nextElementSibling : null;
        const message = wrap ? wrap.querySelector('.mathcourse-player-message') : null;
        if (!configNode || !configNode.classList.contains('mathcourse-player-config')) return;

        let config;
        try { config = JSON.parse(configNode.textContent || '{}'); }
        catch (e) { if (message) message.textContent = '播放器配置无效。'; return; }

        if (!config.tokenUrl || !config.videoId) return;

        const tokenUrl = new URL(config.tokenUrl, window.location.origin);
        if (config.lessonId) tokenUrl.searchParams.set('lesson_id', String(config.lessonId));

        fetch(tokenUrl.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) return response.json().catch(function () { return {}; }).then(function (d) { throw new Error(d.message || '视频授权失败。'); });
                return response.json();
            })
            .then(function (data) {
                if (!data.token) throw new Error('服务器未返回播放令牌。');
                player.dataset.mathcourseToken = data.token;
                player.dataset.mathcourseTokenExpiresIn = String(data.expires_in || 1800);

                const manifestUrl = new URL(config.manifestUrl || '', window.location.origin);
                manifestUrl.searchParams.set('token', data.token);
                player.dataset.mathcourseManifest = manifestUrl.toString();

                if (player.canPlayType('application/vnd.apple.mpegurl')) {
                    player.src = manifestUrl.toString();
                    player.load();
                    return;
                }

                if (window.Hls && window.Hls.isSupported()) {
                    const hls = new window.Hls({ enableWorker: true });
                    hls.loadSource(manifestUrl.toString());
                    hls.attachMedia(player);
                    player._mathcourseHls = hls;
                    return;
                }

                throw new Error('当前浏览器不支持 HLS 视频播放。');
            })
            .catch(function (error) {
                if (message) message.textContent = error.message || '无法取得视频播放授权。';
                player.setAttribute('data-mathcourse-error', '1');
            });
    });
});
