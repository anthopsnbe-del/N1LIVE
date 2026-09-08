/* Couche en ligne : compte joueur, classement et clans.
   Le jeu reste entièrement jouable sans serveur : toutes les fonctions
   échouent en silence et l'interface bascule en « hors ligne ». */
(function () {
  'use strict';

  // URL de l'API par défaut : le dossier server/ déposé sur l'hébergement.
  // Le jeu essaie d'abord HTTPS puis retombe sur HTTP si le certificat manque,
  // et retient l'adresse qui a répondu.
  var DEFAULT_API = 'https://asylum-games.fr/aotidle';

  var state = null;
  var helpers = { toast: function () {}, fmt: function (n) { return n; }, save: function () {} };
  var onReady = null;
  var lastSync = 0;
  var lastRefresh = 0;
  var board = { scope: 'global', entries: [], loading: false };
  var clan = null;

  function api() { return (state && state.online.url) || DEFAULT_API; }

  /* Adresses à essayer : celle enregistrée par le joueur, sinon l'adresse par
     défaut en HTTPS puis en HTTP. */
  function bases() {
    if (state && state.online.url) return [state.online.url];
    if (!DEFAULT_API) return [];
    var list = [DEFAULT_API];
    if (DEFAULT_API.indexOf('https://') === 0) list.push('http://' + DEFAULT_API.slice(8));
    return list;
  }
  function token() { return (state && state.online.token) || ''; }
  function connected() { return !!(api() && token()); }

  function $(id) { return document.getElementById(id); }

  function callOne(base, body) {
    var url = base.replace(/\/+$/, '') + '/index.php';
    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = controller ? setTimeout(function () { controller.abort(); }, 12000) : null;
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: body,
      signal: controller ? controller.signal : undefined
    }).then(function (res) {
      if (timer) clearTimeout(timer);
      return res.json();
    });
  }

  function request(action, payload) {
    var candidates = bases();
    if (!candidates.length) return Promise.reject(new Error('offline'));
    var body = JSON.stringify(Object.assign({ action: action, token: token() }, payload || {}));

    var attempt = function (index) {
      return callOne(candidates[index], body).then(function (data) {
        // L'adresse qui répond devient l'adresse retenue.
        if (state && state.online.url !== candidates[index]) {
          state.online.url = candidates[index];
          helpers.save();
        }
        if (!data || data.ok !== true) throw new Error((data && data.error) || 'erreur serveur');
        return data;
      }, function (err) {
        // Erreur réseau (pas une réponse du serveur) : on tente l'adresse suivante.
        if (index + 1 < candidates.length) return attempt(index + 1);
        throw new Error('serveur injoignable');
      });
    };
    return attempt(0);
  }

  // ------------------------------------------------------------------
  // Écran de connexion
  // ------------------------------------------------------------------

  var mode = 'login';

  function setMode(next) {
    mode = next;
    document.querySelectorAll('.auth-tab').forEach(function (b) {
      b.classList.toggle('active', b.dataset.mode === next);
    });
    $('auth-email-field').classList.toggle('hidden', next === 'login');
    $('auth-submit').textContent = next === 'login' ? 'SE CONNECTER' : 'CRÉER LE COMPTE';
  }

  function authError(message) {
    var el = $('auth-error');
    el.textContent = message;
    el.classList.toggle('hidden', !message);
  }

  function showAuth(callback) {
    onReady = callback;
    $('auth').classList.remove('hidden');
    $('auth-server').textContent = api() ? 'Serveur : ' + api() : 'Aucun serveur configuré — seul le mode hors ligne est disponible.';
    $('auth-sub').textContent = api()
      ? 'Connectez-vous pour le classement et les clans.'
      : 'Aucun serveur configuré : jouez hors ligne.';
    $('auth-pseudo').value = state.online.pseudo || '';
    setMode('login');
    if (!api()) authError('');
  }

  function finishAuth(data) {
    state.online.token = data.token;
    state.online.pseudo = data.player.pseudo;
    state.online.clan = data.player.clan || '';
    helpers.save();
    $('auth').classList.add('hidden');
    helpers.toast('Connecté : ' + data.player.pseudo);
    if (onReady) { var cb = onReady; onReady = null; cb(); }
    refreshWorld();
  }

  function submitAuth() {
    var pseudo = $('auth-pseudo').value.trim();
    var email = $('auth-email').value.trim();
    var password = $('auth-password').value;
    if (!api()) { authError('Configurez d\'abord un serveur dans les options.'); return; }
    if (pseudo.length < 3) { authError('Pseudo trop court (3 caractères minimum).'); return; }
    if (password.length < 6) { authError('Mot de passe trop court (6 caractères minimum).'); return; }
    if (mode === 'register' && email.indexOf('@') < 0) { authError('E-mail invalide.'); return; }
    authError('');
    $('auth-submit').disabled = true;
    request(mode === 'login' ? 'login' : 'register', {
      pseudo: pseudo, email: email, password: password, device: state.online.deviceId
    }).then(finishAuth).catch(function (err) {
      authError(err.message === 'offline' ? 'Aucun serveur configuré.' : err.message);
    }).then(function () { $('auth-submit').disabled = false; });
  }

  /* Google : la WebView d'Android refuse le formulaire OAuth de Google.
     On passe donc par un code d'appairage affiché dans le navigateur. */
  function googleAuth() {
    if (!api()) { authError('Configurez d\'abord un serveur dans les options.'); return; }
    var url = api().replace(/\/+$/, '') + '/google.php';
    window.open(url, '_blank');
    showModal('Connexion Google', '<p>Une page s\'ouvre dans votre navigateur. Connectez-vous avec Gmail, '
      + 'puis recopiez ici le code affiché.</p><p class="tiny">Si rien ne s\'ouvre, allez à <b>' + url + '</b>.</p>'
      + '<textarea id="google-code" placeholder="CODE"></textarea>',
      [{ label: 'Annuler' }, {
        label: 'Valider', primary: true, onClick: function () {
          var code = ($('google-code').value || '').trim().toUpperCase();
          request('google_claim', { code: code, device: state.online.deviceId })
            .then(finishAuth)
            .catch(function (err) { helpers.toast('Code refusé : ' + err.message); });
        }
      }]);
  }

  var showModal = function () {};

  // ------------------------------------------------------------------
  // Synchronisation, classement, clans
  // ------------------------------------------------------------------

  function sync(force) {
    if (!connected()) return Promise.resolve(null);
    var now = Date.now();
    if (!force && now - lastSync < 60000) return Promise.resolve(null);
    lastSync = now;
    return request('sync', {
      chapter: state.bestChapter,
      power: state.bestPower,
      souls: state.souls,
      level: state.level,
      kills: Math.round(state.stats.kills),
      prestiges: state.stats.prestiges,
      playtime: Math.round(state.stats.playtime)
    }).catch(function () { return null; });
  }

  function loadBoard() {
    if (!connected()) { renderBoard(); return; }
    board.loading = true;
    renderBoard();
    request('leaderboard', { scope: board.scope }).then(function (data) {
      board.entries = data.entries || [];
      board.loading = false;
      renderBoard();
    }).catch(function () {
      board.loading = false;
      board.entries = [];
      renderBoard();
    });
  }

  function loadClan() {
    if (!connected()) { clan = null; renderClan(); return; }
    request('clan').then(function (data) {
      clan = data.clan || null;
      state.online.clan = clan ? clan.tag : '';
      renderClan();
    }).catch(function () { clan = null; renderClan(); });
  }

  function renderBoard() {
    var box = $('leaderboard');
    box.innerHTML = '';
    if (!connected()) {
      box.innerHTML = '<p class="hint">Hors ligne — connectez-vous pour apparaître au classement.</p>';
      return;
    }
    if (board.loading) { box.innerHTML = '<p class="hint">Chargement…</p>'; return; }
    if (!board.entries.length) {
      box.innerHTML = '<p class="hint">' + (board.scope === 'clan' ? 'Rejoignez un clan pour voir son classement.' : 'Aucun joueur classé pour le moment.') + '</p>';
      return;
    }
    board.entries.forEach(function (entry, i) {
      var row = document.createElement('div');
      row.className = 'board-row' + (entry.pseudo === state.online.pseudo ? ' me' : '');
      row.innerHTML = '<b class="rank">' + (entry.rank || i + 1) + '</b>'
        + '<span class="who">' + escapeHtml(entry.pseudo) + (entry.clan ? ' <small>[' + escapeHtml(entry.clan) + ']</small>' : '') + '</span>'
        + '<span class="score">ch. ' + entry.chapter + ' · ' + helpers.fmt(entry.power) + ' pts</span>';
      box.appendChild(row);
    });
  }

  function renderClan() {
    var box = $('clan-box');
    box.innerHTML = '';
    if (!connected()) {
      box.innerHTML = '<p class="hint">Connectez-vous pour créer ou rejoindre un clan.</p>';
      return;
    }
    if (clan) {
      var head = document.createElement('div');
      head.className = 'clan-head';
      head.innerHTML = '<b>' + escapeHtml(clan.name) + '</b> <small>[' + escapeHtml(clan.tag) + ']</small>'
        + '<span>' + clan.members.length + ' membre(s) · ' + helpers.fmt(clan.score || 0) + ' pts</span>';
      box.appendChild(head);
      clan.members.forEach(function (m) {
        var row = document.createElement('div');
        row.className = 'board-row';
        row.innerHTML = '<span class="who">' + escapeHtml(m.pseudo) + '</span><span class="score">ch. ' + m.chapter + '</span>';
        box.appendChild(row);
      });
      var leave = document.createElement('button');
      leave.className = 'ghost-btn';
      leave.textContent = 'Quitter le clan';
      leave.addEventListener('click', function () {
        request('clan_leave').then(function () { clan = null; renderClan(); loadBoard(); })
          .catch(function (e) { helpers.toast(e.message); });
      });
      box.appendChild(leave);
      return;
    }
    var join = document.createElement('div');
    join.className = 'clan-actions';
    join.innerHTML = '<label class="field"><span>Rejoindre (tag)</span><input id="clan-tag" maxlength="6" placeholder="SCOUT" /></label>';
    box.appendChild(join);
    var joinBtn = document.createElement('button');
    joinBtn.className = 'ghost-btn';
    joinBtn.textContent = 'Rejoindre';
    joinBtn.addEventListener('click', function () {
      request('clan_join', { tag: ($('clan-tag').value || '').trim().toUpperCase() })
        .then(function (d) { clan = d.clan; renderClan(); loadBoard(); helpers.toast('Clan rejoint'); })
        .catch(function (e) { helpers.toast(e.message); });
    });
    box.appendChild(joinBtn);
    var createBtn = document.createElement('button');
    createBtn.className = 'ghost-btn';
    createBtn.textContent = 'Créer un clan';
    createBtn.addEventListener('click', function () {
      showModal('Créer un clan', '<label class="field"><span>Nom</span><input id="new-clan-name" maxlength="24" /></label>'
        + '<label class="field"><span>Tag (2 à 6 lettres)</span><input id="new-clan-tag" maxlength="6" /></label>',
        [{ label: 'Annuler' }, {
          label: 'Créer', primary: true, onClick: function () {
            request('clan_create', {
              name: ($('new-clan-name').value || '').trim(),
              tag: ($('new-clan-tag').value || '').trim().toUpperCase()
            }).then(function (d) { clan = d.clan; renderClan(); loadBoard(); helpers.toast('Clan créé'); })
              .catch(function (e) { helpers.toast(e.message); });
          }
        }]);
    });
    box.appendChild(createBtn);
  }

  function refreshWorld() {
    loadBoard();
    loadClan();
  }

  function escapeHtml(text) {
    return String(text == null ? '' : text).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // ------------------------------------------------------------------
  // Initialisation
  // ------------------------------------------------------------------

  function init(gameState, tools) {
    state = gameState;
    helpers = Object.assign(helpers, tools || {});
    showModal = helpers.showModal || showModal;

    document.querySelectorAll('.auth-tab').forEach(function (b) {
      b.addEventListener('click', function () { setMode(b.dataset.mode); authError(''); });
    });
    $('auth-submit').addEventListener('click', submitAuth);
    $('auth-google').addEventListener('click', googleAuth);
    $('auth-offline').addEventListener('click', function () {
      $('auth').classList.add('hidden');
      if (onReady) { var cb = onReady; onReady = null; cb(); }
    });

    document.querySelectorAll('.board-tab').forEach(function (b) {
      b.addEventListener('click', function () {
        document.querySelectorAll('.board-tab').forEach(function (o) { o.classList.remove('active'); });
        b.classList.add('active');
        board.scope = b.dataset.scope;
        loadBoard();
      });
    });

    $('opt-server').value = state.online.url || '';
    $('opt-server').placeholder = DEFAULT_API || 'https://exemple.fr/aot-idle/api';
    $('server-save').addEventListener('click', function () {
      state.online.url = ($('opt-server').value || '').trim();
      helpers.save();
      helpers.toast(state.online.url ? 'Serveur enregistré' : 'Mode hors ligne');
      refreshWorld();
    });

    $('account-btn').addEventListener('click', function () {
      if (connected()) {
        showModal('Compte', '<p>Connecté en tant que <b>' + escapeHtml(state.online.pseudo) + '</b>.</p>',
          [{ label: 'Fermer' }, {
            label: 'Se déconnecter', primary: true, onClick: function () {
              state.online.token = '';
              helpers.save();
              clan = null;
              refreshWorld();
              helpers.toast('Déconnecté');
            }
          }]);
      } else {
        showAuth(function () {});
      }
    });

    setInterval(function () { sync(false); }, 60000);
    refreshWorld();
  }

  window.Online = {
    init: init,
    showAuth: showAuth,
    shouldGate: function () { return !connected(); },
    connected: connected,
    sync: sync,
    refresh: function () {
      // À l'ouverture de l'onglet Monde : on pousse d'abord la progression
      // pour que le joueur se voie au classement avec ses vrais chiffres.
      // Cinq secondes de garde évitent d'inonder le serveur en changeant
      // d'onglet à répétition.
      var now = Date.now();
      if (now - lastRefresh < 5000) return;
      lastRefresh = now;
      sync(true).then(refreshWorld, refreshWorld);
    }
  };
})();
