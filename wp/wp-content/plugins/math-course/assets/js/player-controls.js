document.addEventListener('DOMContentLoaded',function(){
 if(!window.videojs)return;
 document.querySelectorAll('video.mathcourse-player.video-js').forEach(function(el){
  var p=videojs.getPlayer(el.id); if(!p)return;
  var wrap=el.parentElement; if(!wrap)return; wrap.style.position='relative';
  var speeds=[0.5,0.75,1,1.25,1.5,1.75,2];
  try{if(typeof p.playbackRates==='function')p.playbackRates(speeds);}catch(e){}
  if(!wrap.querySelector('.mc-player-skip-controls')){
   var controls=document.createElement('div'); controls.className='mc-player-skip-controls'; controls.setAttribute('aria-label','视频快捷控制');
   function skip(label,title,delta){var b=document.createElement('button');b.type='button';b.className='mc-player-skip-button';b.textContent=label;b.title=title;b.setAttribute('aria-label',title);b.onclick=function(e){e.preventDefault();e.stopPropagation();var t=Number(p.currentTime()||0),d=Number(p.duration()||0);p.currentTime(Math.max(0,Math.min(d||Infinity,t+delta)));};return b;}
   controls.appendChild(skip('↶ 10秒','后退10秒',-10)); controls.appendChild(skip('↷ 10秒','快进10秒',10)); wrap.appendChild(controls);
  }
  if(!wrap.querySelector('.mc-player-speed')){
   var speed=document.createElement('div'); speed.className='mc-player-speed';
   var btn=document.createElement('button'); btn.type='button'; btn.className='mc-player-speed-button'; btn.textContent='1×'; btn.setAttribute('aria-label','播放倍速');
   var menu=document.createElement('div'); menu.className='mc-player-speed-menu'; menu.setAttribute('role','menu');
   speeds.forEach(function(rate){var b=document.createElement('button');b.type='button';b.textContent=rate+'×';b.dataset.rate=rate;b.setAttribute('role','menuitem');if(rate===1)b.classList.add('is-active');b.onclick=function(e){e.preventDefault();e.stopPropagation();try{p.playbackRate(rate);}catch(x){}btn.textContent=rate+'×';menu.querySelectorAll('button').forEach(function(x){x.classList.toggle('is-active',x===b);});speed.classList.remove('is-open');};menu.appendChild(b);});
   btn.onclick=function(e){e.preventDefault();e.stopPropagation();speed.classList.toggle('is-open');};
   speed.appendChild(btn);speed.appendChild(menu);wrap.appendChild(speed);
   document.addEventListener('click',function(e){if(!speed.contains(e.target))speed.classList.remove('is-open');});
  }
  function keyboard(e){var tag=(e.target&&e.target.tagName||'').toLowerCase();if(tag==='input'||tag==='textarea'||tag==='select'||e.target.isContentEditable)return;if(!p||p.isDisposed())return;if(e.key==='ArrowLeft'){e.preventDefault();p.currentTime(Math.max(0,Number(p.currentTime()||0)-10));}else if(e.key==='ArrowRight'){e.preventDefault();var d=Number(p.duration()||0);p.currentTime(Math.min(d||Infinity,Number(p.currentTime()||0)+10));}else if(e.code==='Space'){e.preventDefault();if(p.paused())p.play();else p.pause();}}
  document.addEventListener('keydown',keyboard);
 });
});