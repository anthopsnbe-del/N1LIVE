/* AOT IDLE — cosmétiques : cape, harnais, cadre.

   Trois pièces qui ne changent rien aux statistiques : elles se voient, c'est
   tout. Chacune se débloque par un exploit déjà mesuré par le jeu (rang,
   chapitre, Tour, renaissances…), donc rien à acheter et rien à synchroniser
   avec le serveur. Le rendu est entièrement en CSS par-dessus le portrait
   (`fx.css`, section « cosmétiques ») : aucune image supplémentaire dans
   l'APK, et le tout disparaît proprement si « Animations réduites » est actif.

   Le portrait décoré sert sur la fiche de soldat et dans la topbar. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var ready = false;

  function game() { return window.__game; }

  /* Chaque pièce : identifiant CSS, nom, condition et texte de la condition.
     La première de chaque liste est acquise d'emblée. */
  var SETS = [
    {
      slot: 'cape',
      label: 'Cape',
      hint: 'Portée sur les épaules, visible derrière le portrait.',
      items: [
        { id: '', name: 'Sans cape', desc: 'Tenue de cadet', test: function () { return true; } },
        { id: 'exploration', name: 'Ailes de la liberté', desc: 'Terminer le chapitre 10',
          test: function (s) { return s.bestChapter >= 10; } },
        { id: 'garnison', name: 'Roses de la Garnison', desc: 'Atteindre le rang 25',
          test: function (s) { return s.level >= 25; } },
        { id: 'brigade', name: 'Licorne de la Brigade', desc: 'Renaître trois fois',
          test: function (s) { return s.stats.prestiges >= 3; } },
        { id: 'marley', name: 'Manteau de Marley', desc: 'Abattre 50 boss de chapitre',
          test: function (s) { return s.stats.bosses >= 50; } },
        { id: 'liberte', name: 'Cape du commandant', desc: 'Atteindre le chapitre 100',
          test: function (s) { return s.bestChapter >= 100; } }
      ]
    },
    {
      slot: 'harness',
      label: 'Harnais',
      hint: 'Les sangles de l\'équipement tridimensionnel.',
      items: [
        { id: '', name: 'Cuir simple', desc: 'Fourni au 104e', test: function () { return true; } },
        { id: 'renforce', name: 'Harnais renforcé', desc: 'Abattre 500 titans',
          test: function (s) { return s.stats.kills >= 500; } },
        { id: 'ackerman', name: 'Sangles d\'Ackerman', desc: 'Conquérir 25 étages de la Tour',
          test: function (s) { return (s.journey.towerBest || 0) >= 25; } },
        { id: 'foudre', name: 'Lances foudroyantes', desc: 'Abattre 100 boss de chapitre',
          test: function (s) { return s.stats.bosses >= 100; } },
        { id: 'titan', name: 'Attelage de titan', desc: 'Renaître cinq fois',
          test: function (s) { return s.stats.prestiges >= 5; } }
      ]
    },
    {
      slot: 'frame',
      label: 'Cadre',
      hint: 'L\'encadrement du portrait, sur la fiche et dans la topbar.',
      items: [
        { id: '', name: 'Bois brut', desc: 'Cadre de caserne', test: function () { return true; } },
        { id: 'acier', name: 'Acier trempé', desc: 'Atteindre le rang 15',
          test: function (s) { return s.level >= 15; } },
        { id: 'or', name: 'Or du bataillon', desc: 'Terminer le chapitre 50',
          test: function (s) { return s.bestChapter >= 50; } },
        { id: 'sang', name: 'Serment de sang', desc: 'Conquérir 50 étages de la Tour',
          test: function (s) { return (s.journey.towerBest || 0) >= 50; } },
        { id: 'eclipse', name: 'Éclipse', desc: 'Débloquer 12 cartes des Archives',
          test: function (s) { return Object.keys(s.seen || {}).length >= 12; } }
      ]
    }
  ];

  function worn(s, slot) {
    var set = SETS.filter(function (entry) { return entry.slot === slot; })[0];
    var chosen = (s.cosmetics || {})[slot] || '';
    var item = set.items.filter(function (i) { return i.id === chosen; })[0];
    // Une pièce dont la condition n'est plus remplie (renaissance) revient au
    // choix par défaut plutôt que de disparaître sans explication.
    return item && item.test(s) ? item.id : '';
  }

  /* Pose les cosmétiques sur un conteneur de portrait : trois attributs, le
     reste est du CSS. */
  function decorate(el) {
    if (!el || !game()) return;
    var s = game().state;
    el.classList.add('cos');
    el.dataset.cape = worn(s, 'cape');
    el.dataset.harness = worn(s, 'harness');
    el.dataset.frame = worn(s, 'frame');
  }

  function decorateAll() {
    decorate($('profile-avatar'));
    decorate($('topbar-avatar'));
  }

  function unlockedCount(s) {
    var n = 0;
    SETS.forEach(function (set) {
      set.items.forEach(function (item) { if (item.id && item.test(s)) n++; });
    });
    return n;
  }

  function choose(slot, id) {
    var s = game().state;
    if (!s.cosmetics) s.cosmetics = { cape: '', harness: '', frame: '' };
    s.cosmetics[slot] = id;
    game().save();
    if (window.Fx) window.Fx.sound('ui');
    render();
  }

  function render() {
    if (!ready || !game()) return;
    var s = game().state;
    decorateAll();
    $('cos-count').textContent = unlockedCount(s) + ' pièce'
      + (unlockedCount(s) > 1 ? 's' : '') + ' débloquée' + (unlockedCount(s) > 1 ? 's' : '')
      + ' sur ' + (SETS.reduce(function (n, set) { return n + set.items.length - 1; }, 0)) + '.';

    SETS.forEach(function (set) {
      var box = $('cos-' + set.slot);
      box.textContent = '';
      var current = worn(s, set.slot);
      set.items.forEach(function (item) {
        var unlocked = item.test(s);
        var button = document.createElement('button');
        button.className = 'cos-chip' + (item.id === current ? ' selected' : '');
        button.disabled = !unlocked;
        var preview = document.createElement('i');
        preview.className = 'cos-preview cos';
        preview.dataset[set.slot] = item.id;
        var name = document.createElement('b');
        name.textContent = item.name;
        var desc = document.createElement('span');
        desc.textContent = unlocked ? 'Débloqué' : item.desc;
        button.append(preview, name, desc);
        button.onclick = function () { choose(set.slot, item.id); };
        box.append(button);
      });
    });
  }

  window.Cosmetics = { refresh: render, decorate: decorate, worn: worn };

  document.addEventListener('DOMContentLoaded', function () {
    var tab = $('tab-profile');
    if (!tab) return;
    var block = document.createElement('div');
    block.id = 'cos-block';
    var html = '<h2>Cosmétiques</h2>'
      + '<p class="hint">Cape, harnais et cadre ne changent aucune statistique : '
      + 'ils se voient sur votre portrait, ici et dans la barre du haut. '
      + '<b id="cos-count"></b></p>';
    SETS.forEach(function (set) {
      html += '<h3 class="cos-title">' + set.label + '</h3>'
        + '<p class="hint">' + set.hint + '</p>'
        + '<div id="cos-' + set.slot + '" class="cos-grid"></div>';
    });
    block.innerHTML = html;
    // Juste après les titres : on garde « Confort de jeu » en dernier.
    var titles = $('profile-titles');
    if (titles) titles.after(block); else tab.append(block);

    ready = true;
    render();
  });
})();
