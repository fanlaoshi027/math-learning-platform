document.addEventListener('DOMContentLoaded', function () {
    if (!window.Artplayer) return;
    const players = document.querySelectorAll('.mathcourse-artplayer[data-video-url]');
    const speeds = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
    const logoUrl = 'https://fanlaoshishu.com/wp-content/uploads/2026/08/video_logo.png';
    function updateProgressUI(progress, lessonId) { if (!progress) return; document.querySelectorAll('.mc-course-player__progress span').forEach(el => el.textContent='学习进度 '+Number(progress.percent||0)+'%'); document.querySelectorAll('.mc-course-player__progress strong').forEach(el=>el.textContent=Number(progress.completed||0)+' / '+Number(progress.total||0)+' 课时'); document.querySelectorAll('.mc-course-player__progress i').forEach(el=>el.style.width=Number(progress.percent||0)+'%'); document.querySelectorAll('.mc-course-player__sidebar-head span').forEach(el=>el.textContent=Number(progress.completed||0)+'/'+Number(progress.total||0)); }
    function showCompletionPanel(){const p=document.querySelector('.mc-course-player__completion');if(p){p.hidden=false;p.classList.add('is-visible');}}
    players.forEach(function(container){
        const lessonId=parseInt(container.dataset.lessonId||'0',10), courseId=parseInt(container.dataset.courseId||'0',10), url=container.dataset.videoUrl||''; if(!lessonId||!url)return;
        const storageKey='mathcourse_lesson_'+lessonId+'_time'; let completionSent=false,lastSavedSecond=-1;
        function savedTime(){try{const v=parseFloat(localStorage.getItem(storageKey)||'0');return Number.isFinite(v)&&v>0?v:0;}catch(e){return 0;}}
        function clearSavedTime(){try{localStorage.removeItem(storageKey);}catch(e){}}
        function submitCompletion(){if(completionSent||!window.mathcoursePlayer||!mathcoursePlayer.ajax_url||!mathcoursePlayer.nonce)return;completionSent=true;const fd=new FormData();fd.append('action','mathcourse_complete_lesson');fd.append('nonce',mathcoursePlayer.nonce);fd.append('lesson_id',String(lessonId));fetch(mathcoursePlayer.ajax_url,{method:'POST',credentials:'same-origin',body:fd,keepalive:true}).then(r=>r.json()).then(result=>{if(!result||!result.success)throw new Error('progress rejected');clearSavedTime();const d=result.data||{};updateProgressUI(d.progress||null,lessonId);showCompletionPanel();}).catch(e=>{completionSent=false;console.warn('MathCourse: unable to save lesson completion.',e);});}
        function createPlayer(){
            let invertEnabled=false;
            const art=new Artplayer({
                container:container,url:url,type:'m3u8',lang:'zh-cn',theme:'#1769e0',volume:0.8,autoplay:false,muted:false,pip:false,
                fullscreen:true,fullscreenWeb:true,playsInline:true,autoOrientation:true,setting:true,playbackRate:false,flip:false,aspectRatio:false,lock:true,
                moreVideoAttr:{'webkit-playsinline':true,playsInline:true,controlsList:'nodownload noplaybackrate',disablePictureInPicture:true},
                settings:[
                    {name:'math-speed',html:'播放速度',selector:speeds.map(speed=>({html:speed+'×',value:speed,default:speed===1})),onSelect:function(item){const speed=Number(item.value);if(Number.isFinite(speed))art.playbackRate=speed;return item.html;}},
                    {name:'math-invert',html:'反色播放',switch:true,onSwitch:function(value){invertEnabled=!!value;container.classList.toggle('is-inverted',invertEnabled);return value;}}
                ],
                customType:{m3u8:function(video,sourceUrl){if(window.Hls&&Hls.isSupported()){const hls=new Hls({enableWorker:true,lowLatencyMode:false});hls.loadSource(sourceUrl);hls.attachMedia(video);art._mathcourseHls=hls;}else video.src=sourceUrl;}},
                controls:[
                    {name:'math-logo',position:'left',index:1,html:'<img class="mc-art-logo" src="'+logoUrl+'" alt="樊老师数学">',tooltip:'樊老师数学'},
                    {name:'skip-back',position:'left',index:2,html:'<button type="button" class="mc-art-skip">↶ 10</button>',tooltip:'后退 10 秒',click:function(){art.currentTime=Math.max(0,art.currentTime-10);}},
                    {name:'skip-forward',position:'left',index:3,html:'<button type="button" class="mc-art-skip">10 ↷</button>',tooltip:'前进 10 秒',click:function(){art.currentTime=Math.min(art.duration||Infinity,art.currentTime+10);}}
                ]
            });
            art.on('ready',function(){const s=savedTime();if(s>0&&art.duration>0&&s<art.duration-5){try{art.currentTime=Math.min(s,art.duration-1);}catch(e){}}else if(s>=art.duration-5)clearSavedTime();});
            art.on('timeupdate',function(){const t=Number(art.currentTime||0),d=Number(art.duration||0);if(!Number.isFinite(t)||t<=0||(d>0&&t>=d-.5))return;const v=Math.floor(t*10)/10;if(Math.floor(v)===lastSavedSecond)return;lastSavedSecond=Math.floor(v);try{localStorage.setItem(storageKey,String(v));}catch(e){}});
            art.on('pause',function(){const t=Number(art.currentTime||0);if(Number.isFinite(t)&&t>0)try{localStorage.setItem(storageKey,String(Math.floor(t*10)/10));}catch(e){}});
            art.on('ended',submitCompletion); container._mathcourseArt=art;
        }
        if(window.Hls)createPlayer();else{const wait=setInterval(function(){if(window.Hls){clearInterval(wait);createPlayer();}},50);setTimeout(()=>clearInterval(wait),5000);}
    });
});