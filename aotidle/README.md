# AOT IDLE — v6.1

Idle RPG de fan (non officiel) : une application Android qui est une coquille
WebView autour d'un jeu web sans dépendance. Le dépôt contient tout ce qu'il
faut pour reconstruire l'APK.

```
assets/        le jeu (HTML, CSS, JS, images WebP)
shell/         le conteneur Android d'origine : manifeste, dex, ressources
server/        le service PHP à publier sur l'hébergement (sans les secrets)
tools/         images, assemblage et signature de l'APK, paquet FTP
build/         sortie de compilation (non versionnée)
```

## Construire l'APK

```bash
python3 tools/build_apk.py build/AOTIDLE-v6.1.apk
```

Le script assemble `shell/` + `assets/`, garde `resources.arsc` non compressé et
aligné sur 4 octets (exigence Android 11+), puis signe en **schéma v2** avec
`apksig` (téléchargé au premier lancement dans `build/`). `minSdkVersion` vaut
24, donc la signature v1 n'est pas nécessaire.

Par défaut une clé de développement est créée dans `build/dev.p12` : l'APK
s'installe, mais **il faut désinstaller la version précédente** (signature
différente). Pour publier une vraie mise à jour, signez avec la clé d'origine :

```bash
KEYSTORE=/chemin/aotidle.p12 KS_PASS=… KEY_ALIAS=… python3 tools/build_apk.py
```

## Préparer la mise en ligne

```bash
KEYSTORE=… KS_PASS=… KEY_ALIAS=… python3 tools/package_ftp.py
```

Construit `build/AOT-IDLE-v6-FTP.zip` : les fichiers PHP de `server/`, l'APK
signé et un `release.json` calculé sur cet APK (taille et SHA-256, vérifiés par
`release-lib.php`). `server/INSTALLATION-FTP.md` décrit le transfert et les
vérifications à faire ensuite.

`server/` ne contient volontairement ni `config.php`, ni `db.php`, ni
`google.php` : les identifiants restent sur l'hébergement.

## Le boss mondial

`server/boss-core.php` ajoute à `social.php` trois actions (`boss_state`,
`boss_strike`, `boss_claim`) et deux tables créées au premier appel
(`social_boss`, `social_boss_damage`). Un titan par monde et par jour UTC, une
barre de vie commune, un assaut par minute et par joueur. **Les dégâts sont
calculés côté serveur** à partir de la puissance déjà enregistrée par `sync` :
le client ne transmet aucun nombre de dégâts. Les points de vie sont
dimensionnés sur la population active du monde, et la récompense en cristaux
n'est versée qu'une fois par titan.

Côté jeu, `assets/boss.js` tient l'écran (barre de vie, assaut, classement des
assaillants et des clans) et la carte d'accueil ; sans compte connecté, il le
dit au lieu de simuler des joueurs.

## Optimiser les images

`tools/optimize_assets.py` est une passe unique, déjà appliquée. Elle détoure
les titans (le fond noir devient une vraie couche alpha), ramène chaque image à
sa taille d'affichage et convertit en WebP :

| | avant | après |
|---|---|---|
| Images | 27 Mo (PNG 1024×1536) | 1,2 Mo (WebP) |
| APK | 30 Mo | 1,3 Mo |

## Essayer le jeu sans Android

```bash
cd assets && python3 -m http.server 8123   # puis http://localhost:8123/
```

Le jeu tourne entièrement hors ligne ; « Jouer hors ligne » saute la connexion
au serveur (classement, clans, arène).

## Organisation du code

| Fichier | Rôle |
|---|---|
| `game.js` | boucle de jeu, formules, économie, sauvegarde, panneaux |
| `fx.js` | moteur d'effets : rendu des combattants, impacts, particules, sons |
| `screens.js` | carte de campagne, archives des portraits, feuille de réglages |
| `hub.js` | accueil, ordres du jour, Tour de combat |
| `boss.js` | boss mondial coopératif (assauts, classements, récompense) |
| `social.js` / `net.js` | comptes, mondes, chat, amis, arène classée, clans |
| `content.js` / `campaign.js` | 1 000 chapitres, boutique, arcs narratifs |
| `art.js` | découpe des planches d'icônes (bornes en demi-définition) |

## Nouveautés de la v6

**Progression.** Chaque combat d'un chapitre est plus coriace que le précédent
(jusqu'à ×2,1 de PV avant le boss, affiché dans l'en-tête), un combat sur cinq
est une **élite** (×2,2 PV, ×3 or), et le titre d'un chapitre est enfin celui de
sa mission plutôt que celui de l'arc répété cent fois. La défaite ne fait plus
perdre un chapitre en cachette.

**Systèmes.** Sac d'équipement avec fusion (deux pièces identiques → rareté
supérieure) et bonus de panoplie ; passif propre à chaque recrue ; arbre des
âmes en trois branches, payé avec les âmes sans réduire le bonus de
renaissance ; automatisation débloquée au fil des renaissances ; rapport hors
ligne doublable ; trois missions quotidiennes avec série de connexion ;
modificateur d'étage et point faible à toucher dans la Tour ; combo au doigt sur
l'arène ; achats ×1 / ×10 / max avec l'écart de statistique annoncé.

**Présentation.** Écran de chargement branché sur le vrai chargement des
images ; décor en parallaxe avec braises ; recul, arrêt sur image, arcs de lame,
dissolution en vapeur, secousses, bannières de boss, d'élite et de promotion ;
pastilles de notification sur la barre d'onglets ; réglages regroupés dans une
feuille dédiée. Tout est désactivable via « Animations réduites ».

**Performances.** Rendu du texte à la demande plutôt qu'à chaque image, images
dix fois plus légères, `sprites.js` (code mort) supprimé.

## Réserves

- Le jeu est un projet de fan, sans lien avec les ayants droit.
- Le service en ligne tourne sur `asylum-games.fr` : ce dépôt contient son code
  (`server/`) mais rien ne part en production sans un transfert FTP manuel.
- L'APK produit par défaut est signé avec une clé de développement. Pour
  publier une mise à jour installable par-dessus la v5, il faut signer avec la
  clé d'origine.
- Les images d'origine en pleine définition ne sont pas versionnées ; elles
  restent disponibles dans l'APK v5.0 si une nouvelle passe d'optimisation est
  nécessaire.
