document.addEventListener('DOMContentLoaded',function(){
 if(!window.videojs)return;
 var mobileQuery=window.matchMedia&&window.matchMedia('(max-width: 767px)');
 document.querySelectorAll('video.mathcourse-player.video-js').forEach(function(el){
  var p=videojs.getPlayer(el.id); if(!p)return;
  var isMobile=function(){return mobileQuery?mobileQuery.matches:window.innerWidth<=767;};
  var speeds=[0.5,0.75,1,1.25,1.5,1.75,2];

  function addSkipButton(parent,label,title,delta){
   var b=document.createElement('button');b.type='button';b.className='mc-player-skip-button';b.textContent=label;b.title=title;b.setAttribute('aria-label',title);
   b.onclick=function(e){e.preventDefault();e.stopPropagation();var t=Number(p.currentTime()||0),d=Number(p.duration()||0);p.currentTime(Math.max(0,Math.min(d||Infinity,t+delta)));};
   parent.appendChild(b);
  }

  function buildSpeedControl(bar,compact){
   if(bar.querySelector('.mc-player-speed'))return;
   var full=bar.querySelector('.vjs-fullscreen-control'),speed=document.createElement('div');speed.className='mc-player-speed'+(compact?' mc-player-speed-mobile':'');
   var btn=document.createElement('button');btn.type='button';btn.className='mc-player-speed-button';btn.textContent='1×';btn.title='播放倍速';btn.setAttribute('aria-label','播放倍速');
   var menu=document.createElement('div');menu.className='mc-player-speed-menu';menu.setAttribute('role','menu');
   speeds.forEach(function(rate){var b=document.createElement('button');b.type='button';b.textContent=rate+'×';b.dataset.rate=rate;b.setAttribute('role','menuitem');if(rate===1)b.classList.add('is-active');b.onclick=function(e){e.preventDefault();e.stopPropagation();try{p.playbackRate(rate);}catch(x){}btn.textContent=rate+'×';menu.querySelectorAll('button').forEach(function(x){x.classList.toggle('is-active',x===b);});speed.classList.remove('is-open');};menu.appendChild(b);});
   btn.onclick=function(e){e.preventDefault();e.stopPropagation();speed.classList.toggle('is-open');};speed.appendChild(btn);speed.appendChild(menu);if(full)bar.insertBefore(speed,full);else bar.appendChild(speed);
   speed._mcDocumentClick=function(e){if(!speed.contains(e.target))speed.classList.remove('is-open');};document.addEventListener('click',speed._mcDocumentClick);return speed;
  }

  function setupDesktopControls(){
   if(isMobile())return;try{if(typeof p.playbackRates==='function')p.playbackRates(speeds);}catch(e){}
   var bar=el.parentElement&&el.parentElement.querySelector('.vjs-control-bar');if(!bar||bar.querySelector('.mc-player-skip-controls'))return;
   var time=bar.querySelector('.vjs-current-time'),group=document.createElement('div');group.className='mc-player-skip-controls';group.setAttribute('aria-label','视频快捷控制');addSkipButton(group,'↶ 10秒','后退10秒',-10);addSkipButton(group,'↷ 10秒','快进10秒',10);if(time)bar.insertBefore(group,time);else bar.appendChild(group);buildSpeedControl(bar,false);
  }

  function setupMobileControls(){
   if(!isMobile())return;var bar=el.parentElement&&el.parentElement.querySelector('.vjs-control-bar');if(!bar||bar.querySelector('.mc-player-mobile-actions'))return;
   var group=document.createElement('div');group.className='mc-player-mobile-actions';group.setAttribute('aria-label','视频快捷控制');addSkipButton(group,'↶10','后退10秒',-10);addSkipButton(group,'↷10','快进10秒',10);var full=bar.querySelector('.vjs-fullscreen-control');if(full)bar.insertBefore(group,full);else bar.appendChild(group);buildSpeedControl(bar,true);
  }

  function refreshControls(){if(isMobile())setupMobileControls();else setupDesktopControls();}
  if(typeof p.ready==='function')p.ready(refreshControls);else refreshControls();
  if(mobileQuery){var refresh=function(){window.setTimeout(refreshControls,0);};if(typeof mobileQuery.addEventListener==='function')mobileQuery.addEventListener('change',refresh);else if(typeof mobileQuery.addListener==='function')mobileQuery.addListener(refresh);}

  function enterLandscape(){
   if(!isMobile())return;
   var target=document.fullscreenElement||document.webkitFullscreenElement||el;
   try{if(screen.orientation&&typeof screen.orientation.lock==='function'){var promise=screen.orientation.lock('landscape');if(promise&&promise.catch)promise.catch(function(){});}}catch(e){}
   try{if(target&&target.classList)target.classList.add('mc-landscape-player');}catch(e){}
  }
  function leaveLandscape(){
   try{if(screen.orientation&&typeof screen.orientation.unlock==='function')screen.orientation.unlock();}catch(e){}
   try{el.classList.remove('mc-landscape-player');}catch(e){}
  }
  if(typeof p.on==='function')p.on('fullscreenchange',function(){
   var speed=el.parentElement&&el.parentElement.querySelector('.mc-player-speed');if(speed)speed.classList.remove('is-open');
   if(p.isFullscreen&&p.isFullscreen())enterLandscape();else leaveLandscape();
  });

  function keyboard(e){
   if(isMobile())return;var tag=(e.target&&e.target.tagName||'').toLowerCase();if(tag==='input'||tag==='textarea'||tag==='select'||e.target.isContentEditable)return;if(!p||p.isDisposed())return;
   if(e.key==='ArrowLeft'){e.preventDefault();p.currentTime(Math.max(0,Number(p.currentTime()||0)-10));}else if(e.key==='ArrowRight'){e.preventDefault();var d=Number(p.duration()||0);p.currentTime(Math.min(d||Infinity,Number(p.currentTime()||0)+10));}else if(e.code==='Space'){e.preventDefault();if(p.paused())p.play();else p.pause();}
  }
  document.addEventListener('keydown',keyboard);
 });
});
