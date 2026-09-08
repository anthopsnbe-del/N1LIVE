(function(){
  'use strict';
  var atlas=new Image(), environments=new Image(), titans=new Image();
  atlas.src='atlas.png'; environments.src='environments.png'; titans.src='titans.png';
  var enemies={};['titan','anormal','colossal','blinde','feminin','bestial'].forEach(function(k){enemies[k]=new Image();enemies[k].src='enemies/'+k+'.png';});
  var prefs={sound:true,motion:!matchMedia('(prefers-reduced-motion: reduce)').matches};
  try{Object.assign(prefs,JSON.parse(localStorage.getItem('aot-v3-settings')||'{}'));}catch(e){}
  var audio=null,lastSound=0, particles=[],lastHit=0;
  function sound(kind){
    if(!prefs.sound||!audio||document.hidden)return;
    var t=audio.currentTime;if(kind==='hit'&&t-lastSound<0.12)return;lastSound=t;
    var osc=audio.createOscillator(),gain=audio.createGain();osc.connect(gain);gain.connect(audio.destination);
    var freq={hit:160,skill:680,heal:440,win:520,loot:880,ui:350}[kind]||200;
    osc.type=kind==='hit'?'sawtooth':'sine';osc.frequency.setValueAtTime(freq,t);osc.frequency.exponentialRampToValueAtTime(kind==='hit'?40:freq*1.6,t+.15);
    gain.gain.setValueAtTime(kind==='hit'?.025:.07,t);gain.gain.exponentialRampToValueAtTime(.001,t+.25);osc.start(t);osc.stop(t+.26);
  }
  document.addEventListener('pointerdown',function(){if(!audio){try{audio=new(window.AudioContext||window.webkitAudioContext)();}catch(e){}}if(audio&&audio.state==='suspended')audio.resume();},{passive:true});
  function crop(ctx,img,n,cols,rows,x,y,w,h){if(!img.complete||!img.naturalWidth)return;var sw=img.naturalWidth/cols,sh=img.naturalHeight/rows;ctx.drawImage(img,(n%cols)*sw,Math.floor(n/cols)*sh,sw,sh,x,y,w,h);}
  window.Sprites.draw=function(canvas,shape,pal,opts){
    var ctx=canvas.getContext('2d'),w=canvas.width,h=canvas.height,hero=canvas.id==='hero-canvas';ctx.clearRect(0,0,w,h);ctx.imageSmoothingEnabled=true;
    var n={titan:0,anormal:1,rampant:1,colossal:2,blinde:3,feminin:4,bestial:5}[shape];
    ctx.save();var bob=prefs.motion?(opts.bob||0):0;
    if(hero||shape==='soldat'){canvas.style.mixBlendMode='normal';Art.draw(ctx,'heroes',hero&&window.__game?window.__game.state.portrait:19,0,bob,w,h);}
    else{canvas.style.mixBlendMode='screen';if(opts.flash>.1)ctx.globalAlpha=1-opts.flash*.3;(function(){var im=enemies[shape==='rampant'?'anormal':shape]||enemies.titan;if(im.naturalWidth){var scale=Math.min(w/im.naturalWidth,h/im.naturalHeight);ctx.drawImage(im,(w-im.naturalWidth*scale)/2,bob,im.naturalWidth*scale,im.naturalHeight*scale);}})();}
    ctx.restore();
  };
  function hit(crit){sound(crit?'skill':'hit');if(!prefs.motion)return;lastHit=performance.now();var arena=document.getElementById('arena');arena.classList.remove('impact');void arena.offsetWidth;arena.classList.add('impact');for(var i=0;i<(crit?18:7);i++)particles.push({x:150,y:140,vx:(Math.random()-.5)*9,vy:(Math.random()-.6)*8,life:1,crit:crit});if(particles.length>80)particles.splice(0,particles.length-80);}
  window.Remaster={hit:hit,sound:sound};
  document.addEventListener('DOMContentLoaded',function(){
    document.body.classList.toggle('reduced',!prefs.motion);
    var arena=document.getElementById('arena'),bg=document.createElement('canvas'),fx=document.createElement('canvas');bg.id='scene-art';fx.id='battle-fx';bg.width=fx.width=300;bg.height=fx.height=310;arena.prepend(bg);arena.append(fx);
    var sfx=document.getElementById('sfx-option'),motion=document.getElementById('motion-option');sfx.checked=prefs.sound;motion.checked=!prefs.motion;
    function settings(){prefs.sound=sfx.checked;prefs.motion=!motion.checked;document.body.classList.toggle('reduced',!prefs.motion);try{localStorage.setItem('aot-v3-settings',JSON.stringify(prefs));}catch(e){}}
    sfx.onchange=motion.onchange=settings;
    var portraits=document.getElementById('portraits');Art.names.forEach(function(name,i){var b=document.createElement('button');b.innerHTML=Art.icon('heroes',i)+'<span>'+name+'</span>';b.onclick=function(){window.__game.setPortrait(i);if(window.Social)window.Social.setPortrait(i);sound('ui');updatePortrait();};b.dataset.portrait=i;portraits.append(b);});
    function updatePortrait(){if(!window.__game)return;portraits.querySelectorAll('button').forEach(function(b){b.classList.toggle('selected',+b.dataset.portrait===window.__game.state.portrait);});}
    var sheet=document.createElement('div');sheet.id='campaign-sheet';sheet.className='hidden';sheet.innerHTML='<div class="campaign-panel"><header><div><small>ARCHIVES DES EXPÉDITIONS</small><h2>Carte de campagne</h2></div><button id="map-close" aria-label="Fermer">×</button></header><p id="campaign-status"></p><label>Arc <select id="arc-select"></select></label><p id="arc-summary"></p><div id="chapter-grid"></div><p class="hint">Les étapes secondaires sont des missions originales de fan. Les jalons suivent les grands événements de la série. Les étapes futures contiennent des spoilers.</p></div>';document.body.append(sheet);
    var select=document.getElementById('arc-select');window.Content.ARCS.forEach(function(a,i){var op=document.createElement('option');op.value=i;op.textContent=(i+1)+' · '+a[0];select.append(op);});
    function map(){var s=window.__game.state,arc=+select.value;document.getElementById('campaign-status').textContent='Position : '+s.chapter+' / 1 000 · Terminé : '+s.record.chapter+' · Débloqué : '+s.bestChapter;document.getElementById('arc-summary').textContent=window.Content.ARCS[arc][1]+' · '+window.Content.ARCS[arc][3];var grid=document.getElementById('chapter-grid');grid.textContent='';for(var j=0;j<100;j++){var n=arc*100+j+1,b=document.createElement('button');b.textContent=n;b.disabled=n>s.bestChapter;b.className=n===s.chapter?'current':n<=s.record.chapter?'complete':'';b.title=window.Content.CHAPTERS[n-1].brief;b.setAttribute('aria-label','Chapitre '+n+(b.disabled?' verrouillé':n===s.chapter?' actuel':n<=s.record.chapter?' terminé':''));b.dataset.chapter=n;b.onclick=function(){if(window.__game.travel(+this.dataset.chapter)){sheet.classList.add('hidden');sound('ui');document.querySelector('[data-tab="tab-combat"]').click();document.getElementById('map-open').focus();}};grid.append(b);}}
    document.getElementById('map-open').onclick=function(){select.value=Math.floor((window.__game.state.chapter-1)/100);map();sheet.classList.remove('hidden');document.getElementById('map-close').focus();};
    document.getElementById('map-close').onclick=function(){sheet.classList.add('hidden');document.getElementById('map-open').focus();};select.onchange=map;
    sheet.setAttribute('role','dialog');sheet.setAttribute('aria-modal','true');sheet.setAttribute('aria-label','Carte de campagne');
    document.addEventListener('keydown',function(e){if(e.key==='Escape')sheet.classList.add('hidden');if(e.key==='Tab'&&!sheet.classList.contains('hidden')){var nodes=sheet.querySelectorAll('button:not(:disabled),select'),first=nodes[0],last=nodes[nodes.length-1];if(e.shiftKey&&document.activeElement===first){last.focus();e.preventDefault();}else if(!e.shiftKey&&document.activeElement===last){first.focus();e.preventDefault();}}});
    var lastArc=-1,lastChapter=0,lastFrame=0;
    function frame(now){requestAnimationFrame(frame);if(document.hidden||now-lastFrame<33)return;var dt=Math.min(2,(now-lastFrame)/33);lastFrame=now;if(!window.__game)return;var s=window.__game.state,c=window.Content.CHAPTERS[s.chapter-1];if(!c)return;
      if(c.arc!==lastArc&&environments.complete){lastArc=c.arc;var n=c.arc===1||c.arc===3||c.arc===4?1:c.arc===7||c.arc===8?2:c.arc===9?3:0;crop(bg.getContext('2d'),environments,n,2,2,0,0,300,310);}
      if(lastChapter!==s.chapter){lastChapter=s.chapter;document.getElementById('mission-brief').textContent=c.brief;updatePortrait();}
      var ctx=fx.getContext('2d');ctx.clearRect(0,0,300,310);if(!prefs.motion)return;
      if(now-lastHit<160){ctx.strokeStyle='#e5ffff';ctx.lineWidth=3;ctx.shadowColor='#98ffe4';ctx.shadowBlur=14;ctx.beginPath();ctx.moveTo(65,205);ctx.lineTo(225,85);ctx.stroke();ctx.shadowBlur=0;}
      particles=particles.filter(function(p){p.life-=.06*dt;p.x+=p.vx*dt;p.y+=p.vy*dt;p.vy+=.2*dt;ctx.globalAlpha=Math.max(0,p.life);ctx.fillStyle=p.crit?'#ffcd74':'#bdfce6';ctx.fillRect(p.x,p.y,3,3);return p.life>0;});ctx.globalAlpha=1;
    }requestAnimationFrame(frame);
  });
})();
