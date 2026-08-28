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
        if (!config.tokenUrl || !config.videoId || !config.manifestUrl) return;

        let token = null;
        let tokenExpiresAt = 0;
        let hls = null;
        let retryTimer = null;
        let hlsScriptPromise = null;

        function setMessage(text) {
            if (message) message.textContent = text || '';
        }

        function getToken(force) {
            if (!force && token && Date.now() < tokenExpiresAt - 30000) return Promise.resolve(token);
            const url = new URL(config.tokenUrl, window.location.origin);
            if (config.lessonId) url.searchParams.set('lesson_id', String(config.lessonId));
            return fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (response) {
                    if (!response.ok) return response.json().catch(function () { return {}; }).then(function (d) { throw new Error(d.message || '视频授权失败。'); });
                    return response.json();
                })
                .then(function (data) {
                    if (!data.token) throw new Error('服务器未返回播放令牌。');
                    token = data.token;
                    tokenExpiresAt = Date.now() + ((parseInt(data.expires_in, 10) || 1800) * 1000);
                    return token;
                });
        }

        function loadHlsScript() {
            if (window.Hls) return Promise.resolve(window.Hls);
            if (hlsScriptPromise) return hlsScriptPromise;
            const src = config.hlsJsUrl || 'https://cdn.jsdelivr.net/npm/hls.js@1.6.13/dist/hls.min.js';
            hlsScriptPromise = new Promise(function (resolve, reject) {
                const script = document.createElement('script');
                script.src = src;
                script.async = true;
                script.onload = function () {
                    if (window.Hls) resolve(window.Hls);
                    else reject(new Error('HLS 播放组件加载失败。'));
                };
                script.onerror = function () { reject(new Error('HLS 播放组件加载失败。')); };
                document.head.appendChild(script);
            });
            return hlsScriptPromise;
        }

        function buildManifestUrl(value) {
            const url = new URL(config.manifestUrl, window.location.origin);
            url.searchParams.set('token', value);
            return url.toString();
        }

        function destroyHls() {
            if (hls) hls.destroy();
            hls = null;
            player._mathcourseHls = null;
        }

        function startPlayback(value) {
            const manifestUrl = buildManifestUrl(value);
            player.dataset.mathcourseManifest = manifestUrl;
            setMessage('');

            if (player.canPlayType('application/vnd.apple.mpegurl')) {
                destroyHls();
                player.src = manifestUrl;
                player.load();
                return Promise.resolve();
            }

            return loadHlsScript().then(function (Hls) {
                if (!Hls.isSupported()) throw new Error('当前浏览器不支持 HLS 视频播放。');
                destroyHls();
                hls = new Hls({ enableWorker: true, lowLatencyMode: false });
                hls.loadSource(manifestUrl);
                hls.attachMedia(player);
                player._mathcourseHls = hls;
                hls.on(Hls.Events.ERROR, function (event, data) {
                    if (!data || !data.fatal) return;
                    if (data.response && (data.response.code === 401 || data.response.code === 403)) {
                        retryAuthorization();
                    } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                        hls.recoverMediaError();
                    } else {
                        setMessage('视频播放失败，请稍后重试。');
                    }
                });
            });
        }

        function retryAuthorization() {
            if (retryTimer) return;
            retryTimer = window.setTimeout(function () {
                retryTimer = null;
                const wasPlaying = !player.paused;
                getToken(true).then(startPlayback).then(function () {
                    if (wasPlaying) player.play().catch(function () {});
                }).catch(function (error) {
                    setMessage(error.message || '视频授权已失效。');
                });
            }, 300);
        }

        getToken(false).then(startPlayback).catch(function (error) {
            setMessage(error.message || '无法取得视频播放授权。');
            player.setAttribute('data-mathcourse-error', '1');
        });
    });
});
