/* AOT IDLE — accueil, ordres du jour et Tour de combat.

   L'accueil est la première chose que voit le joueur : il dit où il en est et
   ce qu'il a à récupérer. La Tour est le défi solo : 200 étages, un gardien
   tous les dix, et depuis la v6 un modificateur par étage plus un point faible
   à toucher — de quoi jouer plutôt que regarder. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var fight = null;
  var lastTick = 0;
  var lastRender = 0;
  var ready = false;

  function game() { return window.__game; }
  function journey() { return game().state.journey; }
  function today() { return new Date().toISOString().slice(0, 10); }

  // ------------------------------------------------------------------
  // Ordres du jour
  // ------------------------------------------------------------------

  /* Trois objectifs par jour, tous récompensés séparément, plus une série de
     connexions : revenir chaque jour vaut mieux qu'une longue session unique. */
  var MISSIONS = [
    { id: 'kills', goal: 25, reward: 8, label: 'ennemis abattus en campagne' },
    { id: 'tower', goal: 3, reward: 10, label: 'étages de la Tour conquis' },
    { id: 'bosses', goal: 2, reward: 12, label: 'boss de chapitre vaincus' }
  ];

  function daily() {
    var j = journey();
    var s = game().state;
    if (j.day !== today()) {
      // Nouveau jour : on note les compteurs de départ et on avance la série
      // si la dernière visite datait de la veille.
      var yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10);
      j.streak = j.day === yesterday ? (j.streak || 0) + 1 : 1;
      j.day = today();
      j.dayKills = s.stats.kills;
      j.dayTower = j.towerWins;
      j.dayBosses = s.stats.bosses;
      j.claimed = {};
      j.dailyClaimed = false;
      game().save();
    }
    if (!j.claimed) j.claimed = {};
    return {
      kills: Math.max(0, s.stats.kills - j.dayKills),
      tower: Math.max(0, j.towerWins - j.dayTower),
      bosses: Math.max(0, s.stats.bosses - (j.dayBosses || 0)),
      streak: j.streak || 1
    };
  }

  /* La série multiplie les récompenses, jusqu'au double au septième jour. */
  function streakMult(streak) { return 1 + Math.min(6, streak - 1) * 0.17; }

  function claim(id) {
    var progress = daily();
    var mission = MISSIONS.filter(function (m) { return m.id === id; })[0];
    var j = journey();
    if (!mission || j.claimed[id] || progress[id] < mission.goal) return;
    var reward = Math.round(mission.reward * streakMult(progress.streak));
    j.claimed[id] = true;
    game().state.crystals += reward;
    game().save();
    if (window.Fx) { window.Fx.sound('loot'); window.Fx.banner('+' + reward + ' cristaux', 'ORDRE DU JOUR', 'loot'); }
    render();
  }

  function pendingRewards() {
    var progress = daily();
    var j = journey();
    return MISSIONS.filter(function (m) {
      return !j.claimed[m.id] && progress[m.id] >= m.goal;
    }).length;
  }

  // ------------------------------------------------------------------
  // Tour de combat
  // ------------------------------------------------------------------

  /* Un modificateur par étage, choisi par le numéro d'étage : deux joueurs au
     même étage rencontrent la même épreuve. */
  var MODIFIERS = [
    { id: 'aucun', name: 'Terrain dégagé', desc: 'Aucune contrainte : un duel franc.' },
    { id: 'brouillard', name: 'Brouillard', desc: 'Vos dégâts continus tombent de 30 %, mais l\'Assaut frappe deux fois plus fort.' },
    { id: 'blindage', name: 'Blindage', desc: 'Dégâts divisés par deux tant que le point faible n\'est pas touché.' },
    { id: 'furie', name: 'Furie', desc: 'L\'ennemi frappe 40 % plus fort, mais a un quart de PV en moins.' },
    { id: 'usure', name: 'Guerre d\'usure', desc: 'Le titan régénère 1 % de ses PV par seconde. Il faut aller vite.' }
  ];

  function modifierOf(floor) { return MODIFIERS[floor % MODIFIERS.length]; }

  function start() {
    if (fight && fight.active) return;
    var floor = journey().towerBest + 1;
    if (floor > 200) return;
    var boss = floor % 10 === 0;
    var modifier = modifierOf(floor);
    var hp = Math.round(28 * Math.pow(1.17, floor - 1) * (boss ? 2.2 : 1) * (modifier.id === 'furie' ? 0.75 : 1));
    fight = {
      active: true, floor: floor, boss: boss, modifier: modifier,
      hp: hp, max: hp, self: 100, elapsed: 0,
      strike: 0, heal: 0, guard: 0, guardUntil: 0,
      dps: Math.max(1, game().dps()) * (modifier.id === 'brouillard' ? 0.7 : 1),
      damage: (4 + floor * 0.12) * (modifier.id === 'furie' ? 1.4 : 1),
      exposed: 0, weakAt: 3 + Math.random() * 3, weakUntil: 0, weakHits: 0,
      result: ''
    };
    $('tower-enemy').src = 'enemies/' + (boss
      ? ['colossal', 'blinde', 'feminin', 'bestial'][Math.floor(floor / 10 - 1) % 4]
      : floor % 3 === 0 ? 'anormal' : 'titan') + '.webp';
    $('tower-result').textContent = '';
    lastTick = performance.now();
    if (window.Fx) window.Fx.sound(boss ? 'boss' : 'ui');
    render();
  }

  function end(win) {
    if (!fight || !fight.active) return;
    fight.active = false;
    $('tower-weak').hidden = true;
    if (!win) {
      fight.result = 'Repli du bataillon. Renforcez votre escouade, puis réessayez cet étage.';
      if (window.Fx) window.Fx.sound('defeat');
      render();
      return;
    }
    var j = journey();
    if (fight.floor === j.towerBest + 1) {
      j.towerBest = fight.floor;
      j.towerWins++;
      var gold = Math.round(60 * Math.pow(1.13, fight.floor - 1));
      var crystals = fight.boss ? 5 : 1;
      game().state.gold += gold;
      game().state.stats.goldTotal += gold;
      game().state.crystals += crystals;
      game().save();
      fight.result = 'Étage conquis ! +' + game().fmt(gold) + ' or · +' + crystals + ' cristaux';
      if (window.Fx) { window.Fx.sound('win'); window.Fx.banner('Étage ' + fight.floor, 'CONQUIS', 'level'); }
    }
    render();
  }

  function tick(dt) {
    if (!fight || !fight.active || document.hidden) return;
    var duel = $('live-duel');
    if (duel && !duel.hidden) return;
    dt = Math.min(0.15, Math.max(0, dt));
    fight.elapsed += dt;

    // Point faible : une fenêtre de tir à saisir, qui ouvre la garde du titan.
    if (fight.weakUntil <= fight.elapsed && fight.elapsed >= fight.weakAt) {
      fight.weakUntil = fight.elapsed + 2.5;
      fight.weakAt = fight.elapsed + 6 + Math.random() * 3;
      $('tower-weak').hidden = false;
      $('tower-weak').style.left = (15 + Math.random() * 60) + '%';
      $('tower-weak').style.top = (12 + Math.random() * 55) + '%';
    }
    if (fight.weakUntil && fight.elapsed > fight.weakUntil) $('tower-weak').hidden = true;
    if (fight.exposed > 0) fight.exposed -= dt;

    var armored = fight.modifier.id === 'blindage' && fight.exposed <= 0;
    fight.hp = Math.max(0, fight.hp - fight.dps * (armored ? 0.5 : 1) * dt);
    if (fight.modifier.id === 'usure') fight.hp = Math.min(fight.max, fight.hp + fight.max * 0.01 * dt);
    fight.self = Math.max(0, fight.self - fight.damage * (fight.elapsed < fight.guardUntil ? 0.25 : 1) * dt);
    if (fight.self <= 0) end(false);
    else if (fight.hp <= 0) end(true);
  }

  function hitWeakPoint() {
    if (!fight || !fight.active || $('tower-weak').hidden) return;
    $('tower-weak').hidden = true;
    fight.weakUntil = 0;
    fight.weakHits++;
    fight.exposed = 5;
    fight.hp = Math.max(0, fight.hp - fight.dps * 4);
    if (window.Fx) window.Fx.sound('crit');
    flashEnemy();
    if (fight.hp <= 0) end(true);
    render();
  }

  function flashEnemy() {
    var img = $('tower-enemy');
    img.classList.remove('duel-hit');
    void img.offsetWidth;
    img.classList.add('duel-hit');
  }

  function skill(name) {
    if (!fight || !fight.active || fight.elapsed < fight[name]) return;
    if (name === 'strike') {
      fight.strike = fight.elapsed + 4;
      var power = fight.modifier.id === 'brouillard' ? 6 : 3;
      fight.hp = Math.max(0, fight.hp - fight.dps * power);
      flashEnemy();
      if (window.Fx) window.Fx.sound('skill');
      if (fight.hp <= 0) end(true);
    } else if (name === 'heal') {
      fight.heal = fight.elapsed + 12;
      fight.self = Math.min(100, fight.self + 28);
      if (window.Fx) window.Fx.sound('heal');
    } else if (name === 'guard') {
      fight.guard = fight.elapsed + 8;
      fight.guardUntil = fight.elapsed + 3;
      if (window.Fx) window.Fx.sound('ui');
    }
    render();
  }

  // ------------------------------------------------------------------
  // Navigation
  // ------------------------------------------------------------------

  function show(id) {
    document.querySelector('main').scrollTop = 0;
    var target = document.getElementById(id);
    if (target) target.scrollTop = 0;
    document.querySelectorAll('.tab').forEach(function (t) { t.classList.toggle('active', t.id === id); });
    document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.toggle('active', b.dataset.tab === id); });
  }

  function home() { show('tab-home'); render(); }

  function route(where) {
    if (where === 'home') { home(); return; }
    if (where === 'tower') { show('tab-tower'); render(); return; }
    var tab = where === 'campaign' ? 'tab-combat' : 'tab-world';
    document.querySelector('[data-tab="' + tab + '"]').click();
    if (where === 'arena' || where === 'rank') {
      document.querySelector('[data-social-nav="' + where + '"]').click();
    }
  }

  // ------------------------------------------------------------------
  // Rendu
  // ------------------------------------------------------------------

  function render() {
    if (!ready || !game()) return;
    var j = journey();
    var progress = daily();
    var s = game().state;

    $('home-pseudo').textContent = s.online.pseudo || Art.names[s.portrait] || 'Soldat';
    $('home-chapter').textContent = 'Chapitre ' + s.chapter + ' / 1 000';
    $('home-tower').textContent = j.towerBest + ' / 200 étages conquis';
    $('home-campaign-progress').value = Math.max(0, s.record.chapter);
    $('home-tower-progress').value = j.towerBest;
    $('home-streak').textContent = 'Série de ' + progress.streak + ' jour' + (progress.streak > 1 ? 's' : '')
      + ' · récompenses ×' + streakMult(progress.streak).toFixed(2);

    var box = $('daily-list');
    MISSIONS.forEach(function (mission, i) {
      var row = box.children[i];
      var value = progress[mission.id];
      var done = value >= mission.goal;
      var claimed = !!j.claimed[mission.id];
      row.querySelector('span').textContent = Math.min(value, mission.goal) + ' / ' + mission.goal + ' ' + mission.label;
      row.querySelector('progress').value = Math.min(value, mission.goal);
      var button = row.querySelector('button');
      button.disabled = claimed || !done;
      button.textContent = claimed ? 'Récupéré'
        : '+' + Math.round(mission.reward * streakMult(progress.streak)) + ' cristaux';
    });

    var pending = pendingRewards();
    $('home-daily-badge').textContent = pending;
    $('home-daily-badge').classList.toggle('hidden', pending === 0);

    var floor = fight ? fight.floor : j.towerBest + 1;
    var modifier = modifierOf(Math.min(200, floor));
    $('tower-floor').textContent = j.towerBest >= 200 ? 'Tour terminée' : 'Étage ' + floor + ' / 200';
    $('tower-best').textContent = 'Record permanent : ' + j.towerBest + ' étages';
    $('tower-modifier').innerHTML = '<b>' + modifier.name + '</b> — ' + modifier.desc;
    $('tower-start').hidden = !!(fight && fight.active);
    $('tower-start').disabled = j.towerBest >= 200;
    $('tower-start').textContent = j.towerBest >= 200 ? 'Les 200 étages sont conquis'
      : fight && fight.result && fight.self > 0 ? 'Étage suivant' : 'Lancer le combat';
    $('tower-result').textContent = fight ? fight.result : '';
    $('tower-exit').textContent = fight && fight.active ? 'Abandonner et rentrer' : 'Retour à l\'accueil';
    $('tower-enemy-hp').value = fight ? 100 * fight.hp / fight.max : 100;
    $('tower-self-hp').value = fight ? fight.self : 100;
    $('tower-values').textContent = fight
      ? 'Ennemi : ' + Math.ceil(fight.hp) + ' / ' + fight.max + ' PV · Escouade : ' + Math.ceil(fight.self) + ' %'
        + (fight.weakHits ? ' · ' + fight.weakHits + ' point' + (fight.weakHits > 1 ? 's' : '') + ' faible touché' : '')
      : 'Votre puissance d\'escouade détermine vos dégâts.';
    $('tower-type').textContent = fight && fight.boss ? 'GARDIEN · RÉCOMPENSE BONUS' : 'EXPÉDITION SOLO · HORS LIGNE';

    ['strike', 'guard', 'heal'].forEach(function (key) {
      var button = $('tower-' + key);
      var cd = fight ? Math.max(0, Math.ceil(fight[key] - fight.elapsed)) : 0;
      button.disabled = !fight || !fight.active || cd > 0;
      button.querySelector('span').textContent =
        { strike: 'Assaut', guard: 'Garde', heal: 'Soin' }[key] + (cd ? ' · ' + cd + ' s' : '');
    });
  }

  window.Hub = {
    inTower: function () { return !!(fight && fight.active); },
    home: home,
    route: route,
    pending: pendingRewards
  };

  // ------------------------------------------------------------------
  // Construction des écrans
  // ------------------------------------------------------------------

  document.addEventListener('DOMContentLoaded', function () {
    var missionRows = MISSIONS.map(function (m) {
      return '<div class="daily-row"><span></span><progress max="' + m.goal + '" value="0"></progress>'
        + '<button class="buy-btn" data-mission="' + m.id + '"></button></div>';
    }).join('');

    var home = document.createElement('section');
    home.id = 'tab-home';
    home.className = 'tab';
    home.innerHTML = '<header class="home-banner">'
      + '<img class="brand-logo" src="logo.webp" alt="Idle AOT">'
      + '<p>LE DESTIN DE L\'HUMANITÉ VOUS ATTEND</p>'
      + '<h1>Bienvenue, <span id="home-pseudo">Soldat</span></h1>'
      + '<small id="home-streak"></small></header>'
      + '<button class="destination campaign-destination" data-destination="campaign">'
      + '<small>L\'AVENTURE PRINCIPALE</small><h2>Campagne</h2><p id="home-chapter"></p>'
      + '<progress id="home-campaign-progress" max="1000"></progress>'
      + '<span>Reprendre l\'expédition →</span></button>'
      + '<div class="destination-grid">'
      + '<button class="destination" data-destination="arena">' + Art.icon('abilities', 6)
      + '<h2>Arène</h2><p>Duels en temps réel</p><span>Affronter un joueur →</span></button>'
      + '<button class="destination" data-destination="rank">' + Art.icon('abilities', 20)
      + '<h2>Classement</h2><p>Les meilleurs du monde</p><span>Voir les rangs →</span></button></div>'
      + '<button class="destination tower-destination" data-destination="tower">'
      + '<small>DÉFI SOLO</small><h2>Tour de combat</h2><p id="home-tower"></p>'
      + '<progress id="home-tower-progress" max="200"></progress>'
      + '<span>Gravir les 200 étages →</span></button>'
      + '<section class="daily-card"><small>ORDRES DU JOUR</small>'
      + '<h2>Missions du bataillon <b id="home-daily-badge" class="badge hidden">0</b></h2>'
      + '<div id="daily-list">' + missionRows + '</div>'
      + '<p class="hint">Trois objectifs, trois récompenses, renouvelés chaque jour à 00:00 UTC. '
      + 'Revenir chaque jour fait monter la série et multiplie les cristaux.</p></section>';
    document.querySelector('main').prepend(home);

    var tower = document.createElement('section');
    tower.id = 'tab-tower';
    tower.className = 'tab';
    tower.innerHTML = '<header class="tower-heading"><small id="tower-type"></small>'
      + '<h1 id="tower-floor"></h1><p id="tower-best"></p></header>'
      + '<p id="tower-modifier" class="tower-modifier"></p>'
      + '<div class="tower-arena"><img id="tower-enemy" src="enemies/titan.webp" alt="Adversaire de la Tour de combat">'
      + '<button id="tower-weak" hidden aria-label="Frapper le point faible">✦</button>'
      + '<progress id="tower-enemy-hp" max="100" aria-label="Vie de l\'ennemi"></progress></div>'
      + '<p id="tower-values"></p>'
      + '<progress id="tower-self-hp" max="100" aria-label="Vie de votre escouade"></progress>'
      + '<div class="tower-skills">'
      + '<button id="tower-strike">' + Art.icon('abilities', 6) + '<span>Assaut</span></button>'
      + '<button id="tower-guard">' + Art.icon('abilities', 2) + '<span>Garde</span></button>'
      + '<button id="tower-heal">' + Art.icon('abilities', 8) + '<span>Soin</span></button></div>'
      + '<p id="tower-result" role="status"></p>'
      + '<button id="tower-start" class="big-btn">Lancer le combat</button>'
      + '<button id="tower-exit" class="ghost-btn">Retour à l\'accueil</button>'
      + '<p class="hint">Un gardien tous les 10 étages, un modificateur à chaque étage. '
      + 'Touchez le point faible dès qu\'il apparaît : gros dégâts, et la garde du titan tombe pour 5 secondes. '
      + 'Récompense unique au premier passage ; record conservé après une renaissance. '
      + 'La campagne est en pause pendant le duel.</p>';
    document.querySelector('main').append(tower);

    var homeButton = document.createElement('button');
    homeButton.className = 'tab-btn';
    homeButton.dataset.tab = 'tab-home';
    homeButton.innerHTML = '<i><img src="logo.webp" alt=""></i><span>Accueil</span><b class="badge hidden"></b>';
    homeButton.onclick = window.Hub.home;
    document.querySelector('.tabbar').prepend(homeButton);

    document.querySelectorAll('[data-destination]').forEach(function (b) {
      b.onclick = function () { route(b.dataset.destination); };
    });
    document.querySelectorAll('[data-mission]').forEach(function (b) {
      b.onclick = function () { claim(b.dataset.mission); };
    });
    $('tower-start').onclick = start;
    $('tower-weak').onclick = hitWeakPoint;
    ['strike', 'guard', 'heal'].forEach(function (key) {
      $('tower-' + key).onclick = function () { skill(key); };
    });
    $('tower-exit').onclick = function () {
      if (fight && fight.active
        && !confirm('Abandonner cet étage ? Les étages déjà conquis sont conservés.')) return;
      if (fight) fight.active = false;
      $('tower-weak').hidden = true;
      window.Hub.home();
    };

    // Quitter la Tour par la barre d'onglets laisserait un combat en cours.
    document.querySelector('.tabbar').addEventListener('click', function (e) {
      if (fight && fight.active && e.target.closest('.tab-btn')) {
        show('tab-tower');
        $('tower-result').textContent = 'Utilisez « Abandonner et rentrer » pour quitter le combat.';
      }
    });

    ready = true;
    window.Hub.home();
    requestAnimationFrame(function frame(now) {
      var dt = lastTick ? (now - lastTick) / 1000 : 0;
      lastTick = now;
      tick(dt);
      if (now - lastRender > 100) {
        lastRender = now;
        var homeTab = $('tab-home');
        var badge = document.querySelector('[data-tab="tab-home"] .badge');
        if (badge) {
          var pending = pendingRewards();
          badge.textContent = pending;
          badge.classList.toggle('hidden', pending === 0);
        }
        if (homeTab.classList.contains('active') || $('tab-tower').classList.contains('active')) render();
      }
      requestAnimationFrame(frame);
    });
  });
})();
