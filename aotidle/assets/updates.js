/* Optional update check. No credentials, automatic installation or forced update. */
(function () {
  'use strict';
  var build=window.AOT_BUILD, busy=false, lastAttempt=0, latest=null, announced=0;
  var interval=15*60*1000, dismissedUntil=0, dismissedCode=0;
  try { var stored=JSON.parse(localStorage.getItem('aot-update-dismissed')||'{}'); dismissedUntil=+stored.until||0; dismissedCode=+stored.code||0; } catch(e){}
  function validRelease(data) {
    if(!data || !Number.isSafeInteger(data.versionCode) || data.versionCode<1 || typeof data.versionName!=='string' || !/^[0-9A-Za-z.+-]{1,32}$/.test(data.versionName) || data.packageName!==build.packageName) return false;
    try { var url=new URL(data.downloadUrl); return url.href===build.downloadUrl && url.protocol==='https:' && !url.username && !url.password; } catch(e){return false;}
  }
  function status(message) { var el=document.getElementById('update-status');if(el)el.textContent=message; }
  function show(force) {
    if(!latest||latest.versionCode<=build.versionCode)return;
    if(!force && (announced===latest.versionCode || (dismissedCode===latest.versionCode&&Date.now()<dismissedUntil)))return;
    var banner=document.getElementById('update-banner');
    document.getElementById('update-message').textContent='Version '+latest.versionName+' disponible';
    document.getElementById('update-notes').textContent=typeof latest.notes==='string'?latest.notes.slice(0,400):'Une nouvelle version du jeu est disponible.';
    banner.classList.remove('hidden');announced=latest.versionCode;
  }
  function check(manual) {
    if(busy || (!manual && Date.now()-lastAttempt<interval))return Promise.resolve();
    lastAttempt=Date.now();busy=true;status('Recherche de mises à jour…');
    var controller=typeof AbortController!=='undefined'?new AbortController():null,timer;
    var options={cache:'no-store',credentials:'omit',redirect:'error'};if(controller)options.signal=controller.signal;
    var timeout=new Promise(function(_,reject){timer=setTimeout(function(){if(controller)controller.abort();reject(new Error('timeout'));},8000);});
    var request=fetch(build.releaseEndpoint+'?t='+Date.now(),options).then(function(r){if(!r.ok)throw new Error('http');return r.text();}).then(function(text){if(text.length>16384)throw new Error('size');var data=JSON.parse(text);if(!validRelease(data))throw new Error('manifest');return data;});
    return Promise.race([request,timeout]).then(function(data){latest=data;if(data.versionCode>build.versionCode){status('Mise à jour '+data.versionName+' disponible.');show(manual);}else{document.getElementById('update-banner').classList.add('hidden');status('Votre version '+build.versionName+' est à jour.');}},function(){status('Vérification indisponible. Vous pouvez continuer à jouer hors ligne.');}).then(function(){clearTimeout(timer);busy=false;});
  }
  document.addEventListener('DOMContentLoaded',function(){
    var section=document.createElement('section');section.className='update-settings';section.innerHTML='<h2>Mises à jour</h2><p>Version installée : <b></b></p><p id="update-status" role="status">Vérification au lancement du jeu.</p><button class="ghost-btn" id="update-check">Vérifier maintenant</button>';
    section.querySelector('b').textContent=build.versionName;document.getElementById('tab-world').append(section);
    var banner=document.createElement('aside');banner.id='update-banner';banner.className='hidden';banner.setAttribute('aria-label','Mise à jour du jeu');banner.innerHTML='<div role="status"><strong id="update-message"></strong><p id="update-notes"></p></div><div class="update-actions"><a id="update-download" class="big-btn">Télécharger la mise à jour</a><button id="update-later" class="ghost-btn">Plus tard</button></div><small>Le navigateur ouvre le téléchargement. Android vous demandera de confirmer l’installation.</small>';
    banner.querySelector('a').href=build.downloadUrl;banner.querySelector('a').rel='noopener';document.body.append(banner);
    document.getElementById('update-check').onclick=function(){check(true);};
    document.getElementById('update-later').onclick=function(){banner.classList.add('hidden');dismissedCode=latest.versionCode;dismissedUntil=Date.now()+24*60*60*1000;try{localStorage.setItem('aot-update-dismissed',JSON.stringify({code:dismissedCode,until:dismissedUntil}));}catch(e){}};
    document.getElementById('update-download').addEventListener('click',function(){if(window.__game)window.__game.save();});
    document.getElementById('splash-start').addEventListener('click',function(){check(false);});
    document.addEventListener('visibilitychange',function(){if(!document.hidden)check(false);});
    window.addEventListener('aot-resume',function(){check(false);});
    window.addEventListener('online',function(){check(false);});
    setInterval(function(){if(!document.hidden)check(false);},interval);
  });
})();
