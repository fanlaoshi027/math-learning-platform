document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('lesson_hls_edit');
    if (input) {
        // HLS 支持相对路径，例如 /wp-content/uploads/.../index.m3u8。
        // 不要改成 type=url，否则浏览器会阻止相对地址提交。
        input.type = 'text';
        input.setAttribute('inputmode', 'url');
        input.removeAttribute('pattern');
        input.removeAttribute('required');
    }

    /* 给旧版 PHP 页面补充语义 class，避免改动后台数据/业务逻辑。 */
    var params = new URLSearchParams(window.location.search);
    var page = params.get('page');
    var wrap = document.querySelector('.wrap');
    if (wrap && page === 'mathcourse-course-edit') {
        wrap.classList.add('mathcourse-editor');
    }
    if (wrap && page === 'mathcourse-batch') {
        wrap.classList.add('mathcourse-batch-page');
    }
});
