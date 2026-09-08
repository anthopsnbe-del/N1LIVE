/* AOT IDLE — hauts faits : des objectifs longs, récompensés une fois.

   Les titres disent qui vous êtes, les hauts faits disent ce que vous avez
   fait — et paient en cristaux. Tout est vérifié sur la sauvegarde locale : ce
   sont des jalons de campagne, pas des exploits en ligne. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var ready = false;

  function game() { return window.__game; }

  /* Chaque haut fait : un compteur, des paliers, une récompense par palier. */
  var DEEDS = [
    { id: 'kills', name: 'Chasse aux titans', unit: 'ennemis abattus',
      steps: [100, 1000, 10000, 100000], reward: [10, 25, 60, 150],
      value: function (s) { return s.stats.kills; } },
    { id: 'bosses', name: 'Tombeur de boss', unit: 'boss de chapitre vaincus',
      steps: [10, 50, 200, 500], reward: [10, 30, 70, 160],
      value: function (s) { return s.stats.bosses; } },
    { id: 'chapters', name: 'Expédition longue', unit: 'chapitres terminés',
      steps: [10, 50, 150, 400], reward: [15, 40, 90, 200],
      value: function (s) { return s.record.chapter; } },
    { id: 'tower', name: 'Ascension', unit: 'étages de la Tour conquis',
      steps: [10, 50, 100, 200], reward: [15, 45, 100, 250],
      value: function (s) { return s.journey.towerBest || 0; } },
    { id: 'prestige', name: 'Cycle des âmes', unit: 'renaissances',
      steps: [1, 5, 15, 40], reward: [20, 50, 120, 260],
      value: function (s) { return s.stats.prestiges; } },
    { id: 'collection', name: 'Archiviste', unit: 'personnages débloqués',
      steps: [5, 12, 18, 24], reward: [20, 50, 110, 240],
      value: function () { return game().collection().owned; } },
    { id: 'gear', name: 'Armurier', unit: 'pièces de rareté légendaire ou mieux',
      steps: [1, 4, 10, 20], reward: [15, 40, 90, 190],
      value: function (s) {
        var count = 0;
        var rare = ['légendaire', 'mythique'];
        Object.keys(s.gear).forEach(function (slot) {
          if (s.gear[slot] && rare.indexOf(s.gear[slot].rarity) >= 0) count++;
        });
        s.bag.forEach(function (item) { if (rare.indexOf(item.rarity) >= 0) count++; });
        return count;
      } },
    { id: 'playtime', name: 'Vétéran du bataillon', unit: 'heures de jeu',
      steps: [1, 10, 50, 150], reward: [10, 30, 80, 180],
      value: function (s) { return Math.floor(s.stats.playtime / 3600); } }
  ];

  function progress(deed) {
    var s = game().state;
    var value = deed.value(s);
    var claimed = (s.deeds && s.deeds[deed.id]) || 0;
    var reached = 0;
    deed.steps.forEach(function (step) { if (value >= step) reached++; });
    return { value: value, claimed: claimed, reached: reached };
  }

  function claimable() {
    if (!game()) return 0;
    return DEEDS.filter(function (deed) {
      var p = progress(deed);
      return p.reached > p.claimed;
    }).length;
  }

  function claim(deed) {
    var p = progress(deed);
    if (p.reached <= p.claimed) return;
    var total = 0;
    for (var i = p.claimed; i < p.reached; i++) total += deed.reward[i];
    var s = game().state;
    if (!s.deeds) s.deeds = {};
    s.deeds[deed.id] = p.reached;
    game().addCrystals(total);
    if (window.Fx) {
      window.Fx.sound('loot');
      window.Fx.banner('+' + total + ' cristaux', 'HAUT FAIT', 'loot');
    }
    render();
  }

  function render() {
    if (!ready || !game()) return;
    var box = $('deeds-list');
    box.textContent = '';
    var done = 0;
    var total = 0;
    DEEDS.forEach(function (deed) {
      var p = progress(deed);
      done += p.reached;
      total += deed.steps.length;
      var next = Math.min(p.reached, deed.steps.length - 1);
      var goal = deed.steps[next];
      var complete = p.reached >= deed.steps.length;

      var row = document.createElement('div');
      row.className = 'deed' + (complete ? ' complete' : '');
      row.innerHTML = '<div class="deed-body"><b></b><span></span>'
        + '<progress max="1" value="0"></progress></div>';
      row.querySelector('b').textContent = deed.name
        + ' · palier ' + Math.min(p.reached + 1, deed.steps.length) + ' / ' + deed.steps.length;
      row.querySelector('span').textContent = game().fmt(p.value) + ' / ' + game().fmt(goal)
        + ' ' + deed.unit;
      var bar = row.querySelector('progress');
      bar.max = goal;
      bar.value = Math.min(p.value, goal);

      var button = document.createElement('button');
      button.className = 'buy-btn';
      var pending = p.reached > p.claimed;
      var gain = 0;
      for (var i = p.claimed; i < p.reached; i++) gain += deed.reward[i];
      button.textContent = pending ? '+' + gain + ' cristaux'
        : complete ? 'Terminé' : deed.reward[next] + ' cristaux';
      button.disabled = !pending;
      button.onclick = function () { claim(deed); };
      row.append(button);
      box.append(row);
    });
    $('deeds-progress').textContent = done + ' / ' + total + ' paliers franchis';
  }

  window.Deeds = { refresh: render, pending: claimable };

  document.addEventListener('DOMContentLoaded', function () {
    var host = $('tab-profile');
    if (!host) return;
    var section = document.createElement('section');
    section.id = 'deeds';
    section.innerHTML = '<h2>Hauts faits <small id="deeds-progress"></small></h2>'
      + '<p class="hint">Des objectifs de longue haleine, payés en cristaux à chaque palier '
      + 'franchi. Ils survivent aux renaissances.</p>'
      + '<div id="deeds-list" class="deeds-list"></div>';
    // Juste avant le bloc « Confort de jeu », après le registre.
    var anchor = $('profile-stats');
    if (anchor && anchor.parentNode === host) host.insertBefore(section, anchor.nextSibling);
    else host.append(section);

    ready = true;
    render();
    setInterval(function () {
      if (!document.hidden && host.classList.contains('active')) render();
    }, 1500);
  });
})();
