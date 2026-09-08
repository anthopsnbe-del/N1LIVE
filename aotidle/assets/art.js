(function(){
 'use strict';
 var sheets={heroes:[4,6],items:[5,6],abilities:[4,6]},images={},pending=new Set();
 // Measured cell bounds: generated sheets do not have equally spaced rows.
 var bounds={heroes:{x:[[14,254],[266,505],[518,758],[770,1010]],y:[[14,276],[287,533],[544,774],[786,1014],[1026,1254],[1267,1522]]},items:{x:[[14,243],[262,494],[512,743],[760,993],[1010,1240]],y:[[14,231],[246,458],[473,684],[697,895],[909,1066],[1080,1239]]},abilities:{x:[[18,244],[272,498],[526,753],[781,1006]],y:[[13,242],[266,495],[521,754],[778,1011],[1035,1267],[1286,1521]]}};
 function draw(ctx,key,n,x,y,w,h){var im=images[key],grid=sheets[key];if(!im||!im.complete||!im.naturalWidth)return false;var cell=bounds[key],xs=cell.x[n%grid[0]],ys=cell.y[Math.floor(n/grid[0])];ctx.imageSmoothingEnabled=true;ctx.drawImage(im,xs[0]+1,ys[0]+1,xs[1]-xs[0]-2,ys[1]-ys[0]-2,x,y,w,h);return true;}
 function paint(c){if(draw(c.getContext('2d'),c.dataset.sheet,+c.dataset.index,0,0,c.width,c.height))pending.delete(c);else pending.add(c);}
 Object.keys(sheets).forEach(function(k){var im=images[k]=new Image();im.onload=function(){pending.forEach(function(c){if(c.isConnected)paint(c);else pending.delete(c);});};im.src=k+'.png';});
 function scan(el){if(el.nodeType!==1)return;if(el.matches('canvas.asset'))paint(el);el.querySelectorAll('canvas.asset').forEach(paint);}
 new MutationObserver(function(records){records.forEach(function(r){r.addedNodes.forEach(scan);});}).observe(document.documentElement,{childList:true,subtree:true});
 window.Art={draw:draw,icon:function(key,n){if(!sheets[key])return '';n=Math.max(0,Math.min(sheets[key][0]*sheets[key][1]-1,Math.floor(n)||0));return '<canvas class="asset" data-sheet="'+key+'" data-index="'+n+'" width="144" height="144" aria-hidden="true"></canvas>';},names:['Eren','Mikasa','Armin','Livaï','Hange','Erwin','Sasha','Jean','Connie','Historia','Reiner','Bertolt','Annie','Sieg','Pieck','Porco','Ymir','Gabi','Falco','Kenny','Petra','Marco','Floch','Hannes']};
 document.addEventListener('DOMContentLoaded',function(){
  [['.res-item.gold i','items',26],['.res-item.crystal i','items',27],['.res-item.soul i','items',28],['[data-tab="tab-combat"] i','abilities',6],['[data-tab="tab-hero"] i','items',8],['[data-tab="tab-team"] i','abilities',21],['[data-tab="tab-shop"] i','items',24],['[data-tab="tab-world"] i','abilities',23]].forEach(function(d){document.querySelector(d[0]).innerHTML=Art.icon(d[1],d[2]);});
  scan(document.body);var team=document.getElementById('team'),nav=document.createElement('nav');nav.className='rarity-filters';nav.setAttribute('aria-label','Rareté des héros');
  ['Tous','R','SR','SSR','UR','LP'].forEach(function(r){var b=document.createElement('button');b.textContent=r;b.classList.toggle('selected',r==='Tous');b.onclick=function(){nav.querySelectorAll('button').forEach(function(x){x.classList.toggle('selected',x===b);});team.querySelectorAll('[data-hero-rarity]').forEach(function(c){c.hidden=r!=='Tous'&&c.dataset.heroRarity!==r;});};nav.append(b);});team.before(nav);
 });
})();

