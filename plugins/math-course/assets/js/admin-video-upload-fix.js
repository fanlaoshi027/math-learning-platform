document.addEventListener('DOMContentLoaded', function () {
    var wrap = document.querySelector('.wrap.mathcourse-editor');
    if (!wrap) return;

    /*
     * 课程编辑器采用 AJAX 切换课时面板。使用事件委托，避免面板重建后
     * “上传并转换”按钮失去交互；没有选文件时直接打开系统文件选择器。
     */
    wrap.addEventListener('click', function (event) {
        var button = event.target.closest('.mathcourse-video-upload-btn');
        if (!button || button.disabled) return;
        var box = button.closest('.mathcourse-video-upload-box');
        var fileInput = box ? box.querySelector('.mathcourse-video-file') : null;
        if (!fileInput) return;
        if (!fileInput.files || !fileInput.files.length) {
            event.preventDefault();
            event.stopImmediatePropagation();
            fileInput.click();
        }
    }, true);
});
