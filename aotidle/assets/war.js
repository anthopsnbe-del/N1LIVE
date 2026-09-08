/* AOT IDLE — guerre de clans : deux clans, un même front, vingt-quatre heures.

   Le chef engage son clan, le serveur l'apparie avec un clan de taille voisine
   du même monde. Les deux camps frappent le même titan ; le camp qui a le plus
   contribué l'emporte. Comme pour le boss mondial, les dégâts sont calculés
   par le serveur (`war-core.php`) : le client demande un assaut, il ne
   l'invente pas. */
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
  function format(n) { return window.__game ? window.__game.fmt(n) : String(n); }

  function run(action, done) {
    if (busy || !connected()) { render(); return; }
    busy = true;
    notice = '';
    render();
    api().call(action).then(function (r) {
      view = r;
      if (done) done(r);
    }).catch(function (e) {
      // « Rejoignez un clan » n'est pas une panne : c'est l'état du joueur.
      notice = e.name === 'AbortError' ? 'Le serveur ne répond pas. Réessayez.' : e.message;
      view = null;
    }).finally(function () {
      busy = false;
      lastPoll = Date.now();
      render();
    });
  }

  function enroll() { run('war_enroll'); }

  function strike() {
    run('war_strike', function (r) {
      if (!r.hit) return;
      var art = $('war-art');
      art.classList.remove('duel-hit');
      void art.offsetWidth;
      art.classList.add('duel-hit');
      if (window.Fx) {
        window.Fx.sound(r.hit.killing ? 'win' : 'crit');
        if (r.hit.killing) window.Fx.banner('Front effondré', 'GUERRE DE CLANS', 'boss');
      }
      notice = '−' + format(r.hit.damage) + ' PV pour votre camp.';
    });
  }

  function claim() {
    run('war_claim', function (r) {
      var gained = r.crystals || 0;
      window.__game.state.crystals += gained;
      window.__game.save();
      if (window.Fx) {
        window.Fx.sound('loot');
        window.Fx.banner('+' + gained + ' cristaux', 'GUERRE DE CLANS', 'loot');
      }
      notice = 'Récompense versée : ' + gained + ' cristaux.';
    });
  }

  function countdown(endsAt, serverNow) {
    var left = Math.max(0, endsAt - (serverNow || Date.now()));
    var hours = Math.floor(left / 3600000);
    var minutes = Math.floor((left % 3600000) / 60000);
    return hours ? hours + ' h ' + minutes + ' min' : minutes + ' min';
  }

  function pending() {
    if (!view || !view.war || !view.you) return 0;
    return view.war.resolved && view.you.reward > 0 && !view.you.claimed ? 1 : 0;
  }

  function podium(box, entries, mine) {
    box.textContent = '';
    entries.forEach(function (entry, index) {
      var row = document.createElement('div');
      row.className = 'boss-row' + (entry.pseudo === mine ? ' me' : '');
      var rank = document.createElement('b');
      rank.textContent = index + 1;
      var name = document.createElement('span');
      name.textContent = entry.pseudo;
      var damage = document.createElement('em');
      damage.textContent = format(entry.damage);
      row.append(rank, name, damage);
      box.append(row);
    });
    if (!entries.length) {
      var empty = document.createElement('p');
      empty.className = 'hint';
      empty.textContent = 'Aucun assaut de ce camp pour l\'instant.';
      box.append(empty);
    }
  }

  function render() {
    if (!ready) return;
    var offline = !connected();
    $('war-offline').hidden = !offline;
    $('war-notice').textContent = notice;

    var war = view && view.war;
    $('war-idle').hidden = offline || !!war;
    $('war-live').hidden = offline || !war;

    var homeNote = $('home-war-note');
    if (homeNote) {
      homeNote.textContent = offline ? 'Connexion requise.'
        : !view ? (notice || 'Chargement…')
        : war ? (war.resolved
            ? (war.winner === 3 ? 'Égalité — récompense à récupérer.'
              : war.winner === war.side ? 'Victoire — récompense à récupérer.'
              : 'Défaite — récompense à récupérer.')
            : war.clans[0].tag + ' ' + format(war.scores[0]) + ' — ' + format(war.scores[1]) + ' ' + war.clans[1].tag)
        : view.queued ? 'Clan en file d\'attente d\'adversaire.'
        : view.clan ? 'Aucune guerre en cours.' : 'Rejoignez un clan pour participer.';
      var badge = $('home-war-badge');
      if (badge) {
        badge.textContent = pending();
        badge.classList.toggle('hidden', pending() === 0);
      }
    }

    if (offline || !view) return;

    if (!war) {
      var clan = view.clan;
      $('war-clan').textContent = clan
        ? 'Clan [' + clan.tag + '] ' + clan.name + ' · ' + clan.members + ' membre'
          + (clan.members > 1 ? 's' : '')
        : 'Vous n\'avez pas de clan.';
      $('war-queue').textContent = view.queued
        ? 'Votre clan attend un adversaire de taille comparable dans votre monde.'
        : view.canEnroll
          ? 'Engagez le clan : le serveur cherchera un adversaire du même monde.'
          : 'Seul le chef du clan peut déclencher une guerre.';
      var enrollBtn = $('war-enroll');
      enrollBtn.hidden = !view.canEnroll;
      enrollBtn.disabled = busy || view.queued;
      enrollBtn.textContent = view.queued
        ? 'En attente · ' + view.waiting + ' clan' + (view.waiting > 1 ? 's' : '') + ' en file'
        : 'Engager le clan dans une guerre';
      return;
    }

    var mySide = war.side;
    var us = war.clans[mySide - 1];
    var them = war.clans[2 - mySide];
    var ourScore = war.scores[mySide - 1];
    var theirScore = war.scores[2 - mySide];
    var total = Math.max(1, ourScore + theirScore);

    $('war-art').src = 'enemies/' + war.shape + '.webp';
    $('war-title').textContent = '[' + us.tag + '] contre [' + them.tag + ']';
    $('war-hp').max = war.maxHp;
    $('war-hp').value = war.hp;
    $('war-values').textContent = format(war.hp) + ' / ' + format(war.maxHp) + ' PV de front restants';
    $('war-timer').textContent = war.resolved
      ? (war.winner === 3 ? 'Guerre terminée : égalité.'
        : war.winner === mySide ? 'Guerre terminée : victoire de [' + us.tag + '].'
        : 'Guerre terminée : [' + them.tag + '] l\'emporte.')
      : 'Il reste ' + countdown(war.endsAt, view.serverNow) + '.';

    $('war-us-label').textContent = '[' + us.tag + '] ' + us.name;
    $('war-them-label').textContent = '[' + them.tag + '] ' + them.name;
    $('war-us-score').textContent = format(ourScore);
    $('war-them-score').textContent = format(theirScore);
    $('war-us-bar').style.width = Math.round(100 * ourScore / total) + '%';
    $('war-them-bar').style.width = Math.round(100 * theirScore / total) + '%';

    var you = view.you;
    var cooldown = Math.ceil((you.cooldown || 0) / 1000);
    var strikeBtn = $('war-strike');
    strikeBtn.hidden = war.resolved;
    strikeBtn.disabled = busy || cooldown > 0 || you.hitsLeft <= 0;
    strikeBtn.textContent = you.hitsLeft <= 0 ? 'Vous avez donné tout ce que vous pouviez'
      : cooldown > 0 ? 'Assaut dans ' + cooldown + ' s'
      : 'ASSAUT · ' + format(you.strikePower) + ' dégâts';

    var claimBtn = $('war-claim');
    claimBtn.hidden = you.damage <= 0;
    claimBtn.disabled = busy || !war.resolved || you.claimed;
    claimBtn.textContent = you.claimed ? 'Récompense récupérée'
      : war.resolved ? 'Récupérer ' + you.reward + ' cristaux'
      : 'Récompense estimée : ' + you.reward + ' cristaux';

    $('war-mine').textContent = you.damage > 0
      ? 'Votre apport : ' + format(you.damage) + ' · ' + you.hits + ' assaut'
        + (you.hits > 1 ? 's' : '') + ' (' + you.hitsLeft + ' restants)'
      : 'Vous n\'avez pas encore frappé sur ce front.';

    var pseudo = window.__game.state.online.pseudo;
    $('war-us-title').textContent = 'Assauts de [' + us.tag + ']';
    $('war-them-title').textContent = 'Assauts de [' + them.tag + ']';
    podium($('war-us-board'), war.boards[mySide - 1], pseudo);
    podium($('war-them-board'), war.boards[2 - mySide], pseudo);
  }

  window.War = {
    refresh: function (force) {
      if (!connected()) { render(); return; }
      if (!force && Date.now() - lastPoll < 15000) return;
      run('war_state');
    },
    pending: pending
  };

  document.addEventListener('DOMContentLoaded', function () {
    var section = document.createElement('section');
    section.id = 'tab-war';
    section.className = 'tab';
    section.innerHTML = '<header class="tower-heading"><small>GUERRE DE CLANS · 24 HEURES</small>'
      + '<h1 id="war-title">Guerre de clans</h1><p id="war-timer"></p></header>'
      + '<div id="war-offline" hidden><p class="hint">Les guerres de clans opposent deux clans du même '
      + 'monde sur un front commun. Elles demandent un compte : ouvrez « Compte » dans l\'onglet Monde.</p></div>'
      + '<div id="war-idle" hidden><p id="war-clan" class="hint"></p><p id="war-queue" class="hint"></p>'
      + '<button id="war-enroll" class="big-btn" hidden></button>'
      + '<p class="hint">L\'appariement cherche un clan de taille comparable dans votre monde. '
      + 'La guerre dure 24 heures : le camp qui a le plus contribué au front l\'emporte.</p></div>'
      + '<div id="war-live" hidden>'
      + '<div class="tower-arena war-arena"><img id="war-art" src="enemies/blinde.webp" alt="Front de la guerre de clans">'
      + '<progress id="war-hp" max="100" aria-label="Vie du front"></progress></div>'
      + '<p id="war-values"></p>'
      + '<div class="war-scores">'
      + '<div class="war-camp us"><span id="war-us-label"></span><div class="war-bar"><i id="war-us-bar"></i></div>'
      + '<b id="war-us-score"></b></div>'
      + '<div class="war-camp them"><span id="war-them-label"></span><div class="war-bar"><i id="war-them-bar"></i></div>'
      + '<b id="war-them-score"></b></div></div>'
      + '<button id="war-strike" class="big-btn">ASSAUT</button>'
      + '<button id="war-claim" class="ghost-btn" hidden></button>'
      + '<p id="war-notice" role="status"></p><p id="war-mine" class="hint"></p>'
      + '<h2 id="war-us-title"></h2><div id="war-us-board" class="boss-board"></div>'
      + '<h2 id="war-them-title"></h2><div id="war-them-board" class="boss-board"></div>'
      + '<p class="hint">Un assaut par minute, dégâts calculés par le serveur d\'après votre puissance '
      + 'synchronisée. La guerre s\'achève à la chute du front ou au bout de 24 heures ; la récompense '
      + 'se récupère ensuite, plus généreuse pour le camp vainqueur.</p></div>';
    document.querySelector('main').append(section);

    $('war-enroll').onclick = enroll;
    $('war-strike').onclick = strike;
    $('war-claim').onclick = claim;
    ready = true;
    render();

    setInterval(function () {
      if (document.hidden || !connected()) return;
      var active = $('tab-war').classList.contains('active');
      var home = $('tab-home') && $('tab-home').classList.contains('active');
      if (active || home || Date.now() - lastPoll > 120000) window.War.refresh(false);
      if (active) render();
    }, 3000);
    window.addEventListener('aot-resume', function () { window.War.refresh(true); });
  });
})();
