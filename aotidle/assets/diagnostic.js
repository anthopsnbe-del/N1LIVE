/* AOT IDLE — diagnostic du serveur.

   « Service multijoueur indisponible » ne dit pas grand-chose quand on vient
   justement de transférer ses fichiers. Ce panneau interroge le service par
   l'action `ping` — qui ne demande aucun compte — et traduit la réponse :
   version en ligne, modules chargés, tables présentes, et le nom du fichier
   PHP à renvoyer quand il en manque un. Il vérifie aussi `release.php`, ce qui
   explique du même coup une bannière de mise à jour qui reviendrait en boucle.

   Écran : onglet Monde, sous « Mises à jour ». */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var busy = false;

  // À quelle table correspond quel fichier : c'est ce qu'on veut pouvoir dire
  // au joueur quand une table manque.
  var FILES = {
    social_accounts: 'social-core.php',
    social_boss: 'boss-core.php',
    social_wars: 'war-core.php',
    social_wallet: 'wallet-core.php',
    social_items: 'wallet-core.php',
    social_market: 'market-core.php',
    social_seasons: 'season-core.php',
    social_clan_log: 'clan-core.php'
  };

  function base() {
    var s = window.__game && window.__game.state;
    return ((s && s.online && s.online.url) || 'https://asylum-games.fr/aotidle').replace(/\/+$/, '');
  }

  function line(box, text, cls) {
    var p = document.createElement('p');
    p.className = 'diag-line' + (cls ? ' ' + cls : '');
    p.textContent = text;
    box.append(p);
    return p;
  }

  function ping() {
    var controller = new AbortController();
    var timer = setTimeout(function () { controller.abort(); }, 8000);
    return fetch(base() + '/social.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'omit',
      cache: 'no-store',
      signal: controller.signal,
      body: JSON.stringify({ action: 'ping' })
    }).then(function (r) {
      return r.text().then(function (text) {
        var data = null;
        try { data = JSON.parse(text); } catch (e) {}
        return { status: r.status, data: data, text: text };
      });
    }).finally(function () { clearTimeout(timer); });
  }

  function release() {
    var build = window.AOT_BUILD;
    return fetch(build.releaseEndpoint + '?t=' + Date.now(), { cache: 'no-store', credentials: 'omit' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        // release.php répond aussi en erreur : sans versionCode, il n'y a rien à comparer.
        return data && Number.isSafeInteger(data.versionCode) ? data : null;
      })
      .catch(function () { return null; });
  }

  function run() {
    if (busy) return;
    busy = true;
    var box = $('diag-output');
    box.textContent = '';
    $('diag-run').disabled = true;
    line(box, 'Interrogation de ' + base() + '…');

    Promise.all([ping(), release()]).then(function (results) {
      var answer = results[0];
      var published = results[1];
      var build = window.AOT_BUILD;
      box.textContent = '';

      if (!answer.data) {
        line(box, 'Le serveur a répondu ' + answer.status + ' sans JSON lisible : l\'adresse '
          + 'pointe peut-être ailleurs que sur social.php.', 'bad');
      } else if (!answer.data.ok || !answer.data.version) {
        // Un social.php d'avant la 6.6 ne connaît pas « ping ».
        line(box, 'Les fichiers PHP en ligne sont antérieurs à la 6.6 : le service ne connaît pas '
          + 'l\'action « ping ». Renvoyez social.php et social-core.php — et, tant qu\'à faire, '
          + 'tous les *-core.php du paquet, ensemble.', 'bad');
        if (answer.data.error) line(box, 'Réponse du serveur : « ' + answer.data.error + ' »');
      } else {
        line(box, 'Service en ligne : version ' + answer.data.version
          + ' · jeu installé : version ' + build.versionName + '.',
          answer.data.version === build.versionName ? 'good' : 'warn');
        var missing = [];
        Object.keys(answer.data.tables || {}).forEach(function (table) {
          if (!answer.data.tables[table]) missing.push(FILES[table] || table);
        });
        missing = missing.filter(function (f, i) { return missing.indexOf(f) === i; });
        if (missing.length) {
          line(box, 'Fichiers à renvoyer sur le FTP : ' + missing.join(', ') + '.', 'bad');
        } else {
          line(box, 'Toutes les tables du service existent : boss, guerres, dépôt, marché, '
            + 'saisons et journal de clan sont opérationnels.', 'good');
        }
      }

      if (published) {
        var same = published.versionCode === build.versionCode;
        line(box, 'Site : version ' + published.versionName + ' (code ' + published.versionCode
          + ') · application : ' + build.versionName + ' (code ' + build.versionCode + ')'
          + (same ? ' — à jour.'
            : published.versionCode > build.versionCode
              ? ' — une mise à jour est bien disponible.'
              : ' — votre application est plus récente que le site.'),
          same ? 'good' : 'warn');
      } else {
        line(box, 'release.php ne renvoie pas de version exploitable : soit il est injoignable, '
          + 'soit release.json et l\'APK de releases/ ne se correspondent pas.', 'warn');
      }
    }).catch(function (e) {
      $('diag-output').textContent = '';
      line($('diag-output'), 'Serveur injoignable (' + (e.name === 'AbortError' ? 'délai dépassé'
        : e.message) + '). Vérifiez la connexion, puis l\'adresse du serveur dans les réglages.', 'bad');
    }).finally(function () {
      busy = false;
      $('diag-run').disabled = false;
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var world = $('tab-world');
    if (!world) return;
    var section = document.createElement('section');
    section.className = 'update-settings diag-block';
    section.innerHTML = '<h2>Diagnostic du serveur</h2>'
      + '<p class="hint">En cas de « service multijoueur indisponible », ce test dit ce qui '
      + 'manque côté hébergement. Il ne demande ni compte ni mot de passe.</p>'
      + '<button class="ghost-btn" id="diag-run">Tester le serveur</button>'
      + '<div id="diag-output" role="status"></div>';
    world.append(section);
    $('diag-run').onclick = run;
  });
})();
