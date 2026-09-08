/* AOT IDLE — moteur du jeu. Projet de fan, hors ligne, sans dépendance. */
(function () {
  'use strict';

  var CHAPTERS = window.Content.CHAPTERS;
  var SHOP_CONSUMABLES = window.Content.SHOP_CONSUMABLES;
  var SHOP_UPGRADES = window.Content.SHOP_UPGRADES;

  // ------------------------------------------------------------------
  // Données
  // ------------------------------------------------------------------

  var UPGRADES = [
    { id: 'force', icon: Art.icon("abilities",0), name: 'Puissance de coupe', desc: '+13 % de dégâts', cost: 15, growth: 1.24 },
    { id: 'vitalite', icon: Art.icon("abilities",1), name: 'Endurance', desc: '+10 % de points de vie', cost: 20, growth: 1.2 },
    { id: 'armure', icon: Art.icon("abilities",2), name: 'Manœuvre d\'esquive', desc: '+16 % de réduction des dégâts', cost: 30, growth: 1.21 },
    { id: 'vitesse', icon: Art.icon("abilities",3), name: 'Vitesse de lame', desc: '+5 % de vitesse d\'attaque', cost: 45, growth: 1.26 },
    { id: 'crit', icon: Art.icon("abilities",4), name: 'Point vital', desc: '+1,2 % de critique, +5 % dégâts critiques', cost: 70, growth: 1.24 },
    { id: 'fortune', icon: Art.icon("abilities",5), name: 'Récupération', desc: '+8 % d\'or récolté', cost: 60, growth: 1.22 }
  ];

  var COMPANIONS = [
    { id: 'cadet', icon: Art.icon("heroes",0), rarity: "SSR", name: 'Eren Jäger', dps: 2, cost: 30, growth: 1.18 },
    { id: 'eclaireur', icon: Art.icon("heroes",1), rarity: "UR", name: 'Mikasa Ackerman', dps: 14, cost: 320, growth: 1.18 },
    { id: 'tireur', icon: Art.icon("heroes",6), rarity: "SR", name: 'Sasha Braus', dps: 90, cost: 3200, growth: 1.18 },
    { id: 'escouade', icon: Art.icon("heroes",7), rarity: "SR", name: 'Jean Kirstein', dps: 620, cost: 34000, growth: 1.18 },
    { id: 'veteran', icon: Art.icon("heroes",4), rarity: "SSR", name: 'Hange Zoe', dps: 4200, cost: 420000, growth: 1.19 },
    { id: 'capitaine', icon: Art.icon("heroes",3), rarity: "LP", name: 'Livaï Ackerman', dps: 32000, cost: 5.2e6, growth: 1.19 },
    { id: 'commandant', icon: Art.icon("heroes",5), rarity: "UR", name: 'Erwin Smith', dps: 260000, cost: 7.5e7, growth: 1.19 },
    { id: 'allie', icon: Art.icon("heroes",2), rarity: "SSR", name: 'Armin Arlert', dps: 2.4e6, cost: 1.1e9, growth: 1.19 },
{id:"hero8",icon:Art.icon("heroes",8),name:"Connie Springer",rarity:"R",cost:150,dps:8,growth:1.19},
{id:"hero9",icon:Art.icon("heroes",9),name:"Historia Reiss",rarity:"SR",cost:1440,dps:80,growth:1.19},
{id:"hero10",icon:Art.icon("heroes",10),name:"Reiner Braun",rarity:"UR",cost:107520,dps:5973,growth:1.19},
{id:"hero11",icon:Art.icon("heroes",11),name:"Bertolt Hoover",rarity:"SSR",cost:15360,dps:853,growth:1.19},
{id:"hero12",icon:Art.icon("heroes",12),name:"Annie Leonhart",rarity:"UR",cost:138240,dps:7680,growth:1.19},
{id:"hero13",icon:Art.icon("heroes",13),name:"Sieg Jäger",rarity:"LP",cost:1228800,dps:68267,growth:1.19},
{id:"hero14",icon:Art.icon("heroes",14),name:"Pieck Finger",rarity:"SSR",cost:21120,dps:1173,growth:1.19},
{id:"hero15",icon:Art.icon("heroes",15),name:"Porco Galliard",rarity:"SSR",cost:23040,dps:1280,growth:1.19},
{id:"hero16",icon:Art.icon("heroes",16),name:"Ymir",rarity:"SR",cost:3120,dps:173,growth:1.19},
{id:"hero17",icon:Art.icon("heroes",17),name:"Gabi Braun",rarity:"R",cost:420,dps:23,growth:1.19},
{id:"hero18",icon:Art.icon("heroes",18),name:"Falco Grice",rarity:"SR",cost:3600,dps:200,growth:1.19},
{id:"hero19",icon:Art.icon("heroes",19),name:"Kenny Ackerman",rarity:"UR",cost:245760,dps:13653,growth:1.19},
{id:"hero20",icon:Art.icon("heroes",20),name:"Petra Ral",rarity:"SR",cost:4080,dps:227,growth:1.19},
{id:"hero21",icon:Art.icon("heroes",21),name:"Marco Bott",rarity:"R",cost:540,dps:30,growth:1.19},
{id:"hero22",icon:Art.icon("heroes",22),name:"Floch Forster",rarity:"R",cost:570,dps:32,growth:1.19},
{id:"hero23",icon:Art.icon("heroes",23),name:"Hannes",rarity:"R",cost:600,dps:33,growth:1.19}
  ];

  /* Les recrues arrivent dans l'ordre de leur coût : la liste est écrite au
     fil des versions, pas dans l'ordre de recrutement. */
  COMPANIONS.sort(function (a, b) { return a.cost - b.cost; });

  /* Chaque recrue apporte un passif qui lui ressemble, actif dès le premier
     exemplaire et renforcé de moitié à chaque palier de 25. La rareté fixe la
     force du passif : R 2 %, SR 3 %, SSR 4 %, UR 6 %, LP 9 %. */
  var PASSIVE_SCALE = { R: 0.02, SR: 0.03, SSR: 0.04, UR: 0.06, LP: 0.09 };
  var PASSIVES = {
    cadet: ['dmg', 'dégâts du soldat'],
    eclaireur: ['crit', 'chances de critique'],
    tireur: ['gold', 'or récolté'],
    escouade: ['hp', 'points de vie'],
    veteran: ['xp', 'expérience gagnée'],
    capitaine: ['dmg', 'dégâts du soldat'],
    commandant: ['team', 'dégâts de l\'escouade'],
    allie: ['offline', 'butin hors ligne'],
    hero8: ['team', 'dégâts de l\'escouade'],
    hero9: ['gold', 'or récolté'],
    hero10: ['hp', 'points de vie'],
    hero11: ['dmg', 'dégâts du soldat'],
    hero12: ['crit', 'chances de critique'],
    hero13: ['team', 'dégâts de l\'escouade'],
    hero14: ['drop', 'chances de butin'],
    hero15: ['dmg', 'dégâts du soldat'],
    hero16: ['hp', 'points de vie'],
    hero17: ['xp', 'expérience gagnée'],
    hero18: ['drop', 'chances de butin'],
    hero19: ['crit', 'chances de critique'],
    hero20: ['cd', 'récupération des compétences'],
    hero21: ['gold', 'or récolté'],
    hero22: ['team', 'dégâts de l\'escouade'],
    hero23: ['offline', 'butin hors ligne']
  };

  function passiveOf(c) {
    var p = PASSIVES[c.id];
    if (!p) return null;
    return { stat: p[0], per: PASSIVE_SCALE[c.rarity] || 0.02, label: p[1] };
  }

  function heroTier(count) { return 1 + Math.floor(count / 25) * 0.5; }

  /* Somme des passifs d'un type donné, en fraction (0,12 = +12 %). */
  function heroBonus(s, stat) {
    var total = 0;
    COMPANIONS.forEach(function (c) {
      var count = s.team[c.id];
      if (!count) return;
      var p = passiveOf(c);
      if (p && p.stat === stat) total += p.per * heroTier(count);
    });
    return total;
  }

  var SKILLS = [
    { id: 'frappe', icon: Art.icon("abilities",6), name: 'Attaque éclair', cd: 20, dur: 0, level: 1 },
    { id: 'rage', icon: Art.icon("abilities",7), name: 'Rage', cd: 60, dur: 12, level: 5 },
    { id: 'elixir', icon: Art.icon("abilities",8), name: 'Sérum', cd: 45, dur: 0, level: 10 },
    { id: 'ruee', icon: Art.icon("abilities",9), name: 'Fusée', cd: 90, dur: 20, level: 18 }
  ];

  var SLOTS = [
    { id: 'arme', icon: Art.icon("items",22), name: 'Lames', stat: 'Dégâts' },
    { id: 'armure', icon: Art.icon("items",23), name: 'Harnais', stat: 'Vie' },
    { id: 'accessoire', icon: Art.icon("items",24), name: 'Accessoire', stat: 'Critique' },
    { id: 'amulette', icon: Art.icon("items",25), name: 'Cape', stat: 'Or' }
  ];

  var RARITIES = [
    { name: 'mythique', mult: 10, chance: 0.005 },
    { name: 'commun', mult: 1, chance: 0.6 },
    { name: 'rare', mult: 1.8, chance: 0.26 },
    { name: 'épique', mult: 3.2, chance: 0.1 },
    { name: 'légendaire', mult: 6, chance: 0.035 }
  ];

  var GEAR_NAMES = {
    accessoire: ['Fusée de reconnaissance', 'Insigne du bataillon', 'Réserve de gaz', 'Sceau du commandant'],
    arme: ['Lames usées', 'Lames d\'acier', 'Lames affûtées', 'Lames ultra-dures', 'Lames de Ragako', 'Lames du Choix', 'Lames du Fondateur'],
    armure: ['Harnais de cadet', 'Harnais renforcé', 'Harnais d\'élite', 'Harnais blindé', 'Harnais des Ailes', 'Harnais légendaire'],
    amulette: ['Cape de cadet', 'Cape du bataillon', 'Cape des Ailes de la Liberté', 'Cape du Commandant', 'Cape du Fondateur']
  };

  /* Arbre des âmes : dépensé après une renaissance. Le bonus passif des âmes
     suit le total gagné (`soulsEarned`), dépenser ne le fait donc jamais
     baisser. */
  var SOUL_NODES = [
    { id: 'lame', branch: 'Lame', name: 'Tranchant affûté', desc: '+12 % de dégâts du soldat', cost: 2, growth: 1.5, max: 12 },
    { id: 'oeil', branch: 'Lame', name: 'Point vital', desc: '+2 points de critique', cost: 4, growth: 1.6, max: 8 },
    { id: 'eclair', branch: 'Lame', name: 'Réflexes', desc: '-6 % de recharge des compétences', cost: 5, growth: 1.7, max: 6 },
    { id: 'chair', branch: 'Chair', name: 'Endurance de titan', desc: '+15 % de points de vie', cost: 2, growth: 1.5, max: 12 },
    { id: 'veille', branch: 'Chair', name: 'Veille du bataillon', desc: '+2 h hors ligne et +20 % de butin hors ligne', cost: 4, growth: 1.6, max: 6 },
    { id: 'garde', branch: 'Chair', name: 'Garde rapprochée', desc: '+10 % de régénération et -4 % de dégâts subis', cost: 3, growth: 1.55, max: 8 },
    { id: 'bataillon', branch: 'Bataillon', name: 'Ordre de charge', desc: '+20 % de dégâts de l\'escouade', cost: 3, growth: 1.55, max: 12 },
    { id: 'butin', branch: 'Bataillon', name: 'Récupération', desc: '+15 % d\'or récolté', cost: 3, growth: 1.5, max: 12 },
    { id: 'convoi', branch: 'Bataillon', name: 'Convoi de ravitaillement', desc: '+1 cristal par chapitre terminé', cost: 6, growth: 1.8, max: 6 }
  ];

  function soulNode(s, id) { return (s.soul && s.soul[id]) || 0; }
  function soulNodeCost(node, level) { return Math.ceil(node.cost * Math.pow(node.growth, level)); }

  function buySoulNode(id) {
    var def = null;
    SOUL_NODES.forEach(function (n) { if (n.id === id) def = n; });
    if (!def) return false;
    var level = soulNode(state, id);
    if (level >= def.max) return false;
    var cost = soulNodeCost(def, level);
    if (state.souls < cost) return false;
    state.souls -= cost;
    state.soul[id] = level + 1;
    state.hp = Math.min(state.hp, maxHp(state));
    toast(def.name + ' niveau ' + (level + 1));
    if (window.Fx) window.Fx.sound('loot');
    dirty.panels = true;
    return true;
  }

  /* ---------- Équipement : sac, fusion et bonus de panoplie ---------- */

  var RARITY_ORDER = ['commun', 'rare', 'épique', 'légendaire', 'mythique'];
  var BAG_MAX = 16;

  function rarityRank(name) { return Math.max(0, RARITY_ORDER.indexOf(name)); }

  /* Panoplie : les quatre emplacements équipés d'au moins la même rareté
     donnent un bonus commun. Un set complet en épique vaut +30 %. */
  function setRank(s) {
    var rank = 99;
    SLOTS.forEach(function (slot) {
      var item = s.gear[slot.id];
      rank = item ? Math.min(rank, rarityRank(item.rarity)) : -1;
    });
    return rank === 99 ? -1 : rank;
  }

  function setBonus(s, kind) {
    var rank = setRank(s);
    if (rank < 0) return 1;
    return 1 + (rank + 1) * (kind === 'gold' ? 0.1 : 0.15);
  }

  var BOSS_TIME = 45;
  var DOUBLE_COST = 25;
  var SAVE_KEY = 'aot-idle-save-v2';
  var LEGACY_KEY = 'idle-heros-save-v1';
  var OFFLINE_CAP = 12 * 3600;
  var PRESTIGE_POWER = 60;

  // ------------------------------------------------------------------
  // État
  // ------------------------------------------------------------------

  function newState() {
    var s = {
      version: 3,
      portrait: 0,
      title: '',
      journey: { towerBest: 0, towerWins: 0, day: "", dayKills: 0, dayTower: 0,
        dayBosses: 0, streak: 0, claimed: {}, dailyClaimed: false },
      gold: 0,
      crystals: 0,
      souls: 0,
      soulsEarned: 0,
      chapter: 1,
      fight: 0,
      bestChapter: 1,
      bestPower: 0,
      record: { chapter: 0, power: 0 },
      level: 1,
      xp: 0,
      hp: 0,
      up: {},
      team: {},
      soul: {},
      gear: {},
      bag: [],
      shop: {},
      shopBuys: {},
      cooldowns: {},
      buffs: {},
      autoAdvance: true,
      autoSkill: false,
      autoBuy: false,
      lastSeen: Date.now(),
      online: { url: '', pseudo: '', clan: '', deviceId: '', token: '' },
      stats: { kills: 0, bosses: 0, deaths: 0, prestiges: 0, playtime: 0, goldTotal: 0 }
    };
    UPGRADES.forEach(function (u) { s.up[u.id] = 0; });
    COMPANIONS.forEach(function (c) { s.team[c.id] = 0; });
    SLOTS.forEach(function (g) { s.gear[g.id] = null; });
    SOUL_NODES.forEach(function (n) { s.soul[n.id] = 0; });
    SHOP_UPGRADES.forEach(function (u) { s.shop[u.id] = 0; });
    SHOP_CONSUMABLES.forEach(function (c) { s.shopBuys[c.id] = 0; });
    SKILLS.forEach(function (k) { s.cooldowns[k.id] = 0; s.buffs[k.id] = 0; });
    SHOP_CONSUMABLES.forEach(function (c) { if (c.buff) s.buffs[c.buff] = 0; });
    s.hp = maxHp(s);
    return s;
  }

  var state = newState();
  var enemy = null;
  var attackTimer = 0;
  var enemyTimer = 0;
  var bossTimer = 0;
  var respawnTimer = 0;
  var panelTimer = 0;
  var flash = 0;
  var goldWindow = { gold: 0, time: 1 };
  var combo = { count: 0, timer: 0 };
  var dirty = { panels: true };
  var running = false;

  // ------------------------------------------------------------------
  // Chapitres et puissance
  // ------------------------------------------------------------------

  function chapterDef(n) {
    var index = Math.max(1, n) - 1;
    return CHAPTERS[index % CHAPTERS.length];
  }
  function fightsIn(n) { return chapterDef(n).fights; }

  var POWER_OFFSET = (function () {
    var offsets = [0];
    for (var i = 0; i < CHAPTERS.length; i++) offsets.push(offsets[i] + CHAPTERS[i].fights);
    return offsets;
  })();

  function powerOf(chapter, fight) {
    return (chapter - 1) * 1.1 + fight / fightsIn(chapter);
  }

  /* Palier de combat : à l'intérieur d'un chapitre, chaque adversaire est plus
     coriace que le précédent (environ +6 % de PV par combat, jusqu'à x2,1 face
     au dernier titan avant le boss). Le compteur repart au chapitre suivant :
     entrer dans un nouveau chapitre reste un moment de respiration. */
  function fightRamp(fight, fights) {
    var t = Math.min(1, fight / Math.max(1, fights - 1));
    return 1 + 1.1 * Math.pow(t, 1.25);
  }

  /* Un combat sur cinq est une élite : plus de PV, plus d'or, un nom à part. */
  function isElite(chapter, fight) {
    return fight > 0 && fight % 5 === 4 && fight < fightsIn(chapter) - 1;
  }

  function currentPower() { return powerOf(state.chapter, state.fight); }
  function currentRamp() { return fightRamp(state.fight, fightsIn(state.chapter)); }
  function isBossFight() { return state.fight >= fightsIn(state.chapter) - 1; }

  // ------------------------------------------------------------------
  // Formules
  // ------------------------------------------------------------------

  // Le bonus des âmes suit le total gagné : l'arbre se paie sans le réduire.
  function soulMult(s) { return 1 + (s.soulsEarned || s.souls || 0) * 0.05; }
  function shopLevel(s, id) { return s.shop[id] || 0; }
  function gearBonus(s, slot) { return s.gear[slot] ? s.gear[slot].power : 0; }

  function heroDamage(s) {
    var base = 6 * Math.pow(1.13, s.up.force);
    var gear = (1 + gearBonus(s, 'arme') / 100) * setBonus(s, 'dmg');
    var shop = 1 + shopLevel(s, 'tridim') * 0.25;
    var rage = s.buffs.rage > 0 ? 2 : 1;
    var lames = s.buffs.lames > 0 ? 1.6 : 1;
    return base * gear * shop * soulMult(s) * rage * lames * (1 + heroBonus(s, 'dmg') + soulNode(s, 'lame') * 0.12);
  }

  function attackSpeed(s) {
    var base = 1 + s.up.vitesse * 0.05;
    return Math.min(8, base * (1 + shopLevel(s, 'reservoir') * 0.1) * (s.buffs.gaz > 0 ? 1.5 : 1));
  }

  function critChance(s) {
    return Math.min(0.75, 0.05 + s.up.crit * 0.012 + gearBonus(s, 'accessoire') / 10000
      + shopLevel(s, 'acier') * 0.05 + heroBonus(s, 'crit') * 0.5 + soulNode(s, 'oeil') * 0.02);
  }
  function critMult(s) { return 2 + s.up.crit * 0.05; }
  function armor(s) { return s.up.armure ? 3 * Math.pow(1.16, s.up.armure - 1) : 0; }
  function regenRate(s) { return (0.02 + shopLevel(s, 'vivres') * 0.02) * (1 + soulNode(s, 'garde') * 0.1); }

  function maxHp(s) {
    var base = (100 + (s.level - 1) * 15) * Math.pow(1.1, s.up.vitalite);
    var bonus = (1 + gearBonus(s, 'armure') / 100) * (1 + shopLevel(s, 'cape') * 0.3);
    return Math.floor(base * bonus * soulMult(s) * (1 + heroBonus(s, 'hp') + soulNode(s, 'chair') * 0.15));
  }

  function goldMult(s) {
    var buff = (s.buffs.ruee > 0 ? 3 : 1) * (s.buffs.fumigene > 0 ? 2 : 1);
    return Math.pow(1.08, s.up.fortune) * (1 + gearBonus(s, 'amulette') / 100)
      * (1 + shopLevel(s, 'serum') * 0.3) * soulMult(s) * buff
      * (1 + heroBonus(s, 'gold') + soulNode(s, 'butin') * 0.15) * setBonus(s, 'gold');
  }

  function teamDps(s) {
    var total = 0;
    COMPANIONS.forEach(function (c) {
      var n = s.team[c.id];
      if (n) total += c.dps * n * heroTier(n);
    });
    var shop = 1 + shopLevel(s, 'tridim') * 0.25;
    return total * shop * soulMult(s) * (s.buffs.rage > 0 ? 2 : 1) * (s.buffs.lames > 0 ? 1.6 : 1)
      * (1 + heroBonus(s, 'team') + soulNode(s, 'bataillon') * 0.2);
  }

  function totalDps(s) {
    var hit = heroDamage(s) * (1 + critChance(s) * (critMult(s) - 1));
    return hit * attackSpeed(s) + teamDps(s);
  }

  function enemyMaxHp(power, boss, ramp, elite) {
    return Math.ceil(12 * Math.pow(1.05, power) * (ramp || 1) * (boss ? 6 : 1) * (elite ? 2.2 : 1));
  }
  function enemyDamage(power, boss, ramp, elite) {
    // Les dégâts suivent le palier de moitié : la montée se sent sans que le
    // dernier combat d'un chapitre devienne un mur infranchissable.
    return 3 * Math.pow(1.032, power) * (1 + ((ramp || 1) - 1) * 0.5) * (boss ? 1.6 : 1) * (elite ? 1.3 : 1);
  }
  function goldReward(power, boss, ramp, elite) {
    return 5 * Math.pow(1.038, power) * (ramp || 1) * (boss ? 10 : 1) * (elite ? 3 : 1);
  }
  function xpReward(power, boss, ramp, elite) {
    return Math.ceil((4 + power * 0.5) * Math.pow(1.02, power) * (ramp || 1) * (boss ? 6 : 1) * (elite ? 2 : 1));
  }
  function xpNeeded(level) { return Math.ceil(30 * Math.pow(1.2, level - 1)); }

  function upgradeCost(u, level) { return Math.ceil(u.cost * Math.pow(u.growth, level)); }
  function companionCost(c, count) { return Math.ceil(c.cost * Math.pow(c.growth, count)); }
  function consumableCost(c, bought) { return Math.ceil(c.cost * Math.pow(c.growth, Math.min(bought, 12))); }
  function shopUpgradeCost(u, level) { return Math.ceil(u.cost * Math.pow(u.growth, level)); }

  function prestigeGain(s) {
    var total = s.bestPower < PRESTIGE_POWER ? 0 : Math.floor(6 * Math.pow(s.bestPower / PRESTIGE_POWER, 1.6));
    return Math.max(0, total - s.soulsEarned);
  }

  // ------------------------------------------------------------------
  // Utilitaires
  // ------------------------------------------------------------------

  var SUFFIX = ['', 'k', 'M', 'Md', 'B', 'T', 'Qa', 'Qi', 'Sx', 'Sp', 'Oc', 'No', 'Dc'];

  function fmt(n) {
    if (!isFinite(n)) return '∞';
    if (n < 0) return '-' + fmt(-n);
    if (n < 1000) return n < 10 ? (Math.round(n * 10) / 10).toString() : Math.floor(n).toString();
    var tier = Math.min(Math.floor(Math.log10(n) / 3), SUFFIX.length - 1);
    var scaled = n / Math.pow(1000, tier);
    return (scaled < 10 ? scaled.toFixed(2) : scaled < 100 ? scaled.toFixed(1) : Math.floor(scaled)) + SUFFIX[tier];
  }

  function fmtTime(sec) {
    sec = Math.max(0, Math.floor(sec));
    var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
    if (h) return h + ' h ' + m + ' min';
    if (m) return m + ' min ' + s + ' s';
    return s + ' s';
  }

  function $(id) { return document.getElementById(id); }

  function log(text, cls) {
    var box = $('log');
    var line = document.createElement('div');
    if (cls) line.className = cls;
    line.textContent = text;
    box.appendChild(line);
    while (box.childNodes.length > 40) box.removeChild(box.firstChild);
    box.scrollTop = box.scrollHeight;
  }

  var toastTimer = null;
  function toast(text) {
    var el = $('toast');
    el.textContent = text;
    el.classList.remove('hidden');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.classList.add('hidden'); }, 2200);
  }

  /* Les nombres flottants partent du point d'impact et grossissent avec la
     part de vie retirée : un gros coup se voit sans lire le chiffre. */
  function floater(text, cls, weight) {
    if (window.Fx) window.Fx.floater(text, cls, weight);
  }

  // ------------------------------------------------------------------
  // Combat
  // ------------------------------------------------------------------

  function spawnEnemy() {
    var chapter = chapterDef(state.chapter);
    var boss = isBossFight();
    var elite = !boss && isElite(state.chapter, state.fight);
    var def = boss ? chapter.boss : chapter.mobs[Math.floor(Math.random() * chapter.mobs.length)];
    var power = currentPower();
    var ramp = currentRamp();
    var hp = enemyMaxHp(power, boss, ramp, elite);
    enemy = {
      name: elite ? def[0] + ' d\'élite' : def[0],
      shape: def[1],
      palette: boss ? (def[2] || chapter.pal) : chapter.pal,
      boss: boss,
      elite: elite,
      ramp: ramp,
      hp: hp,
      maxHp: hp,
      dmg: enemyDamage(power, boss, ramp, elite)
    };
    bossTimer = boss ? BOSS_TIME : 0;
    enemyTimer = 1.4;
    flash = 0;
    $('boss-tag').classList.toggle('hidden', !boss && !elite);
    $('boss-tag').textContent = boss ? 'BOSS' : 'ÉLITE';
    $('boss-tag').classList.toggle('elite-tag', elite && !boss);
    $('boss-timer').classList.toggle('hidden', !boss);
    var arena = $('arena');
    arena.style.background = 'radial-gradient(120% 80% at 50% 0%, ' + chapter.sky[0] + ' 0%, ' + chapter.sky[1] + ' 75%)';
    if (window.Fx) window.Fx.spawn(enemy);
    renderCombat();
  }

  function damageEnemy(amount, crit, silent) {
    if (!enemy || enemy.hp <= 0) return;
    enemy.hp -= amount;
    var share = amount / enemy.maxHp;
    if (!silent && window.Fx) window.Fx.hit(crit, Math.min(1, share * 4));
    if (amount > 0 && !silent) {
      floater((crit ? '✦ ' : '') + fmt(amount), crit ? 'crit' : '', share);
      flash = 1;
    }
    if (enemy.hp <= 0) killEnemy();
  }

  function killEnemy() {
    if (window.Fx) { window.Fx.sound('win'); window.Fx.kill(); }
    var boss = enemy.boss;
    var power = currentPower();
    var gold = goldReward(power, boss, enemy.ramp, enemy.elite) * goldMult(state);
    state.gold += gold;
    state.stats.goldTotal += gold;
    goldWindow.gold += gold;
    state.stats.kills++;
    gainXp(xpReward(power, boss, enemy.ramp, enemy.elite));
    floater('+' + fmt(gold) + ' or', 'gold');
    enemy.hp = 0;
    respawnTimer = 0.28;

    if (boss) {
      state.stats.bosses++;
      log('Boss vaincu : ' + enemy.name + ' !', 'good');
      dropGear();
      // Les cristaux ne tombent qu'au-delà du record absolu : re-terminer un
      // chapitre déjà bouclé (après une renaissance) n'en donne plus.
      if (state.chapter > state.record.chapter) {
        state.record.chapter = state.chapter;
        var crystals = 2 + Math.floor(state.chapter / 2) + soulNode(state, 'convoi');
        state.crystals += crystals;
        toast(' +' + crystals + ' cristaux');
        log('Chapitre ' + state.chapter + ' terminé pour la première fois : +' + crystals + ' cristaux.', 'loot');
      } else {
        log('Chapitre ' + state.chapter + ' terminé.', 'good');
      }
      state.fight = 0;
      if (window.Online) window.Online.sync(true);
      state.bestChapter = Math.max(state.bestChapter, Math.min(1000, state.chapter + 1));
      if (state.chapter === 1000) { log('Campagne terminée : les 1 000 chapitres sont accomplis.', 'good'); state.autoAdvance = false; }
      if (state.autoAdvance && state.chapter < 1000) {
        
        state.chapter++;
        if (state.chapter > state.bestChapter) state.bestChapter = state.chapter;
        log('Chapitre ' + state.chapter + ' — ' + chapterDef(state.chapter).name);
        if (window.Fx) window.Fx.chapter(chapterDef(state.chapter).name);
      }
    } else {
      state.fight++;
    }
    if (currentPower() > state.bestPower) state.bestPower = currentPower();
    dirty.panels = true;
    renderCombat();
  }

  function gainXp(amount) {
    state.xp += amount * (1 + heroBonus(state, 'xp'));
    var guard = 0;
    while (state.xp >= xpNeeded(state.level) && guard++ < 500) {
      state.xp -= xpNeeded(state.level);
      state.level++;
      state.hp = maxHp(state);
      log('Rang ' + state.level + ' atteint !', 'good');
      if (window.Fx) window.Fx.levelUp(state.level);
      dirty.panels = true;
    }
  }

  function makeGear(slot, rarityName, power) {
    var names = GEAR_NAMES[slot];
    var tier = Math.min(names.length - 1, Math.floor((state.chapter - 1) / 160));
    return { slot: slot, name: names[tier], rarity: rarityName, power: Math.ceil(power) };
  }

  function sellValue(item) {
    return Math.ceil(item.power * goldReward(currentPower(), false, currentRamp(), false) * 0.3);
  }

  function sellGear(index) {
    var item = state.bag[index];
    if (!item) return false;
    state.bag.splice(index, 1);
    state.gold += sellValue(item);
    dirty.panels = true;
    return true;
  }

  function equipGear(index) {
    var item = state.bag[index];
    if (!item) return false;
    state.bag.splice(index, 1);
    var old = state.gear[item.slot];
    state.gear[item.slot] = item;
    if (old) state.bag.push(old);
    state.hp = Math.min(state.hp, maxHp(state));
    if (window.Fx) window.Fx.sound('ui');
    dirty.panels = true;
    return true;
  }

  /* Fusion : deux pièces du même emplacement et de la même rareté donnent une
     pièce de la rareté supérieure, à 1,7 fois la puissance de la meilleure. */
  function fusionPartner(index) {
    var item = state.bag[index];
    if (!item || rarityRank(item.rarity) >= RARITY_ORDER.length - 1) return -1;
    for (var i = 0; i < state.bag.length; i++) {
      var other = state.bag[i];
      if (i !== index && other.slot === item.slot && other.rarity === item.rarity) return i;
    }
    return -1;
  }

  function fuseGear(index) {
    var partner = fusionPartner(index);
    if (partner < 0) return false;
    var a = state.bag[index], b = state.bag[partner];
    var merged = makeGear(a.slot, RARITY_ORDER[rarityRank(a.rarity) + 1],
      Math.max(a.power, b.power) * 1.7);
    state.bag = state.bag.filter(function (_, i) { return i !== index && i !== partner; });
    state.bag.push(merged);
    if (window.Fx) window.Fx.sound('loot');
    toast('Fusion : ' + merged.name + ' (' + merged.rarity + ')');
    dirty.panels = true;
    return true;
  }

  function storeGear(item) {
    state.bag.push(item);
    if (state.bag.length <= BAG_MAX) return;
    // Sac plein : la pièce la plus faible part à la revente, or à la clé.
    var worst = 0;
    state.bag.forEach(function (g, i) { if (g.power < state.bag[worst].power) worst = i; });
    var sold = state.bag.splice(worst, 1)[0];
    state.gold += sellValue(sold);
    log('Sac plein : ' + sold.name + ' revendu (+' + fmt(sellValue(sold)) + ' or)', 'loot');
  }

  function dropGear() {
    if (Math.random() > 0.6 * (1 + heroBonus(state, 'drop'))) return;
    var slot = SLOTS[Math.floor(Math.random() * SLOTS.length)];
    var roll = Math.random(), acc = 0, rarity = RARITIES[0];
    for (var i = 0; i < RARITIES.length; i++) {
      acc += RARITIES[i].chance;
      if (roll <= acc) { rarity = RARITIES[i]; break; }
    }
    var item = makeGear(slot.id, rarity.name, (8 + currentPower() * 0.8) * rarity.mult);
    if (window.Fx) window.Fx.sound('loot');
    var current = state.gear[slot.id];
    if (!current || item.power > current.power) {
      state.gear[slot.id] = item;
      if (current) storeGear(current);
      state.hp = Math.min(state.hp, maxHp(state));
      log('Équipé : ' + item.name + ' (' + item.rarity + ', +' + item.power + ' %)', 'loot');
      toast('Nouvel équipement : ' + item.name);
    } else {
      storeGear(item);
      log('Butin rangé : ' + item.name + ' (' + item.rarity + ')', 'loot');
    }
    if (window.Fx) window.Fx.loot(item.rarity);
    dirty.panels = true;
  }

  function heroDies() {
    state.stats.deaths++;
    state.hp = maxHp(state);
    // Repli de trois combats, jamais de chapitre perdu : la sanction cachée
    // « un chapitre en moins toutes les trois morts » était incompréhensible.
    state.fight = Math.max(0, state.fight - 3);
    if (window.Fx) window.Fx.defeat();
    log('Escouade décimée… repli au combat ' + (state.fight + 1) + '.', 'bad');
    toast(' Vaincu !');
    dirty.panels = true;
    spawnEnemy();
  }

  function skillById(id) {
    var found = null;
    SKILLS.forEach(function (k) { if (k.id === id) found = k; });
    return found;
  }

  function useSkill(id) {
    var skill = skillById(id);
    if (!skill || state.level < skill.level || state.cooldowns[id] > 0) return;
    state.cooldowns[id] = skill.cd * Math.max(0.4, 1 - soulNode(state, 'eclair') * 0.06);
    if (window.Fx) window.Fx.sound(id === 'elixir' ? 'heal' : 'skill');
    if (id === 'frappe') {
      damageEnemy(heroDamage(state) * 12, true);
    } else if (id === 'elixir') {
      var heal = maxHp(state) * 0.7;
      state.hp = Math.min(maxHp(state), state.hp + heal);
      floater('+' + fmt(heal) + ' PV', 'heal');
    } else {
      state.buffs[id] = skill.dur;
    }
  }

  // ------------------------------------------------------------------
  // Achats
  // ------------------------------------------------------------------

  /* Quantité d'achat : ×1, ×10 ou « max ». Choix mémorisé d'une session à
     l'autre, comme dans tous les idle. */
  var buyQty = 1;
  try { buyQty = JSON.parse(localStorage.getItem('aot-buy-qty')) || 1; } catch (e) { /* ignoré */ }

  function setBuyQty(value) {
    buyQty = value;
    try { localStorage.setItem('aot-buy-qty', JSON.stringify(value)); } catch (e) { /* ignoré */ }
    dirty.panels = true;
  }

  function bulk(buy, id) {
    var limit = buyQty === 'max' ? 500 : buyQty;
    var done = 0;
    while (done < limit && buy(id)) done++;
    return done;
  }

  /* Coût cumulé des `count` prochains achats, pour afficher le vrai prix. */
  function bulkCost(cost, level, count) {
    var total = 0;
    for (var i = 0; i < count; i++) total += cost(level + i);
    return total;
  }

  function affordable(cost, level, gold, limit) {
    var total = 0, count = 0;
    while (count < limit) {
      var next = cost(level + count);
      if (total + next > gold) break;
      total += next;
      count++;
    }
    return { count: count, total: total };
  }

  function buyUpgrade(id) {
    var def = null;
    UPGRADES.forEach(function (u) { if (u.id === id) def = u; });
    if (!def) return false;
    var price = upgradeCost(def, state.up[id]);
    if (state.gold < price) return false;
    state.gold -= price;
    state.up[id]++;
    if (id === 'vitalite') state.hp = Math.min(maxHp(state), state.hp * 1.1);
    dirty.panels = true;
    return true;
  }

  function buyCompanion(id) {
    var def = null;
    COMPANIONS.forEach(function (c) { if (c.id === id) def = c; });
    if (!def) return false;
    var price = companionCost(def, state.team[id]);
    if (state.gold < price) return false;
    state.gold -= price;
    state.team[id]++;
    dirty.panels = true;
    return true;
  }

  function buyConsumable(id) {
    var def = null;
    SHOP_CONSUMABLES.forEach(function (c) { if (c.id === id) def = c; });
    if (!def) return false;
    var price = consumableCost(def, state.shopBuys[id] || 0);
    if (state.gold < price) return false;
    state.gold -= price;
    state.shopBuys[id] = (state.shopBuys[id] || 0) + 1;
    if (def.buff) {
      state.buffs[def.buff] = Math.max(state.buffs[def.buff] || 0, def.dur);
      toast(def.name + ' activé !');
    } else {
      var heal = maxHp(state) * 0.6;
      state.hp = Math.min(maxHp(state), state.hp + heal);
      floater('+' + fmt(heal) + ' PV', 'heal');
    }
    dirty.panels = true;
    return true;
  }

  function buyShopUpgrade(id) {
    var def = null;
    SHOP_UPGRADES.forEach(function (u) { if (u.id === id) def = u; });
    if (!def) return false;
    var level = shopLevel(state, id);
    if (level >= def.max) return false;
    var price = shopUpgradeCost(def, level);
    if (state.crystals < price) return false;
    state.crystals -= price;
    state.shop[id] = level + 1;
    state.hp = Math.min(state.hp, maxHp(state));
    toast(def.name + ' niveau ' + (level + 1));
    dirty.panels = true;
    return true;
  }

  // ------------------------------------------------------------------
  // Boucle
  // ------------------------------------------------------------------

  /* L'automatisation se mérite : elle s'ouvre au fil des renaissances. */
  var AUTO_UNLOCK = { autoAdvance: 0, autoSkill: 1, autoBuy: 3 };
  function autoUnlocked(id) { return state.stats.prestiges >= AUTO_UNLOCK[id]; }

  var autoBuyTimer = 0;

  /* Achat automatique : la première amélioration abordable, une par seconde,
     en gardant toujours de quoi acheter une recrue. */
  function autoBuyStep(dt) {
    if (!state.autoBuy || !autoUnlocked('autoBuy')) return;
    autoBuyTimer -= dt;
    if (autoBuyTimer > 0) return;
    autoBuyTimer = 1;
    var best = null, bestCost = Infinity;
    UPGRADES.forEach(function (u) {
      var cost = upgradeCost(u, state.up[u.id]);
      if (cost < bestCost && state.gold >= cost * 3) { best = u; bestCost = cost; }
    });
    if (best) buyUpgrade(best.id);
  }

  function step(dt) {
    if (window.Hub && window.Hub.inTower()) return;
    state.stats.playtime += dt;
    autoBuyStep(dt);
    goldWindow.time += dt;
    if (goldWindow.time > 10) { goldWindow.time = 5; goldWindow.gold *= 0.5; }
    panelTimer += dt;
    if (panelTimer >= 0.5) {
      panelTimer = 0;
      if (!$('tab-combat').classList.contains('active')) refreshPanels();
    }
    if (flash > 0) flash = Math.max(0, flash - dt * 9);
    if (combo.timer > 0) {
      combo.timer -= dt;
      if (combo.timer <= 0) combo.count = 0;
    }

    SKILLS.forEach(function (k) {
      if (state.cooldowns[k.id] > 0) state.cooldowns[k.id] = Math.max(0, state.cooldowns[k.id] - dt);
      if (state.buffs[k.id] > 0) state.buffs[k.id] = Math.max(0, state.buffs[k.id] - dt);
      if (state.autoSkill && autoUnlocked('autoSkill') && state.cooldowns[k.id] === 0 && state.level >= k.level) useSkill(k.id);
    });
    SHOP_CONSUMABLES.forEach(function (c) {
      if (c.buff && state.buffs[c.buff] > 0) state.buffs[c.buff] = Math.max(0, state.buffs[c.buff] - dt);
    });

    if (!enemy) return;
    state.hp = Math.min(maxHp(state), state.hp + maxHp(state) * regenRate(state) * dt);

    if (enemy.hp <= 0) {
      respawnTimer -= dt;
      if (respawnTimer <= 0) spawnEnemy();
      return;
    }

    var team = teamDps(state);
    if (team > 0) damageEnemy(team * dt, false);

    attackTimer -= dt;
    if (attackTimer <= 0) {
      attackTimer += 1 / attackSpeed(state);
      var crit = Math.random() < critChance(state);
      damageEnemy(heroDamage(state) * (crit ? critMult(state) : 1), crit);
    }

    enemyTimer -= dt;
    if (enemyTimer <= 0) {
      enemyTimer += 1.6;
      // Un coup ne peut jamais retirer plus de 30 % des PV : on meurt d'une
      // série de coups encaissés, pas d'un one-shot en entrant dans un chapitre.
      var taken = Math.max(enemy.dmg * 0.15, enemy.dmg - armor(state)) * (1 - Math.min(0.32, soulNode(state, 'garde') * 0.04));
      taken = Math.min(taken, maxHp(state) * 0.3);
      state.hp -= taken;
      floater('-' + fmt(taken), 'bad');
      if (state.hp <= 0) { heroDies(); return; }
    }

    if (enemy.boss) {
      bossTimer -= dt;
      if (bossTimer <= 0) {
        log('Le boss vous a échappé…', 'bad');
        toast(' Temps écoulé !');
        state.fight = Math.max(0, state.fight - 3);
        spawnEnemy();
      }
    }
  }

  var last = 0;
  function loop(now) {
    if (!last) last = now;
    var dt = Math.min(0.25, (now - last) / 1000);
    last = now;
    if (running) {
      step(dt);
      // La limite d'images ne freine que l'affichage : les combats avancent au
      // même rythme quel que soit le réglage.
      if (!window.Fx || window.Fx.frameGate(now, 'game')) {
        renderCombat();
        renderSprites(now);
        if (dirty.panels) { refreshPanels(); dirty.panels = false; }
      }
    }
    requestAnimationFrame(loop);
  }

  // ------------------------------------------------------------------
  // Rendu
  // ------------------------------------------------------------------

  /* Le texte n'est réécrit que s'il change : la boucle tourne à 60 images par
     seconde, réécrire quinze nœuds à chaque image coûtait cher pour rien. */
  var lastText = {};
  function setText(id, value) {
    if (lastText[id] === value) return;
    lastText[id] = value;
    $(id).textContent = value;
  }

  /* Cristaux et âmes changent rarement : quand c'est le cas, le compteur
     tressaille pour que le gain se remarque. */
  function bump(id, value) {
    if (lastText[id] === value) return;
    var known = lastText[id] !== undefined;
    setText(id, value);
    if (!known) return;
    var el = $(id);
    el.classList.remove('bump');
    void el.offsetWidth;
    el.classList.add('bump');
  }

  function setBar(fillId, textId, cur, max, unit) {
    var pct = Math.max(0, Math.min(100, (cur / max) * 100));
    var fill = $(fillId);
    var rounded = pct.toFixed(1) + '%';
    if (fill.dataset.w !== rounded) { fill.style.width = rounded; fill.dataset.w = rounded; }
    setText(textId, fmt(Math.max(0, cur)) + ' / ' + fmt(max) + (unit || ''));
  }

  function renderSprites(now) {
    if (!enemy) return;
    var bob = Math.sin(now / 400) * 3;
    window.Sprites.draw($('enemy-canvas'), enemy.shape, enemy.palette,
      { scale: enemy.boss ? 11 : 9, bob: bob, flash: enemy.hp <= 0 ? 0.8 : flash });
    window.Sprites.draw($('hero-canvas'), 'soldat', 'soldat',
      { scale: 4, bob: Math.sin(now / 300) * 1.5 });
  }

  function renderCombat() {
    setText('gold', fmt(state.gold));
    bump('crystals', fmt(state.crystals));
    bump('souls', fmt(state.souls));
    setText('hero-level', String(state.level));
    setBar('xp-fill', 'xp-text', state.xp, xpNeeded(state.level), ' XP');
    setBar('hero-hp-fill', 'hero-hp-text', state.hp, maxHp(state));

    var chapter = chapterDef(state.chapter);
    setText('chapter-name', chapter.name);
    setText('chapter-label', 'Chapitre ' + state.chapter + ' · ' + (chapter.arcName || ''));
    setText('fight-label', 'Combat ' + Math.min(state.fight + 1, chapter.fights) + ' / ' + chapter.fights
      + ' · ×' + currentRamp().toFixed(2) + ' de PV');
    $('chapter-down').disabled = state.chapter <= 1;
    $('chapter-up').disabled = state.chapter >= state.bestChapter;

    if (enemy) {
      setText('enemy-name', enemy.name);
      setBar('enemy-hp-fill', 'enemy-hp-text', Math.ceil(enemy.hp), enemy.maxHp);
      if (enemy.boss) {
        setText('boss-timer', bossTimer.toFixed(1) + ' s');
        $('boss-timer').classList.toggle('urgent', bossTimer < 10);
      }
    }

    var comboBox = $('combo');
    comboBox.classList.toggle('hidden', combo.count < 3);
    if (combo.count >= 3) setText('combo-count', '×' + combo.count);

    setText('dps-value', fmt(totalDps(state)));
    setText('gps-value', fmt(goldWindow.gold / Math.max(1, goldWindow.time)));

    var bar = $('fight-progress');
    bar.style.width = Math.min(100, (state.fight / chapter.fights) * 100) + '%';
    renderSkills();
  }

  function renderSkills() {
    var box = $('skills');
    if (!box.childNodes.length) {
      SKILLS.forEach(function (k) {
        var btn = document.createElement('button');
        btn.className = 'skill';
        btn.innerHTML = '<i>' + k.icon + '</i>' + k.name + '<div class="cd"></div>';
        btn.addEventListener('click', function () { useSkill(k.id); });
        box.appendChild(btn);
      });
    }
    SKILLS.forEach(function (k, i) {
      var btn = box.childNodes[i];
      var locked = state.level < k.level;
      btn.classList.toggle('locked', locked);
      btn.classList.toggle('ready', !locked && state.cooldowns[k.id] === 0);
      btn.querySelector('.cd').style.height = (state.cooldowns[k.id] / k.cd * 100) + '%';
    });
  }

  function makeCard(icon, withButton, onClick) {
    var el = document.createElement('div');
    el.className = 'card';
    el.innerHTML = '<i>' + icon + '</i><div class="body"><div class="title">'
      + '<span class="c-title"></span> <small class="c-sub"></small></div>'
      + '<div class="desc c-desc"></div></div>';
    var card = { el: el, title: el.querySelector('.c-title'), sub: el.querySelector('.c-sub'), desc: el.querySelector('.c-desc'), btn: null };
    if (withButton) {
      var btn = document.createElement('button');
      btn.className = 'buy-btn';
      btn.addEventListener('click', onClick);
      el.appendChild(btn);
      card.btn = btn;
    }
    return card;
  }

  /* On ne montre pas les 24 recrues d'entrée de jeu : la liste s'ouvre sur
     celles que le joueur peut viser, plus trois suivantes en ligne de mire. */
  function revealedCompanions() {
    var reached = 0;
    COMPANIONS.forEach(function (c, i) {
      if (state.team[c.id] > 0 || state.stats.goldTotal >= c.cost * 0.5) reached = i + 1;
    });
    return Math.min(COMPANIONS.length, Math.max(3, reached + 3));
  }

  var panels = null;

  /* Les panneaux sont construits une fois puis mis à jour en place : recréer
     un bouton sous le doigt ferait perdre l'appui. */
  function buildPanels() {
    panels = {
      upgrades: [], team: [], gear: [], stats: [], consumables: [], shop: [],
      souls: [], bag: [], teamCount: revealedCompanions(), bagCount: state.bag.length
    };

    var upBox = $('upgrades');
    upBox.innerHTML = '';
    UPGRADES.forEach(function (u) {
      var card = makeCard(u.icon, true, function () { bulk(buyUpgrade, u.id); refreshPanels(); });
      card.title.textContent = u.name;
      upBox.appendChild(card.el);
      panels.upgrades.push({ def: u, card: card });
    });

    var gearBox = $('gear');
    gearBox.innerHTML = '';
    SLOTS.forEach(function (slot) {
      var card = makeCard(slot.icon, false, null);
      gearBox.appendChild(card.el);
      panels.gear.push({ def: slot, card: card });
    });

    buildBag();

    var statBox = $('stats');
    statBox.innerHTML = '';
    for (var i = 0; i < 8; i++) {
      var stat = document.createElement('div');
      stat.className = 'stat';
      stat.innerHTML = '<span></span><b></b>';
      statBox.appendChild(stat);
      panels.stats.push(stat);
    }

    var teamBox = $('team');
    teamBox.innerHTML = '';
    COMPANIONS.slice(0, panels.teamCount).forEach(function (c) {
      var card = makeCard(c.icon, true, function () { bulk(buyCompanion, c.id); refreshPanels(); });
      card.title.textContent = c.name;
      card.el.dataset.heroRarity = c.rarity;
      teamBox.appendChild(card.el);
      panels.team.push({ def: c, card: card });
    });

    var consBox = $('shop-consumables');
    consBox.innerHTML = '';
    SHOP_CONSUMABLES.forEach(function (c) {
      var card = makeCard(c.icon, true, function () { buyConsumable(c.id); refreshPanels(); });
      card.title.textContent = c.name;
      card.el.dataset.heroRarity = c.rarity;
      card.desc.textContent = c.desc;
      consBox.appendChild(card.el);
      panels.consumables.push({ def: c, card: card });
    });

    var shopBox = $('shop-upgrades');
    shopBox.innerHTML = '';
    SHOP_UPGRADES.forEach(function (u) {
      var card = makeCard(u.icon, true, function () { buyShopUpgrade(u.id); refreshPanels(); });
      card.title.textContent = u.name;
      card.desc.textContent = u.desc;
      shopBox.appendChild(card.el);
      panels.shop.push({ def: u, card: card });
    });

    buildSoulTree();
    refreshPanels();
  }

  /* Arbre des âmes : trois branches, une carte par nœud. */
  function buildSoulTree() {
    var box = $('soul-tree');
    if (!box) return;
    box.innerHTML = '';
    var branches = {};
    SOUL_NODES.forEach(function (node) {
      if (!branches[node.branch]) {
        var head = document.createElement('h3');
        head.className = 'branch';
        head.textContent = node.branch;
        box.appendChild(head);
        var group = document.createElement('div');
        group.className = 'cards';
        box.appendChild(group);
        branches[node.branch] = group;
      }
      var card = makeCard(SOUL_ICONS[node.id] || '', true, function () {
        buySoulNode(node.id);
        refreshPanels();
      });
      card.title.textContent = node.name;
      card.desc.textContent = node.desc;
      branches[node.branch].appendChild(card.el);
      panels.souls.push({ def: node, card: card });
    });
  }

  /* Le sac change de taille au fil des butins : on le reconstruit alors. */
  function buildBag() {
    var box = $('bag');
    if (!box) return;
    box.innerHTML = '';
    panels.bag = [];
    panels.bagCount = state.bag.length;
    if (!state.bag.length) {
      var empty = document.createElement('p');
      empty.className = 'hint';
      empty.textContent = 'Sac vide : les boss lâchent de l\'équipement.';
      box.appendChild(empty);
      return;
    }
    state.bag.forEach(function (item, index) {
      var card = makeCard(gearIcon(item), false, null);
      card.el.dataset.rarity = item.rarity;
      card.title.className = 'c-title rarity-' + item.rarity;
      card.title.textContent = item.name;
      card.sub.textContent = item.rarity;
      card.desc.textContent = slotLabel(item.slot) + ' · +' + item.power + ' %';

      var actions = document.createElement('div');
      actions.className = 'bag-actions';
      var equip = document.createElement('button');
      equip.className = 'buy-btn';
      equip.textContent = 'Équiper';
      equip.onclick = function () { equipGear(index); buildBag(); refreshPanels(); };
      actions.appendChild(equip);
      if (fusionPartner(index) >= 0) {
        var fuse = document.createElement('button');
        fuse.className = 'buy-btn fuse';
        fuse.textContent = 'Fusionner';
        fuse.onclick = function () { fuseGear(index); buildBag(); refreshPanels(); };
        actions.appendChild(fuse);
      }
      var sell = document.createElement('button');
      sell.className = 'ghost-btn small';
      sell.textContent = 'Vendre · ' + fmt(sellValue(item));
      sell.onclick = function () { sellGear(index); buildBag(); refreshPanels(); };
      actions.appendChild(sell);
      card.el.appendChild(actions);
      box.appendChild(card.el);
      panels.bag.push({ item: item, card: card });
    });
  }

  var SOUL_ICONS = {
    lame: Art.icon('abilities', 0), oeil: Art.icon('abilities', 4), eclair: Art.icon('abilities', 3),
    chair: Art.icon('abilities', 1), veille: Art.icon('abilities', 8), garde: Art.icon('abilities', 2),
    bataillon: Art.icon('abilities', 7), butin: Art.icon('abilities', 5), convoi: Art.icon('abilities', 9)
  };

  function slotLabel(id) {
    var found = id;
    SLOTS.forEach(function (slot) { if (slot.id === id) found = slot.name; });
    return found;
  }

  function gearIcon(item) {
    var base = { arme: 0, armure: 7, accessoire: 18, amulette: 13 }[item.slot];
    var index = GEAR_NAMES[item.slot].indexOf(item.name);
    return Art.icon('items', base + Math.max(0, index));
  }

  /* Ce que le prochain niveau change vraiment, dans l'unité qui parle pour
     cette amélioration : la carte annonce un avant/après, pas un pourcentage
     abstrait. */
  var UPGRADE_STAT = {
    force: ['DPS', totalDps, fmt],
    vitesse: ['DPS', totalDps, fmt],
    vitalite: ['PV', maxHp, fmt],
    armure: ['armure', armor, function (v) { return fmt(v); }],
    crit: ['critique', function (s) { return critChance(s) * 100; }, function (v) { return v.toFixed(1) + ' %'; }],
    fortune: ['or', function (s) { return goldMult(s) * 100; }, function (v) { return Math.round(v) + ' %'; }]
  };

  function upgradeGain(u) {
    var stat = UPGRADE_STAT[u.id] || ['DPS', totalDps, fmt];
    var before = stat[1](state);
    state.up[u.id]++;
    var after = stat[1](state);
    state.up[u.id]--;
    return { label: stat[0], from: stat[2](before), to: stat[2](after) };
  }

  function refreshPanels() {
    if (!panels) { buildPanels(); return; }
    if (panels.teamCount !== revealedCompanions()) { buildPanels(); return; }
    if (panels.bagCount !== state.bag.length) buildBag();

    document.querySelectorAll('.qty').forEach(function (b) {
      b.classList.toggle('selected', String(buyQty) === b.dataset.qty);
    });

    panels.upgrades.forEach(function (row) {
      var level = state.up[row.def.id];
      var def = row.def;
      var cost = function (n) { return upgradeCost(def, n); };
      var deal = buyQty === 'max'
        ? affordable(cost, level, state.gold, 500)
        : { count: buyQty, total: bulkCost(cost, level, buyQty) };
      var count = Math.max(1, deal.count);
      var total = deal.count ? deal.total : cost(level);
      var gain = upgradeGain(def);
      row.card.sub.textContent = 'niv. ' + level;
      row.card.desc.textContent = def.desc + ' — ' + gain.label + ' ' + gain.from + ' → ' + gain.to;
      row.card.btn.textContent = (count > 1 ? '×' + count + ' · ' : '') + fmt(total);
      row.card.btn.disabled = state.gold < cost(level);
    });

    var rank = setRank(state);
    var setInfo = $('set-info');
    if (setInfo) {
      setInfo.textContent = rank < 0
        ? 'Panoplie incomplète : équipez les quatre emplacements pour un bonus commun.'
        : 'Panoplie ' + RARITY_ORDER[rank] + ' : +' + Math.round((setBonus(state, 'dmg') - 1) * 100)
          + ' % de dégâts et +' + Math.round((setBonus(state, 'gold') - 1) * 100) + ' % d\'or.';
    }

    panels.gear.forEach(function (row) {
      var item = state.gear[row.def.id];
      row.card.title.textContent = item ? item.name : 'Aucun';
      var artIndex = item ? null : { arme: 22, armure: 23, accessoire: 24, amulette: 25 }[row.def.id];
      var iconBox = row.card.el.querySelector('i');
      var wanted = item ? item.name + item.rarity : 'vide';
      if (iconBox.dataset.itemArt !== wanted) {
        iconBox.innerHTML = item ? gearIcon(item) : Art.icon('items', artIndex);
        iconBox.dataset.itemArt = wanted;
      }
      row.card.el.dataset.rarity = item ? item.rarity : '';
      row.card.title.className = 'c-title' + (item ? ' rarity-' + item.rarity : '');
      row.card.sub.textContent = item ? item.rarity : row.def.name;
      row.card.desc.textContent = item
        ? '+' + (row.def.id === 'accessoire'
          ? (item.power / 100).toFixed(2) + ' points de critique (plafond 75 %)'
          : item.power + ' % ' + row.def.stat)
        : 'Les boss lâchent de l\'équipement';
    });

    var bagCount = $('bag-count');
    if (bagCount) bagCount.textContent = state.bag.length + ' / ' + BAG_MAX;

    panels.team.forEach(function (row) {
      var def = row.def;
      var count = state.team[def.id];
      var cost = function (n) { return companionCost(def, n); };
      var deal = buyQty === 'max'
        ? affordable(cost, count, state.gold, 500)
        : { count: buyQty, total: bulkCost(cost, count, buyQty) };
      var quantity = Math.max(1, deal.count);
      var total = deal.count ? deal.total : cost(count);
      var each = def.dps * heroTier(count) * soulMult(state);
      var passive = passiveOf(def);
      var next = (Math.floor(count / 25) + 1) * 25;
      row.card.sub.textContent = def.rarity + ' · ' + (count ? '×' + count : 'À recruter');
      row.card.desc.textContent = (count
        ? fmt(each * count) + ' DPS — palier suivant à ' + next
        : fmt(def.dps * soulMult(state)) + ' DPS par recrue')
        + (passive ? ' · +' + Math.round(passive.per * heroTier(count) * 100) + ' % ' + passive.label : '');
      row.card.btn.textContent = (quantity > 1 ? '×' + quantity + ' · ' : '') + fmt(total);
      row.card.btn.disabled = state.gold < cost(count);
    });

    panels.consumables.forEach(function (row) {
      var cost = consumableCost(row.def, state.shopBuys[row.def.id] || 0);
      var active = row.def.buff && state.buffs[row.def.buff] > 0;
      row.card.sub.textContent = active ? Math.ceil(state.buffs[row.def.buff]) + ' s' : '';
      row.card.btn.textContent = fmt(cost);
      row.card.btn.disabled = state.gold < cost;
    });

    panels.shop.forEach(function (row) {
      var level = shopLevel(state, row.def.id);
      var maxed = level >= row.def.max;
      var cost = shopUpgradeCost(row.def, level);
      row.card.sub.textContent = 'niv. ' + level + ' / ' + row.def.max;
      row.card.btn.textContent = maxed ? 'max' : fmt(cost);
      row.card.btn.disabled = maxed || state.crystals < cost;
    });

    panels.souls.forEach(function (row) {
      var level = soulNode(state, row.def.id);
      var maxed = level >= row.def.max;
      var cost = soulNodeCost(row.def, level);
      row.card.sub.textContent = 'niv. ' + level + ' / ' + row.def.max;
      row.card.btn.textContent = maxed ? 'max' : cost + ' âmes';
      row.card.btn.disabled = maxed || state.souls < cost;
    });

    var pool = $('soul-pool');
    if (pool) pool.textContent = fmt(state.souls);

    var values = [
      ['Chapitre maximum', state.bestChapter],
      ['Puissance record', state.bestPower],
      ['Titans abattus', fmt(state.stats.kills)],
      ['Boss vaincus', fmt(state.stats.bosses)],
      ['Défaites', fmt(state.stats.deaths)],
      ['Or total', fmt(state.stats.goldTotal)],
      ['Renaissances', state.stats.prestiges],
      ['Temps de jeu', fmtTime(state.stats.playtime)]
    ];
    panels.stats.forEach(function (node, i) {
      node.childNodes[0].textContent = values[i][0];
      node.childNodes[1].textContent = values[i][1];
    });

    var gain = prestigeGain(state);
    $('prestige-gain').textContent = fmt(gain);
    $('prestige-info').innerHTML = state.bestPower < PRESTIGE_POWER
      ? 'Atteignez la puissance <b>' + PRESTIGE_POWER + '</b> pour renaître — actuellement <b>'
        + Math.floor(state.bestPower) + '</b>.'
      : 'Bonus actuel : <b>+' + Math.round((soulMult(state) - 1) * 100) + ' %</b>.<br>Après la renaissance : <b>+'
        + Math.round((state.soulsEarned + gain) * 5) + ' %</b>.<br>Vous conservez âmes, arbre des âmes, cristaux et achats de la boutique.';
    $('prestige-btn').disabled = gain <= 0;
    refreshBadges(gain);
  }

  /* Pastilles de la barre d'onglets : un point rouge signale qu'il y a
     quelque chose à dépenser ou à récupérer, sans ouvrir chaque onglet. */
  function refreshBadges(prestige) {
    var counts = {
      'tab-hero': UPGRADES.filter(function (u) { return state.gold >= upgradeCost(u, state.up[u.id]); }).length
        + state.bag.length,
      'tab-team': COMPANIONS.slice(0, panels.teamCount).filter(function (c) {
        return state.gold >= companionCost(c, state.team[c.id]);
      }).length,
      'tab-shop': SHOP_UPGRADES.filter(function (u) {
        return shopLevel(state, u.id) < u.max && state.crystals >= shopUpgradeCost(u, shopLevel(state, u.id));
      }).length,
      'tab-world': (prestige > 0 ? 1 : 0) + SOUL_NODES.filter(function (n) {
        return soulNode(state, n.id) < n.max && state.souls >= soulNodeCost(n, soulNode(state, n.id));
      }).length
    };
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
      var badge = btn.querySelector('.badge');
      if (!badge) return;
      var value = counts[btn.dataset.tab] || 0;
      badge.textContent = value > 9 ? '9+' : value;
      badge.classList.toggle('hidden', value <= 0);
    });
  }

  // ------------------------------------------------------------------
  // Sauvegarde
  // ------------------------------------------------------------------

  function save() {
    state.lastSeen = Date.now();
    try { localStorage.setItem(SAVE_KEY, JSON.stringify(state)); } catch (e) { /* stockage indisponible */ }
  }

  function merge(fresh, data) {
    Object.keys(fresh).forEach(function (key) {
      if (data[key] === undefined) return;
      if (fresh[key] && typeof fresh[key] === 'object' && !Array.isArray(fresh[key])) {
        Object.keys(fresh[key]).forEach(function (sub) {
          if (data[key][sub] !== undefined) fresh[key][sub] = data[key][sub];
        });
      } else {
        fresh[key] = data[key];
      }
    });
    return fresh;
  }

  function load() {
    var raw = null;
    try { raw = localStorage.getItem(SAVE_KEY) || localStorage.getItem(LEGACY_KEY); } catch (e) { return false; }
    if (!raw) return false;
    try {
      var data = JSON.parse(raw);
      state = merge(newState(), data);
      if (!state.online.deviceId) state.online.deviceId = randomId();
      state.chapter = Math.max(1, Math.min(1000, Math.floor(state.chapter) || 1));
      state.bestChapter = Math.max(state.chapter, Math.min(1000, Math.floor(state.bestChapter) || 1));
      state.portrait = Math.max(0, Math.min(23, Math.floor(state.portrait) || 0)); state.fight = Math.max(0, Math.min(fightsIn(state.chapter) - 1, Math.floor(state.fight) || 0));
      return true;
    } catch (e) { return false; }
  }

  function randomId() {
    return 'p' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36).slice(-4);
  }

  function offlineCap(s) { return OFFLINE_CAP + soulNode(s, 'veille') * 7200; }

  function offlineProgress() {
    var cap = offlineCap(state);
    var raw = (Date.now() - state.lastSeen) / 1000;
    var elapsed = Math.min(cap, raw);
    if (elapsed < 60) return;
    var power = currentPower();
    var ramp = fightRamp(Math.floor(fightsIn(state.chapter) / 2), fightsIn(state.chapter));
    var yieldMult = 0.6 * (1 + heroBonus(state, 'offline') + soulNode(state, 'veille') * 0.2);
    var killsPerSec = Math.min(4, totalDps(state) / enemyMaxHp(power, false, ramp, false));
    var kills = Math.floor(killsPerSec * elapsed * yieldMult);
    if (kills <= 0) return;
    var gold = kills * goldReward(power, false, ramp, false) * goldMult(state);
    var xp = kills * xpReward(power, false, ramp, false) * 0.5;

    function grant(mult, label) {
      state.gold += gold * mult;
      state.stats.goldTotal += gold * mult;
      state.stats.kills += Math.floor(kills * mult);
      gainXp(xp * mult);
      if (label) toast(label);
      dirty.panels = true;
      save();
    }

    var capped = raw > cap
      ? '<p class="hint">Plafond atteint (' + fmtTime(cap) + '). L\'arbre des âmes — <b>Veille du bataillon</b> — le repousse de 2 h par niveau.</p>'
      : '';
    var actions = [{ label: 'Reprendre le combat', primary: true, onClick: function () { grant(1); } }];
    if (state.crystals >= DOUBLE_COST) {
      actions.unshift({
        label: 'Doubler · ' + DOUBLE_COST + ' cristaux',
        onClick: function () { state.crystals -= DOUBLE_COST; grant(2, 'Rapport doublé !'); }
      });
    }
    showModal('Rapport d\'expédition',
      '<p>Le bataillon a combattu pendant <b>' + fmtTime(elapsed) + '</b>.</p>'
      + '<div class="report"><span>Titans abattus</span><b>' + fmt(kills) + '</b>'
      + '<span>Or récolté</span><b>' + fmt(gold) + '</b>'
      + '<span>Expérience</span><b>' + fmt(xp) + '</b></div>' + capped, actions);
  }

  function doPrestige() {
    var gain = prestigeGain(state);
    if (gain <= 0) return;
    var keep = {
      souls: state.souls + gain,
      soulsEarned: state.soulsEarned + gain,
      crystals: state.crystals,
      shop: state.shop,
      shopBuys: state.shopBuys,
      autoAdvance: state.autoAdvance,
      autoSkill: state.autoSkill,
      online: state.online,
      stats: state.stats,
      record: state.record,
      journey: state.journey,
      portrait: state.portrait,
      title: state.title,
      soul: state.soul,
      autoBuy: state.autoBuy
    };
    state = newState();
    state.souls = keep.souls;
    state.soulsEarned = keep.soulsEarned;
    state.crystals = keep.crystals;
    state.shop = keep.shop;
    state.shopBuys = keep.shopBuys;
    state.autoAdvance = keep.autoAdvance;
    state.autoSkill = keep.autoSkill;
    state.online = keep.online;
    state.stats = keep.stats;
    state.record = keep.record;
    state.journey = keep.journey;
    state.portrait = keep.portrait;
    state.title = keep.title;
    state.soul = keep.soul;
    state.autoBuy = keep.autoBuy;
    state.stats.prestiges++;
    refreshAutomation();
    state.bestChapter = 1;
    state.bestPower = 0;
    state.hp = maxHp(state);
    log('Renaissance ! +' + gain + ' âmes (total ' + state.souls + ').', 'good');
    toast(' +' + gain + ' âmes');
    dirty.panels = true;
    spawnEnemy();
    save();
    if (window.Online) window.Online.sync(true);
  }

  // ------------------------------------------------------------------
  // Modale, écran de chargement, interface
  // ------------------------------------------------------------------

  function showModal(title, html, actions) {
    $('modal-title').textContent = title;
    $('modal-body').innerHTML = html;
    var box = $('modal-actions');
    box.innerHTML = '';
    (actions || [{ label: 'Fermer' }]).forEach(function (a) {
      var btn = document.createElement('button');
      btn.textContent = a.label;
      if (a.primary) btn.className = 'primary';
      btn.addEventListener('click', function () {
        $('modal').classList.add('hidden');
        if (a.onClick) a.onClick();
      });
      box.appendChild(btn);
    });
    $('modal').classList.remove('hidden');
  }

  /* Les automatismes s'ouvrent avec les renaissances : la case reste visible
     mais dit ce qu'il manque pour l'utiliser. */
  function refreshAutomation() {
    var rows = { autoSkill: 'row-autoskill', autoBuy: 'row-autobuy' };
    var missing = [];
    Object.keys(rows).forEach(function (id) {
      var row = $(rows[id]);
      var ok = autoUnlocked(id);
      row.classList.toggle('locked', !ok);
      row.querySelector('input').disabled = !ok;
      if (!ok) missing.push((id === 'autoSkill' ? 'Compétences automatiques' : 'Entraînement automatique')
        + ' : ' + AUTO_UNLOCK[id] + ' renaissance' + (AUTO_UNLOCK[id] > 1 ? 's' : ''));
    });
    $('auto-hint').textContent = missing.length
      ? 'À débloquer — ' + missing.join(' · ') + '. Vous en êtes à ' + state.stats.prestiges + '.'
      : 'Tous les automatismes sont débloqués.';
  }

  function bindUi() {
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab').forEach(function (t) { t.classList.remove('active'); });
        btn.classList.add('active');
        $(btn.dataset.tab).classList.add('active');
        dirty.panels = true;
        if (btn.dataset.tab === 'tab-world' && window.Online) window.Online.refresh();
      });
    });

    $('chapter-down').addEventListener('click', function () {
      if (state.chapter > 1) { state.chapter--; state.fight = 0; spawnEnemy(); dirty.panels = true; }
    });
    $('chapter-up').addEventListener('click', function () {
      if (state.chapter < state.bestChapter) { state.chapter++; state.fight = 0; spawnEnemy(); dirty.panels = true; }
    });

    /* Le tap n'est plus un simple clic à dégâts fixes : les coups enchaînés
       montent un combo qui les renforce, et le combo retombe si on s'arrête. */
    $('arena').addEventListener('pointerdown', function () {
      // Le combo continue de monter entre deux titans : marteler pendant la
      // réapparition n'est pas puni.
      combo.count = Math.min(30, combo.count + 1);
      combo.timer = 2;
      if (window.Fx) window.Fx.tap();
      if (!enemy || enemy.hp <= 0) return;
      var damage = heroDamage(state) * (0.5 + combo.count * 0.06);
      damageEnemy(damage, combo.count >= 15, true);
      floater(fmt(damage), combo.count >= 15 ? 'crit' : '', 0.1);
    });

    $('prestige-btn').addEventListener('click', function () {
      showModal('Renaître ?', '<p>Vous perdez or, améliorations, recrues et équipement.</p>'
        + '<p>Vous gagnez <b>' + fmt(prestigeGain(state)) + ' âmes</b> permanentes.</p>',
        [{ label: 'Annuler' }, { label: 'Renaître', primary: true, onClick: doPrestige }]);
    });

    $('opt-autoadvance').addEventListener('change', function (e) { state.autoAdvance = e.target.checked; });
    $('opt-autoskill').addEventListener('change', function (e) { state.autoSkill = e.target.checked; });
    $('opt-autobuy').addEventListener('change', function (e) { state.autoBuy = e.target.checked; });

    document.querySelectorAll('.qty').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setBuyQty(btn.dataset.qty === 'max' ? 'max' : Number(btn.dataset.qty));
        refreshPanels();
      });
    });

    $('export-btn').addEventListener('click', function () {
      save();
      showModal('Sauvegarde', '<p>Copiez ce texte pour conserver votre partie :</p><textarea readonly>'
        + btoa(unescape(encodeURIComponent(JSON.stringify(state)))) + '</textarea>');
    });

    $('import-btn').addEventListener('click', function () {
      showModal('Importer', '<p>Collez une sauvegarde exportée :</p><textarea id="import-area"></textarea>',
        [{ label: 'Annuler' }, {
          label: 'Importer', primary: true, onClick: function () {
            try {
              var data = JSON.parse(decodeURIComponent(escape(atob($('import-area').value.trim()))));
              localStorage.setItem(SAVE_KEY, JSON.stringify(data));
              location.reload();
            } catch (e) { toast('Sauvegarde illisible'); }
          }
        }]);
    });

    $('wipe-btn').addEventListener('click', function () {
      showModal('Tout effacer ?', '<p>Cette action supprime définitivement votre progression.</p>',
        [{ label: 'Annuler' }, {
          label: 'Effacer', primary: true, onClick: function () {
            try { localStorage.removeItem(SAVE_KEY); localStorage.removeItem(LEGACY_KEY); } catch (e) { /* ignoré */ }
            location.reload();
          }
        }]);
    });

    document.addEventListener('visibilitychange', function () { if (document.hidden) save(); });
    window.addEventListener('pagehide', save);
  }

  var TIPS = [
    'Un combat sur cinq est une élite : plus de PV, mais trois fois plus d\'or.',
    'Tapez sur le titan pour monter un combo : chaque coup enchaîné frappe plus fort.',
    'Deux pièces identiques dans le sac fusionnent en une rareté supérieure.',
    'Les quatre emplacements équipés dans la même rareté donnent un bonus de panoplie.',
    'L\'arbre des âmes se paie avec les âmes sans jamais réduire votre bonus de renaissance.',
    'Chaque recrue apporte un passif, renforcé de moitié tous les 25 exemplaires.'
  ];

  /* Écran de chargement : la barre suit le vrai chargement des images. */
  function splash(hasSave) {
    var bar = $('splash-bar');
    var status = $('splash-status');
    var tip = $('splash-tip');
    var assets = [
      'logo.webp', 'environments.webp', 'atlas.webp', 'heroes.webp', 'items.webp', 'abilities.webp',
      'enemies/titan.webp', 'enemies/anormal.webp', 'enemies/colossal.webp',
      'enemies/blinde.webp', 'enemies/feminin.webp', 'enemies/bestial.webp'
    ];
    var steps = ['Réveil du bataillon…', 'Affûtage des lames…', 'Chargement du gaz…', 'Ouverture des portes…'];
    var done = 0;
    tip.textContent = TIPS[Math.floor(Math.random() * TIPS.length)];
    status.textContent = steps[0];

    function ready() {
      status.textContent = 'Prêt.';
      bar.style.width = '100%';
      $('splash-start').classList.remove('hidden');
      $('splash-start').textContent = hasSave ? 'CONTINUER' : 'COMMENCER';
    }

    function tick() {
      done++;
      var ratio = done / assets.length;
      bar.style.width = Math.round(ratio * 100) + '%';
      status.textContent = steps[Math.min(steps.length - 1, Math.floor(ratio * steps.length))];
      if (done >= assets.length) ready();
    }

    assets.forEach(function (src) {
      var img = new Image();
      img.onload = img.onerror = tick;
      img.src = src;
    });
    // Filet de sécurité : on n'enferme jamais le joueur sur l'écran de
    // chargement si une image manque ou si le cache répond mal.
    setTimeout(ready, 8000);

    $('splash-start').addEventListener('click', function () {
      $('splash').classList.add('gone');
      setTimeout(function () { $('splash').classList.add('hidden'); }, 420);
      var enter = function () {
        running = true;
        if (hasSave) offlineProgress();
        save();
      };
      if (window.Online && window.Online.shouldGate()) window.Online.showAuth(enter);
      else enter();
    });
  }

  function start() {
    var loaded = load();
    if (!loaded) state.online.deviceId = randomId();
    bindUi();
    if (window.Online) window.Online.init(state, { toast: toast, fmt: fmt, save: save, showModal: showModal });
    $('opt-autoadvance').checked = state.autoAdvance;
    $('opt-autoskill').checked = state.autoSkill;
    $('opt-autobuy').checked = state.autoBuy;
    refreshAutomation();
    if (state.hp <= 0 || state.hp > maxHp(state)) state.hp = maxHp(state);
    spawnEnemy();
    buildPanels();
    log(loaded ? 'Partie chargée — chapitre ' + state.chapter + '.' : 'Bienvenue, cadet. Les portes s\'ouvrent.', 'good');
    splash(loaded);
    setInterval(save, 5000);
    requestAnimationFrame(loop);
  }

  window.__game = {
    get state() { return state; },
    step: step,
    fmt: fmt,
    save: save,
    buyUpgrade: buyUpgrade,
    buyCompanion: buyCompanion,
    buyShopUpgrade: buyShopUpgrade,
    prestige: doPrestige,
    upgrades: UPGRADES,
    companions: COMPANIONS,
    costs: { upgrade: upgradeCost, companion: companionCost },
    dps: function () { return totalDps(state); },
    maxHp: function () { return maxHp(state); },
    power: currentPower,
    travel: function(n) { n = Math.floor(Number(n)); if (!Number.isFinite(n) || n < 1 || n > state.bestChapter || n > 1000) return false; state.chapter=n; state.fight=0; spawnEnemy(); renderCombat(); dirty.panels=true; save(); return true; },
    setPortrait: function(n) { state.portrait = n; save(); },
    modal: showModal,
    /* Rapatriement d'une pièce du dépôt en ligne vers le sac. Le serveur en
       est l'émetteur ; on ne fait que la ranger et vérifier sa forme. */
    addGear: function (item) {
      if (!item || !GEAR_NAMES[item.slot]) return false;
      storeGear({
        slot: item.slot,
        name: String(item.name).slice(0, 48),
        rarity: RARITY_ORDER.indexOf(item.rarity) >= 0 ? item.rarity : 'commun',
        power: Math.max(1, Math.floor(Number(item.power) || 1))
      });
      dirty.panels = true;
      save();
      return true;
    },
    addCrystals: function (amount) {
      var value = Math.max(0, Math.floor(Number(amount) || 0));
      state.crystals += value;
      dirty.panels = true;
      save();
      return value;
    },
    start: function () { running = true; }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();

