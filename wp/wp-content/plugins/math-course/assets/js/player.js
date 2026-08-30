document.addEventListener('DOMContentLoaded', function () {
    const players = document.querySelectorAll('video.mathcourse-player, video.video-js[data-lesson-id]');
    players.forEach(function (element) {
        const lessonId=parseInt(element.dataset.lessonId||'0',10), courseId=parseInt(element.dataset.courseId||'0',10);
        if(!lessonId)return;
        const storageKey='mathcourse_lesson_'+lessonId+'_time'; let player=element,completionSent=false,restorePending=true;
        if(window.videojs&&element.classList.contains('video-js')){try{player=window.videojs(element);}catch(e){player=element;}}
        function val(name){return typeof player[name]==='function'?Number(player[name]()):Number(player[name]);}
        function on(target,event,cb){if(target&&typeof target.on==='function')target.on(event,cb);else if(target&&target.addEventListener)target.addEventListener(event,cb);}
        function setTime(t){if(typeof player.currentTime==='function')player.currentTime(t);else player.currentTime=t;}
        function restore(){if(!restorePending)return;const saved=localStorage.getItem(storageKey),duration=val('duration'),time=saved?parseInt(saved,10):0;if(!saved||!Number.isFinite(duration)||duration<=0||!Number.isFinite(time)||time<=0)return;if(time>=duration-5){localStorage.removeItem(storageKey);restorePending=false;return;}try{setTime(time);restorePending=false;}catch(e){}}
        ['loadedmetadata','durationchange','canplay'].forEach(function(e){on(player,e,restore);});
        on(player,'timeupdate',function(){const t=val('currentTime'),d=val('duration');if(t>0&&Number.isFinite(t))localStorage.setItem(storageKey,String(Math.floor(t)));if(!completionSent&&Number.isFinite(d)&&d>0&&t>=d-0.5)submitCompletion();});
        on(player,'ended',submitCompletion);
        function submitCompletion(){if(completionSent||!courseId||!window.mathcoursePlayer||!mathcoursePlayer.ajax_url||!mathcoursePlayer.nonce)return;completionSent=true;const fd=new FormData();fd.append('action','mathcourse_complete_lesson');fd.append('nonce',mathcoursePlayer.nonce);fd.append('lesson_id',String(lessonId));fd.append('course_id',String(courseId));fetch(mathcoursePlayer.ajax_url,{method:'POST',credentials:'same-origin',body:fd}).then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();}).then(function(result){if(!result||!result.success)throw new Error('progress rejected');localStorage.removeItem(storageKey);document.dispatchEvent(new CustomEvent('mathcourse_lesson_complete',{detail:{lessonId:lessonId,courseId:courseId,progress:result.data&&result.data.progress?result.data.progress:null}}));document.dispatchEvent(new CustomEvent('mathcourse_progress_updated',{detail:{lessonId:lessonId,courseId:courseId,progress:result.data&&result.data.progress?result.data.progress:null}}));}).catch(function(error){completionSent=false;console.warn('MathCourse: unable to save lesson completion.',error);});}
    });
});
