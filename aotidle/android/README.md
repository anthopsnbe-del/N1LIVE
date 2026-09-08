# Coquille Android — sources

L'APK actuel réutilise le `classes.dex` d'origine : le jeu vit entièrement dans
la WebView, et `tools/build_apk.py` ne fait que remplacer les fichiers web.
**Cette session ne peut pas recompiler le dex** (pas de SDK Android, pas de
`d8`), donc tout ce qui demande du Java — les notifications système en tête —
est fourni ici en source, à compiler une fois avec Android Studio.

## Ce que ces sources ajoutent

- `MainActivity.java` : la coquille WebView existante, plus un pont
  `AotBridge` que le jeu appelle en JavaScript pour programmer un rappel :

  ```js
  if (window.AotBridge) AotBridge.schedule('Le titan du jour disparaît dans 2 h', 7200000);
  ```

- `AlarmReceiver.java` : reçoit l'alarme et publie la notification, même
  application fermée.
- Les entrées de manifeste correspondantes (permission `POST_NOTIFICATIONS`
  sur Android 13+, `SCHEDULE_EXACT_ALARM`, le receiver).

## Construire

1. Ouvrir ce dossier dans Android Studio (ou `./gradlew assembleRelease` avec
   le SDK installé).
2. Copier le contenu de `../assets/` dans `app/src/main/assets/`.
3. Signer avec **votre** clé (la même que les versions publiées), puis publier
   l'APK et `release.json` comme d'habitude.

Tant que ce build n'est pas fait, le jeu fonctionne exactement comme
aujourd'hui : `window.AotBridge` est simplement absent, et le code JavaScript
qui l'appelle ne fait rien.
