/* AOT IDLE — Archives du bataillon : la collection de personnages.

   Chaque recrue a sa carte. Recruter un exemplaire débloque la carte ; tant
   qu'elle est verrouillée, on voit sa silhouette, sa rareté et ce qu'il faut
   faire pour l'obtenir. Les cartes sont rangées de R à LR, et la collection
   entière donne un bonus permanent au bataillon — collectionner sert.

   Le portrait du joueur se choisit ici, parmi les personnages débloqués. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var ready = false;
  var filter = 'tous';

  function game() { return window.__game; }

  var RARITY_LABEL = {
    R: 'Commune', SR: 'Rare', SSR: 'Super rare',
    UR: 'Ultra rare', LP: 'Légendaire', LR: 'Légende du bataillon'
  };

  function rank(rarity) {
    var order = game().rarities;
    var index = order.indexOf(rarity);
    return index < 0 ? 0 : index;
  }

  /* Ordre d'affichage : rareté croissante, puis coût — on lit la collection
     comme une échelle, de la recrue de base à la légende. */
  function sorted() {
    return game().companions.slice().sort(function (a, b) {
      return rank(a.rarity) - rank(b.rarity) || a.cost - b.cost;
    });
  }

  function owned(def) { return game().state.team[def.id] || 0; }

  function unlockHint(def) {
    return 'Recruter ' + def.name + ' dans l\'Escouade (' + game().fmt(def.cost) + ' or).';
  }

  function detail(def) {
    var count = owned(def);
    var passive = game().passiveOf(def);
    var state = game().state;
    var body = '<div class="card-detail">'
      + '<div class="hero-card is-' + def.rarity + (count ? ' unlocked' : ' locked') + ' big">'
      + '<div class="hero-card-art">' + Art.icon('heroes', def.portrait) + '</div>'
      + '<span class="hero-card-rarity">' + def.rarity + '</span></div>'
      + '<div><h4>' + def.name + '</h4>'
      + '<p class="hint">' + def.rarity + ' · ' + RARITY_LABEL[def.rarity] + '</p>'
      + '<p>' + (count
        ? '<b>×' + count + '</b> en escouade · ' + game().fmt(def.dps * (1 + Math.floor(count / 25) * 0.5))
          + ' DPS par recrue'
        : 'Carte verrouillée.') + '</p>'
      + (passive ? '<p class="hint">Passif : +'
        + Math.round(passive.per * (count ? 1 + Math.floor(count / 25) * 0.5 : 1) * 100)
        + ' % ' + passive.label + '</p>' : '')
      + '<p class="hint">' + (count ? 'Palier suivant à ' + ((Math.floor(count / 25) + 1) * 25)
        + ' exemplaires.' : unlockHint(def)) + '</p></div></div>';

    var actions = [{ label: 'Fermer' }];
    if (count) {
      actions.push({
        label: state.portrait === def.portrait ? 'Portrait actuel' : 'Choisir comme portrait',
        primary: true,
        onClick: function () {
          game().setPortrait(def.portrait);
          if (window.Social) window.Social.setPortrait(def.portrait);
          if (window.Profile) window.Profile.refresh();
          if (window.Fx) window.Fx.sound('ui');
          render();
        }
      });
    }
    game().modal('Archives du bataillon', body, actions);
  }

  function card(def) {
    var count = owned(def);
    var current = game().state.portrait === def.portrait;
    var el = document.createElement('button');
    el.className = 'hero-card is-' + def.rarity + (count ? ' unlocked' : ' locked')
      + (current ? ' current' : '');
    el.dataset.rarity = def.rarity;
    el.innerHTML = '<div class="hero-card-art">' + Art.icon('heroes', def.portrait) + '</div>'
      + '<span class="hero-card-rarity"></span>'
      + '<b class="hero-card-name"></b>'
      + '<span class="hero-card-count"></span>';
    el.querySelector('.hero-card-rarity').textContent = def.rarity;
    el.querySelector('.hero-card-name').textContent = count ? def.name : '???';
    el.querySelector('.hero-card-count').textContent = count ? '×' + count : 'Verrouillée';
    el.onclick = function () { detail(def); };
    return el;
  }

  function render() {
    if (!ready || !game()) return;
    var box = $('collection-grid');
    var stats = game().collection();
    $('collection-progress').textContent = stats.owned + ' / ' + stats.total + ' personnages · bonus de collection +'
      + Math.round((stats.bonus - 1) * 100) + ' % de dégâts';
    var bar = $('collection-bar');
    bar.value = stats.owned;
    bar.max = stats.total;

    box.textContent = '';
    var list = sorted().filter(function (def) {
      return filter === 'tous' || def.rarity === filter;
    });
    list.forEach(function (def) { box.append(card(def)); });

    document.querySelectorAll('[data-rarity-filter]').forEach(function (b) {
      b.classList.toggle('selected', b.dataset.rarityFilter === filter);
    });
  }

  window.Collection = { refresh: render };

  document.addEventListener('DOMContentLoaded', function () {
    var host = $('tab-team');
    if (!host) return;

    var section = document.createElement('section');
    section.id = 'collection';
    section.innerHTML = '<h2>Archives du bataillon</h2>'
      + '<p class="hint" id="collection-progress"></p>'
      + '<progress id="collection-bar" max="24" value="0"></progress>'
      + '<nav class="rarity-filters" id="collection-filters" aria-label="Filtrer par rareté"></nav>'
      + '<div id="collection-grid" class="collection-grid"></div>'
      + '<p class="hint">Recruter un personnage débloque sa carte définitivement, même après une '
      + 'renaissance… tant qu\'il reste dans votre escouade. Chaque carte ajoute +0,6 % de dégâts '
      + 'à tout le bataillon.</p>';
    host.prepend(section);

    var filters = $('collection-filters');
    ['tous'].concat(game() ? game().rarities : ['R', 'SR', 'SSR', 'UR', 'LP', 'LR']).forEach(function (value) {
      var button = document.createElement('button');
      button.dataset.rarityFilter = value;
      button.textContent = value === 'tous' ? 'Tous' : value;
      button.onclick = function () { filter = value; render(); };
      filters.append(button);
    });

    ready = true;
    render();
    setInterval(function () {
      if (!document.hidden && $('tab-team').classList.contains('active')) render();
    }, 1500);
  });
})();
