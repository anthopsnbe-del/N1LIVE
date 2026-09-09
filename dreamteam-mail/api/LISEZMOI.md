# API DreamTeam — adresses jetables

Petit service PHP à installer sur ton hébergement LWS. Il détient **seul** les
identifiants de la boîte catch-all ; l'application distribuée n'a plus jamais
besoin d'un mot de passe de messagerie.

## Ce que ça change

| | Sans API (IMAP direct) | Avec API |
| --- | --- | --- |
| Identifiants sur le poste client | oui, en clair pour l'utilisateur | **aucun** |
| Un utilisateur peut lire le courrier des autres | oui | non : son jeton n'ouvre que son alias |
| Envoi de mail au nom du domaine possible | oui (SMTP) | non |
| Limitation d'usage | aucune | 20 créations/heure par IP |

## Installation (10 minutes)

1. **Copier les fichiers.** Via le Gestionnaire de fichiers LWS ou en FTP,
   dépose le dossier `api/` dans ton espace web, par exemple
   `www/mail-api/`. L'URL sera `https://asylum-games.fr/mail-api/index.php`.
2. **Configurer.** Duplique `config.exemple.php` en `config.php` et remplis-le :
   domaine, identifiants IMAP de `catchall@asylum-games.fr`, et une
   `cle_purge` aléatoire (par exemple 32 caractères tirés au hasard).
3. **Base de données.** Par défaut, SQLite dans `api/donnees/alias.sqlite`
   (le dossier est refusé au web par son `.htaccess`). Si SQLite n'est pas
   disponible, crée une base MySQL dans le panel LWS et remplace le DSN par
   `mysql:host=localhost;dbname=TA_BASE;charset=utf8mb4` avec l'utilisateur et
   le mot de passe. Les tables se créent toutes seules au premier appel.
4. **Vérifier.** Ouvre `https://asylum-games.fr/mail-api/index.php?action=etat` :
   tu dois voir `{"ok":true,...}`. Si tu vois une erreur PHP, c'est que
   `config.php` manque ou est mal rempli.
5. **Cron de purge.** Panel LWS → *Tâches cron*, toutes les 15 minutes :
   `/usr/local/bin/php /home/TON_COMPTE/www/mail-api/purger.php`
   C'est ce qui vide la boîte catch-all même quand personne n'ouvre l'app.
6. **Brancher l'application.** Dans DreamTeam Mail : bouton « Serveur… », champ
   **API**, colle `https://asylum-games.fr/mail-api/index.php`, puis
   **Tester** et **Enregistrer**. Laisse les champs IMAP vides.

## Points de sécurité

- `config.php` et `donnees/` sont bloqués par `.htaccess`. **Vérifie-le** en
  ouvrant `.../mail-api/config.php` dans un navigateur : tu dois obtenir une
  erreur 403, jamais du texte. Si ton hébergement ignore `.htaccess`, place
  `config.php` et la base hors du dossier web et ajuste les chemins.
- Sers l'API **en HTTPS uniquement** : le jeton circule dans la requête.
- Le jeton n'est pas stocké en clair côté serveur, seulement son empreinte
  SHA-256 ; il ne donne accès qu'au courrier de son propre alias.
- Les adresses IP ne sont pas conservées, seulement une empreinte, utilisée
  pour la limite de débit.
- Le serveur plafonne la durée de vie demandée (5 min à 24 h) : un client
  modifié ne peut pas obtenir davantage.
- La suppression est définitive (IMAP `EXPUNGE`), côté serveur comme côté
  client : c'est le but, mais rien n'est récupérable ensuite.

## Points d'entrée

| Action | Paramètres | Réponse |
| --- | --- | --- |
| `etat` | — | `{ok, domaine, duree}` |
| `creer` | `duree` (secondes ; `0` = à vie) | `{alias, email, jeton, duree, expire_a}` |
| `relever` | `alias`, `jeton` | `{messages: [{expediteur, sujet, date, corps}]}` |
| `supprimer` | `alias`, `jeton` | `{supprimes: n}` |
| `envoyer` | `alias`, `jeton`, `destinataire`, `sujet`, `corps` | `{envoye: true}` |
| `purger` | `cle` | `{purges, messages_effaces}` |

Codes d'erreur : 400 requête invalide, 403 jeton refusé, 410 alias expiré,
429 trop de créations, 503 service saturé, 500 erreur interne (le détail reste
dans les logs du serveur).

## Adresses à vie

`duree=0` crée un alias qui n'expire jamais (`expire_a = 0`), ignoré par la
purge. Désactive-le avec `'a_vie_autorisee' => false` dans `config.php` si tu
distribues l'application : sinon chacun peut se réserver un alias définitif.

## Ce qui n'est pas fait

- L'envoi passe par `mail()` de PHP : les en-têtes sont construits côté
  serveur et les retours à la ligne retirés des champs, mais la délivrabilité
  dépend entièrement de la configuration SPF/DKIM du domaine.
- Pas de pièces jointes : seul le texte des messages est retourné.
- Testé contre un serveur PHP local et un faux IMAP, **pas encore contre le
  serveur LWS réel** : le premier `?action=etat` puis un vrai relevé sont donc
  à faire avant de distribuer l'application.
