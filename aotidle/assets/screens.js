/* AOT IDLE — écrans annexes : carte de campagne, archives des portraits et
   feuille de réglages. Le rendu du combat vit dans `fx.js`. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };

  /* Ouverture / fermeture d'une feuille modale, avec piège à tabulation :
     au clavier comme au doigt, on ne sort jamais du panneau par accident. */
  function sheet(id, opener) {
    var el = $(id);
    if (!el) return null;
    var control = {
      open: function () {
        el.classList.remove('hidden');
        var first = el.querySelector('button, select, input');
        if (first) first.focus();
      },
      close: function () {
        el.classList.add('hidden');
        if (opener) opener.focus();
      },
      isOpen: function () { return !el.classList.contains('hidden'); }
    };
    el.addEventListener('click', function (event) { if (event.target === el) control.close(); });
    document.addEventListener('keydown', function (event) {
      if (!control.isOpen()) return;
      if (event.key === 'Escape') { control.close(); return; }
      if (event.key !== 'Tab') return;
      var nodes = el.querySelectorAll('button:not(:disabled), select, input:not(:disabled)');
      if (!nodes.length) return;
      var first = nodes[0], last = nodes[nodes.length - 1];
      if (event.shiftKey && document.activeElement === first) { last.focus(); event.preventDefault(); }
      else if (!event.shiftKey && document.activeElement === last) { first.focus(); event.preventDefault(); }
    });
    return control;
  }

  document.addEventListener('DOMContentLoaded', function () {
    // ----------------------------------------------------------------
    // Réglages
    // ----------------------------------------------------------------
    var settings = sheet('settings-sheet', $('settings-btn'));
    $('settings-btn').onclick = settings.open;
    $('settings-close').onclick = settings.close;

    var sfx = $('sfx-option'), motion = $('motion-option');
    sfx.checked = Fx.prefs.sound;
    motion.checked = !Fx.prefs.motion;
    sfx.onchange = motion.onchange = function () {
      Fx.prefs.sound = sfx.checked;
      Fx.prefs.motion = !motion.checked;
      Fx.savePrefs();
      Fx.sound('ui');
    };

    // ----------------------------------------------------------------
    // Archives : choix du portrait
    // ----------------------------------------------------------------
    var portraits = $('portraits');
    Art.names.forEach(function (name, i) {
      var button = document.createElement('button');
      button.innerHTML = Art.icon('heroes', i) + '<span>' + name + '</span>';
      button.dataset.portrait = i;
      button.onclick = function () {
        window.__game.setPortrait(i);
        if (window.Social) window.Social.setPortrait(i);
        Fx.sound('ui');
        updatePortrait();
      };
      portraits.append(button);
    });

    function updatePortrait() {
      if (!window.__game) return;
      portraits.querySelectorAll('button').forEach(function (b) {
        b.classList.toggle('selected', +b.dataset.portrait === window.__game.state.portrait);
      });
    }

    // ----------------------------------------------------------------
    // Carte de campagne
    // ----------------------------------------------------------------
    var panel = document.createElement('div');
    panel.id = 'campaign-sheet';
    panel.className = 'sheet hidden';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-label', 'Carte de campagne');
    panel.innerHTML = '<div class="sheet-panel campaign-panel">'
      + '<header><div><small>ARCHIVES DES EXPÉDITIONS</small><h2>Carte de campagne</h2></div>'
      + '<button id="map-close" aria-label="Fermer">×</button></header>'
      + '<p id="campaign-status"></p><label>Arc <select id="arc-select"></select></label>'
      + '<p id="arc-summary"></p><div id="chapter-grid"></div>'
      + '<p class="hint">Les étapes secondaires sont des missions originales de fan. '
      + 'Les jalons suivent les grands événements de la série. Les étapes futures contiennent des spoilers.</p></div>';
    document.body.append(panel);

    var campaign = sheet('campaign-sheet', $('map-open'));
    var select = $('arc-select');
    window.Content.ARCS.forEach(function (arc, i) {
      var option = document.createElement('option');
      option.value = i;
      option.textContent = (i + 1) + ' · ' + arc[0];
      select.append(option);
    });

    function drawMap() {
      var s = window.__game.state;
      var arc = +select.value;
      $('campaign-status').textContent = 'Position : ' + s.chapter + ' / 1 000 · Terminé : '
        + s.record.chapter + ' · Débloqué : ' + s.bestChapter;
      $('arc-summary').textContent = window.Content.ARCS[arc][1] + ' · ' + window.Content.ARCS[arc][3];
      var grid = $('chapter-grid');
      grid.textContent = '';
      for (var j = 0; j < 100; j++) {
        var n = arc * 100 + j + 1;
        var button = document.createElement('button');
        button.textContent = n;
        button.disabled = n > s.bestChapter;
        button.className = n === s.chapter ? 'current' : n <= s.record.chapter ? 'complete' : '';
        button.title = window.Content.CHAPTERS[n - 1].brief;
        button.setAttribute('aria-label', 'Chapitre ' + n + (button.disabled ? ' verrouillé'
          : n === s.chapter ? ' actuel' : n <= s.record.chapter ? ' terminé' : ''));
        button.dataset.chapter = n;
        button.onclick = function () {
          if (!window.__game.travel(+this.dataset.chapter)) return;
          campaign.close();
          Fx.sound('ui');
          document.querySelector('[data-tab="tab-combat"]').click();
        };
        grid.append(button);
      }
    }

    $('map-open').onclick = function () {
      select.value = Math.floor((window.__game.state.chapter - 1) / 100);
      drawMap();
      campaign.open();
    };
    $('map-close').onclick = campaign.close;
    select.onchange = drawMap;

    // ----------------------------------------------------------------
    // Suivi du chapitre courant
    // ----------------------------------------------------------------
    var lastChapter = 0;
    setInterval(function () {
      var game = window.__game;
      if (!game) return;
      var chapter = window.Content.CHAPTERS[game.state.chapter - 1];
      if (!chapter || lastChapter === game.state.chapter) return;
      lastChapter = game.state.chapter;
      $('mission-brief').textContent = chapter.brief;
      updatePortrait();
    }, 250);
  });
})();
