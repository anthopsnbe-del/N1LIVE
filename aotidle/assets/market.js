/* AOT IDLE — dépôt du bataillon et marché entre joueurs.

   Le dépôt est la part d'économie tenue par le serveur : il n'est alimenté que
   par ce que le serveur a lui-même accordé (boss mondial, guerres de clans,
   fins de saison). C'est pour cela qu'un marché est possible ici et pas sur
   l'or de la campagne, qui vit dans une sauvegarde locale invérifiable.

   Deux mouvements seulement, tous deux à sens unique vers le joueur :
   retirer des cristaux vers la sauvegarde, rapatrier une pièce dans le sac. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var view = null;
  var busy = false;
  var lastPoll = 0;
  var notice = '';
  var ready = false;
  var panel = 'depot';

  function api() { return window.Api; }
  function connected() { return !!(api() && api().connected()); }
  function format(n) { return window.__game ? window.__game.fmt(n) : String(n); }

  function run(action, payload, done) {
    if (busy || !connected()) { render(); return; }
    busy = true;
    notice = '';
    render();
    api().call(action, payload).then(function (r) {
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

  function withdrawCrystals() {
    run('wallet_withdraw', {}, function (r) {
      var gained = window.__game.addCrystals(r.crystals || 0);
      if (window.Fx) {
        window.Fx.sound('loot');
        window.Fx.banner('+' + gained + ' cristaux', 'RETIRÉ DU DÉPÔT', 'loot');
      }
      notice = gained + ' cristaux ajoutés à votre partie.';
    });
  }

  function withdrawItem(id, name) {
    run('item_withdraw', { itemId: id }, function (r) {
      if (r.item) window.__game.addGear(r.item);
      if (window.Fx) window.Fx.sound('loot');
      notice = name + ' rangé dans votre sac, onglet Soldat.';
    });
  }

  /* Mise en vente : le prix est demandé dans la modale du jeu, pas dans un
     `prompt()` du navigateur, qui n'a pas sa place dans une application. */
  function sell(item) {
    if (!window.__game || !view) return;
    var limits = view.limits;
    window.__game.modal('Mettre en vente',
      '<p>' + item.name + ' — <b>' + item.rarity + '</b>, +' + item.power + ' %</p>'
      + '<label class="field"><span>Prix en cristaux (' + limits.minPrice + ' à ' + limits.maxPrice + ')</span>'
      + '<input id="market-price" type="number" min="' + limits.minPrice + '" max="' + limits.maxPrice
      + '" value="' + Math.min(limits.maxPrice, Math.max(limits.minPrice, Math.round(item.power / 4))) + '"></label>'
      + '<p class="hint">Le bataillon prélève ' + Math.round(limits.fee * 100)
      + ' % sur la vente. Vous pouvez retirer l\'annonce tant qu\'elle n\'est pas vendue.</p>',
      [{ label: 'Annuler' }, {
        label: 'Mettre en vente', primary: true, onClick: function () {
          var field = document.getElementById('market-price');
          run('market_list', { itemId: item.id, price: Number(field && field.value) || 0 });
        }
      }]);
  }

  function rarityClass(rarity) { return 'rarity-' + rarity; }

  function itemCard(item, actions) {
    var card = document.createElement('div');
    card.className = 'card market-card';
    card.dataset.rarity = item.rarity;
    var body = document.createElement('div');
    body.className = 'body';
    var title = document.createElement('div');
    title.className = 'title';
    var name = document.createElement('span');
    name.className = 'c-title ' + rarityClass(item.rarity);
    name.textContent = item.name;
    var sub = document.createElement('small');
    sub.textContent = item.rarity;
    title.append(name, sub);
    var desc = document.createElement('div');
    desc.className = 'desc';
    desc.textContent = item.slot + ' · +' + item.power + ' % · ' + item.origin;
    body.append(title, desc);
    card.append(body);
    var box = document.createElement('div');
    box.className = 'bag-actions';
    actions.forEach(function (action) {
      var button = document.createElement('button');
      button.className = action.ghost ? 'ghost-btn small' : 'buy-btn';
      button.textContent = action.label;
      button.disabled = busy || action.disabled;
      button.onclick = action.onClick;
      box.append(button);
    });
    card.append(box);
    return card;
  }

  function renderDepot() {
    var box = $('market-depot');
    box.textContent = '';
    if (!view.items.length) {
      var empty = document.createElement('p');
      empty.className = 'hint';
      empty.textContent = 'Aucune pièce au dépôt. Le boss mondial et les guerres de clans en versent.';
      box.append(empty);
      return;
    }
    view.items.forEach(function (item) {
      box.append(itemCard(item, item.listed
        ? [{ label: 'En vente', disabled: true }]
        : [
          { label: 'Vendre', onClick: function () { sell(item); } },
          { label: 'Rapatrier', ghost: true, onClick: function () { withdrawItem(item.id, item.name); } }
        ]));
    });
  }

  function renderMarket() {
    var box = $('market-listings');
    box.textContent = '';
    if (!view.listings.length) {
      var empty = document.createElement('p');
      empty.className = 'hint';
      empty.textContent = 'Aucune annonce dans votre monde pour l\'instant.';
      box.append(empty);
      return;
    }
    view.listings.forEach(function (listing) {
      var actions = listing.mine
        ? [{ label: 'Retirer', ghost: true, onClick: function () { run('market_cancel', { listingId: listing.id }); } }]
        : [{
          label: listing.price + ' cristaux',
          disabled: view.wallet.crystals < listing.price,
          onClick: function () { run('market_buy', { listingId: listing.id }); }
        }];
      var card = itemCard(listing.item, actions);
      var seller = document.createElement('div');
      seller.className = 'desc market-seller';
      seller.textContent = listing.mine ? 'Votre annonce · ' + listing.price + ' cristaux'
        : 'Vendu par ' + listing.seller;
      card.querySelector('.body').append(seller);
      box.append(card);
    });
  }

  function renderHistory() {
    var box = $('market-history');
    box.textContent = '';
    var lines = view.wallet.history;
    if (!lines.length) {
      var empty = document.createElement('p');
      empty.className = 'hint';
      empty.textContent = 'Aucun mouvement pour l\'instant.';
      box.append(empty);
      return;
    }
    lines.forEach(function (line) {
      var row = document.createElement('div');
      row.className = 'boss-row';
      var delta = document.createElement('b');
      delta.textContent = (line.delta > 0 ? '+' : '') + line.delta;
      delta.style.color = line.delta > 0 ? 'var(--xp)' : 'var(--muted)';
      var reason = document.createElement('span');
      reason.textContent = line.reason;
      var when = document.createElement('em');
      when.textContent = new Date(line.at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
      row.append(delta, reason, when);
      box.append(row);
    });
  }

  function pending() {
    return view && view.wallet && (view.wallet.crystals > 0 || view.items.length > 0) ? 1 : 0;
  }

  function render() {
    if (!ready) return;
    var offline = !connected();
    $('market-offline').hidden = !offline;
    $('market-live').hidden = offline;
    $('market-notice').textContent = notice;

    var note = $('home-market-note');
    if (note) {
      note.textContent = offline ? 'Connexion requise.'
        : !view ? 'Chargement…'
        : view.wallet.crystals + ' cristaux · ' + view.items.length + ' pièce'
          + (view.items.length > 1 ? 's' : '') + ' au dépôt';
      var badge = $('home-market-badge');
      if (badge) {
        badge.textContent = pending();
        badge.classList.toggle('hidden', pending() === 0);
      }
    }

    if (offline || !view) return;

    $('market-balance').textContent = format(view.wallet.crystals);
    $('market-earned').textContent = 'Total versé depuis le début : ' + format(view.wallet.earned) + ' cristaux';
    var withdraw = $('market-withdraw');
    withdraw.disabled = busy || view.wallet.crystals < 1;
    withdraw.textContent = view.wallet.crystals > 0
      ? 'Retirer ' + view.wallet.crystals + ' cristaux vers la partie'
      : 'Dépôt vide';

    document.querySelectorAll('[data-market-nav]').forEach(function (b) {
      b.classList.toggle('selected', b.dataset.marketNav === panel);
    });
    $('market-panel-depot').hidden = panel !== 'depot';
    $('market-panel-market').hidden = panel !== 'market';
    $('market-panel-history').hidden = panel !== 'history';

    if (panel === 'depot') renderDepot();
    else if (panel === 'market') renderMarket();
    else renderHistory();
  }

  window.Market = {
    refresh: function (force) {
      if (!connected()) { render(); return; }
      if (!force && Date.now() - lastPoll < 15000) return;
      run('market_state', {});
    },
    pending: pending
  };

  document.addEventListener('DOMContentLoaded', function () {
    var section = document.createElement('section');
    section.id = 'tab-market';
    section.className = 'tab';
    section.innerHTML = '<header class="tower-heading"><small>DÉPÔT DU BATAILLON</small>'
      + '<h1><b id="market-balance">0</b> cristaux</h1><p id="market-earned"></p></header>'
      + '<div id="market-offline" hidden><p class="hint">Le dépôt garde les récompenses versées par le serveur '
      + '(boss mondial, guerres de clans, saisons) et sert de monnaie au marché. Il demande un compte : '
      + 'ouvrez « Compte » dans l\'onglet Monde.</p></div>'
      + '<div id="market-live">'
      + '<button id="market-withdraw" class="big-btn"></button>'
      + '<p id="market-notice" role="status"></p>'
      + '<nav class="social-nav market-nav" aria-label="Sections du dépôt">'
      + '<button data-market-nav="depot" class="selected">Mon dépôt</button>'
      + '<button data-market-nav="market">Marché</button>'
      + '<button data-market-nav="history">Mouvements</button></nav>'
      + '<section id="market-panel-depot"><h2>Pièces en dépôt</h2>'
      + '<p class="hint">Vendez-les au marché ou rapatriez-les dans votre sac. '
      + 'Une pièce rapatriée quitte définitivement le marché.</p>'
      + '<div id="market-depot" class="cards"></div></section>'
      + '<section id="market-panel-market" hidden><h2>Annonces de votre monde</h2>'
      + '<p class="hint">Achats et ventes en cristaux du dépôt. Le bataillon prélève 10 % sur chaque vente.</p>'
      + '<div id="market-listings" class="cards"></div></section>'
      + '<section id="market-panel-history" hidden><h2>Mouvements du dépôt</h2>'
      + '<div id="market-history" class="boss-board"></div></section>'
      + '<p class="hint">Seul l\'équipement émis par le serveur s\'échange ici : l\'or et les cristaux de '
      + 'votre campagne restent dans votre sauvegarde, invérifiables par le serveur, et n\'entrent pas '
      + 'sur le marché.</p></div>';
    document.querySelector('main').append(section);

    $('market-withdraw').onclick = withdrawCrystals;
    document.querySelectorAll('[data-market-nav]').forEach(function (b) {
      b.onclick = function () { panel = b.dataset.marketNav; render(); };
    });
    ready = true;
    render();

    setInterval(function () {
      if (document.hidden) return;
      var active = $('tab-market').classList.contains('active');
      var home = $('tab-home') && $('tab-home').classList.contains('active');
      if (!connected()) { if (active || home) render(); return; }
      if (active || home || Date.now() - lastPoll > 120000) window.Market.refresh(false);
    }, 3000);
    window.addEventListener('aot-resume', function () { window.Market.refresh(true); });
  });
})();
