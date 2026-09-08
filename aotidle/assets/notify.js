/* AOT IDLE — rappels système, quand la coquille Android sait les programmer.

   Le pont `AotBridge` n'existe que dans un APK reconstruit à partir de
   `android/` (voir son README). Sans lui, ce fichier ne fait rien : aucun
   bouton fantôme, aucune promesse non tenue. */
(function () {
  'use strict';

  function bridge() { return window.AotBridge && window.AotBridge.available && window.AotBridge; }

  function schedule(message, delayMs) {
    var api = bridge();
    if (!api || !window.Fx || window.Fx.prefs.notify === false) return false;
    try { api.schedule(message, Math.round(delayMs)); return true; } catch (e) { return false; }
  }

  window.Notify = {
    available: function () { return !!bridge(); },
    /* Le boss du jour disparaît à minuit UTC : on prévient deux heures avant. */
    boss: function (endsAt) {
      var left = endsAt - Date.now() - 2 * 3600000;
      if (left > 60000) schedule('Le titan du jour disparaît dans 2 heures.', left);
    },
    war: function (endsAt) {
      var left = endsAt - Date.now() - 3600000;
      if (left > 60000) schedule('Votre guerre de clans se termine dans une heure.', left);
    },
    daily: function () {
      var now = new Date();
      var next = Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate() + 1, 0, 5);
      schedule('Nouvelles missions du bataillon disponibles.', next - Date.now());
    }
  };
})();
