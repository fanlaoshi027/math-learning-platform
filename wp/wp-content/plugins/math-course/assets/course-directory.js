document.addEventListener('click', function (event) {
    const trigger = event.target.closest('[data-mathcourse-lock]');
    if (!trigger) return;

    event.preventDefault();

    let modal = document.getElementById('mathcourse-lock-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'mathcourse-lock-modal';
        modal.className = 'mathcourse-lock-modal';
        modal.innerHTML = '<div class="mathcourse-lock-modal__backdrop" data-mathcourse-close></div>' +
            '<div class="mathcourse-lock-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mathcourse-lock-title">' +
            '<button type="button" class="mathcourse-lock-modal__close" aria-label="关闭" data-mathcourse-close>×</button>' +
            '<div class="mathcourse-lock-modal__icon" aria-hidden="true">🔒</div>' +
            '<h2 id="mathcourse-lock-title">本课时需要课程授权</h2>' +
            '<p>该课时暂未开放观看。试看课时可以直接学习，完整课程需要获得授权。</p>' +
            '<div class="mathcourse-lock-modal__contact"><strong>需要学习这门课程？</strong><span>请联系樊老师获取课程授权。</span></div>' +
            '<div class="mathcourse-lock-modal__actions"><button type="button" class="mathcourse-lock-modal__secondary" data-mathcourse-close>知道了</button></div>' +
            '</div>';
        document.body.appendChild(modal);
    }

    modal.classList.add('is-open');
    document.body.classList.add('mathcourse-modal-open');
});

document.addEventListener('click', function (event) {
    if (!event.target.closest('[data-mathcourse-close]')) return;
    const modal = document.getElementById('mathcourse-lock-modal');
    if (!modal) return;
    modal.classList.remove('is-open');
    document.body.classList.remove('mathcourse-modal-open');
});

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    const modal = document.getElementById('mathcourse-lock-modal');
    if (!modal) return;
    modal.classList.remove('is-open');
    document.body.classList.remove('mathcourse-modal-open');
});
