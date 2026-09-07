(function(){
 'use strict';
 const config=window.GP_CONFIG;if(!config||!document.getElementById('gpRoot'))return;
 const $=id=>document.getElementById(id),catalog=config.catalog,cat=Object.fromEntries(catalog.map(v=>[v.id,v])),bases=catalog.filter(v=>!v.parents.length),hybrids=catalog.filter(v=>v.parents.length);
 let state=null,selected=0,busy=false,serverOffset=0,pending=null,studio=null,alive=true,lightOn=true;
 const now=()=>Date.now()/1000+serverOffset,fmt=n=>Number(n).toLocaleString('fr-FR'),esc=t=>String(t).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const img=v=>config.assetBase+'thumbs/'+v.id+'-bud.webp';
 const TIER_COST=[60,110,180,280,400],TIER_MASTERY=[1,2,3,5,8],SAVE_VERSION=3;
 const tierOf=v=>Math.max(0,Math.min(4,v.tier|0));
 const recipe=(a,b)=>a!==b?cat[[a,b].sort().join('--')]:null;
 const sfx=name=>{try{window.GPSound&&window.GPSound.play(name);}catch{}};
 const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;
 const buzz=pattern=>{try{if(navigator.vibrate&&!reduced)navigator.vibrate(pattern);}catch{}};
 function toast(text,kind=''){const host=$('gpToasts');if(!host)return;
  const el=document.createElement('div');el.className='gp-toast'+(kind?' '+kind:'');el.textContent=text;host.append(el);
  while(host.children.length>4)host.firstChild.remove();
  setTimeout(()=>{el.classList.add('gp-toast-out');setTimeout(()=>el.remove(),450);},2800);}
 /* Le moteur audio est chargé à la demande : aucune modification du gabarit de page n'est nécessaire. */
 (function loadSound(){if(window.GPSound)return;const el=document.createElement('script');el.src=config.assetBase+'sfx.js';el.async=true;
  el.onload=()=>{syncSoundButton();window.GPSound.unlock&&document.addEventListener('pointerdown',unlockAudio,{once:true});};document.head.append(el);})();
 function unlockAudio(){try{window.GPSound&&window.GPSound.unlock();}catch{}syncSoundButton();}
 function syncSoundButton(){const b=$('gpSound');if(!b||!window.GPSound)return;const on=window.GPSound.enabled();
  b.textContent=on?'Son activé':'Son coupé';b.setAttribute('aria-pressed',String(on));}
 function message(text,error=false,retry=false){const el=$('gpMessage');el.classList.toggle('error',error);el.replaceChildren(document.createTextNode(text));if(retry){const btn=document.createElement('button');btn.textContent='Réessayer';btn.onclick=()=>pending?request(pending.action,pending.params,pending.id,pending.revision):load();el.append(btn);}}
 function thumb(v){return '<div class="gp-thumb" style="--bud:'+esc(v.bud)+'"><img data-id="'+esc(v.id)+'" src="'+esc(img(v))+'" alt="Bud 3D de '+esc(v.name)+'" loading="lazy"><span class="gp-rarity t'+tierOf(v)+'">'+esc(v.rarity.toUpperCase())+'</span></div>';}
 function imageFallbacks(){document.querySelectorAll('.gp img').forEach(im=>{const swap=()=>{const fallback=document.createElement('span');fallback.className='gp-fallback-bud';fallback.style.setProperty('--bud',cat[im.dataset.id]?.bud||'#799258');im.replaceWith(fallback);};
  im.onerror=swap;if(im.complete&&im.naturalWidth===0)swap();});}
 function duration(t){t=Math.max(0,Math.ceil(t));return t>=60?Math.floor(t/60)+' min '+String(t%60).padStart(2,'0')+' s':t+' s';}
 function progress(p){if(!p.plant||p.plant.started===null)return 0;return Math.max(0,Math.min(1,1-(p.plant.ready-now())/cat[p.plant.id].duration));}
 function phase(p){if(!p.soil)return 'Pot vide';if(!p.plant)return 'Terreau prêt';if(p.plant.started===null)return 'À arroser';const t=progress(p);return t>=1?'À récolter':t<.28?'Germination':t<.65?'Croissance':'Floraison';}
 function setBusy(value){busy=value;if(state)renderCare();else document.querySelectorAll('#gpRoot button,#gpRoot select').forEach(e=>e.disabled=true);document.querySelectorAll('[data-buy],#gpCross,#gpUnlockPot,#gpRefill').forEach(e=>e.disabled=value||!!pending);if(state&&!value){renderLab();renderSupplies();}}
 function requestId(){return crypto.randomUUID?crypto.randomUUID():Date.now().toString(16)+'-'+Math.random().toString(16).slice(2)+'-'+Math.random().toString(16).slice(2);}
 function adopt(data){if(state&&data.state.revision<state.revision)return;state=data.state;serverOffset=data.server_time-Date.now()/1000;selected=Math.min(selected,state.pots.length-1);render();}
 async function request(action,params={},id=null,revision=null){
  if(busy)return;id=id||requestId();revision=revision===null?state?.revision:revision;
  const creditsBefore=state?state.credits:0,discoveredBefore=state?Object.keys(state.discoveries).length:0;
  if(action!=='state')pending={action,params,id,revision};setBusy(true);
  try{
   let data;
   if(config.demo){
    const key='greenstand-plantation-preview-v1';let local;
    try{local=JSON.parse(localStorage.getItem(key)||'null');}catch{}
    // Toute sauvegarde d'aperçu antérieure est reprise, jamais jetée.
    local=local&&Number.isInteger(local.version)&&local.version>=1&&local.version<=SAVE_VERSION?local:GPDemo.initial(catalog);
    if(local.version<SAVE_VERSION){
     if(local.version<2){for(const id of ['diesel','peche','pin','orchidee'])local.seeds[id]=(local.seeds[id]||0)+2;local.version=2;local.accessories=local.accessories||{};}
     if(local.version<3){const legacy=['emeraude','nebuleuse','citron','velours','menthe','ambre','rose','givre','mangue','onyx','diesel','peche','pin','orchidee'];
      for(const v of bases)if(tierOf(v)===0&&!legacy.includes(v.id))local.seeds[v.id]=(local.seeds[v.id]||0)+1;local.version=3;}
     localStorage.setItem(key,JSON.stringify(local));
    }
    if(action!=='state'){
     if(!local.seen.includes(id)){if(local.revision!==revision){adopt({state:local,server_time:Date.now()/1000});pending=null;throw Error('L’aperçu a changé dans un autre onglet. Réessaie.');}local=GPDemo.apply(local,action,params,Math.floor(Date.now()/1000),catalog);local.seen.push(id);local.seen=local.seen.slice(-24);}
     localStorage.setItem(key,JSON.stringify(local));
    }data={ok:true,state:local,server_time:Date.now()/1000};
   }else{
    const fd=new FormData();Object.entries({action,csrf:config.csrf,request_id:id,revision,...params}).forEach(([k,v])=>fd.append(k,String(v??'')));
    const response=await fetch(config.endpoint,{method:'POST',body:fd,credentials:'same-origin',signal:AbortSignal.timeout(15000)});
    data=await response.json();
    if(!response.ok||!data.ok){if(data.state)adopt(data);pending=null;throw Error(data.error||'La plantation est indisponible.');}
   }
   pending=null;
   if(action==='harvest'){sfx('harvest');buzz([12,45,20]);if(studio){studio.effect('harvest',Number(params.pot??selected));await new Promise(resolve=>setTimeout(resolve,850));}}
   adopt(data);
   if(action!=='state'){
    const names={soil:'Pot préparé. Choisis ta graine.',plant:'Graine semée. Un peu d’eau pour lancer la pousse !',water:'La pousse a commencé.',feed:'Soin ajouté : un bud et 15 points de bonus.',harvest:'Récolte sauvegardée ! Tes buds et tes graines sont dans la collection.',cross:'Nouvelle graine créée ! Tu peux maintenant la semer.',buy:'Matériel ajouté à ta réserve.',refill:'Arrosoir rempli.',pot:'Un nouvel emplacement est disponible.',accessory:'Accessoire installé dans ta serre.'};message(names[action]||'Sauvegardé.');
    if(action!=='harvest'){studio?.effect(action,Number(params.pot??selected));sfx(action);buzz(action==='cross'?[10,30,10,30,14]:8);}
    const gain=state.credits-creditsBefore;
    if(gain>0)toast('+'+fmt(gain)+' points de serre','gain');else if(gain<0)toast(fmt(gain)+' points de serre');
    if(Object.keys(state.discoveries).length>discoveredBefore){sfx('discovery');toast('Nouvel hybride découvert !','gain');}
   }else if($('gpMessage').textContent.includes('Ouverture'))message(config.demo?'Aperçu jouable · Sauvegarde locale de démonstration, séparée de ton compte.':'Ta serre est prête. Commence par ajouter du terreau dans un pot.');
  }catch(err){if(config.demo)pending=null;sfx('error');message(err.name==='TimeoutError'?'La réponse tarde. Réessaie pour vérifier la sauvegarde.':err.message,true,true);
  }finally{setBusy(false);}
 }
 function load(){if(!busy&&!pending)request('state');}
 let readySeen=null;const baseTitle=document.title;
 function readyPots(){return state?state.pots.map((p,i)=>p.plant&&p.plant.ready!==null&&now()>=p.plant.ready?i:-1).filter(i=>i>=0):[];}
 /* Annonce chaque pot qui vient d'arriver à maturité, même onglet en arrière-plan. */
 function watchReady(){
  if(!state)return;const list=readyPots(),set=new Set(list);
  if(readySeen===null)readySeen=set;
  else{
   for(const i of list)if(!readySeen.has(i)){sfx('ready');buzz([8,40,8]);toast('Pot '+String(i+1).padStart(2,'0')+' : la récolte est prête.','ready');}
   readySeen=set;
  }
  document.title=list.length?'('+list.length+') '+baseTitle:baseTitle;
  const all=$('gpHarvestAll');if(all){all.hidden=list.length<2;all.disabled=busy||!!pending;all.textContent='✂ Tout récolter ('+list.length+')';}
 }
 async function harvestAll(){
  for(const i of readyPots()){if(!alive)break;selected=i;studio?.select(i);renderPots();await request('harvest',{pot:i});}
 }
 function renderPots(){const host=$('gpPots');host.replaceChildren();for(let i=0;i<6;i++){const p=state.pots[i],b=document.createElement('button');b.type='button';b.className=(i===selected?'active ':'')+(p&&progress(p)>=1?'ready':'');b.setAttribute('aria-pressed',String(i===selected));b.disabled=!p;b.innerHTML='Pot '+String(i+1).padStart(2,'0')+'<small>'+esc(p?phase(p):'À débloquer')+'</small><i class="gp-pot-bar" style="--v:'+(p?Math.round(progress(p)*100):0)+'%"></i>';b.onclick=()=>{selected=i;studio?.select(i);renderPots();renderCare();};host.append(b);}}
 function renderCare(){if(!state)return;const p=state.pots[selected],v=p.plant?cat[p.plant.id]:null,t=progress(p),locked=busy||!!pending;
  $('gpPotNumber').textContent='POT '+String(selected+1).padStart(2,'0');$('gpPlantName').textContent=v?v.name:'Un nouveau départ.';$('gpStage').textContent=phase(p);$('gpProgress').style.width=t*100+'%';
  $('gpNext').textContent=!p.soil?'Ajoute du terreau pour préparer ce pot.':!v?'Choisis une graine et sème-la.':p.plant.started===null?'Arrose une fois pour lancer son cycle de jeu.':t>=1?'Elle est prête. Prends les ciseaux pour récupérer tes buds et deux graines.':'Ta plante pousse tranquillement. Un soin bonus peut améliorer cette récolte.';
  $('gpTime').textContent=v&&p.plant.started!==null?(t>=1?'Récolte disponible':duration(p.plant.ready-now())):'En attente de tes soins';$('gpQuality').textContent=p.plant?.fed?'✦ Récolte étoilée':'';
  $('gpSoilCount').textContent=state.soil;$('gpWaterCount').textContent=state.water+'/6';$('gpFeedCount').textContent=state.feed;
  const rules={soil:!p.soil&&!p.plant&&state.soil>0,plant:p.soil&&!p.plant&&(state.seeds[$('gpSeedSelect').value]||0)>0,water:!!p.plant&&p.plant.started===null&&state.water>0,feed:!!p.plant&&p.plant.started!==null&&t<1&&!p.plant.fed&&state.feed>0};
  document.querySelectorAll('#gpTools [data-action]').forEach(b=>b.disabled=locked||!rules[b.dataset.action]);$('gpHarvest').disabled=locked||!v||t<1;$('gpSeedSelect').disabled=locked||!!p.plant;
 }
 function renderSeeds(){const select=$('gpSeedSelect'),old=select.value;select.replaceChildren();catalog.filter(v=>(state.seeds[v.id]||0)>0).forEach(v=>{const o=document.createElement('option');o.value=v.id;o.textContent=v.name+' · '+state.seeds[v.id]+' graine(s)';select.append(o);});if([...select.options].some(o=>o.value===old))select.value=old;
  $('gpSeedGrid').innerHTML=bases.map(v=>'<article class="gp-variety" data-variety="'+v.id+'">'+thumb(v)+'<h3>'+esc(v.name)+'</h3><p>'+duration(v.duration)+' · +'+v.reward+' pts<br>'+((state.harvests[v.id]||0)?'✓ Parent étudié':'À récolter pour le laboratoire')+'</p><footer><span>'+fmt(state.seeds[v.id]||0)+' graines</span><button type="button" data-buy="'+v.id+'">+'+1+' · '+v.price+' pts</button></footer></article>').join('');
 }
 function selectedHybrid(){const a=$('gpParentA').value,b=$('gpParentB').value;return a!==b?cat[[a,b].sort().join('--')]:null;}
 function renderLab(){
  const a=$('gpParentA').value,b=$('gpParentB').value,v=selectedHybrid();
  const tier=v?tierOf(v):0,cost=TIER_COST[tier],mastery=TIER_MASTERY[tier];
  $('gpHybridResult').innerHTML=v
   ?'<img data-id="'+esc(v.id)+'" src="'+esc(img(v))+'" alt=""><span>'+esc(v.name)+'<small><b class="gp-tag t'+tier+'">'+esc(v.rarity)+'</b> '+duration(v.duration)+' · '+v.reward+' pts</small></span>'
   :(a===b?'Deux parents différents':'Aucune recette connue');
  const studied=(state.harvests[a]||0)>=mastery&&(state.harvests[b]||0)>=mastery,
   owned=(state.seeds[a]||0)>0&&(state.seeds[b]||0)>0;
  $('gpCross').disabled=busy||!!pending||!v||!studied||!owned||state.credits<cost;
  $('gpCross').textContent=v?'Créer la graine · '+cost+' pts':'Créer la graine';
  $('gpLabHint').textContent=a===b?'Choisis deux fondatrices différentes.'
   :!v?'Ces deux fondatrices ne donnent aucune recette. Sur les 1 225 associations possibles, 500 ont une recette : essaie une autre paire.'
   :!studied?'Recette '+v.rarity.toLowerCase()+' : récolte '+mastery+' fois chaque parent pour la maîtriser ('+(state.harvests[a]||0)+'/'+mastery+' et '+(state.harvests[b]||0)+'/'+mastery+').'
   :!owned?'Il te faut une graine disponible de chaque parent.'
   :state.credits<cost?'Il te faut '+fmt(cost)+' points de serre pour une recette '+v.rarity.toLowerCase()+'.'
   :'Coût : 1 graine de chaque parent + '+fmt(cost)+' points. Résultat : 1 graine '+v.name+' ('+v.rarity.toLowerCase()+'). Les hybrides se cultivent et redonnent leurs propres graines.';
 }
 function renderCollection(){const collected=catalog.filter(v=>state.buds[v.id]>0);$('gpCollectionGrid').innerHTML=collected.length?collected.map(v=>'<article data-variety="'+v.id+'" class="gp-variety">'+thumb(v)+'<h3>'+esc(v.name)+'</h3><p>'+fmt(state.buds[v.id])+' buds · '+fmt(state.harvests[v.id])+' récolte(s)</p><footer><span>'+fmt(state.seeds[v.id]||0)+' graines</span><button data-inspect="'+v.id+'" type="button">Semer</button></footer></article>').join(''):'<div class="gp-empty">Ta première récolte trouvera sa place ici. Prépare un pot, sème et arrose pour commencer.</div>';
  $('gpHistory').replaceChildren();for(const h of state.history){const li=document.createElement('li'),time=document.createElement('time'),text=document.createElement('span');time.textContent=new Date(h.at*1000).toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});text.textContent=h.text;li.append(time,text);$('gpHistory').append(li);}
 }
 function renderSupplies(){document.querySelectorAll('[data-accessory]').forEach(b=>{const owned=!!state.accessories?.[b.dataset.accessory],price={fan:120,humidifier:140,loupe:100}[b.dataset.accessory];b.textContent=owned?'✓ Installé':'Installer · '+price+' pts';b.disabled=owned||busy||!!pending||state.credits<price;});document.querySelectorAll('[data-buy]').forEach(b=>{const cost=b.dataset.buy==='soil'?15:b.dataset.buy==='feed'?20:cat[b.dataset.buy]?.price;b.disabled=busy||!!pending||state.credits<cost;});const n=state.pots.length,cost=150*(n-3);$('gpUnlockPot').textContent=n>=6?'Serre complète':('Pot '+(n+1)+' · '+cost+' pts');$('gpUnlockPot').disabled=busy||!!pending||n>=6||state.credits<cost;$('gpRefill').disabled=busy||!!pending||state.water>=6;}
 function render(){if(!state)return;document.querySelectorAll('.gp-section-tabs button,[data-rarity],#gpParentA,#gpParentB,#gpRotate,#gpLight,#gpInspect,#gpSound,#gpPhoto').forEach(e=>e.disabled=false);
  $('gpScore').textContent=fmt(catalog.filter(v=>(state.harvests[v.id]||0)>0).length*100+hybrids.filter(v=>state.discoveries[v.id]).length*250+Math.min(100,Object.values(state.harvests).reduce((a,b)=>a+Number(b),0)));$('gpCredits').textContent=fmt(state.credits);$('gpHarvestTotal').textContent=fmt(state.total);$('gpDiscovered').textContent=Object.keys(state.discoveries).length+' / '+hybrids.length;$('gpBudTotal').textContent=fmt(Object.values(state.buds).reduce((a,b)=>a+b,0));$('gpSceneCount').textContent=state.pots.length+' POTS';renderSeeds();renderPots();renderCare();
  $('gpHybridGrid').innerHTML=hybrids.map(v=>'<article data-variety="'+v.id+'" class="gp-variety '+(state.discoveries[v.id]?'':'locked')+'">'+thumb(v)+'<h3>'+esc(v.name)+'</h3><p>'+v.parents.map(id=>esc(cat[id].name)).join(' × ')+'</p><footer><span>'+(state.discoveries[v.id]?'✓ Découvert':'À découvrir')+'</span><span>'+fmt(state.seeds[v.id]||0)+' gr.</span></footer></article>').join('');renderLab();renderCollection();renderSupplies();imageFallbacks();applyFilter();watchReady();studio?.sync(state,cat,now());studio?.select(selected);
  try{window.GPSound&&window.GPSound.scene(state,lightOn);}catch{}
 }
 for(const id of ['gpParentA','gpParentB']){bases.forEach(v=>{const o=document.createElement('option');o.value=v.id;o.textContent=v.name;$(id).append(o);});$(id).onchange=()=>{if(state)renderLab();};}$('gpParentB').selectedIndex=1;
 let rarityFilter='';
 function applyFilter(){const q=$('gpSearch').value.trim().toLocaleLowerCase('fr'),onlyFound=$('gpOnlyFound')?.checked;
  document.querySelectorAll('[data-variety]').forEach(el=>{const v=cat[el.dataset.variety];
   const text=!!q&&!(v.name+' '+v.rarity+' '+v.parents.map(p=>cat[p].name).join(' ')).toLocaleLowerCase('fr').includes(q);
   const rarity=rarityFilter!==''&&String(tierOf(v))!==rarityFilter;
   el.hidden=text||rarity||(onlyFound&&el.classList.contains('locked'));});
  const label=$('gpFilterCount');
  if(label){const shown=[...document.querySelectorAll('#gpHybridGrid [data-variety]')].filter(e=>!e.hidden).length;
   label.textContent=fmt(shown)+' recette'+(shown>1?'s':'')+' affichée'+(shown>1?'s':'');}}
 $('gpSearch').oninput=applyFilter;
 document.querySelectorAll('[data-rarity]').forEach(b=>{b.onclick=()=>{
  rarityFilter=b.dataset.rarity;sfx('tab');
  document.querySelectorAll('[data-rarity]').forEach(o=>{o.classList.toggle('active',o===b);o.setAttribute('aria-pressed',String(o===b));});
  applyFilter();};});
 if($('gpOnlyFound'))$('gpOnlyFound').onchange=()=>{applyFilter();sfx('ui');};
 $('gpInspect').onclick=()=>{if(!studio||!state)return;const v=cat[state.pots[selected].plant?.id||$('gpSeedSelect').value]||bases[0];const on=studio.inspect(v);$('gpInspect').textContent=on?'Revenir à la serre':'Voir le bud';};
 $('gpSeedSelect').onchange=renderCare;
 $('gpRoot').addEventListener('pointerdown',unlockAudio,{once:true});
 $('gpRoot').addEventListener('click',e=>{const b=e.target.closest('button');if(!b||b.disabled)return;if(b.dataset.action)request(b.dataset.action,{pot:selected,seed:$('gpSeedSelect').value});if(b.dataset.buy)request('buy',{item:b.dataset.buy});if(b.dataset.accessory)request('accessory',{item:b.dataset.accessory});if(b.dataset.inspect){$('gpSeedSelect').value=b.dataset.inspect;renderCare();$('gpScene').scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'center'});}if(b.dataset.tab){sfx('tab');document.querySelectorAll('.gp-section').forEach(s=>s.hidden=s.id!=='gp-'+b.dataset.tab);document.querySelectorAll('[data-tab]').forEach(t=>{t.classList.toggle('active',t===b);t.setAttribute('aria-pressed',String(t===b));});}});
 $('gpHarvest').onclick=()=>request('harvest',{pot:selected});$('gpRefill').onclick=()=>request('refill');$('gpUnlockPot').onclick=()=>request('pot');$('gpCross').onclick=()=>request('cross',{a:$('gpParentA').value,b:$('gpParentB').value});
 if($('gpHarvestAll'))$('gpHarvestAll').onclick=harvestAll;
 if($('gpSound'))$('gpSound').onclick=()=>{unlockAudio();if(window.GPSound){window.GPSound.toggle();syncSoundButton();}else toast('Le son n’est pas disponible sur cet appareil.');};
 if($('gpPhoto'))$('gpPhoto').onclick=async()=>{
  if(!studio){toast('La 3D n’est pas disponible ici.');return;}
  sfx('ui');const url=await studio.snapshot();const a=document.createElement('a');
  a.href=url;a.download='serre-'+new Date().toISOString().slice(0,19).replace(/[:T]/g,'-')+'.png';a.click();toast('Photo de la serre enregistrée.','gain');};
 /* Raccourcis clavier : 1-6 pots, T terreau, S semer, A arroser, F soin, R récolte, I loupe, M son. */
 document.addEventListener('keydown',e=>{
  if(e.altKey||e.ctrlKey||e.metaKey||!document.getElementById('gpRoot'))return;
  const tag=(e.target.tagName||'').toLowerCase();if(tag==='input'||tag==='select'||tag==='textarea'||e.target.isContentEditable)return;
  const key=e.key.toLowerCase();
  if(key>='1'&&key<='6'){const i=Number(key)-1;if(state&&state.pots[i]){selected=i;studio?.select(i);renderPots();renderCare();sfx('ui');e.preventDefault();}return;}
  const tools={t:'soil',s:'plant',a:'water',f:'feed'};
  const click=el=>{if(el&&!el.disabled&&!el.hidden){el.click();e.preventDefault();}};
  if(tools[key])return click(document.querySelector('#gpTools [data-action="'+tools[key]+'"]'));
  if(key==='r')return click($('gpHarvest'));
  if(key==='i')return click($('gpInspect'));
  if(key==='m')return click($('gpSound'));
 });
 try{if(!window.GPModels)throw Error('WebGL absent');studio=GPModels.studio($('gpCanvas'),i=>{selected=i;studio.select(i);renderPots();renderCare();});}catch(e){$('gpCanvas').innerHTML='<div class="gp-fallback-scene"><div><strong>Ta serre reste ouverte.</strong>La 3D n’est pas disponible sur cet appareil. Les pots, les soins et les récoltes fonctionnent avec les boutons ci-dessous.</div></div>';}
 $('gpCanvas').addEventListener('gp-context-lost',()=>message('L’affichage 3D a été interrompu. Tes cultures sont sauvegardées ; recharge pour le rétablir.',true));
 $('gpRotate').onclick=()=>{if(studio){const on=studio.rotate();$('gpRotate').setAttribute('aria-pressed',String(on));}};$('gpLight').onclick=()=>{if(studio){lightOn=studio.light();$('gpLight').textContent=lightOn?'Lampe allumée':'Lampe tamisée';$('gpLight').setAttribute('aria-pressed',String(lightOn));sfx('ui');try{window.GPSound&&window.GPSound.scene(state,lightOn);}catch{}}};
 const ticker=setInterval(()=>{if(state){watchReady();if(!document.hidden){renderCare();renderPots();studio?.sync(state,cat,now());}}},1000),poll=setInterval(()=>{if(!document.hidden)load();},20000);
 window.addEventListener('pagehide',()=>{alive=false;clearInterval(ticker);clearInterval(poll);studio?.dispose();},{once:true});document.addEventListener('visibilitychange',()=>{if(!document.hidden&&alive)load();});load();
})();
