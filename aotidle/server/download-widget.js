(function(){
  'use strict';
  var endpoint='https://asylum-games.fr/aotidle/release.php', url='https://asylum-games.fr/aotidle/telecharger.php';
  document.querySelectorAll('[data-aot-download]').forEach(function(widget){
    var link=widget.querySelector('a'),label=widget.querySelector('[data-aot-version]'),notice=widget.querySelector('[data-aot-notice]'),release=null,last=0,busy=false;
    link.href=url;
    function check(){
      if(busy||Date.now()-last<60000)return;busy=true;last=Date.now();
      var ctrl=typeof AbortController!=='undefined'?new AbortController():null;
      var timer=setTimeout(function(){if(ctrl)ctrl.abort();},8000);
      var options={cache:'no-store',credentials:'omit',redirect:'error'};if(ctrl)options.signal=ctrl.signal;
      fetch(endpoint+'?t='+Date.now(),options).then(function(r){if(!r.ok)throw new Error();return r.json();}).then(function(data){
        if(!data||!Number.isSafeInteger(data.versionCode)||data.versionCode<1||data.packageName!=='com.n1live.aotidl3'||data.downloadUrl!==url||typeof data.versionName!=='string'||!Number.isFinite(data.size)||data.size<=0)throw new Error();
        release=data;label.textContent='Version '+data.versionName+' · '+(data.size/1048576).toFixed(1)+' Mo · Android 7+';
        var previous=0;try{previous=Number(localStorage.getItem('aot-last-download-code'))||0;}catch(e){}
        notice.textContent=previous&&data.versionCode>previous?'Une version plus récente que votre dernier téléchargement sur ce navigateur est disponible.':'';
      }).catch(function(){label.textContent='Jeu Android · Version actuellement publiée';}).then(function(){clearTimeout(timer);busy=false;});
    }
    link.addEventListener('click',function(){if(release){try{localStorage.setItem('aot-last-download-code',String(release.versionCode));}catch(e){}notice.textContent='';}});
    check();setInterval(function(){if(!document.hidden)check();},15*60*1000);document.addEventListener('visibilitychange',function(){if(!document.hidden)check();});
  });
})();
