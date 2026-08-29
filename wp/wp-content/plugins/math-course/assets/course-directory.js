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
            '<div class="mathcourse-lock-modal__icon">🔒</div>' +
            '<h2 id="mathcourse-lock-title">本课时需要课程授权</h2>' +
            '<p>你可以观看标记为“试看”的课时。获得课程授权后，即可观看全部课程内容。</p>' +
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
