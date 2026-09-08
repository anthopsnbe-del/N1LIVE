/* AOT IDLE — saisons de l'arène.

   Sans remise à zéro, un classement se fige : les premiers inscrits gardent
   leur avance et les nouveaux n'ont plus rien à viser. Le serveur
   (`season-core.php`) clôt la saison tous les quatorze jours, verse les
   récompenses au dépôt et rapproche chaque cote de 1000 — une remise à niveau
   douce plutôt qu'un effacement. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var view = null;
  var busy = false;
  var lastPoll = 0;
  var notice = '';
  var ready = false;

  function api() { return window.Api; }
  function connected() { return !!(api() && api().connected()); }

  function run(action, done) {
    if (busy || !connected()) { render(); return; }
    busy = true;
    notice = '';
    render();
    api().call(action, {}).then(function (r) {
      view = r;
      if (done) done(r);
    }).catch(function (e) {
      notice = e.name === 'AbortError' ? 'Le serveur ne répond pas. Réessayez.' : e.message;
    }).finally(function () {
      busy = false;
      lastPoll = Date.now();
      render();
    });
  }

  function claim() {
    run('season_claim', function (r) {
      var gained = r.credited || 0;
      if (window.Fx) {
        window.Fx.sound('loot');
        window.Fx.banner('+' + gained + ' cristaux', 'FIN DE SAISON', 'loot');
      }
      notice = gained + ' cristaux versés au dépôt. Récupérez-les dans « Dépôt et marché ».';
      if (window.Market) window.Market.refresh(true);
    });
  }

  function countdown(endsAt, serverNow) {
    var left = Math.max(0, endsAt - (serverNow || Date.now()));
    var days = Math.floor(left / 86400000);
    var hours = Math.floor((left % 86400000) / 3600000);
    return days ? days + ' jour' + (days > 1 ? 's' : '') + ' et ' + hours + ' h' : hours + ' h';
  }

  function tier(rating) {
    return rating >= 1800 ? 'Légende' : rating >= 1500 ? 'Diamant'
      : rating >= 1250 ? 'Or' : rating >= 1100 ? 'Argent' : 'Bronze';
  }

  function pending() {
    return view && view.pending && view.pending.length ? 1 : 0;
  }

  function render() {
    if (!ready) return;
    var offline = !connected();
    $('season-offline').hidden = !offline;
    $('season-live').hidden = offline;
    $('season-notice').textContent = notice;

    var note = $('home-season-note');
    if (note) {
      note.textContent = offline ? 'Connexion requise.'
        : !view ? 'Chargement…'
        : 'Saison ' + view.season.number + ' · ' + tier(view.you.rating) + ' · '
          + (view.you.rank ? 'rang ' + view.you.rank : 'non classé');
      var badge = $('home-season-badge');
      if (badge) {
        badge.textContent = pending();
        badge.classList.toggle('hidden', pending() === 0);
      }
    }

    if (offline || !view) return;

    $('season-number').textContent = 'Saison ' + view.season.number;
    $('season-timer').textContent = 'Clôture dans ' + countdown(view.season.endsAt, view.serverNow)
      + ' · monde ' + view.season.world;
    $('season-mine').textContent = view.you.rank
      ? tier(view.you.rating) + ' · cote ' + view.you.rating + ' · rang ' + view.you.rank
        + ' · ' + view.you.wins + ' victoire' + (view.you.wins > 1 ? 's' : '')
        + ' / ' + view.you.losses + ' défaite' + (view.you.losses > 1 ? 's' : '')
      : 'Disputez un duel classé dans l\'arène pour entrer au classement.';

    var claimBtn = $('season-claim');
    var total = view.pending.reduce(function (sum, row) { return sum + row.crystals; }, 0);
    claimBtn.hidden = !view.pending.length;
    claimBtn.disabled = busy;
    claimBtn.textContent = 'Récupérer ' + total + ' cristaux de fin de saison';

    var box = $('season-board');
    box.textContent = '';
    var pseudo = window.__game.state.online.pseudo;
    view.standings.forEach(function (entry, index) {
      var row = document.createElement('div');
      row.className = 'boss-row' + (entry.pseudo === pseudo ? ' me' : '');
      var rank = document.createElement('b');
      rank.textContent = index + 1;
      var name = document.createElement('span');
      name.textContent = entry.pseudo + (entry.clan ? ' [' + entry.clan + ']' : '');
      var rating = document.createElement('em');
      rating.textContent = entry.rating + ' · ' + tier(entry.rating);
      row.append(rank, name, rating);
      box.append(row);
    });
    if (!view.standings.length) {
      var empty = document.createElement('p');
      empty.className = 'hint';
      empty.textContent = 'Classement vide : les premiers duels de la saison l\'ouvriront.';
      box.append(empty);
    }
  }

  window.Season = {
    refresh: function (force) {
      if (!connected()) { render(); return; }
      if (!force && Date.now() - lastPoll < 30000) return;
      run('season_state');
    },
    pending: pending
  };

  document.addEventListener('DOMContentLoaded', function () {
    var section = document.createElement('section');
    section.id = 'tab-season';
    section.className = 'tab';
    section.innerHTML = '<header class="tower-heading"><small>ARÈNE CLASSÉE</small>'
      + '<h1 id="season-number">Saison</h1><p id="season-timer"></p></header>'
      + '<div id="season-offline" hidden><p class="hint">Les saisons rythment l\'arène classée : '
      + 'quatorze jours, puis récompenses et remise à niveau des cotes. Elles demandent un compte : '
      + 'ouvrez « Compte » dans l\'onglet Monde.</p></div>'
      + '<div id="season-live">'
      + '<p id="season-mine" class="hint"></p>'
      + '<button id="season-claim" class="big-btn" hidden></button>'
      + '<p id="season-notice" role="status"></p>'
      + '<h2>Classement de la saison</h2><div id="season-board" class="boss-board"></div>'
      + '<p class="hint">À la clôture : le podium touche 120, 80 et 60 cristaux, le top 10 en reçoit 35, '
      + 'le top 50 quinze, et toute personne ayant gagné un duel repart avec cinq. '
      + 'Les cotes ne repartent pas de zéro : chacune se rapproche de 1000 de moitié.</p></div>';
    document.querySelector('main').append(section);

    $('season-claim').onclick = claim;
    ready = true;
    render();

    setInterval(function () {
      if (document.hidden) return;
      var active = $('tab-season').classList.contains('active');
      var home = $('tab-home') && $('tab-home').classList.contains('active');
      if (!connected()) { if (active || home) render(); return; }
      if (active || home || Date.now() - lastPoll > 300000) window.Season.refresh(false);
    }, 5000);
    window.addEventListener('aot-resume', function () { window.Season.refresh(true); });
  });
})();
