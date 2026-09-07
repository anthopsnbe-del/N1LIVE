/* Local preview only. Production always calls plantation_action.php. */
(function(root){
 const TIER_COST=[60,110,180,280,400],TIER_MASTERY=[1,2,3,5,8],tierOf=v=>Math.max(0,Math.min(4,v.tier|0));
 const initial=catalog=>({version:3,accessories:{},revision:0,credits:250,soil:12,water:6,feed:6,seeds:Object.fromEntries(catalog.filter(v=>!v.parents.length&&tierOf(v)===0).map(v=>[v.id,2])),pots:Array.from({length:4},()=>({soil:false,plant:null})),buds:{},harvests:{},discoveries:{},total:0,history:[],seen:[]});
 function apply(state,action,p,now,catalog){
  const s=JSON.parse(JSON.stringify(state)),cat=Object.fromEntries(catalog.map(v=>[v.id,v])),check=(c,m)=>{if(!c)throw Error(m);},pot=s.pots[Number(p.pot)];
  const event=text=>{s.history.unshift({at:now,text});s.history=s.history.slice(0,12);};
  if(['soil','plant','water','feed','harvest'].includes(action))check(pot&&Number.isInteger(Number(p.pot)),'Pot invalide.');
  if(s.version<2){for(const id of ['diesel','peche','pin','orchidee'])s.seeds[id]=(s.seeds[id]||0)+2;s.version=2;}
  if(s.version<3){const legacy=['emeraude','nebuleuse','citron','velours','menthe','ambre','rose','givre','mangue','onyx','diesel','peche','pin','orchidee'];
   for(const v of catalog)if(!v.parents.length&&tierOf(v)===0&&!legacy.includes(v.id))s.seeds[v.id]=(s.seeds[v.id]||0)+1;s.version=3;}
  s.accessories=s.accessories||{};
  switch(action){
   case 'soil':check(!pot.plant&&!pot.soil,'Ce pot est déjà préparé.');check(s.soil>0,'Il te faut du terreau.');s.soil--;pot.soil=true;break;
   case 'plant':check(cat[p.seed]&&pot.soil&&!pot.plant,'Prépare un pot vide.');check(s.seeds[p.seed]>0,'Graine indisponible.');s.seeds[p.seed]--;pot.plant={id:p.seed,planted:now,started:null,ready:null,fed:false};event(cat[p.seed].name+' semée.');break;
   case 'water':check(pot.plant&&pot.plant.started===null,'Cette plante n’attend pas d’eau.');check(s.water>0,'Remplis ton arrosoir.');s.water--;pot.plant.started=now;pot.plant.ready=now+cat[pot.plant.id].duration;break;
   case 'feed':check(pot.plant&&pot.plant.started!==null&&!pot.plant.fed&&pot.plant.ready>now,'Soin indisponible.');check(s.feed>0,'Plus de soins.');s.feed--;pot.plant.fed=true;break;
   case 'harvest':check(pot.plant&&pot.plant.ready!==null&&now>=pot.plant.ready,'La plante n’est pas prête.');{const pl=pot.plant,v=cat[pl.id],yieldN=pl.fed?3:2;s.buds[v.id]=(s.buds[v.id]||0)+yieldN;s.harvests[v.id]=(s.harvests[v.id]||0)+1;s.seeds[v.id]=Math.min(9999,(s.seeds[v.id]||0)+2);s.credits=Math.min(9999999,s.credits+v.reward+(pl.fed?15:0));s.total++;s.pots[Number(p.pot)]={soil:false,plant:null};event(v.name+' : '+yieldN+' buds et 2 graines récoltés.');}break;
   case 'refill':s.water=6;break;
   case 'buy':{const field=p.item==='soil'?'soil':p.item==='feed'?'feed':null,cost=field?(field==='soil'?15:20):cat[p.item]?.price;check(field||(cat[p.item]&&!cat[p.item].parents.length),'Graine de laboratoire.');check(s.credits>=cost,'Pas assez de points.');if(field){check(s[field]<=995,'Réserve pleine.');s[field]+=5;}else{check((s.seeds[p.item]||0)<9999,'Réserve pleine.');s.seeds[p.item]=(s.seeds[p.item]||0)+1;}s.credits-=cost;}break;
   case 'accessory':{const prices={fan:120,humidifier:140,loupe:100};check(prices[p.item]&&!s.accessories[p.item],'Accessoire indisponible.');check(s.credits>=prices[p.item],'Pas assez de points.');s.credits-=prices[p.item];s.accessories[p.item]=true;}break;
   case 'pot':{const cost=150*(s.pots.length-3);check(s.pots.length<6,'Six pots maximum.');check(s.credits>=cost,'Pas assez de points.');s.credits-=cost;s.pots.push({soil:false,plant:null});}break;
   case 'cross':{const a=cat[p.a],b=cat[p.b];check(a&&b&&p.a!==p.b&&!a.parents.length&&!b.parents.length,'Deux fondatrices différentes sont nécessaires.');
    const id=[p.a,p.b].sort().join('--');check(cat[id],'Ces deux fondatrices ne donnent aucune recette connue.');
    const tier=tierOf(cat[id]),cost=TIER_COST[tier],mastery=TIER_MASTERY[tier];
    check((s.harvests[p.a]||0)>=mastery&&(s.harvests[p.b]||0)>=mastery,'Recette '+cat[id].rarity+' : récolte '+mastery+' fois chaque parent.');
    check(s.seeds[p.a]>0&&s.seeds[p.b]>0,'Une graine de chaque parent est nécessaire.');check(s.credits>=cost,cost+' points nécessaires.');
    check((s.seeds[id]||0)<9999,'Réserve pleine.');s.seeds[p.a]--;s.seeds[p.b]--;s.credits-=cost;s.seeds[id]=(s.seeds[id]||0)+1;s.discoveries[id]=true;event('Croisement réussi : '+cat[id].name+' ('+cat[id].rarity+').');}break;
   default:throw Error('Action inconnue.');
  }s.revision++;return s;
 }
 root.GPDemo={initial,apply};
})(typeof window==='undefined'?globalThis:window);
