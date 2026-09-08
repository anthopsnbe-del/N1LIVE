/* AOT IDLE — boss mondial : le premier écran vraiment coopératif.

   Tous les joueurs d'un monde tapent sur la même barre de vie, remise à neuf
   chaque jour à 00:00 UTC. Le serveur (`social.php`, action `boss_*`) calcule
   les dégâts à partir de la puissance déjà synchronisée : le client n'envoie
   jamais de nombre de dégâts, il demande un assaut et affiche le résultat.

   Sans connexion, l'écran l'explique au lieu de faire semblant. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var view = null;          // dernier état renvoyé par le serveur
  var busy = false;
  var lastPoll = 0;
  var notice = '';
  var ready = false;

  function state() { return window.__game && window.__game.state; }

  function connected() {
    var s = state();
    return !!(s && s.online && s.online.token);
  }

  function call(action) {
    var s = state();
    if (!connected()) return Promise.reject(new Error('Connectez-vous pour rejoindre l\'assaut mondial.'));
    var base = (s.online.url || 'https://asylum-games.fr/aotidle').replace(/\/+$/, '');
    var controller = new AbortController();
    var timer = setTimeout(function () { controller.abort(); }, 8000);
    return fetch(base + '/social.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'omit',
      signal: controller.signal,
      body: JSON.stringify({ action: action, token: s.online.token })
    }).then(function (r) { return r.json(); }).then(function (r) {
      if (!r.ok) throw new Error(r.error || 'Service indisponible.');
      return r;
    }).finally(function () { clearTimeout(timer); });
  }

  function run(action, done) {
    if (busy) return;
    busy = true;
    notice = '';
    render();
    call(action).then(function (r) {
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

  function strike() {
    run('boss_strike', function (r) {
      if (!r.hit) return;
      var img = $('boss-art');
      img.classList.remove('duel-hit');
      void img.offsetWidth;
      img.classList.add('duel-hit');
      if (window.Fx) {
        window.Fx.sound(r.hit.killing ? 'win' : 'crit');
        if (r.hit.killing) window.Fx.banner(r.boss.name, 'TITAN ABATTU', 'boss');
      }
      notice = '−' + format(r.hit.damage) + ' PV infligés au titan.';
    });
  }

  function claim() {
    run('boss_claim', function (r) {
      var gained = r.crystals || 0;
      window.__game.state.crystals += gained;
      window.__game.save();
      if (window.Fx) {
        window.Fx.sound('loot');
        window.Fx.banner('+' + gained + ' cristaux', 'ASSAUT MONDIAL', 'loot');
      }
      notice = 'Récompense versée : ' + gained + ' cristaux.';
    });
  }

  function format(n) {
    return window.__game ? window.__game.fmt(n) : String(n);
  }

  function countdown(endsAt, serverNow) {
    var left = Math.max(0, endsAt - (serverNow || Date.now()));
    var hours = Math.floor(left / 3600000);
    var minutes = Math.floor((left % 3600000) / 60000);
    return hours ? hours + ' h ' + minutes + ' min' : minutes + ' min';
  }

  /* Pastille de l'accueil : une récompense à récupérer, ou un assaut prêt. */
  function pending() {
    if (!view || !view.you) return 0;
    if (view.you.reward > 0 && !view.you.claimed && (view.boss.defeated || Date.now() > view.boss.endsAt)) return 1;
    return 0;
  }

  function render() {
    if (!ready) return;
    var offline = !connected();
    $('boss-offline').hidden = !offline;
    $('boss-live').hidden = offline;
    $('boss-notice').textContent = notice;

    var homeName = $('home-boss-name');
    if (homeName) {
      if (offline || !view) {
        homeName.textContent = offline ? 'Connexion requise' : 'Chargement…';
        $('home-boss-progress').value = 0;
        $('home-boss-note').textContent = 'Un titan par monde, chaque jour.';
      } else {
        homeName.textContent = view.boss.name;
        $('home-boss-progress').max = view.boss.maxHp;
        $('home-boss-progress').value = view.boss.hp;
        $('home-boss-note').textContent = view.boss.defeated
          ? 'Titan abattu — récompense à récupérer.'
          : Math.round(100 * view.boss.hp / view.boss.maxHp) + ' % de vie · '
            + view.boss.participants + ' soldat' + (view.boss.participants > 1 ? 's' : '') + ' engagé'
            + (view.boss.participants > 1 ? 's' : '');
      }
      var badge = $('home-boss-badge');
      if (badge) {
        badge.textContent = pending();
        badge.classList.toggle('hidden', pending() === 0);
      }
    }

    if (offline || !view) return;

    var boss = view.boss;
    var you = view.you;
    $('boss-art').src = 'enemies/' + boss.shape + '.webp';
    $('boss-name').textContent = boss.name;
    $('boss-hp').max = boss.maxHp;
    $('boss-hp').value = boss.hp;
    $('boss-values').textContent = format(boss.hp) + ' / ' + format(boss.maxHp) + ' PV · '
      + boss.participants + ' participant' + (boss.participants > 1 ? 's' : '');
    $('boss-reset').textContent = boss.defeated
      ? 'Titan abattu. Le suivant se lève dans ' + countdown(boss.endsAt, view.serverNow) + '.'
      : 'Il reste ' + countdown(boss.endsAt, view.serverNow) + ' avant sa retraite.';

    var cooldown = Math.ceil((you.cooldown || 0) / 1000);
    var strikeBtn = $('boss-strike');
    strikeBtn.disabled = busy || boss.defeated || cooldown > 0 || you.hitsLeft <= 0;
    strikeBtn.textContent = boss.defeated ? 'Titan abattu'
      : you.hitsLeft <= 0 ? 'Vous avez donné tout ce que vous pouviez'
      : cooldown > 0 ? 'Assaut dans ' + cooldown + ' s'
      : 'ASSAUT · ' + format(you.strikePower) + ' dégâts';

    var claimable = you.reward > 0 && !you.claimed && (boss.defeated || Date.now() > boss.endsAt);
    var claimBtn = $('boss-claim');
    claimBtn.hidden = you.damage <= 0;
    claimBtn.disabled = busy || !claimable;
    claimBtn.textContent = you.claimed ? 'Récompense récupérée'
      : claimable ? 'Récupérer ' + you.reward + ' cristaux'
      : 'Récompense estimée : ' + you.reward + ' cristaux';

    $('boss-mine').textContent = you.damage > 0
      ? 'Vos dégâts : ' + format(you.damage) + ' · rang ' + you.rank + ' · ' + you.hits + ' assaut'
        + (you.hits > 1 ? 's' : '') + ' (' + you.hitsLeft + ' restants)'
      : 'Vous n\'avez pas encore rejoint l\'assaut.';

    var podium = $('boss-top');
    podium.textContent = '';
    view.top.forEach(function (entry, index) {
      var row = document.createElement('div');
      row.className = 'boss-row' + (state().online.pseudo === entry.pseudo ? ' me' : '');
      var rank = document.createElement('b');
      rank.textContent = index + 1;
      var name = document.createElement('span');
      name.textContent = entry.pseudo + (entry.clan ? ' [' + entry.clan + ']' : '');
      var damage = document.createElement('em');
      damage.textContent = format(entry.damage);
      row.append(rank, name, damage);
      podium.append(row);
    });
    if (!view.top.length) {
      var empty = document.createElement('p');
      empty.className = 'hint';
      empty.textContent = 'Personne n\'a encore frappé. Ouvrez le bal.';
      podium.append(empty);
    }

    var clans = $('boss-clans');
    clans.textContent = '';
    view.clans.forEach(function (entry, index) {
      var row = document.createElement('div');
      row.className = 'boss-row';
      var rank = document.createElement('b');
      rank.textContent = index + 1;
      var name = document.createElement('span');
      name.textContent = '[' + entry.tag + '] ' + entry.name + ' · ' + entry.members + ' membre'
        + (entry.members > 1 ? 's' : '');
      var damage = document.createElement('em');
      damage.textContent = format(entry.damage);
      row.append(rank, name, damage);
      clans.append(row);
    });
    if (!view.clans.length) {
      var none = document.createElement('p');
      none.className = 'hint';
      none.textContent = 'Aucun clan engagé : rejoignez-en un pour peser dans le classement.';
      clans.append(none);
    }
  }

  window.Boss = {
    refresh: function (force) {
      if (!connected()) { render(); return; }
      if (!force && Date.now() - lastPoll < 15000) return;
      run('boss_state');
    },
    pending: pending
  };

  document.addEventListener('DOMContentLoaded', function () {
    var section = document.createElement('section');
    section.id = 'tab-boss';
    section.className = 'tab';
    section.innerHTML = '<header class="tower-heading"><small>ASSAUT MONDIAL · COOPÉRATIF</small>'
      + '<h1 id="boss-name">Boss mondial</h1><p id="boss-reset"></p></header>'
      + '<div id="boss-offline" hidden><p class="hint">L\'assaut mondial réunit tous les joueurs de votre monde '
      + 'sur le même titan. Il demande un compte : ouvrez « Compte » dans l\'onglet Monde pour vous connecter.</p></div>'
      + '<div id="boss-live">'
      + '<div class="tower-arena boss-arena"><img id="boss-art" src="enemies/colossal.webp" alt="Boss mondial">'
      + '<progress id="boss-hp" max="100" aria-label="Vie du boss mondial"></progress></div>'
      + '<p id="boss-values"></p>'
      + '<button id="boss-strike" class="big-btn">ASSAUT</button>'
      + '<button id="boss-claim" class="ghost-btn" hidden></button>'
      + '<p id="boss-notice" role="status"></p>'
      + '<p id="boss-mine" class="hint"></p>'
      + '<h2>Meilleurs assaillants</h2><div id="boss-top" class="boss-board"></div>'
      + '<h2>Clans engagés</h2><div id="boss-clans" class="boss-board"></div>'
      + '<p class="hint">Un assaut par minute, dégâts calculés par le serveur d\'après votre puissance '
      + 'synchronisée. Le titan est remplacé chaque jour à 00:00 UTC ; la récompense se récupère à sa chute.</p>'
      + '</div>';
    document.querySelector('main').append(section);

    $('boss-strike').onclick = strike;
    $('boss-claim').onclick = claim;
    ready = true;
    render();

    // Rafraîchissement : à l'ouverture de l'écran, puis toutes les 15 s.
    setInterval(function () {
      if (document.hidden || !connected()) return;
      var active = $('tab-boss').classList.contains('active');
      var home = $('tab-home') && $('tab-home').classList.contains('active');
      if (active || home || Date.now() - lastPoll > 120000) window.Boss.refresh(false);
      if (active) render();
    }, 3000);
    window.addEventListener('aot-resume', function () { window.Boss.refresh(true); });
  });
})();
