<?php
/**
 * GreenStand — Idle 3D : le mini-jeu du stand, intégré au site (rubrique Jeux).
 *
 * Le jeu lui-même tourne entièrement dans le navigateur, comme avant (three.js,
 * sauvegarde locale) — ce fichier ajoute la connexion à la DreamTeam : l'argent
 * gagné en vendant est régulièrement converti en jetons DT sur le compte du joueur
 * (voir greenstand_action.php et greenstand_lib.php), et le classement des
 * meilleurs vendeurs est affiché en bas de cette page.
 */
require __DIR__ . '/common.php';
require_once __DIR__ . '/greenstand_lib.php';

/**
 * Chaque section du jeu a maintenant sa propre page (sa propre URL) :
 *
 *   greenstand-idle-3d.php   Le stand          Distributeur.php  Le Distributeur
 *   Franchise.php            La Franchise      Classement.php    Les classements
 *   boutique.php             La Boutique       telephone.php     Le Téléphone
 *   succes.php               Les Succès
 *   greenstand-idle-3d.php?panel=admin         Le panel admin
 *
 * Ces fichiers ne dupliquent rien : ils posent $gs_panel puis incluent celui-ci.
 * Une seule copie du jeu, du moteur de sauvegarde et de la synchro à maintenir.
 * Seul le panneau demandé est envoyé au navigateur, et la scène 3D n'est
 * construite que sur la page du stand.
 */
$gs_panels_pages = [
    'jeu'          => 'greenstand-idle-3d.php',
    'distributeur' => 'Distributeur.php',
    'boutique'     => 'boutique.php',
    'telephone'    => 'telephone.php',
    'plantation'   => 'Plantation.php',
    'succes'       => 'succes.php',
    'prestige'     => 'Franchise.php',
    'classement'   => 'classement.php',
    'admin'        => 'greenstand-idle-3d.php?panel=admin',
];
if (!isset($gs_panel)) $gs_panel = (string) ($_GET['panel'] ?? 'jeu');
if (!isset($gs_panels_pages[$gs_panel])) $gs_panel = 'jeu';

$me            = current_user();
$gs_board      = greenstand_leaderboard_weekly(GREENSTAND_BOARD_LIMIT, $me['username'] ?? null);
$gs_board_wins = greenstand_leaderboard_wins(GREENSTAND_BOARD_LIMIT, $me['username'] ?? null);
$gs_dt_balance = (int) ($me['jetons'] ?? 0);
// Les sections réservées : sans droits, on affiche le stand plutôt qu'une page vide.
if ($gs_panel === 'admin' && !is_admin_ip()) $gs_panel = 'jeu';
if (in_array($gs_panel, ['distributeur', 'prestige', 'boutique', 'telephone', 'plantation'], true) && !$me) $gs_panel = 'jeu';
$page_title    = 'GreenStand — Idle 3D (BETA) — ' . SITE_NAME;

/**
 * Les visuels du stand, servis en fichiers plutôt qu'inlinés en base64.
 *
 * Avant, les treize images vivaient dans le HTML sous forme de data:URI : 832 Ko que
 * le navigateur retéléchargeait à CHAQUE visite, sans jamais pouvoir les mettre en
 * cache (le HTML est dynamique). En fichiers, il ne les charge qu'une fois.
 *
 * Le ?v= porte la date du fichier : remplacer une image suffit à casser le cache,
 * sans toucher au code.
 */
function gs_asset_urls(): array {
    static $urls = null;
    if ($urls !== null) return $urls;
    $urls = [];
    foreach (glob(__DIR__ . '/assets/greenstand/*.webp') ?: [] as $file) {
        $name = basename($file, '.webp');
        $urls[$name] = 'assets/greenstand/' . basename($file) . '?v=' . (int) @filemtime($file);
    }
    return $urls;
}

/**
 * Largeur/hauteur réelles de chaque visuel, lues sur le fichier (getimagesize)
 * plutôt que devinées côté JS.
 *
 * Sans ça, la taille d'un objet à son niveau T2/T3/T4 dépendait d'une course :
 * le plane 3D est construit dès refreshStandModels(), AVANT que la texture ait
 * fini de charger dans le navigateur — à ce moment-là, ni ASSET_META (qui ne
 * connaît que les 12 visuels d'origine) ni la texture elle-même n'ont les
 * bonnes proportions. Le code retombait alors sur les proportions du visuel
 * T1 (ex. bag: 420x359, un format large) appliquées à une image de palier
 * bien plus verticale (ex. bag_t2/t3/t4: 1024x1536) : le sachet ressortait
 * déformé et pas à la même taille que le T1, et ça ne se corrigeait jamais
 * tant que le palier ne changeait pas une seconde fois.
 *
 * En lisant les dimensions ici, côté serveur, elles sont connues dès le
 * premier rendu — plus de course, et ça vaut pour toute nouvelle image
 * envoyée depuis le panel admin, palier ou pas, sans rien coder en dur.
 */
/**
 * LE MODÈLE 3D DU DISTRIBUTEUR.
 *
 * Le jeu cherche un fichier dans assets/greenstand/atm/ ; s'il n'y en a pas, la
 * page garde la carte « En banque » d'avant. C'est ce qui permet de déposer (ou
 * de retirer) le modèle sans jamais casser la page.
 *
 * Deux formats acceptés :
 *   scene.glb   un seul fichier, tout dedans — le plus simple à déposer ;
 *   scene.gltf  le fichier JSON, avec scene.bin et le dossier textures/ à côté.
 *
 * L'auteur et la licence viennent du fichier lui-même (asset.extras chez
 * Sketchfab) : une licence CC-BY oblige à citer l'auteur, et cette citation ne
 * doit pas dépendre de quelqu'un qui pense à la recopier à la main.
 */
function gs_atm_modele(): ?array {
    $dossier = __DIR__ . '/assets/greenstand/atm';
    foreach (['scene.glb', 'scene.gltf', 'atm.glb', 'atm.gltf'] as $nom) {
        $chemin = $dossier . '/' . $nom;
        if (!is_file($chemin)) continue;

        $credit = null;
        if (str_ends_with($nom, '.gltf')) {
            $json = json_decode((string) @file_get_contents($chemin), true);
            $extras = $json['asset']['extras'] ?? [];
            if (!empty($extras['author'])) {
                $credit = trim((string) $extras['author']);
                if (!empty($extras['license'])) $credit .= ' — ' . preg_replace('/\s*\(http[^)]*\)/', '', (string) $extras['license']);
            }
        }
        return [
            'url'    => 'assets/greenstand/atm/' . $nom . '?v=' . (int) @filemtime($chemin),
            'base'   => 'assets/greenstand/atm/',
            'credit' => $credit,
        ];
    }
    return null;
}

function gs_asset_dims(): array {
    static $dims = null;
    if ($dims !== null) return $dims;
    $dims = [];
    foreach (glob(__DIR__ . '/assets/greenstand/*.webp') ?: [] as $file) {
        $name = basename($file, '.webp');
        $taille = @getimagesize($file);
        if ($taille) $dims[$name] = [(int) $taille[0], (int) $taille[1]];
    }
    return $dims;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($page_title) ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;600;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap');

  :root{
    --void:#0A0F0C;
    --panel:#101A13;
    --surface:#182417;
    --surface-hi:#213321;
    --line:#2A3B28;
    --leaf:#4CAF3D;
    --leaf-dark:#2E5A26;
    --gold:#D9A441;
    --violet:#8B5FBF;
    --text:#EDEAE0;
    --text-dim:#8FA089;
    --lcd-bg:#0C1F0E;
    --lcd-fg:#8BFF6B;
    --foil: linear-gradient(120deg,#D9A441 0%, #8B5FBF 35%, #4CAF3D 60%, #D9A441 100%);
  }

  *{box-sizing:border-box;}
  html,body{margin:0;padding:0;}
  body{
    background:
      radial-gradient(ellipse at 20% -10%, rgba(139,95,191,0.12), transparent 45%),
      radial-gradient(ellipse at 80% 0%, rgba(76,175,61,0.10), transparent 40%),
      var(--void);
    color:var(--text);
    font-family:'Inter',sans-serif;
    min-height:100vh;
    padding:20px 16px 60px;
  }

  .wrap{max-width:960px;margin:0 auto;}

  header{
    display:flex;flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between;
    margin-bottom:22px;
  }
  .brand{display:flex;align-items:center;gap:10px;}
  .brand-leaf{width:34px;height:34px;flex:none;}
  .brand h1{
    font-family:'Bricolage Grotesque',sans-serif;font-weight:800;font-size:22px;letter-spacing:-0.02em;margin:0;
    background:var(--foil);-webkit-background-clip:text;background-clip:text;color:transparent;
    background-size:200% auto;animation:foilshift 6s linear infinite;
  }
  @keyframes foilshift{to{background-position:200% center;}}
  .brand small{display:block;color:var(--text-dim);font-size:11px;letter-spacing:.08em;text-transform:uppercase;}

  .lcd{
    background:var(--lcd-bg);border:2px solid #0a160b;border-radius:10px;padding:10px 18px;
    box-shadow:inset 0 0 14px rgba(0,0,0,.6), 0 2px 0 rgba(255,255,255,0.03);
    text-align:right;min-width:220px;
  }
  .lcd .amount{
    font-family:'JetBrains Mono',monospace;font-weight:700;font-size:28px;color:var(--lcd-fg);
    text-shadow:0 0 8px rgba(139,255,107,.65), 0 0 2px rgba(139,255,107,.9);letter-spacing:1px;line-height:1.1;
  }
  .lcd .rate{font-family:'JetBrains Mono',monospace;font-size:12px;color:rgba(139,255,107,.55);margin-top:2px;}

  .stand-card{
    position:relative;background:var(--panel);border:1px solid var(--line);border-radius:18px;
    padding:18px 18px 24px;margin-bottom:26px;overflow:hidden;
  }
  .stand-title{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px;}
  .stand-title h2{
    font-family:'Bricolage Grotesque',sans-serif;font-size:15px;text-transform:uppercase;letter-spacing:.1em;
    color:var(--text-dim);margin:0;
  }
  .stand-title .lvl{
    font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--gold);
    background:rgba(217,164,65,0.1);border:1px solid rgba(217,164,65,0.35);padding:3px 10px;border-radius:100px;
  }

  /* ---- La scène et la console, côte à côte ---- */
  .stand-duo{display:grid;grid-template-columns:minmax(0,1fr) 264px;gap:12px;align-items:stretch;}
  @media (max-width:1000px){ .stand-duo{grid-template-columns:1fr;} }

  .gs-console{
    display:flex;flex-direction:column;min-height:0;height:400px;
    background:#0A120C;border:1px solid #23331F;border-radius:14px;overflow:hidden;
    font-family:'JetBrains Mono',monospace;
  }
  @media (max-width:1000px){ .gs-console{height:270px;} }
  .gs-console .c-tete{
    display:flex;align-items:center;gap:8px;padding:9px 12px;
    background:linear-gradient(180deg,#16211A,#101A13);border-bottom:1px solid #23331F;
  }
  .gs-console .c-led{
    width:8px;height:8px;border-radius:50%;background:#4CAF3D;flex:0 0 auto;
    box-shadow:0 0 8px #4CAF3D;animation:c-pulse 2.4s ease-in-out infinite;
  }
  @keyframes c-pulse{0%,100%{opacity:1;}50%{opacity:.35;}}
  .gs-console .c-titre{font-size:10.5px;letter-spacing:.18em;color:#8FA089;font-weight:700;}
  .gs-console .c-attente{margin-left:auto;font-size:10px;color:#6E7D69;}
  .gs-console .c-plier{
    all:unset;cursor:pointer;flex:0 0 auto;margin-left:8px;color:#6E7D69;
    font-size:11px;padding:2px 5px;border-radius:5px;transition:transform .2s;
  }
  .gs-console .c-plier:hover{color:#CFEFC4;background:rgba(255,255,255,.06);}

  /* Prochain client : un simple compte à rebours, affiché seulement quand
     personne n'attend déjà. Sans lui, une accalmie ressemblait à une panne :
     on ne savait jamais dans combien de temps ça reprenait. */
  .gs-console .c-prochain{
    font-size:11px;color:#8FA089;padding:7px 12px;border-bottom:1px solid #1B2818;
    display:flex;align-items:center;gap:6px;
  }
  .gs-console .c-prochain b{color:#CFEFC4;font-family:'JetBrains Mono',monospace;}

  /* Distribution automatique : sert les clients prêts tout seul. */
  .gs-console .c-auto{
    display:flex;align-items:center;gap:7px;font-size:11px;color:#A8B5A2;
    padding:8px 12px;border-bottom:1px solid #1B2818;cursor:pointer;user-select:none;
  }
  .gs-console .c-auto input{accent-color:#4CAF3D;width:14px;height:14px;cursor:pointer;}

  /* Bandeau « stand fermé » : c'est le seul des trois effets qui empêche
     complètement de vendre à la main, il doit se voir avant même d'être lu.
     Les !important cassent volontairement le style inline posé en HTML —
     c'est un état d'urgence, pas une variante de couleur. */
  #gsEffetsBandeau.bandeau-urgent{
    background:rgba(200,80,60,.16) !important;
    border:1px solid #C8503C !important;
    color:#FFD3C4 !important;
    padding:14px 16px !important;
    animation:bandeau-urgent-pulse 1.6s ease-in-out infinite;
  }
  @keyframes bandeau-urgent-pulse{
    0%,100%{box-shadow:0 0 0 rgba(200,80,60,0);}
    50%{box-shadow:0 0 18px rgba(200,80,60,.45);}
  }
  .bandeau-urgent-ligne{
    font-size:15px;font-weight:700;display:flex;align-items:center;gap:7px;
  }
  .bandeau-urgent-ligne b{font-size:17px;font-family:'JetBrains Mono',monospace;}
  .bandeau-urgent-reste{margin-top:6px;font-size:11px;opacity:.8;font-weight:400;}

  /* Console repliée : elle se réduit à sa barre de titre et rend toute la
     largeur au stand. Le choix est retenu d'une visite à l'autre. */
  .stand-duo.est-plie{grid-template-columns:minmax(0,1fr) 44px;}
  .stand-duo.est-plie .c-flux,
  .stand-duo.est-plie .c-titre,
  .stand-duo.est-plie .c-prochain,
  .stand-duo.est-plie .c-auto,
  .stand-duo.est-plie .c-attente{display:none;}
  .stand-duo.est-plie .c-tete{flex-direction:column;height:100%;justify-content:flex-start;padding:9px 0;}
  .stand-duo.est-plie .c-plier{transform:rotate(180deg);margin:6px 0 0;}
  .gs-console .c-flux{
    flex:1;min-height:0;overflow-y:auto;padding:10px 11px;display:flex;
    flex-direction:column;gap:7px;scrollbar-width:thin;
  }
  .gs-console .c-flux::-webkit-scrollbar{width:7px;}
  .gs-console .c-flux::-webkit-scrollbar-thumb{background:#23331F;border-radius:4px;}

  .c-ligne{
    font-size:11.5px;line-height:1.45;color:#8FA089;
    border-left:2px solid #23331F;padding:1px 0 1px 8px;
    animation:c-entre .22s ease-out;
  }
  @keyframes c-entre{from{opacity:0;transform:translateX(-6px);}to{opacity:1;transform:none;}}
  .c-ligne .c-h{color:#4E5C4A;margin-right:6px;}
  .c-ligne.c-vente{color:#B9D9AC;border-left-color:#2E5A26;}
  .c-ligne.c-crit {color:#FFE34F;border-left-color:#D9A441;}
  .c-ligne.c-evt  {color:#F0D9A6;border-left-color:#D9A441;}
  .c-ligne.c-bien {color:#CFEFC4;border-left-color:#4CAF3D;}
  .c-ligne.c-mal  {color:#F0B6AE;border-left-color:#8B3A2A;}

  /* La fiche d'un client qui attend : c'est la seule ligne avec quoi interagir. */
  .c-client{
    background:rgba(76,175,61,.08);border:1px solid rgba(76,175,61,.3);
    border-radius:10px;padding:8px 10px;animation:c-entre .22s ease-out;
  }
  .c-client.c-fini{opacity:.5;}
  .c-client .c-qui{font-size:10.5px;color:#7BD46A;font-weight:700;margin-bottom:3px;}
  .c-client .c-dit{
    font-size:11.5px;color:#DDE7D8;line-height:1.45;margin-bottom:5px;
    font-family:'Inter',sans-serif;font-style:italic;
  }
  .c-client .c-variete{
    font-size:10px;font-family:'JetBrains Mono',monospace;font-weight:700;
    letter-spacing:.03em;margin-bottom:7px;
  }
  .c-client .c-jauge{height:4px;border-radius:3px;background:rgba(0,0,0,.4);overflow:hidden;margin-bottom:7px;}
  .c-client .c-jauge div{height:100%;background:#7BD46A;transition:width .25s linear,background .25s;}
  .c-client button{
    all:unset;cursor:pointer;display:block;text-align:center;width:100%;box-sizing:border-box;
    font-size:11px;font-weight:700;padding:6px;border-radius:7px;
    background:linear-gradient(120deg,#7BD46A,#4CAF3D);color:#0E140F;
  }
  .c-client button:hover{filter:brightness(1.12);}
  .c-client button:disabled{background:rgba(255,255,255,.08);color:#6E7D69;cursor:default;}

  .scene-frame{
    position:relative;
    border-radius:14px;
    overflow:hidden;
    background:#0d1710;
    height:400px;
  }
  .scene-frame::after{
    content:'';
    position:absolute; inset:0;
    pointer-events:none;
    background:radial-gradient(ellipse at 50% 40%, rgba(0,0,0,0) 45%, rgba(4,10,6,0.55) 100%);
    box-shadow:inset 0 0 60px rgba(0,0,0,0.5);
  }
  .scene-frame canvas{display:block;width:100%;height:100%;cursor:pointer;}
  .scene-hint{
    position:absolute;bottom:10px;left:0;right:0;text-align:center;
    font-size:11px;color:rgba(237,234,224,0.65);font-family:'JetBrains Mono',monospace;
    pointer-events:none;
    text-shadow:0 1px 4px rgba(0,0,0,.6);
  }
  .scene-hint b{color:#fff;}

  .floater{
    /* fixed, pas absolute : il est placé avec clientX/clientY, qui sont des
       coordonnées de FENÊTRE. En absolute il partait d'autant plus haut que la
       page était scrollée, et sortait souvent de l'écran sur mobile. */
    position:fixed;font-family:'JetBrains Mono',monospace;font-weight:800;font-size:18px;
    color:#EAFFDD;pointer-events:none;animation:float-up .95s ease-out forwards;
    /* Le montant s'affiche PAR-DESSUS la scène 3D : feuillage, bois clair, néon.
       Une couleur seule ne peut pas être lisible sur tout ça. D'où le contour
       noir complet (quatre ombres portées, un pixel dans chaque direction) plus
       une ombre diffuse : le texte garde sa forme quel que soit le fond. */
    text-shadow:
      -1px -1px 0 rgba(0,0,0,.92),  1px -1px 0 rgba(0,0,0,.92),
      -1px  1px 0 rgba(0,0,0,.92),  1px  1px 0 rgba(0,0,0,.92),
       0 3px 10px rgba(0,0,0,.85);
    letter-spacing:.02em;
    z-index:2500;
  }
  /* Le montant part d'un petit sursaut avant de monter : l'oeil l'attrape au
     moment où il apparaît, pas au milieu de sa course. */
  @keyframes float-up{
    0%   {opacity:0;   transform:translate(-50%,4px)   scale(.75);}
    14%  {opacity:1;   transform:translate(-50%,-4px)  scale(1.12);}
    26%  {opacity:1;   transform:translate(-50%,-9px)  scale(1);}
    100% {opacity:0;   transform:translate(-50%,-64px) scale(1);}
  }

  /* ---- Navigation verticale et panneaux ---- */
  .gs-layout{display:grid;grid-template-columns:186px 1fr;gap:20px;align-items:start;}
  @media (max-width:820px){ .gs-layout{grid-template-columns:1fr;} }
  .gs-nav{
    display:flex;flex-direction:column;gap:4px;position:sticky;top:14px;
    background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:8px;
  }
  @media (max-width:820px){
    .gs-nav{position:static;flex-direction:row;overflow-x:auto;padding:6px;}
    .gs-nav-btn{white-space:nowrap;}
  a.gs-nav-btn{text-decoration:none;}
  }
  .gs-nav-btn{
    all:unset;cursor:pointer;display:flex;align-items:center;gap:9px;
    font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--text-dim);
    padding:9px 11px;border-radius:9px;transition:background .12s ease,color .12s ease;
  }
  .gs-nav-btn span{font-size:15px;line-height:1;}
  .gs-nav-btn:hover{background:rgba(255,255,255,.05);color:var(--text);}
  .gs-nav-btn:focus-visible{outline:2px solid var(--gold);outline-offset:-2px;}
  .gs-nav-btn.is-active{background:linear-gradient(120deg,rgba(76,175,61,.22),rgba(139,95,191,.18));color:var(--text);font-weight:700;}
  .gs-nav-admin{color:var(--gold);}
  /* La pastille de notification : petite, rouge, avec le compte dedans. */
  .gs-nav-pastille{
    margin-left:auto;min-width:18px;height:18px;padding:0 5px;border-radius:9px;
    background:#C8503C;color:#fff;font-family:'JetBrains Mono',monospace;font-size:10.5px;
    font-style:normal;font-weight:700;line-height:18px;text-align:center;
    box-shadow:0 0 0 2px rgba(200,80,60,.22);
  }
  .gs-nav-pastille[hidden]{display:none;}
  .gs-nav-pastille.urgent{animation:gsPastillePulse 1.1s ease-in-out infinite;}
  @keyframes gsPastillePulse{
    0%,100%{box-shadow:0 0 0 2px rgba(200,80,60,.22);}
    50%    {box-shadow:0 0 0 6px rgba(200,80,60,0);}
  }

  /* Le résultat d'une livraison, sur la page du téléphone. */
  .gs-tel-res{
    display:flex;align-items:center;gap:14px;margin:0 0 14px;padding:14px 18px;border-radius:14px;
    background:linear-gradient(90deg,rgba(76,175,61,.16),var(--surface) 60%);
    border:1px solid var(--leaf);animation:gsCaisseEntre .35s ease-out;
  }
  .gs-tel-res .gs-avatar{width:46px;height:46px;border-radius:11px;}
  .gs-tel-res .tx{flex:1;min-width:0;}
  .gs-tel-res .t{font-family:'Bricolage Grotesque',sans-serif;font-weight:800;font-size:16px;color:var(--text);}
  .gs-tel-res .s{font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--text-dim);line-height:1.5;}
  .gs-tel-res .m{
    font-family:'Bricolage Grotesque',sans-serif;font-weight:800;font-size:22px;
    color:#8BFF6B;white-space:nowrap;
  }
  .gs-panel{display:none;}
  .gs-panel.is-active{display:block;}

  /* ---- La page des succès ---- */
  .gs-suc-famille{margin:0 0 18px;}
  .gs-suc-tete{
    display:flex;align-items:baseline;gap:10px;margin:0 0 8px;
    font-family:'JetBrains Mono',monospace;font-size:11.5px;letter-spacing:.06em;
    text-transform:uppercase;color:var(--text-dim);
  }
  .gs-suc-tete b{color:var(--text);font-weight:700;}
  .gs-suc-tete .p{margin-left:auto;color:var(--leaf);}
  .gs-suc-barre{height:4px;border-radius:3px;background:var(--surface);overflow:hidden;margin-bottom:9px;}
  .gs-suc-barre span{display:block;height:100%;background:var(--leaf);}
  .gs-suc-grille{display:grid;grid-template-columns:repeat(auto-fill,minmax(158px,1fr));gap:7px;}
  .gs-suc{
    display:flex;gap:8px;align-items:center;padding:7px 9px;border-radius:10px;
    background:var(--surface);border:1px solid var(--line);
  }
  .gs-suc.fait{border-color:var(--leaf-dark);background:var(--surface-hi);}
  .gs-suc .ic{
    width:26px;height:26px;flex:none;display:flex;align-items:center;justify-content:center;
    font-size:15px;border-radius:7px;background:var(--panel);overflow:hidden;
  }
  .gs-suc .ic img{width:100%;height:100%;object-fit:contain;}
  .gs-suc .tx{min-width:0;}
  .gs-suc .n{font-size:11.5px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .gs-suc .d{font-family:'JetBrains Mono',monospace;font-size:9.5px;color:var(--text-dim);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .gs-suc:not(.fait) .n{color:var(--text-dim);}
  .gs-suc-plus{
    all:unset;box-sizing:border-box;cursor:pointer;grid-column:1/-1;text-align:center;
    font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-dim);
    padding:7px 2px;border-radius:9px;border:1px dashed var(--line);
  }
  .gs-suc-plus:hover{color:var(--text);border-color:var(--leaf);}

  /* ---- Le téléphone ---- */
  .gs-tel{display:grid;grid-template-columns:1fr 280px;gap:14px;align-items:start;}
  @media (max-width:900px){ .gs-tel{grid-template-columns:1fr;} }
  .gs-tel-carte{
    background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:12px 12px 10px;
  }
  .gs-tel-tete{
    display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:9px;
    font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.08em;
    text-transform:uppercase;color:var(--text-dim);
  }
  .gs-tel-quota{color:var(--gold);}
  #gsTelMap svg{width:100%;height:auto;display:block;border-radius:10px;background:#0B140E;}
  .gs-tel-legende{
    margin-top:8px;font-family:'JetBrains Mono',monospace;font-size:10.5px;color:var(--text-dim);
    line-height:1.6;
  }
  .gs-tel-cote{display:flex;flex-direction:column;gap:10px;}
  .gs-tel-stock{
    display:flex;flex-direction:column;gap:2px;padding:10px 13px;border-radius:12px;
    background:var(--surface);border:1px solid var(--line);
  }
  .gs-tel-stock .s-label{
    font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.09em;
    text-transform:uppercase;color:var(--text-dim);
  }
  .gs-tel-stock .s-valeur{font-family:'Bricolage Grotesque',sans-serif;font-weight:800;font-size:20px;color:var(--text);}
  .gs-tel-stock .s-note{font-size:10.5px;color:var(--text-dim);line-height:1.45;}
  .gs-tel-cmd{
    display:flex;gap:9px;align-items:center;padding:8px 10px;margin-bottom:7px;border-radius:11px;
    background:var(--surface);border:1px solid var(--line);cursor:pointer;transition:border-color .15s;
  }
  .gs-tel-cmd:hover{border-color:var(--leaf);}
  .gs-tel-cmd.is-cible{border-color:var(--gold);background:var(--surface-hi);}
  .gs-tel-cmd .c-txt{flex:1;min-width:0;}
  .gs-tel-cmd .c-nom{font-size:12px;font-weight:600;color:var(--text);}
  .gs-tel-cmd .c-det{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-dim);}
  .gs-tel-cmd .c-min{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--gold);}
  .gs-avatar{width:38px;height:38px;border-radius:10px;flex:none;overflow:hidden;background:var(--surface-hi);}
  .gs-avatar svg,.gs-avatar img{width:100%;height:100%;display:block;object-fit:cover;}
  .gs-tel-contact{display:flex;gap:11px;align-items:flex-start;}
  .gs-tel-contact .gs-avatar{width:54px;height:54px;border-radius:12px;}
  .gs-tel-ligne{display:flex;gap:7px;align-items:center;margin-top:7px;flex-wrap:wrap;}
  .gs-tel-ligne input{
    all:unset;box-sizing:border-box;width:74px;padding:5px 8px;border-radius:8px;
    background:var(--panel);border:1px solid var(--line);color:var(--text);
    font-family:'JetBrains Mono',monospace;font-size:12px;text-align:right;
  }
  .gs-tel-ligne input:focus{border-color:var(--leaf);}
  .gs-tel-verrou{opacity:.45;}

  /* ---- La boutique : le résultat d'une caisse ---- */
  .gs-caisse-res{
    display:flex;align-items:center;gap:14px;margin:0 0 14px;padding:14px 18px;border-radius:14px;
    background:var(--surface);border:1px solid var(--line);
    animation:gsCaisseEntre .35s ease-out;
  }
  @keyframes gsCaisseEntre{ from{opacity:0;transform:translateY(-6px);} to{opacity:1;transform:none;} }
  .gs-caisse-res.gagne{border-color:var(--leaf);background:linear-gradient(90deg,rgba(76,175,61,.16),var(--surface) 60%);}
  .gs-caisse-res.perd{border-color:#8B3A2A;background:linear-gradient(90deg,rgba(200,80,60,.14),var(--surface) 60%);}
  .gs-caisse-res.jackpot{border-color:var(--gold);background:linear-gradient(90deg,rgba(217,164,65,.24),var(--surface) 65%);}
  .gs-caisse-res .em{font-size:34px;flex:none;line-height:1;}
  .gs-caisse-res .tx{flex:1;min-width:0;}
  .gs-caisse-res .t{
    font-family:'Bricolage Grotesque',sans-serif;font-weight:800;font-size:16px;color:var(--text);
    margin-bottom:2px;
  }
  .gs-caisse-res .s{font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--text-dim);line-height:1.5;}
  .gs-caisse-res .m{
    font-family:'Bricolage Grotesque',sans-serif;font-weight:800;font-size:22px;white-space:nowrap;
  }
  .gs-caisse-res.gagne .m,.gs-caisse-res.jackpot .m{color:#8BFF6B;}
  .gs-caisse-res.perd .m{color:#E8836F;}
  .gs-caisse-hist{
    margin:0 0 14px;font-family:'JetBrains Mono',monospace;font-size:10.5px;color:var(--text-dim);
    display:flex;flex-wrap:wrap;gap:6px;align-items:center;
  }
  .gs-caisse-hist b{color:var(--text-dim);font-weight:400;}
  .gs-caisse-hist span{padding:2px 7px;border-radius:7px;background:var(--surface);border:1px solid var(--line);}
  .gs-caisse-hist span.g{color:#8BFF6B;border-color:var(--leaf-dark);}
  .gs-caisse-hist span.p{color:#E8836F;}

  /* ---- La boutique ---- */
  .gs-bout-limite{
    font-family:'JetBrains Mono',monospace;font-size:10.5px;color:var(--text-dim);
    letter-spacing:.03em;
  }
  .gs-bout-effet{
    font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--leaf);
  }
  .gs-chances{
    margin:18px 0 0;padding:12px 15px;border-radius:12px;
    background:var(--surface);border:1px solid var(--line);
  }
  .gs-chances h4{
    margin:0 0 4px;font-family:'JetBrains Mono',monospace;font-size:11.5px;
    letter-spacing:.05em;text-transform:uppercase;color:var(--text-dim);font-weight:700;
  }
  .gs-chances .c-note{font-size:11.5px;color:var(--text-dim);line-height:1.55;margin:0 0 9px;}
  .gs-chances table{width:100%;border-collapse:collapse;font-family:'JetBrains Mono',monospace;font-size:11.5px;}
  .gs-chances td{padding:4px 0;border-bottom:1px solid var(--line);color:var(--text);}
  .gs-chances tr:last-child td{border-bottom:none;}
  .gs-chances td.c-pct{width:60px;color:var(--gold);}
  .gs-chances td.c-mult{width:70px;text-align:right;color:var(--text-dim);}

  /* ---- Panel admin : les noms d'améliorations d'un stand ---- */
  .gs-stand-noms{
    margin:0 0 14px;padding:10px 14px;border-radius:12px;
    background:var(--surface);border:1px solid var(--line);
  }
  .gs-stand-noms summary{
    cursor:pointer;font-family:'JetBrains Mono',monospace;font-size:12px;
    color:var(--text);letter-spacing:.02em;
  }
  .gs-noms-grille{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px;}
  .gs-nom-ligne{
    display:flex;flex-direction:column;gap:5px;padding:9px 11px;border-radius:10px;
    background:var(--panel);border:1px solid var(--line);
  }
  .gs-nom-ligne .cle{
    font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.06em;
    text-transform:uppercase;color:var(--text-dim);
  }
  .gs-nom-ligne input{
    all:unset;box-sizing:border-box;width:100%;padding:6px 9px;border-radius:8px;
    background:var(--surface);border:1px solid var(--line);color:var(--text);
    font-family:'Inter',sans-serif;font-size:12px;
  }
  .gs-nom-ligne input:focus{border-color:var(--leaf);}
  .gs-nom-ligne input.desc{font-size:11px;color:var(--text-dim);}

  /* ---- Les stands : la barre de choix du comptoir ---- */
  .gs-stands{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px;}
  .gs-stand{
    all:unset;box-sizing:border-box;cursor:pointer;flex:1 1 170px;min-width:150px;
    display:flex;flex-direction:column;gap:3px;padding:9px 12px;border-radius:12px;
    background:var(--surface);border:1px solid var(--line);
    font-family:'JetBrains Mono',monospace;color:var(--text-dim);
    transition:border-color .15s, background .15s, color .15s;
  }
  .gs-stand:hover:not(.is-locked){border-color:var(--leaf);color:var(--text);}
  .gs-stand:focus-visible{outline:2px solid var(--gold);outline-offset:2px;}
  .gs-stand.is-active{
    background:var(--surface-hi);border-color:var(--leaf);color:var(--text);
    box-shadow:inset 0 0 0 1px rgba(255,255,255,.05);
  }
  .gs-stand.is-locked{cursor:not-allowed;opacity:.5;}
  .gs-stand .n{font-weight:700;font-size:12.5px;letter-spacing:.02em;display:flex;align-items:center;gap:6px;}
  .gs-stand .n b{font-weight:700;}
  .gs-stand .p{font-size:10.5px;line-height:1.45;opacity:.85;}
  .gs-stand .m{font-size:10px;letter-spacing:.03em;opacity:.7;}
  .gs-stands-note{
    flex:1 1 100%;font-family:'JetBrains Mono',monospace;font-size:10.5px;
    color:var(--text-dim);letter-spacing:.02em;padding:2px 2px 0;
  }
  .gs-stand.is-active .m{color:var(--leaf);opacity:1;}

  /* ---- Franchise ---- */
  .gs-franchise{
    /* Deux compteurs (or, platine) puis la colonne d'action. */
    display:grid;grid-template-columns:minmax(170px,210px) minmax(170px,210px) 1fr;gap:14px;align-items:center;
    background:var(--panel);border:1px solid rgba(217,164,65,.3);border-radius:14px;
    padding:16px;margin-bottom:26px;
  }
  @media (max-width:980px){ .gs-franchise{grid-template-columns:1fr 1fr;} }
  @media (max-width:720px){ .gs-franchise{grid-template-columns:1fr;} }
  .gs-fr-left{
    display:flex;flex-direction:column;gap:4px;padding:14px 16px;border-radius:11px;
    background:linear-gradient(150deg,rgba(217,164,65,.18),rgba(139,95,191,.12));
    border:1px solid rgba(217,164,65,.24);
  }
  .gs-fr-label{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:var(--text-dim);}
  .gs-fr-value{font-family:'JetBrains Mono',monospace;font-weight:700;font-size:26px;color:var(--gold);font-variant-numeric:tabular-nums;}
  /* Le platine se lit d'un coup d'œil comme une monnaie différente de l'or. */
  #gsPlatine{color:#CFE3F5;}
  .gs-fr-note{font-size:11px;color:var(--text-dim);}
  .gs-fr-right{display:flex;flex-direction:column;gap:9px;align-items:flex-start;}
  .gs-fr-hint{margin:0;font-size:12px;color:var(--text-dim);max-width:46ch;}
  .gs-fr-btn{
    all:unset;cursor:pointer;font-family:'JetBrains Mono',monospace;font-weight:700;font-size:13px;
    padding:10px 18px;border-radius:10px;color:#fff;background:linear-gradient(120deg,#D9A441,#8B5FBF);
  }
  .gs-fr-btn:disabled{opacity:.35;cursor:not-allowed;}
  .gs-fr-btn:focus-visible{outline:2px solid var(--gold);outline-offset:2px;}

  /* ---- Classements ---- */
  .gs-boards{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:14px;}
  .gs-bcard{
    background:var(--panel);border:1px solid var(--line);border-radius:14px;
    padding:14px;display:flex;flex-direction:column;gap:10px;min-width:0;
  }
  .gs-bcard-head{display:flex;flex-direction:column;gap:3px;}
  .gs-bcard-title{
    font-family:'Bricolage Grotesque',sans-serif;font-weight:800;font-size:14px;color:var(--text);
    display:flex;align-items:center;gap:7px;
  }
  /* La règle est SOUS le titre, pas à droite : à droite elle se faisait pousser au bord
     de la carte par les noms longs, et on lisait des bouts de phrase sur les côtés. */
  .gs-bcard-rule{font-size:11px;color:var(--text-dim);line-height:1.45;}
  .gs-bcard-rows{display:flex;flex-direction:column;gap:3px;}
  .gs-brow{
    display:grid;grid-template-columns:26px 1fr auto;align-items:center;gap:9px;
    padding:7px 9px;border-radius:9px;background:rgba(255,255,255,.028);min-width:0;
  }
  .gs-brow.is-me{background:rgba(76,175,61,.16);border:1px solid rgba(76,175,61,.32);}
  .gs-brow-rank{
    font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:700;
    color:var(--text-dim);text-align:center;font-variant-numeric:tabular-nums;
  }
  .gs-brow:nth-child(1) .gs-brow-rank{color:#D9A441;}
  .gs-brow:nth-child(2) .gs-brow-rank{color:#C6CBD1;}
  .gs-brow:nth-child(3) .gs-brow-rank{color:#C08552;}
  .gs-brow-name{
    font-size:12.5px;color:var(--text);min-width:0;
    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  }
  .gs-brow-val{
    font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:700;color:var(--gold);
    font-variant-numeric:tabular-nums;white-space:nowrap;
  }
  .gs-brow-sub{font-size:10px;color:var(--text-dim);}
  .gs-board-empty{font-size:12px;color:var(--text-dim);padding:10px 2px;}
  /* Les lots annoncés en tête du classement doté. */
  .gs-bcard-lots{
    margin-top:9px;padding:8px 10px;border-radius:10px;
    background:rgba(217,164,65,.1);border:1px solid rgba(217,164,65,.32);
  }
  .gs-bcard-lots .l-tete{
    font-family:'JetBrains Mono',monospace;font-size:9.5px;letter-spacing:.14em;
    text-transform:uppercase;color:var(--gold);margin-bottom:5px;
  }
  .gs-bcard-lots .l-ligne{
    display:flex;gap:8px;align-items:baseline;font-size:11.5px;color:#F0D9A6;
    font-family:'JetBrains Mono',monospace;line-height:1.7;
  }
  .gs-brow.est-dote{border-color:rgba(217,164,65,.4);}
  .gs-brow.est-dote .gs-brow-rank{font-size:15px;}

  .ach-img{width:34px;height:34px;object-fit:contain;display:block;margin:0 auto 4px;}

  /* ---- Panel admin ---- */
  .gs-admin-cat{
    font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.12em;
    text-transform:uppercase;color:var(--gold);margin:18px 0 8px;
  }
  .gs-admin-cat:first-child{margin-top:0;}
  .gs-badge-form{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:9px;
    background:var(--panel);border:1px solid var(--line);border-radius:12px;
    padding:13px;margin-bottom:14px;align-items:end;
  }
  .gs-badge-form label{display:flex;flex-direction:column;gap:4px;font-size:10px;color:var(--text-dim);
    font-family:'JetBrains Mono',monospace;letter-spacing:.06em;text-transform:uppercase;}
  .gs-badge-form input,.gs-badge-form select{
    all:unset;box-sizing:border-box;width:100%;font-family:'Inter',sans-serif;font-size:13px;
    color:var(--text);background:rgba(0,0,0,.28);border:1px solid rgba(255,255,255,.1);
    border-radius:8px;padding:7px 9px;
  }
  .gs-badge-form input:focus,.gs-badge-form select:focus{border-color:var(--gold);}
  .gs-badge-form option{background:#16211A;color:#E6EDE4;}
  .gs-badge-form button{
    all:unset;cursor:pointer;text-align:center;font-family:'JetBrains Mono',monospace;
    font-weight:700;font-size:12px;padding:9px;border-radius:8px;color:#0E140F;
    background:linear-gradient(120deg,#7BD46A,#4CAF3D);
  }
  .gs-badge-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:9px;margin-bottom:6px;}
  .gs-badge-row{
    display:flex;align-items:center;gap:9px;padding:9px 11px;border-radius:10px;
    background:rgba(255,255,255,.03);border:1px solid var(--line);
    /* Les boutons passent à la ligne plutôt que d'écraser le nom du badge : avant,
       « Première vente » s'affichait « Premiè… » et la condition tenait sur cinq
       lignes de trois caractères. */
    flex-wrap:wrap;
  }
  .gs-badge-row .b-emoji{font-size:19px;}
  .gs-badge-row .b-txt{flex:1 1 140px;min-width:0;}
  .gs-badge-row .b-nom{font-size:12.5px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .gs-badge-row .b-cond{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-dim);}
  .gs-badge-row button{
    all:unset;cursor:pointer;font-family:'JetBrains Mono',monospace;font-size:10px;
    padding:5px 8px;border-radius:7px;flex:0 0 auto;
  }
  .gs-badge-row .b-act{flex:1 1 100%;display:flex;gap:6px;}
  .gs-badge-row .b-act button{flex:1;text-align:center;}
  .gs-badge-row .b-edit{background:rgba(76,175,61,.2);color:#CFEFC4;}
  .gs-badge-row .b-del{background:rgba(200,60,50,.18);color:#F0B6AE;}
  .gs-admin-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px;}
  .gs-admin-card{
    background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:11px;
    display:flex;flex-direction:column;gap:8px;
  }
  .gs-admin-thumb{
    height:96px;border-radius:9px;background:rgba(0,0,0,.28);
    display:flex;align-items:center;justify-content:center;overflow:hidden;
  }
  .gs-admin-thumb img{max-width:100%;max-height:96px;object-fit:contain;}
  .gs-admin-thumb .vide{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-dim);}
  .gs-admin-name{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text);overflow-wrap:anywhere;}
  .gs-admin-meta{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-dim);}
  .gs-admin-actions{display:flex;gap:6px;}
  .gs-admin-actions label,.gs-admin-actions button{
    all:unset;cursor:pointer;flex:1;text-align:center;
    font-family:'JetBrains Mono',monospace;font-size:11px;padding:6px 4px;border-radius:8px;
  }
  .gs-admin-actions label{background:rgba(76,175,61,.22);color:#CFEFC4;}
  .gs-admin-actions button{background:rgba(200,60,50,.18);color:#F0B6AE;}
  .gs-admin-actions label:focus-within,.gs-admin-actions button:focus-visible{outline:2px solid var(--gold);outline-offset:2px;}
  .gs-admin-actions input[type=file]{display:none;}

  /* ---- Panel admin : la navigation interne ----
     Le panel empilait tout sur une seule page : les feuilles, le formulaire des
     badges, la liste des badges, puis trente-sept vignettes d'images. On ne
     savait plus par où commencer. Quatre onglets, une tâche par onglet. */
  .gs-adm-nav{display:flex;flex-wrap:wrap;gap:7px;margin:0 0 16px;}
  .gs-adm-tab{
    all:unset;cursor:pointer;font-family:'JetBrains Mono',monospace;font-size:11.5px;
    padding:9px 14px;border-radius:10px;color:var(--text-dim);
    background:rgba(255,255,255,.04);border:1px solid var(--line);
  }
  .gs-adm-tab:hover{color:var(--text);}
  .gs-adm-tab.is-active{background:rgba(76,175,61,.2);border-color:#4CAF3D;color:#CFEFC4;font-weight:700;}
  .gs-adm-tab:focus-visible{outline:2px solid var(--gold);outline-offset:2px;}
  .gs-adm-page[hidden]{display:none;}
  .gs-adm-intro{
    background:rgba(255,255,255,.03);border:1px solid var(--line);border-left:3px solid var(--gold);
    border-radius:10px;padding:11px 14px;margin:0 0 14px;
    font-size:12.5px;line-height:1.6;color:var(--text-dim);
  }
  .gs-adm-intro b{color:var(--text);}
  .gs-adm-filter{display:flex;flex-wrap:wrap;gap:9px;align-items:center;margin:0 0 12px;}
  .gs-adm-filter input[type=search]{
    all:unset;box-sizing:border-box;flex:1;min-width:170px;font-family:'Inter',sans-serif;font-size:13px;
    color:var(--text);background:rgba(0,0,0,.28);border:1px solid rgba(255,255,255,.1);
    border-radius:8px;padding:8px 11px;
  }
  .gs-adm-filter input[type=search]:focus{border-color:var(--gold);}
  .gs-adm-filter label{
    display:flex;align-items:center;gap:6px;cursor:pointer;
    font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-dim);
  }
  /* Le décompte se cale à droite : collé à la case à cocher, on lisait
     « Seulement celles qui manquent 9 / 9 affichée(s) » d'une seule traite. */
  .gs-adm-filter .gs-admin-meta{margin-left:auto;}
  /* Les étapes de l'onglet Aide : une consigne par carte, dans l'ordre. */
  .gs-adm-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:11px;}
  .gs-adm-step{
    background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:14px;
    font-size:12.5px;line-height:1.6;color:var(--text-dim);
  }
  .gs-adm-step h4{
    margin:0 0 7px;font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.1em;
    text-transform:uppercase;color:var(--gold);font-weight:700;
  }
  .gs-adm-step b{color:var(--text);}
  .gs-adm-step code{
    font-family:'JetBrains Mono',monospace;font-size:11px;background:rgba(0,0,0,.3);
    padding:1px 5px;border-radius:5px;color:#CFEFC4;
  }
  .gs-adm-btn{
    all:unset;cursor:pointer;font-family:'JetBrains Mono',monospace;font-size:11.5px;
    padding:8px 14px;border-radius:9px;background:rgba(76,175,61,.2);color:#CFEFC4;
    border:1px solid rgba(76,175,61,.4);
  }
  .gs-adm-btn:hover{background:rgba(76,175,61,.3);}
  .gs-adm-btn:focus-visible{outline:2px solid var(--gold);outline-offset:2px;}
  .gs-adm-btn.est-off{background:rgba(200,60,50,.2);border-color:rgba(200,60,50,.5);color:#F0B6AE;}
  .gs-adm-switch{
    display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin:0 0 14px;
    background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:13px 15px;
  }
  .gs-adm-switch.est-off{border-color:#8B3A2A;background:rgba(120,30,30,.14);}
  .gs-adm-switch .s-txt{flex:1;min-width:200px;}
  .gs-adm-switch .s-titre{font-size:13px;color:var(--text);margin-bottom:3px;}
  .gs-adm-switch .s-note{font-size:11.5px;color:var(--text-dim);line-height:1.5;}
  /* Une ligne par compte surveillé / par cliché de sauvegarde. */
  .gs-adm-ligne{
    display:flex;align-items:center;gap:12px;flex-wrap:wrap;
    background:var(--panel);border:1px solid var(--line);border-radius:11px;
    padding:11px 13px;margin-bottom:8px;
  }
  .gs-adm-ligne.alerte{border-color:#8B3A2A;background:rgba(120,30,30,.14);}
  .gs-adm-ligne .qui{
    font-family:'JetBrains Mono',monospace;font-size:13px;color:var(--text);
    font-weight:700;flex:0 0 auto;
  }
  .gs-adm-ligne .quoi{flex:1;min-width:150px;font-size:12px;color:var(--text-dim);line-height:1.55;}
  .gs-adm-ligne .quoi b{color:var(--text);}
  .gs-adm-ligne .etat{
    font-family:'JetBrains Mono',monospace;font-size:10.5px;padding:4px 9px;border-radius:20px;
    background:rgba(255,255,255,.06);color:var(--text-dim);flex:0 0 auto;
  }
  .gs-adm-ligne .etat.rouge{background:rgba(200,60,50,.22);color:#F0B6AE;}
  .gs-adm-ligne .etat.jaune{background:rgba(217,164,65,.18);color:#F0D9A6;}
  /* La vignette d'un badge dans la liste : c'est ELLE qu'on clique pour changer
     l'image. Plus besoin d'aller chercher « badge_xxx » dans la grille d'images. */
  .gs-badge-row .b-img{
    width:38px;height:38px;flex:0 0 38px;border-radius:9px;cursor:pointer;
    background:rgba(0,0,0,.28);border:1px dashed rgba(255,255,255,.16);
    display:flex;align-items:center;justify-content:center;overflow:hidden;font-size:19px;
  }
  .gs-badge-row .b-img:hover{border-color:var(--gold);}
  .gs-badge-row .b-img img{width:100%;height:100%;object-fit:contain;}
  .gs-badge-row .b-img input[type=file]{display:none;}
  .gs-badge-row .b-img.has-img{border-style:solid;}
  .gs-badge-form .champ-image{grid-column:1 / -1;}
  .gs-badge-form .champ-image .zone{
    display:flex;align-items:center;gap:11px;background:rgba(0,0,0,.22);
    border:1px solid rgba(255,255,255,.1);border-radius:9px;padding:9px 11px;
  }
  .gs-badge-form .champ-image .apercu{
    width:46px;height:46px;flex:0 0 46px;border-radius:9px;background:rgba(0,0,0,.3);
    display:flex;align-items:center;justify-content:center;overflow:hidden;font-size:22px;
  }
  .gs-badge-form .champ-image .apercu img{width:100%;height:100%;object-fit:contain;}
  .gs-badge-form .champ-image .aide{flex:1;font-size:11.5px;line-height:1.5;
    color:var(--text-dim);text-transform:none;letter-spacing:0;font-family:'Inter',sans-serif;}
  .gs-badge-form button.secondaire{background:rgba(255,255,255,.07);color:var(--text);}

  /* ---- Palmarès du mois ---- */
  .gs-podium{
    background:linear-gradient(180deg,rgba(217,164,65,.16),rgba(217,164,65,.06));
    border:1px solid rgba(217,164,65,.45);border-radius:14px;padding:13px 15px;margin:0 0 16px;
  }
  .gs-podium .p-tete{
    font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.14em;
    text-transform:uppercase;color:var(--gold);margin-bottom:9px;
  }
  .gs-podium .p-ligne{
    display:flex;align-items:center;gap:10px;padding:5px 0;flex-wrap:wrap;
    border-top:1px solid rgba(217,164,65,.18);
  }
  .gs-podium .p-ligne:first-of-type{border-top:0;}
  .gs-podium .p-rang{font-size:17px;flex:0 0 auto;}
  .gs-podium .p-qui{font-size:13px;color:var(--text);font-weight:600;flex:1;min-width:110px;}
  .gs-podium .p-lot{
    font-family:'JetBrains Mono',monospace;font-size:11.5px;color:#F0D9A6;
  }

  /* ---- Objectifs du jour ---- */
  .gs-daily-ligne{
    background:var(--panel);border:1px solid var(--line);border-radius:11px;
    padding:10px 13px;margin-bottom:7px;
  }
  .gs-daily-ligne.est-fait{border-color:rgba(76,175,61,.55);background:rgba(76,175,61,.09);}
  .gs-daily-ligne .d-haut{
    display:flex;justify-content:space-between;align-items:baseline;gap:10px;
    font-size:12.5px;color:var(--text);margin-bottom:7px;
  }
  .gs-daily-ligne .d-chiffre{
    font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-dim);flex:0 0 auto;
  }
  .gs-daily-ligne .d-barre{height:6px;border-radius:4px;background:rgba(0,0,0,.32);overflow:hidden;}
  .gs-daily-ligne .d-barre div{
    height:100%;border-radius:4px;background:linear-gradient(90deg,#4CAF3D,#7BD46A);
    transition:width .4s ease;
  }
  .gs-daily-ligne.est-fait .d-barre div{background:linear-gradient(90deg,#D9A441,#F0D9A6);}

  /* ---- Distributeur ---- */
  .gs-dist{
    display:grid;grid-template-columns:minmax(190px,240px) 1fr;gap:14px;
    background:var(--panel);border:1px solid rgba(217,164,65,.28);border-radius:14px;
    padding:16px;margin-bottom:26px;
  }
  @media (max-width:720px){ .gs-dist{grid-template-columns:1fr;} }
  /* Avec le distributeur en 3D, la colonne de 240 px ne suffit pas : on passe la
     carte sur toute la largeur et la grille des articles s'installe dessous. */
  .gs-dist.has-atm{grid-template-columns:1fr;}
  .gs-dist-bank{
    display:flex;flex-direction:column;justify-content:center;gap:4px;
    padding:14px 16px;border-radius:11px;
    background:linear-gradient(150deg,rgba(76,175,61,.16),rgba(139,95,191,.14));
    border:1px solid rgba(217,164,65,.22);
  }
  .gs-dist-bank-label{
    font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.12em;
    text-transform:uppercase;color:var(--text-dim);
  }
  .gs-dist-bank-value{
    font-family:'JetBrains Mono',monospace;font-weight:700;font-size:24px;color:var(--gold);
    font-variant-numeric:tabular-nums;line-height:1.15;overflow-wrap:anywhere;
  }
  .gs-dist-bank-note{font-size:11px;color:var(--text-dim);line-height:1.4;}

  /* ---- Le distributeur en 3D ---- */
  .gs-atm{
    display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);gap:18px;align-items:stretch;
    margin:0 0 14px;padding:14px;border-radius:16px;
    background:var(--panel);border:1px solid var(--line);
  }
  @media (max-width:760px){ .gs-atm{grid-template-columns:1fr;} }
  .gs-atm-scene{
    position:relative;min-height:340px;border-radius:12px;overflow:hidden;cursor:grab;
    background:radial-gradient(ellipse at 50% 30%, #232A32, #0D1116 72%);
  }
  .gs-atm-scene:active{cursor:grabbing;}
  .gs-atm-scene canvas{display:block;width:100%;height:100%;}
  .gs-atm-chargement{
    position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
    font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--text-dim);
  }
  .gs-atm-info{display:flex;flex-direction:column;gap:4px;justify-content:center;padding:6px 4px;}
  .gs-atm-aide{margin-top:10px;font-size:11.5px;color:var(--gold);opacity:.85;line-height:1.45;}
  /* La mention d'auteur : un pied de carte, sur toute la largeur, en tout petit. */
  .gs-atm-credit{
    grid-column:1/-1;margin:10px 0 0;padding-top:9px;border-top:1px solid var(--line);
    font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--text-dim);
    opacity:.55;line-height:1.5;text-align:right;overflow-wrap:anywhere;
  }
  .gs-dist-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;}
  .gs-dist-card{
    display:flex;flex-direction:column;gap:8px;padding:13px 14px;border-radius:11px;
    background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);
  }
  .gs-dist-card h4{
    font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:14px;margin:0;
    display:flex;align-items:center;gap:7px;
  }
  .gs-dist-card .rate{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-dim);}
  .gs-dist-card .have{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-dim);}
  .gs-dist-row{display:flex;gap:6px;align-items:stretch;}
  .gs-dist-row input{
    all:unset;flex:1;min-width:0;box-sizing:border-box;
    font-family:'JetBrains Mono',monospace;font-size:13px;color:var(--text);
    background:rgba(0,0,0,.28);border:1px solid rgba(255,255,255,.1);border-radius:8px;
    padding:7px 9px;text-align:right;font-variant-numeric:tabular-nums;
  }
  .gs-dist-row input:focus{border-color:var(--gold);box-shadow:0 0 0 2px rgba(217,164,65,.18);}
  .gs-dist-row button{
    all:unset;cursor:pointer;font-family:'JetBrains Mono',monospace;font-weight:700;
    font-size:12px;padding:7px 13px;border-radius:8px;color:#0E140F;
    background:linear-gradient(120deg,#7BD46A,#4CAF3D);white-space:nowrap;
  }
  .gs-dist-row button:disabled{opacity:.4;cursor:not-allowed;}
  .gs-dist-row button:focus-visible{outline:2px solid var(--gold);outline-offset:2px;}
  .gs-dist-max{
    all:unset;cursor:pointer;font-family:'JetBrains Mono',monospace;font-size:10px;
    color:var(--gold);text-decoration:underline;align-self:flex-start;
  }
  .gs-dist-max:focus-visible{outline:2px solid var(--gold);outline-offset:2px;}

  .shop-head{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:12px;}
  .shop-head h2{
    font-family:'Bricolage Grotesque',sans-serif;font-size:15px;text-transform:uppercase;letter-spacing:.1em;
    color:var(--text-dim);margin:0;
  }
  .shop-grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(260px,1fr));gap:12px;}
  .qty-btn{
    all:unset;cursor:pointer;font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:700;
    padding:5px 11px;border-radius:100px;color:var(--text-dim);border:1px solid var(--line);
    transition:border-color .15s ease, color .15s ease;
  }
  .qty-btn.active{color:var(--gold);border-color:rgba(217,164,65,0.5);background:rgba(217,164,65,0.08);}
  .item-card{
    background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:14px;
    display:flex;gap:12px;align-items:flex-start;transition:border-color .15s ease;
  }
  .item-card.affordable{border-color:rgba(76,175,61,0.45);}
  .item-swatch{
    width:44px;height:44px;flex:none;border-radius:10px;
    display:flex;align-items:center;justify-content:center;overflow:hidden;
    background:var(--surface-hi);
  }
  .item-swatch img{width:100%;height:100%;object-fit:contain;}
  .item-body{flex:1;min-width:0;}
  .item-body h3{margin:0 0 2px;font-size:14px;font-family:'Bricolage Grotesque',sans-serif;font-weight:600;}
  .item-body p{margin:0 0 8px;font-size:12px;color:var(--text-dim);line-height:1.4;}
  .item-foot{display:flex;justify-content:space-between;align-items:center;gap:8px;}
  .item-lvl{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-dim);}
  .item-gain{margin:0 0 8px;font-size:11px;font-family:'JetBrains Mono',monospace;line-height:1.5;}
  .item-gain .now{color:var(--text-dim);}
  .item-gain .next{color:var(--leaf);font-weight:700;}
  .buy-btn{
    all:unset;cursor:pointer;font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:700;
    padding:7px 12px;border-radius:8px;background:var(--leaf-dark);color:#D8F0CE;
    transition:background .15s ease, transform .08s ease;
  }
  .buy-btn:hover{background:var(--leaf);color:#0A1508;}
  .buy-btn:active{transform:scale(.95);}
  .buy-btn:disabled{background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.25);cursor:not-allowed;}

  .ach-head{display:flex;justify-content:space-between;align-items:baseline;margin:26px 0 12px;}
  .ach-head h2{
    font-family:'Bricolage Grotesque',sans-serif;font-size:15px;text-transform:uppercase;letter-spacing:.1em;
    color:var(--text-dim);margin:0;
  }
  .ach-count{font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--gold);}
  .ach-grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(150px,1fr));gap:10px;}
  .ach-badge{
    background:var(--surface);border:1px solid var(--line);border-radius:12px;
    padding:10px;display:flex;align-items:center;gap:8px;
    transition:border-color .15s ease, opacity .15s ease;
  }
  .ach-badge.locked{opacity:0.4;}
  .ach-badge.unlocked{border-color:rgba(217,164,65,0.5);background:rgba(217,164,65,0.06);}
  .ach-icon{font-size:20px;flex:none;}
  .ach-name{font-size:11px;color:var(--text);line-height:1.3;}

  .ach-toast{
    position:fixed;top:16px;left:50%;
    transform:translate(-50%,-20px);
    background:var(--panel);border:1px solid var(--gold);border-radius:12px;
    padding:10px 16px;display:flex;align-items:center;gap:10px;
    box-shadow:0 8px 24px rgba(0,0,0,0.5);
    opacity:0;transition:opacity .3s ease, transform .3s ease;
    z-index:1000;max-width:90vw;
  }
  .ach-toast.show{opacity:1;transform:translate(-50%,0);}
  .ach-toast-icon{font-size:22px;}
  .ach-toast b{color:var(--gold);font-family:'Bricolage Grotesque',sans-serif;font-size:12px;}
  .ach-toast span:last-child{font-size:12px;color:var(--text);line-height:1.4;}

  .stat-bar{
    margin-top:26px;display:flex;gap:14px;flex-wrap:wrap;font-family:'JetBrains Mono',monospace;
    font-size:11px;color:var(--text-dim);border-top:1px solid var(--line);padding-top:14px;
  }
  .stat-bar span b{color:var(--text);}
  .note{margin-top:18px;font-size:11px;color:var(--text-dim);text-align:center;}

  /* ---------------- Barre site : retour + solde DT ---------------- */
  .gs-sitebar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin:0 0 18px;font-family:'JetBrains Mono',monospace;font-size:12px;}
  .gs-back{color:var(--text-dim);text-decoration:none;border:1px solid var(--line);padding:6px 12px;border-radius:100px;transition:border-color .15s ease,color .15s ease;}
  .gs-back:hover{color:var(--text);border-color:var(--leaf);}
  .gs-wallet{display:flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--line);padding:6px 12px;border-radius:100px;color:var(--text-dim);}
  .gs-wallet b{color:var(--gold);}
  .gs-wallet.is-credited{animation:gs-wallet-credit .65s ease;}
  @keyframes gs-wallet-credit{0%,100%{box-shadow:none;}45%{border-color:var(--gold);box-shadow:0 0 18px rgba(217,164,65,.35);transform:scale(1.02);}}
  .gs-wallet-guest a{color:var(--leaf);}
  .gs-dt-coin{width:16px;height:16px;object-fit:contain;vertical-align:middle;flex:none;}
  .gs-sync-note{color:var(--leaf);opacity:0;transition:opacity .3s ease;white-space:nowrap;}
  .gs-sync-note.show{opacity:1;}

  /* ---------------- Classement des meilleurs vendeurs ---------------- */
  .gs-board{display:flex;flex-direction:column;gap:6px;}
  .gs-board-row{display:flex;align-items:center;gap:12px;background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:8px 14px;}
  .gs-board-row.is-me{border-color:rgba(217,164,65,0.5);background:rgba(217,164,65,0.06);}
  .gs-board-rank{font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--text-dim);width:26px;text-align:center;flex:none;}
  .gs-board-user{display:flex;align-items:center;gap:8px;flex:1;min-width:0;}
  .gs-board-name{font-size:13px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .gs-board-sub{font-size:11px;color:var(--text-dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .gs-board-value{display:flex;align-items:center;gap:6px;font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--gold);flex:none;}
  .gs-board-me-out{margin-top:8px;padding-top:8px;border-top:1px dashed var(--line);}
  .gs-board .avatar-sm{width:26px;height:26px;border-radius:50%;object-fit:cover;flex:none;background:var(--surface-hi);}
  .gs-board .avatar-sm svg{width:60%;height:60%;margin:20%;color:var(--text-dim);}
  .gs-board .user-name{color:var(--text);}
  .gs-board-cols{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;}
  .gs-board-col-head{display:flex;align-items:baseline;justify-content:space-between;gap:8px;margin:0 0 10px;}
  .gs-board-col-head h3{margin:0;font-size:15px;color:var(--text);}
  .gs-board-col-head span{font-size:12px;color:var(--text-dim);}
  .gs-board-value.wins-value{color:var(--leaf, var(--gold));}
  @media (max-width:720px){.gs-board-cols{grid-template-columns:1fr;}}

  @media (prefers-reduced-motion: reduce){.brand h1{animation:none;}}
</style>
<?php if ($gs_panel === 'plantation'): ?>
<link rel="stylesheet" href="assets/plantation/plantation.css?v=<?= (int)@filemtime(__DIR__.'/assets/plantation/plantation.css') ?>">
<?php endif; ?>
<?php if ($gs_panel === 'telephone'): ?>
<link rel="stylesheet" href="assets/greenstand/telephone.css?v=<?= (int) @filemtime(__DIR__ . '/assets/greenstand/telephone.css') ?>">
<?php endif; ?>
</head>
<body>
<div class="wrap">

  <header>
    <div class="brand">
      <svg class="brand-leaf" viewBox="0 0 24 24" fill="none"><path d="M12 2C12 6 9 8 9 8s0-4 3-6zM12 2c0 4 3 6 3 6s0-4-3-6zM12 22c0-5-3.5-7-3.5-7s.5 5 3.5 7zm0 0c0-5 3.5-7 3.5-7s-.5 5-3.5 7zM3 12c4 0 6-3 6-3s-4 0-6 3zm18 0c-4 0-6-3-6-3s4 0 6 3zM4 9c4.5 1 5 5 5 5S4.5 13 4 9zm16 0c-4.5 1-5 5-5 5s4.5-1 5-5zM12 9a3 3 0 100 6 3 3 0 000-6z" fill="#4CAF3D"/></svg>
      <div><h1>GreenStand</h1><small>fais grandir ton stand</small></div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
      <button id="muteBtn" title="Activer/couper le son" style="all:unset;cursor:pointer;width:34px;height:34px;border-radius:9px;background:var(--surface);border:1px solid var(--line);display:flex;align-items:center;justify-content:center;font-size:15px;">🔊</button>
      <div class="lcd">
        <div class="amount" id="money">0.00 €</div>
        <div class="rate" id="rate">+0.00 €/s</div>
      </div>
    </div>
  </header>

  <div class="gs-sitebar">
    <a class="gs-back" href="jeux.php?view=caisses">&larr; Retour aux Jeux</a>
    <?php if ($me): ?>
    <div class="gs-wallet" id="gsWallet" aria-live="polite">
      <img class="gs-dt-coin" src="assets/jeton.png" alt="">
      <span><b id="gsDtBalance"><?= number_format($gs_dt_balance, 0, ',', ' ') ?></b> DT</span>
      <span class="gs-sync-note" id="gsSyncNote"></span>
    </div>
    <?php else: ?>
    <div class="gs-wallet gs-wallet-guest">
      <a href="index.php">Connecte-toi</a> pour convertir tes ventes en jetons DT
    </div>
    <?php endif; ?>
  </div>

  <div class="gs-layout">
    <nav class="gs-nav" aria-label="Sections du jeu">
      <?php
      // De vrais liens, pas des boutons : chaque section a son URL, donc son entrée
      // dans l'historique, son favori et son ouverture dans un nouvel onglet.
      $gs_liens = [['jeu', '&#127807;', 'Le stand', true]];
      if ($me) {
          $gs_liens[] = ['distributeur', '&#127974;', 'Distributeur', true];
          $gs_liens[] = ['telephone',    '&#128241;', 'T&eacute;l&eacute;phone',    true];
          $gs_liens[] = ['plantation', '&#127793;', 'Plantation', true];
          $gs_liens[] = ['boutique',     '&#128722;', 'Boutique',     true];
          $gs_liens[] = ['prestige',     '&#127809;', 'Franchise',    true];
      }
      $gs_liens[] = ['succes',     '&#127941;', 'Succ&egrave;s',     true];
      $gs_liens[] = ['classement', '&#127942;', 'Classements', true];
      if (is_admin_ip()) $gs_liens[] = ['admin', '&#128295;', 'Panel admin', false];
      foreach ($gs_liens as [$cle, $icone, $libelle, $normal]):
      ?>
      <?php /* La pastille rouge : un emplacement par entrée de menu, rempli par le
               JS (gsMajPastilles). Vide, elle ne s'affiche pas. */ ?>
      <a class="gs-nav-btn<?= $normal ? '' : ' gs-nav-admin' ?><?= $gs_panel === $cle ? ' is-active' : '' ?>"
         href="<?= htmlspecialchars($gs_panels_pages[$cle], ENT_QUOTES) ?>"
         <?= $gs_panel === $cle ? 'aria-current="page"' : '' ?>><span><?= $icone ?></span><?= $libelle ?><i
         class="gs-nav-pastille" data-pastille="<?= htmlspecialchars($cle, ENT_QUOTES) ?>" hidden></i></a>
      <?php endforeach; ?>
    </nav>

    <div class="gs-panels">
    <?php if ($gs_panel === 'plantation' && $me) require __DIR__.'/plantation_view.php'; ?>
    <?php if ($gs_panel === 'jeu'): ?>
    <section class="gs-panel is-active" data-panel="jeu">
  <div class="stand-card">
    <div class="stand-title">
      <h2>Ton stand</h2>
      <div class="lvl" id="lvlTag">Niveau 1</div>
    </div>
    <!-- Le choix du comptoir. Les stands verrouill&eacute;s restent visibles, avec le
         nombre de franchises qui les ouvre : on doit voir o&ugrave; l'on va. La liste
         est dessin&eacute;e par renderStands(), depuis GS_STANDS. -->
    <div class="gs-stands" id="gsStands"></div>
    <!-- Effets d'événement en cours (affluence, remise, stand fermé). Juste au-dessus
         de la scène : c'est là que le regard se pose en premier, pas en bas de page
         après les Succès — un joueur ne doit jamais avoir à scroller pour savoir
         combien de temps il lui reste à attendre. Masqué quand il n'y en a aucun. -->
    <div id="gsEffetsBandeau" style="display:none;margin:0 0 12px;padding:10px 14px;border-radius:11px;
         background:rgba(217,164,65,.14);border:1px solid rgba(217,164,65,.45);color:#F0D9A6;
         font-family:'JetBrains Mono',monospace;font-size:11.5px;line-height:1.5;"></div>
    <!-- La scène et la console du comptoir, côte à côte. La console passe sous
         la scène en dessous de 1000 px : à cette largeur, deux colonnes ne
         laissent plus assez de place au stand. -->
    <div class="stand-duo">
      <div class="scene-frame" id="sceneFrame">
        <div class="scene-hint">Clique sur le <b>nug</b> au centre pour vendre &mdash; le <b>&#9654;</b> de la console élargit le stand</div>
      </div>

      <aside class="gs-console" id="gsConsole">
        <div class="c-tete">
          <span class="c-led"></span>
          <span class="c-titre">COMPTOIR</span>
          <span class="c-attente" id="gsConsoleAttente">0 en attente</span>
          <button type="button" class="c-plier" id="gsConsolePlier"
                  title="Replier la console pour élargir le stand">&#9654;</button>
        </div>
        <!-- Prochain client : masqué tant que quelqu'un attend déjà au comptoir. -->
        <div class="c-prochain" id="gsProchainClient" style="display:none;"></div>
        <!-- Distribution automatique : sert les clients prêts sans clic, tant que c'est coché.
             Le réglage est purement local au navigateur, pas une donnée de partie. -->
        <label class="c-auto" for="gsAutoServe" title="Sert automatiquement les clients arrivés au comptoir, même si tu ne cliques pas.">
          <input type="checkbox" id="gsAutoServe"> Distribuer automatiquement
        </label>
        <div class="c-flux" id="gsConsoleFlux"></div>
      </aside>
    </div>
  </div>


  <div class="shop-head">
    <h2>Améliorations</h2>
    <div class="buy-qty" id="buyQtyToggle" style="display:flex;gap:6px;">
      <button data-qty="1" class="qty-btn">x1</button>
      <button data-qty="10" class="qty-btn">x10</button>
      <button data-qty="max" class="qty-btn">Max</button>
    </div>
  </div>
  <div class="shop-grid" id="shop"></div>

  <div class="shop-head" style="margin-top:26px;">
    <h2>Gérants</h2>
  </div>
  <p class="note" style="text-align:left;margin:0 0 12px;">Embauche du personnel pour booster durablement ton stand — leurs bonus se multiplient avec tes améliorations, au lieu de s'y ajouter.</p>
  <div class="shop-grid" id="managers"></div>

  <div class="ach-head"><h2>Succès</h2><span class="ach-count" id="achCount">0/12</span></div>
  <div class="ach-grid" id="achGrid"></div>

    </section>
    <?php endif; ?>

    <?php if ($gs_panel === 'distributeur'): ?>
    <section class="gs-panel is-active" data-panel="distributeur">
  <?php if ($me): ?>
  <div class="shop-head" style="margin-top:26px;">
    <h2>&#127974; Distributeur</h2>
  </div>
  <p class="note" style="text-align:left;margin:0 0 12px;">L'argent du stand ne se transforme plus tout seul. Il s'accumule ici, et c'est toi qui décides ce que tu en fais.</p>
  <?php $gs_atm = gs_atm_modele(); ?>
  <div class="gs-dist<?= $gs_atm ? ' has-atm' : '' ?>">
    <?php if ($gs_atm): /* Le vrai distributeur, en 3D. Sans modèle déposé, on garde la carte d'avant. */ ?>
    <div class="gs-atm">
      <div class="gs-atm-scene" id="gsAtmScene">
        <div class="gs-atm-chargement" id="gsAtmChargement">Chargement du distributeur&hellip;</div>
      </div>
      <div class="gs-atm-info">
        <span class="gs-dist-bank-label">En banque</span>
        <span class="gs-dist-bank-value" id="gsBank">0,00 &euro;</span>
        <span class="gs-dist-bank-note" id="gsBankNote">Vends au stand pour alimenter le distributeur.</span>
        <span class="gs-atm-aide" id="gsAtmAide">Touche l'&eacute;cran du distributeur pour retirer tes jetons &mdash; ou sers-toi des cartes plus bas.</span>
      </div>
      <?php if (!empty($gs_atm['credit'])): /* CC-BY : la mention est obligatoire, discrète suffit. */ ?>
      <p class="gs-atm-credit">Mod&egrave;le 3D : <?= htmlspecialchars($gs_atm['credit']) ?></p>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="gs-dist-bank">
      <span class="gs-dist-bank-label">En banque</span>
      <span class="gs-dist-bank-value" id="gsBank">0,00 &euro;</span>
      <span class="gs-dist-bank-note" id="gsBankNote">Vends au stand pour alimenter le distributeur.</span>
    </div>
    <?php endif; ?>
    <div class="gs-dist-grid" id="gsDistGrid"></div>
  </div>
  <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($gs_panel === 'succes'): ?>
    <section class="gs-panel is-active" data-panel="succes">
      <div class="shop-head"><h2>&#127941; Succ&egrave;s</h2><span class="ach-count" id="gsSuccesTotal">0</span></div>
      <p class="note" style="text-align:left;margin:0 0 12px;">
        Mille paliers, r&eacute;partis en familles : chaque vente, chaque client servi, chaque niveau
        de stand en fait avancer un. Ceux du haut se d&eacute;crochent en une minute, ceux du bas
        ne seront peut-&ecirc;tre jamais atteints &mdash; c'est fait pour.
      </p>
      <div class="gs-adm-filter" style="margin-bottom:14px;">
        <input type="search" id="gsSuccesRecherche" placeholder="Chercher un succès…">
        <label><input type="radio" name="gsSuccesVue" value="tous" checked> Tous</label>
        <label><input type="radio" name="gsSuccesVue" value="faits"> D&eacute;bloqu&eacute;s</label>
        <label><input type="radio" name="gsSuccesVue" value="restants"> &Agrave; venir</label>
        <span class="gs-admin-meta" id="gsSuccesCompte"></span>
      </div>
      <div id="gsSuccesListe"></div>
    </section>
    <?php endif; ?>

    <?php if ($gs_panel === 'telephone'): ?>
    <section class="gs-panel is-active" data-panel="telephone">
      <?php if ($me): ?>
      <header class="tel-intro">
        <div><span class="tel-eyebrow">GreenStand / Le réseau</span>
          <h2>Le quartier au bout du fil.</h2>
          <p>Fais le plein chez tes contacts, prends les appels et livre tes clients.
          Chaque livraison fait grandir ton réseau.</p></div>
        <span class="tel-intro-badge">Ligne personnelle</span>
      </header>
      <div id="gsTelResultat" role="status" aria-live="polite"></div>
      <div class="gs-tel">
        <div class="gs-tel-carte">
          <div class="gs-tel-tete"><span>Ton quartier</span><span class="gs-tel-quota" id="gsTelQuota"></span></div>
          <div id="gsTelMap" aria-label="Carte des livraisons"></div>
          <div class="tel-map-key" aria-hidden="true"><span><i></i>Ton stand</span><span><i class="client"></i>Client</span><span><i class="route"></i>Itinéraire</span></div>
          <div class="gs-tel-legende" id="gsTelLegende" aria-live="polite"></div>
        </div>
        <aside class="gs-tel-cote" aria-label="Téléphone personnel">
          <div class="tel-screen">
            <div class="tel-statusbar" aria-hidden="true"><span>GS MOBILE</span><span class="tel-island"></span><span>▂▄▆ ▰</span></div>
            <div class="tel-screen-head"><div><span class="tel-eyebrow">Ta ligne directe</span><h3>Téléphone</h3></div>
              <span class="tel-phone-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3H4a1 1 0 0 0-1 1c0 9 8 17 17 17a1 1 0 0 0 1-1v-3l-5-2-2 2c-3-1-6-4-7-7l2-2-2-5Z"/></svg></span>
            </div>
            <div class="tel-wallet">
              <div class="gs-tel-stock"><span class="s-label">Marchandise</span><span class="s-valeur" id="gsTelStock">—</span><span class="s-note" id="gsTelStockNote"></span></div>
              <div class="gs-tel-stock"><span class="s-label">En banque</span><span class="s-valeur" id="gsTelBank">—</span><span class="s-note" id="gsTelPrix"></span></div>
            </div>
            <div class="tel-inbox-title"><span>Appels & commandes</span><span id="gsTelCount"></span></div>
            <div id="gsTelCommandes"><p class="gs-board-empty">Connexion au réseau…</p></div>
            <div class="tel-homebar" aria-hidden="true"></div>
          </div>
        </aside>
      </div>
      <div class="tel-contacts-head"><div><span class="tel-eyebrow">Les bonnes connexions</span><h2>Ton répertoire</h2></div><p>De nouveaux contacts se débloquent quand ton stand monte de niveau.</p></div>
      <div class="shop-grid" id="gsTelContacts"></div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($gs_panel === 'boutique'): ?>
    <section class="gs-panel is-active" data-panel="boutique">
      <?php if ($me): ?>
      <div class="shop-head"><h2>&#128722; Boutique</h2></div>
      <p class="note" style="text-align:left;margin:0 0 12px;">
        Tout se paie avec les <b>euros de ta banque</b> &mdash; ceux que le stand a d&eacute;pos&eacute;s, avant
        de passer au Distributeur. Les prix suivent ta production : un article co&ucirc;te un certain
        nombre de <b>secondes de stand</b>, il reste donc au m&ecirc;me niveau d'effort du d&eacute;but &agrave; la fin.
        Chaque article a une <b>limite par jour</b>, remise &agrave; z&eacute;ro &agrave; minuit.
      </p>
      <div class="gs-dist-bank" style="margin:0 0 14px;">
        <span class="gs-dist-bank-label">En banque</span>
        <span class="gs-dist-bank-value" id="gsBoutiqueBank">0,00 &euro;</span>
        <span class="gs-dist-bank-note" id="gsBoutiqueNote">Vends au stand pour remplir la caisse.</span>
      </div>
      <div id="gsCaisseResultat"></div>
      <div class="shop-grid" id="gsBoutiqueGrid"></div>
      <div id="gsCaisseChances"></div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($gs_panel === 'prestige'): ?>
    <section class="gs-panel is-active" data-panel="prestige">
      <?php if ($me): ?>
      <div class="shop-head"><h2>&#127809; Ouvrir une franchise</h2></div>
      <p class="note" style="text-align:left;margin:0 0 12px;">Tu sacrifies l'argent et les niveaux de ton stand. Tu gardes tes jetons, tes gemmes et tes cl&eacute;s. Chaque franchise te rapporte <b>une Feuille d'Or</b> &mdash; le troph&eacute;e qui te classe au &laquo;&nbsp;Parrain de la Weed&nbsp;&raquo;, elle ne se d&eacute;pense jamais &mdash; et des <b>Feuilles de Platine</b>, la monnaie qui ach&egrave;te les bonus permanents. Chaque franchise co&ucirc;te plus cher que la pr&eacute;c&eacute;dente.</p>
      <p class="note" style="text-align:left;margin:0 0 12px;">Les franchises ouvrent aussi de <b>nouveaux stands</b>, entre lesquels tu choisis librement depuis la page du stand :
      <?php $gs_verrous = array_slice(greenstand_stands(), 1); ?>
      <?php foreach ($gs_verrous as $gs_i => $gs_st): ?>
        <b><?= htmlspecialchars($gs_st['emoji'] . ' ' . $gs_st['name']) ?></b> &agrave; <?= (int) $gs_st['franchises'] ?> franchises<?= $gs_i === count($gs_verrous) - 1 ? '.' : ',' ?>
      <?php endforeach; ?>
      Aucun n'est meilleur que les autres partout : l'un paie au clic, l'autre en automatique, le dernier tr&egrave;s fort mais devant un comptoir presque vide. Tes niveaux, eux, te suivent d'un stand &agrave; l'autre.</p>

      <div class="gs-franchise">
        <div class="gs-fr-left">
          <span class="gs-fr-label">Feuilles d'Or &#127809;</span>
          <span class="gs-fr-value" id="gsGold">0</span>
          <span class="gs-fr-note" id="gsGoldNote">Franchises ouvertes : 0</span>
        </div>
        <div class="gs-fr-left">
          <span class="gs-fr-label">Feuilles de Platine &#128142;</span>
          <span class="gs-fr-value" id="gsPlatine">0</span>
          <span class="gs-fr-note" id="gsPlatineNote">0 gagn&eacute;e(s) en tout</span>
        </div>
        <div class="gs-fr-right">
          <p class="gs-fr-hint" id="gsFrHint">Il te faut au moins 1 000 000 € en caisse.</p>
          <button type="button" class="gs-fr-btn" id="gsFrBtn" disabled>Ouvrir une franchise &#127809;</button>
        </div>
      </div>

      <div class="shop-head" style="margin-top:26px;"><h2>Bonus permanents</h2></div>
      <p class="note" style="text-align:left;margin:0 0 12px;">Achet&eacute;s en Feuilles de Platine &#128142;. Ils survivent &agrave; toutes les franchises suivantes.</p>
      <div class="shop-grid" id="gsBoosts"></div>
      <?php else: ?>
      <p class="note" style="text-align:left;">Connecte-toi pour ouvrir une franchise.</p>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($gs_panel === 'classement'): ?>
    <section class="gs-panel is-active" data-panel="classement">
      <div class="shop-head"><h2>&#127942; Classements</h2></div>
      <p class="note" style="text-align:left;margin:0 0 14px;">Cinq façons de jouer, cinq façons d'être premier. La page se met à jour toute seule toutes les 30 secondes &mdash; inutile d'actualiser.</p>
      <div id="gsPodium" style="display:none;"></div>
      <div class="gs-boards" id="gsBoards"></div>
    </section>
    <?php endif; ?>

    <?php if (is_admin_ip()): ?>
    <?php if ($gs_panel === 'admin'): ?>
    <section class="gs-panel is-active" data-panel="admin">
      <div class="shop-head"><h2>&#128295; Panel admin</h2></div>

      <!-- Une tâche par onglet. L'onglet actif est retenu d'une visite à l'autre. -->
      <nav class="gs-adm-nav" id="gsAdmNav">
        <button type="button" class="gs-adm-tab is-active" data-adm="badges">&#127941; Badges &amp; succ&egrave;s</button>
        <?php /* Un onglet d'images PAR STAND : quatre comptoirs, c'est plus de cent
                 soixante vignettes, et une seule grille devenait illisible. */ ?>
        <?php foreach (greenstand_stands() as $gs_st): ?>
        <button type="button" class="gs-adm-tab" data-adm="images_<?= htmlspecialchars($gs_st['id']) ?>">
          <?= htmlspecialchars($gs_st['emoji']) ?> <?= htmlspecialchars($gs_st['name']) ?>
        </button>
        <?php endforeach; ?>
        <button type="button" class="gs-adm-tab" data-adm="feuilles">&#127809; Feuilles</button>
        <button type="button" class="gs-adm-tab" data-adm="surveillance">&#128737;&#65039; Surveillance</button>
        <button type="button" class="gs-adm-tab" data-adm="sauvegardes">&#128190; Sauvegardes</button>
        <button type="button" class="gs-adm-tab" data-adm="aide">&#10067; Comment faire</button>
      </nav>

      <!-- ================= ONGLET BADGES ================= -->
      <div class="gs-adm-page" data-adm-page="badges">
        <p class="gs-adm-intro">
          Un badge = une <b>condition</b> (une statistique et un seuil) + une <b>r&eacute;compense</b>.
          Remplis le formulaire et clique sur <b>Enregistrer</b> : un identifiant nouveau cr&eacute;e un badge,
          un identifiant existant le remplace. Pour changer l'image d'un badge d&eacute;j&agrave; cr&eacute;&eacute;,
          <b>clique directement sur sa vignette</b> dans la liste du dessous.
        </p>

        <div class="gs-admin-cat" id="gsBadgeFormTitre">Nouveau badge</div>
        <form class="gs-badge-form" id="gsBadgeForm" autocomplete="off">
          <label>Identifiant<input name="id" placeholder="mon_badge" required></label>
          <label>Nom<input name="name" placeholder="Nom affiché" required></label>
          <label>Pictogramme<input name="emoji" placeholder="&#127942;" maxlength="8"></label>
          <label>Description<input name="desc" placeholder="Ce qu'il faut faire"></label>
          <label>Condition<select name="stat" id="gsBadgeStat"></select></label>
          <label>Seuil<input name="value" type="number" min="0" step="any" value="1" required></label>
          <label>R&eacute;compense &euro;<input name="cash" type="number" min="0" step="any" value="0"></label>
          <label>Bonus permanent<input name="mult" type="number" min="0" max="1" step="0.01" value="0" title="0,05 = +5% sur tous les gains"></label>

          <!-- L'image se choisit ICI, en même temps que le reste : elle part
               automatiquement juste après l'enregistrement du badge. -->
          <label class="champ-image">Image du badge (facultative)
            <span class="zone">
              <span class="apercu" id="gsBadgeApercu">&#127942;</span>
              <span class="aide">
                webp, png, jpg ou gif &mdash; 3&nbsp;Mo maximum, converti en webp tout seul.
                Sans image, le jeu affiche le pictogramme. L'image s'affiche une fois le badge d&eacute;bloqu&eacute;.
                <br><input type="file" id="gsBadgeImage" accept="image/webp,image/png,image/jpeg,image/gif" style="margin-top:6px;">
              </span>
            </span>
          </label>

          <button type="submit" id="gsBadgeSubmit">Enregistrer</button>
          <button type="button" class="secondaire" id="gsBadgeNouveau">Vider le formulaire</button>
        </form>

        <div class="gs-admin-cat">Les badges existants &mdash; clique sur une vignette pour changer son image</div>
        <div class="gs-badge-list" id="gsBadgeList"></div>
      </div>

      <!-- ================= ONGLETS IMAGES — UN PAR STAND ================= -->
      <?php foreach (greenstand_stands() as $gs_st): $gs_id = htmlspecialchars($gs_st['id']); ?>
      <div class="gs-adm-page" data-adm-page="images_<?= $gs_id ?>" hidden>
        <p class="gs-adm-intro">
          Les images du <b><?= htmlspecialchars($gs_st['name']) ?></b>, rang&eacute;es par cat&eacute;gorie.
          Le nom d'un visuel est <b>impos&eacute; par le jeu</b> : on ne peut pas en cr&eacute;er un qu'il ne
          saurait pas afficher, seulement remplacer ceux de la liste. Formats&nbsp;: webp, png, jpg, gif
          &mdash; 3&nbsp;Mo maximum, convertis en webp automatiquement.
          <?php if ($gs_st['prefix'] !== ''): ?>
          <br><br>Les visuels de ce stand commencent tous par <code><?= htmlspecialchars($gs_st['prefix']) ?></code>.
          <b>Tant qu'une image manque ici, le jeu affiche celle du GreenStand &agrave; la place</b> : tu peux
          d&eacute;bloquer le stand et le remplir tranquillement, image par image, sans jamais casser la sc&egrave;ne.
          Ce stand s'ouvre &agrave; <b><?= (int) $gs_st['franchises'] ?> franchises</b>.
          <?php else: ?>
          <br><br>Ce sont les visuels <b>d'origine</b>, sans pr&eacute;fixe : ils servent aussi de <b>repli</b>
          aux autres stands tant que leurs propres images n'ont pas &eacute;t&eacute; envoy&eacute;es. Les badges,
          les g&eacute;rants et les bonus de franchise sont ici : ils sont les m&ecirc;mes sur les quatre comptoirs.
          <?php endif; ?>
          <br><br>Le rayon <b>Paliers</b> contient les mod&egrave;les qui remplacent un objet quand le joueur
          atteint le niveau <b>10</b> (<code>_t2</code>), <b>25</b> (<code>_t3</code>) et <b>50</b>
          (<code>_t4</code>). Aucune n'est obligatoire : sans image, l'objet garde le mod&egrave;le du
          palier pr&eacute;c&eacute;dent<?php if ($gs_st['prefix'] !== ''): ?> <b>de ce stand</b> &mdash; le jeu ne
          va chercher le visuel du GreenStand que si ce stand n'a <b>aucune</b> image pour cet objet,
          jamais pour lui pr&eacute;f&eacute;rer un palier plus haut d'un autre comptoir<?php endif; ?>.
        </p>
        <!-- Les noms des améliorations SUR CE STAND. Sans ça, un stand tout neuf
             affiche les noms du GreenStand (« BazeKush » au WhiteStand) tant que
             personne n'a écrit les siens. -->
        <details class="gs-stand-noms" data-stand="<?= $gs_id ?>">
          <summary>&#9998; Noms des am&eacute;liorations sur ce stand</summary>
          <p class="gs-adm-intro" style="margin:10px 0 12px;">
            Laisse un champ <b>vide</b> pour garder le nom d'origine
            <?php if ($gs_st['prefix'] !== ''): ?>(celui du GreenStand, affich&eacute; en gris)<?php endif; ?>.
            Ça ne change que l'affichage : les niveaux, les prix et les gains d'une am&eacute;lioration
            ne bougent pas, et les joueurs ne perdent rien.
          </p>
          <div class="gs-noms-grille" id="gsNoms_<?= $gs_id ?>"></div>
          <div class="gs-adm-filter" style="margin-top:10px;">
            <button type="button" class="gs-adm-btn gs-noms-save" data-stand="<?= $gs_id ?>">Enregistrer les noms</button>
            <span class="gs-admin-meta" id="gsNomsEtat_<?= $gs_id ?>"></span>
          </div>
        </details>

        <div class="gs-adm-filter">
          <input type="search" class="gs-asset-search" data-stand="<?= $gs_id ?>" placeholder="Chercher un visuel (décor, amélioration, palier…)">
          <label><input type="checkbox" class="gs-asset-vides" data-stand="<?= $gs_id ?>"> Seulement celles qui manquent</label>
          <span class="gs-admin-meta" id="gsAssetCompte_<?= $gs_id ?>"></span>
        </div>
        <div id="gsAdminAssets_<?= $gs_id ?>"></div>
      </div>
      <?php endforeach; ?>

      <!-- ================= ONGLET FEUILLES ================= -->
      <div class="gs-adm-page" data-adm-page="feuilles" hidden>
        <p class="gs-adm-intro">
          Deux monnaies bien s&eacute;par&eacute;es : les <b>Feuilles d'Or</b> &#127809; sont le troph&eacute;e du
          classement &laquo;&nbsp;Parrain de la Weed&nbsp;&raquo; (une par franchise, jamais d&eacute;pens&eacute;e),
          les <b>Feuilles de Platine</b> &#128142; ach&egrave;tent les bonus permanents.
          Un nombre n&eacute;gatif retire, sans jamais descendre sous z&eacute;ro ni toucher aux boosts d&eacute;j&agrave; achet&eacute;s.
        </p>
        <?php if ($me): $gs_moi = greenstand_gold_state((string) $me['username']); ?>
        <p class="note" style="text-align:left;margin:0 0 10px;">
          Compte connect&eacute; : <b><?= h($me['username']) ?></b> &mdash; lu &agrave; l'instant en base :
          <b><?= (int) $gs_moi['gold'] ?></b> Feuille(s) d'Or &#127809;,
          <b><?= (int) $gs_moi['platine'] ?></b> de Platine &#128142; (<?= (int) $gs_moi['platine_life'] ?> gagn&eacute;es en tout),
          <b><?= (int) $gs_moi['franchises'] ?></b> franchise(s).
          C'est exactement ce que l'onglet Franchise doit afficher : si tu y vois autre chose, c'est un probl&egrave;me d'affichage, pas de compte.
        </p>
        <?php endif; ?>
        <form class="gs-badge-form" id="gsGoldForm" autocomplete="off">
          <label>Joueur<input name="username" id="gsGoldPlayer" list="gsGoldPlayers" placeholder="pseudo" required></label>
          <label>Monnaie<select name="devise" id="gsGoldDevise">
            <option value="platine">Feuilles de Platine &#128142; (bonus)</option>
            <option value="or">Feuilles d'Or &#127809; (classement)</option>
          </select></label>
          <label>Combien<input name="amount" type="number" step="1" value="1" required></label>
          <button type="submit" id="gsGoldSubmit">Envoyer</button>
        </form>
        <datalist id="gsGoldPlayers"></datalist>
        <div class="gs-badge-list" id="gsGoldPlayerList"></div>

        <!-- Versement d'un podium. Masqué sauf pour le propriétaire. -->
        <div id="gsPodiumBloc" style="display:none;">
          <div class="gs-admin-cat">Podium du mois &mdash; verser les lots</div>
          <p class="gs-adm-intro">
            À utiliser quand un mois s'est cl&ocirc;tur&eacute; sans que les lots soient vers&eacute;s.
            Les cl&ocirc;tures suivantes se font toutes seules, le 1er du mois.
            <b>1<sup>er</sup></b> : 500&nbsp;000&nbsp;&euro; + 3&nbsp;&#128142; &middot;
            <b>2<sup>e</sup></b> : 350&nbsp;000&nbsp;&euro; + 2&nbsp;&#128142; &middot;
            <b>3<sup>e</sup></b> : 250&nbsp;000&nbsp;&euro; + 1&nbsp;&#128142;.
            <br><br>Le <b>point de Hall of Fame</b> n'est pas donn&eacute; par d&eacute;faut : l'ancienne
            cl&ocirc;ture le distribuait d&eacute;j&agrave; au meilleur vendeur, le rattraper le compterait
            deux fois. Ne coche la case que si tu as v&eacute;rifi&eacute; que le premier ne l'a pas re&ccedil;u.
          </p>
          <form class="gs-badge-form" id="gsPodiumForm" autocomplete="off">
            <label>1<sup>er</sup><input name="p1" list="gsGoldPlayers" placeholder="pseudo"></label>
            <label>2<sup>e</sup><input name="p2" list="gsGoldPlayers" placeholder="pseudo"></label>
            <label>3<sup>e</sup><input name="p3" list="gsGoldPlayers" placeholder="pseudo"></label>
            <label class="champ-image" style="grid-column:1 / -1;">
              <span class="zone" style="padding:8px 11px;">
                <span class="aide">
                  <input type="checkbox" id="gsPodiumHall"> Donner aussi le point de Hall of Fame au premier
                </span>
              </span>
            </label>
            <button type="submit" id="gsPodiumApercu">Aper&ccedil;u</button>
            <button type="button" class="secondaire" id="gsPodiumVerser" disabled>Verser pour de bon</button>
          </form>
          <div id="gsPodiumResultat"></div>
        </div>
      </div>

      <!-- ================= ONGLET SURVEILLANCE ================= -->
      <div class="gs-adm-page" data-adm-page="surveillance" hidden>
        <p class="gs-adm-intro">
          Les joueurs <b>en train de jouer</b>, plus tous ceux qui tra&icirc;nent un avertissement.
          La liste se rafra&icirc;chit toute seule toutes les trois secondes : une cadence
          &laquo;&nbsp;<b>&#9679; en direct</b>&nbsp;&raquo; date de moins de quinze secondes, c'est ce que la
          personne fait en ce moment. Au premier avertissement le jeu se fige derri&egrave;re un menu
          de v&eacute;rification, au second l'acc&egrave;s au stand est <b>suspendu dix minutes</b>.
          Si quelqu'un a &eacute;t&eacute; pris &agrave; tort, <b>L&egrave;ve la sanction</b> :
          les avertissements repartent de z&eacute;ro imm&eacute;diatement.
        </p>
        <!-- Interrupteur général : masqué sauf pour les comptes autorisés. -->
        <div id="gsAntiCheatBloc" style="display:none;" class="gs-adm-switch">
          <div class="s-txt">
            <div class="s-titre">Protection anti auto-clicker</div>
            <div class="s-note" id="gsAntiCheatNote"></div>
          </div>
          <button type="button" class="gs-adm-btn" id="gsAntiCheatBtn">…</button>
        </div>

        <div class="gs-adm-filter">
          <button type="button" class="gs-adm-btn" id="gsWatchRefresh">Actualiser</button>
          <span class="gs-admin-meta" id="gsWatchCompte"></span>
        </div>
        <div id="gsWatchList"></div>
      </div>

      <!-- ================= ONGLET SAUVEGARDES ================= -->
      <div class="gs-adm-page" data-adm-page="sauvegardes" hidden>
        <p class="gs-adm-intro">
          Le jeu garde <b>cinq clich&eacute;s espac&eacute;s</b> de la progression de chaque joueur (un toutes les
          dix minutes de jeu, plus un juste avant chaque remise &agrave; z&eacute;ro). De quoi rendre une
          progression perdue au lieu de r&eacute;pondre &laquo;&nbsp;c'est perdu&nbsp;&raquo;.
          Restaurer &eacute;crase la partie en cours du joueur &mdash; qui part elle-m&ecirc;me dans l'historique,
          donc une restauration rat&eacute;e se rattrape.
        </p>
        <div class="gs-adm-filter">
          <input type="search" id="gsSaveJoueur" list="gsGoldPlayers" placeholder="Pseudo du joueur">
          <button type="button" class="gs-adm-btn" id="gsSaveVoir">Voir ses clich&eacute;s</button>
        </div>
        <div id="gsSaveList"></div>
      </div>

      <!-- ================= ONGLET AIDE ================= -->
      <div class="gs-adm-page" data-adm-page="aide" hidden>
        <p class="gs-adm-intro">Trois choses se g&egrave;rent depuis ce panel, et rien d'autre ne demande de toucher au code.</p>
        <div class="gs-adm-steps">
          <div class="gs-adm-step">
            <h4>1 &middot; Cr&eacute;er un badge</h4>
            Onglet <b>Badges &amp; succ&egrave;s</b>. Un <b>identifiant</b> en minuscules sans espace
            (<code>vendeur_pro</code>), un <b>nom</b>, une <b>condition</b> et un <b>seuil</b>.
            Choisis l'image tout de suite si tu en as une, puis <b>Enregistrer</b>. Le badge appara&icirc;t
            dans la liste et dans le jeu imm&eacute;diatement.
          </div>
          <div class="gs-adm-step">
            <h4>2 &middot; Modifier un badge</h4>
            Bouton <b>Modifier</b> sur sa ligne : le formulaire se remplit tout seul.
            Change ce que tu veux, <b>Enregistrer</b>. Comme l'identifiant est le m&ecirc;me,
            l'ancien badge est remplac&eacute; &mdash; il ne s'en cr&eacute;e pas un deuxi&egrave;me.
          </div>
          <div class="gs-adm-step">
            <h4>3 &middot; Changer l'image d'un badge</h4>
            <b>Clique sur la vignette</b> &agrave; gauche de son nom, choisis un fichier, c'est envoy&eacute;.
            Rien d'autre &agrave; faire. Pour l'enlever : bouton <b>Image&nbsp;&times;</b> sur la m&ecirc;me ligne.
            Le jeu revient alors au pictogramme.
          </div>
          <div class="gs-adm-step">
            <h4>4 &middot; Les autres images</h4>
            <b>Un onglet par stand</b> : GreenStand, BrownStand, BeigeStand, WhiteStand.
            Chacun contient son d&eacute;cor, ses am&eacute;liorations et ses paliers ; les badges, les
            g&eacute;rants et les bonus de franchise sont dans le GreenStand, ils servent partout.
            Coche <b>Seulement celles qui manquent</b> pour voir d'un coup ce qui n'a pas encore de visuel.
            <b>Ajouter</b> / <b>Remplacer</b> sur chaque vignette.
          </div>
          <div class="gs-adm-step">
            <h4>4 ter &middot; Renommer les am&eacute;liorations d'un stand</h4>
            En haut de l'onglet d'un stand : <b>Noms des am&eacute;liorations sur ce stand</b>.
            Un champ par am&eacute;lioration (le nom, puis la description en dessous).
            <b>Laisse vide pour garder le nom d'origine</b> &mdash; il s'affiche en gris dans le champ.
            C'est ce qui sert quand un stand affiche encore &laquo;&nbsp;BazeKush&nbsp;&raquo; au lieu du produit
            qu'il vend vraiment. Ça ne change que le texte : les niveaux, les prix et les gains
            ne bougent pas, et <b>personne ne perd sa progression</b>.
          </div>
          <div class="gs-adm-step">
            <h4>4 bis &middot; Remplir un nouveau stand</h4>
            Rien n'est urgent : <b>tant qu'une image manque, le jeu affiche celle du GreenStand</b>.
            Tu peux donc ouvrir le BrownStand aujourd'hui et dessiner ses visuels un par un, sans
            jamais casser la sc&egrave;ne d'un joueur. Les noms sont impos&eacute;s et tous pr&eacute;fix&eacute;s
            (<code>brown_banner</code>, <code>brown_bag</code>, <code>white_nug_big</code>&hellip;) :
            l'onglet du stand te les donne d&eacute;j&agrave; tout faits, il n'y a qu'&agrave; poser l'image en face.
            Commence par <code>banner</code> (l'enseigne), <code>nug_big</code> (le produit qu'on clique)
            et <code>client</code> : ce sont les trois qu'on voit en premier.
          </div>
          <div class="gs-adm-step">
            <h4>Bon &agrave; savoir</h4>
            Les images sont converties en <code>webp</code> et rang&eacute;es dans
            <code>assets/greenstand/</code>. Une image de badge s'appelle toujours
            <code>badge_&lt;identifiant&gt;</code> : c'est pour &ccedil;a qu'elle appara&icirc;t aussi dans
            l'onglet Images. Carr&eacute;e et d&eacute;tour&eacute;e (fond transparent), c'est plus joli.
          </div>
          <div class="gs-adm-step">
            <h4>5 &middot; Quelqu'un triche&nbsp;?</h4>
            Onglet <b>Surveillance</b> : les comptes dont la cadence de clic d&eacute;passe l'humainement
            possible, avec le nombre d'avertissements et le temps de sanction restant. Rien &agrave; faire
            pour sanctionner, c'est automatique. Le bouton sert &agrave; <b>lever</b> une sanction injuste.
          </div>
          <div class="gs-adm-step">
            <h4>6 &middot; Un joueur a perdu sa partie</h4>
            Onglet <b>Sauvegardes</b> : tape son pseudo, choisis un clich&eacute; dans la liste
            (chacun montre l'argent, le &euro;/s et les ventes qu'il contient, pour reconna&icirc;tre le bon)
            et clique sur <b>Restaurer</b>. La partie en cours est mise de c&ocirc;t&eacute; avant d'&ecirc;tre &eacute;cras&eacute;e.
          </div>
          <div class="gs-adm-step">
            <h4>Ce que voient les joueurs</h4>
            Un badge verrouill&eacute; s'affiche avec un cadenas. Une fois d&eacute;bloqu&eacute;, il montre
            <b>son image si elle existe</b>, son pictogramme sinon. La <b>r&eacute;compense &euro;</b> est vers&eacute;e une
            fois ; le <b>bonus permanent</b> (0,05 = +5&nbsp;%) s'applique &agrave; tous les gains, pour toujours.
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>
    <?php endif; ?>
    </div>
  </div>


  <!-- Objectifs du jour. Masqué tant que le serveur n'en a pas envoyé. -->
  <div id="gsDailyBloc" style="display:none;margin:0 0 12px;">
    <div class="gs-admin-cat" style="margin:0 0 7px;">Objectifs du jour</div>
    <div class="gs-admin-meta" id="gsDailyInfo" style="margin-bottom:8px;"></div>
    <div id="gsDailyListe"></div>
  </div>

  <!-- Avertissement anti auto-clicker : masqué tant que le serveur n'a rien détecté. -->
  <div id="gsAntiClicBanner" style="display:none;margin:0 0 12px;padding:11px 14px;border-radius:11px;
       background:rgba(120,30,30,.22);border:1px solid #8B3A2A;color:#FFD3C4;
       font-family:'JetBrains Mono',monospace;font-size:12px;line-height:1.5;"></div>

  <div class="stat-bar">
    <span>Total gagné : <b id="statTotal">0.00 €</b></span>
    <span>Ventes manuelles : <b id="statClicks">0</b></span>
    <span>Objets achetés : <b id="statItems">0</b></span>
    <span>Clients servis : <b id="statClients">0</b> <span style="opacity:.6;">(<b id="statClientsLost">0</b> perdus)</span></span>
    <span><button id="resetBtn" style="all:unset;cursor:pointer;color:#8FA089;text-decoration:underline;">réinitialiser</button></span>
  </div>

  <p class="note">GreenStand CMS v0.1 | Anti-Cheat v0.1 realesed by m0onnnzy</p>
</div>

<script>
  // Quelle section est affichée. Le HTML des autres n'est pas envoyé : tout ce qui
  // écrit dedans doit donc vérifier que son élément existe (les fonctions de rendu
  // le font toutes) — et la scène 3D n'est construite que pour le stand.
  const GS_PANEL = <?= json_encode($gs_panel) ?>;
  const GS_USERNAME = <?= json_encode((string) ($me['username'] ?? '')) ?>;
  const GS_LOGGED_IN = <?= $me ? 'true' : 'false' ?>;
  const GS_CSRF = <?= json_encode(csrf_token()) ?>;
  // Portefeuille et banque connus dès le rendu : sans ça, l'affichage retomberait à 0
  // le temps que la première réponse du serveur arrive.
  const GS_WALLET0 = <?= json_encode($me ? user_wallet($me) : ['jetons'=>0,'gemmes'=>0,'cles'=>0]) ?>;
  const GS_BANK0   = <?= json_encode($me ? round(greenstand_bank((string) $me['username']), 2) : 0) ?>;
  // Les feuilles, elles aussi, connues dès le rendu de la page. Sans ça, l'onglet
  // Franchise affichait les zéros du JS tant que state_load n'avait pas répondu — et
  // DÉFINITIVEMENT ces zéros si cet appel échouait, alors que la base, elle, avait les
  // bonnes valeurs (c'est ce qui faisait « 1 feuille au classement, rien en réserve »).
  const GS_GOLD0   = <?= json_encode($me ? greenstand_gold_state((string) $me['username']) : null) ?>;
  const GS_RATES0  = <?= json_encode(GREENSTAND_RATES) ?>;
  // 0 côté serveur = aucun plafond par échange : on convertit tout d'un coup.
  const GS_EXCHANGE_MAX = <?= (int) GREENSTAND_EXCHANGE_QTY_CAP ?: 'Infinity' ?>;
  // Ce que chaque colonne de monnaie peut contenir, LU en base (elles sont élargies
  // en BIGINT UNSIGNED au premier chargement) : c'est la seule limite qui reste.
  const GS_CURRENCY_MAX = <?= json_encode(gs_currency_maxes()) ?>;
  const GS_PRESTIGE_K   = <?= (float) GREENSTAND_PRESTIGE_K ?>;
  const GS_PRESTIGE_EXP = <?= (float) GREENSTAND_PRESTIGE_EXP ?>;
  const GS_RESET_GEN    = <?= (int) GREENSTAND_RESET_GEN ?>;
  const GS_WIPE_GEN     = <?= (int) GREENSTAND_WIPE_GEN ?>;
  const GS_BOOSTS       = <?= json_encode(greenstand_boosts(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const GS_GOLD_SEUIL   = <?= (int) GREENSTAND_GOLD_SEUIL ?>;
  // De combien le prix d'une franchise est multiplié à chaque ouverture.
  const GS_FRANCHISE_COST_MULT = <?= (float) GREENSTAND_FRANCHISE_COST_MULT ?>;
  const GS_IS_ADMIN     = <?= is_admin_ip() ? 'true' : 'false' ?>;
  // Ce poste est-il dispensé du contrôle anti auto-clicker ? La liste des IP
  // dispensées est côté serveur (GREENSTAND_AUTOCLICK_EXEMPT_IPS) : la page ne
  // fait que refléter la décision, elle ne la prend pas.
  const GS_ANTICLIC_EXEMPT = <?= greenstand_autoclick_exempt() ? 'true' : 'false' ?>;
  // Ce compte peut-il manoeuvrer l'interrupteur général ? Le serveur décide ; la
  // page ne fait que masquer un bouton, l'action est de toute façon revérifiée.
  const GS_ANTICHEAT_OWNER = <?= ($me && greenstand_anticheat_owner((string) $me['username'])) ? 'true' : 'false' ?>;
  const GS_ANTICHEAT_ACTIF = <?= greenstand_anticheat_actif() ? 'true' : 'false' ?>;
  // Les lots du podium viennent de GREENSTAND_PODIUM, la constante que la clôture
  // utilise pour PAYER. Recopier les montants dans un texte les ferait diverger
  // le jour où tu changes le barème.
  const GS_PODIUM_LOTS = <?= json_encode(GREENSTAND_PODIUM) ?>;
  const GS_HUMAN_CPS       = <?= (int) GREENSTAND_HUMAN_CPS ?>;
  // Les quatre stands (GreenStand, BrownStand, BeigeStand, WhiteStand) : seuils de
  // franchises, multiplicateurs, palettes, produits et phrases de clients. Définis
  // UNE fois côté serveur (greenstand_stands()), parce que c'est aussi cette liste
  // qui borne les gains à la synchro — la page ne fait que l'appliquer.
  // `const` sur un tableau n'empêche pas d'en changer le CONTENU : le panel admin
  // remplace ses éléments après un enregistrement de noms, sans recharger la page.
  // Le modèle 3D du distributeur, s'il a été déposé (voir gs_atm_modele).
  const GS_ATM          = <?= json_encode(gs_atm_modele(), JSON_UNESCAPED_SLASHES) ?>;
  const GS_STANDS       = <?= json_encode(greenstand_stands(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  // Les succès : les badges écrits à la main en entier, et les mille paliers sous
  // forme compacte (le navigateur recompose leurs noms). Voir gs_badges_pour_page().
  const GS_SUCCES      = <?= json_encode(gs_badges_pour_page(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const GS_BADGE_STATS  = <?= json_encode(gs_badge_stats(), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
(function(){
'use strict';
/* ======================================================================
   ANTI-CHEAT — protection basique contre le self-XSS (arnaques du type
   "colle ce code dans la console pour débloquer des jetons DT"). Toute
   la logique de jeu ci-dessous est en plus encapsulée dans cette IIFE :
   `state`, `ITEMS`, `MANAGERS`, `sell`, etc. ne sont pas des variables
   globales, donc injoignables depuis la console du navigateur après
   coup. Ce n'est qu'une gêne pour un tricheur motivé (le JS reste lisible
   et modifiable via les devtools) — la vraie limite reste le plafond
   serveur appliqué dans greenstand_sync().
   ====================================================================== */
console.log('%cSTOP', 'color:#D9A441;font-size:48px;font-weight:900;text-shadow:2px 2px 0 #000;');
console.log('%cCette console est réservée aux développeurs. Si quelqu\'un t\'a demandé de coller du code ici pour "débloquer des jetons DT" ou un autre avantage, c\'est une arnaque (self-XSS) : ne colle rien, tu risques de perdre ton compte.', 'color:#EDEAE0;font-size:14px;');

/* ======================================================================
   TES VRAIES IMAGES, détourées (fond blanc supprimé) et encodées en base64
   ====================================================================== */
const ASSET_DATA = <?= json_encode(gs_asset_urls(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const ASSET_META = {"client": {"w": 469, "h": 480}, "bag": {"w": 420, "h": 359}, "nug_big": {"w": 283, "h": 420}, "grinder": {"w": 420, "h": 316}, "jar": {"w": 420, "h": 384}, "joint": {"w": 420, "h": 292}, "raw_papers": {"w": 420, "h": 297}, "scale": {"w": 420, "h": 321}, "ashtray_joint": {"w": 420, "h": 303}, "tin_nugs": {"w": 420, "h": 295}, "blue_static": {"w": 300, "h": 450}, "pinkyple": {"w": 300, "h": 450}, "skiteelz": {"w": 1024, "h": 1536}, "banner": {"w": 1400, "h": 510}};
// Dimensions réelles de CHAQUE visuel (dont bag_t2/t3/t4 etc.), lues côté serveur :
// c'est la source fiable pour calculer le bon ratio largeur/hauteur, sans dépendre
// du chargement (async) de la texture ni des 12 entrées codées en dur ci-dessus.
const ASSET_DIMS = <?= json_encode(gs_asset_dims(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

/* OÙ EST LE PRODUIT DANS SON IMAGE — [x0, y0, x1, y1] en fractions de la toile,
   mesuré côté serveur sur les pixels visibles (gs_asset_boxes()).

   Sans ça, le jeu posait la TOILE sur le comptoir et lui donnait la hauteur
   voulue. Ça marche pour les visuels d'origine, qui sont des découpes serrées ;
   pas du tout pour ceux des nouveaux stands, où l'objet flotte au milieu d'un
   carré 1536x1024 avec 16 % de vide en dessous : le produit sortait deux fois
   trop petit, décentré, et suspendu au-dessus du comptoir.

   Avec la boîte, on découpe la texture (les UV du plan) sur le sujet et rien
   d'autre : le plan EST le produit. Il a la bonne taille, il est centré sur son
   emplacement, il est posé sur le comptoir, et un clic ne touche que lui. Et
   c'est vrai de n'importe quelle image envoyée depuis le panel admin, sans
   avoir à la recadrer. */
const ASSET_BOX = <?= json_encode(gs_asset_boxes(), JSON_UNESCAPED_SLASHES) ?>;

const ICON_ASSET = {
  bag:'bag', scale:'scale', jar:'jar', grinder:'grinder',
  papers:'raw_papers', ashtray:'ashtray_joint', joint:'joint', tin:'tin_nugs',
  bazekush:'nug_big', blue_static:'blue_static', pinkyple:'pinkyple', skiteelz:'skiteelz',
  client:'client'
};
const STANDEE_HEIGHT = {
  bag:1.7, scale:1.0, jar:1.15, grinder:1.15, papers:0.95, ashtray:1.05, joint:0.8, tin:1.25,
  // Les variétés sont au premier rang (voir SLOT_PAR_OBJET) et un peu plus hautes
  // que les accessoires : ce sont elles le produit, elles doivent se voir.
  // `bazekush` n'utilise pas cette table — c'est le bourgeon central, dont la
  // hauteur est clickNugHeight.
  bazekush:1.1, blue_static:1.2, pinkyple:1.2, skiteelz:1.2
};

/* LA PLACE DISPONIBLE, EN LARGEUR.
   Une hauteur ne suffit pas à cadrer un produit. Les visuels d'origine sont des
   portraits (un sachet debout, un bocal) : leur donner 1,7 de haut leur donne
   1,2 de large, et tout tient. Ceux des nouveaux stands sont des paysages —
   1536x1024, le produit posé de tout son long. À hauteur égale, le bourgeon
   central du BrownStand faisait 2,46 de large : il recouvrait les deux variétés
   du premier rang et débordait de son emplacement.

   Chaque produit reçoit donc AUSSI une largeur maximale, celle que son
   emplacement peut accueillir. Un visuel trop large est réduit jusqu'à tenir,
   au lieu d'écraser ses voisins. Les valeurs sont volontairement au-dessus de
   ce que mesurent les visuels du GreenStand aujourd'hui (bag 1,17 pour 1,90
   permis, tin 1,56 pour 1,70) : le stand d'origine ne bouge donc pas d'un
   pixel, seuls les visuels vraiment trop larges sont ramenés à la raison. */
const STANDEE_LARGEUR_MAX = {
  bag:1.9, scale:1.5, jar:1.4, grinder:1.6, papers:1.5, ashtray:1.6, joint:1.3, tin:1.7,
  // Le bourgeon central a de la place devant lui (les variétés du premier rang
  // sont bien plus près de la caméra) : il peut être large sans les couvrir.
  // Le premier rang, lui, est serré — les trois variétés se touchent presque.
  bazekush:1.8, blue_static:1.05, pinkyple:1.05, skiteelz:1.05
};

/* ======================================================================
   AUDIO — sons synthétisés (Web Audio API, aucun fichier externe)
   ====================================================================== */
let soundEnabled = true;
try {
  const savedSound = localStorage.getItem('greenstand_sound');
  if (savedSound !== null) soundEnabled = savedSound === '1';
} catch(e){}

let audioCtx = null;
function ensureAudio(){
  if(!soundEnabled) return null;
  try {
    if(!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    if(audioCtx.state === 'suspended') audioCtx.resume();
    return audioCtx;
  } catch(e){ return null; }
}

function playTone(freq, duration, type, volume, delay, freqEnd){
  const ctx = ensureAudio();
  if(!ctx) return;
  const t0 = ctx.currentTime + (delay || 0);
  const osc = ctx.createOscillator();
  const gain = ctx.createGain();
  osc.type = type || 'sine';
  osc.frequency.setValueAtTime(freq, t0);
  if(freqEnd) osc.frequency.exponentialRampToValueAtTime(freqEnd, t0 + duration);
  gain.gain.setValueAtTime(volume || 0.1, t0);
  gain.gain.exponentialRampToValueAtTime(0.0001, t0 + duration);
  osc.connect(gain);
  gain.connect(ctx.destination);
  osc.start(t0);
  osc.stop(t0 + duration + 0.03);
}

function playPop(){
  playTone(520, 0.07, 'sine', 0.12, 0, 780);
}

function playCrit(){
  [660,880,1100,1320].forEach((f,i)=> playTone(f, 0.09, 'sine', 0.11, i*0.045));
}

function playCash(){
  playTone(1300, 0.05, 'square', 0.05, 0);
  playTone(1750, 0.08, 'square', 0.07, 0.06);
  playTone(2200, 0.12, 'triangle', 0.06, 0.12);
}

function playAchievementSound(){
  [880,1108,1320,1760].forEach((f,i)=> playTone(f, 0.14, 'triangle', 0.09, i*0.09));
}

function setSoundEnabled(on){
  soundEnabled = on;
  try { localStorage.setItem('greenstand_sound', on ? '1' : '0'); } catch(e){}
  const btn = document.getElementById('muteBtn');
  if(btn) btn.textContent = on ? '🔊' : '🔇';
}


/* ======================================================================
   PROCEDURAL BACKDROP / SHADOW TEXTURES (canvas, no external images)
   ====================================================================== */
function makeCanvas(w,h,draw){
  const c = document.createElement('canvas');
  c.width = w; c.height = h;
  const ctx = c.getContext('2d');
  draw(ctx,w,h);
  const tex = new THREE.CanvasTexture(c);
  tex.needsUpdate = true;
  return tex;
}

/* La palette du stand tenu. Les fonds, l'auvent, le liseré du comptoir et le halo
   de l'enseigne s'y réfèrent tous : c'est ce qui fait qu'un stand ne se reconnaît
   pas seulement à ses images, mais à sa lumière. */
function gsPalette(){ return gsStandCourant().palette; }

function buildSkyTexture(){
  const ciel = gsPalette().ciel;
  return makeCanvas(512,512,(ctx,w,h)=>{
    const g = ctx.createRadialGradient(w*0.5,h*0.38,40, w*0.5,h*0.45,w*0.72);
    g.addColorStop(0,    ciel[0]);
    g.addColorStop(0.35, ciel[1]);
    g.addColorStop(0.62, ciel[2]);
    g.addColorStop(1,    ciel[3]);
    ctx.fillStyle = g;
    ctx.fillRect(0,0,w,h);
    // subtle grain / bokeh dots for atmosphere
    for(let i=0;i<70;i++){
      const r = Math.random()*2.2+0.4;
      ctx.beginPath();
      ctx.fillStyle = 'rgba(255,255,255,'+(Math.random()*0.05).toFixed(3)+')';
      ctx.arc(Math.random()*w, Math.random()*h*0.8, r, 0, Math.PI*2);
      ctx.fill();
    }
  });
}

function buildWoodTexture(){
  return makeCanvas(512,512,(ctx,w,h)=>{
    ctx.fillStyle = '#7A5836';
    ctx.fillRect(0,0,w,h);
    for(let i=0;i<26;i++){
      const y = (i/26)*h + (Math.random()-0.5)*6;
      ctx.strokeStyle = 'rgba(50,30,14,'+(0.16+Math.random()*0.18).toFixed(2)+')';
      ctx.lineWidth = 1+Math.random()*2.5;
      ctx.beginPath();
      ctx.moveTo(0,y);
      for(let x=0;x<=w;x+=32){
        ctx.lineTo(x, y + Math.sin(x*0.02+i)*4 + (Math.random()-0.5)*3);
      }
      ctx.stroke();
    }
    ctx.fillStyle = 'rgba(217,164,65,0.06)';
    ctx.fillRect(0,0,w,h);
    // vernis: un halo plus clair au centre pour simuler un reflet lustré
    const sheen = ctx.createRadialGradient(w*0.5,h*0.32,10, w*0.5,h*0.32,w*0.55);
    sheen.addColorStop(0,'rgba(255,235,190,0.22)');
    sheen.addColorStop(0.5,'rgba(255,235,190,0.06)');
    sheen.addColorStop(1,'rgba(255,235,190,0)');
    ctx.fillStyle = sheen;
    ctx.fillRect(0,0,w,h);
    // vignette sombre sur le pourtour pour donner du volume au comptoir
    const edge = ctx.createRadialGradient(w*0.5,h*0.5,w*0.32, w*0.5,h*0.5,w*0.5);
    edge.addColorStop(0,'rgba(0,0,0,0)');
    edge.addColorStop(1,'rgba(0,0,0,0.35)');
    ctx.fillStyle = edge;
    ctx.fillRect(0,0,w,h);
  });
}

function buildBackWallTexture(){
  const mur  = gsPalette().mur;
  const halo = gsPalette().halo;
  return makeCanvas(512,384,(ctx,w,h)=>{
    const g = ctx.createLinearGradient(0,0,0,h);
    g.addColorStop(0,    mur[0]);
    g.addColorStop(0.55, mur[1]);
    g.addColorStop(1,    mur[2]);
    ctx.fillStyle = g;
    ctx.fillRect(0,0,w,h);
    // planches verticales discrètes, façon lambris de stand
    for(let x=0; x<w; x+=w/14){
      ctx.strokeStyle = 'rgba(0,0,0,0.18)';
      ctx.lineWidth = 2;
      ctx.beginPath(); ctx.moveTo(x,0); ctx.lineTo(x,h); ctx.stroke();
      ctx.strokeStyle = 'rgba(255,255,255,0.03)';
      ctx.beginPath(); ctx.moveTo(x+2,0); ctx.lineTo(x+2,h); ctx.stroke();
    }
    // halo chaud central, comme si une lumière chauffait le mur derrière le comptoir
    const glow = ctx.createRadialGradient(w*0.5,h*0.72,10, w*0.5,h*0.72,w*0.6);
    glow.addColorStop(0,'rgba(' + halo + ',0.16)');
    glow.addColorStop(1,'rgba(' + halo + ',0)');
    ctx.fillStyle = glow;
    ctx.fillRect(0,0,w,h);
    // vignette haute pour assombrir le plafond du décor
    const top = ctx.createLinearGradient(0,0,0,h*0.4);
    top.addColorStop(0,'rgba(0,0,0,0.5)');
    top.addColorStop(1,'rgba(0,0,0,0)');
    ctx.fillStyle = top;
    ctx.fillRect(0,0,w,h*0.4);
  });
}

function buildSmokeTexture(){
  return makeCanvas(128,128,(ctx,w,h)=>{
    const g = ctx.createRadialGradient(w/2,h/2,0, w/2,h/2,w/2);
    g.addColorStop(0,'rgba(235,235,230,0.55)');
    g.addColorStop(0.5,'rgba(235,235,230,0.22)');
    g.addColorStop(1,'rgba(235,235,230,0)');
    ctx.fillStyle = g;
    ctx.fillRect(0,0,w,h);
  });
}

function buildShadowTexture(){
  return makeCanvas(128,128,(ctx,w,h)=>{
    const g = ctx.createRadialGradient(w/2,h/2,0, w/2,h/2,w/2);
    g.addColorStop(0,'rgba(0,0,0,0.55)');
    g.addColorStop(0.7,'rgba(0,0,0,0.22)');
    g.addColorStop(1,'rgba(0,0,0,0)');
    ctx.fillStyle = g;
    ctx.fillRect(0,0,w,h);
  });
}

/* ======================================================================
   LA CONSOLE DU COMPTOIR
   ======================================================================
   Le journal de ce qui se passe au stand, à droite de la scène. Il sert deux
   choses : donner de la vie au jeu entre deux clics, et surtout rendre la file
   de clients LISIBLE. Dans la scène 3D, un client est une découpe de plus au
   milieu des produits ; ici c'est une fiche avec sa phrase, sa patience qui
   descend et un bouton pour le servir.

   Cette partie est déclarée en dehors du bloc de la scène 3D, pour rester
   utilisable même sans WebGL. Elle ne connaît RIEN du code de la scène : la
   fiche d'un client reçoit une fonction à appeler pour le servir, elle ne va
   pas la chercher elle-même. C'est ce qui évite le piège de portée qui a déjà
   coûté deux bugs (voir hauteurPalier et TEXTURES).
   ====================================================================== */
const GS_CONSOLE_MAX = 45;          // lignes gardées avant de jeter les plus vieilles

/* ======================================================================
   LES VARIÉTÉS — ce que le client vient acheter
   ======================================================================
   Chaque client demande une variété précise, et chacune a son propre
   multiplicateur de prix — la première d'un stand est son tarif de base, les
   suivantes coûtent logiquement plus cher : le client sait ce qu'il veut et
   est prêt à payer pour, exactement comme au vrai comptoir.

   La liste n'est plus écrite ici : elle appartient au STAND (GS_STANDS, rempli
   par greenstand_stands() côté serveur). Changer de comptoir change donc ce
   qu'on vend, ce que les clients demandent et ce qu'ils paient — pas seulement
   la couleur du décor.

   `poids` sert au tirage : une variété chère est aussi plus rare, sinon le
   stand ne vendrait plus jamais que la plus rentable. Les phrases restent
   groupées par variété pour que ce que dit le client corresponde à ce qu'il
   paie — avant, « T'aurais de la Blue Static ? » pouvait sortir sur une
   commande facturée au tarif normal, ce qui n'avait pas de sens. */
function gsVarietes(){
  const v = gsStandCourant().varietes;
  return (Array.isArray(v) && v.length) ? v : GS_STANDS[0].varietes;
}

/* Tirage pondéré : une variété chère sort moins souvent qu'une variété de base. */
function tirerVariete(){
  const varietes = gsVarietes();
  const total = varietes.reduce((s, v) => s + v.poids, 0);
  let r = Math.random() * total;
  for(const v of varietes){
    r -= v.poids;
    if(r <= 0) return v;
  }
  return varietes[0];
}

function gsConsoleFlux(){ return document.getElementById('gsConsoleFlux'); }

/* Colle la vue en bas si l'utilisateur y était déjà. S'il a remonté le journal
   pour lire quelque chose, on ne lui arrache pas sa lecture. */
function gsConsoleSuivre(flux, avant){
  if(avant) flux.scrollTop = flux.scrollHeight;
}
function gsConsoleEnBas(flux){
  return flux.scrollHeight - flux.scrollTop - flux.clientHeight < 40;
}

function gsConsoleElaguer(flux){
  while(flux.children.length > GS_CONSOLE_MAX) flux.removeChild(flux.firstChild);
}

function gsHeure(){
  const d = new Date();
  return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
}

/** Une ligne de journal. `type` donne la couleur : vente, crit, evt, bien, mal. */
function gsConsoleLigne(type, texte){
  const flux = gsConsoleFlux();
  if(!flux) return;
  const bas = gsConsoleEnBas(flux);
  const el = document.createElement('div');
  el.className = 'c-ligne ' + (type ? 'c-' + type : '');
  const h = document.createElement('span');
  h.className = 'c-h'; h.textContent = gsHeure();
  el.appendChild(h);
  el.appendChild(document.createTextNode(texte));
  flux.appendChild(el);
  gsConsoleElaguer(flux);
  gsConsoleSuivre(flux, bas);
}

function gsConsoleMajAttente(n){
  const el = document.getElementById('gsConsoleAttente');
  if(el) el.textContent = n === 0 ? 'personne au comptoir'
                                  : (n + (n > 1 ? ' en attente' : ' en attente'));
}

/**
 * La fiche d'un client. `servir` est la fonction à appeler quand on clique —
 * la console ne sait pas comment on sert quelqu'un, et n'a pas à le savoir.
 * `variete` (facultatif, pour compatibilité) ajoute une étiquette de prix :
 * la variété demandée ne se cache plus dans la phrase, elle est écrite noir
 * sur blanc, avec son multiplicateur — pour que le prix qui monte au moment
 * de servir ne surprenne jamais.
 * Retourne de quoi la piloter depuis la file.
 */
function gsConsoleClient(numero, phrase, variete, servir){
  // Compatibilité : si appelé à l'ancienne (numero, phrase, servir), le
  // troisième argument est la fonction, pas une variété.
  if(typeof variete === 'function'){ servir = variete; variete = null; }

  const flux = gsConsoleFlux();
  if(!flux) return {maj(){}, fin(){}};
  const bas = gsConsoleEnBas(flux);

  const el = document.createElement('div');
  el.className = 'c-client';
  el.innerHTML =
      '<div class="c-qui">CLIENT #' + numero + '</div>'
    + '<div class="c-dit"></div>'
    + (variete ? '<div class="c-variete"></div>' : '')
    + '<div class="c-jauge"><div style="width:100%"></div></div>'
    + '<button type="button">Servir</button>';
  el.querySelector('.c-dit').textContent = '« ' + phrase + ' »';
  if(variete){
    const badge = el.querySelector('.c-variete');
    badge.textContent = variete.nom + ' · ×' + variete.mult.toFixed(2);
    // La couleur de l'étiquette suit le prix : plus c'est cher, plus c'est doré.
    badge.style.color = variete.mult >= 2 ? '#F0D9A6' : (variete.mult > 1 ? '#CFEFC4' : '#8FA089');
  }

  const jauge = el.querySelector('.c-jauge div');
  const btn   = el.querySelector('button');
  // Le client marche encore vers le comptoir : servir() refuserait en silence,
  // et un bouton qui ne répond pas laisse croire à une panne. On le dit.
  btn.disabled = true;
  btn.textContent = 'en approche…';
  btn.addEventListener('click', (ev)=>{ if(ev.isTrusted) servir(); });

  flux.appendChild(el);
  gsConsoleElaguer(flux);
  gsConsoleSuivre(flux, bas);

  return {
    // Appelé quand il arrive au comptoir : à partir de là, on peut le servir.
    pret(){
      btn.disabled = false;
      btn.textContent = 'Servir';
    },
    maj(reste){
      jauge.style.width = Math.max(0, Math.min(1, reste)) * 100 + '%';
      jauge.style.background = reste > 0.5 ? '#7BD46A' : (reste > 0.22 ? '#D9A441' : '#C8503C');
    },
    fin(servi, gain){
      el.classList.add('c-fini');
      el.querySelector('.c-jauge').remove();
      btn.disabled = true;
      btn.textContent = servi ? 'Servi · +' + gain : 'Reparti sans rien';
      // La fiche disparaît, remplacée par une ligne de journal : sinon le flux
      // se remplit de cartes mortes et on ne voit plus les clients en cours.
      setTimeout(()=>{
        el.remove();
        gsConsoleLigne(servi ? 'vente' : 'mal',
          servi ? ('Client #' + numero + ' servi · +' + gain)
                : ('Client #' + numero + ' est reparti sans rien'));
      }, 1400);
    }
  };
}

/* Replier / déplier. Le stand récupère toute la largeur quand la console gêne —
   et la caméra se recule ou se rapproche en conséquence (voir onResize). */
function gsConsolePli(plie){
  const duo = document.querySelector('.stand-duo');
  if(!duo) return;
  duo.classList.toggle('est-plie', !!plie);
  try { localStorage.setItem('gs_console_plie', plie ? '1' : '0'); } catch(e){}
  // Le cadre a changé de largeur : la scène doit se recadrer tout de suite.
  window.dispatchEvent(new Event('resize'));
}

// Premières lignes, pour que la console ne soit pas vide au chargement.
if(GS_PANEL === 'jeu'){
  document.addEventListener('DOMContentLoaded', ()=>{
    document.getElementById('gsConsolePlier')?.addEventListener('click', ()=>{
      gsConsolePli(!document.querySelector('.stand-duo')?.classList.contains('est-plie'));
    });
    let plie = false;
    try { plie = localStorage.getItem('gs_console_plie') === '1'; } catch(e){}
    if(plie) gsConsolePli(true);
    gsConsoleLigne('', 'Stand ouvert. Les clients arrivent tout seuls.');
    gsConsoleLigne('', 'Clique sur le nug pour vendre à la main.');
    gsConsoleMajAttente(0);

    // Distribution automatique : préférence locale, comme le pli de la console.
    const autoBox = document.getElementById('gsAutoServe');
    if(autoBox){
      try { gsAutoServeOn = localStorage.getItem('gs_autoserve') === '1'; } catch(e){}
      autoBox.checked = gsAutoServeOn;
      autoBox.addEventListener('change', ()=>{
        gsAutoServeOn = autoBox.checked;
        try { localStorage.setItem('gs_autoserve', gsAutoServeOn ? '1' : '0'); } catch(e){}
        gsConsoleLigne('', gsAutoServeOn
          ? 'Distribution automatique activée — les clients prêts sont servis tout seuls.'
          : 'Distribution automatique désactivée.');
      });
    }
  });
}

/* ======================================================================
   THREE.JS SCENE
   ====================================================================== */
/* ======================================================================
   LES ÉVÉNEMENTS — effets en cours
   ======================================================================
   Déclaré ICI, avant la scène 3D, parce que la file de clients les lit à
   chaque image. Un idle sans rien à décider n'est qu'un compteur qui monte :
   ces effets sont le seul endroit du jeu où un choix change quelque chose.

   Règle de conception, non négociable : AUCUN événement ne crée d'euros.
   Le serveur calcule ce qu'un stand peut produire à partir du barème qu'il
   détient ; un revenu que seul le navigateur connaîtrait serait refusé au
   dépôt, et le joueur verrait ses gains disparaître sans comprendre. Les
   événements ne touchent donc qu'à trois choses : le RYTHME des clients, le
   PRIX des améliorations, et le TEMPS pendant lequel le stand est ouvert.
   ====================================================================== */
const gsEffets = {
  arrivees:  {mult: 1, fin: 0},   // fréquence d'arrivée des clients
  patience:  {mult: 1, fin: 0},   // patience des clients
  cout:      {mult: 1, fin: 0},   // prix des améliorations
  ferme:     {fin: 0},            // stand fermé : aucune vente à la main
};

function gsEffetActif(nom){
  const e = gsEffets[nom];
  if(!e || e.fin <= Date.now()) return 0;
  return Math.ceil((e.fin - Date.now()) / 1000);
}
function gsEffetMult(nom){
  return gsEffetActif(nom) ? gsEffets[nom].mult : 1;
}
function gsStandFerme(){
  return gsEffetActif('ferme') > 0;
}
function gsPoserEffet(nom, mult, secondes){
  gsEffets[nom].mult = mult;
  gsEffets[nom].fin  = Date.now() + secondes * 1000;
}

/* Une fermeture de stand, elle, est enregistrée SUR LE SERVEUR.
   Avant, elle ne vivait que dans la mémoire de la page : actualiser rouvrait le
   stand aussitôt, et le contrôle de police ne coûtait plus rien. Maintenant elle
   survit au rechargement — c'est state_load qui la rend au retour, avec le temps
   qu'il reste d'après l'horloge du serveur.

   Hors ligne, ou si l'appel échoue, on garde au moins la fermeture locale : mieux
   vaut une sanction qui saute au rechargement qu'une sanction qui n'existe pas. */
function gsFermerLeStand(secondes){
  gsPoserEffet('ferme', 1, secondes);
  gsMajBandeauEffets();
  if(!GS_LOGGED_IN) return;
  gsCloudRequest('stand_ferme', {secondes: secondes})
    .catch(err => console.warn('[GreenStand] Fermeture non enregistrée :', err.message));
}

/* ======================================================================
   LE STAND TENU
   ======================================================================
   Le joueur ne tient plus forcément le GreenStand : à partir de la 10e
   franchise il en débloque d'autres, et il passe de l'un à l'autre quand il
   veut (voir GS_STANDS, rempli par greenstand_stands() côté serveur).

   `gsStandId` est déclaré ICI, avant la scène 3D, et pas avec le reste de
   l'état de la partie (`state`, plus bas) : la scène se construit dès
   l'évaluation du script — ciel, comptoir, bourgeon central — et lit donc la
   palette du stand AVANT que `state` existe. Une variable déclarée plus bas
   lèverait une ReferenceError (zone morte temporelle) qui tuerait la scène.

   Ce qui change d'un stand à l'autre : les images (préfixées par l'id du
   stand), la palette, les produits demandés par les clients, trois
   multiplicateurs — clic, revenu automatique, valeur d'un client servi — ET,
   depuis maintenant, LES NIVEAUX D'AMÉLIORATIONS ET DE GÉRANTS.

   Chaque comptoir a les siens. Améliorer la balance du BrownStand n'améliore
   plus celle du GreenStand : ce sont deux commerces, pas deux vitrines du même.
   `gsStandLevels` garde les niveaux de chacun, et l'on bascule de l'un à l'autre
   en changeant de stand (voir gsPoserStand).

   CE QUI NE CHANGE PAS : l'argent, les compteurs à vie, les succès, les
   franchises et les bonus permanents — ils appartiennent au joueur, pas à un
   comptoir. Le « niveau de stand » non plus ne retombe pas : c'est celui du
   meilleur comptoir (voir standLevel), sinon changer de stand fermerait les
   contacts du téléphone et les objectifs du jour déjà gagnés.

   LES PARTIES DÉJÀ EN COURS. Une sauvegarde d'avant la séparation n'a qu'un jeu
   de niveaux commun : on le donne À CHAQUE comptoir (ici comme côté serveur,
   greenstand_stand_levels_normalise). Personne ne perd rien — chacun retrouve
   partout ce qu'il avait — et la séparation commence à partir de là.
   ====================================================================== */
let gsStandId = 'green';

/* { standId: {levels:[…], managerLevels:[…]} } — les niveaux de chaque comptoir.
   Ceux du comptoir TENU sont, eux, dans ITEMS[].level / MANAGERS[].level : c'est
   ce que le reste du jeu lit, sans avoir à connaître cette carte. */
let gsStandLevels = {};

function gsStand(id){
  return GS_STANDS.find(s => s.id === id) || GS_STANDS[0];
}
function gsStandCourant(){ return gsStand(gsStandId); }

/* Le nombre de franchises ouvertes — c'est LUI qui débloque les stands. La valeur
   vient du serveur (GS_GOLD0 au rendu, puis state_load), jamais d'un compteur local. */
function gsFranchisesOuvertes(){
  return Number(gsGold && gsGold.franchises) || 0;
}
function gsStandDebloque(stand){
  return gsFranchisesOuvertes() >= Number(stand.franchises || 0);
}
/* Le meilleur stand auquel le joueur a droit — sert de repli quand la sauvegarde
   annonce un stand qu'il n'a pas (ou plus). Le serveur applique exactement la même
   règle de son côté (greenstand_stand_autorise) : la page ne décide de rien. */
function gsMeilleurStand(){
  let best = GS_STANDS[0];
  GS_STANDS.forEach(st => { if(gsStandDebloque(st)) best = st; });
  return best;
}

/* Le tarif des améliorations et des gérants suit le MEILLEUR stand débloqué, pas
   celui qu'on tient : sinon il suffirait de repasser au GreenStand pour tout
   acheter au prix d'origine avant de revenir encaisser au WhiteStand. */
function gsStandCoutMult(){
  return Number(gsMeilleurStand().costMult) || 1;
}

/* Le nom et la description d'un objet SUR LE STAND TENU. Les quatre produits de
   la boutique changent d'un comptoir à l'autre (on ne vend pas de la BazeKush au
   WhiteStand) ; le matériel, lui, garde son nom partout. C'est purement de
   l'affichage : l'identifiant, le niveau et le prix ne bougent pas — voir le
   champ `objets` de greenstand_stands(). */
function gsNomObjet(item){
  const o = (gsStandCourant().objets || {})[item.id];
  return (o && o.nom) || item.name;
}
function gsDescObjet(item){
  const o = (gsStandCourant().objets || {})[item.id];
  return (o && o.desc) || item.desc;
}

/* La clé d'image à utiliser pour `cle` sur le stand courant : celle du stand si
   elle existe, celle du GreenStand sinon. C'est ce repli qui permet de débloquer
   un stand AVANT d'avoir dessiné ses images — le comptoir n'est jamais vide, il
   emprunte au GreenStand ce qui lui manque encore. */
function gsAssetStand(cle, standId){
  if(!cle) return cle;
  const prefixe = gsStand(standId || gsStandId).prefix || '';
  if(prefixe && ASSET_DATA[prefixe + cle]) return prefixe + cle;
  return cle;
}

let scene3dOk = true;
let refreshStandModels = function(){};
let applyStandDecor = function(){};
let resetStandVisuals = function(){};
// Rejoue tout ce qui dépend du stand dans la scène 3D (enseigne, ciel, comptoir,
// modèles, clients). Coquille vide hors de la page du stand, comme les trois
// fonctions ci-dessus.
let applyStandVisuals = function(){};

// Distribution automatique : coché depuis la console, lu par le tick de la
// scène 3D (voir plus bas). Réglage purement local au navigateur — ce n'est
// pas une donnée de partie, donc pas de conflit possible avec une sauvegarde
// serveur.
let gsAutoServeOn = false;

// « Prochain client » et « qui attend » sont des coquilles ici, comme les
// quatre fonctions ci-dessus : la vraie file de clients n'existe QUE dans la
// scène 3D (voir « LA FILE DE CLIENTS » plus bas), donc si WebGL échoue, ces
// coquilles restent en place et le bandeau du comptoir reste simplement masqué
// au lieu de planter sur une variable qui n'existe pas.
let gsProchainClientSecondes = function(){ return null; };
let gsAutoServirClients = function(){};

let sell = function(clientX, clientY, ev){
  state.money += state.clickValue;
  state.totalEarned += state.clickValue;
  state.lifetimeTotalEarned += state.clickValue;
  state.manualSales += 1;
  state.lifetimeManualSales += 1;
  renderTop(); renderShop();
};

/* ======================================================================
   PALIERS VISUELS
   ======================================================================
   Le stand était identique au niveau 5 et au niveau 50 : un objet apparaissait
   à l'achat, puis ne bougeait plus jamais. Améliorer ne se voyait nulle part.

   Un objet change maintenant de modèle aux niveaux 10, 25 et 50. Les images
   s'appellent <asset>_t2, _t3, _t4 (par exemple jar_t2) et s'envoient depuis
   l'onglet « Images du jeu » du panel admin, comme les autres.

   Rien n'oblige à les fournir toutes : le jeu descend jusqu'au palier dont
   l'image existe. Un objet sans aucune variante garde simplement son modèle
   d'origine à tous les niveaux — le stand ne devient jamais vide.
   ====================================================================== */
const TIER_SEUILS = [[50, 't4'], [25, 't3'], [10, 't2']];

function tierSuffixe(niveau){
  for(const [seuil, sfx] of TIER_SEUILS) if(niveau >= seuil) return sfx;
  return '';
}

/* La vignette d'un objet dans « Améliorations » doit suivre le même palier que
   son modèle sur le stand : même logique que assetPourObjet() (plus bas, qui
   dépend de la scène 3D et de TEXTURES), mais utilisable partout — y compris
   sur Distributeur.php ou Franchise.php, où la scène 3D n'existe pas — en
   s'appuyant sur ASSET_DATA (toujours disponible) plutôt que sur les textures
   Three.js. */
function assetKeyBoutique(item){
  const base = ICON_ASSET[item.icon];
  if(!base) return null;
  const sfx = tierSuffixe(item.level || 0);
  const candidats = [];
  if(sfx === 't4') candidats.push(base + '_t4', base + '_t3', base + '_t2');
  else if(sfx === 't3') candidats.push(base + '_t3', base + '_t2');
  else if(sfx === 't2') candidats.push(base + '_t2');
  candidats.push(base);
  // Même repli que assetPourObjet() : le comptoir tenu d'abord, sur TOUS ses
  // paliers, le GreenStand seulement après.
  return gsChoisirAsset(candidats, cle => !!ASSET_DATA[cle]);
}

/* Le premier visuel disponible parmi `candidats` (du meilleur palier au plus bas).
   On passe DEUX FOIS sur la liste : d'abord les images du comptoir tenu, ensuite
   seulement celles du GreenStand.

   L'ordre inverse (palier par palier, stand puis Green) était un bug visible : au
   niveau 50 le Sachet du BrownStand cherchait `brown_bag_t4`, ne le trouvait pas,
   et prenait `bag_t4` — le sachet VERT — alors que `brown_bag` existait. Le joueur
   voyait le produit d'un autre comptoir. Le bon palier ne vaut rien s'il montre la
   marchandise du voisin : l'identité du stand passe avant. */
function gsChoisirAsset(candidats, existe){
  const prefixe = gsStandCourant().prefix || '';
  if(prefixe){
    for(const cle of candidats){ if(existe(prefixe + cle)) return prefixe + cle; }
  }
  for(const cle of candidats){ if(existe(cle)) return cle; }
  return null;
}

/* ======================================================================
   PRIX PAR PALIER
   ======================================================================
   Un changement de modèle purement cosmétique n'avait pas de sens pour un
   objet qui devient VISIBLEMENT un autre produit : le sachet basique, le
   sachet holo violet et le sachet « SKITEELZ » doré ne se vendent pas au
   même prix dans la vraie vie, ils ne devraient pas rapporter pareil ici.

   Ce n'est PAS rétroactif : les niveaux achetés avant de passer un palier
   gardent la valeur qu'ils avaient au moment de l'achat. Seuls les niveaux
   achetés APRÈS le palier profitent du nouveau tarif — comme le magasin qui
   change de fournisseur, pas comme une remise qui reviendrait sur les ventes
   déjà faites. C'est aussi ce qui rend « Prochain niveau : +X € » fiable :
   le chiffre annoncé avant l'achat est exactement celui obtenu après.

   Un objet absent de cette table n'est pas concerné : son comportement reste
   exactement celui d'avant (croissance lisse, aucun palier de prix). */
const TIER_PRIX_MULT = {
  bag: { t2: 1.35, t3: 1.90, t4: 2.75 }  // Sachet mylar holo → holo → SKITEELZ
};

/* Multiplicateur de prix applicable à un niveau AFFICHÉ précis (1-indexé,
   celui du compteur « Niv. X/50 ») pour l'objet dont la clé d'image est `icon`. */
function tierPrixMultAuNiveau(icon, niveauAffiche){
  const table = TIER_PRIX_MULT[icon];
  if(!table) return 1;
  return table[tierSuffixe(niveauAffiche)] || 1;
}

// Les quatre fonctions ci-dessus restent des coquilles vides hors de la page du
// stand : rien d'autre dans le fichier n'a besoin de la scène pour fonctionner.
if (GS_PANEL === 'jeu') {
try {
if (typeof THREE === 'undefined') throw new Error('THREE non chargé');

const frame = document.getElementById('sceneFrame');
const scene = new THREE.Scene();
scene.background = buildSkyTexture();

const camera = new THREE.PerspectiveCamera(39, frame.clientWidth/frame.clientHeight, 0.1, 100);
camera.position.set(0, 2.5, 7.2);
camera.lookAt(0,0.6,0);

const renderer = new THREE.WebGLRenderer({antialias:true});
renderer.setSize(frame.clientWidth, frame.clientHeight);
renderer.setPixelRatio(Math.min(window.devicePixelRatio,2));
if (THREE.sRGBEncoding) renderer.outputEncoding = THREE.sRGBEncoding;
frame.insertBefore(renderer.domElement, frame.firstChild);

scene.add(new THREE.HemisphereLight(0xEFEAD8, 0x2B3A22, 0.9));
const key = new THREE.DirectionalLight(0xFFF6E0, 1.0);
key.position.set(3,6,4);
scene.add(key);
const rim = new THREE.PointLight(0x8B5FBF, 0.7, 12);
rim.position.set(-3,2,-2);
scene.add(rim);
const rim2 = new THREE.PointLight(0x4CAF3D, 0.5, 12);
rim2.position.set(3,1.5,-3);
scene.add(rim2);

/* counter */
const woodTex = buildWoodTexture();
woodTex.wrapS = woodTex.wrapT = THREE.RepeatWrapping;
const counter = new THREE.Mesh(
  new THREE.CylinderGeometry(4.3,4.3,0.3,56),
  new THREE.MeshStandardMaterial({map:woodTex, roughness:0.55, metalness:0.12})
);
counter.position.y = -0.15;
scene.add(counter);

// ombre de contact large et douce sous le comptoir, pour l'ancrer dans le décor
const groundShadow = new THREE.Mesh(
  new THREE.CircleGeometry(5.6,48),
  new THREE.MeshBasicMaterial({map:buildShadowTexture(), transparent:true, opacity:0.5, depthWrite:false})
);
groundShadow.rotation.x = -Math.PI/2;
groundShadow.position.y = -0.31;
scene.add(groundShadow);

const trim = new THREE.Mesh(
  new THREE.TorusGeometry(4.3,0.035,10,64),
  new THREE.MeshStandardMaterial({color:0x4CAF3D, emissive:0x1E5B1E, emissiveIntensity:0.6, metalness:0.4, roughness:0.4})
);
trim.rotation.x = Math.PI/2;
trim.position.y = 0.0;
scene.add(trim);

// Le fond reste, mais il n'est plus qu'une ambiance derrière le stand : c'est la
// bannière GreenStand (buildStandFacade, plus bas) qui fait le décor maintenant.
const backWall = new THREE.Mesh(
  new THREE.PlaneGeometry(20,9),
  new THREE.MeshBasicMaterial({map:buildBackWallTexture(), transparent:false})
);
backWall.position.set(0,3.4,-5.6);
scene.add(backWall);

/* texture loader for the user's real photos */
const loader = new THREE.TextureLoader();
const TEXTURES = {};
Object.keys(ASSET_DATA).forEach(key=>{
  const t = loader.load(ASSET_DATA[key]);
  if (THREE.sRGBEncoding) t.encoding = THREE.sRGBEncoding;
  t.anisotropy = renderer.capabilities.getMaxAnisotropy ? renderer.capabilities.getMaxAnisotropy() : 1;
  TEXTURES[key] = t;
});
const shadowTex = buildShadowTexture();

/* ATTENTION À LA PORTÉE — ce bloc doit rester ICI, après TEXTURES.
   Il était plus haut, à côté de STANDEE_HEIGHT, hors du bloc « if (GS_PANEL ===
   'jeu') ». Or TEXTURES est déclaré DANS ce bloc : assetPourObjet() levait donc
   « TEXTURES is not defined » dès le premier appel de refreshStandModels(), ce
   qui tuait la fin du script — d'où un stand vide, des grilles Gérants et Succès
   jamais dessinées, et un compteur de succès figé sur la valeur du HTML. */

/* La clé d'image à utiliser pour cet objet, à son niveau actuel. */
function assetPourObjet(item){
  const base = ICON_ASSET[item.icon];
  if(!base) return null;
  const sfx = tierSuffixe(item.level || 0);
  const candidats = [];
  if(sfx === 't4') candidats.push(base + '_t4', base + '_t3', base + '_t2');
  else if(sfx === 't3') candidats.push(base + '_t3', base + '_t2');
  else if(sfx === 't2') candidats.push(base + '_t2');
  candidats.push(base);
  // Le comptoir tenu d'abord, sur tous ses paliers (`brown_bag_t2`, puis
  // `brown_bag`), et le GreenStand seulement s'il n'a AUCUNE image pour cet objet.
  // Voir gsChoisirAsset() : montrer le sachet vert sur le BrownStand parce que le
  // palier 4 brun n'est pas encore dessiné, c'était montrer le mauvais produit.
  return gsChoisirAsset(candidats, cle => !!TEXTURES[cle]);
}

/* Une image de palier est plus grande que l'originale : elle lui ajoute de la
   place pour l'aura et les exemplaires en retrait. Comme le jeu cale un modèle
   sur sa HAUTEUR, la même hauteur appliquée à une toile plus haute ferait
   RÉTRÉCIR le produit à mesure qu'on l'améliore — l'inverse de l'effet voulu.
   On agrandit donc la cible d'autant que la toile a grandi.

   Ces facteurs suivent ceux de greenstand_paliers.php, qui fabrique les images
   (1.16 pour t2, 1.14 pour t3 et t4). Si tu changes les uns, change les autres. */
function hauteurPalier(cle){
  if(/_t2$/.test(cle)) return 1.16;
  if(/_t[34]$/.test(cle)) return 1.14;
  return 1;
}

/* Les proportions d'une image de palier ne sont pas dans ASSET_META (qui est
   écrit en dur pour les 12 visuels d'origine) : on les lit dans ASSET_DIMS,
   envoyé par le serveur pour TOUS les visuels — mais SEULEMENT quand
   ASSET_META ne dit rien, car ASSET_META n'est pas qu'un doublon des
   dimensions du fichier : c'est un recadrage choisi à la main sur le SUJET
   visible dans l'image, qui peut avoir une marge transparente autour de lui.
   Utiliser les dimensions brutes du fichier à sa place déforme le rendu —
   c'est exactement ce qui est arrivé à Blue Static/Pinkyple quand ASSET_DIMS
   passait en premier : leur image a une toile plus large que le bourgeon
   qu'elle contient, et le standee s'est retrouvé bien plus large (et donc
   visuellement « couché ») que prévu. */
function metaPourAsset(cle){
  if(ASSET_META[cle]) return ASSET_META[cle];
  const dims = ASSET_DIMS[cle];
  if(dims) return {w: dims[0], h: dims[1]};
  const tex = TEXTURES[cle];
  if(tex && tex.image && tex.image.width) return {w: tex.image.width, h: tex.image.height};
  const base = String(cle).replace(/_t[234]$/, '');
  return ASSET_META[base] || {w: 400, h: 400};
}


/* La boîte du sujet, ou la toile entière quand on ne l'a pas mesurée (image
   toute neuve, dossier data/ non inscriptible, visuel sans transparence). Dans
   ce cas on retombe exactement sur l'ancien comportement. */
function boitePourAsset(cle){
  const b = ASSET_BOX[cle];
  return (Array.isArray(b) && b.length === 4 && b[2] > b[0] && b[3] > b[1]) ? b : [0, 0, 1, 1];
}

/* Recadre les coordonnées de texture d'un plan sur la boîte du sujet.
   PlaneGeometry donne quatre sommets, dans cet ordre : haut-gauche, haut-droit,
   bas-gauche, bas-droit. L'axe V d'une texture monte alors que l'axe Y d'une
   image descend, d'où les 1 - y. */
function recadrerUV(geo, box){
  const uv = geo.attributes.uv;
  const u0 = box[0], u1 = box[2], vHaut = 1 - box[1], vBas = 1 - box[3];
  uv.setXY(0, u0, vHaut); uv.setXY(1, u1, vHaut);
  uv.setXY(2, u0, vBas);  uv.setXY(3, u1, vBas);
  uv.needsUpdate = true;
}

/* Un plan qui contient EXACTEMENT le sujet de l'image : haut de `targetHeight`,
   sauf s'il faut le réduire pour tenir dans `largeurMax`. */
function planeSujet(assetKey, targetHeight, largeurMax){
  const meta = metaPourAsset(assetKey);
  const box  = boitePourAsset(assetKey);
  let h = targetHeight;
  // Les proportions sont celles du SUJET, pas de la toile : une image carrée
  // dont le produit est deux fois plus haut que large donne un plan étroit.
  const ratio = (meta.w * (box[2] - box[0])) / (meta.h * (box[3] - box[1]));
  let w = h * ratio;
  if(largeurMax > 0 && w > largeurMax){
    // Trop large pour son emplacement : on réduit l'ensemble, sans déformer.
    w = largeurMax;
    h = w / ratio;
  }
  const geo = new THREE.PlaneGeometry(w, h);
  recadrerUV(geo, box);
  return {geo: geo, w: w, h: h};
}

function makeStandee(assetKey, targetHeight, largeurMax){
  const {geo, w, h} = planeSujet(assetKey, targetHeight, largeurMax);
  const mat = new THREE.MeshBasicMaterial({
    map:TEXTURES[assetKey], transparent:true, alphaTest:0.35, side:THREE.DoubleSide
  });
  const plane = new THREE.Mesh(geo, mat);
  // Le plan EST le sujet : son bas repose sur le comptoir, comme avant.
  plane.position.y = h/2;

  const g = new THREE.Group();
  g.add(plane);

  const shadow = new THREE.Mesh(
    new THREE.PlaneGeometry(w*1.15, w*0.6),
    new THREE.MeshBasicMaterial({map:shadowTex, transparent:true, opacity:0.75, depthWrite:false})
  );
  shadow.rotation.x = -Math.PI/2;
  shadow.position.y = 0.02;
  g.add(shadow);

  // La largeur réellement occupée : utile pour vérifier qu'aucun produit ne
  // déborde de son emplacement.
  g.userData.largeur = w;
  return g;
}

/* ---------------- OÙ CHAQUE OBJET SE POSE SUR LE STAND ----------------

   AVANT, la place d'un objet était SLOT_POS[idx % SLOT_POS.length] : sa position
   dans la liste des objets, ramenée à douze emplacements. Ça marchait tant qu'il
   y avait douze objets ou moins. Il y en a quatorze : la Pinkyple (13e) retombait
   sur l'emplacement du sachet et la Skiteelz (14e) sur celui de la balance. Les
   deux variétés étaient bien posées sur le stand, mais À L'INTÉRIEUR d'un autre
   objet — d'où l'impression qu'elles « buguaient dans les autres images ».

   Une place FIXE par identifiant règle le problème pour de bon : ajouter un objet
   à la liste ne déplace plus rien, et deux objets ne peuvent plus se retrouver au
   même endroit.

   Les huit accessoires gardent exactement la place qu'ils avaient. Les VARIÉTÉS,
   elles, passent au premier rang, devant les accessoires et devant les clients :
   ce sont les produits du stand, c'est ce qu'on doit voir en premier. Le centre
   reste libre — c'est le gros bourgeon cliquable (la BazeKush), qu'un modèle posé
   devant masquerait à moitié. */
const SLOT_PAR_OBJET = {
  // accessoires — inchangés
  bag:     [-2.3, 0, -0.4],
  scale:   [-1.15, 0, -1.1],
  jar:     [1.15, 0, -1.1],
  grinder: [2.3, 0, -0.4],
  papers:  [-1.7, 0, 1.1],
  ashtray: [1.7, 0, 1.1],
  joint:   [-3.1, 0, 0.5],
  tin:     [3.1, 0, 0.5],
  // variétés — premier rang, au bord du comptoir, face à la caméra.
  // Ces trois places ont été choisies pour ne recouvrir NI le bourgeon central
  // (le clic reste parfaitement visible) NI le bord du cadre, même quand la
  // console rétrécit la scène. Ne les déplace pas au jugé : z au-delà de 3,3
  // et le pied du modèle sort par le bas de l'image.
  skiteelz:    [-1.55, 0, 3.0],
  pinkyple:    [1.55, 0, 3.0],
  blue_static: [-0.9, 0, 1.9]
  // [0.9, 0, 1.9] reste libre : c'est la place d'une quatrième variété, le jour
  // où tu en ajoutes une. La BazeKush, elle, EST déjà le bourgeon central.
};

/* Emplacements de secours pour un objet qui n'est pas dans la table ci-dessus
   (un ajout futur dont on aurait oublié la ligne) : ils sont distribués dans
   l'ordre, et jamais deux fois le même. Mieux vaut un objet un peu excentré
   qu'un objet posé dans un autre. */
const SLOT_SECOURS = [
  [0.9, 0, 1.9], [-3.8, 0, 1.6], [3.8, 0, 1.6], [-2.5, 0, -1.6], [2.5, 0, -1.6]
];
const slotSecoursPris = {};

function slotPourObjet(item){
  const fixe = SLOT_PAR_OBJET[item.id];
  if(fixe) return fixe;
  if(!slotSecoursPris[item.id]){
    const dejaPris = Object.keys(slotSecoursPris).length;
    slotSecoursPris[item.id] = SLOT_SECOURS[dejaPris % SLOT_SECOURS.length];
  }
  return slotSecoursPris[item.id];
}
const itemGroup = new THREE.Group();
scene.add(itemGroup);
const placed = {};

const clickNugHeight = 1.55;
// Le bourgeon central a de la place autour de lui, mais pas au point de couvrir
// les deux variétés du premier rang : sa largeur est bornée comme les autres.
const clickNugLargeurMax = STANDEE_LARGEUR_MAX.bazekush;
/* Le bourgeon central EST la BazeKush : c'est le même visuel (ICON_ASSET.bazekush
   vaut 'nug_big'), et c'est pour ça que refreshStandModels() ne lui pose pas de
   second modèle sur le stand. Mais il était construit une fois pour toutes avec
   l'image de base : améliorer la BazeKush jusqu'au palier 10, 25 ou 50 ne changeait
   RIEN au centre de la scène, alors que tous les autres objets, eux, changeaient de
   modèle. On le reconstruit donc quand son palier change, comme les autres.
   `let` et pas `const` : c'est un nouvel objet à chaque palier franchi. */
let clickNug = makeStandee(gsAssetStand('nug_big'), clickNugHeight, clickNugLargeurMax);
let clickNugAsset = gsAssetStand('nug_big');
clickNug.position.set(0,0,0.9);
scene.add(clickNug);

/* Remplace le bourgeon central par son modèle de palier, en gardant sa place et
   son orientation courante (la boucle d'animation les remet à jour de toute façon). */
function majBourgeonCentral(assetKey){
  if(!assetKey || assetKey === clickNugAsset) return false;
  const ancien = clickNug;
  const neuf = makeStandee(assetKey, clickNugHeight, clickNugLargeurMax);
  neuf.position.copy(ancien.position);
  neuf.rotation.copy(ancien.rotation);
  scene.remove(ancien);
  scene.add(neuf);
  clickNug = neuf;
  clickNugAsset = assetKey;
  return true;
}

/* fumée : quelques volutes qui montent et se dissipent en boucle */
const smokeTex = buildSmokeTexture();
const smokeEmitters = [];
function createSmokeEmitter(x, z, baseY){
  const g = new THREE.Group();
  g.position.set(x, baseY, z);
  scene.add(g);
  const puffs = [];
  const count = 5;
  for(let i=0;i<count;i++){
    const size = 0.28 + Math.random()*0.16;
    const mat = new THREE.MeshBasicMaterial({map:smokeTex, transparent:true, opacity:0, depthWrite:false, side:THREE.DoubleSide});
    const puff = new THREE.Mesh(new THREE.PlaneGeometry(size,size), mat);
    puff.userData.life = i/count;
    puff.userData.drift = (Math.random()-0.5)*0.25;
    puff.userData.speed = 0.16 + Math.random()*0.06;
    g.add(puff);
    puffs.push(puff);
  }
  const emitter = {
    group:g,
    update(dt, t, camera){
      puffs.forEach(p=>{
        p.userData.life += dt*p.userData.speed;
        if(p.userData.life > 1) p.userData.life -= 1;
        const l = p.userData.life;
        p.position.y = l*1.3;
        p.position.x = Math.sin(l*Math.PI*2*0.5 + p.userData.drift*4)*p.userData.drift*0.6;
        p.scale.setScalar(0.6 + l*1.1);
        p.material.opacity = Math.sin(l*Math.PI) * 0.28;
        p.quaternion.copy(camera.quaternion);
      });
    }
  };
  smokeEmitters.push(emitter);
  return emitter;
}
// volute permanente au-dessus du joint posé sur le nug central
createSmokeEmitter(0.32, 0.75, clickNugHeight*0.92);

// Quel modèle est posé pour quel objet : on retient la CLÉ D'IMAGE, pas seulement
// la présence. C'est ce qui permet de remplacer le modèle quand un palier est
// franchi, au lieu de le poser une fois pour toutes.
const posePar = {};
const fumeePosee = {};

refreshStandModels = function(){
  ITEMS.forEach((item)=>{
    if(item.level <= 0) return;
    // BazeKush partage le modèle du gros bourgeon central déjà cliquable :
    // pas de doublon sur le stand, sinon ça change de place et prête à confusion.
    // En revanche ses paliers, eux, se voient — sur le bourgeon central.
    if(item.id === 'bazekush'){
      const cle = assetPourObjet(item);
      if(majBourgeonCentral(cle) && posePar[item.id]){
        gsShowSyncNote(gsNomObjet(item) + ' — nouveau modèle au niveau ' + item.level);
        gsConsoleLigne('bien', gsNomObjet(item) + ' passe au niveau ' + item.level + ' — nouveau bourgeon au centre du stand.');
      }
      if(cle) posePar[item.id] = cle;
      return;
    }
    // Idem pour « client » : c'est un bonus sur ce que rapportent les clients,
    // pas un produit — son visuel sert déjà aux clients qui défilent au comptoir.
    if(item.id === 'client') return;

    // Certains bonus, comme le minuteur, n'ont pas de modèle 3D :
    // ils restent visibles dans la boutique sans bloquer le jeu.
    const assetKey = assetPourObjet(item);
    if(!assetKey) return;

    // Rien à faire tant que le palier n'a pas changé.
    if(posePar[item.id] === assetKey) return;

    // Palier franchi : l'ancien modèle laisse la place au nouveau, au même endroit.
    if(placed[item.id]){
      itemGroup.remove(placed[item.id]);
      delete placed[item.id];
    }

    const h = (STANDEE_HEIGHT[item.icon] || 1.1) * hauteurPalier(assetKey);
    const lMax = (STANDEE_LARGEUR_MAX[item.icon] || 1.6) * hauteurPalier(assetKey);
    const mesh = makeStandee(assetKey, h, lMax);
    const pos = slotPourObjet(item);
    mesh.position.set(pos[0], pos[1], pos[2]);
    mesh.rotation.y = (Math.random()-0.5)*0.3;
    itemGroup.add(mesh);
    placed[item.id] = mesh;

    // Un objet qui monte de palier se signale : une petite poussée d'échelle,
    // sinon le changement passe inaperçu au milieu du stand.
    if(posePar[item.id]){
      mesh.scale.setScalar(1.35);
      const depart = performance.now();
      const detente = ()=>{
        const t = Math.min(1, (performance.now() - depart) / 420);
        mesh.scale.setScalar(1.35 - 0.35 * t);
        if(t < 1) requestAnimationFrame(detente);
      };
      requestAnimationFrame(detente);
      gsShowSyncNote(gsNomObjet(item) + ' — nouveau modèle au niveau ' + item.level);
      gsConsoleLigne('bien', gsNomObjet(item) + ' passe au niveau ' + item.level + ' — nouveau modèle sur le stand.');
    }
    posePar[item.id] = assetKey;

    // volute de fumée au-dessus du coin détente + cendrier, une fois acheté
    if(item.id === 'ashtray' && !fumeePosee[item.id]){
      fumeePosee[item.id] = true;
      createSmokeEmitter(pos[0], pos[2], h*0.85);
    }
  });
  applyStandDecor(standLevel());
};

/* ======================================================================
   PALIERS VISUELS DU STAND (néon, guirlande, auvent, clients)
   ====================================================================== */
function buildNeonSignTexture(){
  return makeCanvas(640,180,(ctx,w,h)=>{
    ctx.clearRect(0,0,w,h);
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.font = '900 78px "Arial Black", Arial, sans-serif';
    ctx.shadowColor = '#8BFF6B';
    ctx.shadowBlur = 34;
    ctx.fillStyle = '#8BFF6B';
    ctx.fillText('GREENSTAND', w/2, h/2);
    ctx.shadowBlur = 10;
    ctx.fillStyle = '#EAFFE0';
    ctx.fillText('GREENSTAND', w/2, h/2);
  });
}
/* ---------------- LE STAND ----------------
   Le stand n'est plus dessiné à la volée (mur texturé + auvent en toile + enseigne
   néon en canvas) : c'est la vraie bannière GreenStand, à l'échelle du décor qu'elle
   remplace — l'ancien fond faisait 14 de large, la façade en fait 13,4.

   Trois plans superposés, du fond vers l'avant :
     1. une nappe de lumière verte, derrière, qui fait respirer le néon de l'enseigne ;
     2. la bannière elle-même ;
     3. rien d'autre — les guirlandes et les clients restent des objets 3D séparés,
        posés devant, pour garder de la profondeur.

   La façade est volontairement PLUS BASSE que son centre géométrique : l'image
   contient déjà un comptoir, on l'aligne derrière le comptoir 3D pour que les deux
   se lisent comme un seul meuble au lieu de se dédoubler. */
const FACADE_WIDTH = 13.4;

function buildStandFacade(){
  // La bannière du stand tenu, ou celle du GreenStand tant qu'il n'a pas la sienne.
  // metaPourAsset() sait mesurer une image que ASSET_META ne connaît pas (toutes
  // celles des nouveaux stands) : elle lit les dimensions envoyées par le serveur.
  const banniere = gsAssetStand('banner');
  const meta = metaPourAsset(banniere) || {w:1400, h:510};
  const w = FACADE_WIDTH;
  const h = w * (meta.h / meta.w);
  const g = new THREE.Group();

  // 1. la nappe de lumière : un dégradé radial vert, en fusion additive, qui donne
  //    au néon de l'enseigne une vraie diffusion au lieu d'un aplat.
  const halo = gsPalette().halo;
  const glowTex = makeCanvas(256, 256, (ctx, cw, ch) => {
    const grad = ctx.createRadialGradient(cw/2, ch/2, 0, cw/2, ch/2, cw/2);
    grad.addColorStop(0,    'rgba(' + halo + ',0.55)');
    grad.addColorStop(0.45, 'rgba(' + halo + ',0.22)');
    grad.addColorStop(1,    'rgba(' + halo + ',0)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, cw, ch);
  });
  const glowMat = new THREE.MeshBasicMaterial({
    map: glowTex, transparent: true, depthWrite: false,
    blending: THREE.AdditiveBlending, opacity: 0.85
  });
  const glow = new THREE.Mesh(new THREE.PlaneGeometry(w * 1.15, h * 1.6), glowMat);
  glow.position.set(0, 0, -0.12);
  g.add(glow);

  // 2. la bannière. alphaTest bas : le détourage de l'image garde des bords très
  //    doux (feuilles, halos du néon) qu'un seuil élevé découperait au couteau.
  const mat = new THREE.MeshBasicMaterial({
    map: TEXTURES[banniere] || TEXTURES['banner'], transparent: true, alphaTest: 0.04,
    depthWrite: false, side: THREE.DoubleSide
  });
  const plane = new THREE.Mesh(new THREE.PlaneGeometry(w, h), mat);
  g.add(plane);

  g.position.set(0, 2.35, -4.15);
  g.userData.neonPlane = plane;
  g.userData.glow = glow;
  return g;
}

// Conservé sous son ancien nom : applyStandDecor() l'appelle toujours.
function buildNeonSign(){ return buildStandFacade(); }

function buildBulbTexture(){
  const ampoule = gsPalette().ampoule;
  return makeCanvas(64,64,(ctx,w,h)=>{
    const grad = ctx.createRadialGradient(w/2,h/2,0, w/2,h/2,w/2);
    grad.addColorStop(0,'rgba(255,255,235,1)');
    grad.addColorStop(0.45,'rgba(' + ampoule + ',0.85)');
    grad.addColorStop(1,'rgba(' + ampoule + ',0)');
    ctx.fillStyle = grad;
    ctx.fillRect(0,0,w,h);
  });
}
function buildStringLights(){
  const g = new THREE.Group();
  const bulbTex = buildBulbTexture();
  const count = 16;
  for(let i=0;i<count;i++){
    const tt = i/(count-1);
    const x = -4.8 + tt*9.6;
    const y = 2.95 - Math.sin(tt*Math.PI)*0.7;
    const mat = new THREE.MeshBasicMaterial({map:bulbTex, transparent:true, depthWrite:false, blending:THREE.AdditiveBlending});
    const bulb = new THREE.Mesh(new THREE.PlaneGeometry(0.24,0.24), mat);
    bulb.position.set(x,y,-3.6);
    bulb.userData.twinklePhase = Math.random()*Math.PI*2;
    g.add(bulb);
    stringBulbs.push(bulb);
  }
  return g;
}

function buildAwningTexture(){
  const auvent = gsPalette().auvent;
  return makeCanvas(256,128,(ctx,w,h)=>{
    const stripeW = w/8;
    for(let i=0;i<8;i++){
      ctx.fillStyle = i%2===0 ? auvent[0] : auvent[1];
      ctx.fillRect(i*stripeW,0,stripeW,h);
    }
  });
}
function buildAwning(){
  const tex = buildAwningTexture();
  tex.wrapS = THREE.RepeatWrapping; tex.repeat.x = 3;
  const mat = new THREE.MeshStandardMaterial({map:tex, side:THREE.DoubleSide, roughness:0.85});
  const mesh = new THREE.Mesh(new THREE.PlaneGeometry(9.4,1.5), mat);
  mesh.position.set(0,3.35,-3.1);
  mesh.rotation.x = -0.55;
  const g = new THREE.Group();
  g.add(mesh);
  return g;
}

const decorGroup = new THREE.Group();
scene.add(decorGroup);
const stringBulbs = [];
let currentDecorLevel = -1;

applyStandDecor = function(level){
  if(level === currentDecorLevel) return;
  currentDecorLevel = level;
  while(decorGroup.children.length){ decorGroup.remove(decorGroup.children[0]); }
  stringBulbs.length = 0;
  // La façade est là dès le départ : c'est le stand, pas une décoration à débloquer.
  decorGroup.add(buildStandFacade());
  if(level >= 3) decorGroup.add(buildStringLights());
  // Plus aucun palier à atteindre : les clients sont là dès la première seconde.
  // Les réserver au palier 2 avait un effet pervers — un stand revenu au niveau 1
  // (remise à zéro, franchise) n'avait plus AUCUNE des mécaniques récentes, et
  // donnait l'impression que le jeu était cassé plutôt que recommencé.
  clientsEnabled = true;
};

/* ======================================================================
   LA FILE DE CLIENTS
   ======================================================================
   Avant : un client traversait le fond de l'écran, purement décoratif, et on
   ne pouvait rien en faire.

   Maintenant ils s'arrêtent au comptoir, avec une jauge de patience. Un clic
   dessus encaisse la commande ; ignorés, ils repartent et la vente est perdue.
   C'est la première chose du jeu qui demande de REGARDER l'écran plutôt que de
   cliquer dans le vide.

   Contrainte de conception importante : une commande servie ne crée pas d'euros
   sortis de nulle part. Elle vaut exactement N ventes à la main, et incrémente
   le compteur de ventes comme le ferait un clic. Le plafond de dépôt du serveur
   la couvre donc sans rien changer côté serveur — un revenu que seul le
   navigateur connaîtrait serait refusé au dépôt.

   Deuxième garde-fou : servir trois clients dans la même seconde ferait un pic
   de ventes qui ressemble à une macro. D'où le petit délai entre deux
   encaissements, et la taille de commande plafonnée.
   ====================================================================== */
const GS_CLIENT_MAX        = 3;      // pas plus de trois au comptoir à la fois
const GS_CLIENT_SLOTS      = [[-2.05, 1.55], [0, 1.9], [2.05, 1.55]];
const GS_CLIENT_PATIENCE   = 16;     // secondes avant qu'il reparte
const GS_CLIENT_DELAI_MIN  = 7;
const GS_CLIENT_DELAI_MAX  = 15;
const GS_CLIENT_COOLDOWN   = 350;    // ms entre deux encaissements
const GS_CLIENT_TAILLE_MAX = 12;     // ventes que vaut une commande, au plus

const clientHeight = 1.35;
/* La découpe d'un client, elle aussi recadrée sur le personnage et pas sur sa
   toile — sinon le client du BrownStand (un portrait 1024x1536 où il n'occupe
   que 65 % de la largeur) arriverait avec une marge invisible de chaque côté,
   qui masquerait le bourgeon central et volerait ses clics : la file est testée
   AVANT le bourgeon dans le raycast.

   `let` : recalculé à chaque changement de stand (voir applyStandVisuals). */
function tailleClient(){
  const cle  = gsAssetStand('client');
  const meta = metaPourAsset(cle);
  const box  = boitePourAsset(cle);
  return {
    cle: cle,
    largeur: clientHeight * (meta.w * (box[2] - box[0])) / (meta.h * (box[3] - box[1]))
  };
}
let clientWidth = tailleClient().largeur;

let clientsEnabled = false;
let clientProchain = 4;              // secondes avant la prochaine arrivée
let clientDernierServiAt = 0;
let clientNumero = 0;                // numéro affiché dans la console
const clients = [];                  // ceux présents en ce moment
const clientGroup = new THREE.Group();
scene.add(clientGroup);

/* Ce que vaut une commande, en ventes à la main. Grandit avec le stand pour
   rester intéressant, mais reste plafonné (voir le garde-fou plus haut). */
function commandeTaille(){
  return Math.max(3, Math.min(GS_CLIENT_TAILLE_MAX, 2 + standLevel() * 2));
}

function faireJaugePatience(){
  const g = new THREE.Group();
  const fond = new THREE.Mesh(
    new THREE.PlaneGeometry(0.78, 0.11),
    new THREE.MeshBasicMaterial({color:0x11170F, transparent:true, opacity:0.85, depthTest:false})
  );
  const jauge = new THREE.Mesh(
    new THREE.PlaneGeometry(0.74, 0.07),
    new THREE.MeshBasicMaterial({color:0x7BD46A, depthTest:false})
  );
  jauge.position.z = 0.01;
  g.add(fond, jauge);
  g.renderOrder = 999;              // toujours lisible, même derrière un objet
  return {groupe: g, jauge: jauge};
}

function creerClient(){
  const libres = GS_CLIENT_SLOTS.map((_, i) => i).filter(i => !clients.some(c => c.slot === i));
  if(!libres.length) return;
  const slot = libres[Math.floor(Math.random() * libres.length)];
  const [x, z] = GS_CLIENT_SLOTS[slot];

  const g = new THREE.Group();
  const cleClient = gsAssetStand('client');
  const mat = new THREE.MeshBasicMaterial({
    map: TEXTURES[cleClient] || TEXTURES.client,
    transparent:true, alphaTest:0.15, side:THREE.DoubleSide
  });
  const geoClient = new THREE.PlaneGeometry(clientWidth, clientHeight);
  recadrerUV(geoClient, boitePourAsset(cleClient));
  const corps = new THREE.Mesh(geoClient, mat);
  corps.position.y = clientHeight / 2;
  g.add(corps);

  const pat = faireJaugePatience();
  pat.groupe.position.y = clientHeight + 0.22;
  g.add(pat.groupe);

  // Il entre par la gauche et rejoint sa place au comptoir.
  g.position.set(-7, 0, z);
  clientGroup.add(g);

  // La patience est fixée à l'arrivée : un effet qui se termine pendant qu'un
  // client attend ne doit pas lui rallonger la sienne en cours de route.
  const patienceMax = GS_CLIENT_PATIENCE * gsEffetMult('patience');
  // La variété est tirée UNE fois, à l'arrivée : c'est elle qui fixe le prix
  // de la commande, servirClient() ne fait que la relire.
  const variete = tirerVariete();
  const c = {
    g: g, corps: corps, jauge: pat.jauge, jaugeGroupe: pat.groupe,
    slot: slot, cibleX: x, etat: 'arrive',
    patience: patienceMax, patienceMax: patienceMax,
    taille: commandeTaille(), sortieX: 7.5,
    numero: ++clientNumero, variete: variete
  };
  clients.push(c);

  // Sa fiche au comptoir. On lui passe DE QUOI le servir : la console ne connaît
  // pas servirClient(), et n'a pas à aller la chercher.
  const phrase = variete.phrases[Math.floor(Math.random() * variete.phrases.length)];
  c.fiche = gsConsoleClient(c.numero, phrase, variete, ()=> servirClient(c));
  gsConsoleMajAttente(clients.filter(x => x.etat !== 'part').length);
}

function retirerClient(c){
  clientGroup.remove(c.g);
  const i = clients.indexOf(c);
  if(i >= 0) clients.splice(i, 1);
}

/* Encaissement. Passe par sell() ? Non : sell() est le chemin du clic sur le
   bourgeon, avec son filtre anti-macro. Ici le clic a déjà été validé par ce
   même filtre avant d'arriver, et la commande vaut plusieurs ventes d'un coup. */
function servirClient(c){
  const now = performance.now();
  if(c.etat !== 'attend' || gsStandFerme()) return false;
  if(now - clientDernierServiAt < GS_CLIENT_COOLDOWN) return false;
  clientDernierServiAt = now;

  // Le prix suit la variété demandée : la première du stand (×1) est son
  // tarif de base, les autres coûtent logiquement plus cher — voir gsVarietes().
  // state.clientValueMult vient du niveau de l'objet « client » (Améliorations) :
  // lui seul agit sur ce que rapporte un client servi, sans toucher aux ventes
  // manuelles ni au revenu passif.
  const multVariete = c.variete ? c.variete.mult : 1;
  const gain = state.clickValue * c.taille * multVariete * (state.clientValueMult || 1);
  state.money              += gain;
  state.totalEarned        += gain;
  state.lifetimeTotalEarned += gain;
  state.manualSales        += c.taille;
  state.lifetimeManualSales += c.taille;
  state.clientsServed       = (state.clientsServed || 0) + 1;

  c.etat = 'part';
  c.jaugeGroupe.visible = false;
  if(c.fiche) c.fiche.fin(true, fmt(gain));
  gsConsoleMajAttente(clients.filter(x => x.etat !== 'part').length);

  // Le montant s'affiche à l'écran, à l'endroit du client.
  const p = c.g.position.clone();
  p.y += clientHeight;
  p.project(camera);
  const rect = renderer.domElement.getBoundingClientRect();
  const fl = document.createElement('div');
  fl.className = 'floater';
  fl.textContent = '+' + fmt(gain) + ' · ' + c.taille + ' ventes'
    + (c.variete ? ' · ' + c.variete.nom : '');
  fl.style.left = (rect.left + (p.x * 0.5 + 0.5) * rect.width) + 'px';
  fl.style.top  = (rect.top  + (-p.y * 0.5 + 0.5) * rect.height) + 'px';
  fl.style.color = '#FFD98A';
  document.body.appendChild(fl);
  setTimeout(()=> fl.remove(), 800);

  playCash();
  renderTop(); refreshShopPrices(); renderDistributeur();
  checkAchievements();
  return true;
}

function clientPerdu(c){
  c.etat = 'part';
  c.jaugeGroupe.visible = false;
  state.clientsLost = (state.clientsLost || 0) + 1;
  if(c.fiche) c.fiche.fin(false, '');
  gsConsoleMajAttente(clients.filter(x => x.etat !== 'part').length);
  renderTop();
}

/* On remplace ici les coquilles posées plus haut (avant la scène 3D) par les
   vraies implémentations, qui seules connaissent `clients` et `clientProchain`. */

// Secondes avant le prochain client, ou null si ça ne veut rien dire en ce
// moment (quelqu'un attend déjà, la file est pleine, les clients sont
// désactivés). C'est ce null qui masque le compteur côté console.
gsProchainClientSecondes = function(){
  if(!clientsEnabled) return null;
  if(clients.length) return null;          // quelqu'un est déjà en scène : pas d'accalmie à annoncer
  return Math.max(0, clientProchain);
};

// Appelée en boucle par le tick de la scène tant que la case « Distribuer
// automatiquement » est cochée. Elle ne fait rien de plus qu'un clic sur le
// bouton « Servir » du client le plus ancien déjà au comptoir : même
// fonction, même cooldown, même prix — juste sans avoir à cliquer.
gsAutoServirClients = function(){
  if(!gsAutoServeOn || gsStandFerme()) return;
  const cible = clients.find(c => c.etat === 'attend');
  if(cible) servirClient(cible);
};

/* Avancement de la file, appelé à chaque image. */
function majClients(dt){
  if(!clientsEnabled){
    while(clients.length) retirerClient(clients[0]);
    return;
  }

  clientProchain -= dt;
  if(clientProchain <= 0 && clients.length < GS_CLIENT_MAX){
    creerClient();
    const attente = GS_CLIENT_DELAI_MIN + Math.random() * (GS_CLIENT_DELAI_MAX - GS_CLIENT_DELAI_MIN);
    // Une heure de pointe divise l'attente entre deux arrivées.
    clientProchain = attente / gsEffetMult('arrivees');
  }

  for(let i = clients.length - 1; i >= 0; i--){
    const c = clients[i];

    if(c.etat === 'arrive'){
      c.g.position.x += dt * 3.4;
      // Petit rebond de marche : sans lui, le client glisse comme un carton.
      c.g.position.y = Math.abs(Math.sin(c.g.position.x * 4)) * 0.05;
      if(c.g.position.x >= c.cibleX){
        c.g.position.x = c.cibleX;
        c.g.position.y = 0;
        c.etat = 'attend';
        if(c.fiche) c.fiche.pret();
      }
    } else if(c.etat === 'attend'){
      c.patience -= dt;
      const reste = Math.max(0, c.patience / c.patienceMax);
      c.jauge.scale.x = Math.max(0.001, reste);
      // La jauge se vide par la gauche, pas depuis son centre.
      c.jauge.position.x = -0.37 * (1 - reste);
      // Verte, puis orange, puis rouge : lisible d'un coup d'œil.
      c.jauge.material.color.setHex(reste > 0.5 ? 0x7BD46A : (reste > 0.22 ? 0xD9A441 : 0xC8503C));
      if(c.fiche) c.fiche.maj(reste);
      if(c.patience <= 0) clientPerdu(c);
    } else {
      c.g.position.x += dt * 4.2;
      if(c.g.position.x > c.sortieX) retirerClient(c);
    }

    // Les découpes restent face à la caméra malgré la légère rotation de la scène.
    c.g.rotation.y = -clientGroup.rotation.y;
  }
}

/* ======================================================================
   CHANGER DE STAND — côté scène
   ======================================================================
   Rejoue tout ce qui porte l'identité du comptoir : le ciel, le mur du fond, le
   liseré du comptoir, la lumière d'appoint, l'enseigne, l'auvent, la guirlande,
   le bourgeon central, les modèles posés sur le stand et les clients déjà là.

   On REPART des textures plutôt que de les teinter : une teinte multiplie la
   couleur d'origine, donc un stand blanc posé sur un décor vert donnerait du
   vert pâle, jamais du blanc. Les fonds sont dessinés au canvas — les redessiner
   coûte quelques millisecondes, une fois, au moment du changement.
   ====================================================================== */
applyStandVisuals = function(){
  const pal = gsPalette();

  // 1. les fonds
  scene.background = buildSkyTexture();
  if(backWall.material.map) backWall.material.map.dispose();
  backWall.material.map = buildBackWallTexture();
  backWall.material.needsUpdate = true;

  // 2. le liseré du comptoir et la lumière d'appoint, qui donnent sa couleur à
  //    tout le mobilier
  trim.material.color.set(pal.accent);
  trim.material.emissive.set(pal.accentDark);
  rim2.color.set(pal.accent);

  // 3. l'enseigne, l'auvent et la guirlande : applyStandDecor() les reconstruit
  //    entièrement, il suffit de lui faire oublier le palier déjà posé.
  currentDecorLevel = -1;
  applyStandDecor(standLevel());

  // 4. les produits posés sur le stand : on vide, refreshStandModels() repose
  //    tout avec les images du nouveau comptoir.
  while(itemGroup.children.length){ itemGroup.remove(itemGroup.children[0]); }
  Object.keys(placed).forEach(k => delete placed[k]);
  Object.keys(posePar).forEach(k => delete posePar[k]);
  Object.keys(fumeePosee).forEach(k => delete fumeePosee[k]);

  // 5. le bourgeon central. Il n'est pas dans itemGroup : c'est le produit
  //    cliquable, il se remplace à part (au bon palier de la BazeKush).
  const baze = ITEMS.find(it => it.id === 'bazekush');
  majBourgeonCentral((baze && baze.level > 0 && assetPourObjet(baze)) || gsAssetStand('nug_big'));

  // 6. les clients déjà au comptoir changent de tête eux aussi, sinon le stand
  //    est blanc et la file est encore verte.
  const t = tailleClient();
  clientWidth = t.largeur;
  clients.forEach(c => {
    c.corps.material.map = TEXTURES[t.cle] || TEXTURES.client;
    c.corps.material.needsUpdate = true;
    c.corps.geometry.dispose();
    const geo = new THREE.PlaneGeometry(clientWidth, clientHeight);
    recadrerUV(geo, boitePourAsset(t.cle));
    c.corps.geometry = geo;
  });

  refreshStandModels();
};

resetStandVisuals = function(){
  while(itemGroup.children.length){ itemGroup.remove(itemGroup.children[0]); }
  // Le bourgeon central n'est pas dans itemGroup : il se remet à sa base à part,
  // sinon un stand remis à zéro garderait le modèle doré de la BazeKush 50.
  majBourgeonCentral(gsAssetStand('nug_big'));
  Object.keys(slotSecoursPris).forEach(k => delete slotSecoursPris[k]);
  Object.keys(placed).forEach(k => delete placed[k]);
  Object.keys(posePar).forEach(k => delete posePar[k]);
  Object.keys(fumeePosee).forEach(k => delete fumeePosee[k]);
  currentDecorLevel = -1;
  applyStandDecor(1);
  while(clients.length) retirerClient(clients[0]);
  clientProchain = 4;
};


/* La largeur de stand qu'on veut TOUJOURS voir, en unités de la scène. Les
   emplacements d'objets vont de -3,1 à +3,1 : il faut un peu plus pour que rien
   ne touche le bord. Les variétés du premier rang sont moins écartées (-1,55 à
   +1,55) mais plus près de la caméra : à l'écran elles reviennent au même, et
   tiennent donc dans ce cadre-là. */
const SCENE_LARGEUR_VISEE = 8.6;
const CAMERA_Z_MIN = 7.2;      // le recul d'origine, sur un cadre large

function onResize(){
  const w = frame.clientWidth, h = frame.clientHeight;
  // Cadre masqué (onglet replié, accordéon fermé, transition) : w/h vaut 0/0 = NaN,
  // et une matrice de projection NaN ne se répare pas — la scène disparaît jusqu'au
  // rechargement. On ne fait rien tant qu'il n'a pas de taille.
  if(!w || !h) return;
  camera.aspect = w/h;

  // La console a rétréci le cadre, et les objets des bords sont sortis du champ :
  // une caméra à distance fixe voit d'autant moins large que le cadre est étroit.
  // On recule donc juste ce qu'il faut pour garder SCENE_LARGEUR_VISEE visible.
  // À largeur confortable le calcul redonne le recul d'origine : rien ne change.
  const demiAngleV = (39 * Math.PI / 180) / 2;
  const distance = (SCENE_LARGEUR_VISEE / 2) / (Math.tan(demiAngleV) * camera.aspect);
  camera.position.z = Math.max(CAMERA_Z_MIN, Math.min(distance, 16));
  camera.lookAt(0, 0.6, 0);

  camera.updateProjectionMatrix();
  renderer.setSize(w,h);
}
window.addEventListener('resize', onResize);

let t = 0;
let lastFrameTime = performance.now();
let targetRotY = 0, curRotY = 0;
frame.addEventListener('pointermove', (ev)=>{
  const rect = frame.getBoundingClientRect();
  const nx = ((ev.clientX-rect.left)/rect.width)*2-1;
  targetRotY = nx * 0.18;
});
function animate(){
  requestAnimationFrame(animate);
  const now = performance.now();
  const dt = Math.min((now-lastFrameTime)/1000, 0.1);
  lastFrameTime = now;
  t += 0.006;
  curRotY += (targetRotY-curRotY)*0.04;
  itemGroup.rotation.y = curRotY + Math.sin(t*0.3)*0.05;
  clickNug.rotation.y = curRotY*0.6;
  clickNug.position.y = Math.sin(t*1.6)*0.03;

  stringBulbs.forEach(b=>{
    const flick = 0.7 + 0.3*Math.sin(t*4 + b.userData.twinklePhase);
    b.material.opacity = flick;
  });

  // respiration lumineuse du liseré vert du comptoir
  trim.material.emissiveIntensity = 0.55 + 0.25*Math.sin(t*1.4);

  // léger halo pulsé sur l'enseigne néon, si elle est installée
  // C'est la nappe de lumière qui respire, pas la façade : faire varier l'opacité du
  // panneau lui-même rendrait tout le stand translucide par intermittence.
  decorGroup.children.forEach(g=>{
    if(g.userData.glow) g.userData.glow.material.opacity = 0.70 + 0.22*Math.sin(t*2.2);
  });

  smokeEmitters.forEach(e=> e.update(dt, t, camera));

  majClients(dt);
  gsAutoServirClients();

  renderer.render(scene, camera);
}
animate();

/* click-to-sell via raycast */
const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();
renderer.domElement.addEventListener('click', (ev)=>{
  const rect = renderer.domElement.getBoundingClientRect();
  pointer.x = ((ev.clientX-rect.left)/rect.width)*2-1;
  pointer.y = -((ev.clientY-rect.top)/rect.height)*2+1;
  raycaster.setFromCamera(pointer, camera);

  // Les clients sont devant le comptoir : ils passent avant le bourgeon, sinon
  // un clic sur quelqu'un debout devant le stand vendrait dans son dos.
  const surClient = raycaster.intersectObjects(clients.map(c => c.corps), false);
  if(surClient.length){
    const c = clients.find(c => c.corps === surClient[0].object);
    // Le clic passe par le même filtre anti-macro que le reste : une commande
    // vaut plusieurs ventes, ce serait la porte de service idéale sans ça.
    if(c && isHumanLikeClick(performance.now(), ev) && servirClient(c)){
      c.corps.scale.setScalar(1.12);
      setTimeout(()=> c.corps && c.corps.scale.setScalar(1), 120);
    }
    return;
  }

  const hits = raycaster.intersectObject(clickNug, true);
  if(hits.length){
    if(sell(ev.clientX, ev.clientY, ev)){
      clickNug.scale.setScalar(1.18);
      setTimeout(()=>clickNug.scale.setScalar(1), 120);
    }
  }
});

} catch(err) {
  scene3dOk = false;
  console.error('Scène 3D indisponible, passage en mode simplifié :', err);
  const frame = document.getElementById('sceneFrame');
  if (frame) {
  frame.innerHTML = '<div style="display:flex;flex-direction:column;gap:14px;align-items:center;justify-content:center;height:100%;padding:20px;text-align:center;color:#EDEAE0;font-family:JetBrains Mono,monospace;font-size:12px;">Rendu 3D indisponible dans ce navigateur.<br>Le jeu reste jouable ci-dessous.<button id="fallbackSell" style="all:unset;cursor:pointer;background:#2E5A26;color:#D8F0CE;padding:12px 22px;border-radius:10px;font-weight:700;">Vendre 🌿</button></div>';
  document.getElementById('fallbackSell').addEventListener('click', (ev)=> sell(ev.clientX, ev.clientY, ev));
  }
}
}

/* ======================================================================
   GAME STATE / SHOP
   ====================================================================== */
const state = { money:0, totalEarned:0, clickValue:0.10, perSec:0, clientValueMult:1, manualSales:0, itemsBought:0, prestigeSeeds:0,
  clientsServed:0, clientsLost:0,
  lifetimeManualSales:0, lifetimeItemsBought:0, lifetimeTotalEarned:0, critCount:0, unlockedAchievements:[],
  gsSyncedLifetime:0 };

// Les améliorations et les gérants sont désormais définis UNE SEULE FOIS, côté serveur
// (greenstand_items() / greenstand_managers() dans greenstand_lib.php), et envoyés ici.
//
// Ce n'est pas une coquetterie : c'est ce qui permet au serveur de recalculer lui-même
// ce qu'un joueur est censé produire, et donc de plafonner un dépôt sur son stand réel
// au lieu d'un chiffre rond. Rééquilibrer se fait dans le fichier PHP, à un seul endroit.
const ITEMS = <?= json_encode(greenstand_items(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const GS_SOFT_CAP  = <?= (int) GREENSTAND_SOFT_CAP ?>;
const GS_LATE_MULT = <?= (float) GREENSTAND_LATE_MULT ?>;
ITEMS.forEach(it => it.level = 0);

/* ---------------- GÉRANTS (2e couche de progression) ----------------
   Contrairement aux améliorations ci-dessus (bonus additifs), les gérants
   appliquent un multiplicateur qui grandit avec leur niveau (mult^niveau).
   Ils sont volontairement chers pour rester un objectif de mi/fin de partie. */
const MANAGERS = <?= json_encode(greenstand_managers(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
MANAGERS.forEach(m => m.level = 0);
function managerMultiplier(effectType){
  return MANAGERS
    .filter(m => m.effect === effectType && m.level > 0)
    .reduce((acc, m) => acc * Math.pow(m.mult, m.level), 1);
}
function managerCostDiscount(){
  const acc = MANAGERS.find(m => m.id === 'accountant');
  const gerant = (!acc || acc.level <= 0) ? 1 : Math.pow(acc.mult, acc.level);
  // Le « Comptable de l'ombre » (boost de franchise) s'ajoute au gérant Comptable :
  // l'un disparaît à la revente, l'autre survit.
  const b = GS_BOOSTS.find(x => x.effect === 'cost');
  const boost = b ? Math.max(0.1, 1 - b.step * gsBoostLevel(b.id)) : 1;
  // Un lot acheté à un fournisseur de passage fait baisser les prix un moment.
  return gerant * boost * gsEffetMult('cout');
}

/* ---------------- SAUVEGARDE AUTOMATIQUE (localStorage) ---------------- */
const SAVE_KEY = 'greenstand_save_v1';

/* ---------------- PRESTIGE ---------------- */
/* Le prestige — « Revendre le stand », les graines, le bonus permanent — a été retiré
   du jeu. Il reste deux couches de progression, et c'est volontaire : les améliorations,
   qui s'additionnent, et les gérants, qui multiplient. Pas de remise à zéro à négocier,
   pas de seconde monnaie à comprendre.

   state.prestigeSeeds reste dans la sauvegarde, figé à zéro : le serveur valide les
   sauvegardes clé par clé (greenstand_normalize_save_state) et retirer le champ ferait
   échouer la validation de toutes celles déjà en base. */

/* ---------------- SUCCÈS ---------------- */
/* Les badges viennent du serveur (gs_badges), pas d'une liste écrite ici : le panel
   admin doit pouvoir en ajouter, en modifier et en supprimer sans rouvrir ce fichier.

   Une condition n'est pas du code mais un couple (statistique, seuil) pris dans une
   liste fermée — un badge créé depuis le panel ne peut donc rien exécuter. */
function badgeStatValue(s, stat){
  switch(stat){
    case 'standLevel':    return standLevel();
    // Le meilleur comptoir : un succès déjà en cours ne recule pas parce qu'on
    // vient d'en ouvrir un autre.
    case 'managerLevels': return gsMeilleurTotalGerants();
    case 'franchises':    return Number(gsGold && gsGold.franchises) || 0;
    default:              return Number(s[stat]) || 0;
  }
}
/* Un nombre écrit comme dans le jeu : 3 100 000, sans décimales. */
function gsNombreRond(v){
  return Math.round(Number(v) || 0).toLocaleString('fr-FR');
}

/* La liste complète des succès, reconstruite depuis la charge utile compacte.
   Les badges de l'admin d'abord (ce sont eux qui portent les vraies images et les
   vraies récompenses), puis les mille paliers. */
const ACHIEVEMENTS = (function(){
  const out = [];
  (GS_SUCCES.mains || []).forEach(b=>{
    out.push({
      id: b.id, name: b.name, desc: b.desc, icon: b.emoji, img: '',
      stat: b.stat, value: Number(b.value) || 0, famille: 'Badges du stand',
      reward: { cash: Number(b.cash) || 0, multBonus: Number(b.mult) || 0 },
    });
  });
  (GS_SUCCES.autos || []).forEach(ligne=>{
    const [id, iStat, valeur, cash, mult, iFam, iRang] = ligne;
    const f = GS_SUCCES.familles[iFam] || {nom:'Succès', unite:'', emoji:'🏅', img:''};
    out.push({
      id: id, name: f.nom + ' ' + (GS_SUCCES.rangs[iRang] || ''),
      desc: gsNombreRond(valeur) + (f.argent ? ' €' : '') + ' ' + f.unite,
      icon: f.emoji, img: f.img, stat: GS_SUCCES.stats[iStat],
      value: Number(valeur) || 0, famille: f.nom,
      reward: { cash: Number(cash) || 0, multBonus: Number(mult) || 0 },
    });
  });
  out.forEach(a => { a.check = s => badgeStatValue(s, a.stat) >= a.value; });
  return out;
})();

/* Mille succès, c'est mille comparaisons à chaque vente si on s'y prend mal.
   Les paliers d'une même statistique forment une ÉCHELLE : on les range par
   seuil, et on ne teste que le premier pas encore décroché. Un clic coûte alors
   neuf comparaisons, pas mille.

   Un ensemble pour l'appartenance, aussi : `includes` sur un tableau de mille
   identifiants, appelé à chaque clic, se voyait à la manette. */
const ACH_PAR_STAT = {};
const ACH_CURSEUR  = {};
let   ACH_DEBLOQUES = new Set();

ACHIEVEMENTS.forEach((a, i)=>{
  (ACH_PAR_STAT[a.stat] = ACH_PAR_STAT[a.stat] || []).push(i);
});
Object.keys(ACH_PAR_STAT).forEach(stat=>{
  ACH_PAR_STAT[stat].sort((x, y)=> ACHIEVEMENTS[x].value - ACHIEVEMENTS[y].value);
  ACH_CURSEUR[stat] = 0;
});

/* À rappeler après un chargement ou une fusion de sauvegarde : la liste des
   succès débloqués vient de changer sous les pieds des curseurs. */
function gsAchSynchroniser(){
  ACH_DEBLOQUES = new Set(state.unlockedAchievements || []);
  Object.keys(ACH_PAR_STAT).forEach(stat=>{
    let i = 0;
    const liste = ACH_PAR_STAT[stat];
    while(i < liste.length && ACH_DEBLOQUES.has(ACHIEVEMENTS[liste[i]].id)) i++;
    ACH_CURSEUR[stat] = i;
  });
}

function achievementMultBonus(){
  return ACHIEVEMENTS
    .filter(a => state.unlockedAchievements.includes(a.id) && a.reward.multBonus)
    .reduce((sum,a) => sum + a.reward.multBonus, 0);
}

function checkAchievements(){
  const unlockedNow = [];
  Object.keys(ACH_PAR_STAT).forEach(stat=>{
    const liste = ACH_PAR_STAT[stat];
    const valeur = badgeStatValue(state, stat);
    let i = ACH_CURSEUR[stat];
    // On avance tant que le palier suivant est atteint. Comme la liste est triée,
    // le premier palier hors de portée arrête la boucle : le reste l'est aussi.
    while(i < liste.length){
      const a = ACHIEVEMENTS[liste[i]];
      if(ACH_DEBLOQUES.has(a.id)){ i++; continue; }
      if(valeur < a.value) break;
      ACH_DEBLOQUES.add(a.id);
      state.unlockedAchievements.push(a.id);
      if(a.reward.cash){ state.money += a.reward.cash; state.totalEarned += a.reward.cash; state.lifetimeTotalEarned += a.reward.cash; }
      unlockedNow.push(a);
      i++;
    }
    ACH_CURSEUR[stat] = i;
  });
  if(unlockedNow.length){
    recomputeDerived();
    renderAll();
    saveGame();
    // Une reprise de partie peut en débloquer cinquante d'un coup : on annonce les
    // trois premiers, puis on compte. Cinquante bulles à la suite, c'est une minute
    // pendant laquelle le jeu est illisible.
    unlockedNow.slice(0, 3).forEach((a, i)=> setTimeout(()=> showAchievementToast(a), i * 900));
    if(unlockedNow.length > 3){
      setTimeout(()=> gsShowSyncNote('+' + (unlockedNow.length - 3) + ' autres succès débloqués'), 2900);
    }
  }
}

function showAchievementToast(a){
  const toast = document.createElement('div');
  toast.className = 'ach-toast';
  toast.innerHTML = '<span class="ach-toast-icon">' + a.icon + '</span><span><b>Succès débloqué</b><br>' + a.name + (a.reward.cash ? (' · +' + fmt(a.reward.cash)) : (a.reward.multBonus ? (' · +' + Math.round(a.reward.multBonus*100) + '% permanent') : '')) + '</span>';
  document.body.appendChild(toast);
  playAchievementSound();
  requestAnimationFrame(()=> toast.classList.add('show'));
  setTimeout(()=>{
    toast.classList.remove('show');
    setTimeout(()=> toast.remove(), 400);
  }, 3200);
}

/* L'image d'un succès : celle que l'admin a posée pour ce badge, sinon le visuel
   du jeu que sa famille réutilise, sinon son pictogramme. Aucun succès généré n'a
   demandé une seule image nouvelle. */
function gsSuccesImage(a){
  return ASSET_DATA['badge_' + a.id] || (a.img ? ASSET_DATA[a.img] : '') || '';
}

/* La vignette de la page du stand. Elle n'affiche PLUS les mille : mille cartes
   sous le comptoir, personne ne les regarde et le navigateur rame. On montre les
   derniers décrochés et les tout prochains, et la page Succès fait le reste. */
function renderAchievements(){
  const grid = document.getElementById('achGrid');
  const countEl = document.getElementById('achCount');
  if(!grid) return;
  if(countEl) countEl.textContent = state.unlockedAchievements.length + '/' + ACHIEVEMENTS.length;

  const faits = state.unlockedAchievements.slice(-8).reverse()
    .map(id => ACHIEVEMENTS.find(a => a.id === id)).filter(Boolean);
  const aVenir = gsProchainsSucces(4);

  grid.innerHTML = '';
  faits.concat(aVenir).forEach(a=>{
    const unlocked = ACH_DEBLOQUES.has(a.id);
    const el = document.createElement('div');
    el.className = 'ach-badge ' + (unlocked ? 'unlocked' : 'locked');
    el.title = a.name + ' — ' + a.desc;
    const url = unlocked ? gsSuccesImage(a) : '';
    el.innerHTML = url
      ? '<img class="ach-img" src="' + url + '" alt="">'
      : '<span class="ach-icon">' + (unlocked ? a.icon : '🔒') + '</span>';
    const nom = document.createElement('span');
    nom.className = 'ach-name';
    nom.textContent = a.name;
    el.appendChild(nom);
    grid.appendChild(el);
  });
}

/* Les prochains paliers à tomber, un par famille : c'est ce qui donne un cap. */
function gsProchainsSucces(combien){
  const out = [];
  Object.keys(ACH_PAR_STAT).forEach(stat=>{
    const liste = ACH_PAR_STAT[stat];
    for(let i = ACH_CURSEUR[stat]; i < liste.length; i++){
      const a = ACHIEVEMENTS[liste[i]];
      if(ACH_DEBLOQUES.has(a.id)) continue;
      out.push({a: a, reste: a.value - badgeStatValue(state, stat)});
      break;
    }
  });
  // Le plus proche du but d'abord, en proportion : « il te manque 3 clients » est
  // un meilleur objectif que « il te manque 400 milliards d'euros ».
  out.sort((x, y)=> (x.reste / Math.max(1, x.a.value)) - (y.reste / Math.max(1, y.a.value)));
  return out.slice(0, combien).map(o => o.a);
}

/* ======================================================================
   LA PAGE DES SUCCÈS
   ======================================================================
   Mille paliers rangés par famille, avec la progression de chacune. Le filtre et
   la recherche existent parce qu'une liste de mille lignes sans eux n'est pas une
   liste, c'est un mur.
   ====================================================================== */
let gsSuccesVue = 'tous';
let gsSuccesQ = '';
/* Les familles qu'on a demandé à voir en entier. Par défaut on n'affiche qu'une
   fenêtre autour de là où le joueur en est : mille cartes d'un coup, c'est dix
   mille pixels de page où l'on ne trouve plus rien. */
const gsSuccesOuvertes = new Set();

function renderSucces(){
  const hote = document.getElementById('gsSuccesListe');
  if(!hote) return;

  const total = document.getElementById('gsSuccesTotal');
  if(total) total.textContent = state.unlockedAchievements.length + ' / ' + ACHIEVEMENTS.length;

  // Rangés par famille, dans l'ordre où ils arrivent (les badges du stand d'abord).
  const familles = [];
  const parNom = {};
  ACHIEVEMENTS.forEach(a=>{
    if(!parNom[a.famille]){ parNom[a.famille] = []; familles.push(a.famille); }
    parNom[a.famille].push(a);
  });

  hote.innerHTML = '';
  let affiches = 0;
  familles.forEach(nom=>{
    const liste = parNom[nom];
    const faits = liste.filter(a => ACH_DEBLOQUES.has(a.id)).length;
    const visibles = liste.filter(a=>{
      const fait = ACH_DEBLOQUES.has(a.id);
      if(gsSuccesVue === 'faits' && !fait) return false;
      if(gsSuccesVue === 'restants' && fait) return false;
      if(gsSuccesQ && (a.name + ' ' + a.desc).toLowerCase().indexOf(gsSuccesQ) < 0) return false;
      return true;
    });
    if(!visibles.length) return;

    const bloc = document.createElement('div');
    bloc.className = 'gs-suc-famille';

    const tete = document.createElement('div');
    tete.className = 'gs-suc-tete';
    const t1 = document.createElement('b'); t1.textContent = nom;
    const t2 = document.createElement('span'); t2.textContent = faits + ' / ' + liste.length;
    const t3 = document.createElement('span');
    t3.className = 'p';
    t3.textContent = Math.round(faits / liste.length * 100) + ' %';
    tete.append(t1, t2, t3);

    const barre = document.createElement('div');
    barre.className = 'gs-suc-barre';
    const jauge = document.createElement('span');
    jauge.style.width = (faits / liste.length * 100) + '%';
    barre.appendChild(jauge);

    const grille = document.createElement('div');
    grille.className = 'gs-suc-grille';

    // Quoi montrer : tout si on a filtré, cherché, ou demandé à voir la famille
    // en entier ; sinon une fenêtre autour du premier palier pas encore décroché,
    // avec les derniers obtenus juste avant — c'est là qu'on veut regarder.
    const filtre = gsSuccesQ || gsSuccesVue !== 'tous';
    const tout = filtre || gsSuccesOuvertes.has(nom);
    let montres = visibles;
    if(!tout && visibles.length > 24){
      let premierRestant = visibles.findIndex(a => !ACH_DEBLOQUES.has(a.id));
      if(premierRestant < 0) premierRestant = visibles.length - 1;
      const debut = Math.max(0, Math.min(premierRestant - 4, visibles.length - 24));
      montres = visibles.slice(debut, debut + 24);
    }
    montres.forEach(a=> grille.appendChild(gsCarteSucces(a)));
    affiches += montres.length;

    if(!tout && montres.length < visibles.length){
      const plus = document.createElement('button');
      plus.className = 'gs-suc-plus';
      plus.type = 'button';
      plus.textContent = 'Voir les ' + visibles.length + ' paliers de « ' + nom + ' »';
      plus.addEventListener('click', ()=>{ gsSuccesOuvertes.add(nom); renderSucces(); });
      grille.appendChild(plus);
    } else if(!filtre && gsSuccesOuvertes.has(nom) && visibles.length > 24){
      const moins = document.createElement('button');
      moins.className = 'gs-suc-plus';
      moins.type = 'button';
      moins.textContent = 'Replier « ' + nom + ' »';
      moins.addEventListener('click', ()=>{ gsSuccesOuvertes.delete(nom); renderSucces(); });
      grille.appendChild(moins);
    }

    bloc.append(tete, barre, grille);
    hote.appendChild(bloc);
  });

  const compte = document.getElementById('gsSuccesCompte');
  if(compte) compte.textContent = affiches + ' affiché(s) sur ' + ACHIEVEMENTS.length;
  if(!affiches) hote.innerHTML = '<div class="gs-board-empty">Aucun succès ne correspond.</div>';
}

function gsCarteSucces(a){
  const fait = ACH_DEBLOQUES.has(a.id);
  const el = document.createElement('div');
  el.className = 'gs-suc' + (fait ? ' fait' : '');
  el.title = a.desc + (a.reward.multBonus ? (' — +' + Math.round(a.reward.multBonus * 1000) / 10 + '% permanent') : '')
           + (a.reward.cash ? (' — ' + fmt(a.reward.cash)) : '');

  const ic = document.createElement('div');
  ic.className = 'ic';
  const url = fait ? gsSuccesImage(a) : '';
  if(url){
    const img = document.createElement('img');
    img.src = url; img.alt = ''; img.loading = 'lazy';
    ic.appendChild(img);
  } else {
    ic.textContent = fait ? a.icon : '🔒';
  }

  const tx = document.createElement('div');
  tx.className = 'tx';
  const n = document.createElement('div'); n.className = 'n'; n.textContent = a.name;
  const d = document.createElement('div'); d.className = 'd'; d.textContent = a.desc;
  tx.append(n, d);

  el.append(ic, tx);
  return el;
}

function itemUnitGain(it, level){
  // gain marginal apporté par l'achat du niveau (level+1), en partant de level=0.
  // Le palier de prix se lit au niveau RÉSULTANT (level+1) : c'est lui qui décide
  // si cet achat précis tombe dans la tranche basique, holo ou SKITEELZ.
  const base = it.clickAdd + it.autoAdd + (it.offlineCapAdd || 0);
  const brut = base * Math.pow(it.gainMult || 1, level);
  return brut * tierPrixMultAuNiveau(it.icon, level + 1);
}
function itemTotalContribution(it){
  // somme des gains de tous les niveaux déjà achetés (série géométrique)
  if(it.level <= 0) return 0;
  const base = it.clickAdd + it.autoAdd + (it.offlineCapAdd || 0);
  const r = it.gainMult || 1;
  if(!TIER_PRIX_MULT[it.icon]){
    // Comportement historique, inchangé pour tout objet sans palier de prix.
    if(r === 1) return base * it.level;
    return base * (Math.pow(r, it.level) - 1) / (r - 1);
  }
  // Objet à paliers de prix : la somme géométrique se découpe par tranche
  // (niveaux 1-9, 10-24, 25-49, 50+), chacune à son propre tarif — voir
  // TIER_PRIX_MULT plus haut pour pourquoi ce n'est pas rétroactif.
  // `bornes` s'exprime en INDICE d'achat (0-based) : l'achat d'indice L amène
  // l'objet au niveau affiché L+1, donc la frontière d'un palier au niveau
  // affiché N tombe à l'indice N-1.
  const bornes = [0, 9, 24, 49, it.level];
  const mults  = [1, tierPrixMultAuNiveau(it.icon, 10), tierPrixMultAuNiveau(it.icon, 25), tierPrixMultAuNiveau(it.icon, 50)];
  let total = 0;
  for(let i = 0; i < 4; i++){
    const from = bornes[i], to = Math.min(bornes[i + 1], it.level);
    if(to <= from) continue;
    const part = (r === 1) ? base * (to - from) : base * (Math.pow(r, to) - Math.pow(r, from)) / (r - 1);
    total += part * mults[i];
  }
  return total;
}
function recomputeDerived(){
  let cv = 0.10, ps = 0;
  ITEMS.forEach(it => {
    const contrib = itemTotalContribution(it);
    if(it.clickAdd > 0) cv += contrib;
    if(it.autoAdd > 0) ps += contrib;
  });
  // Les succès et les boosts de franchise sont les bonus qui survivent aux reventes.
  const globalBonus = 1 + achievementMultBonus();
  const globalMgrMult = managerMultiplier('global');
  const clickMgrMult = managerMultiplier('click') * globalMgrMult;
  const autoMgrMult = managerMultiplier('auto') * globalMgrMult;
  const franchiseBoost = effect => {
    const b = GS_BOOSTS.find(x => x.effect === effect);
    return b ? 1 + b.step * gsBoostLevel(b.id) : 1;
  };
  // Le stand tenu. Il ne touche à AUCUN niveau : il multiplie ce que les mêmes
  // améliorations rapportent, différemment selon le comptoir (le BrownStand paie
  // au clic, le BeigeStand en automatique, le WhiteStand les deux mais avec une
  // clientèle rare). Le serveur applique exactement les mêmes facteurs quand il
  // calcule le plafond d'un dépôt — voir greenstand_stand_autorise().
  const stand = gsStandCourant();
  state.clickValue = cv * globalBonus * clickMgrMult * franchiseBoost('click') * (Number(stand.clickMult) || 1);
  state.perSec = ps * globalBonus * autoMgrMult * franchiseBoost('persec') * (Number(stand.autoMult) || 1);

  // « client » n'ajoute rien à clickValue/perSec (clickAdd et autoAdd valent 0
  // pour cet objet) : c'est un multiplicateur à part, qui ne joue QUE sur ce
  // que rapporte un client servi — pas les ventes manuelles. Un objet non
  // rétabli après un reset renvoie 1 (aucun bonus), pas une erreur.
  const clientItem = ITEMS.find(it => it.id === 'client');
  state.clientValueMult = (clientItem ? Math.pow(clientItem.clientMult || 1, clientItem.level) : 1)
    * (Number(stand.clientMult) || 1);
}

function gameSaveData(){
  return {
    money: Math.round(state.money * 100) / 100,
    totalEarned: state.totalEarned, manualSales: state.manualSales,
    itemsBought: state.itemsBought, prestigeSeeds: state.prestigeSeeds,
    lifetimeManualSales: state.lifetimeManualSales, lifetimeItemsBought: state.lifetimeItemsBought,
    lifetimeTotalEarned: state.lifetimeTotalEarned, critCount: state.critCount,
    clientsServed: state.clientsServed, clientsLost: state.clientsLost,
    unlockedAchievements: state.unlockedAchievements, levels: ITEMS.map(it => it.level),
    managerLevels: MANAGERS.map(m => m.level),
    // Les niveaux des QUATRE comptoirs. Ceux du comptoir tenu sont rangés à
    // l'instant : 'levels' ci-dessus et gsStandLevels[gsStandId] disent donc
    // toujours la même chose. Déclaré aussi dans greenstand_normalize_save_state()
    // côté serveur — une clé absente de là-bas est jetée à chaque relecture.
    standLevels: (gsCapturerNiveaux(), gsStandLevels),
    gsSyncedLifetime: state.gsSyncedLifetime, savedAt: Date.now(),
    // Le comptoir tenu. Déclaré aussi dans greenstand_normalize_save_state() côté
    // serveur : une clé absente de cette liste-là est jetée à chaque relecture, et
    // le joueur repasserait au GreenStand à chaque rechargement.
    stand: gsStandId,
    resetGen: GS_RESET_GEN, wipeGen: GS_WIPE_GEN
  };
}

/* Poser le stand courant, en refusant proprement ce que les franchises du joueur
   ne permettent pas encore. Le serveur refait la même vérification de son côté :
   forcer 'white' dans la sauvegarde ne donne pas les gains du WhiteStand. */
function gsPoserStand(id, silencieux, sansCapture){
  const voulu = gsStand(id);
  const stand = gsStandDebloque(voulu) ? voulu : gsMeilleurStand();
  const change = stand.id !== gsStandId;
  // On range les niveaux du comptoir qu'on quitte AVANT de poser ceux du nouveau :
  // sans ça, changer de stand écraserait la progression du précédent.
  // `sansCapture` sert aux chargements (loadGame, fusion cloud), où les niveaux en
  // jeu sont ceux de la sauvegarde qu'on vient de lire et non ceux du stand affiché.
  if(!sansCapture) gsCapturerNiveaux(gsStandId);
  gsStandId = stand.id;
  gsAppliquerNiveaux(gsStandId);
  gsAppliquerThemeStand();
  recomputeDerived();
  if(change && !silencieux){
    gsConsoleLigne('bien', 'Tu tiens maintenant le ' + stand.name + ' — ' + stand.profil.toLowerCase() + '.');
    // Dit une fois, au bon moment : sans ça, retrouver ses améliorations à zéro
    // en changeant de comptoir ressemble à une progression perdue.
    gsConsoleLigne('info', 'Chaque comptoir a ses propres améliorations et ses propres gérants : '
      + 'ceux du stand que tu viens de quitter t\'attendent, intacts, quand tu y retournes.');
  }
  return change;
}

/**
 * Remise à zéro TOTALE, pilotée par GREENSTAND_WIPE_GEN côté serveur.
 *
 * Une sauvegarde antérieure à la génération courante est vidée : argent, total gagné,
 * compteurs, niveaux d'améliorations et de gérants, succès. Une seule fois — dès
 * qu'elle est réenregistrée elle porte la nouvelle génération.
 *
 * Ce qui n'est PAS touché : les jetons, gemmes et clés du site. Le joueur les a sortis
 * du jeu en les convertissant ; les reprendre serait lui prendre autre chose que sa
 * partie.
 *
 * Appelé sur les DEUX chemins (localStorage et cloud) : sinon le joueur perdrait sa
 * partie en local et le serveur la lui rendrait à la synchro suivante.
 */
function applyWipeGen(save){
  const gen = Number(save && save.wipeGen) || 0;
  if(gen >= GS_WIPE_GEN) return false;
  state.money = 0; state.totalEarned = 0;
  state.manualSales = 0; state.itemsBought = 0;
  state.lifetimeManualSales = 0; state.lifetimeItemsBought = 0;
  state.lifetimeTotalEarned = 0; state.critCount = 0;
  state.gsSyncedLifetime = 0;
  state.unlockedAchievements = [];
  // Les quatre comptoirs, pas seulement celui qu'on tient.
  gsViderTousLesNiveaux();
  return true;
}
let gsWiped = false;

// Ces deux-là n'étaient déclarées NULLE PART. Tout le jeu tourne dans une IIFE en
// 'use strict', où écrire dans une variable non déclarée lève une ReferenceError :
//   - gsInitCloudSave() plantait sur « gsCloudReady = true », juste après avoir
//     fusionné la sauvegarde, donc la banque, le portefeuille, les feuilles d'or et
//     de platine et les gains hors-ligne n'étaient JAMAIS appliqués. L'onglet
//     Franchise gardait les zéros du JS, en contradiction avec la base ;
//   - saveGame() plantait sur clearTimeout(gsCloudSaveTimer), donc plus aucune
//     sauvegarde cloud n'était envoyée.
// C'est la cause unique de « 1 feuille au classement, rien en réserve ».
let gsCloudReady = false;
let gsCloudSaveTimer = null;

function saveGame(){
  try {
    localStorage.setItem(SAVE_KEY, JSON.stringify(gameSaveData()));
    showSaveIndicator();
    gsQueueCloudSave();
  } catch(e) { console.warn('Sauvegarde impossible :', e); }
}

function gsQueueCloudSave(){
  if(!GS_LOGGED_IN || !gsCloudReady) return;
  clearTimeout(gsCloudSaveTimer);
  gsCloudSaveTimer = setTimeout(()=>gsCloudSave(), 1200);
}

async function gsCloudRequest(action, extra = {}){
  const fd = new FormData();
  fd.append('action', action); fd.append('csrf', GS_CSRF);
  Object.entries(extra).forEach(([key, value])=>fd.append(key, value));
  const response = await fetch('greenstand_action.php', {method:'POST', body:fd, credentials:'same-origin', cache:'no-store', headers:{'Accept':'application/json'}});
  const result = await response.json();
  if(!response.ok || !result.ok) throw new Error(result.error || 'Erreur de sauvegarde');
  return result;
}

async function gsCloudSave(){
  if(!GS_LOGGED_IN || !gsCloudReady) return;
  try { await gsCloudRequest('state_save', {state: JSON.stringify(gameSaveData())}); }
  catch(err) { console.warn('[GreenStand] Sauvegarde cloud reportée :', err.message); }
}

// Ramène tout niveau (améliorations ou gérants) dépassant sa nouvelle limite (maxLevel)
// à cette limite. Utile pour les sauvegardes existantes créées avant l'ajout du plafond.
function clampLevelsToMax(){
  ITEMS.forEach(it => { if (it.maxLevel !== undefined && it.level > it.maxLevel) it.level = it.maxLevel; });
  MANAGERS.forEach(m => { if (m.maxLevel !== undefined && m.level > m.maxLevel) m.level = m.maxLevel; });
}

/* ---------------- LES NIVEAUX, COMPTOIR PAR COMPTOIR ----------------
   ITEMS[].level et MANAGERS[].level sont TOUJOURS ceux du comptoir tenu : tout le
   reste du jeu (boutique, production, succès, 3D) continue de les lire sans rien
   savoir de cette carte. Ces quatre fonctions sont les seules à la manipuler. */

function gsNiveauxVides(){
  return { levels: ITEMS.map(()=>0), managerLevels: MANAGERS.map(()=>0) };
}

/** Range les niveaux actuellement en jeu dans la case du comptoir donné. */
function gsCapturerNiveaux(standId){
  gsStandLevels[standId || gsStandId] = {
    levels: ITEMS.map(it => it.level || 0),
    managerLevels: MANAGERS.map(m => m.level || 0),
  };
}

/** Remet en jeu les niveaux d'un comptoir (zéro s'il n'a jamais été ouvert). */
function gsAppliquerNiveaux(standId){
  const e = gsStandLevels[standId] || gsNiveauxVides();
  const lv = Array.isArray(e.levels) ? e.levels : [];
  const ml = Array.isArray(e.managerLevels) ? e.managerLevels : [];
  ITEMS.forEach((it, i) => { it.level = Number(lv[i]) || 0; });
  MANAGERS.forEach((m, i) => { m.level = Number(ml[i]) || 0; });
  clampLevelsToMax();
}

/** Relit la carte depuis une sauvegarde — et la fabrique si elle est d'avant la
    séparation : chaque comptoir hérite alors des niveaux communs. */
function gsCarteDepuisSave(save){
  const carte = (save && save.standLevels && typeof save.standLevels === 'object') ? save.standLevels : null;
  const communs = {
    levels: Array.isArray(save && save.levels) ? save.levels : [],
    managerLevels: Array.isArray(save && save.managerLevels) ? save.managerLevels : [],
  };
  const out = {};
  GS_STANDS.forEach(st => {
    const e = carte && carte[st.id] && typeof carte[st.id] === 'object' ? carte[st.id] : communs;
    out[st.id] = {
      levels: (Array.isArray(e.levels) ? e.levels : []).map(n => Number(n) || 0),
      managerLevels: (Array.isArray(e.managerLevels) ? e.managerLevels : []).map(n => Number(n) || 0),
    };
  });
  return out;
}

/** Le plus haut total de niveaux d'améliorations, tous comptoirs confondus. */
function gsMeilleurTotalNiveaux(){
  let best = ITEMS.reduce((s, i) => s + (i.level || 0), 0);
  Object.keys(gsStandLevels).forEach(id => {
    const lv = gsStandLevels[id] && gsStandLevels[id].levels;
    if(Array.isArray(lv)) best = Math.max(best, lv.reduce((s, n) => s + (Number(n) || 0), 0));
  });
  return best;
}

/** Idem pour les gérants — sert aux succès, qui ne doivent jamais reculer. */
function gsMeilleurTotalGerants(){
  let best = MANAGERS.reduce((s, m) => s + (m.level || 0), 0);
  Object.keys(gsStandLevels).forEach(id => {
    const ml = gsStandLevels[id] && gsStandLevels[id].managerLevels;
    if(Array.isArray(ml)) best = Math.max(best, ml.reduce((s, n) => s + (Number(n) || 0), 0));
  });
  return best;
}

/** Remet à zéro les quatre comptoirs (revente de franchise, remise à zéro générale). */
function gsViderTousLesNiveaux(){
  gsStandLevels = {};
  GS_STANDS.forEach(st => { gsStandLevels[st.id] = gsNiveauxVides(); });
  ITEMS.forEach(it => it.level = 0);
  MANAGERS.forEach(m => m.level = 0);
}

function gsMergeSave(remote){
  if(!remote || typeof remote !== 'object') return;
  const local = gameSaveData();
  const max = key => Math.max(Number(local[key]) || 0, Number(remote[key]) || 0);
  // Les compteurs de PROGRESSION peuvent prendre le plus avancé des deux : c'est ce qui
  // fait marcher le multi-appareil, et les gonfler ne rapporte rien.
  ['manualSales','itemsBought','prestigeSeeds','lifetimeManualSales','lifetimeItemsBought','critCount',
   'clientsServed','clientsLost'].forEach(key=>state[key] = max(key));

  // Les compteurs d'ARGENT, eux, suivent le serveur dès qu'il en a un. Avant, un
  // Math.max sur lifetimeTotalEarned faisait qu'une sauvegarde locale trafiquée
  // gagnait toujours : il suffisait d'écrire un grand nombre dans le localStorage et
  // de recharger. Le serveur fait foi ; le local ne sert que s'il n'y a rien en face.
  const serverKnows = key => remote[key] !== undefined && remote[key] !== null;
  ['money','totalEarned','lifetimeTotalEarned','gsSyncedLifetime'].forEach(key=>{
    state[key] = serverKnows(key) ? (Number(remote[key]) || 0) : (Number(local[key]) || 0);
  });
  // Les niveaux se fusionnent COMPTOIR PAR COMPTOIR, au plus avancé des deux :
  // c'est ce qui fait marcher le multi-appareil sans qu'un stand joué ailleurs
  // efface celui d'ici. Les niveaux en jeu sont ensuite ceux du comptoir tenu.
  const carteLocale = gsCarteDepuisSave(local);
  const carteDistante = gsCarteDepuisSave(remote);
  const fusion = {};
  GS_STANDS.forEach(st=>{
    const a = carteLocale[st.id] || gsNiveauxVides();
    const b = carteDistante[st.id] || gsNiveauxVides();
    fusion[st.id] = {
      levels: ITEMS.map((it, i)=> Math.max(Number(a.levels[i]) || 0, Number(b.levels[i]) || 0)),
      managerLevels: MANAGERS.map((m, i)=> Math.max(Number(a.managerLevels[i]) || 0, Number(b.managerLevels[i]) || 0)),
    };
  });
  gsStandLevels = fusion;
  if(applyWipeGen(remote)) gsWiped = true;
  const unlocked = new Set([...(Array.isArray(local.unlockedAchievements) ? local.unlockedAchievements : []), ...(Array.isArray(remote.unlockedAchievements) ? remote.unlockedAchievements : [])]);
  state.unlockedAchievements = [...unlocked];

  // Le stand tenu n'est pas une progression : on ne prend pas « le plus avancé des
  // deux », on prend celui du serveur dès qu'il en a un. C'est un réglage, et le
  // dernier appareil qui a joué fait foi. gsPoserStand() se charge de redescendre
  // au meilleur stand autorisé si celui-là n'est plus accessible.
  gsPoserStand(remote.stand || gsStandId, true, true);
  // Une remise à zéro venue du cloud efface aussi ce que la fusion vient de remonter.
  if(gsWiped) applyWipeGen(null);
  gsAchSynchroniser();
  recomputeDerived();
}

let gsInitTentatives = 0;
async function gsInitCloudSave(){
  if(!GS_LOGGED_IN) return;
  try {
    const result = await gsCloudRequest('state_load');
    // Première connexion : la progression locale est envoyée telle quelle. Sur un autre
    // appareil, les deux états sont fusionnés avec la progression la plus avancée.
    gsMergeSave(result.state);
    gsCloudReady = true;
    gsBank = Number(result.bank_eur) || 0;
    if(result.rates)  gsRates  = result.rates;
    if(result.wallet) gsWallet = result.wallet;
    if(result.gold)   gsGold   = result.gold;
    // Le nombre de franchises vient d'être mis à jour : c'est lui qui décide des
    // stands autorisés. On repose donc le comptoir maintenant — au cas où celui de
    // la sauvegarde ne serait plus (ou pas encore) accessible.
    gsPoserStand(gsStandId, true);
    if(Number(result.offline_rate) > 0) gsServerOfflineRate = Number(result.offline_rate);
    // Ce que le joueur a acheté à la boutique tourne encore : on le repose ici, avec
    // le temps qu'il reste d'après l'horloge du SERVEUR. Sans ça, un effet payé sur
    // la page Boutique n'arriverait jamais jusqu'au stand.
    gsAppliquerEffetsAchetes(result.effets);
    gsAntiClicAvis(result.autoclick);
    // Suspension encore en cours : l'écran revient, rechargement ou pas.
    if(result.autoclick && result.autoclick.blocked){
      gsEcranSuspension(result.autoclick.seconds, result.autoclick.strikes);
    }
    // L'absence mesurée par le serveur, jamais par la machine du joueur.
    applyOfflineEarnings(Number(result.offline_seconds) || 0);
    saveGame();
    renderAll(); renderDistributeur(); checkAchievements();
    gsShowWipeNotice();
  } catch(err) {
    // Silencieux, cet échec laissait la page afficher des zéros sans rien dire : le
    // joueur croyait avoir perdu ses feuilles. On le dit, et on réessaie.
    console.warn('[GreenStand] Chargement cloud reporté :', err.message);
    gsShowSyncNote('Chargement du compte : ' + (err.message || 'échec') + ' — nouvel essai…');
    if(gsInitTentatives < 3){ gsInitTentatives++; setTimeout(gsInitCloudSave, 3000); }
  }
}

function loadGame(){
  try {
    const raw = localStorage.getItem(SAVE_KEY);
    if(!raw) return;
    const data = JSON.parse(raw);
    state.money = data.money || 0;
    state.totalEarned = data.totalEarned || 0;
    state.manualSales = data.manualSales || 0;
    state.itemsBought = data.itemsBought || 0;
    state.prestigeSeeds = data.prestigeSeeds || 0;
    state.lifetimeManualSales = data.lifetimeManualSales || 0;
    state.lifetimeItemsBought = data.lifetimeItemsBought || 0;
    state.lifetimeTotalEarned = data.lifetimeTotalEarned || state.totalEarned || 0;
    state.critCount = data.critCount || 0;
    state.unlockedAchievements = Array.isArray(data.unlockedAchievements) ? data.unlockedAchievements : [];
    state.gsSyncedLifetime = data.gsSyncedLifetime || 0;
    gsStandId = gsStand(data.stand).id;
    // La carte des comptoirs d'abord (elle se fabrique toute seule si la
    // sauvegarde est d'avant la séparation), puis les niveaux de celui qu'on tient.
    gsStandLevels = gsCarteDepuisSave(data);
    gsAppliquerNiveaux(gsStandId);
    if(applyWipeGen(data)) gsWiped = true;
    gsAchSynchroniser();
    recomputeDerived();
    // Connecté : on attend le serveur, c'est lui qui dira depuis combien de temps le
    // joueur est parti (gsInitCloudSave). Avancer l'horloge de la machine ne fabrique
    // donc plus 72 h de production à chaque rechargement.
    if(!GS_LOGGED_IN && data.savedAt){
      applyOfflineEarnings((Date.now() - data.savedAt) / 1000);
    }
  } catch(e) {
    console.warn('Chargement de la sauvegarde impossible :', e);
  }
}

const OFFLINE_CAP_BASE_SECONDS = 4 * 3600; // gains hors-ligne plafonnés à 4h de base, avant améliorations
const OFFLINE_CAP_MAX_SECONDS = 72 * 3600; // le Minuteur de veille ne peut jamais pousser le plafond au-delà de 72h
const OFFLINE_RATE = 0.5; // 50% du taux normal pendant l'absence, avant gérants

// Taux de gains hors-ligne courant = taux de base amélioré par le "Veilleur de nuit",
// plafonné à 100% (on ne peut pas gagner plus hors-ligne qu'en jouant activement).
// Le taux de base, le gérant « Veilleur de nuit », et le boost de franchise
// « Veilleuse permanente » (dont le serveur nous donne le facteur au chargement).
let gsServerOfflineRate = OFFLINE_RATE;
function currentOfflineRate(){
  return Math.min(1, gsServerOfflineRate * managerMultiplier('offline_rate'));
}

// Plafond hors-ligne courant = base + bonus du "Minuteur de veille" (item 'clock'),
// mais jamais plus de 72h au total, même à haut niveau.
function currentOfflineCapSeconds(){
  const clockItem = ITEMS.find(it => it.id === 'clock');
  const bonus = clockItem ? itemTotalContribution(clockItem) : 0;
  return Math.min(OFFLINE_CAP_BASE_SECONDS + bonus, OFFLINE_CAP_MAX_SECONDS);
}

function formatDuration(seconds){
  const h = Math.floor(seconds/3600), m = Math.round((seconds%3600)/60);
  if(h > 0) return m > 0 ? (h + 'h' + String(m).padStart(2,'0')) : (h + 'h');
  return m + ' min';
}

/**
 * Gains pendant l'absence.
 *
 * @param elapsedSec  durée d'absence EN SECONDES. Quand le joueur est connecté, elle
 *                    vient du serveur (écart entre deux greenstand_last_sync) : c'est
 *                    la seule horloge qu'il ne peut pas avancer. Hors connexion, on
 *                    retombe sur l'horloge locale — il n'y a alors aucun jeton en jeu.
 */
function applyOfflineEarnings(elapsedSec){
  if(!elapsedSec || elapsedSec <= 0 || state.perSec <= 0) return;
  if(elapsedSec < 30) return; // pas la peine pour une simple actualisation de page
  const capSeconds = currentOfflineCapSeconds();
  const effectiveSec = Math.min(elapsedSec, capSeconds);
  const gain = state.perSec * effectiveSec * currentOfflineRate();
  if(gain <= 0.01) return;
  state.money += gain;
  state.totalEarned += gain;
  state.lifetimeTotalEarned += gain;
  const capped = elapsedSec > capSeconds;
  showOfflineModal(gain, effectiveSec, capped, capSeconds);
}

function showOfflineModal(gain, seconds, capped, capSeconds){
  const hrs = Math.floor(seconds/3600), mins = Math.round((seconds%3600)/60);
  const timeStr = hrs > 0 ? (hrs + 'h' + String(mins).padStart(2,'0')) : (mins + ' min');
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(10,15,12,0.75);display:flex;align-items:center;justify-content:center;z-index:2000;padding:20px;';
  overlay.innerHTML = `
    <div style="background:var(--panel);border:1px solid var(--gold);border-radius:16px;padding:24px 26px;max-width:320px;text-align:center;font-family:'Inter',sans-serif;color:var(--text);box-shadow:0 12px 40px rgba(0,0,0,.6);">
      <div style="font-size:28px;margin-bottom:8px;">🌿</div>
      <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:800;font-size:16px;margin-bottom:6px;">Pendant ton absence</div>
      <div style="font-family:'JetBrains Mono',monospace;color:var(--gold);font-size:22px;font-weight:700;margin-bottom:6px;">+${fmt(gain)}</div>
      <div style="font-size:12px;color:var(--text-dim);margin-bottom:${capped ? '4px' : '16px'};">Le stand a tourné pendant ${timeStr} (à moitié régime).</div>
      ${capped ? `<div style="font-size:11px;color:var(--text-dim);margin-bottom:16px;">Gains hors-ligne plafonnés à ${formatDuration(capSeconds)}.</div>` : ''}
      <button id="offlineModalOk" style="all:unset;cursor:pointer;font-family:'JetBrains Mono',monospace;font-weight:700;font-size:13px;padding:9px 22px;border-radius:10px;color:#fff;background:linear-gradient(120deg,#8B5FBF,#4CAF3D);">Récupérer</button>
    </div>`;
  document.body.appendChild(overlay);
  overlay.querySelector('#offlineModalOk').addEventListener('click', ()=>{
    overlay.remove();
    renderAll();
    saveGame();
  });
}

let saveIndicatorTimeout = null;
function showSaveIndicator(){
  let el = document.getElementById('saveIndicator');
  if (!el) {
    el = document.createElement('span');
    el.id = 'saveIndicator';
    el.style.cssText = 'margin-left:8px;color:#4CAF3D;opacity:0;transition:opacity .3s ease;';
    el.textContent = '✓ sauvegardé';
    const bar = document.querySelector('.stat-bar');
    if (bar) bar.appendChild(el);
  }
  el.style.opacity = '1';
  clearTimeout(saveIndicatorTimeout);
  saveIndicatorTimeout = setTimeout(()=>{ el.style.opacity = '0'; }, 1500);
}

/* Les centimes comptent au début, plus du tout à partir du million : au-delà on passe
   aux suffixes, sinon la fin de partie affiche « 1 284 991 003 447,00 € » dans un
   bouton de boutique. */
const FMT_SUFFIXES = [
  [1e15, 'Q'], [1e12, 'T'], [1e9, 'Md'], [1e6, 'M'],
];
function fmt(n){
  if(!Number.isFinite(n)) return '0,00 €';
  const abs = Math.abs(n);
  for(const [seuil, suffixe] of FMT_SUFFIXES){
    if(abs >= seuil){
      return (n/seuil).toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2})
             + ' ' + suffixe + ' €';
    }
  }
  return n.toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';
}
// Le coût d'un niveau donné.
//
// Les GS_SOFT_CAP premiers niveaux suivent le multiplicateur propre à l'objet — c'est
// le début de partie, il doit rester rapide. Au-delà, tous les objets basculent sur un
// multiplicateur commun, plus raide : c'est ce qui fait durer la partie jusqu'au niveau
// 50 au lieu de la boucler en une soirée.
//
// Les gérants n'ont pas de softCap : leur propre multiplicateur est déjà brutal.
//
// S'y ajoute le tarif des STANDS : un comptoir qui rapporte plus coûte plus cher à
// équiper. Ce facteur suit le MEILLEUR stand débloqué, pas celui qu'on tient —
// sinon il suffirait de repasser au GreenStand pour tout acheter au prix d'origine
// avant de revenir encaisser au WhiteStand. Les niveaux, eux, restent communs aux
// quatre stands : on ne rachète jamais deux fois la même amélioration.
function costAtLevel(entry, lvl){
  const soft = entry.softCap === undefined ? GS_SOFT_CAP : entry.softCap;
  const early = Math.min(lvl, soft);
  const late  = Math.max(0, lvl - soft);
  const lateMult = entry.mult !== undefined ? entry.costMult : GS_LATE_MULT; // gérant : pas de bascule
  return entry.baseCost * Math.pow(entry.costMult, early) * Math.pow(lateMult, late) * gsStandCoutMult();
}
function costFor(item, discount=1){ return costAtLevel(item, item.level) * discount; }

let buyQty = 1; // 1, 10, ou 'max'

// Coût total pour acheter `n` niveaux supplémentaires à partir du niveau actuel
// `discount` (0-1) applique le bonus du gérant Comptable — uniquement utilisé pour ITEMS.
function costForN(item, n, discount=1){
  let total = 0, lvl = item.level;
  for(let i=0;i<n;i++){ total += costAtLevel(item, lvl+i) * discount; }
  return total;
}
// Combien de niveaux le joueur peut se payer avec state.money (plafonné pour éviter une boucle infinie)
function remainingLevels(item){
  return item.maxLevel === undefined ? Infinity : Math.max(0, item.maxLevel - item.level);
}
function maxAffordableCount(item, discount=1){
  let n = 0, total = 0, lvl = item.level;
  const limit = Math.min(9999, remainingLevels(item));
  while(n < limit){
    const next = costAtLevel(item, lvl+n) * discount;
    if(total + next > state.money) break;
    total += next; n++;
  }
  return n;
}
function resolveQty(item, discount=1){
  const remaining = remainingLevels(item);
  if(remaining <= 0) return 0;
  if(buyQty === 'max') return maxAffordableCount(item, discount);
  return Math.min(buyQty, remaining);
}
function totalItemsLevel(){ return ITEMS.reduce((s,i)=>s+i.level,0); }
/* Le niveau du stand suit le MEILLEUR comptoir, pas celui qu'on tient : ouvrir un
   BrownStand tout neuf ne doit pas refermer les contacts du téléphone ni les
   objectifs du jour déjà gagnés sur le GreenStand. Même règle côté serveur
   (greenstand_stand_niveau / greenstand_total_niveaux). */
function standLevel(){ return 1 + Math.floor(gsMeilleurTotalNiveaux()/3); }

const shopEl = document.getElementById('shop');
function renderShop(){
  if(!shopEl) return;
  shopEl.innerHTML = '';
  const discount = managerCostDiscount();
  ITEMS.forEach(item=>{
    const qty = resolveQty(item, discount);
    const cost = costForN(item, qty, discount);
    const affordable = qty > 0 && state.money >= cost;
    const assetKey = assetKeyBoutique(item);
    const nextGain = itemUnitGain(item, item.level);
    const totalNow = itemTotalContribution(item);
    let maxed = remainingLevels(item) <= 0;
    let gainHtml;
    if(item.clientMult){
      // Bonus multiplicatif, comme un gérant, mais affiché dans « Améliorations »
      // puisque le joueur l'achète au même titre que le reste du stand.
      const pctNow  = Math.round((Math.pow(item.clientMult, item.level) - 1) * 100);
      const pctNext = Math.round((Math.pow(item.clientMult, item.level + 1) - 1) * 100);
      gainHtml = item.level > 0
        ? `<span class="now">Actuel : +${pctNow}% sur les ventes aux clients</span><br><span class="next">Prochain niveau : +${pctNext}%</span>`
        : `<span class="next">Premier niveau : +${pctNext}% sur les ventes aux clients</span>`;
    } else if(item.offlineCapAdd){
      const capNow = Math.min(OFFLINE_CAP_BASE_SECONDS + totalNow, OFFLINE_CAP_MAX_SECONDS);
      const capNext = Math.min(OFFLINE_CAP_BASE_SECONDS + totalNow + nextGain, OFFLINE_CAP_MAX_SECONDS);
      const capReached = capNow >= OFFLINE_CAP_MAX_SECONDS;
      if(capReached) maxed = true; // inutile d'acheter davantage une fois les 72h atteintes
      gainHtml = item.level > 0
        ? `<span class="now">Actuel : ${formatDuration(capNow)} de durée hors-ligne max</span>${capReached ? '<br><span class="next">Plafond de 72h atteint</span>' : (maxed ? '<br><span class="next">Niveau maximum atteint</span>' : `<br><span class="next">Prochain niveau : ${formatDuration(capNext)}</span>`)}`
        : `<span class="next">Premier niveau : ${formatDuration(capNext)} de durée hors-ligne max</span>`;
    } else {
      const unit = item.clickAdd > 0 ? 'par clic' : '/ seconde';
      gainHtml = item.level > 0
        ? `<span class="now">Actuel : +${fmt(totalNow)} ${unit}</span><br><span class="next">Prochain niveau : +${fmt(nextGain)} ${unit}</span>`
        : `<span class="next">Premier niveau : +${fmt(nextGain)} ${unit}</span>`;
    }
    const swatchHtml = assetKey
      ? `<img src="${ASSET_DATA[assetKey]}" alt="">`
      : `<span style="font-size:22px;">${item.emoji || '✨'}</span>`;
    const qtyLabel = qty > 1 ? ('x' + qty + ' — ') : '';
    const card = document.createElement('div');
    card.className = 'item-card ' + (affordable && !maxed ? 'affordable' : '');
    card.innerHTML = `
      <div class="item-swatch">${swatchHtml}</div>
      <div class="item-body">
        <h3>${gsNomObjet(item)}</h3>
        <p>${gsDescObjet(item)}</p>
        <p class="item-gain">${gainHtml}</p>
        <div class="item-foot">
          <span class="item-lvl">Niv. ${item.level}${item.maxLevel ? '/' + item.maxLevel : ''}</span>
          <button class="buy-btn" data-id="${item.id}" ${affordable && !maxed ? '' : 'disabled'}>${maxed ? 'MAX' : (qtyLabel + fmt(cost))}</button>
        </div>
      </div>`;
    card.dataset.entry = item.id;
    shopEl.appendChild(card);
  });
  shopEl.querySelectorAll('.buy-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>buyItem(btn.dataset.id));
  });
}

/* Rafraîchit UNIQUEMENT ce qui bouge quand l'argent change : le prix affiché et
   l'état "abordable". Le reste des cartes (nom, description, gains, niveau) ne
   dépend que des niveaux, qui ne changent qu'à l'achat.

   Avant, chaque vente manuelle ET chaque seconde relançaient renderShop(), qui vide
   le conteneur et reconstruit les douze cartes. À dix clics par seconde ça faisait
   dix reconstructions complètes du DOM — d'où les états :hover qui sautaient et les
   clics qui se perdaient pendant la reconstruction. */
function refreshShopPrices(){
  const discount = managerCostDiscount();
  [[shopEl, ITEMS, discount], [managersEl, MANAGERS, 1]].forEach(([root, list, disc])=>{
    if(!root) return;
    list.forEach(entry=>{
      const card = root.querySelector('[data-entry="' + entry.id + '"]');
      if(!card) return;
      const btn = card.querySelector('.buy-btn');
      if(!btn) return;
      const qty   = resolveQty(entry, disc);
      const cost  = costForN(entry, qty, disc);
      const maxed = remainingLevels(entry) <= 0;
      const affordable = qty > 0 && state.money >= cost;
      if(!maxed){
        const label = (qty > 1 ? ('x' + qty + ' — ') : '') + fmt(cost);
        if(btn.textContent !== label) btn.textContent = label;
      }
      btn.disabled = !(affordable && !maxed);
      card.classList.toggle('affordable', affordable && !maxed);
    });
  });
}

function buyItem(id){
  const item = ITEMS.find(i=>i.id===id);
  const discount = managerCostDiscount();
  const qty = resolveQty(item, discount);
  if(qty <= 0) return;
  const cost = costForN(item, qty, discount);
  if(state.money < cost) return;
  state.money -= cost;
  item.level += qty;
  state.itemsBought += qty;
  state.lifetimeItemsBought += qty;
  recomputeDerived();
  refreshStandModels();
  renderAll();
  saveGame();
  playCash();
  checkAchievements();
}

/* ---------------- Boutique des gérants (multiplicatifs) ---------------- */
const managersEl = document.getElementById('managers');
function renderManagers(){
  if(!managersEl) return;
  managersEl.innerHTML = '';
  MANAGERS.forEach(mgr=>{
    const qty = resolveQty(mgr, 1);
    const cost = costForN(mgr, qty, 1);
    const affordable = qty > 0 && state.money >= cost;
    const maxed = remainingLevels(mgr) <= 0;
    const currentMult = Math.pow(mgr.mult, mgr.level);
    const nextMult = maxed ? currentMult : Math.pow(mgr.mult, mgr.level + 1);
    const label = mgr.effect === 'click' ? 'les gains au clic'
                : mgr.effect === 'auto'  ? 'le revenu passif'
                : mgr.effect === 'global' ? 'tous les gains'
                : mgr.effect === 'offline_rate' ? 'le taux de gains hors-ligne'
                : 'le coût des améliorations';
    const fmtMult = v => (mgr.effect === 'cost' ? ('-' + Math.round((1 - v) * 100) + '%') : ('x' + v.toFixed(2)));
    const gainHtml = mgr.level > 0
      ? `<span class="now">Actuel : ${fmtMult(currentMult)} sur ${label}</span>${maxed ? '<br><span class="next">Niveau maximum atteint</span>' : `<br><span class="next">Prochain niveau : ${fmtMult(nextMult)}</span>`}`
      : `<span class="next">Niveau 1 : ${fmtMult(mgr.mult)} sur ${label}</span>`;
    const qtyLabel = qty > 1 ? ('x' + qty + ' — ') : '';
    const card = document.createElement('div');
    card.className = 'item-card ' + (affordable && !maxed ? 'affordable' : '');
    card.innerHTML = `
      <div class="item-swatch"><span style="font-size:22px;">${mgr.emoji}</span></div>
      <div class="item-body">
        <h3>${mgr.name}</h3>
        <p>${mgr.desc}</p>
        <p class="item-gain">${gainHtml}</p>
        <div class="item-foot">
          <span class="item-lvl">Niv. ${mgr.level}${mgr.maxLevel ? '/' + mgr.maxLevel : ''}</span>
          <button class="buy-btn" data-mgr="${mgr.id}" ${affordable && !maxed ? '' : 'disabled'}>${maxed ? 'MAX' : (qtyLabel + fmt(cost))}</button>
        </div>
      </div>`;
    card.dataset.entry = mgr.id;
    managersEl.appendChild(card);
  });
  managersEl.querySelectorAll('.buy-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>buyManager(btn.dataset.mgr));
  });
}

function buyManager(id){
  const mgr = MANAGERS.find(m=>m.id===id);
  if(!mgr) return;
  const qty = resolveQty(mgr, 1);
  if(qty <= 0) return;
  const cost = costForN(mgr, qty, 1);
  if(state.money < cost) return;
  state.money -= cost;
  mgr.level += qty;
  recomputeDerived();
  renderAll();
  saveGame();
  playCash();
  checkAchievements();
}

const buyQtyToggle = document.getElementById('buyQtyToggle');
if(buyQtyToggle){
  buyQtyToggle.querySelectorAll('.qty-btn').forEach(btn=>{
    if(btn.dataset.qty === '1') btn.classList.add('active');
    btn.addEventListener('click', ()=>{
      buyQty = btn.dataset.qty === 'max' ? 'max' : parseInt(btn.dataset.qty, 10);
      buyQtyToggle.querySelectorAll('.qty-btn').forEach(b=>b.classList.toggle('active', b===btn));
      renderShop();
    });
  });
}

function renderTop(){
  // L'en-tête et la barre de stats sont sur toutes les pages, le niveau du stand
  // seulement sur la sienne : on écrit dans ce qui existe, sans supposer.
  const ecrire = (id, texte) => { const el = document.getElementById(id); if(el) el.textContent = texte; };
  ecrire('money', fmt(state.money));
  ecrire('rate', '+' + fmt(state.perSec) + '/s');
  ecrire('lvlTag', 'Niveau ' + standLevel());
  ecrire('statTotal', fmt(state.totalEarned));
  ecrire('statClicks', state.manualSales);
  ecrire('statItems', state.itemsBought);
  ecrire('statClients', state.clientsServed || 0);
  ecrire('statClientsLost', state.clientsLost || 0);
}
/* ======================================================================
   LA BARRE DES STANDS
   ======================================================================
   Un bouton par comptoir. Ceux qu'on n'a pas encore restent affichés, grisés,
   avec le nombre de franchises qui les ouvre : un objectif qu'on ne voit pas
   n'en est pas un.

   Rien n'est écrit en dur ici — ni les noms, ni les seuils, ni les
   multiplicateurs : tout vient de GS_STANDS (greenstand_stands(), côté
   serveur). Ajouter un cinquième stand ne demande donc pas de toucher à cette
   fonction.
   ====================================================================== */
function gsStandResume(stand){
  const pct = v => Math.round(((Number(v) || 1) - 1) * 100);
  const bout = (label, v) => {
    const p = pct(v);
    return label + ' ' + (p >= 0 ? '+' : '') + p + '%';
  };
  const parts = [bout('clic', stand.clickMult), bout('auto', stand.autoMult), bout('clients', stand.clientMult)];
  // Chaque comptoir a ses propres améliorations : autant dire où en est celui-là,
  // sinon passer de l'un à l'autre donne l'impression d'avoir tout perdu.
  if(gsStandDebloque(stand)) parts.push(gsNiveauxDuStand(stand.id) + ' niv. achetés');
  return parts.join(' · ');
}

/* Le total de niveaux (améliorations + gérants) achetés sur un comptoir donné. */
function gsNiveauxDuStand(standId){
  if(standId === gsStandId){
    return ITEMS.reduce((s,i)=> s + (i.level||0), 0) + MANAGERS.reduce((s,m)=> s + (m.level||0), 0);
  }
  const e = gsStandLevels[standId];
  if(!e) return 0;
  const somme = t => (Array.isArray(t) ? t : []).reduce((s,n)=> s + (Number(n)||0), 0);
  return somme(e.levels) + somme(e.managerLevels);
}

function renderStands(){
  const root = document.getElementById('gsStands');
  if(!root) return;
  root.innerHTML = '';
  GS_STANDS.forEach(stand=>{
    const ouvert = gsStandDebloque(stand);
    const actif  = stand.id === gsStandId;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'gs-stand' + (actif ? ' is-active' : '') + (ouvert ? '' : ' is-locked');
    btn.disabled = !ouvert;
    btn.title = ouvert
      ? stand.desc
      : (stand.name + " s'ouvre à " + stand.franchises + ' franchises — tu en as ' + gsFranchisesOuvertes() + '.');

    const nom = document.createElement('span');
    nom.className = 'n';
    nom.textContent = (stand.emoji || '') + ' ' + stand.name + (actif ? '  ●' : '');

    const profil = document.createElement('span');
    profil.className = 'p';
    profil.textContent = ouvert ? stand.profil : ('Verrouillé — ' + stand.franchises + ' franchises');

    const mults = document.createElement('span');
    mults.className = 'm';
    mults.textContent = gsStandResume(stand);

    btn.append(nom, profil, mults);
    btn.addEventListener('click', ()=> gsChoisirStand(stand.id));
    root.appendChild(btn);
  });
  gsRendreBonusPermanents(root);
}

/* Les bonus permanents de la Franchise (Souris en or, Serre en or, Comptable de
   l'ombre) s'appliquent sur LES QUATRE STANDS : ils multiplient les gains avant
   que le stand n'applique les siens (voir recomputeDerived), et le serveur en
   tient compte de la même façon dans le plafond de dépôt.

   On l'affiche sous la barre des stands, parce que c'est invisible autrement :
   on achète un bonus sur la page Franchise et rien ne dit, au comptoir, qu'il
   agit encore quand on change de stand. */
function gsRendreBonusPermanents(root){
  const parts = [];
  const pct = (b, signe) => Math.round(b.step * gsBoostLevel(b.id) * 100 * signe);
  GS_BOOSTS.forEach(b=>{
    const lvl = gsBoostLevel(b.id);
    if(!lvl) return;
    if(b.effect === 'click')   parts.push('clic +' + pct(b, 1) + '%');
    if(b.effect === 'persec')  parts.push('€/s +' + pct(b, 1) + '%');
    if(b.effect === 'cost')    parts.push('prix −' + pct(b, 1) + '%');
    if(b.effect === 'offline') parts.push('hors-ligne +' + pct(b, 1) + '%');
  });
  const ligne = document.createElement('div');
  ligne.className = 'gs-stands-note';
  ligne.textContent = parts.length
    ? ('Bonus permanents de la Franchise, actifs sur les quatre stands : ' + parts.join(' · '))
    : "Les bonus permanents achetés en Feuilles de Platine s'appliquent sur les quatre stands.";
  root.appendChild(ligne);
}

/* Le joueur clique sur un comptoir. Un stand verrouillé le dit et ne change rien —
   le serveur refuserait de toute façon d'en tenir compte dans ses calculs. */
function gsChoisirStand(id){
  const stand = gsStand(id);
  if(!gsStandDebloque(stand)){
    gsShowSyncNote(stand.name + " s'ouvre à " + stand.franchises + ' franchises — tu en as ' + gsFranchisesOuvertes() + '.');
    return;
  }
  if(stand.id === gsStandId) return;
  gsPoserStand(stand.id);
  renderAll();
  saveGame();
}

/* Applique la couleur du stand à la PAGE (le décor 3D, lui, est rejoué par
   applyStandVisuals). Trois variables CSS suffisent : tout le reste de la feuille
   de style en dérive déjà. */
function gsAppliquerThemeStand(){
  const stand = gsStandCourant();
  const pal = stand.palette || {};
  const root = document.documentElement;
  if(pal.accent)     root.style.setProperty('--leaf', pal.accent);
  if(pal.accentDark) root.style.setProperty('--leaf-dark', pal.accentDark);
  if(pal.lcd)        root.style.setProperty('--lcd-fg', pal.lcd);

  // Le conseil sous la scène nomme le produit du comptoir : « clique sur le nug »
  // n'a plus de sens quand on tient le WhiteStand.
  const hint = document.querySelector('.scene-hint');
  if(hint){
    hint.innerHTML = '';
    hint.append(document.createTextNode('Clique sur le '));
    const b = document.createElement('b');
    b.textContent = stand.unite || 'nug';
    hint.append(b, document.createTextNode(' au centre pour vendre — le '));
    const t = document.createElement('b');
    t.textContent = '▶';
    hint.append(t, document.createTextNode(' de la console élargit le stand'));
  }

  applyStandVisuals();
  renderStands();
}

function renderAll(){ renderTop(); renderShop(); renderManagers(); renderAchievements(); renderDistributeur(); renderFranchise(); renderStands(); renderSucces(); }

const CRIT_CHANCE = 0.05;
const CRIT_MULT = 5;

/* ======================================================================
   ANTI AUTO-CLICKER — LE FILTRE DU NAVIGATEUR
   ======================================================================
   Première ligne de défense : elle écarte les macros directement dans la
   page, avant même qu'un euro soit crédité au compteur local. Un clic jugé
   non humain ne rapporte rien du tout.

   Elle n'est pas la protection réelle des jetons DT — celle-ci est côté
   serveur (greenstand_click_audit() dans greenstand_lib.php) et ne dépend
   en rien de ce qui se passe ici : quelqu'un qui désactive ce script ne
   gagne rien de plus, le serveur ne comptera de toute façon que les clics
   compatibles avec une cadence humaine.

   Ce que le filtre regarde, dans l'ordre :
     1. ev.isTrusted — un clic fabriqué par du JavaScript (element.click(),
        dispatchEvent, la plupart des extensions « auto-clicker ») est marqué
        comme non authentique par le navigateur lui-même. C'est infalsifiable
        depuis la page : ce seul test élimine l'écrasante majorité des outils.
     2. l'intervalle minimum entre deux clics ;
     3. la cadence moyenne soutenue, sur fenêtre courte et sur 10 secondes ;
     4. la régularité : une macro clique à intervalle quasi constant, une main
        humaine non. On mesure le coefficient de variation, ce qui attrape
        aussi les auto-clickers lents et bien réglés (200 ms pile) que
        l'ancien seuil absolu laissait passer ;
     5. l'immobilité : un flot rapide, parfaitement régulier et sans le
        moindre mouvement de souris est un automate, pas un joueur.

   Chaque détection déclenche une pénalité qui double à chaque récidive.
   ====================================================================== */
/* Les seuils de cadence suivent GS_HUMAN_CPS (40 clics/s, côté serveur). À ce
   niveau-là ils ne se déclencheront pour ainsi dire jamais : 40 clics/s, c'est le
   territoire du drag-click. Ce n'est de toute façon pas la vitesse qui trahit une
   macro — ce sont les clics fabriqués par script (isTrusted) et la régularité de
   métronome, deux contrôles que ce relèvement ne touche pas. */
const AC_MIN_INTERVAL_MS   = 12;    // 80 clics/s : au-delà, plus aucune main ne suit
const AC_WINDOW            = 16;    // taille de la fenêtre glissante analysée
const AC_MAX_SUSTAINED_MS  = 22;    // rythme moyen soutenu jugé irréaliste (~45 clics/s)
const AC_LONG_WINDOW_MS    = 10000; // seconde fenêtre, plus longue
const AC_LONG_MAX_CLICKS   = 400;   // soit 40 clics/s tenus pendant 10 s
const AC_REGULARITY_MAX_MS = 600;   // au-delà, la régularité n'est plus un indice
const AC_MIN_CV            = 0.035; // écart-type / moyenne minimum attendu d'une main
const AC_CV_CONFIRM        = 3;     // ...et nombre de fenêtres d'affilée sous ce seuil
const AC_MIN_SAMPLE        = 8;     // nombre de clics avant de juger la régularité
const AC_STILL_MAX_MS      = 250;   // cadence à partir de laquelle l'immobilité compte
const AC_STILL_WINDOW_MS   = 20000; // ...et depuis combien de temps la souris n'a pas bougé
const AC_PENALTY_MS        = 900;   // pénalité initiale, doublée à chaque récidive
const AC_PENALTY_MAX_MS    = 15000;
const AC_PAUSE_MS          = 1000;  // au-delà, c'est une pause, pas un rythme de clic
const AC_FORGIVE_MS        = 30000; // clics propres pendant ce temps : l'ardoise s'efface

let acIntervals = [];     // écarts entre clics consécutifs, hors pauses
let acLastAt   = 0;       // dernier clic pris en compte
let acGapDirty = true;    // le prochain écart enjambe un blocage : à ignorer
let acLong     = [];      // horodatages sur la fenêtre de 10 s
let acBlockedUntil = 0;
let acPenalty  = AC_PENALTY_MS;
let acLastMoveAt = 0;     // dernier mouvement de souris AUTHENTIQUE
let acRefused  = 0;       // nombre de clics écartés
let acLastPunishAt = -Infinity;
let acCvBas    = 0;       // fenêtres consécutives à la régularité suspecte

// Un mouvement de pointeur réel, capté au niveau du document : le filtre s'en
// sert pour distinguer une main d'un automate immobile.
document.addEventListener('pointermove', (ev)=>{ if(ev.isTrusted) acLastMoveAt = performance.now(); }, {passive:true});
document.addEventListener('touchstart',  (ev)=>{ if(ev.isTrusted) acLastMoveAt = performance.now(); }, {passive:true});

function acPunish(now, raison){
  acBlockedUntil = now + acPenalty;
  // La pénalité double à chaque récidive et NE redescend pas à la fin d'une
  // pénalité : sans cela, une macro reprenait 900 ms plus tard comme si de rien
  // n'était et retrouvait presque toute sa cadence entre deux blocages.
  acPenalty = Math.min(acPenalty * 2, AC_PENALTY_MAX_MS);
  acLastPunishAt = now;
  acCvBas = 0;
  // Le clic écarté ne suffit plus : le jeu s'arrête et le dit. gsAvertir() se
  // charge de n'ouvrir qu'un menu, même si la macro déclenche cent détections.
  gsAvertir(raison);
  // L'écart qui enjambera la pénalité n'est pas un rythme de clic : le compter
  // rendait la série irrégulière aux yeux du filtre et blanchissait la macro
  // pour une quinzaine de clics après chaque blocage.
  acGapDirty = true;
  acRefused += 1;
  acToast(raison);
  return false;
}

// On prévient le joueur : un clic qui disparaît sans explication passe pour un
// bug. Message limité à un toutes les 3 secondes.
let acLastToastAt = 0;
function acToast(raison){
  const now = performance.now();
  if(now - acLastToastAt < 3000) return;
  acLastToastAt = now;
  const el = document.createElement('div');
  el.textContent = 'Clic ignoré — ' + raison;
  el.style.cssText = 'position:fixed;left:50%;bottom:26px;transform:translateX(-50%);z-index:9999;'
    + 'background:rgba(30,20,20,.94);color:#FFD3C4;border:1px solid #7A3B2A;border-radius:10px;'
    + "padding:9px 16px;font-family:'JetBrains Mono',monospace;font-size:12px;pointer-events:none;";
  document.body.appendChild(el);
  setTimeout(()=>el.remove(), 2600);
}

/**
 * ev : l'événement d'origine. Sans lui, on ne peut pas vérifier isTrusted —
 * un appel sans événement est donc traité comme un clic fabriqué.
 */
function isHumanLikeClick(now, ev){
  if(gsStandFerme()) return false;      // stand fermé le temps d'un contrôle
  if(GS_ANTICLIC_EXEMPT) return true;   // poste dispensé (IP d'administration)
  if(gsJeuEnPause) return false;        // menu d'avertissement ou suspension en cours

  // 1. Clic fabriqué par un script : refusé, sans appel possible.
  if(!ev || ev.isTrusted !== true) return acPunish(now, 'clic non authentique');

  if(now < acBlockedUntil) return false;

  // 2. Intervalle minimum.
  const gap = acLastAt ? now - acLastAt : Infinity;
  if(gap < AC_MIN_INTERVAL_MS) return acPunish(now, 'cadence impossible');

  // Une vraie pause remet le rythme à plat ; un écart qui enjambe une pénalité
  // est jeté. Seuls les écarts « en rafale » nourrissent l'analyse.
  if(acGapDirty || gap > AC_PAUSE_MS){
    if(gap > AC_PAUSE_MS && !acGapDirty) acIntervals = [];
    acGapDirty = false;
  } else {
    acIntervals.push(gap);
    if(acIntervals.length > AC_WINDOW) acIntervals.shift();
  }
  acLastAt = now;
  acLong.push(now);
  while(acLong.length && now - acLong[0] > AC_LONG_WINDOW_MS) acLong.shift();

  // 3. Cadence tenue sur 10 secondes.
  if(acLong.length > AC_LONG_MAX_CLICKS) return acPunish(now, 'cadence tenue trop élevée');

  const intervals = acIntervals;
  if(intervals.length >= 5){
    const avg = intervals.reduce((a,b)=>a+b,0) / intervals.length;
    const variance = intervals.reduce((a,b)=>a+(b-avg)*(b-avg),0) / intervals.length;
    const stdev = Math.sqrt(variance);

    // 3 bis. Rythme moyen trop soutenu pour être tenu à la main.
    if(avg < AC_MAX_SUSTAINED_MS) return acPunish(now, 'cadence trop rapide');

    // 4. Régularité de métronome. Le coefficient de variation (écart-type
    //    rapporté à la moyenne) attrape aussi bien un automate à 60 ms qu'un
    //    automate à 500 ms ; une main humaine reste largement au-dessus.
    //
    //    Une seule fenêtre sous le seuil ne suffit PAS à condamner : sur seize
    //    clics, une main rapide et régulière passe dessous de temps en temps par
    //    simple hasard, et le joueur se retrouvait bloqué pour rien. Un automate,
    //    lui, y reste en permanence. On exige donc plusieurs fenêtres d'affilée.
    if(intervals.length >= AC_MIN_SAMPLE - 1 && avg < AC_REGULARITY_MAX_MS && (stdev / avg) < AC_MIN_CV){
      acCvBas += 1;
      if(acCvBas >= AC_CV_CONFIRM) return acPunish(now, 'rythme trop régulier');
    } else {
      acCvBas = 0;
    }

    // 5. Flot rapide, immobile : la souris n'a pas bougé d'un pixel depuis
    //    plusieurs secondes alors que les clics s'enchaînent.
    if(avg < AC_STILL_MAX_MS && (now - acLastMoveAt) > AC_STILL_WINDOW_MS){
      return acPunish(now, 'aucun mouvement détecté');
    }
  }

  // L'ardoise ne s'efface qu'après une longue série de clics propres — pas à la
  // sortie d'une pénalité, sinon la sanction ne monterait jamais.
  if(now - acLastPunishAt > AC_FORGIVE_MS) acPenalty = AC_PENALTY_MS;
  return true;
}


/* ======================================================================
   LE MENU D'AVERTISSEMENT — « es-tu là ? »
   ======================================================================
   Un clic écarté en silence, un tricheur ne le remarque même pas : son script
   continue, il perd juste des gains. Ici, la détection ARRÊTE le jeu et le dit.

     1er avertissement — le stand se fige derrière un menu qu'on ne peut pas
       fermer. Pour repartir, il faut cliquer trois fois sur un bouton qui
       change de place à chaque clic, avec un espacement humain entre les
       clics. Une macro à position fixe n'y arrive pas ; une personne, oui,
       en trois secondes. Rien ne reprend tant que ce n'est pas fait : ni les
       ventes à la main, ni le revenu automatique.

     2e avertissement — même menu, mais cette fois l'accès au stand est
       suspendu dix minutes, compte à rebours à l'écran. La suspension est
       enregistrée SUR LE SERVEUR : recharger la page ou vider le cache ne
       l'enlève pas, et pendant ce temps ni dépôt ni change ne passent.
   ====================================================================== */
const AC_CLICS_PREUVE   = 3;     // clics demandés pour prouver qu'on est là
const AC_PREUVE_MIN_MS  = 140;   // espacement minimum entre deux de ces clics
const AC_AVERTIR_MIN_MS = 8000;  // un avertissement au plus toutes les 8 s

let gsJeuEnPause     = false;    // le stand est figé (menu ouvert ou suspension)
let gsSuspenduJusqua = 0;        // horodatage local de fin de suspension
let acDernierAvertAt = -Infinity;

/* Appelé par le filtre quand il écarte un clic. La plupart des détections
   arrivent en rafale : on n'ouvre pas un menu par clic écarté. */
function gsAvertir(raison){
  const now = performance.now();
  if(gsJeuEnPause) return;
  if(now - acDernierAvertAt < AC_AVERTIR_MIN_MS) return;
  acDernierAvertAt = now;

  // Le serveur décide : c'est lui qui compte les avertissements et qui pose la
  // suspension. On lui signale, il répond avec l'état à afficher.
  if(GS_LOGGED_IN){
    gsCloudRequest('autoclick_strike', {reason: raison || ''})
      .then(res => gsAppliquerSanction(res.autoclick, raison))
      .catch(()  => gsAppliquerSanction(null, raison));   // hors ligne : menu quand même
  } else {
    gsAppliquerSanction(null, raison);
  }
}

function gsAppliquerSanction(info, raison){
  if(info && info.blocked) gsEcranSuspension(info.seconds, info.strikes);
  else                     gsEcranVerification(raison, info ? info.strikes : 1);
}

/* Le stand se fige. Le rattrapage du revenu ne doit RIEN accumuler pendant ce
   temps : sans ça, dix minutes de suspension seraient versées à la reprise. */
function gsPause(actif){
  gsJeuEnPause = !!actif;
  if(!gsJeuEnPause) lastTickAt = Date.now();
}

function gsOverlay(){
  let o = document.getElementById('gsSanctionOverlay');
  if(o) return o;
  o = document.createElement('div');
  o.id = 'gsSanctionOverlay';
  o.style.cssText = 'position:fixed;inset:0;z-index:3000;background:rgba(8,10,8,.92);'
    + 'display:flex;align-items:center;justify-content:center;padding:20px;'
    + "font-family:'Inter',sans-serif;color:#EDEAE0;";
  document.body.appendChild(o);
  return o;
}

/* ---- 1er avertissement : prouver qu'il y a quelqu'un ---- */
function gsEcranVerification(raison, avertissements){
  gsPause(true);
  const o = gsOverlay();
  let restants = AC_CLICS_PREUVE;
  let dernierClic = 0;

  o.innerHTML =
    '<div id="gsVerifBoite" style="position:relative;background:#141C15;border:1px solid #D9A441;'
    + 'border-radius:16px;padding:26px 28px;max-width:440px;width:100%;min-height:270px;'
    + 'box-shadow:0 14px 50px rgba(0,0,0,.7);text-align:center;">'
    + '<div style="font-size:34px;margin-bottom:10px;">&#9888;&#65039;</div>'
    + '<div style="font-family:\'Bricolage Grotesque\',sans-serif;font-weight:800;font-size:17px;margin-bottom:10px;">'
    +   'Avertissement &mdash; cadence de clic anormale</div>'
    + '<div style="font-size:13px;color:#A8B5A2;line-height:1.6;margin-bottom:8px;">'
    +   'Le stand est <b style="color:#EDEAE0;">en pause</b>. Motif : ' + (raison || 'clics non humains') + '.<br>'
    +   'Clique <b style="color:#EDEAE0;">trois fois</b> sur le bouton pour montrer que tu es bien l&agrave;. '
    +   'Il change de place &agrave; chaque fois.</div>'
    + '<div style="font-size:11px;color:#7E8C79;margin-bottom:16px;">'
    +   'Au prochain avertissement, l\'acc&egrave;s au stand sera suspendu 10 minutes.</div>'
    + '<button id="gsVerifBtn" style="all:unset;cursor:pointer;position:absolute;'
    +   'font-family:\'JetBrains Mono\',monospace;font-weight:700;font-size:13px;padding:11px 20px;'
    +   'border-radius:11px;color:#0E140F;background:linear-gradient(120deg,#7BD46A,#4CAF3D);'
    +   'left:50%;top:78%;transform:translate(-50%,-50%);">Je suis l&agrave; &middot; ' + restants + '</button>'
    + '</div>';

  const btn   = o.querySelector('#gsVerifBtn');
  const boite = o.querySelector('#gsVerifBoite');

  btn.addEventListener('click', (ev)=>{
    // Les mêmes exigences que sur le stand : un clic authentique, espacé.
    if(!ev.isTrusted) return;
    const now = performance.now();
    if(now - dernierClic < AC_PREUVE_MIN_MS) return;
    dernierClic = now;

    restants -= 1;
    if(restants <= 0){
      o.remove();
      gsPause(false);
      // On repart d'une ardoise propre côté navigateur : la personne a répondu.
      acIntervals = []; acLong = []; acBlockedUntil = 0; acPenalty = AC_PENALTY_MS;
      acGapDirty = true;
      gsShowSyncNote('Merci — le stand repart.');
      return;
    }
    btn.textContent = 'Je suis là · ' + restants;
    // Le bouton se déplace : une macro qui frappe toujours le même pixel
    // ne peut pas enchaîner les trois clics.
    const marge = 18;
    const maxX = Math.max(marge, boite.clientWidth  - btn.offsetWidth  - marge);
    const maxY = Math.max(120,   boite.clientHeight - btn.offsetHeight - marge);
    btn.style.left = (marge + Math.random() * (maxX - marge)) + 'px';
    btn.style.top  = (120   + Math.random() * (maxY - 120))   + 'px';
    btn.style.transform = 'none';
  });
}

/* ---- 2e avertissement : suspension ---- */
function gsEcranSuspension(secondes, avertissements){
  gsPause(true);
  gsSuspenduJusqua = Date.now() + Math.max(1, Number(secondes) || 0) * 1000;

  const o = gsOverlay();
  o.innerHTML =
    '<div style="background:#1A1210;border:1px solid #8B3A2A;border-radius:16px;padding:26px 28px;'
    + 'max-width:440px;width:100%;box-shadow:0 14px 50px rgba(0,0,0,.7);text-align:center;">'
    + '<div style="font-size:34px;margin-bottom:10px;">&#9940;</div>'
    + '<div style="font-family:\'Bricolage Grotesque\',sans-serif;font-weight:800;font-size:17px;margin-bottom:10px;color:#FFD3C4;">'
    +   'Acc&egrave;s au stand suspendu</div>'
    + '<div style="font-size:13px;color:#C9A79C;line-height:1.6;margin-bottom:14px;">'
    +   'Deuxi&egrave;me avertissement pour auto-clic. Le stand, le Distributeur et les d&eacute;p&ocirc;ts '
    +   'sont ferm&eacute;s le temps de la suspension.<br>'
    +   '<b style="color:#FFD3C4;">Recharger la page n\'y change rien</b> : elle est enregistr&eacute;e sur le serveur.</div>'
    + '<div id="gsSuspChrono" style="font-family:\'JetBrains Mono\',monospace;font-size:30px;font-weight:700;color:#F0B6AE;">--:--</div>'
    + '<div style="font-size:11px;color:#8A7A75;margin-top:12px;">Le jeu repart tout seul &agrave; z&eacute;ro.</div>'
    + '</div>';

  const chrono = o.querySelector('#gsSuspChrono');
  const tic = setInterval(()=>{
    const reste = Math.max(0, Math.ceil((gsSuspenduJusqua - Date.now()) / 1000));
    const mm = String(Math.floor(reste / 60)).padStart(2, '0');
    const ss = String(reste % 60).padStart(2, '0');
    if(chrono) chrono.textContent = mm + ':' + ss;
    if(reste <= 0){
      clearInterval(tic);
      o.remove();
      gsPause(false);
      acIntervals = []; acLong = []; acBlockedUntil = 0; acPenalty = AC_PENALTY_MS;
      acGapDirty = true;
      gsShowSyncNote('Suspension terminée — le stand rouvre.');
    }
  }, 1000);
}

/* Il y avait ici une fonction extraClicksPerGooseClick() qui cherchait un objet
   'mouse_click' absent d'ITEMS — elle renvoyait donc toujours 0 — et dont le texte de
   boutique parlait de « clic sur l'oie », vocabulaire venu d'un autre jeu. Supprimée,
   avec la branche extraClicksByLevel de renderShop() qui était inatteignable, et la
   variable _sell3d qui n'était jamais relue. */
sell = function(clientX, clientY, ev){
  // ev est l'événement d'origine : c'est lui qui porte isTrusted, le seul signal
  // qu'un script ne peut pas contrefaire depuis la page.
  if(!isHumanLikeClick(performance.now(), ev)) return false; // clic ignoré : pattern non humain

  // L'amélioration souris reproduit des clics manuels : elle ne modifie pas la valeur d'un clic.
  const clickCount = 1;
  const isCrit = Math.random() < CRIT_CHANCE;
  const gain = (isCrit ? state.clickValue * CRIT_MULT : state.clickValue) * clickCount;
  state.money += gain;
  state.totalEarned += gain;
  state.lifetimeTotalEarned += gain;
  state.manualSales += clickCount;
  state.lifetimeManualSales += clickCount;
  if(isCrit) state.critCount += 1;

  const floater = document.createElement('div');
  floater.className = 'floater';
  if(isCrit){
    floater.textContent = '★ CRITIQUE ★ +' + gain.toFixed(2) + ' €';
    // L'orange se noyait dans le bois clair de la table : on passe à un jaune
    // franc, qu'aucun élément de la scène ne porte, et on garde le contour noir
    // du .floater en y ajoutant une lueur pour la distinguer d'une vente normale.
    floater.style.color = '#FFE34F';
    floater.style.fontSize = '25px';
    floater.style.textShadow =
      '-1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000,'
      + '0 0 16px rgba(255,190,40,.95), 0 3px 12px rgba(0,0,0,.9)';
    floater.style.animationDuration = '1.15s';
    playCrit();
  } else {
    floater.textContent = '+' + gain.toFixed(2) + ' €';
    playPop();
  }
  floater.style.left = clientX + 'px';
  floater.style.top = clientY + 'px';
  document.body.appendChild(floater);
  setTimeout(()=>floater.remove(), 800);

  renderTop();
  refreshShopPrices();
  renderDistributeur();
  checkAchievements();
  return true;
};

/* ======================================================================
   LES OBJECTIFS DU JOUR — affichage
   ======================================================================
   Tout est décidé côté serveur : les objectifs, la progression, la récompense
   et son versement. Cette partie ne fait que demander l'état et le dessiner —
   elle ne calcule rien qui compte, et ne peut donc rien réclamer.
   ====================================================================== */
const GS_DAILY_REFRESH_MS = 60000;
let gsDailyDernier = null;

async function gsChargerDaily(){
  if(!GS_LOGGED_IN) return;
  try {
    const res = await gsCloudRequest('daily');
    // Une récompense versée pendant qu'on jouait : on le dit, sinon l'argent
    // apparaît en banque sans explication.
    if(Number(res.credite) > 0){
      gsShowSyncNote('Objectif du jour atteint — ' + fmt(res.credite) + ' versés en banque');
      gsBank = Number(res.bank_eur) || gsBank;
      renderDistributeur();
    }
    gsDailyDernier = res;
    gsRendreDaily(res);
  } catch(err){
    // Silencieux : la prochaine tentative a lieu dans une minute.
  }
}

function gsRendreDaily(res){
  const bloc = document.getElementById('gsDailyBloc');
  const liste = document.getElementById('gsDailyListe');
  if(!bloc || !liste) return;
  const objectifs = (res && res.objectifs) || [];
  if(!objectifs.length){ bloc.style.display = 'none'; return; }
  bloc.style.display = '';

  const heures = Math.max(0, Math.floor((Number(res.reste) || 0) / 3600));
  const info = document.getElementById('gsDailyInfo');
  if(info){
    // fmt() pose déjà le symbole € : en rajouter un donnait « 8 140,50 € € ».
    info.textContent = fmt(res.recompense) + ' par objectif · nouveaux objectifs dans '
      + (heures >= 1 ? heures + ' h' : 'moins d\'une heure');
  }

  liste.innerHTML = '';
  objectifs.forEach(o=>{
    const el = document.createElement('div');
    el.className = 'gs-daily-ligne' + (o.termine ? ' est-fait' : '');

    const haut = document.createElement('div');
    haut.className = 'd-haut';
    const nom = document.createElement('span');
    nom.textContent = (o.termine ? '✔ ' : '') + o.texte;
    const chiffre = document.createElement('span');
    chiffre.className = 'd-chiffre';
    chiffre.textContent = fmtInt(o.fait) + ' / ' + fmtInt(o.seuil);
    haut.append(nom, chiffre);

    const barre = document.createElement('div');
    barre.className = 'd-barre';
    const fill = document.createElement('div');
    fill.style.width = Math.min(100, Number(o.pct) || 0) + '%';
    barre.appendChild(fill);

    el.append(haut, barre);
    liste.appendChild(el);
  });
}

if(GS_PANEL === 'jeu' && GS_LOGGED_IN){
  setInterval(gsChargerDaily, GS_DAILY_REFRESH_MS);
}

/* ======================================================================
   LES ÉVÉNEMENTS — le catalogue et la carte de choix
   ======================================================================
   Une carte apparaît en bas à droite avec deux options et un compte à rebours.
   Elle ne bloque pas le jeu : on peut continuer à servir pendant qu'on décide.
   Sans réponse, l'option par défaut s'applique — un joueur parti se faire un
   café ne doit pas retrouver son stand figé sur une question.

   Chaque option ne touche qu'au rythme des clients, au prix des améliorations
   ou au temps d'ouverture. Voir la règle en tête de gsEffets : rien ici ne
   crée d'euros, sinon le serveur les refuserait au dépôt.
   ====================================================================== */
const GS_EVT_DELAI_MIN = 180;   // secondes entre deux événements
const GS_EVT_DELAI_MAX = 360;
const GS_EVT_DECISION  = 25;    // secondes pour choisir
const GS_EVT_NIVEAU_MIN = 2;    // palier de stand à partir duquel ça commence

const GS_EVENEMENTS = [
  {
    id: 'pointe',
    emoji: '&#128101;',
    titre: 'Heure de pointe',
    texte: "Une file se forme sur le trottoir. Tu ouvres grand, ou tu prends ton temps ?",
    options: [
      {
        libelle: 'Ouvrir grand',
        detail: '3× plus de clients pendant 2 min, mais ils sont pressés (patience −40 %)',
        appliquer: ()=>{
          gsPoserEffet('arrivees', 3, 120);
          gsPoserEffet('patience', 0.6, 120);
          return 'Le stand est pris d\'assaut — sers vite.';
        }
      },
      {
        libelle: 'Servir tranquillement',
        detail: '1,6× plus de clients pendant 2 min, patience normale',
        defaut: true,
        appliquer: ()=>{
          gsPoserEffet('arrivees', 1.6, 120);
          return 'Rythme tenu, personne ne s\'énerve.';
        }
      }
    ]
  },
  {
    id: 'fournisseur',
    emoji: '&#128666;',
    titre: 'Fournisseur de passage',
    texte: "Un fournisseur propose son lot du jour. Il faut payer maintenant.",
    options: [
      {
        libelle: 'Acheter le lot',
        detail: 'Coûte 30 % de ta caisse — améliorations à −30 % pendant 3 min',
        cout: ()=> state.money * 0.30,
        appliquer: ()=>{
          const prix = state.money * 0.30;
          state.money -= prix;
          gsPoserEffet('cout', 0.7, 180);
          renderTop(); renderShop();
          return 'Lot payé ' + fmt(prix) + ' — les prix baissent pendant 3 min.';
        }
      },
      {
        libelle: 'Passer son tour',
        detail: 'Rien ne change',
        defaut: true,
        appliquer: ()=> 'Le fournisseur repart avec son lot.'
      }
    ]
  },
  {
    id: 'controle',
    emoji: '&#128683;',
    titre: 'Contrôle en approche',
    texte: "Deux silhouettes remontent la rue en regardant les stands.",
    options: [
      {
        libelle: 'Ranger le stand',
        detail: 'Fermé 20 s — aucune vente à la main, le revenu automatique continue',
        defaut: true,
        appliquer: ()=>{
          gsFermerLeStand(20);
          return 'Stand rangé. Vingt secondes à attendre.';
        }
      },
      {
        libelle: 'Ne rien changer',
        detail: 'Une chance sur deux que ça passe — sinon, fermé 50 s',
        appliquer: ()=>{
          if(Math.random() < 0.5) return 'Ils sont passés sans s\'arrêter. Bien joué.';
          gsFermerLeStand(50);
          // Les clients présents ne restent pas pour assister à la scène.
          if(typeof clients !== 'undefined'){
            clients.slice().forEach(c => { if(c.etat === 'attend') clientPerdu(c); });
          }
          return 'Mauvaise pioche : contrôle, stand fermé 50 s.';
        }
      }
    ]
  }
];

let gsEvtProchain = 90;        // premier événement après une minute et demie
let gsEvtEnCours  = null;
let gsEvtTimer    = null;

function gsEvtPeutJouer(){
  return GS_PANEL === 'jeu' && !gsJeuEnPause && standLevel() >= GS_EVT_NIVEAU_MIN;
}

function gsEvtLancer(){
  if(gsEvtEnCours) return;
  const evt = GS_EVENEMENTS[Math.floor(Math.random() * GS_EVENEMENTS.length)];
  gsEvtEnCours = evt;

  const carte = document.createElement('div');
  carte.id = 'gsEvtCarte';
  carte.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:2400;max-width:330px;'
    + 'background:#141C15;border:1px solid var(--gold);border-radius:14px;padding:15px 16px;'
    + "box-shadow:0 12px 40px rgba(0,0,0,.6);font-family:'Inter',sans-serif;color:#EDEAE0;";
  carte.innerHTML =
      '<div style="font-size:22px;margin-bottom:4px;">' + evt.emoji + '</div>'
    + '<div style="font-family:\'Bricolage Grotesque\',sans-serif;font-weight:800;font-size:14.5px;margin-bottom:5px;">'
    +   evt.titre + '</div>'
    + '<div style="font-size:12.5px;color:#A8B5A2;line-height:1.55;margin-bottom:11px;">' + evt.texte + '</div>'
    + '<div id="gsEvtOptions" style="display:flex;flex-direction:column;gap:7px;"></div>'
    // Barre de progression du temps de décision : le texte seul se lit, la barre se
    // voit du coin de l'œil — on sait qu'il reste peu de temps sans avoir à lire.
    + '<div style="height:3px;border-radius:2px;background:rgba(255,255,255,.08);margin-top:11px;overflow:hidden;">'
    +   '<div id="gsEvtBarre" style="height:100%;width:100%;background:var(--gold);'
    +     'transition:width 1s linear,background-color .3s;"></div></div>'
    + '<div id="gsEvtChrono" style="font-family:\'JetBrains Mono\',monospace;font-size:10.5px;'
    +   'color:#7E8C79;margin-top:6px;text-align:right;"></div>';
  document.body.appendChild(carte);
  gsConsoleLigne('evt', evt.titre + ' — il faut décider.');

  const zone = carte.querySelector('#gsEvtOptions');
  evt.options.forEach(opt=>{
    const b = document.createElement('button');
    b.type = 'button';
    b.style.cssText = 'all:unset;cursor:pointer;display:block;padding:9px 11px;border-radius:10px;'
      + 'background:rgba(76,175,61,.16);border:1px solid rgba(76,175,61,.4);';
    b.innerHTML = '<div style="font-family:\'JetBrains Mono\',monospace;font-size:12px;font-weight:700;color:#CFEFC4;">'
      + opt.libelle + '</div>'
      + '<div style="font-size:11px;color:#8FA089;line-height:1.45;margin-top:2px;">' + opt.detail + '</div>';
    b.addEventListener('click', (ev)=>{ if(ev.isTrusted) gsEvtChoisir(opt); });
    zone.appendChild(b);
  });

  let reste = GS_EVT_DECISION;
  const chrono = carte.querySelector('#gsEvtChrono');
  const barre  = carte.querySelector('#gsEvtBarre');
  const defaut = evt.options.find(o => o.defaut) || evt.options[evt.options.length - 1];
  const majBarre = ()=>{
    if(!barre) return;
    const part = Math.max(0, reste) / GS_EVT_DECISION;
    barre.style.width = (part * 100) + '%';
    // Les cinq dernières secondes, la barre passe au rouge : c'est le même
    // signal visuel que le bandeau « stand fermé », le joueur le reconnaît déjà.
    barre.style.background = reste <= 5 ? '#C8503C' : 'var(--gold)';
  };
  const tic = ()=>{
    reste -= 1;
    if(chrono) chrono.textContent = 'sans réponse : « ' + defaut.libelle + ' » dans ' + Math.max(0, reste) + ' s';
    majBarre();
    if(reste <= 0) gsEvtChoisir(defaut);
  };
  if(chrono) chrono.textContent = 'sans réponse : « ' + defaut.libelle + ' » dans ' + reste + ' s';
  majBarre();
  gsEvtTimer = setInterval(tic, 1000);
}

function gsEvtChoisir(opt){
  clearInterval(gsEvtTimer);
  gsEvtTimer = null;
  document.getElementById('gsEvtCarte')?.remove();
  gsEvtEnCours = null;
  gsEvtProchain = GS_EVT_DELAI_MIN + Math.random() * (GS_EVT_DELAI_MAX - GS_EVT_DELAI_MIN);
  try {
    const message = opt.appliquer();
    if(message){ gsShowSyncNote(message); gsConsoleLigne('evt', message); }
  } catch(err){
    console.warn('[GreenStand] Événement :', err);
  }
  gsMajBandeauEffets();
}

/* Le bandeau des effets en cours, au-dessus de la scène. Sans lui, un joueur ne
   sait pas pourquoi ses clients arrivent plus vite ou ses prix ont baissé.
   Le cas « fermé » est mis en avant : c'est le seul des trois qui empêche
   complètement de vendre à la main, il mérite d'être vu au premier coup d'œil
   plutôt que noyé dans la même ligne discrète que les deux autres. */
function gsMajBandeauEffets(){
  const el = document.getElementById('gsEffetsBandeau');
  if(!el) return;
  const p = gsEffetActif('arrivees');
  const c = gsEffetActif('cout');
  const f = gsEffetActif('ferme');

  const bouts = [];
  if(p) bouts.push('&#128101; Affluence ×' + gsEffets.arrivees.mult.toFixed(1)
                   + (gsEffetActif('patience') ? ' (clients pressés)' : '') + ' · ' + p + ' s');
  if(c) bouts.push('&#128666; Améliorations −' + Math.round((1 - gsEffets.cout.mult) * 100) + ' % · ' + c + ' s');

  if(f){
    // Gros compte à rebours dédié : on sait exactement dans combien de temps
    // ça repart, sans avoir à deviner.
    el.classList.add('bandeau-urgent');
    el.innerHTML = '<div class="bandeau-urgent-ligne">'
      + '&#128683; Stand fermé — ça repart dans <b>' + f + ' s</b></div>'
      + (bouts.length ? '<div class="bandeau-urgent-reste">' + bouts.join(' &nbsp;·&nbsp; ') + '</div>' : '');
  } else {
    el.classList.remove('bandeau-urgent');
    el.innerHTML = bouts.join(' &nbsp;·&nbsp; ');
  }
  el.style.display = (f || bouts.length) ? 'block' : 'none';
}

/* Le compteur « prochain client », dans l'en-tête du comptoir. Masqué tant que
   quelqu'un attend déjà ; sinon, annonce combien de temps avant la prochaine
   arrivée — l'accalmie ne ressemble plus à une panne. */
function gsMajProchainClient(){
  const el = document.getElementById('gsProchainClient');
  if(!el) return;
  const reste = gsProchainClientSecondes();
  if(reste === null){ el.style.display = 'none'; return; }
  el.style.display = 'flex';
  el.innerHTML = reste <= 1
    ? '&#128100; Un client approche…'
    : '&#8987; Prochain client dans <b>' + Math.ceil(reste) + ' s</b>';
}

// Une seule horloge pour les trois : le compte à rebours du prochain événement,
// le bandeau d'effets, et le compteur « prochain client ».
setInterval(()=>{
  gsMajBandeauEffets();
  gsMajProchainClient();
  if(!gsEvtPeutJouer() || gsEvtEnCours) return;
  // Onglet en arrière-plan : on ne pose pas une question que personne ne voit.
  if(document.visibilityState === 'hidden') return;
  gsEvtProchain -= 1;
  if(gsEvtProchain <= 0) gsEvtLancer();
}, 1000);

/* ---------------- LE TICK DE REVENU ----------------
   Avant : setInterval(1000) qui ajoutait exactement perSec à chaque tour. Deux
   problèmes, tous deux visibles en jeu :

     1. les navigateurs ralentissent les minuteurs des onglets masqués (jusqu'à un
        tour par minute) — le stand s'arrêtait donc de produire dès que le joueur
        regardait ailleurs, et rien ne rattrapait au retour ;
     2. un tour en retard créditait quand même une seconde pile, d'où une dérive.

   Maintenant on mesure le temps réellement écoulé. Le rattrapage au retour d'onglet
   devient automatique, et la dérive disparaît. Le rattrapage est borné par la même
   fenêtre que les gains hors-ligne : un onglet resté ouvert trois jours ne verse pas
   plus qu'une absence de trois jours. */
let lastTickAt = Date.now();
setInterval(()=>{
  const now = Date.now();
  let dt = (now - lastTickAt) / 1000;
  lastTickAt = now;
  if(dt <= 0) return;
  // Stand figé : on ne produit rien ET on n'accumule pas le temps écoulé, sinon
  // dix minutes de suspension seraient versées d'un coup à la réouverture.
  if(gsJeuEnPause) return;

  // Au-delà de quelques secondes, on est resté en arrière-plan : le rattrapage se
  // fait au taux hors-ligne, comme une absence, et sous le même plafond.
  if(dt > 5){
    dt = Math.min(dt, currentOfflineCapSeconds()) * currentOfflineRate();
  }
  if(state.perSec > 0){
    const gain = state.perSec * dt;
    state.money += gain;
    state.totalEarned += gain;
    state.lifetimeTotalEarned += gain;
    renderTop(); refreshShopPrices(); renderDistributeur();
    checkAchievements();
  }
}, 1000);

/* Le compte à rebours du bandeau (stand fermé, affluence, remise) descend chaque
   seconde. Il était rafraîchi par la boucle des ÉVÉNEMENTS, qui ne tourne qu'à
   partir du niveau 2 et seulement sur la page du stand : une fermeture restaurée
   au chargement pouvait donc s'afficher figée. */
setInterval(gsMajBandeauEffets, 1000);

setInterval(saveGame, 5000);
window.addEventListener('beforeunload', saveGame);
window.addEventListener('pagehide', saveGame);
document.addEventListener('visibilitychange', ()=>{ if(document.visibilityState === 'hidden') saveGame(); });

// `?.` : ce bouton vit dans la barre de statistiques, présente sur toutes les
// pages — mais une seule ligne non protégée ici tuerait TOUT le script sur la
// page où elle ne le serait plus, y compris loadGame(). Le fichier a déjà payé ce
// prix deux fois (voir les commentaires sur gsAdmOnglet et sur TEXTURES).
document.getElementById('resetBtn')?.addEventListener('click', ()=>{
  if(!confirm('Réinitialiser toute ta progression : argent, améliorations et gérants ?')) return;
  try { localStorage.removeItem(SAVE_KEY); } catch(e){}
  if(GS_LOGGED_IN) gsCloudRequest('state_reset').catch(()=>{}).finally(()=>location.reload());
  else location.reload();
});


const muteBtn = document.getElementById('muteBtn');
muteBtn.textContent = soundEnabled ? '🔊' : '🔇';
muteBtn.addEventListener('click', ()=>{
  setSoundEnabled(!soundEnabled);
  if(soundEnabled) ensureAudio();
});

/* Le démarrage est TOUT EN BAS du fichier, après le Distributeur et la synchro.
   renderAll() appelle renderDistributeur(), qui lit gsBank : une variable déclarée
   avec `let` n'existe pas avant sa ligne de déclaration (zone morte temporelle), et
   démarrer ici planterait la page avec un ReferenceError. */

/* ======================================================================
   LE DISTRIBUTEUR — euros du stand -> monnaie du site
   ======================================================================
   Le stand ne fabrique plus de jetons tout seul. Il fabrique des EUROS, qui
   s'accumulent dans une banque tenue par le serveur, et c'est le joueur qui
   décide quoi en tirer : jetons, gemmes ou clés.

   Les taux sont ceux du comptoir de change du site (1 gemme = 6 000 jetons,
   1 clé = 30 gemmes), donc aucun chemin n'est plus rentable qu'un autre.
   Ils viennent du serveur : rien n'est écrit en dur ici.

   Tout se décide côté serveur (greenstand_exchange) : ce fichier ne fait
   qu'afficher un solde et poster une demande.
   ====================================================================== */
let gsBank = GS_BANK0;                // banque en euros, telle que le serveur la connaît
let gsLastCapEur = 0;                 // dernier plafond par seconde annoncé par le serveur

/* Ce qui est gagné mais pas encore déposé. C'est exactement le montant que le
   prochain dépôt proposera au serveur. */
function gsPendingEur(){
  const p = (Number(state.lifetimeTotalEarned) || 0) - (Number(state.gsSyncedLifetime) || 0);
  return p > 0 ? p : 0;
}
let gsRates = GS_RATES0;
let gsWallet = GS_WALLET0;
let gsExchanging = false;

/* La place restante sur un solde du site — la SEULE limite d'un échange depuis que
   le plafond « par échange » a sauté. Une monnaie sans plafond connu n'en a pas
   plutôt que d'en avoir un à zéro : mieux vaut laisser le serveur refuser qu'un
   bouton mort sans explication. */
function gsPlaceRestante(code){
  const max = Number(GS_CURRENCY_MAX && GS_CURRENCY_MAX[code]);
  if(!Number.isFinite(max) || max <= 0) return Infinity;
  return Math.max(0, max - (Number(gsWallet[code]) || 0));
}

const GS_CURRENCY_META = {
  jetons: {label:'Jetons DT', icon:'&#129689;', one:'jeton',  many:'jetons'},
  gemmes: {label:'Gemmes',    icon:'&#128142;', one:'gemme',  many:'gemmes'},
  cles:   {label:'Cl&eacute;s', icon:'&#128273;', one:'cl&eacute;', many:'cl&eacute;s'},
};

function fmtInt(n){ return Math.trunc(n).toLocaleString('fr-FR'); }

function renderDistributeur(){
  const bankEl = document.getElementById('gsBank');
  const noteEl = document.getElementById('gsBankNote');
  const grid   = document.getElementById('gsDistGrid');
  if(!grid) return;

  if(bankEl) bankEl.textContent = fmt(gsBank);
  if(noteEl){
    // Ce qui est gagné mais pas encore déposé. Le chiffre du haut est celui que le
    // SERVEUR a confirmé — c'est le seul qu'on puisse échanger tout de suite, donc
    // c'est le seul qu'on affiche en gros. Le reste est annoncé à part plutôt que
    // gonflé dans le total : un total qui redescendrait après un plafonnement serait
    // pire que pas de total du tout.
    const enRoute = gsPendingEur();
    if(gsLastCapEur > 0 && enRoute > gsLastCapEur * 1.5){
      noteEl.textContent = 'Dépôt plafonné à ' + fmt(gsLastCapEur) + ' par seconde — '
        + fmt(enRoute) + ' en attente, ça rentre au fur et à mesure.';
    } else if(enRoute >= 1){
      noteEl.textContent = '+ ' + fmt(enRoute) + ' en cours de dépôt';
    } else if(gsBank >= 1){
      noteEl.textContent = 'Prêt à échanger.';
    } else {
      noteEl.textContent = 'Vends au stand pour alimenter le distributeur.';
    }
  }

  // Construit une fois, met à jour ensuite : même raison que pour la boutique.
  if(!grid.children.length){
    Object.keys(gsRates).forEach(code=>{
      const meta = GS_CURRENCY_META[code];
      const card = document.createElement('div');
      card.className = 'gs-dist-card';
      card.dataset.code = code;
      card.innerHTML =
        '<h4><span>' + meta.icon + '</span>' + meta.label + '</h4>' +
        '<span class="rate" data-rate></span>' +
        '<span class="have" data-have></span>' +
        '<div class="gs-dist-row">' +
          '<input type="number" min="1" step="1" value="1" aria-label="Quantité de ' + meta.label + '">' +
          '<button type="button">Échanger</button>' +
        '</div>' +
        '<button type="button" class="gs-dist-max">Tout ce que je peux</button>';
      grid.appendChild(card);

      const input = card.querySelector('input');
      card.querySelector('.gs-dist-row button').addEventListener('click', ()=>{
        gsExchange(code, parseInt(input.value, 10));
      });
      card.querySelector('.gs-dist-max').addEventListener('click', ()=>{
        // Borné par les trois limites réelles, pour ne jamais demander au serveur
        // quelque chose qu'il refusera : la banque, le plafond par échange, et la
        // place restante sur le solde (les colonnes de monnaie sont des INT UNSIGNED).
        const parBanque = Math.floor(gsBank / gsRates[code]);
        input.value = Math.max(1, Math.min(parBanque, GS_EXCHANGE_MAX, gsPlaceRestante(code)));
        renderDistributeur();
      });
      input.addEventListener('input', ()=> renderDistributeur());
    });
  }

  Array.from(grid.children).forEach(card=>{
    const code = card.dataset.code;
    const rate = gsRates[code] || 1;
    const meta = GS_CURRENCY_META[code];
    const input = card.querySelector('input');
    const qty = Math.max(1, parseInt(input.value, 10) || 1);
    const cost = rate * qty;

    const place = gsPlaceRestante(code);
    const tropGros  = qty > GS_EXCHANGE_MAX;
    const tropPlein = qty > place;
    const tropCher  = cost > gsBank + 0.005;

    card.querySelector('[data-rate]').textContent = fmt(rate) + ' → 1 ' + meta.one.replace(/&[a-z]+;/g,'é');
    card.querySelector('[data-have]').textContent =
        tropGros  ? ('Maximum ' + fmtInt(GS_EXCHANGE_MAX) + ' par échange')
      : tropPlein ? ('Il ne reste de la place que pour ' + fmtInt(Math.max(0, place)))
      : ('Tu en as ' + fmtInt(gsWallet[code] || 0) + ' · coût ' + fmt(cost));
    card.querySelector('.gs-dist-row button').disabled =
      gsExchanging || tropCher || tropGros || tropPlein;
  });
}

async function gsExchange(code, qty){
  if(gsExchanging) return {ok: false, message: 'Un échange est déjà en cours.'};
  qty = Math.max(1, parseInt(qty, 10) || 0);
  if(!gsRates[code] || qty < 1) return {ok: false, message: 'Demande invalide.'};

  gsExchanging = true;
  renderDistributeur();
  let sortie = {ok: false, message: 'Échange refusé'};
  try {
    const result = await gsCloudRequest('exchange', {to: code, qty: String(qty)});
    gsBank = Number(result.bank_eur) || 0;
    if(result.wallet) gsWallet = result.wallet;
    const meta = GS_CURRENCY_META[code];
    gsShowSyncNote('+' + fmtInt(result.gained) + ' ' + meta.many.replace(/&[a-z]+;/g,'é'));
    gsUpdateWallet(Number(gsWallet.jetons), code === 'jetons' ? result.gained : 0);
    // Rendu pour l'écran du distributeur, qui en imprime un reçu. Les cartes,
    // elles, l'ignorent : c'est le même chemin, avec ou sans écran.
    sortie = {ok: true, gained: result.gained};
  } catch(err) {
    // Le serveur explique pourquoi (solde insuffisant, quantité trop grande…) :
    // on montre SON message, pas un « erreur » générique.
    gsShowSyncNote(err.message || 'Échange refusé');
    sortie = {ok: false, message: err.message || 'Échange refusé'};
  } finally {
    gsExchanging = false;
    renderDistributeur();
    gsRefreshBoardsIfOpen();
  }
  return sortie;
}

/* ======================================================================
   SYNCHRO JETONS DT (site) : convertit périodiquement l'argent gagné au
   stand depuis la dernière synchro en jetons DT sur le compte du joueur.
   Le montant réellement crédité est décidé par le serveur (greenstand_
   action.php / greenstand_lib.php) — ce delta n'est qu'une suggestion.
   ====================================================================== */
// Toutes les 5 secondes, pas 15. À 1 M €/s, un dépôt toutes les 15 s laissait la
// banque afficher jusqu'à 15,6 M € de retard sur le compteur du stand, et avancer par
// à-coups. À 5 s le retard tombe à 5 M, et la ligne « en cours de dépôt » ci-dessous
// montre en direct ce qui n'est pas encore arrivé.
const GS_SYNC_INTERVAL_MS = 5000;
let gsSyncNoteTimeout = null;
let gsSyncing = false;
let gsSyncTimer = null;

function gsShowSyncNote(text){
  const el = document.getElementById('gsSyncNote');
  if(!el) return;
  el.textContent = text;
  el.classList.add('show');
  clearTimeout(gsSyncNoteTimeout);
  gsSyncNoteTimeout = setTimeout(()=> el.classList.remove('show'), 2500);
}

function gsUpdateWallet(jetons, credited){
  const balance = document.getElementById('gsDtBalance');
  const wallet = document.getElementById('gsWallet');
  if(balance && Number.isFinite(jetons)){
    balance.textContent = Math.trunc(jetons).toLocaleString('fr-FR');
  }
  if(wallet && credited > 0){
    wallet.classList.remove('is-credited');
    void wallet.offsetWidth;
    wallet.classList.add('is-credited');
    setTimeout(()=> wallet.classList.remove('is-credited'), 700);
  }
}

/**
 * Le serveur a détecté une cadence de clic impossible : on le dit clairement.
 * Tant que la sourdine dure, les ventes manuelles ne financent plus aucun dépôt ;
 * le revenu automatique du stand, lui, continue normalement.
 */
function gsAntiClicAvis(info){
  const el = document.getElementById('gsAntiClicBanner');
  if(!el) return;
  if(!info || !info.blocked){ el.style.display = 'none'; return; }
  const min = Math.ceil((Number(info.seconds) || 0) / 60);
  el.textContent = '🚫 Auto-clic détecté : tes ventes manuelles ne rapportent plus rien '
    + 'pendant ' + min + ' min. Le stand continue de produire tout seul. '
    + 'Cadence maximale acceptée : ' + (info.human_cps || GS_HUMAN_CPS) + ' clics/seconde.';
  el.style.display = 'block';
}

async function gsSyncDT(){
  if(!GS_LOGGED_IN || gsSyncing) return false;

  const syncedAtStart = Number(state.gsSyncedLifetime) || 0;
  const earnedAtStart = Number(state.lifetimeTotalEarned) || 0;
  const delta = earnedAtStart - syncedAtStart;
  if(!Number.isFinite(delta) || delta <= 0.005) return false;

  // Cible figée : les ventes faites pendant la requête restent pour le tour suivant.
  const syncedTarget = earnedAtStart;
  gsSyncing = true;

  try {
    const fd = new FormData();
    fd.append('action', 'sync');
    fd.append('csrf', GS_CSRF);
    fd.append('delta_eur', delta.toFixed(2));
    fd.append('state', JSON.stringify(gameSaveData()));

    const response = await fetch('greenstand_action.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Accept': 'application/json' },
      keepalive: true
    });

    let result = null;
    try { result = await response.json(); } catch(e) {}
    if(!response.ok || !result || !result.ok){
      throw new Error(result && result.error ? result.error : 'Synchronisation refusée');
    }

    // Le serveur a confirmé le crédit : on avance le curseur et on met le solde
    // affiché à jour tout de suite, sans rechargement de la page.
    // Le curseur n'avance que de ce que le serveur a RÉELLEMENT accepté. Avant, il
    // sautait à la cible complète même quand le serveur plafonnait : la différence
    // était perdue pour toujours. Maintenant le reliquat repart au tour suivant.
    const creditedEur = Number(result.credited_eur) || 0;
    state.gsSyncedLifetime = (Number(state.gsSyncedLifetime) || 0) + creditedEur;

    gsBank = Number(result.bank_eur) || gsBank;
    if(result.wallet) gsWallet = result.wallet;
    gsAntiClicAvis(result.autoclick);
    // Le serveur dit combien il accepte par fenêtre : on s'en sert pour expliquer
    // l'attente au joueur au lieu de le laisser deviner.
    const capFenetre = Number(result.cap_eur) || 0;
    if(capFenetre > 0) gsLastCapEur = capFenetre / (GS_SYNC_INTERVAL_MS / 1000);
    renderDistributeur();
    saveGame();
    return true;
  } catch(err) {
    // Silencieux : le prochain passage a lieu dans GS_SYNC_INTERVAL_MS et rattrape
    // le retard tout seul. L'ancien bandeau annonçait « nouvel essai dans 15 s »
    // alors que l'intervalle est de 5 s, et s'affichait au moindre hoquet réseau.
    console.warn('[GreenStand] Synchro DT reportée :', err.message);
    return false;
  } finally {
    gsSyncing = false;
  }
}

function gsScheduleSync(delay = GS_SYNC_INTERVAL_MS){
  clearTimeout(gsSyncTimer);
  gsSyncTimer = setTimeout(async ()=>{
    await gsSyncDT();
    gsScheduleSync(GS_SYNC_INTERVAL_MS);
  }, delay);
}

if(GS_LOGGED_IN){
  // Premier versement rapide, puis un passage toutes les GS_SYNC_INTERVAL_MS (5 s).
  gsScheduleSync(1000);
  window.addEventListener('online', ()=> gsScheduleSync(0));
  window.addEventListener('pagehide', gsSyncDT);
  document.addEventListener('visibilitychange', ()=>{
    if(document.visibilityState === 'visible') gsScheduleSync(0);
    else gsSyncDT();
  });
}

/* ======================================================================
   NAVIGATION — un panneau à la fois
   ====================================================================== */
// La navigation est faite de liens : c'est le serveur qui envoie la bonne section.
// Il ne reste qu'à demander les données de celle qui est ouverte — après le chargement
// du document, car ces fonctions lisent des variables déclarées plus bas dans ce script.
document.addEventListener('DOMContentLoaded', ()=>{
  if(GS_PANEL === 'classement'){
    gsLoadBoards(true);
    setInterval(()=> gsLoadBoards(true), GS_BOARDS_REFRESH_MS);
  }
  // Le panel admin ne doit JAMAIS pouvoir emporter le jeu avec lui : une erreur
  // ici laissait la page à 0,00 €, sans état chargé, compte apparemment déconnecté.
  if(GS_PANEL === 'jeu' && GS_LOGGED_IN) gsChargerDaily();
  if(GS_PANEL === 'boutique') gsChargerBoutique();
  if(GS_PANEL === 'succes'){
    const recherche = document.getElementById('gsSuccesRecherche');
    if(recherche) recherche.addEventListener('input', ()=>{
      gsSuccesQ = recherche.value.trim().toLowerCase();
      renderSucces();
    });
    document.querySelectorAll('input[name="gsSuccesVue"]').forEach(r=>{
      r.addEventListener('change', ()=>{ gsSuccesVue = r.value; renderSucces(); });
    });
    renderSucces();
  }
  if(GS_PANEL === 'distributeur') gsMonterAtm();

  // La pastille du menu, sur toutes les pages sauf celle du téléphone (qui a
  // mieux : l'état complet). Une fois au chargement, puis toutes les 30 secondes.
  gsChargerPastilles();
  setInterval(gsChargerPastilles, 30000);

  if(GS_PANEL === 'telephone'){
    gsChargerTelephone();
    // Une seconde : c'est l'affichage qui descend, pas le réseau. gsTelTicTac()
    // ne redemande l'état au serveur que lorsqu'il se passe vraiment quelque
    // chose (une commande périmée, un délai d'appel écoulé), et jamais plus d'une
    // fois toutes les dix secondes.
    gsTelTimer = setInterval(gsTelTicTac, 1000);
    // Filet de sécurité : même sans rien à signaler, on se resynchronise de temps
    // en temps (une livraison faite sur un autre onglet, par exemple).
    setInterval(()=>{ if(!gsTelEnCours) gsChargerTelephone(); }, 60000);
  }
  if(GS_PANEL === 'admin'){
    try {
      gsLoadAdminAssets();
      // Le formulaire des noms se remplit depuis GS_STANDS, déjà là : il ne doit
      // pas attendre la réponse du serveur pour les vignettes (ni disparaître si
      // cette réponse échoue).
      gsRenderStandNoms();
      gsRenderBadgeAdmin();
      gsBadgeFormTitre(null);
      gsLoadGoldPlayers('');
      let dernier = 'badges';
      try { dernier = localStorage.getItem('gs_admin_tab') || 'badges'; } catch(e){}
      gsAdmOnglet(dernier);
    } catch(err){
      console.error('[GreenStand] Panel admin indisponible :', err);
      gsShowSyncNote('Panel admin indisponible — le jeu, lui, fonctionne.');
    }
  }
});

/* Repose les effets achetés à la boutique, tels que le serveur les compte. Appelé
   au chargement du stand, et après un achat fait depuis la page Boutique. */
function gsAppliquerEffetsAchetes(effets){
  if(!Array.isArray(effets)) return;
  effets.forEach(e=>{
    if(!e || !gsEffets[e.nom]) return;
    const restant = Number(e.restant) || 0;
    if(restant > 0) gsPoserEffet(e.nom, Number(e.mult) || 1, restant);
  });
  gsMajBandeauEffets();
}

/* ======================================================================
   LE TÉLÉPHONE
   ======================================================================
   Le répertoire, la marchandise, et la carte du quartier où l'on va livrer.

   Comme pour la boutique, TOUT est décidé côté serveur : qui appelle, combien il
   commande, à quelle adresse, à quel prix, combien de temps il attend et combien
   de livraisons sont permises dans la journée. Cette partie affiche, et demande.
   Un prix calculé ici serait un prix négociable depuis la console.

   LA CARTE est dessinée en SVG à partir des rues envoyées par le serveur
   (greenstand_tel_rues) : les deux bouts parlent des mêmes rues, sinon une
   adresse tirée par le serveur tomberait à côté de la chaussée. L'itinéraire
   suit les rues — on sort du stand par l'avenue Centrale, puis on remonte la rue
   du client : à angle droit, comme on marcherait vraiment.
   ====================================================================== */
let gsTel = null;
let gsTelCible = null;        // la commande sélectionnée sur la carte
let gsTelEnCours = false;
let gsTelTimer = null;
/* Quand l'état a été reçu. Les durées envoyées par le serveur (temps restant
   d'une commande, délai avant le prochain appel) sont des instantanés : sans
   cette référence, « prochain appel dans 68 s » restait affiché tel quel jusqu'au
   rafraîchissement suivant, et il fallait recharger la page pour voir le chiffre
   bouger. On retranche le temps écoulé depuis. */
let gsTelRecuA = 0;
let gsTelDernierAppel = 0;

/* Le temps restant d'une durée reçue du serveur, à cette seconde-ci. */
function gsTelReste(secondes){
  const ecoule = (Date.now() - gsTelRecuA) / 1000;
  return Math.max(0, Math.round((Number(secondes) || 0) - ecoule));
}

async function gsChargerTelephone(){
  if(GS_PANEL !== 'telephone' || !GS_LOGGED_IN) return;
  try {
    const res = await gsCloudRequest('tel_state');
    gsTel = res.etat;
    gsTelRecuA = Date.now();
    renderTelephone();
    gsMajPastilles({attente: (gsTel.commandes || []).length,
                    urgence: (gsTel.commandes || []).reduce((m, c) => Math.min(m, c.restant), 1e9)});
  } catch(err){
    const el = document.getElementById('gsTelContacts');
    if(el) el.innerHTML = '<div class="gs-board-empty">' + (err.message || 'Téléphone indisponible.') + '</div>';
  }
}

/* La tête d'un contact. Si l'admin a envoyé une photo (visuel « contact_<id> »),
   c'est elle ; sinon on en dessine une, toujours la même pour un contact donné.
   Aucune image n'est empruntée à qui que ce soit : elle est tracée ici, en SVG. */
function gsAvatarContact(contact){
  const boite = document.createElement('div');
  boite.className = 'gs-avatar';
  const url = ASSET_DATA['contact_' + contact.id];
  if(url){
    const img = document.createElement('img');
    img.src = url; img.alt = contact.nom; img.loading = 'lazy';
    boite.appendChild(img);
    return boite;
  }
  // Un numéro pas encore débloqué n'a pas de tête : une silhouette, et rien de
  // plus. On ne montre pas le visage de quelqu'un qu'on n'a pas encore rencontré.
  if(contact.debloque === false){
    boite.innerHTML = '<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">'
      + '<rect width="64" height="64" rx="12" fill="#182117"/>'
      + '<circle cx="32" cy="58" r="20" fill="#20301F"/>'
      + '<circle cx="32" cy="27" r="13" fill="#20301F"/>'
      + '<text x="32" y="36" text-anchor="middle" font-size="20" font-family="JetBrains Mono, monospace"'
      + ' fill="#3E5A3A">?</text></svg>';
    return boite;
  }

  const l = contact.look || {};
  const fond = l.fond || '#2E5A26', peau = l.peau || '#C58A5B';
  const cheveux = l.cheveux || '#1F1B16', vetement = l.vetement || '#243A20';
  const parts = [];
  parts.push('<rect width="64" height="64" rx="12" fill="' + fond + '"/>');
  parts.push('<circle cx="32" cy="58" r="20" fill="' + vetement + '"/>');       // épaules
  parts.push('<rect x="27" y="34" width="10" height="9" rx="4" fill="' + peau + '"/>'); // cou
  parts.push('<ellipse cx="32" cy="27" rx="13" ry="14" fill="' + peau + '"/>'); // visage
  if(l.coiffe === 'casquette'){
    // Une casquette se reconnaît à sa VISIÈRE : elle doit dépasser franchement,
    // sinon en 38 pixels de large ça ne ressemble qu'à des cheveux.
    parts.push('<path d="M17 23a15 13 0 0 1 30 0z" fill="' + cheveux + '"/>');
    parts.push('<path d="M45 21h11a3.5 3.5 0 0 1 0 7H45z" fill="' + cheveux + '"/>');
    parts.push('<circle cx="32" cy="12" r="1.8" fill="' + cheveux + '"/>');
  } else if(l.coiffe === 'longs'){
    parts.push('<path d="M18 26a14 14 0 0 1 28 0v18h-5V27H23v17h-5z" fill="' + cheveux + '"/>');
  } else if(l.coiffe === 'capuche'){
    parts.push('<path d="M16 30a16 16 0 0 1 32 0v4h-4a12 12 0 0 0-24 0h-4z" fill="' + vetement + '"/>');
    parts.push('<path d="M20 24a12 12 0 0 1 24 0z" fill="' + cheveux + '"/>');
  } else if(l.coiffe === 'chignon'){
    parts.push('<circle cx="32" cy="12" r="6" fill="' + cheveux + '"/>');
    parts.push('<path d="M19 25a13 13 0 0 1 26 0z" fill="' + cheveux + '"/>');
  } else if(l.coiffe === 'rase'){
    parts.push('<path d="M20 23a12 12 0 0 1 24 0z" fill="' + cheveux + '" opacity=".55"/>');
  } else {
    parts.push('<path d="M19 24a13 13 0 0 1 26 0z" fill="' + cheveux + '"/>');
  }
  parts.push('<circle cx="27" cy="28" r="1.6" fill="#161A14"/><circle cx="37" cy="28" r="1.6" fill="#161A14"/>');
  if(l.accessoire === 'lunettes'){
    parts.push('<g fill="none" stroke="#EDEAE0" stroke-width="1.4" opacity=".9">'
      + '<circle cx="27" cy="28" r="4"/><circle cx="37" cy="28" r="4"/><path d="M31 28h2"/></g>');
  }
  if(l.accessoire === 'moustache') parts.push('<rect x="27" y="32" width="10" height="2.4" rx="1.2" fill="' + cheveux + '"/>');
  if(l.accessoire === 'boucles')   parts.push('<circle cx="19" cy="30" r="1.8" fill="#D9A441"/><circle cx="45" cy="30" r="1.8" fill="#D9A441"/>');
  if(l.accessoire === 'ombre')     parts.push('<rect x="19" y="20" width="26" height="12" fill="#0A0F0C" opacity=".72"/>');
  parts.push('<rect width="64" height="64" rx="12" fill="none" stroke="#FFFFFF" stroke-opacity=".12"/>');
  parts.push('<path d="M28 34q4 3 8 0" stroke="#8B5A3C" stroke-width="1.3" fill="none" opacity=".8"/>');
  boite.innerHTML = '<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">' + parts.join('') + '</svg>';
  return boite;
}

/* ---------------- LA CARTE DU QUARTIER ----------------
   Les rues viennent du serveur ; les immeubles sont les blocs qu'elles découpent.
   Le stand est au centre, les commandes sont des épingles, et l'itinéraire de la
   commande sélectionnée est tracé le long des rues. */
function gsTelDessinerCarte(){
  const hote = document.getElementById('gsTelMap');
  if(!hote || !gsTel) return;
  const rues = gsTel.rues || [];
  const h = rues.filter(r => r.axe === 'h').sort((a,b)=>a.pos-b.pos);
  const v = rues.filter(r => r.axe === 'v').sort((a,b)=>a.pos-b.pos);
  const p = [];

  p.push('<rect width="100" height="100" fill="#0B140E"/>');

  // Un tirage reproductible : la ville doit être la même à chaque affichage, sinon
  // les immeubles dansent d'un rafraîchissement à l'autre. (Mulberry32 — court, et
  // sans les motifs visibles d'un « graine * 1103515245 » dont on ne garde que les
  // bits de poids fort : la première version faisait un quartier plein de parcs
  // alignés.)
  let graine = 0x9E3779B9;
  const alea = () => {
    graine = (graine + 0x6D2B79F5) | 0;
    let t = Math.imul(graine ^ (graine >>> 15), 1 | graine);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };

  // Les pâtés de maisons : ce qui reste entre deux rues. Chacun est REMPLI
  // d'immeubles de tailles différentes plutôt que peint d'un seul aplat — c'est ce
  // qui fait la différence entre un quartier et du papier millimétré.
  const bords = liste => [0].concat(liste.map(r => r.pos)).concat([100]);
  const bh = bords(h), bv = bords(v);
  for(let i = 0; i < bh.length - 1; i++){
    for(let j = 0; j < bv.length - 1; j++){
      const y0 = bh[i] + 1.7, y1 = bh[i+1] - 1.7;
      const x0 = bv[j] + 1.7, x1 = bv[j+1] - 1.7;
      const w = x1 - x0, hh = y1 - y0;
      if(w < 2.5 || hh < 2.5) continue;
      const t = alea();

      if(t > 0.88){
        // Une place plantée d'arbres, de loin en loin.
        p.push('<rect x="' + x0.toFixed(1) + '" y="' + y0.toFixed(1) + '" width="' + w.toFixed(1)
          + '" height="' + hh.toFixed(1) + '" rx="1" fill="#183A20" stroke="#245C2C" stroke-width="0.35"/>');
        const n = Math.max(1, Math.round(Math.min(w, hh) / 3));
        for(let k = 0; k < n; k++){
          p.push('<circle cx="' + (x0 + 1.5 + alea() * (w - 3)).toFixed(1)
            + '" cy="' + (y0 + 1.5 + alea() * (hh - 3)).toFixed(1)
            + '" r="' + (0.9 + alea() * 0.9).toFixed(1) + '" fill="#2F7A38" opacity=".85"/>');
        }
        continue;
      }

      // La cour intérieure du pâté, puis les immeubles autour.
      p.push('<rect x="' + x0.toFixed(1) + '" y="' + y0.toFixed(1) + '" width="' + w.toFixed(1)
        + '" height="' + hh.toFixed(1) + '" rx="0.6" fill="#121A14"/>');

      // Une bande d'immeubles le long de chaque rue qui borde le pâté.
      const profondeur = Math.min(3.6, Math.max(1.8, Math.min(w, hh) * 0.34));
      const bande = (bx, by, bw, bh2, horizontal) => {
        let d = 0;
        const longueur = horizontal ? bw : bh2;
        while(d < longueur - 0.6){
          const taille = Math.min(longueur - d, 1.6 + alea() * 3.4);
          const creux = alea() > 0.82;           // une dent creuse : passage, cour
          if(!creux){
            const teinte = ['#3d5948', '#4b6552', '#344c40', '#58735b'][Math.floor(alea() * 4)];
            const tx = horizontal ? bx + d : bx, ty = horizontal ? by : by + d;
            const tw = horizontal ? taille : bw, th = horizontal ? bh2 : taille;
            const relief = 0.55 + alea() * 0.65;
            p.push('<rect x="' + (tx + .35) + '" y="' + (ty + .5) + '" width="' + tw + '" height="' + th + '" fill="#000" opacity=".28"/>');
            p.push('<path d="M' + tx + ' ' + (ty + th) + 'l' + relief + ' ' + (-relief) + 'h' + tw + 'l' + (-relief) + ' ' + relief + 'Z" fill="#71826a"/>');
            p.push('<path d="M' + (tx + tw) + ' ' + ty + 'l' + relief + ' ' + (-relief) + 'v' + th + 'l' + (-relief) + ' ' + relief + 'Z" fill="#203b2d"/>');
            p.push('<rect x="' + (horizontal ? bx + d : bx).toFixed(1)
              + '" y="' + (horizontal ? by : by + d).toFixed(1)
              + '" width="' + (horizontal ? taille : bw).toFixed(1)
              + '" height="' + (horizontal ? bh2 : taille).toFixed(1)
              + '" fill="' + teinte + '" stroke="#0E1611" stroke-width="0.18"/>');
          }
          d += taille;
        }
      };
      bande(x0, y0, w, profondeur, true);                          // façade nord
      bande(x0, y1 - profondeur, w, profondeur, true);             // façade sud
      if(hh > profondeur * 2.4){
        bande(x0, y0 + profondeur, profondeur, hh - profondeur * 2, false);
        bande(x1 - profondeur, y0 + profondeur, profondeur, hh - profondeur * 2, false);
      }
    }
  }

  // Le canal, en bas à droite : un quartier avec un peu d'eau se lit tout de suite.
  p.push('<path d="M100 74 L86 78 L74 86 L66 100 L100 100 Z" fill="#12303A" opacity=".95"/>');
  p.push('<path d="M100 74 L86 78 L74 86 L66 100" stroke="#1C4A57" stroke-width="0.6" fill="none"/>');

  // Les rues elles-mêmes : la chaussée, puis la ligne blanche pour les avenues.
  rues.forEach(r=>{
    const large = r.large ? 4.0 : 2.4;
    const d = r.axe === 'h' ? 'M0 ' + r.pos + ' H100' : 'M' + r.pos + ' 0 V100';
    p.push('<path d="' + d + '" stroke="#33463500" stroke-width="' + (large + 1) + '"/>');
    p.push('<path d="' + d + '" stroke="#2B3B2D" stroke-width="' + large + '" stroke-linecap="square"/>');
    if(r.large){
      p.push('<path d="' + d + '" stroke="#54704F" stroke-width="0.32" stroke-dasharray="3 2.5" opacity=".85"/>');
    }
  });

  // Le nom des rues, écrit le long de la chaussée comme sur un plan. Discret : on
  // doit pouvoir lire l'adresse d'une commande sans que la carte devienne un mur
  // de texte.
  rues.forEach(r=>{
    const taille = r.large ? 2.0 : 1.7;
    const opacite = r.large ? 0.72 : 0.5;
    if(r.axe === 'h'){
      p.push('<text x="2" y="' + (r.pos - 1.1) + '" fill="#8FA089" opacity="' + opacite
        + '" font-size="' + taille + '" font-family="JetBrains Mono, monospace">' + gsEchappeXml(r.nom) + '</text>');
    } else {
      p.push('<text x="' + (r.pos - 1.1) + '" y="2.4" fill="#8FA089" opacity="' + opacite
        + '" font-size="' + taille + '" font-family="JetBrains Mono, monospace"'
        + ' transform="rotate(90 ' + (r.pos - 1.1) + ' 2.4)">' + gsEchappeXml(r.nom) + '</text>');
    }
  });

  // L'itinéraire vers la commande sélectionnée. Il doit rester SUR LA CHAUSSÉE :
  // le stand est au croisement des deux avenues du centre, donc on descend (ou on
  // remonte) l'avenue jusqu'à la rue du client, puis on prend cette rue. L'ordre
  // des deux segments dépend de l'orientation de la rue d'arrivée — l'inverser
  // faisait passer le livreur à travers les immeubles.
  const cible = gsTelCommandeCible();
  if(cible){
    const a = cible.adresse;
    const rue = rues.find(r => r.id === a.rue);
    const trajet = (rue && rue.axe === 'h')
      ? 'M50 50 V' + a.y + ' H' + a.x      // rue horizontale : l'avenue verticale d'abord
      : 'M50 50 H' + a.x + ' V' + a.y;     // rue verticale : l'avenue horizontale d'abord
    p.push('<path d="' + trajet + '" stroke="#D9A441" stroke-width="1.1" fill="none"'
      + ' stroke-dasharray="2.5 2" opacity=".95" stroke-linejoin="round"/>');
  }

  // Le stand, au centre.
  p.push('<circle cx="50" cy="50" r="4.4" fill="#4CAF3D" opacity=".22"/>');
  p.push('<circle cx="50" cy="50" r="2.4" fill="#8BFF6B" stroke="#0B140E" stroke-width="0.7"/>');

  // Les épingles des commandes.
  (gsTel.commandes || []).forEach(c=>{
    const a = c.adresse;
    const active = cible && cible.id === c.id;
    p.push('<g class="gs-pin" data-cmd="' + c.id + '" style="cursor:pointer">');
    p.push('<circle cx="' + a.x + '" cy="' + a.y + '" r="' + (active ? 5.2 : 4.2)
      + '" fill="' + (active ? '#D9A441' : '#C8503C') + '" opacity=".25"/>');
    p.push('<path d="M' + a.x + ' ' + (a.y + 3.4) + ' l-2.4 -4.2 a2.8 2.8 0 1 1 4.8 0 Z" fill="'
      + (active ? '#D9A441' : '#C8503C') + '" stroke="#0B140E" stroke-width="0.5"/>');
    p.push('</g>');
  });

  hote.innerHTML = '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">' + p.join('') + '</svg>';
  hote.querySelectorAll('.gs-pin').forEach(g=>{
    const cmd = (gsTel.commandes || []).find(c=>String(c.id) === g.dataset.cmd);
    g.setAttribute('tabindex','0'); g.setAttribute('role','button');
    g.setAttribute('aria-label', cmd ? ('Commande de ' + cmd.nom) : 'Sélectionner cette commande');
    const select = ()=>{ gsTelCible = cmd ? cmd.id : g.dataset.cmd; renderTelephone(); };
    g.addEventListener('click', select);
    g.addEventListener('keydown', e=>{ if(e.key === 'Enter' || e.key === ' '){ e.preventDefault(); select(); document.getElementById('gsTelCommandes').querySelector('.is-cible')?.focus(); } });
  });

  const legende = document.getElementById('gsTelLegende');
  if(legende){
    legende.textContent = cible
      ? ('Itinéraire : ' + cible.adresse.numero + ' ' + cible.adresse.nom + ' — choisis « Livrer » dans le téléphone.')
      : 'Le point vert, c\'est ton stand. Les épingles rouges sont les clients qui attendent : clique dessus.';
  }
}

/* Les noms de rues partent dans du SVG assemblé à la main : ils viennent de notre
   propre table, mais on les échappe quand même — c'est la règle, pas l'exception. */
function gsEchappeXml(t){
  return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/* Une durée lisible : « 9 min », « 45 s ». */
function gsTelDuree(secondes){
  return secondes >= 60 ? (Math.round(secondes / 60) + ' min') : (secondes + ' s');
}

function gsTelTexteAttente(){
  const reste = gsTelReste(gsTel ? gsTel.prochain : 0);
  const enAttente = gsTel && gsTel.commandes ? gsTel.commandes.length : 0;
  if(reste > 0){
    return (enAttente ? 'Prochain appel' : 'Personne au bout du fil. Prochain appel')
         + ' possible dans ' + reste + ' s.';
  }
  return enAttente ? 'Le téléphone peut resonner à tout moment.'
                   : 'Personne au bout du fil — tu peux rappeler.';
}

/* Chaque seconde : on met à jour les chiffres qui descendent, et rien d'autre.
   Redessiner toute la page (dont la carte du quartier en SVG) une fois par
   seconde serait du gâchis, et ferait clignoter la sélection. */
function gsTelTicTac(){
  if(GS_PANEL !== 'telephone' || !gsTel) return;

  const attente = document.getElementById('gsTelAttente');
  if(attente) attente.textContent = gsTelTexteAttente();
  const btn = document.getElementById('gsTelAppeler');
  if(btn) btn.disabled = gsTelReste(gsTel.prochain) > 0 || gsTelEnCours;

  let perimee = false;
  document.querySelectorAll('#gsTelCommandes .c-min').forEach(el=>{
    const reste = gsTelReste(el.dataset.fin);
    el.textContent = gsTelDuree(reste);
    // Sous une minute, le compte à rebours passe en orange : c'est le moment de
    // choisir entre cette livraison et une autre.
    el.style.color = reste <= 60 ? 'var(--gold)' : '';
    if(reste <= 0) perimee = true;
  });

  // Une commande vient d'expirer, ou le délai du prochain appel est passé : on
  // redemande l'état au serveur (au plus une fois toutes les dix secondes).
  const doitRelire = perimee || gsTelReste(gsTel.prochain) <= 0;
  if(doitRelire && Date.now() - gsTelDernierAppel > 10000 && !gsTelEnCours){
    gsTelDernierAppel = Date.now();
    gsChargerTelephone();
  }
}

function gsTelCommandeCible(){
  if(!gsTel) return null;
  return (gsTel.commandes || []).find(c => c.id === gsTelCible) || null;
}

function renderTelephone(){
  if(!gsTel) return;
  const count = document.getElementById('gsTelCount');
  if(count) { const n = (gsTel.commandes || []).length; count.textContent = n + (n > 1 ? ' en attente' : ' en attente'); }
  const stock = document.getElementById('gsTelStock');
  if(stock) stock.textContent = fmtInt(gsTel.stock) + ' / ' + fmtInt(gsTel.stock_max);
  const note = document.getElementById('gsTelStockNote');
  if(note) note.textContent = gsTel.stock > 0
    ? 'De quoi honorer les commandes qui arrivent.'
    : "Vide : achète chez un dealer avant qu'un client appelle.";
  const bank = document.getElementById('gsTelBank');
  if(bank) bank.textContent = fmt(Number(gsTel.bank_eur) || 0);
  const prix = document.getElementById('gsTelPrix');
  if(prix) prix.textContent = 'Prix de référence : ' + fmt(gsTel.prix_unite) + ' l\'unité';
  const quota = document.getElementById('gsTelQuota');
  if(quota) quota.textContent = gsTel.livrees + ' / ' + gsTel.livrees_max + ' livraisons aujourd\'hui';

  gsTelDessinerCarte();
  gsTelRendreCommandes();
  gsTelRendreContacts();
}

function gsTelRendreCommandes(){
  const el = document.getElementById('gsTelCommandes');
  if(!el) return;
  el.innerHTML = '';
  const cmds = gsTel.commandes || [];

  // Le délai avant le prochain appel s'affiche TOUJOURS, même quand des commandes
  // attendent déjà : savoir dans combien de temps le téléphone resonne fait partie
  // de la décision (livrer maintenant, ou attendre d'en avoir deux à faire).
  const attente = document.createElement('div');
  attente.className = 'gs-board-empty';
  attente.id = 'gsTelAttente';
  attente.textContent = gsTelTexteAttente();
  el.appendChild(attente);

  // Un bouton pour rappeler tout de suite quand le délai est écoulé : le téléphone
  // sonne tout seul, mais rester devant un écran qui ne bouge pas en attendant,
  // ce n'est pas jouer.
  const btnAppel = document.createElement('button');
  btnAppel.className = 'buy-btn';
  btnAppel.id = 'gsTelAppeler';
  btnAppel.style.width = '100%';
  btnAppel.style.marginBottom = '10px';
  btnAppel.textContent = 'Passer un coup de fil';
  btnAppel.disabled = gsTelReste(gsTel.prochain) > 0 || gsTelEnCours;
  btnAppel.addEventListener('click', ()=> gsChargerTelephone());
  el.appendChild(btnAppel);

  if(!cmds.length) return;
  cmds.forEach(c=>{
    const ligne = document.createElement('div');
    ligne.className = 'gs-tel-cmd' + (gsTelCible === c.id ? ' is-cible' : '');
    ligne.tabIndex = 0;
    ligne.setAttribute('role', 'button');
    ligne.setAttribute('aria-pressed', String(gsTelCible === c.id));
    ligne.setAttribute('aria-label', 'Sélectionner la commande de ' + c.nom);
    ligne.addEventListener('keydown', e=>{ if(e.key === 'Enter' || e.key === ' '){ e.preventDefault(); gsTelCible = c.id; renderTelephone(); document.getElementById('gsTelCommandes').querySelector('.is-cible')?.focus(); } });
    ligne.appendChild(gsAvatarContact(c));

    const txt = document.createElement('div');
    txt.className = 'c-txt';
    const nom = document.createElement('div');
    nom.className = 'c-nom';
    nom.textContent = c.nom + ' — ' + fmtInt(c.qte) + ' unités';
    const det = document.createElement('div');
    det.className = 'c-det';
    det.textContent = c.adresse.numero + ' ' + c.adresse.nom + ' · ' + fmt(c.total);
    txt.append(nom, det);

    const min = document.createElement('span');
    min.className = 'c-min';
    min.dataset.fin = c.restant;          // relu chaque seconde par gsTelTicTac()
    min.textContent = gsTelDuree(gsTelReste(c.restant));

    ligne.append(txt, min);
    ligne.addEventListener('click', ()=>{ gsTelCible = c.id; renderTelephone(); });
    el.appendChild(ligne);

    if(gsTelCible === c.id){
      const btn = document.createElement('button');
      btn.className = 'buy-btn';
      btn.style.width = '100%';
      btn.style.marginBottom = '9px';
      const assez = gsTel.stock >= c.qte;
      btn.textContent = assez ? ('Livrer — ' + fmt(c.total)) : ('Il te faut ' + fmtInt(c.qte) + ' unités');
      btn.disabled = !assez || gsTelEnCours;
      btn.addEventListener('click', ()=> gsTelLivrer(c.id));
      el.appendChild(btn);
    }
  });
}

function gsTelRendreContacts(){
  const el = document.getElementById('gsTelContacts');
  if(!el) return;
  el.innerHTML = '';
  (gsTel.contacts || []).forEach(c=>{
    const card = document.createElement('div');
    card.className = 'item-card' + (c.debloque ? '' : ' gs-tel-verrou');

    const haut = document.createElement('div');
    haut.className = 'gs-tel-contact';
    haut.appendChild(gsAvatarContact(c));

    const corps = document.createElement('div');
    corps.className = 'item-body';
    const titre = document.createElement('h3');
    titre.textContent = c.nom + (c.surnom ? ' ' + c.surnom : '');
    const desc = document.createElement('p');
    desc.textContent = c.debloque ? c.desc : ('Se débloque au niveau de stand ' + c.niveau + '.');
    corps.append(titre, desc);

    if(c.debloque && c.type === 'dealer'){
      const prix = document.createElement('p');
      prix.className = 'item-gain';
      prix.textContent = 'Achat ' + fmt(c.prix_achat) + ' · il te rachète ' + fmt(c.prix_vente)
        + ' · lots de ' + fmtInt(c.lot_min) + ' à ' + fmtInt(c.lot_max);
      corps.appendChild(prix);

      const ligne = document.createElement('div');
      ligne.className = 'gs-tel-ligne';
      const champ = document.createElement('input');
      champ.setAttribute('aria-label', 'Quantité pour ' + c.nom);
      champ.type = 'number'; champ.min = 1; champ.max = c.lot_max; champ.value = c.lot_min;
      const acheter = document.createElement('button');
      acheter.className = 'buy-btn';
      acheter.textContent = 'Acheter';
      acheter.disabled = gsTelEnCours;
      acheter.addEventListener('click', ()=> gsTelDealer(c.id, 'achat', parseInt(champ.value, 10) || 0));
      const revendre = document.createElement('button');
      revendre.className = 'buy-btn';
      revendre.textContent = 'Revendre';
      revendre.disabled = gsTelEnCours || gsTel.stock <= 0;
      revendre.addEventListener('click', ()=> gsTelDealer(c.id, 'vente', parseInt(champ.value, 10) || 0));
      ligne.append(champ, acheter, revendre);
      corps.appendChild(ligne);
    } else if(c.debloque){
      const info = document.createElement('p');
      info.className = 'item-gain';
      info.textContent = 'Client — il appelle tout seul, tu livres sur la carte.';
      corps.appendChild(info);
    }

    haut.appendChild(corps);
    card.appendChild(haut);
    el.appendChild(card);
  });
}

async function gsTelDealer(id, sens, qte){
  if(gsTelEnCours) return;
  if(!(qte > 0)){ gsShowSyncNote('Indique une quantité.'); return; }
  gsTelEnCours = true; renderTelephone();
  try {
    const res = await gsCloudRequest('tel_dealer', {contact: id, sens: sens, qte: qte});
    gsTel = res.etat;
    gsBank = Number(gsTel.bank_eur) || gsBank;
    gsShowSyncNote(res.sens === 'achat'
      ? (fmtInt(res.qte) + ' unités achetées à ' + res.nom + ' — ' + fmt(res.montant))
      : (fmtInt(res.qte) + ' unités revendues à ' + res.nom + ' — +' + fmt(res.montant)));
  } catch(err){
    gsShowSyncNote(err.message || 'Opération refusée');
  } finally {
    gsTelEnCours = false; renderTelephone();
  }
}

/* Ce que dit le client en récupérant sa commande. Rien de décisif : c'est ce qui
   fait la différence entre « +28 000 € » et une livraison. */
const GS_TEL_MERCIS = [
  'Pile ce qu\'il fallait. À la prochaine.',
  'Rapide. Je garde ton numéro.',
  'Nickel, je te rappelle cette semaine.',
  'T\'as fait vite, merci.',
  'Comme d\'habitude, impeccable.',
  'Je te fais de la pub, compte sur moi.',
];

/* Le résultat d'une livraison, affiché en grand sur la page — comme le résultat
   d'une caisse à la boutique. La petite ligne collée au compteur de jetons, tout
   en haut, ne rendait pas justice à une tournée qu'on vient de faire. */
function gsAfficherLivraison(res){
  const el = document.getElementById('gsTelResultat');
  if(!el) return;
  const contact = (gsTel && (gsTel.contacts || []).find(c => c.nom === res.nom)) || {nom: res.nom, look: {}};

  const bloc = document.createElement('div');
  bloc.className = 'gs-tel-res';
  bloc.appendChild(gsAvatarContact(contact));

  const tx = document.createElement('div');
  tx.className = 'tx';
  const t = document.createElement('div');
  t.className = 't';
  t.textContent = 'Livré à ' + res.nom + ' — ' + fmtInt(res.qte) + ' unités';
  const sous = document.createElement('div');
  sous.className = 's';
  const adresse = res.adresse ? (res.adresse.numero + ' ' + res.adresse.nom + ' · ') : '';
  sous.textContent = adresse + '« ' + GS_TEL_MERCIS[Math.floor(Math.random() * GS_TEL_MERCIS.length)] + ' »';
  tx.append(t, sous);

  const m = document.createElement('div');
  m.className = 'm';
  m.textContent = '+' + fmt(res.gain);

  bloc.append(tx, m);
  el.innerHTML = '';
  el.appendChild(bloc);

  gsConsoleLigne('bien', 'Livraison : ' + fmtInt(res.qte) + ' unités à ' + res.nom
    + (adresse ? (' (' + adresse.replace(' · ', '') + ')') : '') + ' — +' + fmt(res.gain) + '.');
  playCash();
}

async function gsTelLivrer(id){
  if(gsTelEnCours) return;
  gsTelEnCours = true; renderTelephone();
  try {
    const res = await gsCloudRequest('tel_livrer', {commande: id});
    gsTel = res.etat;
    gsTelRecuA = Date.now();
    gsBank = Number(gsTel.bank_eur) || gsBank;
    gsTelCible = null;
    gsAfficherLivraison(res);
    gsMajPastilles({attente: (gsTel.commandes || []).length});
  } catch(err){
    gsShowSyncNote(err.message || 'Livraison refusée');
  } finally {
    gsTelEnCours = false; renderTelephone();
  }
}

/* ======================================================================
   LE DISTRIBUTEUR EN 3D
   ======================================================================
   Le vrai modèle du distributeur, à la place de la carte « En banque ».

   POURQUOI UN LECTEUR glTF ÉCRIT ICI. three.js est déjà chargé (c'est lui qui
   fait le stand), mais son GLTFLoader est un fichier à part, sur un autre CDN.
   Une dépendance de plus, c'est une page qui casse le jour où ce CDN tousse —
   et le format n'a rien de sorcier : un JSON qui décrit des morceaux d'un
   fichier binaire. Les cent lignes ci-dessous lisent ce dont CE modèle a besoin :
   positions, normales, coordonnées de texture, indices, matériaux PBR simples,
   et l'arbre des nœuds avec leurs matrices.

   Ce qu'elles ne lisent PAS, faute d'en avoir l'usage : les animations, les
   squelettes, les morph targets, la compression Draco. Un modèle qui en contient
   ne s'affichera pas — la page garde alors sa carte d'avant, sans rien casser.

   Les fichiers vont dans assets/greenstand/atm/ (voir gs_atm_modele côté PHP) :
   soit un scene.glb tout seul, soit scene.gltf + scene.bin + textures/.
   ====================================================================== */

/* Le nombre d'octets d'un type de composant glTF, et le tableau typé qui va avec. */
const GS_GLTF_TYPES = {
  5120: {t: Int8Array,    n: 1}, 5121: {t: Uint8Array,  n: 1},
  5122: {t: Int16Array,   n: 2}, 5123: {t: Uint16Array, n: 2},
  5125: {t: Uint32Array,  n: 4}, 5126: {t: Float32Array, n: 4},
};
const GS_GLTF_COMPOSANTES = {SCALAR: 1, VEC2: 2, VEC3: 3, VEC4: 4, MAT4: 16};

/* Lit un accesseur : le morceau de binaire qui contient une liste de positions,
   de normales, d'indices… en tenant compte de l'entrelacement (byteStride). */
function gsGltfAccesseur(gltf, buffers, index){
  const acc = gltf.accessors[index];
  const composantes = GS_GLTF_COMPOSANTES[acc.type];
  const type = GS_GLTF_TYPES[acc.componentType];
  if(!composantes || !type) throw new Error('accesseur glTF non géré');
  const sortie = new type.t(acc.count * composantes);

  if(acc.bufferView === undefined){ return sortie; }      // accesseur creux : des zéros
  const vue = gltf.bufferViews[acc.bufferView];
  const buffer = buffers[vue.buffer || 0];
  const debut = (vue.byteOffset || 0) + (acc.byteOffset || 0);
  const pas = vue.byteStride || composantes * type.n;

  if(pas === composantes * type.n){
    // Cas courant : les éléments se suivent, une seule copie suffit.
    sortie.set(new type.t(buffer, debut, acc.count * composantes));
  } else {
    // Entrelacé : on saute d'un élément à l'autre.
    for(let i = 0; i < acc.count; i++){
      const bout = new type.t(buffer, debut + i * pas, composantes);
      sortie.set(bout, i * composantes);
    }
  }
  return sortie;
}

/* Une couleur de repli par matériau, tirée de son nom. Tant que les textures ne
   sont pas déposées, le distributeur s'affiche en aplats — mais dans SES couleurs
   à lui : la carrosserie claire, le capot noir, l'écran bleu. Rien de vert : le
   vert du reste du jeu sur un distributeur bancaire, ça ne ressemblait à rien. */
function gsGltfCouleurDeSecours(nom){
  const n = String(nom || '').toLowerCase();
  if(n.indexOf('screen') >= 0) return 0x16324B;   // l'écran, bleu sombre
  if(n.indexOf('black') >= 0)  return 0x1C1E22;   // le capot et la façade
  if(n.indexOf('button') >= 0) return 0x565D66;   // le clavier
  if(n.indexOf('grey') >= 0)   return 0xA8AFB7;   // l'acier brossé
  return 0xC3C8CD;                                // la carrosserie
}

/* Les matériaux dont le nom parle de métal méritent d'en avoir l'air : sans
   texture, seuls la brillance et le grain distinguent l'acier du plastique. */
function gsGltfFiniDeSecours(nom){
  const n = String(nom || '').toLowerCase();
  if(n.indexOf('screen') >= 0) return {metalness: 0.10, roughness: 0.25};
  if(n.indexOf('grey') >= 0)   return {metalness: 0.65, roughness: 0.35};
  if(n.indexOf('button') >= 0) return {metalness: 0.35, roughness: 0.55};
  return {metalness: 0.30, roughness: 0.50};
}

/* Charge un .gltf (avec son .bin) ou un .glb, et rend un THREE.Group. */
async function gsChargerGltf(url, base){
  const reponse = await fetch(url, {cache: 'force-cache'});
  if(!reponse.ok) throw new Error('modèle introuvable (' + reponse.status + ')');

  let gltf, buffers = [];
  if(/\.glb(\?|$)/i.test(url)){
    // .glb : un en-tête, puis des morceaux (JSON, puis binaire).
    const donnees = await reponse.arrayBuffer();
    const vue = new DataView(donnees);
    if(vue.getUint32(0, true) !== 0x46546C67) throw new Error('ce .glb n\'en est pas un');
    let position = 12;
    let binaire = null;
    while(position < donnees.byteLength){
      const taille = vue.getUint32(position, true);
      const type = vue.getUint32(position + 4, true);
      const contenu = donnees.slice(position + 8, position + 8 + taille);
      if(type === 0x4E4F534A) gltf = JSON.parse(new TextDecoder().decode(contenu));
      if(type === 0x004E4942) binaire = contenu;
      position += 8 + taille + ((4 - (taille % 4)) % 4);
    }
    buffers = [binaire];
  } else {
    gltf = await reponse.json();
    buffers = await Promise.all((gltf.buffers || []).map(async b=>{
      if(!b.uri) throw new Error('buffer sans fichier');
      if(b.uri.startsWith('data:')){
        const brut = atob(b.uri.split(',')[1]);
        const octets = new Uint8Array(brut.length);
        for(let i = 0; i < brut.length; i++) octets[i] = brut.charCodeAt(i);
        return octets.buffer;
      }
      const r = await fetch(base + b.uri, {cache: 'force-cache'});
      if(!r.ok) throw new Error('binaire du modèle introuvable (' + r.status + ')');
      return r.arrayBuffer();
    }));
  }

  // Les textures. Attention au piège : TextureLoader.load rend TOUT DE SUITE un
  // objet, même si l'image n'arrive jamais — s'y fier, c'est poser une texture
  // vide sur le modèle et le voir tout noir. On ne pose donc l'image QUE dans le
  // rappel de succès. D'ici là, et pour toujours si le fichier manque, le
  // matériau garde la couleur de secours tirée de son nom.
  const loader = new THREE.TextureLoader();
  const dejaChargees = {};
  const texture = (index, srgb, quandPrete)=>{
    const t = gltf.textures && gltf.textures[index];
    const img = t && gltf.images && gltf.images[t.source];
    if(!img || !img.uri || img.uri.startsWith('data:')) return;
    // Deux matériaux peuvent viser la même image : on ne la télécharge qu'une fois.
    const cle = index + '|' + (srgb ? 's' : 'l');
    const connue = dejaChargees[cle];
    if(connue){
      if(connue.prete) quandPrete(connue.tex);
      else connue.attente.push(quandPrete);
      return;
    }
    const entree = {tex: null, prete: false, attente: [quandPrete]};
    dejaChargees[cle] = entree;
    const tex = loader.load(base + img.uri, ()=>{
      entree.prete = true;
      entree.attente.splice(0).forEach(f => f(tex));
    }, undefined, ()=>{ entree.attente.length = 0; });
    // Deux conventions glTF qu'on ne devine pas : les UV partent du haut, et les
    // couleurs de base sont en sRGB (les cartes de normales, non).
    tex.flipY = false;
    if(srgb && THREE.sRGBEncoding) tex.encoding = THREE.sRGBEncoding;
    tex.wrapS = tex.wrapT = THREE.RepeatWrapping;
    entree.tex = tex;
  };

  const materiaux = (gltf.materials || []).map(m=>{
    const pbr = m.pbrMetallicRoughness || {};
    const base = pbr.baseColorFactor;
    // Ce modèle-ci ne donne AUCUN facteur de couleur : tout est dans les images.
    // Tant qu'elles ne sont pas là, on habille le distributeur d'après le nom de
    // ses matériaux — et le fini annoncé par le .gltf (métal à 0 partout) ne veut
    // alors rien dire non plus, donc on prend celui qui va avec la couleur.
    const secours = !base;
    const fini = gsGltfFiniDeSecours(m.name);
    const mat = new THREE.MeshStandardMaterial({
      name: m.name || '',
      color: base ? new THREE.Color(base[0], base[1], base[2]) : gsGltfCouleurDeSecours(m.name),
      metalness: secours ? fini.metalness : (pbr.metallicFactor !== undefined ? pbr.metallicFactor : 0.15),
      roughness: secours ? fini.roughness : (pbr.roughnessFactor !== undefined ? pbr.roughnessFactor : 0.8),
      side: m.doubleSided ? THREE.DoubleSide : THREE.FrontSide,
    });
    if(m.emissiveFactor) mat.emissive = new THREE.Color(m.emissiveFactor[0], m.emissiveFactor[1], m.emissiveFactor[2]);
    // Un écran éteint ressemble à une plaque. Celui-là s'allume un peu.
    else if(secours && /screen/i.test(m.name || '')){
      mat.emissive = new THREE.Color(0x123A63);
      mat.emissiveIntensity = 0.55;
    }
    if(pbr.baseColorTexture) texture(pbr.baseColorTexture.index, true, tex=>{
      // L'image est là : elle porte la couleur, le matériau redevient neutre et
      // reprend le fini que le .gltf annonçait.
      mat.map = tex;
      mat.color.setHex(0xFFFFFF);
      mat.metalness = pbr.metallicFactor !== undefined ? pbr.metallicFactor : 0.15;
      mat.roughness = pbr.roughnessFactor !== undefined ? pbr.roughnessFactor : 0.8;
      if(!m.emissiveFactor && mat.emissive) mat.emissive.setHex(0x000000);
      mat.needsUpdate = true;
    });
    if(m.normalTexture) texture(m.normalTexture.index, false, tex=>{
      mat.normalMap = tex; mat.needsUpdate = true;
    });
    return mat;
  });
  const matDefaut = new THREE.MeshStandardMaterial({color: 0x8A9096, roughness: 0.8, metalness: 0.1});

  const geometrie = (primitive)=>{
    const geo = new THREE.BufferGeometry();
    const a = primitive.attributes || {};
    if(a.POSITION === undefined) return null;
    geo.setAttribute('position', new THREE.BufferAttribute(gsGltfAccesseur(gltf, buffers, a.POSITION), 3));
    if(a.NORMAL !== undefined)     geo.setAttribute('normal', new THREE.BufferAttribute(gsGltfAccesseur(gltf, buffers, a.NORMAL), 3));
    if(a.TEXCOORD_0 !== undefined) geo.setAttribute('uv', new THREE.BufferAttribute(gsGltfAccesseur(gltf, buffers, a.TEXCOORD_0), 2));
    if(primitive.indices !== undefined) geo.setIndex(new THREE.BufferAttribute(gsGltfAccesseur(gltf, buffers, primitive.indices), 1));
    if(a.NORMAL === undefined) geo.computeVertexNormals();
    return geo;
  };

  // L'arbre des nœuds. Chaque nœud porte soit une matrice complète, soit un
  // triplet position / rotation / échelle.
  const construire = (index)=>{
    const n = gltf.nodes[index];
    const objet = new THREE.Group();
    if(n.matrix){
      objet.matrixAutoUpdate = false;
      objet.matrix.fromArray(n.matrix);       // glTF et three.js rangent tous deux en colonnes
      objet.matrix.decompose(objet.position, objet.quaternion, objet.scale);
      objet.matrixAutoUpdate = true;
    } else {
      if(n.translation) objet.position.fromArray(n.translation);
      if(n.rotation)    objet.quaternion.fromArray(n.rotation);
      if(n.scale)       objet.scale.fromArray(n.scale);
    }
    if(n.mesh !== undefined){
      (gltf.meshes[n.mesh].primitives || []).forEach(prim=>{
        const geo = geometrie(prim);
        if(!geo) return;
        objet.add(new THREE.Mesh(geo, materiaux[prim.material] || matDefaut));
      });
    }
    (n.children || []).forEach(enfant => objet.add(construire(enfant)));
    return objet;
  };

  const racine = new THREE.Group();
  const scene = gltf.scenes[gltf.scene || 0];
  (scene.nodes || []).forEach(i => racine.add(construire(i)));
  return racine;
}

/* ======================================================================
   L'ÉCRAN DU DISTRIBUTEUR
   ======================================================================
   Le distributeur ne sert plus seulement à être regardé : on l'allume, on
   touche sa dalle, et on retire ses jetons dessus.

   Un point important : cet écran n'invente RIEN. Il appelle gsExchange(),
   exactement comme les cartes en dessous — donc c'est toujours le serveur
   qui décide de ce qui est crédité. Un écran qui distribuerait lui-même de
   l'argent serait effacé au premier dépôt (le serveur recalcule tout), et
   aurait surtout donné à un joueur l'impression d'avoir été volé.

   Les cartes restent d'ailleurs sous la scène : elles marchent au clavier,
   et sans WebGL. L'écran est le plaisir, pas le seul chemin.
   ====================================================================== */

const GS_ATM_L = 512;                 // largeur du dessin ; la hauteur suit l'écran du modèle
let   gsAtmEcran = null;              // {canvas, ctx, texture, h}
let   gsAtmZones = [];                // les touches dessinées : {x,y,w,h,action,actif}
let   gsAtmEtat  = {vue: 'veille', devise: null, message: null, recu: null};
let   gsAtmDernierSolde = -1;

/* La palette de l'écran : un bleu de terminal bancaire, lisible de loin. */
const GS_ATM_C = {
  fond:    '#06182B', fondHaut: '#0A2C4C', trait: '#1E5C8A',
  texte:   '#DCEAF6', pale: '#7FA8C6', or: '#F2C14E',
  touche:  '#0E3E63', toucheOff: '#0A2437', vert: '#5FD08A', rouge: '#E8737B',
};

function gsAtmTexte(ctx, txt, x, y, taille, couleur, aligne, gras){
  ctx.font = (gras ? '700 ' : '') + taille + "px 'JetBrains Mono', ui-monospace, monospace";
  ctx.fillStyle = couleur;
  ctx.textAlign = aligne || 'left';
  ctx.textBaseline = 'middle';
  ctx.fillText(txt, x, y);
}

/* Une touche de l'écran. Elle s'enregistre dans gsAtmZones : c'est cette
   liste, et elle seule, qui dit plus tard où le joueur a appuyé. */
function gsAtmTouche(ctx, x, y, l, h, titre, sous, action, actif){
  gsAtmZones.push({x, y, w: l, h, action, actif});
  ctx.fillStyle = actif ? GS_ATM_C.touche : GS_ATM_C.toucheOff;
  ctx.strokeStyle = actif ? GS_ATM_C.trait : '#12314A';
  ctx.lineWidth = 2;
  ctx.beginPath();
  if(ctx.roundRect) ctx.roundRect(x, y, l, h, 8); else ctx.rect(x, y, l, h);
  ctx.fill(); ctx.stroke();
  gsAtmTexte(ctx, titre, x + 14, y + (sous ? h * 0.36 : h / 2), 17,
             actif ? GS_ATM_C.texte : '#4A6478', 'left', true);
  if(sous) gsAtmTexte(ctx, sous, x + 14, y + h * 0.70, 13,
                      actif ? GS_ATM_C.pale : '#3C5468');
}

/* Combien de cette monnaie le joueur peut prendre d'un coup : les trois
   mêmes limites que la carte (la banque, le plafond par échange, la place
   sur le solde). Demander plus, c'est se faire refuser par le serveur. */
function gsAtmMax(code){
  const taux = gsRates[code] || 1;
  return Math.max(0, Math.min(Math.floor(gsBank / taux), GS_EXCHANGE_MAX, gsPlaceRestante(code)));
}

function gsAtmDessiner(){
  if(!gsAtmEcran) return;
  const {ctx, h} = gsAtmEcran, L = GS_ATM_L;
  gsAtmZones = [];

  ctx.fillStyle = GS_ATM_C.fond;
  ctx.fillRect(0, 0, L, h);

  if(gsAtmEtat.vue === 'veille'){
    // Écran de veille : sombre, et il dit comment le réveiller.
    gsAtmTexte(ctx, 'GREENSTAND', L / 2, h * 0.38, 34, '#2B6E9E', 'center', true);
    gsAtmTexte(ctx, 'B A N Q U E', L / 2, h * 0.50, 18, '#1F5378', 'center');
    gsAtmTexte(ctx, 'TOUCHEZ L’ÉCRAN', L / 2, h * 0.72, 16,
               (Math.floor(Date.now() / 700) % 2) ? '#4E93C4' : '#28607F', 'center', true);
    gsAtmZones.push({x: 0, y: 0, w: L, h: h, action: 'allumer', actif: true});
    gsAtmEcran.texture.needsUpdate = true;
    return;
  }

  // Le bandeau du haut : toujours le solde que le SERVEUR a confirmé.
  ctx.fillStyle = GS_ATM_C.fondHaut;
  ctx.fillRect(0, 0, L, 74);
  ctx.strokeStyle = GS_ATM_C.trait; ctx.lineWidth = 2;
  ctx.beginPath(); ctx.moveTo(0, 74); ctx.lineTo(L, 74); ctx.stroke();
  gsAtmTexte(ctx, 'EN BANQUE', 18, 24, 12, GS_ATM_C.pale);
  gsAtmTexte(ctx, fmt(gsBank), 18, 50, 26, GS_ATM_C.or, 'left', true);

  const marge = 18, largeurTouche = L - marge * 2;

  if(gsAtmEtat.vue === 'menu'){
    gsAtmTexte(ctx, 'QUE VOULEZ-VOUS RETIRER ?', marge, 98, 13, GS_ATM_C.pale);
    let y = 116;
    Object.keys(gsRates).forEach(code=>{
      const meta = GS_ATM_META[code] || {label: code, one: code};
      const dispo = gsAtmMax(code);
      gsAtmTouche(ctx, marge, y, largeurTouche, 58, meta.label,
                  dispo > 0 ? (fmt(gsRates[code]) + ' l’unité · jusqu’à ' + fmtInt(dispo))
                            : 'Pas assez en banque',
                  dispo > 0 ? ('devise:' + code) : null, dispo > 0);
      y += 68;
    });
    gsAtmTouche(ctx, marge, h - 62, largeurTouche, 44, 'ÉTEINDRE', null, 'eteindre', true);
  }

  else if(gsAtmEtat.vue === 'montant'){
    const code = gsAtmEtat.devise;
    const meta = GS_ATM_META[code] || {label: code};
    const dispo = gsAtmMax(code);
    gsAtmTexte(ctx, meta.label.toUpperCase() + ' — COMBIEN ?', marge, 98, 13, GS_ATM_C.pale);

    // Des montants ronds, comme un vrai distributeur, plus « le maximum ».
    const choix = [1, 10, 100, 1000].filter(n => n <= dispo);
    const l2 = (largeurTouche - 12) / 2;
    let y = 116;
    choix.forEach((n, i)=>{
      gsAtmTouche(ctx, marge + (i % 2) * (l2 + 12), y + Math.floor(i / 2) * 62, l2, 52,
                  fmtInt(n), fmt(n * (gsRates[code] || 1)), 'retirer:' + n, true);
    });
    y += Math.ceil(choix.length / 2) * 62;
    if(dispo > 0){
      gsAtmTouche(ctx, marge, y, largeurTouche, 52, 'MAXIMUM — ' + fmtInt(dispo),
                  fmt(dispo * (gsRates[code] || 1)), 'retirer:' + dispo, true);
    } else {
      gsAtmTexte(ctx, 'Fonds insuffisants.', marge, y + 26, 15, GS_ATM_C.rouge);
    }
    gsAtmTouche(ctx, marge, h - 62, largeurTouche, 44, 'RETOUR', null, 'menu', true);
  }

  else if(gsAtmEtat.vue === 'travail'){
    gsAtmTexte(ctx, 'TRAITEMENT…', L / 2, h * 0.5, 22, GS_ATM_C.texte, 'center', true);
    gsAtmTexte(ctx, 'Ne quittez pas.', L / 2, h * 0.5 + 32, 14, GS_ATM_C.pale, 'center');
  }

  else if(gsAtmEtat.vue === 'recu'){
    const r = gsAtmEtat.recu || {};
    gsAtmTexte(ctx, r.ok ? 'OPÉRATION ACCEPTÉE' : 'OPÉRATION REFUSÉE',
               marge, 104, 15, r.ok ? GS_ATM_C.vert : GS_ATM_C.rouge, 'left', true);
    // Le message vient du serveur quand il refuse : c'est LUI qui sait pourquoi.
    const mots = String(r.texte || '').split(' ');
    let ligne = '', y = 140;
    ctx.font = "17px 'JetBrains Mono', ui-monospace, monospace";
    mots.forEach(mot=>{
      if(ctx.measureText(ligne + ' ' + mot).width > largeurTouche && ligne){
        gsAtmTexte(ctx, ligne, marge, y, 17, GS_ATM_C.texte); y += 26; ligne = mot;
      } else ligne = ligne ? ligne + ' ' + mot : mot;
    });
    if(ligne) gsAtmTexte(ctx, ligne, marge, y, 17, GS_ATM_C.texte);
    gsAtmTouche(ctx, marge, h - 62, largeurTouche, 44, 'CONTINUER', null, 'menu', true);
  }

  gsAtmEcran.texture.needsUpdate = true;
}

/* Le joueur a appuyé quelque part sur la dalle. u,v sont les coordonnées
   rendues par le rayon, en 0..1 depuis le coin bas-gauche de l'écran. */
function gsAtmAppui(u, v){
  const x = u * GS_ATM_L, y = v * gsAtmEcran.h;   // v part du haut, comme le canevas
  const zone = gsAtmZones.find(z => z.actif && x >= z.x && x <= z.x + z.w && y >= z.y && y <= z.y + z.h);
  if(!zone || !zone.action) return false;

  if(zone.action === 'allumer'){ gsAtmEtat = {vue: 'menu'}; }
  else if(zone.action === 'eteindre'){ gsAtmEtat = {vue: 'veille'}; }
  else if(zone.action === 'menu'){ gsAtmEtat = {vue: 'menu'}; }
  else if(zone.action.startsWith('devise:')){
    gsAtmEtat = {vue: 'montant', devise: zone.action.slice(7)};
  }
  else if(zone.action.startsWith('retirer:')){
    const code = gsAtmEtat.devise, qty = parseInt(zone.action.slice(8), 10);
    gsAtmEtat = {vue: 'travail'};
    gsAtmDessiner();
    // gsExchange fait TOUT le travail : c'est le serveur qui crédite, et le
    // reste de la page (solde, cartes, portefeuille) se met à jour tout seul.
    gsExchange(code, qty).then(res=>{
      const meta = GS_ATM_META[code] || {many: code};
      gsAtmEtat = {vue: 'recu', recu: res && res.ok
        ? {ok: true,  texte: '+' + fmtInt(res.gained) + ' ' + meta.many + '. Nouveau solde : ' + fmt(gsBank) + '.'}
        : {ok: false, texte: (res && res.message) || 'Refusé.'}};
      gsAtmDessiner();
    });
    return true;
  }
  gsAtmDessiner();
  return true;
}

/* Les noms affichés sur l'écran, sans les entités HTML des cartes. */
const GS_ATM_META = {
  jetons: {label: 'Jetons DT', one: 'jeton',  many: 'jetons'},
  gemmes: {label: 'Gemmes',    one: 'gemme',  many: 'gemmes'},
  cles:   {label: 'Clés', one: 'clé', many: 'clés'},
};

/* Prépare la dalle : on reprojette les UV du maillage de l'écran sur ses deux
   grands axes, puis on y colle notre canevas. Reprojeter est indispensable —
   les UV d'origine pointent vers l'atlas de textures du modèle, y coller notre
   dessin l'aurait déchiré en morceaux. */
function gsAtmPreparerEcran(maillage){
  const geo = maillage.geometry, pos = geo.attributes.position;
  const monde = maillage.matrixWorld;
  const p = new THREE.Vector3();

  // Les extensions du maillage dans le repère du modèle : le plus petit axe
  // est l'épaisseur de la dalle, donc sa normale.
  const boite = new THREE.Box3().setFromObject(maillage);
  const dim = boite.getSize(new THREE.Vector3()).toArray();
  const axeFin = dim.indexOf(Math.min.apply(null, dim));
  const n = new THREE.Vector3(); n.setComponent(axeFin, 1);
  const versEcran = boite.getCenter(new THREE.Vector3());
  if(versEcran.getComponent(axeFin) < 0) n.negate();      // vers l'extérieur

  // v monte, u va vers la droite de celui qui regarde l'écran.
  const restants = [0, 1, 2].filter(i => i !== axeFin);
  const iv = Math.abs(new THREE.Vector3().setComponent(restants[0], 1).y) >
             Math.abs(new THREE.Vector3().setComponent(restants[1], 1).y) ? restants[0] : restants[1];
  const vAxe = new THREE.Vector3().setComponent(iv, 1);
  // u va vers la droite de celui qui regarde la dalle : droite = normale × haut,
  // vérifié sur le rendu (dans l'autre sens, l'écran s'affiche en miroir).
  const uAxe = new THREE.Vector3().crossVectors(vAxe, n);

  let uMin = Infinity, uMax = -Infinity, vMin = Infinity, vMax = -Infinity;
  for(let i = 0; i < pos.count; i++){
    p.fromBufferAttribute(pos, i).applyMatrix4(monde);
    const cu = p.dot(uAxe), cv = p.dot(vAxe);
    if(cu < uMin) uMin = cu; if(cu > uMax) uMax = cu;
    if(cv < vMin) vMin = cv; if(cv > vMax) vMax = cv;
  }
  const uv = new Float32Array(pos.count * 2);
  for(let i = 0; i < pos.count; i++){
    p.fromBufferAttribute(pos, i).applyMatrix4(monde);
    uv[i * 2]     = (p.dot(uAxe) - uMin) / Math.max(1e-6, uMax - uMin);
    // v part du HAUT, comme les pixels d'un canevas (la texture n'est pas
    // retournée, cf. flipY plus bas) : sinon l'écran s'affiche la tête en bas.
    uv[i * 2 + 1] = 1 - (p.dot(vAxe) - vMin) / Math.max(1e-6, vMax - vMin);
  }
  geo.setAttribute('uv', new THREE.BufferAttribute(uv, 2));

  // Le canevas garde les proportions de la vraie dalle : sinon le texte
  // s'étire, et un écran de banque étiré, ça se voit tout de suite.
  // (makeCanvas de la page rend une texture ; ici on veut le canevas lui-même,
  //  pour continuer à dessiner dedans à chaque changement d'écran.)
  const canvas = document.createElement('canvas');
  canvas.width  = GS_ATM_L;
  canvas.height = Math.round(GS_ATM_L * (vMax - vMin) / Math.max(1e-6, uMax - uMin));
  const texture = new THREE.CanvasTexture(canvas);
  texture.flipY = false;
  if(THREE.sRGBEncoding) texture.encoding = THREE.sRGBEncoding;
  gsAtmEcran = {canvas, ctx: canvas.getContext('2d'), texture, h: canvas.height};

  maillage.material = new THREE.MeshBasicMaterial({map: texture, toneMapped: false});
  gsAtmDessiner();

  // Où est la dalle, et quelle taille fait-elle : la caméra s'en sert pour
  // venir se placer devant, à la bonne distance.
  return {centre: boite.getCenter(new THREE.Vector3()), hauteur: vMax - vMin};
}

/* ---------------- La petite scène du distributeur ---------------- */
let gsAtmPret = false;

async function gsMonterAtm(){
  const cadre = document.getElementById('gsAtmScene');
  if(!cadre || gsAtmPret || typeof GS_ATM !== 'object' || !GS_ATM) return;
  if(typeof THREE === 'undefined'){ gsAtmEchec('rendu 3D indisponible'); return; }
  gsAtmPret = true;

  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({antialias: true, alpha: true});
  } catch(e){ gsAtmEchec('rendu 3D indisponible'); return; }

  const largeur = () => Math.max(160, cadre.clientWidth || 320);
  const hauteur = () => Math.max(200, cadre.clientHeight || 260);
  renderer.setSize(largeur(), hauteur());
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  if(THREE.sRGBEncoding) renderer.outputEncoding = THREE.sRGBEncoding;
  cadre.appendChild(renderer.domElement);

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(38, largeur() / hauteur(), 0.1, 100);

  // Lumière neutre, comme un hall de banque. La version d'avant avait un
  // contre-jour vert (celui du stand) : il repeignait tout le distributeur.
  scene.add(new THREE.HemisphereLight(0xEDF1F6, 0x2A2E33, 0.95));
  const cle  = new THREE.DirectionalLight(0xFFFFFF, 1.15); cle.position.set(2.5, 4, 3);
  const bord = new THREE.DirectionalLight(0xC9D6E4, 0.45);  bord.position.set(-3, 1.5, -2);
  const face = new THREE.DirectionalLight(0xFFFFFF, 0.35);  face.position.set(0, 1, 5);
  scene.add(cle, bord, face);

  const socle = new THREE.Mesh(
    new THREE.CircleGeometry(1.25, 48),
    new THREE.MeshBasicMaterial({map: buildShadowTexture(), transparent: true, opacity: 0.55, depthWrite: false})
  );
  socle.rotation.x = -Math.PI / 2;
  scene.add(socle);

  let modele = null;
  try {
    modele = await gsChargerGltf(GS_ATM.url, GS_ATM.base);
  } catch(err){
    console.warn('[GreenStand] Distributeur 3D :', err.message);
    gsAtmEchec(err.message);
    return;
  }

  // Le modèle arrive dans SES unités (celui-ci mesure des centaines) : on le
  // recentre et on le met à l'échelle du cadre, quelle que soit sa taille.
  const boite = new THREE.Box3().setFromObject(modele);
  const taille = boite.getSize(new THREE.Vector3());
  const centre = boite.getCenter(new THREE.Vector3());
  const echelle = 1.7 / Math.max(taille.x, taille.y, taille.z, 0.0001);
  modele.scale.setScalar(echelle);
  modele.position.set(-centre.x * echelle, -boite.min.y * echelle, -centre.z * echelle);

  const pivot = new THREE.Group();
  pivot.add(modele);
  scene.add(pivot);

  // De quel côté regarde-t-il ? L'écran le dit. On cherche donc les maillages
  // dont le matériau parle d'écran ou de clavier, et on tourne le modèle pour
  // mettre ce côté-là face à la caméra. Deviner l'axe aurait marché pour CE
  // modèle et faux pour le suivant : ici, le modèle répond lui-même.
  modele.updateMatrixWorld(true);   // sinon les boîtes ci-dessous lisent l'ancienne position
  let avant = null, poids = 0, dalle = null, dalleAire = 0;
  const boiteMaillage = new THREE.Box3(), pointMaillage = new THREE.Vector3();
  modele.traverse(o=>{
    if(!o.isMesh || !/screen|button/i.test(o.material && o.material.name || '')) return;
    boiteMaillage.setFromObject(o).getCenter(pointMaillage);
    if(!avant) avant = new THREE.Vector3();
    avant.add(pointMaillage);
    poids++;
    // La dalle, c'est le plus GRAND des écrans : ce modèle en a deux (le
    // bandeau du haut et l'afficheur), et c'est sur l'afficheur qu'on tape.
    if(/screen/i.test(o.material.name)){
      const d = boiteMaillage.getSize(new THREE.Vector3()).toArray().sort((a, b)=> b - a);
      if(d[0] * d[1] > dalleAire){ dalleAire = d[0] * d[1]; dalle = o; }
    }
  });
  const dalleInfo = dalle ? gsAtmPreparerEcran(dalle) : null;
  // Rotation autour de Y qui amène la direction trouvée sur +Z, là où est l'œil.
  const angleFace = (avant && poids)
    ? -Math.atan2(avant.x / poids, avant.z / poids)
    : 0;

  // Deux places pour la caméra : au large pour regarder la machine, et devant
  // l'écran pour s'en servir. On passe de l'une à l'autre en glissant, comme si
  // on s'avançait — un écran lisible seulement après un zoom brutal, ça ne
  // donne pas envie de s'en servir.
  const poseLoin  = {oeil: new THREE.Vector3(0, 1.15, 3.1), vise: new THREE.Vector3(0, 0.85, 0)};
  let   posePres  = poseLoin;
  if(dalleInfo){
    // La distance qui fait tenir la dalle dans le cadre, d'après l'angle de vue.
    const recul = (dalleInfo.hauteur * 1.35) / (2 * Math.tan(camera.fov * Math.PI / 360));
    const p = dalleInfo.centre.clone().applyAxisAngle(new THREE.Vector3(0, 1, 0), angleFace);
    posePres = {oeil: p.clone().add(new THREE.Vector3(0, 0.02, recul)), vise: p};
  }
  const oeil = poseLoin.oeil.clone(), vise = poseLoin.vise.clone();
  camera.position.copy(oeil);
  camera.lookAt(vise);

  const chargement = document.getElementById('gsAtmChargement');
  if(chargement) chargement.remove();

  // On tourne doucement, et on peut attraper le distributeur pour le regarder
  // sous un autre angle.
  let angle = angleFace, vitesse = 0.12, attrape = false, dernierX = 0, parcouru = 0;

  // Où le doigt a-t-il touché la dalle ? Le rayon le dit, et rend les
  // coordonnées de texture du point touché : exactement le repère dans lequel
  // l'écran a été dessiné. Pas de calcul d'angle à refaire à la main.
  const rayon = new THREE.Raycaster(), pointeur = new THREE.Vector2();
  const toucherEcran = (ev)=>{
    if(!dalle || !gsAtmEcran) return false;
    const r = cadre.getBoundingClientRect();
    pointeur.x = ((ev.clientX - r.left) / r.width) * 2 - 1;
    pointeur.y = -((ev.clientY - r.top) / r.height) * 2 + 1;
    rayon.setFromCamera(pointeur, camera);
    const touche = rayon.intersectObject(dalle, false)[0];
    if(!touche || !touche.uv) return false;
    return gsAtmAppui(touche.uv.x, touche.uv.y);
  };

  cadre.addEventListener('pointerdown', ev=>{
    attrape = true; parcouru = 0; dernierX = ev.clientX;
    cadre.setPointerCapture(ev.pointerId);
  });
  cadre.addEventListener('pointerup', ev=>{
    attrape = false;
    try{ cadre.releasePointerCapture(ev.pointerId); }catch(e){}
    // Tourner le distributeur ne doit pas appuyer sur ses touches : en dessous
    // de quelques pixels c'est un appui, au-delà c'était un geste.
    if(parcouru < 6) toucherEcran(ev);
  });
  cadre.addEventListener('pointermove', ev=>{
    if(!attrape) return;
    parcouru += Math.abs(ev.clientX - dernierX);
    if(gsAtmEtat.vue === 'veille') angle += (ev.clientX - dernierX) * 0.01;
    dernierX = ev.clientX;
  });

  let precedent = performance.now(), dernierEcran = 0;
  const boucle = ()=>{
    const maintenant = performance.now();
    const dt = Math.min(0.1, (maintenant - precedent) / 1000);
    precedent = maintenant;
    // Il tourne pour se montrer ; dès qu'on s'en sert, il se remet droit et
    // se tient tranquille, écran vers nous.
    const enService = gsAtmEtat.vue !== 'veille';
    if(!attrape && !enService) angle += vitesse * dt;
    if(enService){
      // Vers angleFace par le plus court chemin, sinon il ferait un tour complet.
      let ecart = (angleFace - angle) % (Math.PI * 2);
      if(ecart >  Math.PI) ecart -= Math.PI * 2;
      if(ecart < -Math.PI) ecart += Math.PI * 2;
      angle += ecart * Math.min(1, dt * 4);
    }
    const pose = enService ? posePres : poseLoin;
    const pas = 1 - Math.pow(0.006, dt);      // même douceur quel que soit le rythme
    oeil.lerp(pose.oeil, pas);
    vise.lerp(pose.vise, pas);
    camera.position.copy(oeil);
    camera.lookAt(vise);
    // L'écran suit la banque sans qu'on ait à le prévenir de partout, et il
    // redessine sa veille pour la faire clignoter.
    // (au plus 4 fois par seconde : redessiner un canevas à chaque image pour
    //  faire clignoter trois mots serait payé par tout le reste de la page)
    if(gsAtmEcran && maintenant - dernierEcran > 250
       && (gsBank !== gsAtmDernierSolde || gsAtmEtat.vue === 'veille')){
      dernierEcran = maintenant;
      gsAtmDernierSolde = gsBank;
      gsAtmDessiner();
    }
    pivot.rotation.y = angle;
    renderer.render(scene, camera);
    requestAnimationFrame(boucle);
  };
  boucle();

  const redimensionner = ()=>{
    renderer.setSize(largeur(), hauteur());
    camera.aspect = largeur() / hauteur();
    camera.updateProjectionMatrix();
  };
  window.addEventListener('resize', redimensionner);
}

/* Le modèle n'a pas pu être chargé : on le dit dans le cadre, et le reste de la
   page (le solde, le change) continue de fonctionner normalement. */
function gsAtmEchec(raison){
  const chargement = document.getElementById('gsAtmChargement');
  if(chargement) chargement.textContent = 'Distributeur 3D indisponible — ' + raison;
  // Inutile d'inviter à toucher un écran qui ne s'affichera pas : les cartes,
  // elles, sont toujours là.
  const aide = document.getElementById('gsAtmAide');
  if(aide) aide.textContent = 'Sers-toi des cartes plus bas pour changer ton argent.';
}

/* ======================================================================
   LES PASTILLES DU MENU
   ======================================================================
   Une bulle rouge avec un chiffre sur « Téléphone », visible depuis N'IMPORTE
   QUELLE page : sans elle, un client pouvait attendre — et repartir fâché —
   pendant qu'on regardait la boutique ou le classement.

   Le point d'appel est volontairement minuscule (tel_badge : un compte, pas le
   quartier entier), et c'est lui qui fait sonner le téléphone. Sinon les
   commandes ne naîtraient qu'en ouvrant la page, et il n'y aurait jamais rien à
   annoncer.
   ====================================================================== */
let gsPastilleTel = 0;
let gsPastillePremiere = true;

function gsMajPastilles(badge){
  const el = document.querySelector('.gs-nav-pastille[data-pastille="telephone"]');
  if(!el) return;
  const n = Math.max(0, Number(badge && badge.attente) || 0);
  el.textContent = n;
  el.hidden = n === 0;
  // Le dernier client a moins de deux minutes : la pastille bat.
  const urgence = Number(badge && badge.urgence);
  el.classList.toggle('urgent', n > 0 && urgence > 0 && urgence < 120);
  if(n > 0) el.title = n + ' client(s) au bout du fil';

  // Un client de plus qu'au dernier passage : un petit son, si le son est activé.
  // Jamais au premier affichage : arriver sur une page ne doit pas sonner, même
  // quand trois clients attendent déjà depuis tout à l'heure.
  if(n > gsPastilleTel && !gsPastillePremiere) gsSonnerieTelephone();
  gsPastilleTel = n;
  gsPastillePremiere = false;
}

/* Deux notes brèves, pas une sonnerie de téléphone : le jeu tourne souvent dans
   un onglet en fond, et on a coupé le son de tout le reste avec le même bouton. */
function gsSonnerieTelephone(){
  playTone(880, 0.09, 'sine', 0.07, 0);
  playTone(1170, 0.11, 'sine', 0.06, 0.11);
}

async function gsChargerPastilles(){
  if(!GS_LOGGED_IN) return;
  // Sur la page du téléphone, l'état complet fait déjà le travail : inutile de
  // demander deux fois la même chose au serveur.
  if(GS_PANEL === 'telephone') return;
  try {
    const res = await gsCloudRequest('tel_badge');
    gsMajPastilles(res.badge);
  } catch(err){ /* silencieux : une pastille absente n'empêche pas de jouer */ }
}

/* ======================================================================
   LA BOUTIQUE
   ======================================================================
   La deuxième sortie des euros du stand, après le Distributeur — et la seule qui
   les dépense DANS le jeu.

   Tout est décidé par le serveur : le catalogue, les prix (calculés sur la
   production réelle du joueur), les limites du jour et le tirage de la caisse.
   Cette partie ne fait qu'afficher et appliquer ce qu'on lui renvoie. C'est
   important : un prix calculé ici serait un prix négociable depuis la console.

   Les effets vendus sont ceux des événements — rythme des clients, patience,
   prix des améliorations — et pas un multiplicateur de gains. La raison est dans
   greenstand_boutique() côté serveur : un gain que le serveur ne peut pas
   recalculer serait refusé au dépôt, et le joueur le verrait disparaître.
   ====================================================================== */
let gsBoutique = null;
let gsBoutiqueEnCours = false;

async function gsChargerBoutique(){
  if(GS_PANEL !== 'boutique' || !GS_LOGGED_IN) return;
  try {
    const res = await gsCloudRequest('boutique');
    gsBoutique = res.etat;
    renderBoutique();
  } catch(err){
    const grid = document.getElementById('gsBoutiqueGrid');
    if(grid) grid.innerHTML = '<div class="gs-board-empty">' + (err.message || 'Boutique indisponible.') + '</div>';
  }
}

function renderBoutique(){
  const grid = document.getElementById('gsBoutiqueGrid');
  if(!grid || !gsBoutique) return;

  const bank = Number(gsBoutique.bank_eur) || 0;
  const bankEl = document.getElementById('gsBoutiqueBank');
  if(bankEl) bankEl.textContent = fmt(bank);
  const noteEl = document.getElementById('gsBoutiqueNote');
  if(noteEl){
    noteEl.textContent = bank > 0
      ? 'Les prix suivent ta production : plus ton stand tourne, plus la boutique est chère — et plus tu peux payer.'
      : 'Vends au stand, puis dépose au Distributeur : c\'est cet argent-là qui achète ici.';
  }

  grid.innerHTML = '';
  gsBoutique.articles.forEach(a=>{
    const abordable = bank >= a.prix && a.restant > 0;
    const card = document.createElement('div');
    card.className = 'item-card ' + (abordable ? 'affordable' : '');

    const corps = document.createElement('div');
    corps.className = 'item-body';

    const titre = document.createElement('h3');
    titre.textContent = a.emoji + ' ' + a.nom;
    const desc = document.createElement('p');
    desc.textContent = a.desc;

    const effet = document.createElement('p');
    effet.className = 'gs-bout-effet';
    effet.textContent = gsBoutiqueEffetTexte(a);

    const pied = document.createElement('div');
    pied.className = 'item-foot';
    const limite = document.createElement('span');
    limite.className = 'gs-bout-limite';
    limite.textContent = a.restant > 0
      ? (a.restant + ' / ' + a.limite + ' aujourd\'hui')
      : 'épuisé jusqu\'à demain';

    const btn = document.createElement('button');
    btn.className = 'buy-btn';
    btn.textContent = a.restant > 0 ? fmt(a.prix) : 'demain';
    btn.disabled = !abordable || gsBoutiqueEnCours;
    btn.addEventListener('click', ()=> gsAcheterBoutique(a.id));

    pied.append(limite, btn);
    corps.append(titre, desc, effet, pied);
    card.appendChild(corps);
    grid.appendChild(card);
  });

  gsRendreEffetsEnCours();
  gsRendreChancesCaisse();
}

/* Ce qui tourne en ce moment, avec le temps restant. La boutique et le stand sont
   deux pages : sans ce rappel, on achète un effet et on ne voit plus rien. */
function gsRendreEffetsEnCours(){
  let el = document.getElementById('gsBoutiqueEffets');
  const grid = document.getElementById('gsBoutiqueGrid');
  if(!grid) return;
  if(!el){
    el = document.createElement('div');
    el.id = 'gsBoutiqueEffets';
    grid.parentNode.insertBefore(el, grid);
  }
  const effets = (gsBoutique && gsBoutique.effets) || [];
  if(!effets.length){ el.innerHTML = ''; return; }

  const noms = {arrivees:'Affluence', patience:'Patience des clients', cout:'Remise sur les prix', ferme:'Stand fermé'};
  const bloc = document.createElement('div');
  bloc.className = 'gs-chances';
  const h = document.createElement('h4');
  h.textContent = '⏳ En cours au stand';
  bloc.appendChild(h);
  effets.forEach(e=>{
    const ligne = document.createElement('p');
    ligne.className = 'c-note';
    ligne.style.margin = '0 0 4px';
    ligne.textContent = (noms[e.nom] || e.nom) + ' ×' + e.mult + ' — encore '
      + Math.max(1, Math.round(e.restant / 60)) + ' min';
    bloc.appendChild(ligne);
  });
  const lien = document.createElement('a');
  lien.className = 'gs-nav-btn';
  lien.href = 'greenstand-idle-3d.php';
  lien.style.marginTop = '8px';
  lien.textContent = '→ Aller au stand';
  bloc.appendChild(lien);
  el.innerHTML = '';
  el.appendChild(bloc);
}

/* Ce que fait l'article, en une ligne : la durée d'un effet, ou le fait que la
   caisse est un pari. Le joueur doit savoir ce qu'il achète AVANT de cliquer. */
function gsBoutiqueEffetTexte(a){
  if(a.effet === 'caisse')    return 'Pari — gain tiré au sort par le serveur';
  if(a.effet === 'reouvrir')  return 'Rouvre le stand immédiatement';
  if(a.effet === 'quota_tel') return '+' + a.valeur + ' livraisons au téléphone aujourd\'hui';
  const minutes = Math.round(a.duree / 60);
  const duree = minutes + ' min';
  if(a.effet === 'arrivees') return '×' + a.mult + ' de clients pendant ' + duree;
  if(a.effet === 'patience') return 'patience ×' + a.mult + ' pendant ' + duree;
  if(a.effet === 'cout')     return '−' + Math.round((1 - a.mult) * 100) + '% sur les prix pendant ' + duree;
  return duree;
}

/* Les chances de la caisse, affichées en toutes lettres. Elles viennent du
   serveur — la même table que celle qui tire — et l'espérance est affichée avec :
   si l'article reprend en moyenne plus qu'il ne donne, le joueur doit le lire
   avant d'acheter, pas le découvrir après. */
function gsRendreChancesCaisse(){
  const el = document.getElementById('gsCaisseChances');
  if(!el || !gsBoutique || !Array.isArray(gsBoutique.caisse)) return;
  const esperance = Number(gsBoutique.esperance) || 1;
  const perte = Math.round((1 - esperance) * 100);

  const bloc = document.createElement('div');
  bloc.className = 'gs-chances';
  const h = document.createElement('h4');
  h.textContent = '🎁 Caisse du fournisseur — les chances exactes';
  const note = document.createElement('p');
  note.className = 'c-note';
  note.textContent = perte > 0
    ? ('C\'est un pari, pas un placement : sur beaucoup de caisses, tu récupères en moyenne '
       + Math.round(esperance * 100) + ' % de ta mise, soit ' + perte + ' % de moins que ce que tu paies. '
       + 'Le tirage se fait sur le serveur, avec la table ci-dessous et rien d\'autre.')
    : 'Le tirage se fait sur le serveur, avec la table ci-dessous et rien d\'autre.';

  const table = document.createElement('table');
  gsBoutique.caisse.forEach(l=>{
    const tr = document.createElement('tr');
    const pct = document.createElement('td');
    pct.className = 'c-pct'; pct.textContent = l.chance + ' %';
    const txt = document.createElement('td');
    txt.textContent = l.texte;
    const mult = document.createElement('td');
    mult.className = 'c-mult'; mult.textContent = '×' + l.mult;
    tr.append(pct, txt, mult);
    table.appendChild(tr);
  });

  bloc.append(h, note, table);
  el.innerHTML = '';
  el.appendChild(bloc);
}

/* Les dernières caisses ouvertes, gardées le temps de la visite : voir la série
   (deux vides, une bonne) rend le pari lisible, et rappelle que la moyenne est
   sous la mise. */
const gsCaisseHistorique = [];

function gsAfficherCaisse(res){
  const el = document.getElementById('gsCaisseResultat');
  if(!el) return;
  const gain = Number(res.caisse.gain) || 0;
  const prix = Number(res.prix) || 0;
  const ecart = gain - prix;
  const mult = Number(res.caisse.mult) || 0;
  const classe = mult >= 3 ? 'jackpot' : (ecart >= 0 ? 'gagne' : 'perd');

  gsCaisseHistorique.unshift({mult: mult, gagne: ecart >= 0});
  gsCaisseHistorique.splice(8);

  const bloc = document.createElement('div');
  bloc.className = 'gs-caisse-res ' + classe;

  const em = document.createElement('div');
  em.className = 'em';
  em.textContent = mult >= 3 ? '🏆' : (ecart >= 0 ? '📦' : '🕳️');

  const tx = document.createElement('div');
  tx.className = 'tx';
  const t = document.createElement('div');
  t.className = 't';
  t.textContent = res.caisse.texte + ' — ×' + mult;
  const sous = document.createElement('div');
  sous.className = 's';
  sous.textContent = 'Payé ' + fmt(prix) + ' · reçu ' + fmt(gain)
    + ' · ' + (ecart >= 0 ? 'tu gagnes ' : 'tu perds ') + fmt(Math.abs(ecart));
  tx.append(t, sous);

  const m = document.createElement('div');
  m.className = 'm';
  m.textContent = (ecart >= 0 ? '+' : '−') + fmt(Math.abs(ecart));

  bloc.append(em, tx, m);

  // La série des dernières caisses, sous le résultat.
  const hist = document.createElement('div');
  hist.className = 'gs-caisse-hist';
  const label = document.createElement('b');
  label.textContent = 'Tes dernières caisses :';
  hist.appendChild(label);
  gsCaisseHistorique.forEach(h=>{
    const pastille = document.createElement('span');
    pastille.className = h.gagne ? 'g' : 'p';
    pastille.textContent = '×' + h.mult;
    hist.appendChild(pastille);
  });

  el.innerHTML = '';
  el.append(bloc, hist);

  // Le journal du comptoir garde la trace, comme pour une vente.
  gsConsoleLigne(ecart >= 0 ? 'bien' : 'mal',
    'Caisse du fournisseur : ' + res.caisse.texte + ' — ' + fmt(gain)
    + ' pour ' + fmt(prix) + ' (' + (ecart >= 0 ? '+' : '−') + fmt(Math.abs(ecart)) + ').');
}

async function gsAcheterBoutique(id){
  if(gsBoutiqueEnCours) return;
  gsBoutiqueEnCours = true;
  renderBoutique();
  try {
    const res = await gsCloudRequest('boutique_buy', {article: id});
    gsBoutique = res.etat;
    gsBank = Number(res.bank_eur) || gsBank;

    if(res.caisse){
      // Le résultat d'une caisse mérite mieux qu'une ligne collée au compteur de
      // jetons, en haut de page, où elle se lisait « 969 006 000 DT Marchandise
      // correcte — 5,47 M € ». Il s'affiche en grand, au-dessus du rayon, avec ce
      // qu'on a payé, ce qu'on a reçu, et la différence — et il reste là.
      gsAfficherCaisse(res);
    } else if(res.reouvert){
      // Le pot-de-vin lève la fermeture : la page du stand la relira au retour,
      // mais si elle est ouverte à côté, autant qu'elle s'en aperçoive tout de suite.
      gsEffets.ferme.fin = 0;
      gsMajBandeauEffets();
      gsShowSyncNote('Rideau relevé — le stand rouvre.');
    } else if(res.quota){
      gsShowSyncNote('+' + res.quota + ' livraisons au téléphone aujourd\'hui');
    } else if(res.effet){
      // Le serveur a déjà enregistré l'effet : il attendra le joueur au stand, même
      // s'il ferme cette page. On le pose aussi ici, pour le cas où la boutique
      // serait ouverte à côté du stand dans un autre onglet.
      gsAppliquerEffetsAchetes(gsBoutique && gsBoutique.effets);
      gsShowSyncNote(res.nom + ' — en cours pour ' + Math.round(res.effet.secondes / 60) + ' min');
    }
    renderDistributeur();
  } catch(err){
    gsShowSyncNote(err.message || 'Achat refusé');
  } finally {
    gsBoutiqueEnCours = false;
    renderBoutique();
  }
}

/* ======================================================================
   LA FRANCHISE — le prestige
   ====================================================================== */
let gsGold = GS_GOLD0 || {gold:0, lifetime:0, platine:0, platine_life:0, franchises:0, next_cost:GS_GOLD_SEUIL, levels:{}};
let gsFranchising = false;

/* Le prix de la prochaine franchise vient du SERVEUR (next_cost) : il monte à chaque
   franchise ouverte, et le navigateur n'a pas à rejouer la formule. Tant que le serveur
   n'a pas répondu, on affiche le prix de la première. */
function gsFranchiseCost(){
  const c = Number(gsGold.next_cost);
  return Number.isFinite(c) && c > 0 ? c : GS_GOLD_SEUIL;
}

/* Ce que la revente rapporterait maintenant, en Feuilles de Platine. Même formule que
   le serveur (greenstand_platine_for) — il recalcule de son côté, sur SA copie de la
   sauvegarde, et c'est son chiffre qui fait foi ; celui-ci ne sert qu'à l'affichage. */
function gsPlatineFor(eur){
  const cost = gsFranchiseCost();
  return eur < cost ? 0 : Math.max(1, Math.floor(Math.sqrt(eur / cost)));
}
function gsBoostLevel(id){ return Number(gsGold.levels && gsGold.levels[id]) || 0; }
function gsBoostCost(b, lvl){ return Math.ceil(b.baseCost * Math.pow(b.costMult, lvl)); }

function renderFranchise(){
  const goldEl = document.getElementById('gsGold');
  const grid   = document.getElementById('gsBoosts');
  if(!goldEl || !grid) return;

  goldEl.textContent = fmtInt(gsGold.gold);
  const note = document.getElementById('gsGoldNote');
  if(note) note.textContent = 'Franchises ouvertes : ' + fmtInt(gsGold.franchises)
    + " · une Feuille d'Or par franchise, jamais dépensée";

  const platEl = document.getElementById('gsPlatine');
  if(platEl) platEl.textContent = fmtInt(gsGold.platine);
  const platNote = document.getElementById('gsPlatineNote');
  if(platNote) platNote.textContent = fmtInt(gsGold.platine_life) + ' gagnée(s) en tout';

  const cost = gsFranchiseCost();
  const gain = gsPlatineFor(state.money);
  const hint = document.getElementById('gsFrHint');
  const btn  = document.getElementById('gsFrBtn');
  if(hint){
    hint.textContent = gain < 1
      ? ('Ta franchise n°' + fmtInt(gsGold.franchises + 1) + ' coûte ' + fmt(cost)
         + ' en caisse. Tu en as ' + fmt(state.money) + '.')
      : ('Revendre maintenant te rapporte 1 Feuille d\'Or et ' + fmtInt(gain)
         + ' Feuille(s) de Platine. '
         + 'Ton argent et tes niveaux repartent à zéro ; tes jetons, gemmes et clés ne bougent pas. '
         + 'La franchise suivante coûtera ' + fmt(cost * GS_FRANCHISE_COST_MULT) + '.');
  }
  if(btn) btn.disabled = gsFranchising || gain < 1;

  if(!grid.children.length){
    GS_BOOSTS.forEach(b=>{
      const card = document.createElement('div');
      card.className = 'item-card';
      card.dataset.boost = b.id;
      card.innerHTML =
        '<div class="item-swatch"><span style="font-size:22px;">' + b.emoji + '</span></div>' +
        '<div class="item-body">' +
          '<h3>' + b.name + '</h3><p>' + b.desc + '</p>' +
          '<p class="item-gain" data-gain></p>' +
          '<div class="item-foot"><span class="item-lvl" data-lvl></span>' +
          '<button class="buy-btn" type="button">—</button></div>' +
        '</div>';
      card.querySelector('button').addEventListener('click', ()=> gsBuyBoost(b.id));
      grid.appendChild(card);
    });
  }

  GS_BOOSTS.forEach(b=>{
    const card = grid.querySelector('[data-boost="' + b.id + '"]');
    if(!card) return;
    const lvl   = gsBoostLevel(b.id);
    const maxed = lvl >= b.maxLevel;
    const cost  = gsBoostCost(b, lvl);
    const pct   = v => Math.round(v * 100) + '%';
    const sens  = ['offline', 'click', 'persec'].includes(b.effect) ? '+' : '−';
    card.querySelector('[data-gain]').innerHTML =
      '<span class="now">Actuel : ' + sens + pct(b.step * lvl) + '</span><br>' +
      '<span class="next">' + (maxed ? 'Niveau maximum atteint'
        : ('Prochain niveau : ' + sens + pct(b.step * (lvl + 1)))) + '</span>';
    card.querySelector('[data-lvl]').textContent = 'Niv. ' + lvl + '/' + b.maxLevel;
    const btn = card.querySelector('button');
    btn.textContent = maxed ? 'MAX' : (fmtInt(cost) + ' 💎');
    btn.disabled = maxed || gsGold.platine < cost || gsFranchising;
    card.classList.toggle('affordable', !maxed && gsGold.platine >= cost);
  });
}

async function gsFranchise(){
  if(gsFranchising) return;
  const gain = gsPlatineFor(state.money);
  if(gain < 1) return;
  if(!confirm("Ouvrir ta franchise n°" + fmtInt(gsGold.franchises + 1) + " ?\n\n"
    + "Tu gagnes 1 Feuille d'Or (le trophée du classement)\n"
    + 'et ' + fmtInt(gain) + " Feuille(s) de Platine (pour les bonus permanents).\n\n"
    + "Ton argent et tous tes niveaux (améliorations et gérants) repartent à zéro.\n"
    + "Tes jetons, gemmes et clés ne bougent pas.\n"
    + 'La franchise suivante coûtera ' + fmt(gsFranchiseCost() * GS_FRANCHISE_COST_MULT) + ".\n\nContinuer ?")) return;

  gsFranchising = true; renderFranchise();
  try {
    // C'est le SERVEUR qui décide : il calcule les feuilles sur SA copie de la
    // sauvegarde et renvoie l'état remis à zéro. On l'applique tel quel.
    // On joint la partie instantanément : les dernières ventes sont ainsi bien
    // prises en compte pour les feuilles d'or, même avant la sauvegarde différée.
    const res = await gsCloudRequest('franchise', {state: JSON.stringify(gameSaveData())});
    if(res.state) gsApplyServerState(res.state);
    if(res.gold)  gsGold = res.gold;
    resetStandVisuals();
    recomputeDerived(); renderAll();
    gsShowSyncNote("+1 Feuille d'Or 🍁 et +" + fmtInt(res.gain) + ' Feuille(s) de Platine 💎');
    // Cette franchise vient peut-être d'ouvrir un nouveau comptoir : ça ne doit
    // pas passer inaperçu au milieu des feuilles.
    const ouvert = GS_STANDS.find(st => Number(st.franchises) === gsFranchisesOuvertes());
    if(ouvert){
      gsShowSyncNote('Nouveau stand débloqué : ' + ouvert.name + ' ' + (ouvert.emoji || ''));
      gsConsoleLigne('bien', ouvert.name + ' débloqué — ' + ouvert.desc);
    }
    saveGame();
    gsRefreshBoardsIfOpen();
  } catch(err){ gsShowSyncNote(err.message || 'Revente refusée'); }
  finally { gsFranchising = false; renderFranchise(); }
}

async function gsBuyBoost(id){
  if(gsFranchising) return;
  gsFranchising = true; renderFranchise();
  try {
    const res = await gsCloudRequest('buy_boost', {boost: id});
    if(res.gold) gsGold = res.gold;
    recomputeDerived(); renderAll();
    gsShowSyncNote('Boost permanent amélioré');
  } catch(err){ gsShowSyncNote(err.message || 'Achat refusé'); }
  finally { gsFranchising = false; renderFranchise(); }
}

/** Recharge l'état de jeu depuis une sauvegarde renvoyée par le serveur. */
function gsApplyServerState(save){
  if(!save) return;
  ['money','totalEarned','manualSales','itemsBought','lifetimeManualSales',
   'lifetimeItemsBought','lifetimeTotalEarned','critCount','gsSyncedLifetime']
    .forEach(k => { if(save[k] !== undefined) state[k] = Number(save[k]) || 0; });
  // L'état vient du serveur : sa carte des comptoirs fait foi (la revente d'une
  // franchise remet les quatre à zéro, pas seulement celui qu'on tenait).
  gsStandLevels = gsCarteDepuisSave(save);
  gsPoserStand(save.stand || gsStandId, true, true);
  gsAchSynchroniser();
}

/* ======================================================================
   LES TROIS CLASSEMENTS
   ====================================================================== */
let gsBoardsLoaded = false;

// Si le panneau est visible, le résultat bouge immédiatement. Sinon on invalide son
// cache : la prochaine ouverture récupérera les valeurs fraîches.
function gsRefreshBoardsIfOpen(){
  const panel = document.querySelector('[data-panel="classement"]');
  if(panel && panel.classList.contains('is-active')) gsLoadBoards(true);
  else gsBoardsLoaded = false;
}

// Le classement se recharge tout seul, mais LENTEMENT : toutes les 30 secondes, pas à
// chaque dépôt (c'était toutes les 5 s, et la carte se reconstruisait entièrement à
// chaque fois — illisible). Et le rendu n'a lieu que si les chiffres ont bougé.
const GS_BOARDS_REFRESH_MS = 30000;
let gsBoardsSignature = '';

/* Les cinq classements, avec leur règle. La règle est écrite ici, à côté de ce qui
   l'applique : c'est la première chose qu'on demande en voyant un classement, et c'est
   la première qui devient fausse quand personne ne la range près du code. */
const GS_BOARDS = [
  { cle:'plantation', titre:'Maître de la Plantation', icone:'&#127793;',
    regle:'100 points par variété récoltée + 250 par hybride découvert + 1 par récolte (bonus plafonné à 100). Permanent, sans récompense monétaire. Même score : même rang. Dépenser tes points de serre ne change pas ce score.',
    valeur:r => fmtInt(r.board_value) + ' pts',
    sous:r => fmtInt(r.extra_varieties) + ' variétés · ' + fmtInt(r.extra_hybrids) + ' hybrides · ' + fmtInt(r.extra_harvests) + ' récoltes' },
  { cle:'semaine', titre:'Meilleurs vendeurs', icone:'&#127942;',
    regle:"Euros déposés au Distributeur depuis le 1er du mois. Le classement est remis à 0 le 1er de chaque mois, et les trois premiers touchent leurs lots à ce moment-là.",
    lots:true,
    valeur:r => fmt(Number(r.board_value) || 0) },
  { cle:'hall', titre:'Hall of Fame', icone:'&#128081;',
    regle:"Nombre de mois terminés en 1re place. Jamais remis à zéro.",
    valeur:r => fmtInt(r.board_value) + (Number(r.board_value) > 1 ? ' fois' : ' fois'),
    sous:r => r.extra_best_week_eur ? ('record : ' + fmt(Number(r.extra_best_week_eur))) : '' },
  { cle:'production', titre:'Roi du Stand', icone:'&#127807;',
    regle:"Le meilleur €/s jamais atteint. Recalculé par le serveur à chaque dépôt, d'après tes niveaux — pas d'après ce que ton navigateur annonce.",
    valeur:r => fmt(Number(r.board_value) || 0) + '/s' },
  { cle:'parrain', titre:'Parrain de la Weed', icone:'&#127809;',
    regle:"Une Feuille d'Or par franchise ouverte. C'est un trophée : elle ne se dépense jamais (les bonus permanents s'achètent en Feuilles de Platine), donc ce classement ne peut que monter.",
    valeur:r => fmtInt(r.board_value) + ' 🍁',
    sous:r => r.extra_franchises ? (fmtInt(r.extra_franchises) + ' franchise(s)') : '' },
  { cle:'investisseur', titre:'Investisseur', icone:'&#128176;',
    regle:"Jetons DT en poche, convertis au Distributeur. Dépenser en caisses fait baisser ce classement — c'est un classement de trésorerie, pas de production.",
    valeur:r => fmtInt(r.board_value) + ' DT',
    sous:r => r.extra_gemmes ? (fmtInt(r.extra_gemmes) + ' gemme(s)') : '' },
];

async function gsLoadBoards(force){
  if(gsBoardsLoaded && !force) return;
  gsBoardsLoaded = true;
  const root = document.getElementById('gsBoards');
  if(!root) return;
  root.innerHTML = '<div class="gs-board-empty">Chargement des classements…</div>';
  try {
    const res = await gsCloudRequest('boards');
    gsRendrePodium(res.podium);
    gsRenderBoards(res.boards || {});
  } catch(err){
    root.innerHTML = '<div class="gs-board-empty">Classements indisponibles pour le moment.</div>';
    gsBoardsLoaded = false;   // on réessaiera au prochain passage
  }
}

function gsRenderBoards(boards){
  const root = document.getElementById('gsBoards');
  if(!root) return;

  // Rien n'a changé depuis le dernier passage : on ne touche pas au DOM. Sans ça, la
  // page clignotait à chaque rafraîchissement même quand les valeurs étaient identiques.
  const signature = JSON.stringify(boards);
  if(signature === gsBoardsSignature && root.children.length) return;
  gsBoardsSignature = signature;

  root.innerHTML = '';

  GS_BOARDS.forEach(def=>{
    const board = boards[def.cle] || {};
    const rows  = board.rows || [];

    const card = document.createElement('div');
    card.className = 'gs-bcard';

    const head = document.createElement('div');
    head.className = 'gs-bcard-head';
    const titre = document.createElement('div');
    titre.className = 'gs-bcard-title';
    titre.innerHTML = def.icone + '<span></span>';
    titre.querySelector('span').textContent = def.titre;
    const regle = document.createElement('div');
    regle.className = 'gs-bcard-rule';
    regle.textContent = def.regle;
    head.append(titre, regle);

    // Ce que gagnent les trois premiers, annoncé AVANT la fin du mois :
    // un classement dont on ignore l'enjeu ne fait courir personne.
    if(def.lots && typeof GS_PODIUM_LOTS === 'object'){
      const lots = document.createElement('div');
      lots.className = 'gs-bcard-lots';
      const medailles = ['🥇','🥈','🥉'];
      lots.innerHTML = '<div class="l-tete">À gagner le 1er du mois</div>'
        + [1,2,3].map((rang, i)=>{
            const lot = GS_PODIUM_LOTS[rang] || GS_PODIUM_LOTS[String(rang)];
            if(!lot) return '';
            const bouts = [];
            if(Number(lot.eur) > 0)     bouts.push(fmt(Number(lot.eur)));
            if(Number(lot.platine) > 0) bouts.push(lot.platine + ' 💎');
            if(lot.hall)                bouts.push('1 Hall of Fame 🏆');
            return '<div class="l-ligne"><span>' + medailles[i] + '</span>'
                 + '<span>' + bouts.join(' + ') + '</span></div>';
          }).join('');
      head.appendChild(lots);
    }

    const liste = document.createElement('div');
    liste.className = 'gs-bcard-rows';
    if(!rows.length){
      const vide = document.createElement('div');
      vide.className = 'gs-board-empty';
      vide.textContent = 'Personne encore classé. À toi de jouer.';
      liste.appendChild(vide);
    } else {
      rows.forEach(r=>{
        const ligne = document.createElement('div');
        ligne.className = 'gs-brow' + (r.is_me ? ' is-me' : '');

        const rang = document.createElement('div');
        rang.className = 'gs-brow-rank';
        rang.textContent = r.rank;
        // Sur le classement doté, le podium se repère sans compter les lignes.
        if(def.lots && Number(r.rank) <= 3){
          ligne.classList.add('est-dote');
          rang.textContent = ['🥇','🥈','🥉'][Number(r.rank) - 1];
        }

        // textContent, jamais innerHTML : un pseudo est du texte écrit par un joueur.
        const nom = document.createElement('div');
        nom.className = 'gs-brow-name';
        nom.textContent = r.display_name || r.username || '—';
        const sous = def.sous ? def.sous(r) : '';
        if(sous){
          const petit = document.createElement('div');
          petit.className = 'gs-brow-sub';
          petit.textContent = sous;
          nom.appendChild(petit);
        }

        const val = document.createElement('div');
        val.className = 'gs-brow-val';
        val.textContent = def.valeur(r);

        ligne.append(rang, nom, val);
        liste.appendChild(ligne);
      });
    }

    card.append(head, liste);
    root.appendChild(card);
  });
}

/* ======================================================================
   PANEL ADMIN — envoyer des Feuilles (or ou platine)
   ====================================================================== */
// La liste montre les compteurs RÉELS lus en base : réserve, dépensé en boosts et
// total à vie (celui du classement). C'est ce qu'il faut regarder quand un joueur dit
// qu'il voit une feuille au classement sans pouvoir la dépenser.
async function gsLoadGoldPlayers(q){
  const liste = document.getElementById('gsGoldPlayerList');
  const dl    = document.getElementById('gsGoldPlayers');
  if(!liste || !dl) return;
  try {
    const res = await gsCloudRequest('admin_players', q ? {q: q} : {});
    gsRenderGoldPlayers(res.players || []);
    // Au premier affichage, on vise TON compte : c'est le cas le plus courant, et ça
    // évite d'envoyer les feuilles à un pseudo qui se ressemble.
    const champ = document.getElementById('gsGoldPlayer');
    if(champ && !champ.value && GS_USERNAME) champ.value = GS_USERNAME;
  } catch(err){
    liste.innerHTML = '<div class="gs-board-empty">Liste des joueurs indisponible.</div>';
  }
}

function gsRenderGoldPlayers(players){
  const liste = document.getElementById('gsGoldPlayerList');
  const dl    = document.getElementById('gsGoldPlayers');
  if(!liste || !dl) return;

  dl.innerHTML = '';
  players.forEach(p=>{
    const opt = document.createElement('option');
    opt.value = p.username;
    dl.appendChild(opt);
  });

  liste.innerHTML = '';
  if(!players.length){
    liste.innerHTML = '<div class="gs-board-empty">Aucun joueur trouvé.</div>';
    return;
  }
  players.forEach(p=>{
    const moi = p.username === GS_USERNAME;
    const ligne = document.createElement('div');
    // is-me surligne TON compte : c'est la question qu'on se pose en premier devant
    // cette liste, et se tromper de ligne, c'est créditer quelqu'un d'autre.
    ligne.className = 'gs-brow' + (moi ? ' is-me' : '');
    ligne.style.cursor = 'pointer';
    ligne.style.gridTemplateColumns = '1fr auto';

    // textContent partout : un pseudo est du texte écrit par un joueur.
    const nom = document.createElement('div');
    nom.className = 'gs-brow-name';
    nom.textContent = (p.display || p.username) + (moi ? ' (toi)' : '');
    const sous = document.createElement('div');
    sous.className = 'gs-brow-sub';
    sous.textContent = p.username
      + ' · or ' + fmtInt(p.gold)
      + ' · platine ' + fmtInt(p.platine) + ' (dépensé ' + fmtInt(p.spent)
      + ', gagné ' + fmtInt(p.platine_life) + ')'
      + ' · ' + fmtInt(p.franchises) + ' franchise(s)';
    nom.appendChild(sous);

    const val = document.createElement('div');
    val.className = 'gs-brow-val';
    val.textContent = fmtInt(p.gold) + ' 🍁 · ' + fmtInt(p.platine) + ' 💎';

    ligne.append(nom, val);
    ligne.addEventListener('click', ()=>{
      const champ = document.getElementById('gsGoldPlayer');
      if(champ) champ.value = p.username;
    });
    liste.appendChild(ligne);
  });
}

document.getElementById('gsGoldForm')?.addEventListener('submit', async (ev)=>{
  ev.preventDefault();
  const form = ev.currentTarget;
  const btn  = document.getElementById('gsGoldSubmit');
  const data = new FormData(form);
  const pseudo = String(data.get('username') || '').trim();
  const nb     = Number(data.get('amount'));
  const devise = String(data.get('devise') || 'platine');
  const nomDevise = devise === 'or' ? "Feuille(s) d'Or 🍁" : 'Feuille(s) de Platine 💎';
  if(!pseudo || !Number.isFinite(nb) || nb === 0) return;
  if(!confirm((nb > 0 ? 'Envoyer ' : 'Retirer ') + Math.abs(nb) + ' ' + nomDevise + ' '
     + (nb > 0 ? 'à ' : 'de ') + pseudo + ' ?')) return;

  if(btn) btn.disabled = true;
  try {
    const res = await gsCloudRequest('admin_gold_grant',
      {username: pseudo, amount: String(Math.trunc(nb)), devise: devise});
    gsRenderGoldPlayers(res.players || []);
    gsShowSyncNote(res.username + ' : ' + fmtInt(res.avant) + ' → ' + fmtInt(res.apres)
      + (devise === 'or' ? ' 🍁' : ' 💎'));
    // L'admin peut s'envoyer des feuilles : son propre onglet Franchise doit suivre.
    if(res.username === GS_USERNAME){
      try { const etat = await gsCloudRequest('state_load'); if(etat.gold) gsGold = etat.gold; renderFranchise(); }
      catch(e){ console.warn('[GreenStand] Rafraîchissement des feuilles :', e.message); }
    }
  } catch(err){ alert(err.message || 'Envoi refusé'); }
  finally { if(btn) btn.disabled = false; }
});

let gsGoldSearchTimer = null;
document.getElementById('gsGoldPlayer')?.addEventListener('input', (ev)=>{
  clearTimeout(gsGoldSearchTimer);
  const q = ev.target.value.trim();
  gsGoldSearchTimer = setTimeout(()=> gsLoadGoldPlayers(q), 350);
});

/* Le palmarès de la dernière clôture. Sans lui, un joueur voit son solde grimper
   le 1er du mois sans savoir pourquoi. */
const GS_PODIUM_RANG = ['&#129351;', '&#129352;', '&#129353;'];

function gsRendrePodium(podium){
  const el = document.getElementById('gsPodium');
  if(!el) return;
  const rangs = (podium && podium.rangs) || [];
  if(!rangs.length){ el.style.display = 'none'; return; }
  el.style.display = '';

  const lignes = rangs.map((r, i)=>{
    const lots = [];
    if(Number(r.eur) > 0)     lots.push('+' + fmt(r.eur));
    if(Number(r.platine) > 0) lots.push('+' + r.platine + ' &#128142;');
    if(r.hall)                lots.push('+1 Hall of Fame &#127942;');
    return '<div class="p-ligne">'
      + '<span class="p-rang">' + (GS_PODIUM_RANG[i] || (i + 1)) + '</span>'
      + '<span class="p-qui"></span>'
      + '<span class="p-lot">' + lots.join(' &middot; ') + '</span>'
      + '</div>';
  }).join('');

  el.innerHTML = '<div class="gs-podium">'
    + '<div class="p-tete">Palmarès du mois clos' + (podium.semaine ? ' le ' + podium.semaine : '') + '</div>'
    + lignes + '</div>';
  // Le pseudo passe par textContent : c'est du texte joueur, il n'a rien à faire
  // dans du HTML assemblé à la main.
  el.querySelectorAll('.p-qui').forEach((n, i)=>{ n.textContent = rangs[i].username; });
}

/* ======================================================================
   PANEL ADMIN — la navigation interne
   ======================================================================
   Le panel présentait tout d'un bloc : feuilles, formulaire de badge, liste des
   badges, puis la grille complète des visuels. Quatre onglets, une tâche par
   onglet, et celui qu'on utilise le plus est retenu d'une visite à l'autre.
   ====================================================================== */
function gsAdmOnglet(nom){
  const onglets = document.querySelectorAll('.gs-adm-tab');
  if(!onglets.length) return;
  // L'onglet « Images du jeu » a été remplacé par un onglet par stand : un panel
  // rouvert sur l'ancien nom atterrit sur celui du GreenStand, pas sur les badges.
  if(nom === 'images') nom = 'images_' + GS_STANDS[0].id;
  let connu = false;
  onglets.forEach(t => { if(t.dataset.adm === nom) connu = true; });
  if(!connu) nom = 'badges';

  onglets.forEach(t => t.classList.toggle('is-active', t.dataset.adm === nom));
  document.querySelectorAll('.gs-adm-page').forEach(p => { p.hidden = p.dataset.admPage !== nom; });
  try { localStorage.setItem('gs_admin_tab', nom); } catch(e){}

  // La liste de surveillance se lit à l'ouverture de son onglet, puis se
  // rafraîchit toute seule tant qu'on la regarde — et s'arrête dès qu'on part.
  if(nom === 'feuilles'){
    const bloc = document.getElementById('gsPodiumBloc');
    if(bloc) bloc.style.display = GS_ANTICHEAT_OWNER ? '' : 'none';
  }
  if(nom === 'surveillance'){
    gsAntiCheatRendre();
    if(!gsWatchCharge) gsLoadWatchlist();
    gsWatchAuto(true);
  } else {
    gsWatchAuto(false);
  }
}

document.getElementById('gsAdmNav')?.addEventListener('click', (ev)=>{
  const btn = ev.target.closest('.gs-adm-tab');
  if(btn) gsAdmOnglet(btn.dataset.adm);
});

// Les filtres des onglets d'images : chaque stand a les siens, on ne redessine
// que la grille concernée.
document.querySelectorAll('.gs-asset-search').forEach(el =>
  el.addEventListener('input', ()=> gsRenderAdminAssetsStand(el.dataset.stand)));
document.querySelectorAll('.gs-asset-vides').forEach(el =>
  el.addEventListener('change', ()=> gsRenderAdminAssetsStand(el.dataset.stand)));

/* L'onglet à rouvrir est restauré depuis le bloc DOMContentLoaded plus haut, PAS
   ici. gsAdmOnglet() lit gsWatchCharge, déclaré quelques lignes plus bas : appelée
   au milieu du script, elle levait une ReferenceError (zone morte temporelle) qui
   tuait tout le reste — y compris loadGame(). Le jeu s'ouvrait alors à 0,00 € avec
   un compte qui semblait déconnecté. Tout ce qui touche au panel démarre désormais
   au même endroit, après que le script entier a été évalué. */

/* ======================================================================
   PANEL ADMIN — SURVEILLANCE
   ======================================================================
   Le registre anti auto-clicker accumulait ses relevés sans que rien ne les
   montre : il fallait ouvrir les journaux PHP du serveur pour savoir qu'un
   compte avait été sanctionné, et rien ne permettait de lever une sanction
   prise à tort. Les deux se font ici.
   ====================================================================== */
function gsDuree(sec){
  sec = Math.max(0, Math.round(Number(sec) || 0));
  if(sec < 60)   return sec + ' s';
  if(sec < 3600) return Math.round(sec / 60) + ' min';
  if(sec < 86400) return Math.round(sec / 3600) + ' h';
  return Math.round(sec / 86400) + ' j';
}

let gsWatchCharge = false;
let gsWatchTimer  = null;

// Rafraîchissement automatique tant que l'onglet Surveillance est ouvert. Sans
// lui, la cadence affichée était celle du moment où on avait ouvert la page :
// inutilisable pour regarder quelqu'un jouer.
const GS_WATCH_REFRESH_MS = 3000;

async function gsLoadWatchlist(silencieux){
  const root = document.getElementById('gsWatchList');
  if(!root) return;
  gsWatchCharge = true;
  // Un « Chargement… » toutes les trois secondes ferait clignoter la liste.
  if(!silencieux) root.innerHTML = '<div class="gs-board-empty">Chargement…</div>';
  try {
    const res = await gsCloudRequest('admin_watch');
    gsRenderWatchlist(res.watchlist || []);
  } catch(err){
    if(!silencieux){
      root.innerHTML = '<div class="gs-board-empty">' + (err.message || 'Chargement impossible.') + '</div>';
      gsWatchCharge = false;
    }
  }
}

function gsWatchAuto(actif){
  clearInterval(gsWatchTimer);
  gsWatchTimer = null;
  if(!actif) return;
  gsWatchTimer = setInterval(()=>{
    // Onglet du navigateur en arrière-plan : inutile d'interroger le serveur.
    if(document.visibilityState === 'hidden') return;
    gsLoadWatchlist(true);
  }, GS_WATCH_REFRESH_MS);
}

function gsRenderWatchlist(liste){
  const root = document.getElementById('gsWatchList');
  if(!root) return;
  root.innerHTML = '';

  const compte = document.getElementById('gsWatchCompte');
  const punis   = liste.filter(j => j.blocked).length;
  const enLigne = liste.filter(j => j.online).length;
  if(compte){
    compte.textContent = liste.length
      ? (enLigne + ' en ligne · ' + liste.length + ' suivi(s) · ' + punis + ' sous sanction'
         + ' · rafraîchi toutes les ' + (GS_WATCH_REFRESH_MS / 1000) + ' s')
      : '';
  }
  if(!liste.length){
    root.innerHTML = '<div class="gs-board-empty">Personne en jeu, et aucune cadence anormale mesurée.</div>';
    return;
  }

  liste.forEach(j=>{
    const row = document.createElement('div');
    row.className = 'gs-adm-ligne'
      + ((j.blocked || (j.live && Number(j.rate) > j.human_cps)) ? ' alerte' : '');

    const qui = document.createElement('div');
    qui.className = 'qui'; qui.textContent = j.username;

    const quoi = document.createElement('div');
    quoi.className = 'quoi';
    // En direct, la cadence est celle des dernières secondes ; sinon c'est un
    // vieux relevé, et l'afficher comme une valeur courante induirait en erreur.
    const taux = Number(j.rate) || 0;
    const cadence = document.createElement('div');
    if(j.live){
      const chaud = taux > j.human_cps;
      cadence.innerHTML = '<span style="color:#7BD46A;">&#9679; en direct</span> · '
        + '<b style="font-family:\'JetBrains Mono\',monospace;font-size:14px;'
        + 'color:' + (chaud ? '#F0B6AE' : '#EDEAE0') + ';">' + taux.toFixed(1) + ' clics/s</b>'
        + ' <span style="opacity:.7;">(plafond ' + j.human_cps + '/s)</span>';
    } else {
      // Pas de chiffre. Une mesure vieille d'une heure affichée à côté de « hors
      // ligne » se lit comme une cadence actuelle : on voyait « hors ligne ·
      // 1.5 clics/s » et on se demandait qui cliquait. Le dernier relevé n'est
      // rappelé que pour un compte sous surveillance, là où il sert à enquêter.
      const passe = (j.strikes > 0 || j.blocked)
        ? ' · dernier relevé : <b>' + taux.toFixed(1) + ' clics/s</b>'
        : '';
      cadence.innerHTML = '<span style="opacity:.55;">&#9675; '
        + (j.online ? 'en pause &mdash; rien reçu depuis ' + gsDuree(j.age)
                    : 'hors ligne &mdash; vu il y a ' + gsDuree(j.age))
        + '</span>' + passe;
    }
    const hist = document.createElement('div');
    hist.textContent = j.strikes + ' avertissement(s)'
      + (j.flagged_age !== null && j.flagged_age !== undefined ? ' · dernier signalement il y a ' + gsDuree(j.flagged_age) : '');
    quoi.append(cadence, hist);

    const etat = document.createElement('span');
    etat.className = 'etat ' + (j.blocked ? 'rouge' : (j.strikes > 0 ? 'jaune' : ''));
    etat.textContent = j.blocked
      ? ('Clics coupés encore ' + gsDuree(j.seconds))
      : (j.strikes > 0 ? 'Sous surveillance' : 'Rien à signaler');

    row.append(qui, quoi, etat);
    if(j.strikes > 0 || j.blocked){
      const btn = document.createElement('button');
      btn.type = 'button'; btn.className = 'gs-adm-btn';
      btn.textContent = 'Lever la sanction';
      btn.addEventListener('click', ()=>{
        if(!confirm('Effacer les avertissements de « ' + j.username + ' » et lever la sanction en cours ?')) return;
        gsWatchClear(j.username, btn);
      });
      row.appendChild(btn);
    }
    root.appendChild(row);
  });
}

async function gsWatchClear(username, btn){
  if(btn) btn.disabled = true;
  try {
    const res = await gsCloudRequest('admin_watch_clear', {username: username});
    gsRenderWatchlist(res.watchlist || []);
    gsShowSyncNote('Sanction levée pour ' + username);
  } catch(err){
    gsShowSyncNote(err.message || 'Impossible de lever la sanction');
    if(btn) btn.disabled = false;
  }
}

/* ---- L'interrupteur général anti-triche ----
   Le bouton n'est visible que pour les comptes autorisés, mais ce n'est qu'un
   confort d'affichage : le serveur revérifie à chaque appel. Masquer un bouton
   n'a jamais protégé une action. */
let gsAntiCheatActif = GS_ANTICHEAT_ACTIF;

function gsAntiCheatRendre(){
  const bloc = document.getElementById('gsAntiCheatBloc');
  const btn  = document.getElementById('gsAntiCheatBtn');
  const note = document.getElementById('gsAntiCheatNote');
  if(!bloc || !btn) return;
  if(!GS_ANTICHEAT_OWNER){ bloc.style.display = 'none'; return; }
  bloc.style.display = '';
  bloc.classList.toggle('est-off', !gsAntiCheatActif);
  btn.classList.toggle('est-off', !gsAntiCheatActif);
  btn.textContent = gsAntiCheatActif ? 'Désactiver' : 'Réactiver';
  if(note){
    note.textContent = gsAntiCheatActif
      ? "Active pour tout le site : filtre du navigateur, mesure de cadence, menu d'avertissement et suspensions."
      : "COUPÉE pour TOUT LE MONDE. Plus aucun clic n'est filtré et aucune sanction n'est prise. "
        + "Le frein de requêtes du serveur, lui, reste en place.";
  }
}

document.getElementById('gsAntiCheatBtn')?.addEventListener('click', async (ev)=>{
  if(!ev.isTrusted) return;
  const veut = !gsAntiCheatActif;
  if(veut === false && !confirm(
      "Couper la protection anti auto-clicker pour TOUT LE SITE ?\n\n"
    + "Plus aucun clic ne sera filtré et aucune sanction ne sera prise, pour tous les joueurs, "
    + "jusqu'à ce que tu la réactives.")) return;
  const btn = ev.currentTarget;
  btn.disabled = true;
  try {
    const res = await gsCloudRequest('admin_anticheat', {actif: veut ? '1' : '0'});
    gsAntiCheatActif = !!res.actif;
    gsShowSyncNote('Anti auto-clicker ' + (gsAntiCheatActif ? 'réactivé' : 'désactivé')
                   + ' — les joueurs doivent recharger la page.');
  } catch(err){
    gsShowSyncNote(err.message || 'Changement refusé');
  } finally {
    btn.disabled = false;
    gsAntiCheatRendre();
  }
});

/* ---- Verser un podium ----
   Deux temps volontairement séparés : l'aperçu ne touche à rien, et le bouton de
   versement ne s'active qu'après lui. Payer trois joueurs est irréversible ;
   personne ne devrait pouvoir le faire d'un clic distrait. */
let gsPodiumVu = null;

function gsPodiumChamps(){
  const f = document.getElementById('gsPodiumForm');
  return {
    p1: f?.elements['p1']?.value.trim() || '',
    p2: f?.elements['p2']?.value.trim() || '',
    p3: f?.elements['p3']?.value.trim() || '',
    hall: document.getElementById('gsPodiumHall')?.checked ? '1' : '0',
  };
}

function gsPodiumRendre(res){
  const zone = document.getElementById('gsPodiumResultat');
  if(!zone) return;
  zone.innerHTML = '';
  (res.lignes || []).forEach(l=>{
    const row = document.createElement('div');
    row.className = 'gs-adm-ligne' + (l.ok ? '' : ' alerte');

    const rang = document.createElement('div');
    rang.className = 'qui';
    rang.textContent = l.rang + (l.rang === 1 ? 'er' : 'e');

    const txt = document.createElement('div');
    txt.className = 'quoi';
    if(!l.ok){
      txt.textContent = (l.demande || l.username || '?') + ' — ' + (l.error || 'refusé');
    } else {
      const lots = [];
      if(l.eur > 0)     lots.push('+' + fmt(l.eur));
      if(l.platine > 0) lots.push('+' + l.platine + ' 💎');
      if(l.hall)        lots.push('+1 Hall of Fame 🏆');
      const l1 = document.createElement('div');
      l1.innerHTML = '<b></b> — ' + lots.join(' · ');
      l1.querySelector('b').textContent = l.username;
      const l2 = document.createElement('div');
      l2.textContent = l.apres
        ? ('banque ' + fmt(l.avant.bank) + ' → ' + fmt(l.apres.bank)
           + ' · platine ' + l.avant.platine + ' → ' + l.apres.platine
           + ' · victoires ' + l.avant.wins + ' → ' + l.apres.wins)
        : ('actuellement : ' + fmt(l.avant.bank) + ' en banque, '
           + l.avant.platine + ' platine, ' + l.avant.wins + ' victoire(s)');
      txt.append(l1, l2);
    }

    const etat = document.createElement('span');
    etat.className = 'etat ' + (l.ok ? (l.apres ? '' : 'jaune') : 'rouge');
    etat.textContent = l.ok ? (l.apres ? 'versé' : 'à verser') : 'refusé';

    row.append(rang, txt, etat);
    zone.appendChild(row);
  });
}

document.getElementById('gsPodiumForm')?.addEventListener('submit', async (ev)=>{
  ev.preventDefault();
  const champs = gsPodiumChamps();
  const verser = document.getElementById('gsPodiumVerser');
  if(verser) verser.disabled = true;
  try {
    const res = await gsCloudRequest('admin_podium', Object.assign({ecrire: '0'}, champs));
    gsPodiumVu = champs;
    gsPodiumRendre(res);
    // On n'ouvre le versement que si TOUT est bon : un pseudo faux et on
    // paierait deux joueurs sur trois sans s'en rendre compte.
    if(verser) verser.disabled = (res.erreurs || 0) > 0;
    if(res.erreurs) gsShowSyncNote('Corrige les pseudos en rouge avant de verser.');
  } catch(err){ gsShowSyncNote(err.message || 'Aperçu impossible'); }
});

document.getElementById('gsPodiumVerser')?.addEventListener('click', async (ev)=>{
  if(!ev.isTrusted) return;
  const champs = gsPodiumChamps();
  // L'aperçu portait sur d'autres pseudos : on repart de zéro plutôt que de
  // verser à quelqu'un qui n'a jamais été affiché.
  if(!gsPodiumVu || JSON.stringify(gsPodiumVu) !== JSON.stringify(champs)){
    gsShowSyncNote('Les pseudos ont changé — refais un aperçu.');
    ev.currentTarget.disabled = true;
    return;
  }
  const noms = [champs.p1, champs.p2, champs.p3].filter(Boolean).join(', ');
  if(!confirm('Verser les lots du podium à : ' + noms + ' ?\n\nC\'est irréversible.')) return;

  ev.currentTarget.disabled = true;
  try {
    const res = await gsCloudRequest('admin_podium', Object.assign({ecrire: '1'}, champs));
    gsPodiumRendre(res);
    gsShowSyncNote(res.erreurs ? 'Versé, avec des erreurs — regarde le détail.' : 'Lots versés.');
    gsPodiumVu = null;
  } catch(err){ gsShowSyncNote(err.message || 'Versement refusé'); }
});

document.getElementById('gsWatchRefresh')?.addEventListener('click', ()=> gsLoadWatchlist());
// Retour sur l'onglet du navigateur : on remet la liste à jour tout de suite.
document.addEventListener('visibilitychange', ()=>{
  if(document.visibilityState === 'visible' && gsWatchTimer) gsLoadWatchlist(true);
});

/* ======================================================================
   PANEL ADMIN — HISTORIQUE DES SAUVEGARDES
   ====================================================================== */
let gsSaveJoueurCourant = '';

async function gsLoadSnapshots(username){
  const root = document.getElementById('gsSaveList');
  if(!root) return;
  username = (username || '').trim();
  if(!username){
    root.innerHTML = '<div class="gs-board-empty">Tape un pseudo pour voir ses clichés.</div>';
    return;
  }
  gsSaveJoueurCourant = username;
  root.innerHTML = '<div class="gs-board-empty">Chargement…</div>';
  try {
    const res = await gsCloudRequest('admin_saves', {username: username});
    gsRenderSnapshots(res.snapshots || [], res.username || username);
  } catch(err){
    root.innerHTML = '<div class="gs-board-empty">' + (err.message || 'Chargement impossible.') + '</div>';
  }
}

// Pourquoi ce cliché existe : dit en français, pas en nom de colonne.
const GS_SNAP_RAISON = {
  auto:                'Sauvegarde automatique',
  avant_reset:         "Juste avant une remise à zéro",
  avant_restauration:  'Mis de côté avant une restauration'
};

function gsRenderSnapshots(liste, username){
  const root = document.getElementById('gsSaveList');
  if(!root) return;
  root.innerHTML = '';

  if(!liste.length){
    root.innerHTML = '<div class="gs-board-empty">Aucun cliché pour « ' + username
      + ' ». Soit le pseudo est faux, soit ce joueur n\'a pas encore joué depuis la mise en place de l\'historique.</div>';
    return;
  }

  liste.forEach(sn=>{
    const row = document.createElement('div');
    row.className = 'gs-adm-ligne' + (sn.reason !== 'auto' ? ' alerte' : '');

    const qui = document.createElement('div');
    qui.className = 'qui'; qui.textContent = 'il y a ' + gsDuree(sn.age);

    // Le résumé sert à reconnaître le bon cliché SANS avoir à le restaurer.
    const quoi = document.createElement('div');
    quoi.className = 'quoi';
    if(sn.money === null || sn.money === undefined){
      quoi.textContent = 'Contenu illisible — restauration impossible.';
    } else {
      const l1 = document.createElement('div');
      l1.innerHTML = '<b>' + fmt(sn.money) + ' €</b> en caisse · <b>' + fmt(sn.per_sec) + ' €/s</b>'
        + ' · ' + fmtInt(sn.clicks) + ' ventes à la main';
      const l2 = document.createElement('div');
      l2.textContent = fmtInt(sn.levels) + ' niveaux cumulés · ' + fmt(sn.earned) + ' € gagnés en tout';
      quoi.append(l1, l2);
    }

    const etat = document.createElement('span');
    etat.className = 'etat' + (sn.reason !== 'auto' ? ' jaune' : '');
    etat.textContent = GS_SNAP_RAISON[sn.reason] || sn.reason;

    row.append(qui, quoi, etat);

    if(sn.money !== null && sn.money !== undefined){
      const btn = document.createElement('button');
      btn.type = 'button'; btn.className = 'gs-adm-btn';
      btn.textContent = 'Restaurer';
      btn.addEventListener('click', ()=>{
        if(!confirm('Rendre à « ' + username + ' » sa progression d\'il y a ' + gsDuree(sn.age) + ' ?\n\n'
          + 'Sa partie actuelle sera écrasée — elle part dans l\'historique, donc récupérable.\n'
          + "S'il a le jeu ouvert, dis-lui de recharger la page.")) return;
        gsRestoreSnapshot(username, sn.id, btn);
      });
      row.appendChild(btn);
    }
    root.appendChild(row);
  });
}

async function gsRestoreSnapshot(username, id, btn){
  if(btn) btn.disabled = true;
  try {
    const res = await gsCloudRequest('admin_save_restore', {username: username, id: String(id)});
    gsRenderSnapshots(res.snapshots || [], username);
    gsShowSyncNote('Progression restaurée pour ' + username);
  } catch(err){
    gsShowSyncNote(err.message || 'Restauration refusée');
    if(btn) btn.disabled = false;
  }
}

document.getElementById('gsSaveVoir')?.addEventListener('click', ()=>{
  gsLoadSnapshots(document.getElementById('gsSaveJoueur')?.value);
});
document.getElementById('gsSaveJoueur')?.addEventListener('keydown', (ev)=>{
  if(ev.key === 'Enter'){ ev.preventDefault(); gsLoadSnapshots(ev.target.value); }
});
// La même recherche que l'onglet Feuilles remplit la liste de suggestions.
let gsSaveSearchTimer = null;
document.getElementById('gsSaveJoueur')?.addEventListener('input', (ev)=>{
  clearTimeout(gsSaveSearchTimer);
  const q = ev.target.value.trim();
  gsSaveSearchTimer = setTimeout(()=> gsLoadGoldPlayers(q), 350);
});

/* ======================================================================
   PANEL ADMIN — les visuels du jeu
   ====================================================================== */
let gsAdminLoaded = false;
async function gsLoadAdminAssets(force){
  if(!GS_IS_ADMIN) return;
  if(gsAdminLoaded && !force) return;
  gsAdminLoaded = true;
  // Une grille par stand : le message de chargement (puis l'erreur, s'il y en a
  // une) va dans toutes, sinon l'onglet qu'on regarde reste désespérément vide.
  const grilles = GS_STANDS
    .map(st => document.getElementById('gsAdminAssets_' + st.id))
    .filter(Boolean);
  if(!grilles.length) return;
  grilles.forEach(g => { g.innerHTML = '<div class="gs-board-empty">Chargement…</div>'; });
  try {
    const res = await gsCloudRequest('admin_assets');
    gsSetAssets(res.assets || []);
  } catch(err){
    grilles.forEach(g => {
      g.innerHTML = '<div class="gs-board-empty">' + (err.message || 'Chargement impossible.') + '</div>';
    });
    gsAdminLoaded = false;
  }
}

/* La liste des visuels vit à UN seul endroit : la grille d'images et les
   vignettes des badges la lisent toutes les deux. Sans ça, changer l'image d'un
   badge ne rafraîchissait que la moitié de l'écran. */
let gsAssetsList = [];
const gsAssetMap = {};

function gsSetAssets(assets){
  if(Array.isArray(assets)) gsAssetsList = assets;
  Object.keys(gsAssetMap).forEach(k => delete gsAssetMap[k]);
  gsAssetsList.forEach(a => { gsAssetMap[a.name] = a; });
  gsRenderAdminAssets();
  gsRenderStandNoms();
  gsRenderBadgeAdmin();
}

/* Chaque stand a son onglet, donc sa grille : on dessine celle d'un stand à la
   fois. Un visuel sait à quel comptoir il appartient (champ `stand`, posé par
   gs_admin_asset_stand() côté serveur) — les badges, gérants et bonus de
   franchise, communs à tout le jeu, sont rattachés au GreenStand. */
function gsRenderAdminAssets(){
  GS_STANDS.forEach(st => gsRenderAdminAssetsStand(st.id));
}

function gsRenderAdminAssetsStand(standId){
  const root = document.getElementById('gsAdminAssets_' + standId);
  if(!root) return;
  root.innerHTML = '';

  const duStand = gsAssetsList.filter(a => (a.stand || 'green') === standId);

  // Filtre de l'onglet : chercher par nom, ou n'afficher que les manquantes.
  const champ = document.querySelector('.gs-asset-search[data-stand="' + standId + '"]');
  const case_ = document.querySelector('.gs-asset-vides[data-stand="' + standId + '"]');
  const q = (champ?.value || '').trim().toLowerCase();
  const seulementVides = !!case_?.checked;
  const assets = duStand.filter(a =>
    (!q || a.name.toLowerCase().includes(q) || (a.cat || '').toLowerCase().includes(q)) &&
    (!seulementVides || !a.exists)
  );

  const compte = document.getElementById('gsAssetCompte_' + standId);
  if(compte){
    const vides = duStand.filter(a => !a.exists).length;
    compte.textContent = assets.length + ' / ' + duStand.length + ' affichée(s) · ' + vides + ' sans image';
  }
  if(!assets.length){
    root.innerHTML = '<div class="gs-board-empty">Aucun visuel ne correspond.</div>';
    return;
  }

  // Rangé par catégorie : trente-sept vignettes à la suite, personne ne s'y retrouve.
  const ordre = ['Décor', 'Améliorations', 'Gérants', 'Franchise', 'Badges'];
  const parCat = {};
  assets.forEach(a => (parCat[a.cat || 'Autres'] = parCat[a.cat || 'Autres'] || []).push(a));

  ordre.concat(Object.keys(parCat).filter(c => !ordre.includes(c))).forEach(cat=>{
    const liste = parCat[cat];
    if(!liste || !liste.length) return;

    const titre = document.createElement('div');
    titre.className = 'gs-admin-cat';
    titre.textContent = cat + ' · ' + liste.length;
    root.appendChild(titre);

    const grille = document.createElement('div');
    grille.className = 'gs-admin-grid';
    liste.forEach(a=> grille.appendChild(gsAdminAssetCard(a)));
    root.appendChild(grille);
  });
}

function gsAdminAssetCard(a){
  const card = document.createElement('div');
  card.className = 'gs-admin-card';

  const thumb = document.createElement('div');
  thumb.className = 'gs-admin-thumb';
  if(a.exists){
    const img = document.createElement('img');
    img.src = a.url; img.alt = a.name; img.loading = 'lazy';
    thumb.appendChild(img);
  } else {
    const vide = document.createElement('span');
    vide.className = 'vide'; vide.textContent = 'aucune image';
    thumb.appendChild(vide);
  }

  const nom = document.createElement('div');
  nom.className = 'gs-admin-name'; nom.textContent = a.name;
  const meta = document.createElement('div');
  meta.className = 'gs-admin-meta';
  meta.textContent = a.exists ? (Math.round(a.size / 1024) + ' Ko') : '—';

  const actions = document.createElement('div');
  actions.className = 'gs-admin-actions';
  const label = document.createElement('label');
  label.textContent = a.exists ? 'Remplacer' : 'Ajouter';
  const input = document.createElement('input');
  input.type = 'file'; input.accept = 'image/webp,image/png,image/jpeg,image/gif';
  input.addEventListener('change', ()=>{
    if(input.files && input.files[0]) gsUploadAsset(a.name, input.files[0]);
    input.value = '';
  });
  label.appendChild(input);
  actions.appendChild(label);
  if(a.exists){
    const del = document.createElement('button');
    del.type = 'button'; del.textContent = 'Supprimer';
    del.addEventListener('click', ()=>{
      if(confirm('Supprimer le visuel « ' + a.name + ' » ?')) gsDeleteAsset(a.name);
    });
    actions.appendChild(del);
  }

  card.append(thumb, nom, meta, actions);
  return card;
}

/* ======================================================================
   PANEL ADMIN — les noms d'améliorations, stand par stand
   ======================================================================
   Un stand tout neuf affiche les noms du GreenStand tant que personne n'a écrit
   les siens : « BazeKush » au WhiteStand. Le code en propose déjà (Brune
   Maison, Galet Maison, Blanche Maison…), mais c'est du texte, et du texte ça
   se corrige depuis le panel, pas en rouvrant un fichier PHP.

   Un champ laissé vide = on garde le nom d'origine. Le placeholder montre
   justement ce nom d'origine, pour qu'on voie ce qu'on remplace.
   ====================================================================== */
function gsRenderStandNoms(){
  GS_STANDS.forEach(stand=>{
    const grille = document.getElementById('gsNoms_' + stand.id);
    if(!grille) return;
    grille.innerHTML = '';
    const objets = stand.objets || {};
    ITEMS.forEach(item=>{
      const perso = objets[item.id] || {};
      const ligne = document.createElement('div');
      ligne.className = 'gs-nom-ligne';
      ligne.dataset.item = item.id;

      const cle = document.createElement('span');
      cle.className = 'cle';
      cle.textContent = item.id;

      const nom = document.createElement('input');
      nom.className = 'nom'; nom.type = 'text'; nom.maxLength = 40;
      nom.placeholder = item.name;
      nom.value = perso.nom || '';

      const desc = document.createElement('input');
      desc.className = 'desc'; desc.type = 'text'; desc.maxLength = 200;
      desc.placeholder = item.desc;
      desc.value = perso.desc || '';

      ligne.append(cle, nom, desc);
      grille.appendChild(ligne);
    });
  });
}

async function gsEnregistrerStandNoms(standId){
  const grille = document.getElementById('gsNoms_' + standId);
  const etat = document.getElementById('gsNomsEtat_' + standId);
  if(!grille) return;
  const objets = {};
  grille.querySelectorAll('.gs-nom-ligne').forEach(ligne=>{
    const nom  = ligne.querySelector('.nom').value.trim();
    const desc = ligne.querySelector('.desc').value.trim();
    // Les deux champs vides : rien à enregistrer, l'amélioration garde son nom.
    if(nom || desc) objets[ligne.dataset.item] = {nom: nom, desc: desc};
  });
  if(etat) etat.textContent = 'Enregistrement…';
  try {
    const res = await gsCloudRequest('admin_stand_noms', {stand: standId, objets: JSON.stringify(objets)});
    // Le serveur renvoie les stands relus : on les applique tout de suite, la
    // boutique et la scène prennent les nouveaux noms sans recharger la page.
    if(Array.isArray(res.stands)){
      GS_STANDS.length = 0;
      res.stands.forEach(st => GS_STANDS.push(st));
      gsRenderStandNoms();
      renderAll();
    }
    if(etat) etat.textContent = 'Enregistré ✓';
  } catch(err){
    if(etat) etat.textContent = err.message || 'Enregistrement impossible.';
  }
}

document.querySelectorAll('.gs-noms-save').forEach(btn=>
  btn.addEventListener('click', ()=> gsEnregistrerStandNoms(btn.dataset.stand)));

/* ---------------- Les badges, côté panel admin ---------------- */
// Le panel admin ne s'occupe QUE des badges écrits à la main : les mille paliers
// générés n'ont ni image à poser ni champ à modifier (ils viennent d'une formule).
let gsBadges = (GS_SUCCES.mains || []).slice();

function gsRenderBadgeAdmin(){
  const sel = document.getElementById('gsBadgeStat');
  if(sel && !sel.children.length){
    Object.entries(GS_BADGE_STATS).forEach(([cle, libelle])=>{
      const o = document.createElement('option');
      o.value = cle; o.textContent = libelle;
      sel.appendChild(o);
    });
  }
  const liste = document.getElementById('gsBadgeList');
  if(!liste) return;
  liste.innerHTML = '';
  gsBadges.forEach(b=>{
    const row = document.createElement('div');
    row.className = 'gs-badge-row';

    // La vignette EST le bouton d'envoi d'image : un clic ouvre le sélecteur de
    // fichier, et l'image part aussitôt sous le nom badge_<identifiant>. C'était
    // le geste qu'on ne pouvait faire qu'en cherchant la bonne case dans la
    // grille des trente-sept visuels.
    const visuel = gsAssetMap['badge_' + b.id];
    const vign = document.createElement('label');
    vign.className = 'b-img' + (visuel && visuel.exists ? ' has-img' : '');
    vign.title = (visuel && visuel.exists ? "Remplacer l'image de « " : "Ajouter une image à « ") + b.name + ' »';
    if(visuel && visuel.exists){
      const img = document.createElement('img');
      img.src = visuel.url; img.alt = ''; img.loading = 'lazy';
      vign.appendChild(img);
    } else {
      vign.appendChild(document.createTextNode(b.emoji || '🏅'));
    }
    const fichier = document.createElement('input');
    fichier.type = 'file';
    fichier.accept = 'image/webp,image/png,image/jpeg,image/gif';
    fichier.addEventListener('change', ()=>{
      if(fichier.files && fichier.files[0]) gsUploadAsset('badge_' + b.id, fichier.files[0]);
      fichier.value = '';
    });
    vign.appendChild(fichier);

    const txt = document.createElement('div');
    txt.className = 'b-txt';
    const nom = document.createElement('div');
    nom.className = 'b-nom'; nom.textContent = b.name;
    const cond = document.createElement('div');
    cond.className = 'b-cond';
    cond.textContent = (GS_BADGE_STATS[b.stat] || b.stat) + ' ≥ ' + fmtInt(b.value);
    txt.append(nom, cond);

    const edit = document.createElement('button');
    edit.type = 'button'; edit.className = 'b-edit'; edit.textContent = 'Modifier';
    edit.addEventListener('click', ()=> gsBadgeFill(b));

    const del = document.createElement('button');
    del.type = 'button'; del.className = 'b-del'; del.textContent = 'Suppr.';
    del.addEventListener('click', ()=>{
      if(confirm('Supprimer le badge « ' + b.name + ' » ?')) gsBadgeDelete(b.id);
    });

    // Les boutons vivent dans leur propre bande : sinon « Modifier » restait seul
    // sur la première ligne et le reste passait dessous, au hasard de la largeur.
    const act = document.createElement('div');
    act.className = 'b-act';
    act.appendChild(edit);
    row.append(vign, txt, act);
    // Retirer l'image sans quitter la liste : le jeu revient au pictogramme.
    if(visuel && visuel.exists){
      const delImg = document.createElement('button');
      delImg.type = 'button'; delImg.className = 'b-del'; delImg.textContent = 'Image ×';
      delImg.title = "Retirer l'image de ce badge";
      delImg.addEventListener('click', ()=>{
        if(confirm("Retirer l'image du badge « " + b.name + " » ? Le pictogramme reprendra sa place."))
          gsDeleteAsset('badge_' + b.id);
      });
      act.appendChild(delImg);
    }
    act.appendChild(del);
    liste.appendChild(row);
  });
}

function gsBadgeFill(b){
  const f = document.getElementById('gsBadgeForm');
  if(!f) return;
  ['id','name','desc','emoji','stat','value','cash','mult'].forEach(k=>{
    if(f.elements[k]) f.elements[k].value = b[k] !== undefined ? b[k] : '';
  });
  gsBadgeFormTitre(b);
  f.scrollIntoView({behavior:'smooth', block:'center'});
}

/* Le formulaire sert à la fois à créer et à modifier : il doit dire lequel des
   deux, sinon on croit créer un badge alors qu'on en écrase un autre. */
function gsBadgeFormTitre(b){
  const t = document.getElementById('gsBadgeFormTitre');
  if(t) t.textContent = b ? ('Modification de « ' + b.name + ' »') : 'Nouveau badge';

  const ap = document.getElementById('gsBadgeApercu');
  if(!ap) return;
  ap.innerHTML = '';
  const visuel = b ? gsAssetMap['badge_' + b.id] : null;
  if(visuel && visuel.exists){
    const img = document.createElement('img');
    img.src = visuel.url; img.alt = '';
    ap.appendChild(img);
  } else {
    ap.textContent = (b && b.emoji) || '🏆';
  }
}

document.getElementById('gsBadgeNouveau')?.addEventListener('click', ()=>{
  const f = document.getElementById('gsBadgeForm');
  if(f) f.reset();
  const img = document.getElementById('gsBadgeImage');
  if(img) img.value = '';
  gsBadgeFormTitre(null);
});

async function gsBadgeSave(ev){
  ev.preventDefault();
  const f = ev.target;
  const fd = new FormData(f);
  fd.append('action', 'admin_badge_save');
  fd.append('csrf', GS_CSRF);
  try {
    const rep = await fetch('greenstand_action.php', {method:'POST', body:fd, credentials:'same-origin', cache:'no-store'});
    const res = await rep.json();
    if(!rep.ok || !res.ok) throw new Error(res.error || 'Enregistrement refusé');
    gsBadges = res.badges || gsBadges;
    if(res.assets) gsSetAssets(res.assets); else gsRenderBadgeAdmin();

    // L'image choisie dans le formulaire part maintenant : elle ne pouvait pas
    // partir avant, le nom « badge_<id> » n'existant qu'une fois le badge créé.
    const champImage = document.getElementById('gsBadgeImage');
    const fichier = champImage && champImage.files && champImage.files[0];
    if(fichier){
      await gsUploadAsset('badge_' + String(fd.get('id') || '').toLowerCase().replace(/[^a-z0-9_]/g, ''), fichier);
      champImage.value = '';
    }

    gsShowSyncNote(res.created ? 'Badge créé' : 'Badge modifié');
    f.reset();
    gsBadgeFormTitre(null);
  } catch(err){ gsShowSyncNote(err.message || 'Enregistrement refusé'); }
}

async function gsBadgeDelete(id){
  try {
    const res = await gsCloudRequest('admin_badge_delete', {id: id});
    gsBadges = res.badges || gsBadges;
    if(res.assets) gsSetAssets(res.assets); else gsRenderBadgeAdmin();
    gsShowSyncNote('Badge supprimé');
    gsBadgeFormTitre(null);
  } catch(err){ gsShowSyncNote(err.message || 'Suppression refusée'); }
}

const gsBadgeForm = document.getElementById('gsBadgeForm');
if(gsBadgeForm) gsBadgeForm.addEventListener('submit', gsBadgeSave);

async function gsUploadAsset(name, file){
  const fd = new FormData();
  fd.append('action', 'admin_asset_upload');
  fd.append('csrf', GS_CSRF);
  fd.append('name', name);
  fd.append('image', file);
  try {
    const rep = await fetch('greenstand_action.php', {method:'POST', body:fd, credentials:'same-origin', cache:'no-store'});
    const res = await rep.json();
    if(!rep.ok || !res.ok) throw new Error(res.error || 'Envoi refusé');
    gsSetAssets(res.assets || []);
    gsShowSyncNote('Visuel « ' + name + ' » mis à jour');
  } catch(err){ gsShowSyncNote(err.message || 'Envoi refusé'); }
}

async function gsDeleteAsset(name){
  try {
    const res = await gsCloudRequest('admin_asset_delete', {name: name});
    gsSetAssets(res.assets || []);
    gsShowSyncNote('Visuel « ' + name + ' » supprimé');
  } catch(err){ gsShowSyncNote(err.message || 'Suppression refusée'); }
}

const gsFrBtn = document.getElementById('gsFrBtn');
if(gsFrBtn) gsFrBtn.addEventListener('click', gsFranchise);

/* Une remise à zéro générale ne doit jamais arriver en silence. */
function gsShowWipeNotice(){
  if(!gsWiped) return;
  gsWiped = false;
  const o = document.createElement('div');
  o.style.cssText = 'position:fixed;inset:0;background:rgba(10,15,12,.8);display:flex;align-items:center;justify-content:center;z-index:2100;padding:20px;';
  o.innerHTML =
    '<div style="background:var(--panel);border:1px solid var(--gold);border-radius:16px;padding:24px 26px;max-width:380px;text-align:center;font-family:\'Inter\',sans-serif;color:var(--text);box-shadow:0 12px 40px rgba(0,0,0,.6);">' +
      '<div style="font-size:28px;margin-bottom:8px;">&#127807;</div>' +
      '<div style="font-family:\'Bricolage Grotesque\',sans-serif;font-weight:800;font-size:16px;margin-bottom:8px;">GreenStand repart de zéro</div>' +
      '<div style="font-size:13px;color:var(--text-dim);line-height:1.55;margin-bottom:16px;">' +
        'Le jeu passe en version finale : la Franchise remplace les graines, trois classements ' +
        'arrivent, et tout le monde repart sur la même ligne de départ.<br><br>' +
        '<b>Tes jetons, tes gemmes et tes clés n\'ont pas bougé</b> — ils sont à toi, ils restent à toi.' +
      '</div>' +
      '<button id="gsWipeOk" style="all:unset;cursor:pointer;font-family:\'JetBrains Mono\',monospace;font-weight:700;font-size:13px;padding:9px 22px;border-radius:10px;color:#fff;background:linear-gradient(120deg,#8B5FBF,#4CAF3D);">C\'est parti</button>' +
    '</div>';
  document.body.appendChild(o);
  o.querySelector('#gsWipeOk').addEventListener('click', ()=> o.remove());
}

/* ======================================================================
   DÉMARRAGE
   ====================================================================== */
loadGame();
// Le stand tenu est posé AVANT le premier rendu : il décide de la palette, des
// images et des multiplicateurs. gsPoserStand() redescend tout seul au meilleur
// comptoir autorisé si la sauvegarde en annonce un que les franchises ne
// permettent plus (remise à zéro, restauration d'un ancien cliché…).
gsPoserStand(gsStandId, true);
refreshStandModels();
renderAll();
checkAchievements();
if(!GS_LOGGED_IN) gsShowWipeNotice();
gsInitCloudSave();
})();
</script>
<?php if ($gs_panel === 'plantation' && $me): ?>
<script>window.GP_CONFIG = <?= json_encode(['catalog'=>json_decode(file_get_contents(__DIR__.'/assets/plantation/catalog.json'),true),'csrf'=>csrf_token(),'endpoint'=>'plantation_action.php','assetBase'=>'assets/plantation/','demo'=>false],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="assets/plantation/models.js?v=<?= (int)@filemtime(__DIR__.'/assets/plantation/models.js') ?>"></script>
<script src="assets/plantation/plantation.js?v=<?= (int)@filemtime(__DIR__.'/assets/plantation/plantation.js') ?>"></script>
<?php endif; ?>
</body>
</html>
