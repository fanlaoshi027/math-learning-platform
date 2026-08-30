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
});
