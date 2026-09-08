(function(){
 'use strict';
 var fight=null,last=0,lastRender=0,ready=false;
 var $=function(id){return document.getElementById(id);};
 function g(){return window.__game;}function journey(){return g().state.journey;}
 function day(){return new Date().toISOString().slice(0,10);}
 function daily(){var j=journey();if(j.day!==day()){j.day=day();j.dayKills=g().state.stats.kills;j.dayTower=j.towerWins;j.dailyClaimed=false;g().save();}return {kills:Math.min(25,Math.max(0,g().state.stats.kills-j.dayKills)),tower:Math.min(3,Math.max(0,j.towerWins-j.dayTower))};}
 function show(id){document.querySelector('main').scrollTop=0;var target=document.getElementById(id);if(target)target.scrollTop=0;document.querySelectorAll('.tab').forEach(function(t){t.classList.toggle('active',t.id===id);});document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.toggle('active',b.dataset.tab===id);});}
 function home(){show('tab-home');render();}
 function route(where){if(where==='home'){home();return;}if(where==='tower'){show('tab-tower');render();return;}var tab=where==='campaign'?'tab-combat':'tab-world';document.querySelector('[data-tab="'+tab+'"]').click();if(where==='arena'||where==='rank')document.querySelector('[data-social-nav="'+where+'"]').click();}
 function start(){
  if(fight&&fight.active)return;var floor=journey().towerBest+1;if(floor>200)return;
  var boss=floor%10===0,hp=Math.round(28*Math.pow(1.17,floor-1)*(boss?2.2:1));
  fight={active:true,floor:floor,boss:boss,hp:hp,max:hp,self:100,elapsed:0,strike:0,heal:0,guard:0,guardUntil:0,dps:Math.max(1,g().dps()),damage:4+floor*.12,result:''};
  $('tower-enemy').src='enemies/'+(boss?['colossal','blinde','feminin','bestial'][Math.floor(floor/10-1)%4]:floor%3===0?'anormal':'titan')+'.png';
  $('tower-result').textContent='';last=performance.now();render();
 }
 function end(win){
  if(!fight||!fight.active)return;fight.active=false;
  if(win){var j=journey();if(fight.floor===j.towerBest+1){j.towerBest=fight.floor;j.towerWins++;var gold=Math.round(60*Math.pow(1.13,fight.floor-1)),crystals=fight.boss?5:1;g().state.gold+=gold;g().state.stats.goldTotal+=gold;g().state.crystals+=crystals;g().save();fight.result='Étage conquis ! +'+g().fmt(gold)+' or · +'+crystals+' cristaux';if(window.Remaster)Remaster.sound('win');}}
  else fight.result='Repli du bataillon. Renforcez votre escouade, puis réessayez cet étage.';
  render();
 }
 function tick(dt){if(!fight||!fight.active||document.hidden||!document.getElementById('live-duel').hidden)return;dt=Math.min(.15,Math.max(0,dt));fight.elapsed+=dt;fight.hp=Math.max(0,fight.hp-fight.dps*dt);fight.self=Math.max(0,fight.self-fight.damage*(fight.elapsed<fight.guardUntil?.25:1)*dt);if(fight.self<=0)end(false);else if(fight.hp<=0)end(true);}
 function skill(name){if(!fight||!fight.active||fight.elapsed<fight[name])return;
  if(name==='strike'){fight.strike=fight.elapsed+4;fight.hp=Math.max(0,fight.hp-fight.dps*3);$('tower-enemy').classList.remove('duel-hit');void $('tower-enemy').offsetWidth;$('tower-enemy').classList.add('duel-hit');if(window.Remaster)Remaster.sound('skill');if(fight.hp<=0)end(true);}
  if(name==='heal'){fight.heal=fight.elapsed+12;fight.self=Math.min(100,fight.self+28);if(window.Remaster)Remaster.sound('heal');}
  if(name==='guard'){fight.guard=fight.elapsed+8;fight.guardUntil=fight.elapsed+3;}
  render();
 }
 function claim(){var d=daily(),j=journey();if(j.dailyClaimed||(d.kills<25&&d.tower<3))return;j.dailyClaimed=true;g().state.crystals+=15;g().save();render();if(window.Remaster)Remaster.sound('loot');}
 function render(){if(!ready||!g())return;var j=journey(),d=daily(),s=g().state;
  $('home-pseudo').textContent=s.online.pseudo||Art.names[s.portrait]||'Soldat';$('home-chapter').textContent='Chapitre '+s.chapter+' / 1 000';$('home-tower').textContent=j.towerBest+' / 200 étages conquis';$('home-campaign-progress').value=Math.max(0,s.record.chapter);$('home-tower-progress').value=j.towerBest;
  $('daily-kills').textContent=d.kills+' / 25 ennemis en campagne';$('daily-tower').textContent=d.tower+' / 3 étages de tour';$('daily-claim').disabled=j.dailyClaimed||(d.kills<25&&d.tower<3);$('daily-claim').textContent=j.dailyClaimed?'Récompense récupérée':'Récupérer 15 cristaux';
  $('tower-floor').textContent=j.towerBest>=200?'Tour terminée': 'Étage '+(fight?fight.floor:j.towerBest+1)+' / 200';$('tower-best').textContent='Record permanent : '+j.towerBest+' étages';$('tower-start').hidden=!!(fight&&fight.active);$('tower-start').disabled=j.towerBest>=200;$('tower-start').textContent=j.towerBest>=200?'Les 200 étages sont conquis':fight&&fight.result&&fight.self>0?'Étage suivant':'Lancer le combat';
  $('tower-result').textContent=fight?fight.result:'';$('tower-exit').textContent=fight&&fight.active?'Abandonner et rentrer':'Retour à l’accueil';
  $('tower-enemy-hp').value=fight?100*fight.hp/fight.max:100;$('tower-self-hp').value=fight?fight.self:100;$('tower-values').textContent=fight?'Ennemi : '+Math.ceil(fight.hp)+' / '+fight.max+' PV · Escouade : '+Math.ceil(fight.self)+' %':'Votre puissance d’escouade détermine vos dégâts.';
  $('tower-type').textContent=fight&&fight.boss?'GARDIEN · RÉCOMPENSE BONUS':'EXPÉDITION SOLO · HORS LIGNE';
  ['strike','guard','heal'].forEach(function(k){var b=$('tower-'+k),cd=fight?Math.max(0,Math.ceil(fight[k]-fight.elapsed)):0;b.disabled=!fight||!fight.active||cd>0;b.querySelector('span').textContent={strike:'Assaut',guard:'Garde',heal:'Soin'}[k]+(cd?' · '+cd+' s':'');});
 }
 window.Hub={inTower:function(){return !!(fight&&fight.active);},home:home,route:route};
 document.addEventListener('DOMContentLoaded',function(){
  var el=document.createElement('section');el.id='tab-home';el.className='tab';el.innerHTML=`<header class="home-banner"><img class="brand-logo" src="logo.png" alt="Idle AOT"><p>LE DESTIN DE L’HUMANITÉ VOUS ATTEND</p><h1>Bienvenue, <span id="home-pseudo">Soldat</span></h1></header><button class="destination campaign-destination" data-destination="campaign"><small>L’AVENTURE PRINCIPALE</small><h2>Campagne</h2><p id="home-chapter"></p><progress id="home-campaign-progress" max="1000"></progress><span>Reprendre l’expédition →</span></button><div class="destination-grid"><button class="destination" data-destination="arena">${Art.icon('abilities',6)}<h2>Arène</h2><p>Duels en temps réel</p><span>Affronter un joueur →</span></button><button class="destination" data-destination="rank">${Art.icon('abilities',20)}<h2>Classement</h2><p>Les meilleurs du monde</p><span>Voir les rangs →</span></button></div><button class="destination tower-destination" data-destination="tower"><small>NOUVEAU · DÉFI SOLO</small><h2>Tour de combat</h2><p id="home-tower"></p><progress id="home-tower-progress" max="200"></progress><span>Gravir les 200 étages →</span></button><section class="daily-card"><small>ORDRES DU JOUR</small><h2>Mission du bataillon</h2><p id="daily-kills"></p><p id="daily-tower"></p><button id="daily-claim" class="big-btn"></button><p class="hint">Au choix : 25 ennemis en campagne ou 3 étages conquis. Nouveaux objectifs chaque jour à 00:00 UTC. Progression à partir de votre première visite du jour.</p></section>`;document.querySelector('main').prepend(el);
  var tower=document.createElement('section');tower.id='tab-tower';tower.className='tab';tower.innerHTML=`<header class="tower-heading"><small id="tower-type"></small><h1 id="tower-floor"></h1><p id="tower-best"></p></header><div class="tower-arena"><img id="tower-enemy" src="enemies/titan.png" alt="Adversaire de la Tour de combat"><progress id="tower-enemy-hp" max="100" aria-label="Vie de l’ennemi"></progress></div><p id="tower-values"></p><progress id="tower-self-hp" max="100" aria-label="Vie de votre escouade"></progress><div class="tower-skills"><button id="tower-strike">${Art.icon('abilities',6)}<span>Assaut</span></button><button id="tower-guard">${Art.icon('abilities',2)}<span>Garde</span></button><button id="tower-heal">${Art.icon('abilities',8)}<span>Soin</span></button></div><p id="tower-result" role="status"></p><button id="tower-start" class="big-btn">Lancer le combat</button><button id="tower-exit" class="ghost-btn">Retour à l’accueil</button><p class="hint">Un gardien tous les 10 étages. Vos recrues et améliorations augmentent les dégâts. Récompense unique à chaque premier passage ; record conservé après une renaissance. La campagne est en pause pendant le duel.</p>`;document.querySelector('main').append(tower);
  var b=document.createElement('button');b.className='tab-btn';b.dataset.tab='tab-home';b.innerHTML='<i><img src="logo.png" alt=""></i><span>Accueil</span>';b.onclick=home;document.querySelector('.tabbar').prepend(b);
  document.querySelectorAll('[data-destination]').forEach(function(b){b.onclick=function(){route(b.dataset.destination);};});$('tower-start').onclick=start;$('daily-claim').onclick=claim;['strike','guard','heal'].forEach(function(k){$('tower-'+k).onclick=function(){skill(k);};});$('tower-exit').onclick=function(){if(fight&&fight.active&&!confirm('Abandonner cet étage ? Les étages déjà conquis sont conservés.'))return;if(fight)fight.active=false;home();};
  document.querySelector('.tabbar').addEventListener('click',function(e){if(fight&&fight.active&&e.target.closest('.tab-btn')){show('tab-tower');$('tower-result').textContent='Utilisez « Abandonner et rentrer » pour quitter le combat.';}});
  ready=true;home();requestAnimationFrame(function frame(now){var dt=last?(now-last)/1000:0;last=now;tick(dt);if(now-lastRender>100){lastRender=now;if($('tab-home').classList.contains('active')||$('tab-tower').classList.contains('active'))render();}requestAnimationFrame(frame);});
 });
})();

