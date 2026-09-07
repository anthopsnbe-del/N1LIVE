# Plantation — la serre 3D du Botanical Club

Mini-jeu de culture et de croisements : 14 variétés fondatrices, 91 hybrides,
serre en 3D temps réel (three.js r128), état de jeu entièrement serveur (PHP + MySQL).

## Arborescence attendue en production

```
plantation_view.php        vue HTML injectée dans la page du panneau
plantation_action.php      API POST (une action = une transition d'état)
plantation_lib.php         règles du jeu, transitions pures, schéma SQL
plantation_ranking.php     score de collection et classement
Plantation.php             point d'entrée du panneau
assets/plantation/         three.min.js, models.js, plantation.js, sfx.js,
                           demo.js, plantation.css, catalog.json, thumbs/
```

`plantation_lib.php` lit `assets/plantation/catalog.json` et le front lit
`GP_CONFIG.assetBase` (qui doit pointer sur `assets/plantation/`).

Le gabarit de page continue de charger `three.min.js`, `models.js`, `demo.js` et
`plantation.js` comme avant : **aucune balise `<script>` à ajouter**. Le moteur
sonore `sfx.js` est chargé à la demande par `plantation.js`.

## Aperçu local, sans base de données

```bash
php -S localhost:8000        # depuis la racine du dépôt
# puis ouvrir http://localhost:8000/plantation_preview.html
```

Cet aperçu utilise la sauvegarde locale de démonstration (`localStorage`),
totalement séparée des comptes joueurs.

## Tests

```bash
php tests/plantation_test.php
```

La suite couvre le cycle de culture, le laboratoire, la boutique, le classement
et surtout la **non-régression des sauvegardes existantes** : points, graines,
récoltes, buds, découvertes, pots débloqués et révision sont conservés tels quels.

## Une morphologie par variété

`models.js` ne stocke aucune donnée supplémentaire : la forme et les pigments de
chaque bud sont déduits du catalogue existant.

| Donnée du catalogue | Effet sur le bud |
|---|---|
| `height / width` | épi effilé (Onyx, 2.6) ou tête large et trapue (Ambre, 0.85) |
| `seed` | densité : cola compacte ou structure aérée qui laisse voir la tige |
| teinte de `bud` | pigments : anthocyanes violettes (200-352°), rouille (12-66°), sinon vert |
| `parents` | les hybrides sont plus givrés que les fondatrices |

Le manteau de feuilles sucrées — petites feuilles dentelées, pliées en gouttière,
qui percent la silhouette — est ce qui distingue une vraie tête d'un bloc lisse.

## Palette : 105 variétés, 105 couleurs

`bud`, `leaf` et `pistil` de `catalog.json` sont générés par
`tools/palette.py` ; toutes les autres données (prix, durées, parents, rareté)
sont laissées intactes.

- Les 14 fondatrices sont choisies à la main, fidèles à leur nom.
- Les 91 hybrides héritent de leurs parents : moyenne circulaire des teintes, et
  quand les deux parents sont quasi opposés sur la roue, l'un domine et l'autre
  l'infléchit — une moyenne donnerait une couleur étrangère aux deux.
- Une passe d'écartement en espace Lab garantit qu'aucune paire de variétés
  n'est confondable : **ΔE minimum 7**, contre 28 paires sous 5 auparavant.

```bash
python3 tools/palette.py            # aperçu, n'écrit rien
python3 tools/palette.py --write    # met à jour catalog.json
```

## Régénérer les miniatures

Les cartes de la grainothèque et de la collection utilisent
`assets/plantation/thumbs/<id>-bud.webp`. Après un changement de couleurs ou de
modèle, on les régénère depuis le vrai modèle 3D :

```bash
php -S localhost:8146 &                      # sert la racine du dépôt
GP_BASE=http://localhost:8146 node tools/render-thumbs.js
```

Sans argument, l'outil rend les 105 variétés ; on peut aussi lui passer des
identifiants (`node tools/render-thumbs.js emeraude velours`).

La densité pilote aussi le nombre de calices, de pistils, de trichomes et de
feuilles sucrées ; une variété aérée porte plus de feuilles et moins de calices.

## Sons

`assets/plantation/sfx.js` synthétise tout en Web Audio : aucun fichier audio à
héberger, aucune requête réseau. Le son démarre au premier geste de
l'utilisateur, le bouton « Son » le coupe et la préférence est mémorisée dans le
navigateur (`gp-sound-v1`, `gp-volume-v1`). L'ambiance suit l'état de la serre
(ventilateur, brumisateur, lampe).

## Raccourcis clavier

| Touche | Action |
|---|---|
| `1` à `6` | choisir un pot |
| `T` | terreau |
| `S` | semer |
| `A` | arroser |
| `F` | soin bonus |
| `R` | récolter |
| `I` | vue rapprochée du bud |
| `M` | couper / rétablir le son |

## Note sur les assets

Les 315 fichiers `.glb` (69 Mo) et `bud-surface.png` (3,8 Mo, doublon du `.webp`)
de l'archive d'origine ne sont pas versionnés : aucun code du jeu ne les
référence, les plantes et les buds sont générés proceduralement par `models.js`.
Ils peuvent être réajoutés dans `assets/plantation/models/` si un usage apparaît.
