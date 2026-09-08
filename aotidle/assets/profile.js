/* AOT IDLE — fiche de soldat : identité, titres, registre et confort de jeu.

   Un seul écran pour tout ce qui vous concerne : portrait, pseudo, monde et
   cote d'arène, titres débloqués, statistiques de campagne, et les réglages
   qu'on veut pouvoir changer sans fouiller (son, animations, images par
   seconde). Le pseudo part au serveur quand le compte est connecté ; hors
   ligne, il reste local. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var notice = '';
  var ready = false;
  var busy = false;

  function game() { return window.__game; }
  function connected() { return !!(window.Api && window.Api.connected()); }

  /* Titres : des jalons qui racontent la partie, tous vérifiables localement. */
  var TITLES = [
    { id: 'cadet', name: 'Cadet du 104e', desc: 'Rejoindre le bataillon', test: function () { return true; } },
    { id: 'eclaireur', name: 'Éclaireur', desc: 'Atteindre le rang 10',
      test: function (s) { return s.level >= 10; } },
    { id: 'chasseur', name: 'Chasseur de titans', desc: 'Abattre 1 000 ennemis',
      test: function (s) { return s.stats.kills >= 1000; } },
    { id: 'briseur', name: 'Briseur de murs', desc: 'Terminer 25 chapitres',
      test: function (s) { return s.record.chapter >= 25; } },
    { id: 'grimpeur', name: 'Grimpeur', desc: 'Conquérir 25 étages de la Tour',
      test: function (s) { return (s.journey.towerBest || 0) >= 25; } },
    { id: 'ame', name: 'Âme errante', desc: 'Renaître trois fois',
      test: function (s) { return s.stats.prestiges >= 3; } },
    { id: 'ackerman', name: 'Sang d\'Ackerman', desc: 'Abattre 100 boss de chapitre',
      test: function (s) { return s.stats.bosses >= 100; } },
    { id: 'commandant', name: 'Commandant', desc: 'Atteindre le chapitre 100',
      test: function (s) { return s.bestChapter >= 100; } }
  ];

  function unlockedTitles(s) {
    return TITLES.filter(function (title) { return title.test(s); });
  }

  function currentTitle(s) {
    var list = unlockedTitles(s);
    var chosen = list.filter(function (t) { return t.id === s.title; })[0];
    return chosen || list[list.length - 1];
  }

  function fmtTime(sec) {
    var hours = Math.floor(sec / 3600);
    var minutes = Math.floor((sec % 3600) / 60);
    return hours ? hours + ' h ' + minutes + ' min' : minutes + ' min';
  }

  function rename() {
    var field = $('profile-pseudo-input');
    var value = (field.value || '').trim();
    if (value.length < 3) { notice = 'Pseudo : au moins trois caractères.'; render(); return; }
    if (!connected()) {
      // Hors ligne, le pseudo n'est qu'un nom d'affichage local.
      game().state.online.pseudo = value.slice(0, 18);
      game().save();
      notice = 'Pseudo enregistré sur cet appareil. Connectez-vous pour le réserver en ligne.';
      render();
      return;
    }
    busy = true;
    render();
    window.Api.call('rename', { pseudo: value }).then(function (r) {
      game().state.online.pseudo = r.profile ? r.profile.pseudo : value;
      game().save();
      notice = 'Pseudo mis à jour.';
    }).catch(function (e) {
      notice = e.message;
    }).finally(function () { busy = false; render(); });
  }

  function setTitle(id) {
    game().state.title = id;
    game().save();
    if (window.Fx) window.Fx.sound('ui');
    render();
  }

  function setFps(value) {
    window.Fx.prefs.fps = value;
    window.Fx.savePrefs();
    render();
  }

  function render() {
    if (!ready || !game()) return;
    var s = game().state;
    var title = currentTitle(s);

    $('profile-avatar').innerHTML = Art.icon('heroes', s.portrait);
    var avatar = $('topbar-avatar');
    if (avatar && avatar.dataset.portrait !== String(s.portrait)) {
      avatar.innerHTML = Art.icon('heroes', s.portrait);
      avatar.dataset.portrait = s.portrait;
    }
    $('profile-name').textContent = s.online.pseudo || Art.names[s.portrait] || 'Soldat';
    $('profile-title').textContent = title ? title.name : 'Cadet du 104e';
    $('profile-line').textContent = 'Rang ' + s.level + ' · chapitre ' + s.chapter + ' / 1 000'
      + (connected() ? ' · en ligne' : ' · hors ligne');
    $('profile-notice').textContent = notice;
    $('profile-pseudo-input').placeholder = s.online.pseudo || 'Soldat';
    $('profile-save').disabled = busy;

    var titles = $('profile-titles');
    titles.textContent = '';
    TITLES.forEach(function (entry) {
      var unlocked = entry.test(s);
      var button = document.createElement('button');
      button.className = 'title-chip' + (title && title.id === entry.id ? ' selected' : '');
      button.disabled = !unlocked;
      button.innerHTML = '<b></b><span></span>';
      button.querySelector('b').textContent = entry.name;
      button.querySelector('span').textContent = unlocked ? 'Débloqué' : entry.desc;
      button.onclick = function () { setTitle(entry.id); };
      titles.append(button);
    });

    var stats = [
      ['Rang', s.level],
      ['Chapitre atteint', s.bestChapter],
      ['Chapitres terminés', s.record.chapter],
      ['Titans abattus', game().fmt(s.stats.kills)],
      ['Boss vaincus', game().fmt(s.stats.bosses)],
      ['Étages de la Tour', (s.journey.towerBest || 0) + ' / 200'],
      ['Renaissances', s.stats.prestiges],
      ['Âmes', game().fmt(s.souls)],
      ['Or total récolté', game().fmt(s.stats.goldTotal)],
      ['Temps de jeu', fmtTime(s.stats.playtime)]
    ];
    var box = $('profile-stats');
    box.textContent = '';
    stats.forEach(function (row) {
      var cell = document.createElement('div');
      cell.className = 'stat';
      var label = document.createElement('span');
      label.textContent = row[0];
      var value = document.createElement('b');
      value.textContent = row[1];
      cell.append(label, value);
      box.append(cell);
    });

    var prefs = window.Fx.prefs;
    $('profile-sound').checked = prefs.sound;
    $('profile-motion').checked = !prefs.motion;
    $('profile-meter').checked = !!prefs.meter;
    $('profile-music').checked = prefs.music !== false;
    document.querySelectorAll('[data-fps]').forEach(function (b) {
      b.classList.toggle('selected', Number(b.dataset.fps) === (prefs.fps || 0));
    });
  }

  window.Profile = { refresh: render, title: function () { return currentTitle(game().state); } };

  document.addEventListener('DOMContentLoaded', function () {
    var section = document.createElement('section');
    section.id = 'tab-profile';
    section.className = 'tab';
    section.innerHTML = '<header class="profile-head">'
      + '<div id="profile-avatar" class="profile-avatar"></div>'
      + '<div><h1 id="profile-name">Soldat</h1>'
      + '<p id="profile-title" class="profile-rank"></p>'
      + '<p id="profile-line" class="hint"></p></div></header>'
      + '<p id="profile-notice" role="status"></p>'
      + '<h2>Identité</h2>'
      + '<label class="field"><span>Pseudo public (3 à 18 caractères)</span>'
      + '<input id="profile-pseudo-input" maxlength="18" autocomplete="off"></label>'
      + '<button id="profile-save" class="big-btn">Enregistrer le pseudo</button>'
      + '<h2>Portrait</h2><p class="hint">Le portrait choisi vous représente au combat, '
      + 'dans l\'arène et sur les classements.</p>'
      + '<div id="profile-portraits"></div>'
      + '<h2>Titres</h2><p class="hint">Débloqués par vos exploits. Le titre choisi s\'affiche ici.</p>'
      + '<div id="profile-titles" class="title-grid"></div>'
      + '<h2>Registre</h2><div id="profile-stats" class="stats"></div>'
      + '<h2>Confort de jeu</h2>'
      + '<label class="switch"><input id="profile-sound" type="checkbox"> <span>Effets sonores</span></label>'
      + '<label class="switch"><input id="profile-motion" type="checkbox"> <span>Animations réduites</span></label>'
      + '<label class="switch"><input id="profile-music" type="checkbox"> <span>Ambiance sonore</span></label>'
      + '<label class="switch"><input id="profile-meter" type="checkbox"> <span>Afficher les images par seconde</span></label>'
      + '<p class="hint">Limite d\'images : moins d\'images, moins de batterie. '
      + 'La simulation, elle, tourne toujours en temps réel.</p>'
      + '<div class="qty-picker fps-picker">'
      + '<button data-fps="30">30 i/s</button><button data-fps="60">60 i/s</button>'
      + '<button data-fps="0">Sans limite</button></div>'
      + '<button id="profile-settings" class="ghost-btn">Compte, sauvegarde et serveur…</button>';
    document.querySelector('main').append(section);

    // Le choix du portrait vivait dans « Escouade » : sa place est ici, sur la
    // fiche. On déplace le nœud existant plutôt que d'en créer un second.
    var portraits = $('portraits');
    if (portraits) $('profile-portraits').append(portraits);

    $('profile-save').onclick = rename;
    $('profile-settings').onclick = function () { $('settings-btn').click(); };
    $('profile-sound').onchange = function () {
      window.Fx.prefs.sound = this.checked;
      window.Fx.savePrefs();
      var sfx = $('sfx-option');
      if (sfx) sfx.checked = this.checked;
    };
    $('profile-motion').onchange = function () {
      window.Fx.prefs.motion = !this.checked;
      window.Fx.savePrefs();
      var motion = $('motion-option');
      if (motion) motion.checked = this.checked;
    };
    $('profile-meter').onchange = function () {
      window.Fx.prefs.meter = this.checked;
      window.Fx.savePrefs();
    };
    $('profile-music').onchange = function () {
      window.Fx.prefs.music = this.checked;
      window.Fx.startAmbience();
      window.Fx.savePrefs();
    };
    document.querySelectorAll('[data-fps]').forEach(function (b) {
      b.onclick = function () { setFps(Number(b.dataset.fps)); };
    });

    // Accès direct depuis la topbar : le portrait sert de bouton.
    var avatar = document.createElement('button');
    avatar.id = 'topbar-avatar';
    avatar.className = 'icon-btn avatar-btn';
    avatar.setAttribute('aria-label', 'Fiche de soldat');
    avatar.onclick = function () {
      if (window.Hub) window.Hub.route('profile');
    };
    var line = document.querySelector('.hero-line');
    if (line) line.insertBefore(avatar, $('settings-btn'));

    ready = true;
    render();
    setInterval(function () {
      if (!document.hidden && $('tab-profile').classList.contains('active')) render();
    }, 1000);
  });
})();
