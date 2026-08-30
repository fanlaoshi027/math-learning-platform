document.addEventListener('DOMContentLoaded', function () {
    if (!window.videojs) return;
    document.querySelectorAll('video.mathcourse-player.video-js').forEach(function (element) {
        var player = videojs.getPlayer(element.id);
        if (!player) return;

        try {
            if (typeof player.playbackRates === 'function') player.playbackRates([0.5, 0.75, 1, 1.25, 1.5, 1.75, 2]);
            if (player.controlBar && !player.controlBar.getChild('PlaybackRateMenuButton')) {
                player.controlBar.addChild('PlaybackRateMenuButton', {});
            }
        } catch (e) {}

        var wrapper = element.parentElement;
        if (!wrapper || wrapper.querySelector('.mc-player-skip-controls')) return;
        wrapper.style.position = 'relative';

        var controls = document.createElement('div');
        controls.className = 'mc-player-skip-controls';
        controls.setAttribute('aria-label', '视频快捷控制');

        function makeButton(label, title, delta) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'mc-player-skip-button';
            button.textContent = label;
            button.title = title;
            button.setAttribute('aria-label', title);
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var current = Number(player.currentTime() || 0);
                var duration = Number(player.duration() || 0);
                player.currentTime(Math.max(0, Math.min(duration || Infinity, current + delta)));
            });
            return button;
        }

        controls.appendChild(makeButton('↶ 10秒', '后退10秒', -10));
        controls.appendChild(makeButton('↷ 10秒', '快进10秒', 10));
        wrapper.appendChild(controls);

        function keyboard(event) {
            var tag = (event.target && event.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || event.target.isContentEditable) return;
            if (!player || player.isDisposed()) return;
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                player.currentTime(Math.max(0, Number(player.currentTime() || 0) - 10));
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                var duration = Number(player.duration() || 0);
                player.currentTime(Math.min(duration || Infinity, Number(player.currentTime() || 0) + 10));
            } else if (event.code === 'Space') {
                event.preventDefault();
                if (player.paused()) player.play(); else player.pause();
            }
        }
        document.addEventListener('keydown', keyboard);
    });
});