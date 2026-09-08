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

  var BOSS_TIME = 45;
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
      journey: { towerBest: 0, towerWins: 0, day: "", dayKills: 0, dayTower: 0, dailyClaimed: false },
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
      gear: {},
      shop: {},
      shopBuys: {},
      cooldowns: {},
      buffs: {},
      autoAdvance: true,
      autoSkill: false,
      lastSeen: Date.now(),
      online: { url: '', pseudo: '', clan: '', deviceId: '', token: '' },
      stats: { kills: 0, bosses: 0, deaths: 0, prestiges: 0, playtime: 0, goldTotal: 0 }
    };
    UPGRADES.forEach(function (u) { s.up[u.id] = 0; });
    COMPANIONS.forEach(function (c) { s.team[c.id] = 0; });
    SLOTS.forEach(function (g) { s.gear[g.id] = null; });
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
    // Au-delà du 30e chapitre, on repart sur les mêmes décors mais la
    // puissance requise continue de grimper (mode sans fin).
    var cycle = Math.floor((chapter - 1) / CHAPTERS.length);
    var index = (chapter - 1) % CHAPTERS.length;
    return (chapter - 1) * 1.1 + fight / fightsIn(chapter);
  }

  function currentPower() { return powerOf(state.chapter, state.fight); }
  function isBossFight() { return state.fight >= fightsIn(state.chapter) - 1; }

  // ------------------------------------------------------------------
  // Formules
  // ------------------------------------------------------------------

  function soulMult(s) { return 1 + (s.souls || 0) * 0.05; }
  function shopLevel(s, id) { return s.shop[id] || 0; }
  function gearBonus(s, slot) { return s.gear[slot] ? s.gear[slot].power : 0; }

  function heroDamage(s) {
    var base = 6 * Math.pow(1.13, s.up.force);
    var gear = 1 + gearBonus(s, 'arme') / 100;
    var shop = 1 + shopLevel(s, 'tridim') * 0.25;
    var rage = s.buffs.rage > 0 ? 2 : 1;
    var lames = s.buffs.lames > 0 ? 1.6 : 1;
    return base * gear * shop * soulMult(s) * rage * lames;
  }

  function attackSpeed(s) {
    var base = 1 + s.up.vitesse * 0.05;
    return Math.min(8, base * (1 + shopLevel(s, 'reservoir') * 0.1) * (s.buffs.gaz > 0 ? 1.5 : 1));
  }

  function critChance(s) { return Math.min(0.75, 0.05 + s.up.crit * 0.012 + gearBonus(s, 'accessoire') / 10000 + shopLevel(s, 'acier') * 0.05); }
  function critMult(s) { return 2 + s.up.crit * 0.05; }
  function armor(s) { return s.up.armure ? 3 * Math.pow(1.16, s.up.armure - 1) : 0; }
  function regenRate(s) { return 0.02 + shopLevel(s, 'vivres') * 0.02; }

  function maxHp(s) {
    var base = (100 + (s.level - 1) * 15) * Math.pow(1.1, s.up.vitalite);
    var bonus = (1 + gearBonus(s, 'armure') / 100) * (1 + shopLevel(s, 'cape') * 0.3);
    return Math.floor(base * bonus * soulMult(s));
  }

  function goldMult(s) {
    var buff = (s.buffs.ruee > 0 ? 3 : 1) * (s.buffs.fumigene > 0 ? 2 : 1);
    return Math.pow(1.08, s.up.fortune) * (1 + gearBonus(s, 'amulette') / 100)
      * (1 + shopLevel(s, 'serum') * 0.3) * soulMult(s) * buff;
  }

  function teamDps(s) {
    var total = 0;
    COMPANIONS.forEach(function (c) {
      var n = s.team[c.id];
      if (n) total += c.dps * n * (1 + Math.floor(n / 25) * 0.5);
    });
    var shop = 1 + shopLevel(s, 'tridim') * 0.25;
    return total * shop * soulMult(s) * (s.buffs.rage > 0 ? 2 : 1) * (s.buffs.lames > 0 ? 1.6 : 1);
  }

  function totalDps(s) {
    var hit = heroDamage(s) * (1 + critChance(s) * (critMult(s) - 1));
    return hit * attackSpeed(s) + teamDps(s);
  }

  function enemyMaxHp(power, boss) { return Math.ceil(12 * Math.pow(1.05, power) * (boss ? 6 : 1)); }
  function enemyDamage(power, boss) { return 3 * Math.pow(1.032, power) * (boss ? 1.6 : 1); }
  function goldReward(power, boss) { return 5 * Math.pow(1.038, power) * (boss ? 10 : 1); }
  function xpReward(power, boss) { return Math.ceil((4 + power * 0.5) * Math.pow(1.02, power) * (boss ? 6 : 1)); }
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

  function floater(text, cls) {
    var box = $('floaters');
    if (box.childNodes.length > 16) return;
    var el = document.createElement('div');
    el.className = 'floater' + (cls ? ' ' + cls : '');
    el.textContent = text;
    el.style.left = (16 + Math.random() * 60) + '%';
    el.style.top = (16 + Math.random() * 32) + '%';
    box.appendChild(el);
    setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 1000);
  }

  // ------------------------------------------------------------------
  // Combat
  // ------------------------------------------------------------------

  function spawnEnemy() {
    var chapter = chapterDef(state.chapter);
    var boss = isBossFight();
    var def = boss ? chapter.boss : chapter.mobs[Math.floor(Math.random() * chapter.mobs.length)];
    var power = currentPower();
    enemy = {
      name: def[0],
      shape: def[1],
      palette: boss ? (def[2] || chapter.pal) : chapter.pal,
      boss: boss,
      hp: enemyMaxHp(power, boss),
      maxHp: enemyMaxHp(power, boss),
      dmg: enemyDamage(power, boss)
    };
    bossTimer = boss ? BOSS_TIME : 0;
    enemyTimer = 1.4;
    flash = 0;
    $('boss-tag').classList.toggle('hidden', !boss);
    $('boss-timer').classList.toggle('hidden', !boss);
    var arena = $('arena');
    arena.style.background = 'radial-gradient(120% 80% at 50% 0%, ' + chapter.sky[0] + ' 0%, ' + chapter.sky[1] + ' 75%)';
    renderCombat();
  }

  function damageEnemy(amount, crit) {
    if (!enemy || enemy.hp <= 0) return;
    enemy.hp -= amount;
    if (window.Remaster) window.Remaster.hit(crit);
    if (amount > 0) {
      floater((crit ? '✦ ' : '') + fmt(amount), crit ? 'crit' : '');
      flash = 1;
    }
    if (enemy.hp <= 0) killEnemy();
  }

  function killEnemy() {
    if (window.Remaster) window.Remaster.sound('win');
    var boss = enemy.boss;
    var power = currentPower();
    var gold = goldReward(power, boss) * goldMult(state);
    state.gold += gold;
    state.stats.goldTotal += gold;
    goldWindow.gold += gold;
    state.stats.kills++;
    gainXp(xpReward(power, boss));
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
        var crystals = 2 + Math.floor(state.chapter / 2);
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
      }
    } else {
      state.fight++;
    }
    if (currentPower() > state.bestPower) state.bestPower = currentPower();
    dirty.panels = true;
    renderCombat();
  }

  function gainXp(amount) {
    state.xp += amount;
    var guard = 0;
    while (state.xp >= xpNeeded(state.level) && guard++ < 500) {
      state.xp -= xpNeeded(state.level);
      state.level++;
      state.hp = maxHp(state);
      log('Niveau ' + state.level + ' atteint !', 'good');
      floater('Niveau ' + state.level, 'heal');
      dirty.panels = true;
    }
  }

  function dropGear() {
    if (Math.random() > 0.6) return;
    var slot = SLOTS[Math.floor(Math.random() * SLOTS.length)];
    var roll = Math.random(), acc = 0, rarity = RARITIES[0];
    for (var i = 0; i < RARITIES.length; i++) {
      acc += RARITIES[i].chance;
      if (roll <= acc) { rarity = RARITIES[i]; break; }
    }
    var names = GEAR_NAMES[slot.id];
    var tier = Math.min(names.length - 1, Math.floor((state.chapter - 1) / 160));
    var power = Math.ceil((8 + currentPower() * 0.8) * rarity.mult);
    var item = { name: names[tier], rarity: rarity.name, power: power };
    if (window.Remaster) window.Remaster.sound('loot');
    var current = state.gear[slot.id];
    if (!current || item.power > current.power) {
      state.gear[slot.id] = item;
      state.hp = Math.min(state.hp, maxHp(state));
      log('Équipé : ' + item.name + ' (' + rarity.name + ', +' + power + ' %)', 'loot');
      toast('Nouvel équipement : ' + item.name);
    } else {
      var sold = Math.ceil(power * goldReward(currentPower(), false) * 0.3);
      state.gold += sold;
      log('Butin revendu (+' + fmt(sold) + ' or)', 'loot');
    }
    dirty.panels = true;
  }

  function heroDies() {
    state.stats.deaths++;
    state.hp = maxHp(state);
    state.fight = Math.max(0, state.fight - 3);
    if (state.fight === 0 && state.chapter > 1 && state.stats.deaths % 3 === 0) state.chapter--;
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
    state.cooldowns[id] = skill.cd;
    if (window.Remaster) window.Remaster.sound(id === 'elixir' ? 'heal' : 'skill');
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

  function step(dt) {
    if (window.Hub && window.Hub.inTower()) return;
    state.stats.playtime += dt;
    goldWindow.time += dt;
    if (goldWindow.time > 10) { goldWindow.time = 5; goldWindow.gold *= 0.5; }
    panelTimer += dt;
    if (panelTimer >= 0.5) {
      panelTimer = 0;
      if (!$('tab-combat').classList.contains('active')) refreshPanels();
    }
    if (flash > 0) flash = Math.max(0, flash - dt * 9);

    SKILLS.forEach(function (k) {
      if (state.cooldowns[k.id] > 0) state.cooldowns[k.id] = Math.max(0, state.cooldowns[k.id] - dt);
      if (state.buffs[k.id] > 0) state.buffs[k.id] = Math.max(0, state.buffs[k.id] - dt);
      if (state.autoSkill && state.cooldowns[k.id] === 0 && state.level >= k.level) useSkill(k.id);
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
      var taken = Math.max(enemy.dmg * 0.15, enemy.dmg - armor(state));
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
      renderCombat();
      renderSprites(now);
      if (dirty.panels) { refreshPanels(); dirty.panels = false; }
    }
    requestAnimationFrame(loop);
  }

  // ------------------------------------------------------------------
  // Rendu
  // ------------------------------------------------------------------

  function setBar(fillId, textId, cur, max, unit) {
    $(fillId).style.width = Math.max(0, Math.min(100, (cur / max) * 100)) + '%';
    $(textId).textContent = fmt(Math.max(0, cur)) + ' / ' + fmt(max) + (unit || '');
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
    $('gold').textContent = fmt(state.gold);
    $('crystals').textContent = fmt(state.crystals);
    $('souls').textContent = fmt(state.souls);
    $('hero-level').textContent = state.level;
    setBar('xp-fill', 'xp-text', state.xp, xpNeeded(state.level), ' XP');
    setBar('hero-hp-fill', 'hero-hp-text', state.hp, maxHp(state));

    var chapter = chapterDef(state.chapter);
    $('chapter-name').textContent = chapter.name;
    var cycle = Math.floor((state.chapter - 1) / CHAPTERS.length);
    $('chapter-label').textContent = 'Chapitre ' + state.chapter
      + (cycle ? ' · cycle ' + (cycle + 1) : ' / ' + CHAPTERS.length);
    $('fight-label').textContent = 'Combat ' + Math.min(state.fight + 1, chapter.fights) + ' / ' + chapter.fights;
    $('chapter-down').disabled = state.chapter <= 1;
    $('chapter-up').disabled = state.chapter >= state.bestChapter;

    if (enemy) {
      $('enemy-name').textContent = enemy.name;
      setBar('enemy-hp-fill', 'enemy-hp-text', Math.ceil(enemy.hp), enemy.maxHp);
      if (enemy.boss) $('boss-timer').textContent = ' ' + bossTimer.toFixed(1) + ' s';
    }

    $('dps-value').textContent = fmt(totalDps(state));
    $('gps-value').textContent = fmt(goldWindow.gold / Math.max(1, goldWindow.time));

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

  function revealedCompanions() {
    var lastOwned = -1;
    COMPANIONS.forEach(function (c, i) { if (state.team[c.id] > 0) lastOwned = i; });
    return COMPANIONS.length;
  }

  var panels = null;

  /* Les panneaux sont construits une fois puis mis à jour en place : recréer
     un bouton sous le doigt ferait perdre l'appui. */
  function buildPanels() {
    panels = { upgrades: [], team: [], gear: [], stats: [], consumables: [], shop: [], teamCount: revealedCompanions() };

    var upBox = $('upgrades');
    upBox.innerHTML = '';
    UPGRADES.forEach(function (u) {
      var card = makeCard(u.icon, true, function () { buyUpgrade(u.id); refreshPanels(); });
      card.title.textContent = u.name;
      card.desc.textContent = u.desc;
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
      var card = makeCard(c.icon, true, function () { buyCompanion(c.id); refreshPanels(); });
      card.title.textContent = c.name; card.el.dataset.heroRarity = c.rarity;
      teamBox.appendChild(card.el);
      panels.team.push({ def: c, card: card });
    });

    var consBox = $('shop-consumables');
    consBox.innerHTML = '';
    SHOP_CONSUMABLES.forEach(function (c) {
      var card = makeCard(c.icon, true, function () { buyConsumable(c.id); refreshPanels(); });
      card.title.textContent = c.name; card.el.dataset.heroRarity = c.rarity;
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

    refreshPanels();
  }

  function refreshPanels() {
    if (!panels) { buildPanels(); return; }
    if (panels.teamCount !== revealedCompanions()) { buildPanels(); return; }

    panels.upgrades.forEach(function (row) {
      var cost = upgradeCost(row.def, state.up[row.def.id]);
      row.card.sub.textContent = 'niv. ' + state.up[row.def.id];
      row.card.btn.textContent = ' ' + fmt(cost);
      row.card.btn.disabled = state.gold < cost;
    });

    panels.gear.forEach(function (row) {
      var item = state.gear[row.def.id];
      row.card.title.textContent = item ? item.name : 'Aucun';
      var gi = item ? GEAR_NAMES[row.def.id].indexOf(item.name) : -1;
      var artIndex = gi < 0 ? {arme:22,armure:23,accessoire:24,amulette:25}[row.def.id] : {arme:0,armure:7,accessoire:18,amulette:13}[row.def.id]+gi;
      var iconBox = row.card.el.querySelector('i'); if(iconBox.dataset.itemArt !== String(artIndex)){iconBox.innerHTML=Art.icon('items',artIndex);iconBox.dataset.itemArt=artIndex;}
      row.card.el.dataset.rarity = item ? item.rarity : '';
      row.card.title.className = 'c-title' + (item ? ' rarity-' + item.rarity : '');
      row.card.sub.textContent = item ? item.rarity : row.def.name;
      row.card.desc.textContent = item ? '+' + (row.def.id === 'accessoire' ? (item.power / 100).toFixed(2) + ' points de critique (plafond 75 %)' : item.power + ' % ' + row.def.stat) : 'Les boss lâchent de l\'équipement';
    });

    panels.team.forEach(function (row) {
      var count = state.team[row.def.id];
      var cost = companionCost(row.def, count);
      var each = row.def.dps * (1 + Math.floor(count / 25) * 0.5) * soulMult(state);
      row.card.sub.textContent = row.def.rarity + ' · ' + (count ? '×' + count : 'À recruter');
      row.card.desc.textContent = count
        ? fmt(each * count) + ' DPS — palier suivant à ' + ((Math.floor(count / 25) + 1) * 25)
        : fmt(row.def.dps * soulMult(state)) + ' DPS par recrue';
      row.card.btn.textContent = ' ' + fmt(cost);
      row.card.btn.disabled = state.gold < cost;
    });

    panels.consumables.forEach(function (row) {
      var cost = consumableCost(row.def, state.shopBuys[row.def.id] || 0);
      var active = row.def.buff && state.buffs[row.def.buff] > 0;
      row.card.sub.textContent = active ? Math.ceil(state.buffs[row.def.buff]) + ' s' : '';
      row.card.btn.textContent = ' ' + fmt(cost);
      row.card.btn.disabled = state.gold < cost;
    });

    panels.shop.forEach(function (row) {
      var level = shopLevel(state, row.def.id);
      var maxed = level >= row.def.max;
      var cost = shopUpgradeCost(row.def, level);
      row.card.sub.textContent = 'niv. ' + level + ' / ' + row.def.max;
      row.card.btn.textContent = maxed ? 'max' : ' ' + fmt(cost);
      row.card.btn.disabled = maxed || state.crystals < cost;
    });

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
      ? 'Atteignez la puissance <b>' + PRESTIGE_POWER + '</b> (chapitre 3) pour renaître — actuellement <b>' + state.bestPower + '</b>.'
      : 'Bonus actuel : <b>+' + Math.round((soulMult(state) - 1) * 100) + ' %</b>.<br>Après la renaissance : <b>+'
        + Math.round((state.souls + gain) * 5) + ' %</b>.<br>Vous conservez âmes, cristaux et achats de la boutique.';
    $('prestige-btn').disabled = gain <= 0;
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

  function offlineProgress() {
    var elapsed = Math.min(OFFLINE_CAP, (Date.now() - state.lastSeen) / 1000);
    if (elapsed < 60) return;
    var power = currentPower();
    var killsPerSec = Math.min(4, totalDps(state) / enemyMaxHp(power, false));
    var kills = Math.floor(killsPerSec * elapsed * 0.6);
    if (kills <= 0) return;
    var gold = kills * goldReward(power, false) * goldMult(state);
    var xp = kills * xpReward(power, false) * 0.5;
    state.gold += gold;
    state.stats.goldTotal += gold;
    state.stats.kills += kills;
    gainXp(xp);
    showModal('Rapport d\'expédition', '<p>Le bataillon a combattu pendant <b>' + fmtTime(elapsed) + '</b>.</p>'
      + '<p> Titans abattus : <b>' + fmt(kills) + '</b><br> Or récolté : <b>' + fmt(gold) + '</b><br> Expérience : <b>' + fmt(xp) + '</b></p>',
      [{ label: 'Reprendre le combat', primary: true }]);
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
      portrait: state.portrait
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
    state.stats.prestiges++;
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

    $('arena').addEventListener('click', function () { damageEnemy(heroDamage(state) * 0.5, false); });

    $('prestige-btn').addEventListener('click', function () {
      showModal('Renaître ?', '<p>Vous perdez or, améliorations, recrues et équipement.</p>'
        + '<p>Vous gagnez <b>' + fmt(prestigeGain(state)) + ' âmes</b> permanentes.</p>',
        [{ label: 'Annuler' }, { label: 'Renaître', primary: true, onClick: doPrestige }]);
    });

    $('opt-autoadvance').addEventListener('change', function (e) { state.autoAdvance = e.target.checked; });
    $('opt-autoskill').addEventListener('change', function (e) { state.autoSkill = e.target.checked; });

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

  /* Écran de chargement : barre de progression puis bouton d'entrée. */
  function splash(hasSave) {
    var bar = $('splash-bar');
    var status = $('splash-status');
    var steps = ['Réveil du bataillon…', 'Affûtage des lames…', 'Chargement du gaz…', 'Ouverture des portes…'];
    var progress = 0;
    var index = 0;
    status.textContent = steps[0];
    var timer = setInterval(function () {
      progress += 6 + Math.random() * 12;
      if (progress >= 100) {
        progress = 100;
        clearInterval(timer);
        status.textContent = 'Prêt.';
        $('splash-start').classList.remove('hidden');
        $('splash-start').textContent = hasSave ? 'CONTINUER' : 'COMMENCER';
      } else {
        var next = Math.min(steps.length - 1, Math.floor(progress / 25));
        if (next !== index) { index = next; status.textContent = steps[index]; }
      }
      bar.style.width = progress + '%';
    }, 110);

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
    start: function () { running = true; }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();

