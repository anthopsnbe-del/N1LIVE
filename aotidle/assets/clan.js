/* AOT IDLE — journal de clan et emotes.

   Le clan avait un chat et une guerre, mais aucune mémoire : qui a engagé le
   clan, qui a fait tomber le front, qui est reparti avec quoi. Le journal tient
   cette trace, écrite par le serveur lui-même (`clan-core.php`) à chaque fait
   marquant. Les emotes sont une liste fixe côté serveur : le client n'envoie
   qu'un identifiant, ce qui évite d'ajouter un second champ de texte libre à
   modérer. Le panneau vit sous l'écran « Guerre de clans ». */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var view = null;
  var notice = '';
  var busy = false;
  var ready = false;
  var lastPoll = 0;
  var noClan = false;

  function api() { return window.Api; }
  function connected() { return !!(api() && api().connected()); }

  function ago(at, now) {
    var sec = Math.max(0, Math.round(((now || Date.now()) - at) / 1000));
    if (sec < 60) return 'à l\'instant';
    if (sec < 3600) return 'il y a ' + Math.floor(sec / 60) + ' min';
    if (sec < 86400) return 'il y a ' + Math.floor(sec / 3600) + ' h';
    return 'il y a ' + Math.floor(sec / 86400) + ' j';
  }

  function run(action, payload) {
    if (busy || !connected()) { render(); return; }
    busy = true;
    render();
    api().call(action, payload || {}).then(function (r) {
      view = r;
      noClan = false;
      notice = '';
    }).catch(function (e) {
      // « Rejoignez un clan » n'est pas une panne : c'est l'état du joueur.
      noClan = /clan/i.test(e.message || '');
      notice = noClan ? '' : e.message;
    }).finally(function () {
      busy = false;
      lastPoll = Date.now();
      render();
    });
  }

  function emote(id) {
    if (window.Fx) window.Fx.sound('ui');
    run('clan_emote', { emote: id });
  }

  function render() {
    if (!ready) return;
    var offline = !connected();
    $('clan-journal-panel').hidden = offline;
    if (offline) return;

    $('clan-journal-notice').textContent = notice;
    $('clan-journal-hint').hidden = !noClan;
    $('clan-emotes').hidden = noClan;

    var box = $('clan-journal-list');
    box.textContent = '';
    var entries = (view && view.journal) || [];
    if (noClan || !entries.length) {
      var empty = document.createElement('p');
      empty.className = 'hint';
      empty.textContent = noClan
        ? 'Rejoignez un clan depuis l\'onglet Monde : le journal s\'ouvrira ici.'
        : busy ? 'Chargement du journal…'
        : 'Rien encore. Le journal se remplit dès la première guerre ou emote.';
      box.append(empty);
    }
    entries.forEach(function (entry) {
      var row = document.createElement('div');
      row.className = 'clan-entry kind-' + entry.kind;
      var who = document.createElement('b');
      who.textContent = entry.who || 'Le clan';
      var body = document.createElement('span');
      body.textContent = entry.body;
      var when = document.createElement('em');
      when.textContent = ago(entry.at, view && view.serverNow);
      row.append(who, body, when);
      box.append(row);
    });

    var bar = $('clan-emotes');
    var emotes = (view && view.emotes) || [];
    if (bar.dataset.count !== String(emotes.length)) {
      bar.textContent = '';
      emotes.forEach(function (item) {
        var button = document.createElement('button');
        button.className = 'clan-emote';
        button.innerHTML = '<b></b><span></span>';
        button.querySelector('b').textContent = item.icon;
        button.querySelector('span').textContent = item.text;
        button.onclick = function () { emote(item.id); };
        bar.append(button);
      });
      bar.dataset.count = emotes.length;
    }
    bar.querySelectorAll('button').forEach(function (b) { b.disabled = busy; });
  }

  window.Clan = {
    refresh: function (force) {
      if (!connected()) { render(); return; }
      if (!force && Date.now() - lastPoll < 20000) return;
      run('clan_journal');
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    var panel = document.createElement('section');
    panel.id = 'clan-journal-panel';
    panel.className = 'clan-journal';
    panel.hidden = true;
    panel.innerHTML = '<h2>Journal du clan</h2>'
      + '<p class="hint">Ce que le clan a fait : engagements, fronts effondrés, butins. '
      + 'Les emotes sont visibles par tous les membres.</p>'
      + '<p id="clan-journal-notice" role="status"></p>'
      + '<p id="clan-journal-hint" class="hint" hidden>Rejoignez un clan pour ouvrir son journal.</p>'
      + '<div id="clan-journal-list" class="clan-log"></div>'
      + '<div id="clan-emotes" class="emote-bar"></div>';
    var war = $('tab-war');
    if (war) war.append(panel);

    ready = true;
    render();
    setInterval(function () {
      if (document.hidden) return;
      if (!$('tab-war').classList.contains('active')) return;
      window.Clan.refresh(false);
    }, 5000);
    window.addEventListener('aot-resume', function () { window.Clan.refresh(true); });
  });
})();
