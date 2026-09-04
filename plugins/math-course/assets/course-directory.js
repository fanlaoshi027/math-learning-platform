(function () {
    function getModal() {
        var modal = document.getElementById('mathcourse-lock-modal');
        if (modal) return modal;
        modal = document.createElement('div');
        modal.id = 'mathcourse-lock-modal';
        modal.className = 'mathcourse-lock-modal';
        modal.innerHTML = '<div class="mathcourse-lock-modal__backdrop" data-mathcourse-close></div>' +
            '<div class="mathcourse-lock-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mathcourse-lock-title">' +
            '<button type="button" class="mathcourse-lock-modal__close" aria-label="关闭" data-mathcourse-close>×</button>' +
            '<div class="mathcourse-lock-modal__icon" aria-hidden="true">🔒</div>' +
            '<h2 id="mathcourse-lock-title">本课时需要课程授权</h2>' +
            '<p>该课时暂未开放观看，完整课程需要获得授权。</p>' +
            '<div class="mathcourse-lock-modal__contact"><strong>需要学习这门课程？</strong><span>添加樊老师微信，获取课程授权。</span><div class="mathcourse-lock-modal__wechat"><b>微信号：fanlaoshi027</b><button type="button" data-mathcourse-copy>复制微信号</button></div><em data-mathcourse-copy-status aria-live="polite"></em></div>' +
            '<div class="mathcourse-lock-modal__actions"><button type="button" class="mathcourse-lock-modal__secondary" data-mathcourse-close>知道了</button></div>' +
            '</div>';
        document.body.appendChild(modal);
        return modal;
    }
    function openModal() {
        var modal = getModal();
        modal.classList.add('is-open');
        document.body.classList.add('mathcourse-modal-open');
    }
    function closeModal() {
        var modal = document.getElementById('mathcourse-lock-modal');
        if (!modal) return;
        modal.classList.remove('is-open');
        document.body.classList.remove('mathcourse-modal-open');
    }
    function adjacentLesson(trigger) {
        var player = trigger.closest('.mc-course-player');
        if (!player) return null;
        var active = player.querySelector('.mc-course-player__item.is-active');
        if (!active) return null;
        var items = Array.prototype.slice.call(player.querySelectorAll('.mc-course-player__item'));
        var index = items.indexOf(active);
        if (index < 0) return null;
        var direction = trigger.classList.contains('is-next') ? 1 : -1;
        return items[index + direction] || null;
    }
    document.addEventListener('click', function (event) {
        var lock = event.target.closest('[data-mathcourse-lock], .mc-course-player__item.is-locked');
        if (lock) {
            event.preventDefault();
            openModal();
            return;
        }
        var nav = event.target.closest('.mc-course-player__nav-button');
        if (nav && !nav.classList.contains('is-disabled')) {
            var adjacent = adjacentLesson(nav);
            if (adjacent && adjacent.classList.contains('is-locked')) {
                event.preventDefault();
                openModal();
                return;
            }
        }
        if (event.target.closest('[data-mathcourse-copy]')) {
            event.preventDefault();
            var status = event.target.closest('.mathcourse-lock-modal__contact').querySelector('[data-mathcourse-copy-status]');
            var done = function () { if (status) { status.textContent = '已复制'; setTimeout(function () { status.textContent = ''; }, 1800); } };
            if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText('fanlaoshi027').then(done).catch(function () {});
            else { var input = document.createElement('textarea'); input.value = 'fanlaoshi027'; input.style.position = 'fixed'; input.style.opacity = '0'; document.body.appendChild(input); input.select(); try { document.execCommand('copy'); done(); } catch (e) {} document.body.removeChild(input); }
        }
        if (event.target.closest('[data-mathcourse-close]')) closeModal();
    });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeModal(); });
})();
