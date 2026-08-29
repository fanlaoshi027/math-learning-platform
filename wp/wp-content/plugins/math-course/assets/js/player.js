document.addEventListener(
'DOMContentLoaded',
function(){

const players = document.querySelectorAll('.mathcourse-player');

players.forEach(function(player){

    player.controls = true;

    const lessonId = player.dataset.lessonId;

    if(!lessonId){
        return;
    }

    const storageKey = 'mathcourse_lesson_' + lessonId;

    // 恢复本地播放位置
    player.addEventListener('loadedmetadata', function(){

        const saved = localStorage.getItem(storageKey);

        if(saved){

            const time = parseInt(saved,10);

            if(time > 0 && time < player.duration - 10){
                player.currentTime = time;
            }
        }

    });


    // 本地保存播放秒数，不上传服务器
    player.addEventListener('timeupdate', function(){

        localStorage.setItem(
            storageKey,
            Math.floor(player.currentTime)
        );

    });


    // 播放完成，只通知完成状态
    player.addEventListener('ended', function(){

        localStorage.removeItem(storageKey);

        document.dispatchEvent(
            new CustomEvent(
                'mathcourse_lesson_complete',
                {
                    detail:{
                        lessonId:lessonId
                    }
                }
            )
        );

    });

});

}
);