(function () {
    'use strict';
    if (typeof window.jQuery === 'undefined') return;

    function initBatch() {
        var start = document.getElementById('mc-batch-start');
        if (!start || typeof window.MathCourseBatch === 'undefined') return;
        var busy = false;
        start.addEventListener('click', function () {
            if (busy) return;
            busy = true;
            start.disabled = true;
            step();
        });
        function step() {
            jQuery.post(MathCourseBatch.ajaxurl, {
                action: 'mathcourse_batch_step',
                nonce: MathCourseBatch.nonce,
                task_id: MathCourseBatch.taskId
            }).done(function (response) {
                if (!response || !response.success) {
                    busy = false; start.disabled = false; return;
                }
                var d = response.data;
                var pct = d.total ? Math.round(d.done / d.total * 100) : 100;
                var bar = document.getElementById('mc-batch-bar');
                var percent = document.getElementById('mc-batch-percent');
                var text = document.getElementById('mc-batch-text');
                var state = document.getElementById('mc-batch-state');
                if (bar) bar.style.width = pct + '%';
                if (percent) percent.textContent = pct + '%';
                if (text) text.textContent = d.done + ' / ' + d.total;
                if (state) state.textContent = d.status;
                var stats = document.getElementById('mc-batch-stats');
                if (stats) stats.innerHTML = '<span>新建：<strong>' + d.created_count + '</strong></span><span>跳过：<strong>' + d.skipped_count + '</strong></span><span>失败：<strong>' + d.failed_count + '</strong></span><span>状态：<strong id="mc-batch-state">' + d.status + '</strong></span>';
                if (d.errors && d.errors.length) {
                    var box = document.getElementById('mc-batch-errors');
                    if (box) box.innerHTML = '<div class="notice notice-error"><p>' + d.errors.map(function (e) { return '第' + e.page + '页：' + e.message; }).join('<br>') + '</p></div>';
                }
                if (d.status === 'completed' || d.status === 'paused' || d.status === 'cancelled') {
                    busy = false; start.disabled = false;
                    return;
                }
                window.setTimeout(step, 120);
            }).fail(function () {
                busy = false; start.disabled = false;
            });
        }
    }

    document.addEventListener('DOMContentLoaded', initBatch);
}());
