# Publier la version 6.2 avec FileZilla

Ce dossier est prêt à transférer. **Il n'a pas été publié.**

## À lire avant tout : la clé de signature

L'APK fourni ici est signé avec une **clé de développement**, parce que la clé
d'origine n'est pas dans ce dépôt et ne doit pas y entrer. Publié tel quel, il
s'installerait comme une application différente : vos joueurs verraient une
erreur de mise à jour et devraient désinstaller la v5. Avant publication,
reconstruisez l'APK avec votre clé :

```bash
KEYSTORE=/chemin/vers/votre.p12 KS_PASS=… KEY_ALIAS=… python3 tools/package_ftp.py
```

Le paquet est alors régénéré avec le bon APK **et** le `release.json`
correspondant (taille et SHA-256 recalculés). Ne transférez jamais la clé
elle-même sur l'hébergement.

## Transfert

1. Se connecter au bon hébergement dans FileZilla. Ne pas envoyer le mot de
   passe dans une conversation.
2. Sauvegarder le dossier distant `aotidle` **et exporter la base de données**
   avant la mise à jour.
3. Dans la racine publique de `asylum-games.fr`, ouvrir le dossier distant
   `aotidle` existant. Conserver ses fichiers `config.php`, `db.php`,
   `google.php` et sa configuration serveur : ils ne sont pas dans ce paquet.
4. Envoyer d'abord `aotidle/releases/aot-idle-6.2.apk`, puis `social-core.php`,
   **`boss-core.php`** et **`war-core.php`** (nouveaux), `social.php`, `index.php`, `release-lib.php`,
   `release.php`, `telecharger.php` et les deux fichiers `download-widget.*`.
5. Envoyer **release.json en dernier**, une fois l'APK entièrement transféré.
   C'est ce fichier qui annonce la nouvelle version aux joueurs.
6. Le fragment `BOUTON-A-COLLER.html` n'a pas changé depuis la v5 : rien à
   refaire si le bouton est déjà en place.
7. Vérifier `https://asylum-games.fr/aotidle/release.php` : versionName doit
   valoir 6.2 et versionCode 10. Installer ensuite sur un téléphone de test.

## Nouveau service : le boss mondial

`boss-core.php` ajoute trois actions à `social.php` (`boss_state`,
`boss_strike`, `boss_claim`) et **deux tables créées automatiquement** au
premier appel : `social_boss` et `social_boss_damage`. Aucune table existante
n'est modifiée ni supprimée ; le compte SQL doit simplement pouvoir créer des
tables, comme pour les tables `social_*` de la v5.

Fonctionnement : un titan par monde et par jour UTC, la même barre de vie pour
tous les joueurs du monde. Les dégâts sont calculés **côté serveur** à partir
de la puissance déjà enregistrée par `sync` — le client ne transmet aucun
nombre de dégâts. Un assaut par minute et par joueur, 120 assauts au maximum
par titan. Les points de vie du titan sont dimensionnés sur la population
active du monde (7 derniers jours), avec un minimum pour qu'un monde peu peuplé
puisse l'abattre.

La récompense en cristaux est versée une seule fois par titan, à sa chute ou à
la fin de la journée. Le serveur ne tient pas la bourse du joueur (elle vit
dans sa sauvegarde locale) : il autorise le versement, le client l'applique.

## Nouveau service : les guerres de clans

`war-core.php` ajoute quatre actions (`war_state`, `war_enroll`, `war_strike`,
`war_claim`) et **trois tables créées automatiquement** : `social_wars`,
`social_war_queue` et `social_war_damage`. Il dépend de `boss-core.php`, qui
fournit le calcul de la puissance de frappe : envoyez les deux fichiers.

Fonctionnement : le **chef d'un clan** engage son clan ; le serveur l'apparie
avec le clan en attente d'effectif le plus proche, dans le même monde. Les deux
clans frappent alors le **même front** pendant 24 heures, chacun marquant son
score. Le camp qui a le plus contribué l'emporte, à la chute du front ou à
l'échéance. Mêmes garde-fous que le boss mondial : dégâts calculés côté
serveur, un assaut par minute, 120 assauts par joueur, récompense versée une
seule fois — plus généreuse pour le camp vainqueur, sans laisser le camp battu
les mains vides.

## Vérifications après mise en ligne

1. Ouvrir le jeu, se connecter, aller sur **Accueil → Boss mondial** : le titan
   du jour, ses PV et le compte à rebours doivent s'afficher.
2. Lancer un assaut : les PV baissent, le bouton passe en récupération d'une
   minute, votre rang apparaît.
3. Avec un second compte du même monde, vérifier que la barre de vie est bien
   partagée et que le classement des assaillants liste les deux joueurs.
4. Avec deux comptes d'un même clan, vérifier la ligne du clan dans « Clans
   engagés ».
5. **Accueil → Guerre de clans** : avec deux clans d'au moins un membre dans le
   même monde, faire engager les deux chefs. L'appariement doit être immédiat
   pour le second, le front commun s'affiche et chaque assaut alimente le score
   du bon camp.
6. Tester aussi les fonctions v5 qui n'ont pas bougé : recherche classée dans
   l'arène, demande d'ami, défi, chat global et de clan.

## Rappels

Le serveur réutilise les comptes et clans de l'installation existante. PHP 8.1+
avec PDO MySQL et tables InnoDB recommandé. `index.php` est l'API existante
avec la limite des chapitres portée à 1000.

La notification de mise à jour dans le jeu est vérifiée à l'ouverture, au
retour au premier plan et périodiquement pendant l'utilisation. Ce n'est pas
une notification système : elle ne s'affiche pas application fermée. Le bouton
du site peut signaler une version plus récente que le dernier téléchargement
effectué dans ce navigateur ; il ne peut pas inspecter les applications
installées sur le téléphone.

Pour une prochaine publication : reconstruire l'APK avec un versionCode
supérieur et la même clé de signature, envoyer l'APK, puis le nouveau
release.json.

Ne jamais transférer la clé de signature, un fichier de test local ou une copie
de la base de données dans la racine publique.
