<?php
/**
 * GreenStand — le pont entre l'argent (€) du stand (calculé côté client, dans le
 * navigateur, comme n'importe quel idle-clicker) et les jetons DT du site.
 *
 * Le jeu tourne entièrement en JS/three.js : il n'y a aucune simulation serveur des
 * achats, niveaux ou clics. On ne peut donc pas vérifier qu'un montant "gagné" annoncé
 * par le client est réellement légitime. Le seul rempart appliqué ici est un plafond
 * calculé à partir du temps *réellement* écoulé côté serveur (jamais celui envoyé par
 * le client) depuis la dernière synchro : au-delà, le surplus n'est simplement pas
 * crédité (perdu, pas volé). Ce n'est pas une preuve d'anti-triche absolue — un idle-
 * clicker 100% client ne peut pas l'être sans réécrire le jeu côté serveur — mais ça
 * borne largement ce qu'on peut extraire en modifiant le state JS ou le localStorage.
 */

// 1€ de vente au stand = ce nombre de jetons DT.
if (!defined('GREENSTAND_DT_PER_EURO'))     define('GREENSTAND_DT_PER_EURO', 1);
// Plafond anti-abus : aucune synchro ne peut créditer plus que ce débit (€/s), quel
// que soit le montant annoncé par le client. Volontairement large pour ne pas gêner
// une partie très avancée (beaucoup d'améliorations achetées + prestige).
if (!defined('GREENSTAND_MAX_EUR_PER_SEC')) define('GREENSTAND_MAX_EUR_PER_SEC', 15000);
// Fenêtre max prise en compte par synchro, même après une longue absence : évite
// qu'une resynchro tardive (onglet resté ouvert des heures) ne verse un pactole.
if (!defined('GREENSTAND_MAX_SYNC_GAP'))    define('GREENSTAND_MAX_SYNC_GAP', 120);
// Nombre de lignes affichées sur le classement des meilleurs vendeurs.
if (!defined('GREENSTAND_BOARD_LIMIT'))     define('GREENSTAND_BOARD_LIMIT', 100);

// ---- Le podium du mois ----
// Ce que touchent les trois premiers du classement « Meilleurs vendeurs » à la
// clôture, le 1er du mois. Les euros arrivent en banque, prêts à passer au
// Distributeur ; le platine achète les bonus permanents ; le point de Hall of
// Fame ne va qu'au premier, et ne se perd jamais.
//
// Les montants ont suivi le passage de la semaine au mois : une place se dispute
// désormais sur trente jours, elle vaut donc quatre fois plus cher (500 000 € et
// 3 platine par semaine -> 2 000 000 € et 15 platine par mois).
//
// Ces montants sont versés PAR LE SERVEUR au moment de la clôture. Ils ne
// passent pas par le plafond de dépôt, qui ne concerne que ce que le navigateur
// annonce avoir produit.
if (!defined('GREENSTAND_PODIUM')) define('GREENSTAND_PODIUM', [
    1 => ['eur' => 2000000.0, 'platine' => 15, 'hall' => true],
    2 => ['eur' => 1500000.0, 'platine' => 10, 'hall' => false],
    3 => ['eur' => 1000000.0, 'platine' => 5,  'hall' => false],
]);

// ---- Générations de remise à zéro ----
// Ces deux constantes sont définies ICI, tout en haut, et pas plus bas avec le
// barème : greenstand_ensure_schema() s'exécute dès le chargement du fichier et
// appelle greenstand_maybe_wipe_boards(), qui lit GREENSTAND_WIPE_GEN. Définie
// plus loin, la constante n'existe pas encore à ce moment-là et la page plante
// avec une « Undefined constant » avant d'avoir affiché quoi que ce soit.
if (!defined('GREENSTAND_RESET_GEN')) define('GREENSTAND_RESET_GEN', 1);
if (!defined('GREENSTAND_WIPE_GEN')) define('GREENSTAND_WIPE_GEN', 1);

// ---- Le Distributeur ----
// Ce que coûte, EN EUROS DU STAND, une unité de chaque monnaie du site.
// Ces taux sont ceux du comptoir de change du site (EXCHANGE_JETONS_PER_GEMME = 6000,
// EXCHANGE_GEMMES_PER_CLE = 30) : 1 clé = 30 gemmes = 180 000 jetons = 180 000 €.
// Les garder alignés est ce qui empêche de gagner de la monnaie en passant par un
// chemin plutôt qu'un autre.
if (!defined('GREENSTAND_EUR_PAR_JETON')) define('GREENSTAND_EUR_PAR_JETON', 10);
if (!defined('GREENSTAND_RATES')) define('GREENSTAND_RATES', [
    'jetons' => GREENSTAND_EUR_PAR_JETON,
    'gemmes' => GREENSTAND_EUR_PAR_JETON * EXCHANGE_JETONS_PER_GEMME,                            //     60 000 €
    'cles'   => GREENSTAND_EUR_PAR_JETON * EXCHANGE_JETONS_PER_GEMME * EXCHANGE_GEMMES_PER_CLE,  //  1 800 000 €
]);
// Quantité maximale par échange. 0 = AUCUNE LIMITE : le joueur convertit tout ce que
// sa banque lui permet, en une seule fois.
//
// Il y avait ici un plafond de 500 000 000 par échange. C'était un garde-fou de saisie,
// mais pour un joueur de fin de partie c'était devenu un mur : au-delà, le Distributeur
// refusait, et le bouton « Tout ce que je peux » s'arrêtait là sans dire pourquoi.
// La seule limite qui reste est celle qui protège vraiment quelque chose : la place
// restante sur le solde de destination (gs_currency_max(), ci-dessous).
if (!defined('GREENSTAND_EXCHANGE_QTY_CAP')) define('GREENSTAND_EXCHANGE_QTY_CAP', 0);
// Repli si la base ne sait pas dire de quel type est la colonne de monnaie : la valeur
// historique (INT UNSIGNED). Le vrai plafond est LU en base par gs_currency_max().
if (!defined('GREENSTAND_CURRENCY_MAX')) define('GREENSTAND_CURRENCY_MAX', 4294967295);
// Faut-il élargir les colonnes de monnaie en BIGINT UNSIGNED au premier chargement ?
// C'est ce qui rend la conversion réellement illimitée : une colonne INT UNSIGNED
// s'arrête à 4,29 milliards, et un joueur qui approche ce chiffre ne peut plus rien
// convertir. Mets-la à false si tu préfères garder la base telle quelle.
if (!defined('GREENSTAND_WIDEN_CURRENCIES')) define('GREENSTAND_WIDEN_CURRENCIES', true);

// ---- Plafond de dépôt ----
// Marge accordée au-dessus de ce que le stand produit vraiment : un plafond au ras
// des pâquerettes volerait des gains légitimes au moindre décalage d'horloge.
if (!defined('GREENSTAND_DEPOSIT_MARGIN')) define('GREENSTAND_DEPOSIT_MARGIN', 3.0);
// Plancher : même un stand vide peut déposer ça, sinon un débutant qui clique ne
// verrait jamais un centime arriver.
if (!defined('GREENSTAND_DEPOSIT_FLOOR'))  define('GREENSTAND_DEPOSIT_FLOOR', 5.0);

// ======================================================================
// ANTI AUTO-CLICKER
// ======================================================================
// Le filtre JavaScript du jeu (isHumanLikeClick) écarte les macros évidentes,
// mais il tourne chez le joueur : quelqu'un qui désactive le script, rejoue la
// requête à la main ou gonfle son localStorage passe à travers. La vraie
// barrière est ici, côté serveur, et elle ne fait confiance qu'à deux choses :
// l'horloge du serveur, et le compteur de ventes manuelles que la sauvegarde
// porte déjà (lifetimeManualSales).
//
// Principe : entre deux sauvegardes, on mesure combien de ventes manuelles ont
// été déclarées et sur combien de secondes RÉELLES. Au-delà de la cadence qu'un
// humain peut tenir, le surplus n'est pas comptabilisé — les clics en trop ne
// rapportent donc rien — et le compte prend un avertissement. Trois
// avertissements et l'enveloppe « clic » du plafond de dépôt tombe à zéro
// pendant un moment : le revenu automatique du stand continue, les clics ne
// paient plus.

// Cadence humaine soutenable, en clics par seconde.
//
// 12 au départ (clic ordinaire), puis 20 (butterfly-click, deux doigts en
// alternance), maintenant 40 — le territoire du drag-click, où le frottement du
// doigt sur le bouton déclenche une rafale de contacts que la souris rapporte
// comme autant de clics.
//
// À 40, la cadence ne dit plus grand-chose : ce seuil ne se déclenchera pour
// ainsi dire jamais. Ce qui trahit encore une macro, ce sont les deux autres
// contrôles, et ils restent entiers : les clics fabriqués par script
// (isTrusted) et la régularité de métronome. L'enveloppe « ventes à la main »
// du plafond de dépôt, elle, double par rapport à 20.
if (!defined('GREENSTAND_HUMAN_CPS'))        define('GREENSTAND_HUMAN_CPS', 40);
// Tolérance ponctuelle, en clics : absorbe une rafale courte et les décalages
// entre l'horodatage d'une sauvegarde et celui de la suivante.
if (!defined('GREENSTAND_HUMAN_BURST'))      define('GREENSTAND_HUMAN_BURST', 25);
// Fenêtre maximale prise en compte entre deux mesures. Sans ce plafond, un
// onglet resté ouvert une nuit accumulerait un droit à des centaines de milliers
// de clics d'un coup. On ne clique pas hors ligne : rien de légitime n'est perdu.
if (!defined('GREENSTAND_CLICK_WINDOW_MAX')) define('GREENSTAND_CLICK_WINDOW_MAX', 120);
// Nombre d'avertissements avant la suspension. Le premier fait apparaître le menu
// de vérification (« es-tu là ? »), le second suspend l'accès au stand.
if (!defined('GREENSTAND_AUTOCLICK_STRIKES')) define('GREENSTAND_AUTOCLICK_STRIKES', 2);
// Durée de la suspension, en secondes.
if (!defined('GREENSTAND_AUTOCLICK_COOLDOWN')) define('GREENSTAND_AUTOCLICK_COOLDOWN', 600);
// Adresses IP dispensées de tout ce mécanisme (poste de l'administrateur : tests
// du jeu, macros de recette). Elles ne sont ni filtrées côté navigateur, ni
// bridées côté serveur.
if (!defined('GREENSTAND_AUTOCLICK_EXEMPT_IPS')) define('GREENSTAND_AUTOCLICK_EXEMPT_IPS', [
    '91.86.110.72',
]);
// Les comptes qui voient l'interrupteur général anti-triche dans le panel admin.
// La comparaison ignore la casse. Ajoute ici le pseudo EXACT tel qu'il apparaît
// dans la colonne username, sinon le bouton ne s'affichera pas.
if (!defined('GREENSTAND_ANTICHEAT_OWNERS')) define('GREENSTAND_ANTICHEAT_OWNERS', [
    'm0onnnnzy',
    'm0onnnzy',
    'HSPNishenafou',
]);
// Le site est-il derrière un reverse-proxy de confiance (Cloudflare, nginx) qui
// renseigne X-Forwarded-For ? Tant que c'est false, on ne lit que REMOTE_ADDR :
// n'importe qui peut inventer un en-tête, personne ne peut inventer son IP TCP.
if (!defined('GREENSTAND_TRUST_PROXY'))      define('GREENSTAND_TRUST_PROXY', false);

/* ----------------------------------------------------------------------
   LE PLAFOND DES MONNAIES DU SITE — lu en base, pas deviné

   Le Distributeur ne s'interdit plus rien sur la quantité échangée (voir
   GREENSTAND_EXCHANGE_QTY_CAP) : la seule limite qui reste est physique, c'est
   ce que la colonne de destination peut contenir. Autant la LIRE plutôt que de
   l'écrire en dur : une colonne élargie en BIGINT (voir juste en dessous) doit
   ouvrir le plafond du même coup, sinon élargir ne servirait à rien.
   ---------------------------------------------------------------------- */

/** Le type SQL d'une colonne de la table users, en minuscules ('' si inconnu). */
function gs_column_type(string $col): string {
    static $cache = [];
    if (array_key_exists($col, $cache)) return $cache[$col];
    try {
        $stmt = db()->prepare("
            SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$col]);
        return $cache[$col] = strtolower(trim((string) $stmt->fetchColumn()));
    } catch (\Throwable $e) {
        return $cache[$col] = '';
    }
}

/** Ce que la colonne d'une monnaie du site peut contenir, au maximum. */
function gs_currency_max(string $devise): int {
    static $cache = [];
    if (isset($cache[$devise])) return $cache[$devise];
    $col = CURRENCY_COLUMNS[$devise] ?? '';
    if ($col === '') return $cache[$devise] = GREENSTAND_CURRENCY_MAX;

    $type   = gs_column_type($col);
    $signed = $type !== '' && !str_contains($type, 'unsigned');
    $max    = GREENSTAND_CURRENCY_MAX;
    if (str_starts_with($type, 'bigint'))         $max = PHP_INT_MAX;   // 9,2 x 10^18 : illimité en pratique
    elseif (str_starts_with($type, 'mediumint'))  $max = $signed ? 8388607 : 16777215;
    elseif (str_starts_with($type, 'smallint'))   $max = $signed ? 32767 : 65535;
    elseif (str_starts_with($type, 'int'))        $max = $signed ? 2147483647 : 4294967295;
    elseif (str_starts_with($type, 'decimal'))    $max = PHP_INT_MAX;
    return $cache[$devise] = $max;
}

/** Les plafonds des trois monnaies, pour la page (JS). */
function gs_currency_maxes(): array {
    $out = [];
    foreach (array_keys(GREENSTAND_RATES) as $devise) $out[$devise] = gs_currency_max($devise);
    return $out;
}

/**
 * Élargit les colonnes de monnaie en BIGINT UNSIGNED — une seule fois.
 *
 * Sans ça, « convertir sans limite » resterait un mensonge : la colonne s'arrête à
 * 4 294 967 295 et l'échange qui la dépasserait est refusé (à raison : MySQL
 * tronquerait le solde). L'opération ne perd RIEN — un INT UNSIGNED entre dans un
 * BIGINT UNSIGNED sans conversion — et ne touche pas aux valeurs.
 *
 * Un témoin dans data/ évite de relancer un ALTER à chaque chargement de page, y
 * compris quand il échoue (droits insuffisants) : supprime le fichier pour réessayer.
 */
function gs_widen_currency_columns(): void {
    if (!GREENSTAND_WIDEN_CURRENCIES) return;
    $temoin = __DIR__ . '/data/greenstand_currencies_bigint.json';
    if (is_file($temoin)) return;

    $rapport = [];
    foreach (CURRENCY_COLUMNS as $devise => $col) {
        // Un nom de colonne ne vient JAMAIS de l'utilisateur (CURRENCY_COLUMNS est
        // écrit en dur dans common.php) ; on le revalide quand même avant de le
        // coller dans un ALTER, qui n'accepte pas de paramètre lié.
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $col)) continue;
        $type = gs_column_type((string) $col);
        if ($type === '' || str_starts_with($type, 'bigint')) { $rapport[$col] = $type ?: 'inconnu'; continue; }
        if (!str_starts_with($type, 'int') && !str_starts_with($type, 'mediumint') && !str_starts_with($type, 'smallint')) {
            $rapport[$col] = 'laissé tel quel (' . $type . ')';
            continue;
        }
        try {
            db()->exec("ALTER TABLE users MODIFY {$col} BIGINT UNSIGNED NOT NULL DEFAULT 0");
            $rapport[$col] = 'bigint unsigned';
        } catch (\Throwable $e) {
            $rapport[$col] = 'échec : ' . $e->getMessage();
            error_log('[greenstand] élargissement ' . $col . ' : ' . $e->getMessage());
        }
    }
    @mkdir(dirname($temoin), 0775, true);
    @file_put_contents($temoin, json_encode(['le' => date('c'), 'colonnes' => $rapport],
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function greenstand_ensure_schema(): void {
    // Les soldes de jetons/gemmes/clés passent en BIGINT UNSIGNED : c'est ce qui
    // permet au Distributeur de convertir sans plafond (une seule fois, cf. témoin).
    gs_widen_currency_columns();
    // Total de jetons DT gagnés au stand (à vie) : c'est la colonne qui fait le
    // classement des meilleurs vendeurs, ci-dessous.
    ensure_column('users', 'greenstand_dt_earned', "BIGINT UNSIGNED NOT NULL DEFAULT 0");
    // Total en euros du jeu (juste pour info/affichage, ne sert à aucun calcul).
    ensure_column('users', 'greenstand_eur_earned', "DECIMAL(14,2) NOT NULL DEFAULT 0");
    // Horodatage de la dernière synchro : c'est LUI qui fixe le plafond anti-abus,
    // jamais une valeur envoyée par le client.
    ensure_column('users', 'greenstand_last_sync', "DATETIME NULL");
    // La banque du stand, en euros. Depuis le Distributeur, l'argent gagné n'est plus
    // converti tout seul en jetons : il s'accumule ici, et le joueur choisit ce qu'il
    // en fait (jetons, gemmes ou clés). C'est le solde qui fait foi côté serveur.
    ensure_column('users', 'greenstand_eur_bank', "DECIMAL(18,2) NOT NULL DEFAULT 0");

    // ---- La Franchise (le prestige) ----
    // Feuilles d'or en réserve, total gagné à vie (classement « Parrain »), nombre de
    // reventes, niveaux des trois boosts permanents (JSON), et meilleur €/s atteint
    // (classement « Roi du Stand »).
    ensure_column('users', 'greenstand_gold',        "INT UNSIGNED NOT NULL DEFAULT 0");
    ensure_column('users', 'greenstand_gold_life',   "BIGINT UNSIGNED NOT NULL DEFAULT 0");
    ensure_column('users', 'greenstand_franchises',  "INT UNSIGNED NOT NULL DEFAULT 0");
    // Depuis la v10, deux monnaies bien distinctes :
    //   greenstand_gold / greenstand_gold_life  = les Feuilles d'Or. Un trophée : une
    //     par franchise ouverte, JAMAIS dépensée. C'est le classement « Parrain ».
    //   greenstand_platine / greenstand_platine_life = les Feuilles de Platine. La
    //     monnaie, et la seule : elle paie les bonus permanents.
    // Séparer les deux supprime la confusion qui rendait une feuille visible au
    // classement mais introuvable en réserve : ce ne sont plus les mêmes feuilles.
    ensure_column('users', 'greenstand_platine',      "INT UNSIGNED NOT NULL DEFAULT 0");
    ensure_column('users', 'greenstand_platine_life', "BIGINT UNSIGNED NOT NULL DEFAULT 0");
    ensure_column('users', 'greenstand_boosts',      "VARCHAR(255) NOT NULL DEFAULT ''");
    ensure_column('users', 'greenstand_best_persec', "DECIMAL(20,2) NOT NULL DEFAULT 0");

    // Le classement des meilleurs vendeurs se compte en EUROS déposés, pas en jetons.
    // Les jetons dépendent de ce que le joueur a décidé de convertir au Distributeur :
    // deux joueurs qui produisent autant peuvent avoir des jetons très différents. Les
    // euros déposés mesurent ce que le stand a réellement produit.
    ensure_column('users', 'greenstand_week_eur',      "DECIMAL(20,2) NOT NULL DEFAULT 0");
    ensure_column('users', 'greenstand_best_week_eur', "DECIMAL(20,2) NOT NULL DEFAULT 0");

    // Une seule sauvegarde de jeu par compte. LONGTEXT évite de dépendre du type JSON
    // (certaines installations MySQL/MariaDB plus anciennes ne l'ont pas).
    db()->exec("
        CREATE TABLE IF NOT EXISTS greenstand_saves (
            username VARCHAR(24) NOT NULL PRIMARY KEY,
            state_json LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX greenstand_saves_updated_at (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Jetons DT gagnés au stand depuis le début du MOIS en cours (remis à 0 le 1er,
    // cf. greenstand_maybe_reset_period()) : c'est LUI qui alimente le classement
    // principal. Les colonnes gardent leur nom en « week » : elles existent déjà en
    // base avec les données des joueurs, et les renommer ne changerait rien au jeu.
    ensure_column('users', 'greenstand_week_dt', "BIGINT UNSIGNED NOT NULL DEFAULT 0");
    // Nombre de fois où le joueur a terminé 1er du classement (à vie,
    // jamais remis à 0) : alimente le second classement "nombre de fois premier".
    ensure_column('users', 'greenstand_weekly_wins', "INT UNSIGNED NOT NULL DEFAULT 0");
    // Record personnel : le plus gros total de jetons DT jamais atteint sur UNE période
    // (jamais remis à 0, contrairement à greenstand_week_dt qui repart de 0 le 1er).
    ensure_column('users', 'greenstand_best_week_dt', "BIGINT UNSIGNED NOT NULL DEFAULT 0");

    // Historique des sauvegardes : cinq clichés espacés par joueur. La table
    // greenstand_saves n'a qu'UNE ligne par joueur, écrasée toutes les cinq
    // secondes — une progression perdue l'était définitivement.
    db()->exec("
        CREATE TABLE IF NOT EXISTS greenstand_save_history (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username   VARCHAR(64) NOT NULL,
            state_json MEDIUMTEXT NOT NULL,
            reason     VARCHAR(32) NOT NULL DEFAULT 'auto',
            created_at DATETIME NOT NULL,
            KEY idx_joueur_date (username, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Objectifs du jour : trois par joueur, remis en jeu chaque matin.
    db()->exec("
        CREATE TABLE IF NOT EXISTS greenstand_daily (
            username       VARCHAR(64) NOT NULL PRIMARY KEY,
            day            DATE NOT NULL,
            base_json      TEXT NOT NULL,
            objectifs_json TEXT NOT NULL,
            paid_json      TEXT NOT NULL,
            updated_at     DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Les achats de la boutique, pour tenir les limites du jour. Une ligne par
    // joueur : la date sert de remise à zéro, on ne garde pas d'historique.
    db()->exec("
        CREATE TABLE IF NOT EXISTS greenstand_boutique (
            username    VARCHAR(64) NOT NULL PRIMARY KEY,
            day         DATE NOT NULL,
            achats_json TEXT NOT NULL,
            effets_json TEXT NOT NULL,
            updated_at  DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // La colonne des effets est arrivée après la table : sur une installation qui
    // avait déjà la première version, CREATE TABLE IF NOT EXISTS ne l'aurait pas
    // ajoutée, et tous les achats d'effets seraient perdus au changement de page.
    ensure_column('greenstand_boutique', 'effets_json', "TEXT NOT NULL");

    // Le téléphone : la marchandise en stock, les commandes en attente, et le
    // compteur de livraisons du jour. Une ligne par joueur.
    db()->exec("
        CREATE TABLE IF NOT EXISTS greenstand_telephone (
            username     VARCHAR(64) NOT NULL PRIMARY KEY,
            stock        INT UNSIGNED NOT NULL DEFAULT 0,
            commandes    TEXT NOT NULL,
            day          DATE NOT NULL,
            livrees      INT UNSIGNED NOT NULL DEFAULT 0,
            dernier_appel INT UNSIGNED NOT NULL DEFAULT 0,
            quota_bonus  INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at   DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Les livraisons offertes par le « Carnet d'adresses » de la boutique. Ajoutée
    // après la table : sur une installation qui avait déjà la première version,
    // CREATE TABLE IF NOT EXISTS ne l'aurait pas créée.
    ensure_column('greenstand_telephone', 'quota_bonus', "INT UNSIGNED NOT NULL DEFAULT 0");

    // Frein de cadence sur greenstand_action.php : une fenêtre glissante par
    // joueur. Chaque sauvegarde écrit dans deux tables ; sans compteur, un script
    // peut appeler l'endpoint en boucle.
    db()->exec("
        CREATE TABLE IF NOT EXISTS greenstand_rate (
            username      VARCHAR(64) NOT NULL PRIMARY KEY,
            window_start  DATETIME NOT NULL,
            hits          INT UNSIGNED NOT NULL DEFAULT 0,
            blocked_until DATETIME NULL,
            trips         INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Registre anti auto-clicker : une ligne par joueur, tenue à jour à chaque
    // sauvegarde. Elle mémorise le compteur de ventes manuelles vu la dernière fois
    // et l'heure SERVEUR de cette lecture — c'est la différence entre les deux qui
    // donne la cadence réelle, sans jamais croire une horloge cliente.
    db()->exec("
        CREATE TABLE IF NOT EXISTS greenstand_clicks (
            username       VARCHAR(24) NOT NULL PRIMARY KEY,
            last_clicks    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            pending_clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
            checked_at     DATETIME NOT NULL,
            strikes        INT UNSIGNED NOT NULL DEFAULT 0,
            blocked_until  DATETIME NULL,
            last_rate      DECIMAL(10,2) NOT NULL DEFAULT 0,
            flagged_at     DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Petite table clé/valeur servant uniquement à détecter, sans cron, le passage au
    // mois suivant (cf. greenstand_maybe_reset_period() ci-dessous).
    db()->exec("
        CREATE TABLE IF NOT EXISTS greenstand_meta (
            name  VARCHAR(64) NOT NULL PRIMARY KEY,
            value VARCHAR(32) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // INSERT IGNORE : la ligne n'est écrite que la première fois. C'est aussi ce qui
    // fait la bascule hebdo -> mensuel en douceur, sans clôturer la semaine en cours
    // (voir greenstand_maybe_reset_period()). L'ancienne ligne 'week_key' est laissée
    // en place : elle ne sert plus à rien, et la supprimer ne rapporterait rien.
    db()->prepare("INSERT IGNORE INTO greenstand_meta (name, value) VALUES ('period_key', ?)")
        ->execute([greenstand_current_period_key()]);

    // IMPORTANT : ne jamais déclencher une remise à zéro globale au chargement.
    // Une ancienne version l'avait fait à l'installation et avait effacé les
    // franchises déjà validées.
    greenstand_repair_uncredited_gold();
    greenstand_fix_gold_franchises_v6();
    greenstand_repair_gold_reserve_v7();
    greenstand_split_platine_v10();
}

/**
 * Réparation unique de la version 4.
 *
 * Certaines franchises déjà enregistrées ont incrémenté le total à vie (visible
 * au classement) sans laisser la feuille dans la réserve du joueur. On restitue
 * donc exactement le total à vie aux comptes dont la réserve est à 0 et qui
 * n'ont acheté aucun boost : ces comptes n'ont pu dépenser aucune feuille.
 * Une marque en base empêche toute nouvelle restitution à la prochaine mise à
 * jour et ne touche jamais aux comptes ayant déjà investi leurs feuilles.
 */
function greenstand_repair_uncredited_gold(): void {
    $stmt = db()->prepare("SELECT value FROM greenstand_meta WHERE name = 'gold_repair_v4' LIMIT 1");
    $stmt->execute();
    if ($stmt->fetchColumn() !== false) return;

    db()->prepare("
        UPDATE users
        SET greenstand_gold = greenstand_gold_life
        WHERE greenstand_gold = 0
          AND greenstand_gold_life > 0
          AND (greenstand_boosts IS NULL OR greenstand_boosts = '' OR greenstand_boosts = '{}')
    ")->execute();
    db()->prepare("INSERT INTO greenstand_meta (name, value) VALUES ('gold_repair_v4', 'done')
                   ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute();
}


/**
 * Correctif v6. La compensation v5 a été trop large : elle a donné une feuille
 * à des comptes qui n'avaient jamais ouvert de franchise. On annule strictement
 * ces lignes (1 feuille, 0 franchise, aucun boost), puis on crédite la réserve
 * depuis le total gagné à vie UNIQUEMENT pour les vraies franchises enregistrées.
 * Marque unique : aucune modification ne se répète aux chargements suivants.
 */
function greenstand_fix_gold_franchises_v6(): void {
    $stmt = db()->prepare("SELECT value FROM greenstand_meta WHERE name = 'gold_fix_v6' LIMIT 1");
    $stmt->execute();
    if ($stmt->fetchColumn() !== false) return;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Retire seulement le cadeau v5 identifiable : les non-joueurs avaient
        // zéro franchise, zéro boost et exactement une feuille dans les deux compteurs.
        $pdo->prepare("
            UPDATE users
            SET greenstand_gold = 0, greenstand_gold_life = 0
            WHERE COALESCE(greenstand_franchises, 0) = 0
              AND COALESCE(greenstand_gold, 0) = 1
              AND COALESCE(greenstand_gold_life, 0) = 1
              AND (greenstand_boosts IS NULL OR greenstand_boosts = '' OR greenstand_boosts = '{}')
        ")->execute();

        // La feuille demandée est celle de la Franchise : le nombre validé à vie
        // est donc la source fiable. Ne touche pas aux joueurs ayant investi.
        $pdo->prepare("
            UPDATE users
            SET greenstand_gold = greenstand_gold_life
            WHERE COALESCE(greenstand_franchises, 0) > 0
              AND COALESCE(greenstand_gold, 0) = 0
              AND COALESCE(greenstand_gold_life, 0) > 0
              AND (greenstand_boosts IS NULL OR greenstand_boosts = '' OR greenstand_boosts = '{}')
        ")->execute();

        $pdo->prepare("INSERT INTO greenstand_meta (name, value) VALUES ('gold_fix_v6', 'done')
                       ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[greenstand_fix_gold_franchises_v6] ' . $e->getMessage());
    }
}

/**
 * Passage aux deux monnaies (v10), une seule fois.
 *
 * Personne ne perd rien : les feuilles qu'un joueur avait en réserve deviennent des
 * Feuilles de Platine (même nombre), et son total à vie devient son total de platine
 * gagné. Les Feuilles d'Or, elles, deviennent le trophée : réserve = total à vie,
 * puisqu'elles ne se dépensent plus.
 */
function greenstand_split_platine_v10(): void {
    $stmt = db()->prepare("SELECT value FROM greenstand_meta WHERE name = 'platine_v10' LIMIT 1");
    $stmt->execute();
    if ($stmt->fetchColumn() !== false) return;

    try {
        $rows = db()->query("SELECT username, greenstand_gold, greenstand_gold_life, greenstand_boosts
                             FROM users WHERE COALESCE(greenstand_gold_life, 0) > 0")->fetchAll();
        $maj = db()->prepare("UPDATE users
                              SET greenstand_platine = ?, greenstand_platine_life = ?,
                                  greenstand_gold = greenstand_gold_life
                              WHERE username = ?");
        foreach ($rows as $r) {
            $data  = json_decode((string) ($r['greenstand_boosts'] ?? ''), true);
            $life  = (int) $r['greenstand_gold_life'];
            $spent = greenstand_gold_spent(is_array($data) ? $data : []);
            // Ce qui restait dépensable devient du platine ; ce qui a déjà été dépensé
            // reste dépensé (les niveaux de boosts sont conservés tels quels).
            $maj->execute([max(0, $life - $spent), $life, $r['username']]);
        }
        db()->prepare("INSERT INTO greenstand_meta (name, value) VALUES ('platine_v10', 'done')
                       ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute();
        error_log('[GreenStand] v10 : ' . count($rows) . ' compte(s) passés aux Feuilles de Platine.');
    } catch (Throwable $e) {
        error_log('[greenstand_split_platine_v10] ' . $e->getMessage());
    }
}

/* ======================================================================
   L'INVARIANT DES FEUILLES D'OR
   ======================================================================
   Une feuille n'a qu'une seule origine (une franchise) et qu'un seul
   usage (un niveau de boost). Donc, pour tout compte :

       réserve (greenstand_gold) = gagnées à vie (greenstand_gold_life)
                                   − dépensées en boosts

   Les correctifs v4, v5 et v6 essayaient de deviner les comptes à
   réparer avec des conditions précises (« exactement 1 feuille », « aucun
   boost acheté », « au moins une franchise »). Tout compte qui sortait de
   ces cases restait cassé : c'est le cas où la feuille apparaît au
   classement « Parrain de la Weed » (qui lit le total à vie) mais reste
   introuvable dans l'onglet Franchise (qui lit la réserve), donc
   impossible à dépenser — aucun boost ne coûte moins d'une feuille.

   On ne devine plus : on recalcule l'invariant. Il n'a besoin d'aucune
   hypothèse sur l'historique du compte.
   ====================================================================== */

/**
 * Ce qu'un joueur a réellement dépensé en feuilles, d'après ses niveaux de
 * boosts : chaque niveau coûte 1, 2, 4, 8… feuilles, donc L niveaux d'un
 * boost coûtent 2^L − 1. Calculé avec greenstand_boost_cost() pour rester
 * juste si le barème change un jour.
 *
 * @param array<string,int> $levels  id du boost => niveau atteint
 */
function greenstand_gold_spent(array $levels): int {
    $spent = 0;
    foreach (greenstand_boosts() as $b) {
        $lvl = max(0, min((int) ($levels[$b['id']] ?? 0), (int) $b['maxLevel']));
        for ($i = 0; $i < $lvl; $i++) $spent += greenstand_boost_cost($b, $i);
    }
    return $spent;
}

/**
 * La réserve que le compte DEVRAIT avoir, d'après l'invariant ci-dessus.
 * Jamais négative : un total à vie plus petit que les dépenses (données
 * héritées d'une ancienne version) donne simplement 0.
 */
function greenstand_expected_gold(int $lifetime, array $levels): int {
    return max(0, $lifetime - greenstand_gold_spent($levels));
}

/**
 * Remet la réserve d'UN joueur en accord avec l'invariant, si elle est en
 * dessous. On ne retire jamais rien : une réserve supérieure à l'attendu
 * (don d'admin, futur ajustement) est laissée telle quelle.
 *
 * Appelée par greenstand_gold_state(), donc à chaque chargement de la page,
 * à chaque franchise et à chaque achat de boost : un compte ne peut plus
 * rester bloqué en attendant une mise à jour du site.
 *
 * @return int  la réserve après réparation
 */
function greenstand_reconcile_platine(string $username): int {
    $stmt = db()->prepare("SELECT greenstand_platine, greenstand_platine_life, greenstand_boosts
                           FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row) return 0;

    $reserve = (int) ($row['greenstand_platine'] ?? 0);
    $life    = (int) ($row['greenstand_platine_life'] ?? 0);
    $data    = json_decode((string) ($row['greenstand_boosts'] ?? ''), true);
    $attendu = greenstand_expected_gold($life, is_array($data) ? $data : []);
    if ($attendu <= $reserve) return $reserve;

    db()->prepare("UPDATE users SET greenstand_platine = ? WHERE username = ?")
        ->execute([$attendu, $username]);
    error_log('[GreenStand] Réserve de platine rétablie pour ' . $username
        . ' : ' . $reserve . ' -> ' . $attendu . ' (gagné à vie ' . $life . ').');
    return $attendu;
}

/**
 * Correctif v7 — passage unique sur tous les comptes qui ont déjà gagné des
 * feuilles, pour appliquer l'invariant sans attendre que chacun rouvre le jeu
 * (le classement et l'onglet Franchise sont ainsi d'accord tout de suite).
 *
 * Contrairement aux correctifs précédents, il ne supprime rien et ne pose
 * aucune condition sur le nombre de franchises ou de boosts.
 */
function greenstand_repair_gold_reserve_v7(): void {
    $stmt = db()->prepare("SELECT value FROM greenstand_meta WHERE name = 'gold_reserve_v7' LIMIT 1");
    $stmt->execute();
    if ($stmt->fetchColumn() !== false) return;

    try {
        $rows = db()->query("SELECT username, greenstand_gold, greenstand_gold_life, greenstand_boosts
                             FROM users WHERE COALESCE(greenstand_gold_life, 0) > 0")->fetchAll();
        $maj = db()->prepare("UPDATE users SET greenstand_gold = ? WHERE username = ?");
        $n = 0;
        foreach ($rows as $r) {
            $data    = json_decode((string) ($r['greenstand_boosts'] ?? ''), true);
            $attendu = greenstand_expected_gold((int) $r['greenstand_gold_life'], is_array($data) ? $data : []);
            if ($attendu > (int) $r['greenstand_gold']) { $maj->execute([$attendu, $r['username']]); $n++; }
        }
        db()->prepare("INSERT INTO greenstand_meta (name, value) VALUES ('gold_reserve_v7', 'done')
                       ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute();
        if ($n) error_log('[GreenStand] Correctif v7 : réserve de feuilles rétablie pour ' . $n . ' compte(s).');
    } catch (Throwable $e) {
        error_log('[greenstand_repair_gold_reserve_v7] ' . $e->getMessage());
    }
}

/**
 * Remise à zéro des compteurs de jeu de TOUS les joueurs, une seule fois par
 * génération (GREENSTAND_WIPE_GEN).
 *
 * Le wipe côté client vide la partie ; celui-ci vide ce qui vit en base : classements,
 * banque en euros, feuilles d'or, boosts et franchises. Sans lui, les nouveaux
 * classements afficheraient encore les scores de l'ancienne économie — c'est ce que tu
 * voyais avec des « 2 741 100 444 DT » impossibles à rattraper.
 *
 * Les jetons, gemmes et clés ne sont PAS touchés : ils appartiennent au joueur.
 */
function greenstand_maybe_wipe_boards(): void {
    $stmt = db()->prepare("SELECT value FROM greenstand_meta WHERE name = 'wipe_gen' LIMIT 1");
    $stmt->execute();
    $fait = (int) ($stmt->fetchColumn() ?: 0);
    if ($fait >= GREENSTAND_WIPE_GEN) return;

    db()->exec("
        UPDATE users SET
            greenstand_dt_earned    = 0, greenstand_eur_earned   = 0,
            greenstand_week_dt      = 0, greenstand_best_week_dt = 0,
            greenstand_week_eur     = 0, greenstand_best_week_eur= 0,
            greenstand_weekly_wins  = 0, greenstand_best_persec  = 0,
            greenstand_eur_bank     = 0,
            greenstand_gold         = 0, greenstand_gold_life    = 0,
            greenstand_franchises   = 0, greenstand_boosts       = '',
            greenstand_last_sync    = NULL
    ");
    db()->exec("DELETE FROM greenstand_saves");
    db()->prepare("INSERT INTO greenstand_meta (name, value) VALUES ('wipe_gen', ?)
                   ON DUPLICATE KEY UPDATE value = VALUES(value)")
        ->execute([(string) GREENSTAND_WIPE_GEN]);
    error_log('[GreenStand] Remise à zéro générale appliquée (génération ' . GREENSTAND_WIPE_GEN . ').');
}
greenstand_ensure_schema();

/**
 * Identifiant "année-mois" de la période en cours côté serveur.
 *
 * Le classement se jouait à la semaine ISO ("2026-36") et se clôturait donc chaque
 * lundi. Il se joue maintenant au MOIS : cette valeur change toute seule le 1er à
 * minuit (heure du serveur), sans cron, exactement comme avant.
 */
function greenstand_current_period_key(): string {
    return (new DateTime())->format('Y-m');
}

/** Ancien nom, gardé pour ne rien casser si un script l'appelle encore. */
function greenstand_current_week_key(): string {
    return greenstand_current_period_key();
}

/**
 * À appeler avant toute lecture/écriture liée au classement. Détecte, de façon sûre
 * même avec plusieurs requêtes concurrentes (verrou de ligne InnoDB sur l'UPDATE), le
 * passage au mois suivant et déclenche alors une seule fois la clôture du mois
 * précédent (podium versé, point de victoire, remise à zéro des compteurs).
 *
 * LE PASSAGE DE LA SEMAINE AU MOIS. La clé est lue sous un nom NEUF ('period_key')
 * et pas sous l'ancien ('week_key'). Ce n'est pas une coquetterie : les deux formats
 * se ressemblent trop pour être distingués à coup sûr ("2026-09" est un mois de
 * septembre, mais c'était aussi la semaine 9). En changeant de nom, la ligne
 * n'existe pas encore au premier chargement : greenstand_ensure_schema() l'écrit
 * avec le mois courant, l'UPDATE ci-dessous ne voit alors aucun écart, et RIEN n'est
 * clôturé. Autrement dit, la bascule ne verse aucun lot pour la semaine en cours —
 * le mois démarre proprement, et la première clôture mensuelle aura lieu le 1er du
 * mois suivant.
 */
function greenstand_maybe_reset_period(): void {
    $newKey = greenstand_current_period_key();
    $stmt = db()->prepare("UPDATE greenstand_meta SET value = ? WHERE name = 'period_key' AND value <> ?");
    $stmt->execute([$newKey, $newKey]);
    if ($stmt->rowCount() > 0) {
        greenstand_finalize_period();
    }
}

/** Ancien nom, gardé pour ne rien casser si un script l'appelle encore. */
function greenstand_maybe_reset_week(): void {
    greenstand_maybe_reset_period();
}

/**
 * Clôture le mois qui vient de s'écouler : le joueur au sommet du classement gagne
 * un point de victoire à vie, puis tous les compteurs de période repartent à 0.
 */
/**
 * Verse des Feuilles de Platine à un joueur.
 *
 * Le platine se dépense : le total « à vie » doit rester réserve + déjà dépensé,
 * sinon la réparation automatique (greenstand_reconcile_platine) reprendrait ce
 * qu'on vient de donner. Même calcul que le panel admin.
 */
function greenstand_donner_platine(string $username, int $combien): void {
    if ($combien <= 0) return;
    $stmt = db()->prepare("SELECT greenstand_platine, greenstand_boosts FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row) return;

    $data  = json_decode((string) ($row['greenstand_boosts'] ?? ''), true);
    $spent = greenstand_gold_spent(is_array($data) ? $data : []);
    $apres = max(0, (int) $row['greenstand_platine'] + $combien);

    db()->prepare("UPDATE users SET greenstand_platine = ?, greenstand_platine_life = ? WHERE username = ?")
        ->execute([$apres, $apres + $spent, $username]);
}

/**
 * Verse le lot d'un rang de podium. Utilisée par la clôture du mois ET par le
 * script de rattrapage : un seul endroit décide de ce qui est versé, pour que les
 * deux ne puissent pas diverger.
 *
 * $avecHall vaut false quand le point de Hall of Fame a déjà été attribué par
 * ailleurs — c'est le cas d'un rattrapage sur une période déjà close.
 */
function greenstand_verser_podium(string $username, int $rang, bool $avecHall = true): array {
    $lot = GREENSTAND_PODIUM[$rang] ?? null;
    if (!$lot) return ['ok' => false, 'error' => "Rang inconnu : $rang"];

    $stmt = db()->prepare("SELECT username FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $exact = $stmt->fetchColumn();
    // La comparaison SQL ignore la casse : on repart du pseudo EXACT de la base,
    // sinon l'écriture suivante viserait une autre ligne (ou aucune).
    if (!$exact) return ['ok' => false, 'error' => "Joueur introuvable : $username"];
    $exact = (string) $exact;

    db()->prepare("UPDATE users
                   SET greenstand_eur_bank = LEAST(greenstand_eur_bank + ?, 9999999999999.99)
                   WHERE username = ?")->execute([$lot['eur'], $exact]);

    greenstand_donner_platine($exact, (int) $lot['platine']);

    $hall = $avecHall && !empty($lot['hall']);
    if ($hall) {
        db()->prepare("UPDATE users SET greenstand_weekly_wins = greenstand_weekly_wins + 1 WHERE username = ?")
            ->execute([$exact]);
    }

    if (function_exists('log_admin_action')) {
        log_admin_action('greenstand_podium', null, $exact . ' rang ' . $rang);
    }
    error_log(sprintf('[GreenStand] Podium : %s, rang %d — %s € et %d platine%s.',
        $exact, $rang, number_format($lot['eur'], 0, ',', ' '), $lot['platine'],
        $hall ? ' + 1 Hall of Fame' : ''));

    return ['ok' => true, 'username' => $exact, 'rang' => $rang,
            'eur' => $lot['eur'], 'platine' => (int) $lot['platine'], 'hall' => $hall];
}

/**
 * Clôture du mois : on récompense les trois premiers, puis on remet les
 * compteurs de période à zéro.
 *
 * Départage : à égalité d'euros, l'ordre alphabétique tranche. Avant, TOUS les
 * joueurs à égalité au sommet recevaient le point de Hall of Fame ; avec des lots
 * différents par rang, il faut un classement sans ex aequo, sinon deux joueurs se
 * partagent la première place et le total versé dépasse ce qui est prévu.
 */
function greenstand_finalize_period(): void {
    // La période se joue en EUROS déposés, pas en jetons convertis.
    $podium = db()->query("
        SELECT username, greenstand_week_eur
        FROM users
        WHERE greenstand_week_eur > 0
        ORDER BY greenstand_week_eur DESC, username ASC
        LIMIT 3
    ")->fetchAll();

    $palmares = [];
    foreach ($podium as $i => $row) {
        $res = greenstand_verser_podium((string) $row['username'], $i + 1, true);
        if (empty($res['ok'])) continue;
        // Clé historique : les pages qui l'affichent la lisent sous ce nom.
        $res['eur_semaine'] = (float) $row['greenstand_week_eur'];
        $palmares[] = $res;
    }

    // On garde le palmarès pour l'afficher : sans ça, un joueur voit son solde
    // grimper le 1er du mois sans savoir pourquoi.
    db()->prepare("INSERT INTO greenstand_meta (name, value) VALUES ('last_podium', ?)
                   ON DUPLICATE KEY UPDATE value = VALUES(value)")
        ->execute([json_encode([
            'semaine' => date('Y-m-d'),
            'rangs'   => $palmares,
        ], JSON_UNESCAPED_UNICODE)]);

    db()->exec("UPDATE users SET greenstand_week_eur = 0, greenstand_week_dt = 0");
}

/** Ancien nom, gardé pour ne rien casser si un script l'appelle encore. */
function greenstand_finalize_week(): void {
    greenstand_finalize_period();
}

/**
 * Verser un podium à la main, depuis le panel admin.
 *
 * $ecrire = false ne touche à rien : il ne fait que dire ce qui serait versé et
 * l'état actuel des joueurs. C'est le mode par défaut du panel — payer trois
 * joueurs est irréversible, ça se regarde avant de se faire.
 */
function gs_admin_podium(array $pseudos, bool $ecrire = false, bool $hall = false): array {
    $lignes  = [];
    $erreurs = 0;

    foreach ([1, 2, 3] as $rang) {
        $pseudo = trim((string) ($pseudos[$rang] ?? ''));
        if ($pseudo === '') continue;

        $lot  = GREENSTAND_PODIUM[$rang];
        $stmt = db()->prepare("SELECT username, greenstand_eur_bank, greenstand_platine, greenstand_weekly_wins
                               FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$pseudo]);
        $avant = $stmt->fetch();

        if (!$avant) {
            $lignes[] = ['rang' => $rang, 'demande' => $pseudo, 'ok' => false,
                         'error' => 'Joueur introuvable'];
            $erreurs++;
            continue;
        }

        $donneHall = $hall && !empty($lot['hall']);
        $ligne = [
            'rang'     => $rang,
            'username' => (string) $avant['username'],
            'ok'       => true,
            'eur'      => (float) $lot['eur'],
            'platine'  => (int) $lot['platine'],
            'hall'     => $donneHall,
            'avant'    => [
                'bank'    => round((float) $avant['greenstand_eur_bank'], 2),
                'platine' => (int) $avant['greenstand_platine'],
                'wins'    => (int) $avant['greenstand_weekly_wins'],
            ],
        ];

        if ($ecrire) {
            $res = greenstand_verser_podium((string) $avant['username'], $rang, $donneHall);
            if (empty($res['ok'])) {
                $ligne['ok'] = false;
                $ligne['error'] = $res['error'] ?? 'versement refusé';
                $erreurs++;
            } else {
                $stmt->execute([$pseudo]);
                $apres = $stmt->fetch();
                $ligne['apres'] = [
                    'bank'    => round((float) $apres['greenstand_eur_bank'], 2),
                    'platine' => (int) $apres['greenstand_platine'],
                    'wins'    => (int) $apres['greenstand_weekly_wins'],
                ];
            }
        }
        $lignes[] = $ligne;
    }

    if (!$lignes) return ['ok' => false, 'error' => "Indique au moins un joueur."];
    return ['ok' => true, 'ecrit' => $ecrire, 'erreurs' => $erreurs, 'lignes' => $lignes,
            'bareme' => GREENSTAND_PODIUM];
}

/** Le dernier palmarès, pour l'afficher au-dessus des classements. */
function greenstand_dernier_podium(): ?array {
    $stmt = db()->prepare("SELECT value FROM greenstand_meta WHERE name = 'last_podium' LIMIT 1");
    $stmt->execute();
    $v = $stmt->fetchColumn();
    if (!$v) return null;
    $d = json_decode((string) $v, true);
    return is_array($d) && !empty($d['rangs']) ? $d : null;
}

/**
 * Crédite le delta d'argent gagné au stand depuis la dernière synchro, converti en
 * jetons DT et plafonné au débit maximum plausible depuis la dernière synchro réelle
 * (horodatage serveur). Retourne le détail de ce qui a effectivement été crédité.
 */
/**
 * Ancienne synchro : convertissait directement les euros en jetons.
 *
 * Elle reste pour ne rien casser si un script ou un onglet resté ouvert l'appelle
 * encore, mais elle ne crédite plus de jetons : elle dépose en banque, comme le reste.
 * C'est le Distributeur qui convertit désormais.
 */
function greenstand_sync(string $username, float $reported_eur_delta): array {
    return greenstand_deposit($username, $reported_eur_delta);
}

/**
 * Base commune aux deux classements ci-dessous : trie les joueurs par une colonne
 * numérique donnée (jamais une valeur fournie par l'utilisateur — toujours un nom de
 * colonne codé en dur dans les fonctions publiques qui appellent celle-ci) et fait
 * remonter la ligne de $highlight même s'il est hors du top affiché.
 * $extraColumns : ['clé_du_tableau_retourné' => 'nom_colonne_sql'], également codé en
 * dur par l'appelant, pour ajouter d'autres valeurs à afficher (ex. record personnel).
 */
function greenstand_board_by(string $orderColumn, string $valueKey, int $limit, ?string $highlight, array $extraColumns = []): array {
    $extraSelect = '';
    foreach ($extraColumns as $key => $col) $extraSelect .= ", {$col} AS extra_{$key}";

    $stmt = db()->prepare("
        SELECT username, avatar, steam_avatar, xp, display_name, name_style, {$orderColumn} AS board_value{$extraSelect}
        FROM users
        WHERE {$orderColumn} > 0
        ORDER BY {$orderColumn} DESC, username ASC
        LIMIT ?
    ");
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    $rows   = [];
    $rank   = 0;
    $me_row = null;
    foreach ($stmt->fetchAll() as $r) {
        $rank++;
        // Le client utilise une forme commune pour les cinq tableaux. On garde aussi
        // les anciennes clés nommées pour rester compatible avec un éventuel ancien écran.
        $boardValue = is_numeric($r['board_value']) ? (float) $r['board_value'] : 0.0;
        $entry = [
            'rank'         => $rank,
            'username'     => $r['username'],
            'avatar'       => effective_avatar(['avatar' => $r['avatar'], 'steam_avatar' => $r['steam_avatar']]),
            'level'        => xp_level((int) $r['xp'])['level'],
            'display_name' => $r['display_name'],
            'name_style'   => $r['name_style'],
            'board_value'  => $boardValue,
            'is_me'        => $highlight !== null && $r['username'] === $highlight,
            $valueKey      => $boardValue,
        ];
        foreach ($extraColumns as $key => $col) {
            $extraValue = is_numeric($r['extra_' . $key]) ? (float) $r['extra_' . $key] : 0.0;
            $entry[$key] = $extraValue;
            $entry['extra_' . $key] = $extraValue;
        }
        $rows[] = $entry;
        if ($highlight !== null && $r['username'] === $highlight) $me_row = $entry;
    }

    // Hors du top affiché : on va chercher son rang réel avec un simple COUNT, pour
    // pouvoir quand même afficher sa ligne sous le tableau (même logique que les
    // autres classements du site, ex. panoplies).
    if ($me_row === null && $highlight !== null) {
        $stmt2 = db()->prepare("
            SELECT {$orderColumn} AS board_value, avatar, steam_avatar, xp, display_name, name_style{$extraSelect}
            FROM users WHERE username = ? LIMIT 1
        ");
        $stmt2->execute([$highlight]);
        $me = $stmt2->fetch();
        if ($me && (float) $me['board_value'] > 0) {
            $count_stmt = db()->prepare("SELECT COUNT(*) c FROM users WHERE {$orderColumn} > ?");
            $count_stmt->execute([(float) $me['board_value']]);
            $boardValue = is_numeric($me['board_value']) ? (float) $me['board_value'] : 0.0;
            $me_row = [
                'rank'         => (int) $count_stmt->fetch()['c'] + 1,
                'username'     => $highlight,
                'avatar'       => effective_avatar(['avatar' => $me['avatar'], 'steam_avatar' => $me['steam_avatar']]),
                'level'        => xp_level((int) $me['xp'])['level'],
                'display_name' => $me['display_name'],
                'name_style'   => $me['name_style'],
                'board_value'  => $boardValue,
                'is_me'        => true,
                $valueKey      => $boardValue,
            ];
            foreach ($extraColumns as $key => $col) {
                $extraValue = is_numeric($me['extra_' . $key]) ? (float) $me['extra_' . $key] : 0.0;
                $me_row[$key] = $extraValue;
                $me_row['extra_' . $key] = $extraValue;
            }
        }
    }

    $total = db()->query("SELECT COUNT(*) c FROM users WHERE {$orderColumn} > 0")->fetch();
    return ['rows' => $rows, 'players' => (int) $total['c'], 'me' => $me_row];
}

/**
 * Classement mensuel des meilleurs vendeurs : euros déposés au Distributeur depuis
 * le 1er. Remis à 0 automatiquement le 1er du mois (cf. greenstand_maybe_reset_period()).
 * $highlight (le username courant) fait remonter sa ligne même hors du top.
 */
function greenstand_leaderboard_weekly(int $limit = GREENSTAND_BOARD_LIMIT, ?string $highlight = null): array {
    greenstand_maybe_reset_week();
    return greenstand_board_by('greenstand_week_eur', 'eur', $limit, $highlight);
}

/**
 * Classement "Hall of Fame" : nombre de fois où chaque joueur a terminé 1er du
 * classement ci-dessus (jamais remis à 0), accompagné de son record personnel
 * (plus gros total jamais atteint sur une seule période).
 */
function greenstand_leaderboard_wins(int $limit = GREENSTAND_BOARD_LIMIT, ?string $highlight = null): array {
    greenstand_maybe_reset_week();
    return greenstand_board_by('greenstand_weekly_wins', 'wins', $limit, $highlight, ['best_week_eur' => 'greenstand_best_week_eur']);
}


/* ======================================================================
   ANTI AUTO-CLICKER — LE CONTRÔLE QUI COMPTE
   ======================================================================
   Tout ce qui suit ne lit que deux sources : l'horloge du serveur et le
   compteur de ventes manuelles contenu dans la sauvegarde. Aucune valeur
   « de confiance » envoyée par le navigateur (durée écoulée annoncée,
   résultat du filtre JS, statistiques de clics) n'entre dans la décision.
   Un joueur qui supprime le JavaScript du jeu, rejoue la requête POST à la
   main ou édite son localStorage se heurte quand même à ce plafond.
   ====================================================================== */

/**
 * L'adresse IP du visiteur. On ne lit X-Forwarded-For que si le site est
 * déclaré derrière un proxy de confiance : sinon, n'importe qui pourrait
 * s'attribuer l'IP dispensée en ajoutant un en-tête à sa requête.
 */
function greenstand_client_ip(): string {
    if (GREENSTAND_TRUST_PROXY && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $first = trim($parts[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** Ce POSTE est-il dispensé, par son adresse ? (Le frein de cadence s'y fie aussi.) */
function greenstand_ip_dispensee(): bool {
    return in_array(greenstand_client_ip(), GREENSTAND_AUTOCLICK_EXEMPT_IPS, true);
}

/**
 * Le contrôle anti auto-clicker est-il actif sur le site ?
 *
 * Interrupteur général, rangé dans greenstand_meta et manoeuvrable depuis le
 * panel admin par les seuls comptes listés dans GREENSTAND_ANTICHEAT_OWNERS.
 * Éteint, plus rien ne filtre ni ne sanctionne : ni le filtre du navigateur, ni
 * la mesure de cadence, ni les suspensions. Le frein de cadence de l'endpoint,
 * lui, reste en place — il protège le serveur, pas le jeu.
 */
function greenstand_anticheat_actif(): bool {
    static $cache = null;
    if ($cache !== null) return $cache;
    $stmt = db()->prepare("SELECT value FROM greenstand_meta WHERE name = 'anticheat' LIMIT 1");
    $stmt->execute();
    $v = $stmt->fetchColumn();
    // Absent = actif : une protection ne doit jamais être éteinte par défaut,
    // ni par l'oubli d'une ligne en base.
    return $cache = ($v === false || $v === null || (string) $v !== '0');
}

function greenstand_anticheat_set(bool $actif): void {
    db()->prepare("INSERT INTO greenstand_meta (name, value) VALUES ('anticheat', ?)
                   ON DUPLICATE KEY UPDATE value = VALUES(value)")
        ->execute([$actif ? '1' : '0']);
    if (function_exists('log_admin_action')) {
        log_admin_action('greenstand_anticheat', null, $actif ? 'active' : 'desactive');
    }
    error_log('[GreenStand] Anti auto-clicker ' . ($actif ? 'RÉACTIVÉ' : 'DÉSACTIVÉ') . ' depuis le panel.');
}

/** Ce compte a-t-il le droit de manoeuvrer l'interrupteur ? */
function greenstand_anticheat_owner(string $username): bool {
    if (!is_admin_ip()) return false;
    foreach (GREENSTAND_ANTICHEAT_OWNERS as $nom) {
        if (strcasecmp(trim($nom), trim($username)) === 0) return true;
    }
    return false;
}

/**
 * Ce poste est-il dispensé du contrôle anti auto-clicker ?
 * Vrai si l'interrupteur général est éteint, ou si l'adresse est dispensée.
 */
function greenstand_autoclick_exempt(): bool {
    return !greenstand_anticheat_actif() || greenstand_ip_dispensee();
}

/** La ligne du registre de clics, créée à la volée à la première visite. */
function greenstand_click_row(string $username, int $lifetimeClicks = 0): array {
    $stmt = db()->prepare('SELECT * FROM greenstand_clicks WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if ($row) return $row;

    db()->prepare("
        INSERT INTO greenstand_clicks (username, last_clicks, pending_clicks, checked_at)
        VALUES (?, ?, 0, NOW())
        ON DUPLICATE KEY UPDATE username = username
    ")->execute([$username, max(0, $lifetimeClicks)]);

    $stmt->execute([$username]);
    return $stmt->fetch() ?: [
        'username' => $username, 'last_clicks' => $lifetimeClicks, 'pending_clicks' => 0,
        'checked_at' => date('Y-m-d H:i:s'), 'strikes' => 0, 'blocked_until' => null,
        'last_rate' => 0, 'flagged_at' => null,
    ];
}

/**
 * Le cœur du dispositif : appelé à chaque sauvegarde reçue.
 *
 * Compare le compteur de ventes manuelles à celui de la sauvegarde précédente,
 * rapporté au temps réellement écoulé côté serveur. Ce qui dépasse la cadence
 * humaine n'est pas mis au crédit du joueur : ces clics-là ne financeront aucun
 * dépôt. Trois mesures hors normes d'affilée et l'enveloppe « clic » est coupée
 * pendant GREENSTAND_AUTOCLICK_COOLDOWN secondes.
 */
function greenstand_click_audit(string $username, array $state): array {
    $lifetime = (int) round((float) ($state['lifetimeManualSales'] ?? 0));
    $row      = greenstand_click_row($username, $lifetime);
    $now      = time();
    $exempt   = greenstand_autoclick_exempt();

    $previous = (int) $row['last_clicks'];
    // Une franchise ou une réinitialisation fait retomber le compteur : on repart
    // simplement de la valeur basse, sans compter la chute comme des clics.
    $delta   = $lifetime > $previous ? $lifetime - $previous : 0;
    $brut    = $now - strtotime((string) $row['checked_at']);
    $elapsed = max(1, min($brut, GREENSTAND_CLICK_WINDOW_MAX));

    // PREMIÈRE MESURE D'UNE SESSION : on ne mesure rien, on se resynchronise.
    //
    // Le jeu fusionne les sauvegardes en prenant le PLUS AVANCÉ des deux compteurs
    // (gsMergeSave, côté navigateur) : c'est ce qui fait marcher le multi-appareil.
    // Conséquence : quelqu'un qui revient après avoir joué ailleurs — ou dont le
    // localStorage était en avance sur le serveur — fait bondir le compteur d'un
    // seul coup. Ce bond n'est PAS une cadence : ce sont des clics déjà faits,
    // ailleurs, étalés sur des heures. Le lire comme des clics par seconde donnait
    // une cadence fantôme, et pouvait valoir un avertissement à quelqu'un qui
    // venait simplement de se reconnecter.
    if ($brut > GREENSTAND_CLICK_WINDOW_MAX) {
        // Aucun avertissement, aucune cadence retenue. On crédite quand même de quoi
        // couvrir une coupure réseau ou un onglet mis en veille par le navigateur —
        // ce que la fenêtre entière permet à une main — sinon un joueur qui perd sa
        // connexion deux minutes perdrait aussi les clics qu'il a réellement faits.
        $plafond = GREENSTAND_HUMAN_CPS * GREENSTAND_CLICK_WINDOW_MAX;
        $rendu   = min($delta, $plafond);
        $pending = min((int) $row['pending_clicks'] + ($exempt ? $delta : $rendu),
                       GREENSTAND_HUMAN_CPS * GREENSTAND_MAX_SYNC_GAP + GREENSTAND_HUMAN_BURST);

        db()->prepare('UPDATE greenstand_clicks SET last_clicks = ?, pending_clicks = ?, checked_at = NOW(), last_rate = 0 WHERE username = ?')
            ->execute([max($lifetime, 0), $pending, $username]);

        return ['delta' => $delta, 'legit' => $rendu, 'excess' => 0, 'rate' => 0.0,
                'strikes' => (int) $row['strikes'], 'blocked' => false,
                'blocked_seconds' => 0, 'exempt' => $exempt, 'resync' => true];
    }

    $rate = $delta / $elapsed;

    $allowed = (int) floor(GREENSTAND_HUMAN_CPS * $elapsed) + GREENSTAND_HUMAN_BURST;
    $legit   = min($delta, $allowed);
    $excess  = $delta - $legit;

    $strikes      = (int) $row['strikes'];
    $blockedUntil = $row['blocked_until'] ? strtotime((string) $row['blocked_until']) : 0;
    $flaggedAt    = $row['flagged_at'];

    if ($exempt) {
        // Poste dispensé : on tient le registre à jour pour ne pas fausser la
        // mesure suivante, mais rien n'est rogné ni sanctionné.
        $legit = $delta; $excess = 0; $strikes = 0; $blockedUntil = 0;
    } elseif ($excess > 0) {
        $strikes   = min(10, $strikes + 1);
        $flaggedAt = date('Y-m-d H:i:s', $now);
        if ($strikes >= GREENSTAND_AUTOCLICK_STRIKES) {
            $blockedUntil = $now + GREENSTAND_AUTOCLICK_COOLDOWN;
        }
    } elseif ($rate < GREENSTAND_HUMAN_CPS * 0.8) {
        // Retour à une cadence normale : l'ardoise s'efface peu à peu. Un joueur
        // honnête qui a déclenché une alerte une fois n'est pas marqué à vie.
        $strikes = max(0, $strikes - 1);
    }

    $blocked = !$exempt && $blockedUntil > $now;
    // Pendant la mise en sourdine, les clics ne sont plus mis en réserve du tout.
    $pending = (int) $row['pending_clicks'] + ($blocked ? 0 : $legit);
    // La réserve ne s'accumule pas indéfiniment : de quoi couvrir une fenêtre de
    // synchro complète, pas trois heures de clics épargnés pour un méga-dépôt.
    $pending = min($pending, GREENSTAND_HUMAN_CPS * GREENSTAND_MAX_SYNC_GAP + GREENSTAND_HUMAN_BURST);

    db()->prepare("
        UPDATE greenstand_clicks
        SET last_clicks = ?, pending_clicks = ?, checked_at = NOW(),
            strikes = ?, blocked_until = ?, last_rate = ?, flagged_at = ?
        WHERE username = ?
    ")->execute([
        max($lifetime, 0), $pending, $strikes,
        $blockedUntil > $now ? date('Y-m-d H:i:s', $blockedUntil) : null,
        round($rate, 2), $flaggedAt, $username,
    ]);

    if ($excess > 0 && !$exempt) {
        error_log(sprintf('[GreenStand] Cadence de clic non humaine : %s — %d clics en %ds (%.1f/s), %d ignorés, %d avertissement(s).',
            $username, $delta, $elapsed, $rate, $excess, $strikes));
    }

    return [
        'delta'   => $delta,
        'legit'   => $legit,
        'excess'  => $excess,
        'rate'    => round($rate, 2),
        'strikes' => $strikes,
        'blocked' => $blocked,
        'blocked_seconds' => $blocked ? $blockedUntil - $now : 0,
        'exempt'  => $exempt,
    ];
}

/**
 * Consomme la réserve de clics reconnus humains : c'est elle, et elle seule, qui
 * finance l'enveloppe « ventes manuelles » du plafond de dépôt.
 */
function greenstand_click_take(string $username): int {
    $row     = greenstand_click_row($username);
    $pending = (int) $row['pending_clicks'];
    if ($pending > 0) {
        db()->prepare('UPDATE greenstand_clicks SET pending_clicks = GREATEST(0, pending_clicks - ?) WHERE username = ?')
            ->execute([$pending, $username]);
    }
    // Le petit supplément couvre les clics des dernières millisecondes, faits
    // entre la sauvegarde jointe au dépôt et le calcul du plafond.
    return $pending + GREENSTAND_HUMAN_BURST;
}

/**
 * Avertissement déclaré par le navigateur (son filtre a vu une macro).
 *
 * Se croire sur parole n'est acceptable que dans UN sens : un client peut
 * s'accuser, jamais se disculper. Un tricheur qui retire ce signal de son
 * navigateur ne gagne rien — la mesure de cadence côté serveur, elle, continue
 * de tourner et sanctionne toute seule. Ce que ça apporte : la suspension est
 * immédiate et survit à un rechargement de page, au lieu d'attendre la
 * prochaine sauvegarde.
 */
function greenstand_autoclick_strike(string $username, string $raison = ''): array {
    if (greenstand_autoclick_exempt()) return greenstand_autoclick_status($username);

    $row = greenstand_click_row($username);
    $now = time();

    $strikes = min(10, (int) $row['strikes'] + 1);
    $till    = $row['blocked_until'] ? strtotime((string) $row['blocked_until']) : 0;
    if ($strikes >= GREENSTAND_AUTOCLICK_STRIKES) $till = max($till, $now + GREENSTAND_AUTOCLICK_COOLDOWN);

    db()->prepare("
        UPDATE greenstand_clicks
        SET strikes = ?, blocked_until = ?, flagged_at = NOW(), pending_clicks = 0
        WHERE username = ?
    ")->execute([$strikes, $till > $now ? date('Y-m-d H:i:s', $till) : null, $username]);

    error_log(sprintf('[GreenStand] Avertissement signalé par le jeu : %s — %s (%d au total).',
        $username, $raison !== '' ? $raison : 'motif non précisé', $strikes));

    return greenstand_autoclick_status($username);
}

/** État lisible par l'interface : sert à expliquer au joueur ce qui se passe. */
function greenstand_autoclick_status(string $username): array {
    $row  = greenstand_click_row($username);
    $now  = time();
    $till = $row['blocked_until'] ? strtotime((string) $row['blocked_until']) : 0;
    return [
        'actif'   => greenstand_anticheat_actif(),
        'exempt'  => greenstand_autoclick_exempt(),
        'strikes' => (int) $row['strikes'],
        'blocked' => !greenstand_autoclick_exempt() && $till > $now,
        'seconds' => $till > $now ? $till - $now : 0,
        'rate'    => (float) $row['last_rate'],
        'human_cps' => GREENSTAND_HUMAN_CPS,
    ];
}

/**
 * Accepte uniquement le format de sauvegarde du jeu. Les plafonds empêchent une
 * requête anormale de remplir la base ; le gameplay demeure côté navigateur.
 */
function greenstand_normalize_save_state(mixed $raw): ?array {
    if (is_string($raw)) {
        try { $raw = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
        catch (Throwable) { return null; }
    }
    if (!is_array($raw)) return null;

    // ATTENTION — TOUTE clé de sauvegarde doit être listée ici, sans exception.
    //
    // Cette fonction ne conserve QUE les clés énumérées ci-dessous : le reste est jeté
    // silencieusement. Un marqueur de version oublié ici est donc absent de la
    // sauvegarde cloud à chaque relecture, et son mécanisme se redéclenche à l'infini.
    //
    // C'est arrivé deux fois. La première avec 'resetGen'. La seconde avec 'wipeGen' :
    // la remise à zéro générale se rejouait à CHAQUE actualisation de page, effaçant la
    // progression des joueurs en boucle et leur redonnant le succès « Première vente ».
    //
    // Si tu ajoutes un champ à gameSaveData() côté JS, ajoute-le ici dans la foulée.
    $numberKeys = [
        'money', 'totalEarned', 'manualSales', 'itemsBought', 'prestigeSeeds',
        'lifetimeManualSales', 'lifetimeItemsBought', 'lifetimeTotalEarned',
        'critCount', 'gsSyncedLifetime', 'savedAt', 'resetGen', 'wipeGen',
        // Clients servis / laissés partir au comptoir. À déclarer ICI aussi,
        // sinon la clé est jetée à chaque relecture et le compteur repart à zéro
        // (voir l'avertissement en tête de cette liste).
        'clientsServed', 'clientsLost'
    ];
    $out = [];
    foreach ($numberKeys as $key) {
        $value = $raw[$key] ?? 0;
        if (!is_numeric($value) || !is_finite((float) $value)) return null;
        $out[$key] = max(0, min((float) $value, 1000000000000000));
    }

    $levels = $raw['levels'] ?? [];
    if (!is_array($levels) || count($levels) > 32) return null;
    $out['levels'] = [];
    foreach ($levels as $level) {
        if (!is_numeric($level)) return null;
        $out['levels'][] = max(0, min((int) $level, 1000000));
    }

    // Niveaux des gérants (2e couche de progression, multiplicative). Même logique
    // de bornage que 'levels' ci-dessus, avec une limite plus basse (peu de gérants).
    $managerLevels = $raw['managerLevels'] ?? [];
    if (!is_array($managerLevels) || count($managerLevels) > 16) return null;
    $out['managerLevels'] = [];
    foreach ($managerLevels as $level) {
        if (!is_numeric($level)) return null;
        $out['managerLevels'][] = max(0, min((int) $level, 1000000));
    }

    // 128 suffisait quand il y avait douze badges. Le jeu en compte maintenant plus
    // de mille : à 129 succès débloqués, la sauvegarde entière était REFUSÉE, donc
    // plus rien n'était enregistré. La limite haute reste là pour borner la taille
    // du champ, elle n'est plus une limite de jeu.
    $achievements = $raw['unlockedAchievements'] ?? [];
    if (!is_array($achievements) || count($achievements) > 2000) return null;
    $out['unlockedAchievements'] = [];
    foreach ($achievements as $achievement) {
        if (!is_string($achievement) || strlen($achievement) > 64) return null;
        $out['unlockedAchievements'][] = $achievement;
    }
    $out['unlockedAchievements'] = array_values(array_unique($out['unlockedAchievements']));

    // Le stand que le joueur tient (voir greenstand_stands()). C'est une clé de
    // sauvegarde comme les autres : oubliée ici, elle serait jetée à chaque
    // relecture et le joueur repasserait au GreenStand à chaque rechargement.
    //
    // On ne valide QUE la forme (un id connu) : le droit de tenir ce stand, lui,
    // se vérifie au moment de calculer des gains (greenstand_stand_autorise), et
    // pas ici — cette fonction ne sait pas de quel joueur vient la sauvegarde.
    $stand = $raw['stand'] ?? 'green';
    $out['stand'] = (is_string($stand) && in_array($stand, greenstand_stand_ids(), true)) ? $stand : 'green';

    // LES NIVEAUX, COMPTOIR PAR COMPTOIR.
    //
    // 'levels' / 'managerLevels' ci-dessus restent ceux du stand TENU : tout le reste
    // du serveur (production, plafond de dépôt, niveau du stand, téléphone) continue
    // de les lire sans rien changer. 'standLevels' garde en plus, pour chacun des
    // quatre comptoirs, les niveaux qui lui appartiennent — améliorer le BrownStand
    // n'améliore plus le GreenStand.
    //
    // MIGRATION. Une sauvegarde d'avant cette séparation n'a pas de 'standLevels' :
    // on donne alors À CHAQUE STAND les niveaux communs qu'elle porte. Personne ne
    // perd quoi que ce soit — ce que le joueur avait payé, il l'a partout, comme
    // avant — et la séparation ne commence qu'à partir de là.
    $out['standLevels'] = greenstand_stand_levels_normalise($raw['standLevels'] ?? null, $out);
    return $out;
}

/**
 * Normalise la carte { stand => {levels, managerLevels} }, et la fabrique depuis les
 * niveaux communs quand elle manque (sauvegarde d'avant la séparation).
 */
function greenstand_stand_levels_normalise(mixed $brut, array $save): array {
    $ids  = greenstand_stand_ids();
    $out  = [];
    $brut = is_array($brut) ? $brut : [];
    foreach ($ids as $id) {
        $entree = is_array($brut[$id] ?? null) ? $brut[$id] : null;
        if ($entree === null) {
            // Migration : ce comptoir hérite des niveaux communs de la sauvegarde.
            $out[$id] = [
                'levels'        => array_values($save['levels'] ?? []),
                'managerLevels' => array_values($save['managerLevels'] ?? []),
            ];
            continue;
        }
        $out[$id] = [
            'levels'        => greenstand_niveaux_bornes($entree['levels'] ?? [], 32),
            'managerLevels' => greenstand_niveaux_bornes($entree['managerLevels'] ?? [], 16),
        ];
    }
    return $out;
}

/** Un tableau de niveaux, borné en longueur comme en valeur. Jamais d'échec : on nettoie. */
function greenstand_niveaux_bornes(mixed $brut, int $maxCount): array {
    if (!is_array($brut)) return [];
    $out = [];
    foreach (array_values($brut) as $i => $n) {
        if ($i >= $maxCount) break;
        $out[] = is_numeric($n) ? max(0, min((int) $n, 1000000)) : 0;
    }
    return $out;
}

/**
 * Les niveaux d'un stand donné, dans une sauvegarde.
 *
 * C'est ce que le serveur doit lire pour calculer ce qu'un joueur produit : depuis
 * la séparation, les niveaux du GreenStand ne disent plus rien de ce que le
 * BrownStand rapporte. Repli sur les niveaux communs si la carte manque encore.
 */
function greenstand_levels_du_stand(?array $save, string $standId, string $cle = 'levels'): array {
    if (!$save) return [];
    $carte = is_array($save['standLevels'] ?? null) ? $save['standLevels'] : [];
    $entree = is_array($carte[$standId] ?? null) ? $carte[$standId] : null;
    if ($entree !== null && is_array($entree[$cle] ?? null)) return $entree[$cle];
    return is_array($save[$cle] ?? null) ? $save[$cle] : [];
}

/**
 * Fusionne la carte des niveaux qui arrive du navigateur avec celle déjà en base.
 *
 * Un onglet resté ouvert sur l'ANCIENNE version de la page envoie une sauvegarde sans
 * 'standLevels' : sans cette fusion, la migration ci-dessus recopierait les niveaux du
 * comptoir tenu sur les trois autres et effacerait leur progression. Ici, un comptoir
 * absent de ce qui arrive garde ce que la base a ; seul celui que le joueur tient est
 * réécrit par ce qu'il envoie.
 */
function greenstand_fusion_stand_levels(?array $ancien, array $neuf): array {
    if (!$ancien) return $neuf;
    $carteAncienne = is_array($ancien['standLevels'] ?? null) ? $ancien['standLevels'] : [];
    $carteNeuve    = is_array($neuf['standLevels'] ?? null) ? $neuf['standLevels'] : [];
    $brutNeuf      = $carteNeuve;
    foreach (greenstand_stand_ids() as $id) {
        if (!isset($brutNeuf[$id]) && isset($carteAncienne[$id])) $carteNeuve[$id] = $carteAncienne[$id];
    }
    // Le comptoir tenu fait toujours foi : c'est celui que le joueur vient de jouer.
    $tenu = (string) ($neuf['stand'] ?? 'green');
    $carteNeuve[$tenu] = [
        'levels'        => array_values($neuf['levels'] ?? []),
        'managerLevels' => array_values($neuf['managerLevels'] ?? []),
    ];
    $neuf['standLevels'] = $carteNeuve;
    return $neuf;
}

function greenstand_load_save(string $username): ?array {
    $stmt = db()->prepare('SELECT state_json FROM greenstand_saves WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return greenstand_normalize_save_state($row['state_json']);
}

function greenstand_store_save(string $username, mixed $raw): bool {
    $state = greenstand_normalize_save_state($raw);
    if ($state === null) return false;
    // Ce qu'un vieil onglet ne dit pas sur les AUTRES comptoirs, la base le sait :
    // on garde leurs niveaux au lieu de les écraser (voir greenstand_fusion_stand_levels).
    $etaitLa = is_array($raw) ? array_key_exists('standLevels', $raw)
             : (is_string($raw) && str_contains($raw, '"standLevels"'));
    if (!$etaitLa) $state = greenstand_fusion_stand_levels(greenstand_load_save($username), $state);
    // Point de passage obligé de TOUTE sauvegarde (sauvegarde périodique, dépôt,
    // revente) : c'est donc ici qu'on mesure la cadence de clic réelle. Les clics
    // au-delà du possible humain ne rejoignent pas la réserve qui finance les
    // dépôts, et le compte est averti. Voir greenstand_click_audit().
    greenstand_click_audit($username, $state);
    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $stmt = db()->prepare("\n        INSERT INTO greenstand_saves (username, state_json, updated_at) VALUES (?, ?, NOW())\n        ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = NOW()\n    ");
    $stmt->execute([$username, $json]);
    // Un cliché de temps en temps, pour pouvoir rendre une progression perdue.
    // L'espacement est géré dans la fonction : appelée toutes les cinq secondes,
    // elle n'écrit qu'une fois toutes les dix minutes.
    greenstand_snapshot_save($username, $json);
    return true;
}

function greenstand_delete_save(string $username): void {
    // Le clic sur « réinitialiser » est le moment où l'on perd tout : on garde
    // l'état d'avant, quoi qu'il arrive, même si le dernier cliché date de neuf
    // minutes. C'est exactement le cas que l'historique doit rattraper.
    $stmt = db()->prepare('SELECT state_json FROM greenstand_saves WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $avant = $stmt->fetchColumn();
    if ($avant) greenstand_snapshot_save($username, (string) $avant, 'avant_reset');

    $stmt = db()->prepare('DELETE FROM greenstand_saves WHERE username = ?');
    $stmt->execute([$username]);
    // Le registre anti auto-clicker suit la sauvegarde : sans ça, le compteur de
    // référence resterait à l'ancien total et les clics d'après une remise à zéro
    // ne compteraient plus tant que le joueur n'aurait pas rattrapé son ancien score.
    db()->prepare('DELETE FROM greenstand_clicks WHERE username = ?')->execute([$username]);
}

/* ======================================================================
   LE BARÈME DU JEU — source unique, côté serveur
   ======================================================================
   Avant, les améliorations et les gérants étaient écrits en dur dans le
   JavaScript de greenstand-idle-3d.php. Le serveur ne pouvait donc pas
   savoir ce qu'un joueur est censé produire, et devait croire le montant
   que le navigateur lui annonçait.

   Ces tables sont maintenant définies ICI et envoyées au JS par
   json_encode() : un seul endroit à modifier pour rééquilibrer, et le
   serveur peut recalculer lui-même le revenu d'un joueur à partir des
   niveaux qu'il a stockés (voir greenstand_per_sec()).
   ====================================================================== */

// Au-delà de ce niveau, le coût cesse de suivre le multiplicateur propre à
// l'objet et passe au multiplicateur commun ci-dessous. C'est ce qui donne
// une partie longue : les vingt premiers niveaux restent rapides, les trente
// suivants se méritent.
/**
 * Génération de remise à zéro du prestige.
 *
 * Incrémenter ce nombre efface les graines de TOUS les joueurs au premier chargement
 * qui suit — chacun repart à zéro graine et zéro bonus permanent. Le reste de la
 * progression (améliorations, gérants, succès, argent) n'est pas touché.
 *
 * C'est le seul moyen honnête de rééquilibrer le prestige : impossible d'appliquer une
 * nouvelle courbe à des joueurs assis sur 1 461 graines gagnées sous l'ancienne, sans
 * que le résultat soit soit absurde, soit une punition arbitraire.
 *
 * Ne le change QUE pour une remise à zéro voulue. Il n'y a pas de retour en arrière.
 */

/**
 * Remise à zéro TOTALE du jeu.
 *
 * Incrémenter ce nombre efface, au premier chargement qui suit et une seule fois :
 * l'argent, le total gagné, les niveaux d'améliorations et de gérants, les compteurs,
 * les succès, les feuilles d'or, les boosts et les franchises.
 *
 * Ce qu'il NE touche PAS : les jetons, gemmes et clés du site. Ce sont les monnaies du
 * site, déjà converties par le joueur — les reprendre serait lui retirer quelque chose
 * qu'il a sorti du jeu.
 *
 * À ne changer que pour un vrai départ commun. Il n'y a pas de retour en arrière.
 */

/**
 * Le bonus permanent apporté par les graines de prestige.
 *
 * Avant : 1 + graines x 0,02, strictement linéaire. Comme les graines s'accumulent
 * sans fin et que le bonus fait gagner plus, donc récolter plus de graines, la boucle
 * s'emballait — x59,83 en cours de partie, et +1728% proposé au reset suivant.
 *
 * Maintenant l'exposant 0,75 casse la boucle sans casser la récompense :
 *
 *      10 graines -> x1,11        1 000 graines -> x4,56
 *     100 graines -> x1,63       10 000 graines -> x21,0
 *
 * Doubler ses graines ne double plus son bonus : il faut travailler de plus en plus
 * pour le même gain, ce qui est exactement ce qu'on attend d'un prestige.
 */
if (!defined('GREENSTAND_PRESTIGE_K'))   define('GREENSTAND_PRESTIGE_K', 0.02);
if (!defined('GREENSTAND_PRESTIGE_EXP')) define('GREENSTAND_PRESTIGE_EXP', 0.75);

function greenstand_prestige_bonus(float $seeds): float {
    if ($seeds <= 0) return 1.0;
    return 1.0 + GREENSTAND_PRESTIGE_K * pow($seeds, GREENSTAND_PRESTIGE_EXP);
}

if (!defined('GREENSTAND_SOFT_CAP'))   define('GREENSTAND_SOFT_CAP', 20);
if (!defined('GREENSTAND_LATE_MULT'))  define('GREENSTAND_LATE_MULT', 1.35);
if (!defined('GREENSTAND_MAX_LEVEL'))  define('GREENSTAND_MAX_LEVEL', 50);

function greenstand_items(): array {
    $M = GREENSTAND_MAX_LEVEL;
    return [
        // Les multiplicateurs de coût ont été relevés (bande 1,15-1,22 -> 1,19-1,26) :
        // chaque niveau se voit maintenant clairement plus cher que le précédent, au
        // lieu d'une marche à peine perceptible sur les premiers paliers.
        ['id'=>'bag',        'name'=>'Sachet mylar holo',       'desc'=>'Un sachet de plus en rayon.',                 'icon'=>'bag',        'baseCost'=>5,    'costMult'=>1.19, 'clickAdd'=>0.05, 'autoAdd'=>0,    'gainMult'=>1.08, 'maxLevel'=>$M],
        ['id'=>'scale',      'name'=>'Balance numérique',        'desc'=>'Pèse plus vite.',                             'icon'=>'scale',      'baseCost'=>20,   'costMult'=>1.21, 'clickAdd'=>0,    'autoAdd'=>0.15, 'gainMult'=>1.08, 'maxLevel'=>$M],
        ['id'=>'jar',        'name'=>'Bocal en verre',           'desc'=>'Stock mieux conservé.',                       'icon'=>'jar',        'baseCost'=>60,   'costMult'=>1.20, 'clickAdd'=>0.10, 'autoAdd'=>0,    'gainMult'=>1.08, 'maxLevel'=>$M],
        ['id'=>'grinder',    'name'=>'Grinder alu',              'desc'=>'Meilleur produit fini.',                      'icon'=>'grinder',    'baseCost'=>150,  'costMult'=>1.22, 'clickAdd'=>0,    'autoAdd'=>0.40, 'gainMult'=>1.08, 'maxLevel'=>$M],
        ['id'=>'papers',     'name'=>'Feuilles RAW',             'desc'=>'Les clients reviennent.',                     'icon'=>'papers',     'baseCost'=>400,  'costMult'=>1.23, 'clickAdd'=>0.30, 'autoAdd'=>0,    'gainMult'=>1.08, 'maxLevel'=>$M],
        ['id'=>'ashtray',    'name'=>'Coin détente + cendrier',  'desc'=>'Les clients traînent, achètent plus.',        'icon'=>'ashtray',    'baseCost'=>900,  'costMult'=>1.24, 'clickAdd'=>0,    'autoAdd'=>1.2,  'gainMult'=>1.08, 'maxLevel'=>$M],
        ['id'=>'joint',      'name'=>'Pré-rolls maison',         'desc'=>'Produit dérivé premium.',                     'icon'=>'joint',      'baseCost'=>2200, 'costMult'=>1.25, 'clickAdd'=>0.80, 'autoAdd'=>0,    'gainMult'=>1.08, 'maxLevel'=>$M],
        ['id'=>'tin',        'name'=>'Coffret de rangement',     'desc'=>'Ton stand devient une vraie boutique.',       'icon'=>'tin',        'baseCost'=>6000, 'costMult'=>1.26, 'clickAdd'=>0,    'autoAdd'=>5,    'gainMult'=>1.08, 'maxLevel'=>$M],
        ['id'=>'clock',      'name'=>'Minuteur de veille',       'desc'=>'Le stand continue de tourner plus longtemps pendant ton absence.', 'icon'=>'clock', 'emoji'=>'⏳', 'baseCost'=>800, 'costMult'=>1.8, 'clickAdd'=>0, 'autoAdd'=>0, 'offlineCapAdd'=>12240, 'gainMult'=>1, 'maxLevel'=>20],

        // « client » n'a pas de clickAdd/autoAdd : son effet passe par 'clientMult',
        // un multiplicateur (comme un gérant) qui ne s'applique QU'AU gain d'un
        // client servi au comptoir (voir servirClient() et recomputeDerived() côté
        // JS), pas aux ventes manuelles ni au revenu passif des autres objets.
        ['id'=>'client',     'name'=>'Fidélisation clientèle',   'desc'=>'Les clients servis dépensent plus à chaque passage.', 'icon'=>'client', 'baseCost'=>1200, 'costMult'=>1.24, 'clickAdd'=>0, 'autoAdd'=>0, 'clientMult'=>1.02, 'gainMult'=>1, 'maxLevel'=>$M],

        // ---- LES TROIS VARIÉTÉS ----
        // C'étaient les trois objets les plus faibles du jeu : 0,60 € pour +0,01 par
        // clic, soit une amélioration de figuration. Ce sont pourtant LES produits du
        // stand — celles qui portent son identité et ses vraies images.
        //
        // Elles deviennent la gamme haute du clic : chères, mais ce sont elles qui
        // font la valeur d'une vente à la main. Au niveau 50, les trois pèsent environ
        // 2 000 € par clic contre 720 € pour tous les autres objets réunis.
        ['id'=>'bazekush',   'name'=>'BazeKush',                 'desc'=>'La weed maison, celle qui a lancé le stand. Chaque vente rapporte nettement plus.', 'icon'=>'bazekush',   'baseCost'=>120,  'costMult'=>1.19, 'clickAdd'=>0.35, 'autoAdd'=>0, 'gainMult'=>1.08, 'maxLevel'=>$M],
        ['id'=>'blue_static','name'=>'Blue Static',              'desc'=>'Variété bleutée électrique. Rare, chère, et les clients la réclament.',            'icon'=>'blue_static','baseCost'=>600,  'costMult'=>1.21, 'clickAdd'=>0.90, 'autoAdd'=>0, 'gainMult'=>1.08, 'maxLevel'=>$M],
        ['id'=>'pinkyple',   'name'=>'Pinkyple',                 'desc'=>'La violette premium du stand. Le haut de gamme, au prix qui va avec.',             'icon'=>'pinkyple',   'baseCost'=>2500, 'costMult'=>1.23, 'clickAdd'=>2.20, 'autoAdd'=>0, 'gainMult'=>1.08, 'maxLevel'=>$M],
        ['id'=>'skiteelz',   'name'=>'Skiteelz',                 'desc'=>'Le lot doré, au-dessus de tout. Encore plus rare que la Pinkyple, encore plus cher.', 'icon'=>'skiteelz',   'baseCost'=>3200, 'costMult'=>1.25, 'clickAdd'=>2.60, 'autoAdd'=>0, 'gainMult'=>1.08, 'maxLevel'=>$M],
    ];
}

/* ======================================================================
   LES STANDS — la deuxième vie de la Franchise
   ======================================================================
   Ouvrir des franchises ne servait qu'à empiler des Feuilles d'Or et des
   bonus permanents. À partir de la 10e, ça débloque maintenant un STAND :
   un comptoir entier — son enseigne, ses produits, ses clients, ses
   visuels — entre lesquels le joueur choisit celui qu'il tient.

   Aucun n'est « le meilleur » : chacun a son profil. Le BrownStand paie au
   clic, le BeigeStand paie tout seul, le WhiteStand paie très fort mais
   voit passer beaucoup moins de monde. C'est ce qui fait qu'aucun stand
   débloqué ne devient inutile — et que le GreenStand reste un choix, pas
   un souvenir.

   CE QUE ÇA COÛTE. Un stand qui rapporte plus doit coûter plus, sinon la
   partie se termine à la 50e franchise : `costMult` renchérit TOUTES les
   améliorations et TOUS les gérants. Attention à un piège — ce coût suit le
   MEILLEUR stand débloqué, pas celui qu'on tient à l'instant. Sinon il
   suffirait de repasser au GreenStand pour tout acheter au tarif d'origine,
   puis de revenir au WhiteStand pour encaisser : les prix ne seraient qu'une
   formalité.

   LES NIVEAUX APPARTIENNENT AU COMPTOIR. Chaque stand a ses propres
   améliorations et ses propres gérants : améliorer le BrownStand n'améliore
   plus le GreenStand. C'étaient auparavant des niveaux communs aux quatre —
   un stand fraîchement débloqué arrivait donc tout équipé, et « changer de
   stand » ne changeait qu'une vitrine. Les niveaux du comptoir tenu restent
   dans 'levels' / 'managerLevels' (tout le serveur les lit là) ; ceux des
   quatre sont dans 'standLevels' (greenstand_stand_levels_normalise).
   Une sauvegarde d'avant la séparation donne ses niveaux communs à CHACUN des
   quatre comptoirs : personne ne perd ce qu'il avait payé.

   CE QUI EST DÉFINI ICI L'EST POUR TOUT LE MONDE : le navigateur reçoit
   cette liste telle quelle (GS_STANDS), et le serveur s'en sert pour
   recalculer ce qu'un stand est censé produire (greenstand_per_sec,
   greenstand_deposit_cap). Un joueur qui trafique son stand dans la page
   ne déplace donc pas son plafond de dépôt : le serveur ne retient que le
   stand que ses franchises lui donnent VRAIMENT le droit de tenir.

   POUR VOIR UN STAND AVANT DE L'AVOIR DÉBLOQUÉ (utile pendant que tu dessines
   ses images) : mets son 'franchises' à 0 ci-dessous, regarde, puis remets la
   vraie valeur. Rien d'autre à faire — le serveur relit ce fichier à chaque
   requête. Attention : tant que le seuil est à 0, il est ouvert à TOUS les
   joueurs, et leurs gains sont calculés avec les multiplicateurs de ce stand.

   LES NOMS. Les quatre PRODUITS de la boutique (les ex-variétés de weed)
   changent de nom et de description selon le comptoir : c'est la même
   amélioration, au même niveau, mais on ne vend pas de la BazeKush au
   WhiteStand. Le reste du matériel (balance, bocal, coffret…) garde son nom :
   une balance est une balance sur les quatre stands. Un objet absent de
   `objets` garde simplement ce que dit greenstand_items().

   LES VISUELS. Chaque stand a son jeu d'images, préfixé par son id :
   `brown_banner`, `brown_bag`, `brown_nug_big`, `brown_bag_t2`… Le
   GreenStand, lui, garde les noms historiques, SANS préfixe : renommer ses
   fichiers aurait cassé toutes les images déjà en place. Tant qu'une image
   manque, le stand affiche celle du GreenStand à la place — le jeu ne se
   retrouve donc jamais vide pendant que tu remplis le panel admin, image
   par image.
   ====================================================================== */

/**
 * Les stands, avec les noms d'améliorations posés depuis le panel admin.
 *
 * La liste de référence est celle de greenstand_stands_defaut(), dans le code.
 * Par-dessus viennent les noms écrits par l'admin (data/greenstand_stand_noms.json) :
 * c'est ce qui permet de corriger « le BeigeStand affiche encore BazeKush » sans
 * rouvrir un fichier PHP. Rien d'autre n'est modifiable de l'extérieur — ni les
 * seuils, ni les multiplicateurs, ni les prix : ce sont eux qui décident des gains,
 * ils restent dans le code.
 */
function greenstand_stands(bool $recharger = false): array {
    static $cache = null;
    if ($recharger) $cache = null;
    if ($cache !== null) return $cache;

    $noms = gs_stand_noms($recharger);
    $cache = [];
    foreach (greenstand_stands_defaut() as $stand) {
        $perso = $noms[$stand['id']] ?? [];
        foreach ($perso as $itemId => $champs) {
            $base = $stand['objets'][$itemId] ?? [];
            if (($champs['nom'] ?? '') !== '')  $base['nom']  = $champs['nom'];
            if (($champs['desc'] ?? '') !== '') $base['desc'] = $champs['desc'];
            if ($base) $stand['objets'][$itemId] = $base;
        }
        $cache[] = $stand;
    }
    return $cache;
}

/* ----------------------------------------------------------------------
   LES NOMS D'AMÉLIORATIONS, STAND PAR STAND — écrits depuis le panel admin

   Le fichier ne contient QUE du texte d'affichage, et seulement pour des
   couples (stand, amélioration) qui existent : un identifiant inventé est
   ignoré à la relecture. Aucun prix, aucun multiplicateur, aucune formule ne
   passe par là — rien de ce qui décide des gains n'est modifiable depuis le
   panel, et le format de sauvegarde des joueurs n'est pas concerné.
   ---------------------------------------------------------------------- */

if (!defined('GS_STAND_NOMS_FILE')) define('GS_STAND_NOMS_FILE', __DIR__ . '/data/greenstand_stand_noms.json');

/** Longueur maximale d'un nom / d'une description posés depuis le panel. */
if (!defined('GS_NOM_MAX'))  define('GS_NOM_MAX', 40);
if (!defined('GS_DESC_MAX')) define('GS_DESC_MAX', 200);

function gs_stand_noms(bool $recharger = false): array {
    static $cache = null;
    if ($recharger) $cache = null;
    if ($cache !== null) return $cache;

    $brut = is_file(GS_STAND_NOMS_FILE)
        ? json_decode((string) file_get_contents(GS_STAND_NOMS_FILE), true)
        : null;
    $cache = gs_stand_noms_normalise(is_array($brut) ? $brut : []);
    return $cache;
}

/** Ne garde que des stands connus, des améliorations connues, et du texte borné. */
function gs_stand_noms_normalise(array $brut): array {
    $standsConnus = array_column(greenstand_stands_defaut(), 'id');
    $itemsConnus  = array_column(greenstand_items(), 'id');
    $out = [];
    foreach ($brut as $standId => $objets) {
        if (!is_string($standId) || !in_array($standId, $standsConnus, true) || !is_array($objets)) continue;
        foreach ($objets as $itemId => $champs) {
            if (!is_string($itemId) || !in_array($itemId, $itemsConnus, true) || !is_array($champs)) continue;
            $nom  = trim((string) ($champs['nom'] ?? ''));
            $desc = trim((string) ($champs['desc'] ?? ''));
            if (function_exists('mb_substr')) {
                $nom  = mb_substr($nom, 0, GS_NOM_MAX);
                $desc = mb_substr($desc, 0, GS_DESC_MAX);
            } else {
                $nom  = substr($nom, 0, GS_NOM_MAX);
                $desc = substr($desc, 0, GS_DESC_MAX);
            }
            if ($nom === '' && $desc === '') continue;    // champ vidé = retour au nom d'origine
            $out[$standId][$itemId] = ['nom' => $nom, 'desc' => $desc];
        }
    }
    return $out;
}

function gs_stand_noms_ecrire(array $noms): bool {
    $dir = dirname(GS_STAND_NOMS_FILE);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
    $tmp = GS_STAND_NOMS_FILE . '.tmp';
    $ok = @file_put_contents($tmp, json_encode($noms,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
    return $ok && @rename($tmp, GS_STAND_NOMS_FILE);
}

/**
 * Le panel admin enregistre les noms d'UN stand. Les autres stands ne sont pas
 * touchés : deux onglets ouverts côte à côte ne s'écrasent pas l'un l'autre.
 */
function gs_admin_stand_noms_save(string $standId, array $objets): array {
    $standsConnus = array_column(greenstand_stands_defaut(), 'id');
    if (!in_array($standId, $standsConnus, true)) {
        return ['ok' => false, 'error' => "Stand inconnu : « " . $standId . " »."];
    }
    $tout = gs_stand_noms();
    $propre = gs_stand_noms_normalise([$standId => $objets]);
    if (isset($propre[$standId])) $tout[$standId] = $propre[$standId];
    else unset($tout[$standId]);                 // tout vidé : on retire l'entrée

    if (!gs_stand_noms_ecrire($tout)) {
        return ['ok' => false, 'error' => "Écriture de data/greenstand_stand_noms.json impossible (droits du dossier ?)."];
    }
    if (function_exists('log_admin_action')) log_admin_action('greenstand_stand_noms', null, $standId);
    return ['ok' => true, 'stand' => $standId, 'stands' => greenstand_stands(true)];
}

/** À partir de combien de franchises chaque stand s'ouvre, et ce qu'il change. */
function greenstand_stands_defaut(): array {
    return [
        [
            'id' => 'green', 'name' => 'GreenStand', 'emoji' => '🌿',
            // Pas de préfixe : ce sont les visuels d'origine, déjà en place.
            'prefix' => '', 'franchises' => 0,
            // Vide : les noms de greenstand_items() SONT ceux du GreenStand.
            'objets' => [],
            'produit' => 'de la weed', 'unite' => 'nug',
            'desc'    => "Le stand d'origine. Équilibré : ce que tu vends à la main et ce que le stand produit tout seul se valent.",
            'profil'  => 'Équilibré',
            'clickMult' => 1.00, 'autoMult' => 1.00, 'clientMult' => 1.00, 'costMult' => 1.00,
            'palette' => [
                'accent' => '#4CAF3D', 'accentDark' => '#2E5A26', 'lcd' => '#8BFF6B',
                'ciel'   => ['#EDE7D6', '#CFC9B0', '#5B7A54', '#0B1410'],
                'mur'    => ['#0A140D', '#14261A', '#0C1A11'],
                'halo'   => '120,200,90', 'auvent' => ['#2E7D32', '#EDE7D6'],
                'ampoule'=> '255,196,90',
            ],
            'varietes' => [
                ['id'=>'og',   'nom'=>'OG Maison',   'mult'=>1.00, 'poids'=>46, 'phrases'=>[
                    "Salut, tu me mets un sachet ?",
                    "Je prends ce que tu as de base.",
                    "C'est ouvert ? Parfait.",
                    "Deux, si tu peux.",
                    "T'as de la monnaie sur un gros billet ?",
                    "Je passais devant, ça sentait bon.",
                ]],
                ['id'=>'blue', 'nom'=>'Blue Static', 'mult'=>1.35, 'poids'=>28, 'phrases'=>[
                    "T'aurais de la Blue Static ?",
                    "Le même que la dernière fois, la Blue Static, s'il te plaît.",
                    "On m'a dit que ta Blue Static était nickel.",
                ]],
                ['id'=>'baze', 'nom'=>'BazeKush',    'mult'=>1.80, 'poids'=>17, 'phrases'=>[
                    "On m'a dit que ta BazeKush valait le détour.",
                    "T'aurais quelque chose de doux ? De la BazeKush, si t'as.",
                    "Mon pote m'a dit du bien de ta BazeKush.",
                ]],
                ['id'=>'zkit', 'nom'=>'Zkittlez',    'mult'=>2.40, 'poids'=>9,  'phrases'=>[
                    "C'est toi qu'on m'a recommandé pour la Zkittlez ?",
                    "Je reviens de loin pour ta Zkittlez.",
                    "Je prends ce que tu as de mieux — la Zkittlez, si elle est là.",
                ]],
            ],
        ],
        [
            'id' => 'brown', 'name' => 'BrownStand', 'emoji' => '🟤',
            'prefix' => 'brown_', 'franchises' => 10,
            // Les quatre produits, renommés pour ce comptoir. Mêmes identifiants,
            // mêmes niveaux : seul l'habillage change.
            'objets' => [
                'bazekush'    => ['nom' => 'Brune Maison', 'desc' => "Le produit maison, celui qui a lancé le comptoir. Chaque vente rapporte nettement plus."],
                'blue_static' => ['nom' => 'Caramel',      'desc' => "Plus douce, plus chère, et les clients la demandent par son nom."],
                'pinkyple'    => ['nom' => 'Velours',      'desc' => "Le haut de gamme du comptoir. Le prix va avec."],
                'skiteelz'    => ['nom' => 'Nuit Douce',   'desc' => "Ce qui se fait de mieux ici. Rare, et facturé comme tel."],
            ],
            'produit' => 'de la brune', 'unite' => 'sachet',
            'desc'    => "Le comptoir de la brune. Chaque vente à la main rapporte presque le double — mais le stand tourne moins bien sans toi.",
            'profil'  => 'Pour jouer au clic',
            'clickMult' => 1.90, 'autoMult' => 0.85, 'clientMult' => 1.15, 'costMult' => 1.50,
            'palette' => [
                'accent' => '#A9713C', 'accentDark' => '#5A3A1E', 'lcd' => '#FFC98B',
                'ciel'   => ['#EFE3D0', '#D3BE9E', '#7A5A3A', '#140E08'],
                'mur'    => ['#140D07', '#251A11', '#170F09'],
                'halo'   => '200,150,90', 'auvent' => ['#7A4A22', '#EDE0C8'],
                'ampoule'=> '255,190,120',
            ],
            'varietes' => [
                ['id'=>'maison',  'nom'=>'Brune Maison', 'mult'=>1.00, 'poids'=>44, 'phrases'=>[
                    "Salut, tu me sers comme d'habitude ?",
                    "La maison, comme toujours.",
                    "Je prends la basique.",
                    "T'es ouvert tard ce soir ?",
                ]],
                ['id'=>'caramel', 'nom'=>'Caramel',      'mult'=>1.45, 'poids'=>29, 'phrases'=>[
                    "T'aurais de la Caramel ?",
                    "On m'a dit que ta Caramel était au-dessus.",
                    "La Caramel, si elle est là.",
                ]],
                ['id'=>'velours', 'nom'=>'Velours',      'mult'=>1.95, 'poids'=>18, 'phrases'=>[
                    "Je viens pour la Velours.",
                    "La Velours, et je ne t'embête pas plus longtemps.",
                    "C'est toi qui as la Velours ?",
                ]],
                ['id'=>'nuit',    'nom'=>'Nuit Douce',   'mult'=>2.60, 'poids'=>9,  'phrases'=>[
                    "Tu as encore de la Nuit Douce ?",
                    "J'ai fait la route pour ta Nuit Douce.",
                    "Ce que tu as de mieux : la Nuit Douce.",
                ]],
            ],
        ],
        [
            'id' => 'beige', 'name' => 'BeigeStand', 'emoji' => '🟠',
            'prefix' => 'beige_', 'franchises' => 25,
            'objets' => [
                'bazekush'    => ['nom' => 'Galet Maison',  'desc' => "Le produit maison, celui qui a lancé le comptoir. Chaque vente rapporte nettement plus."],
                'blue_static' => ['nom' => 'Roche Claire',  'desc' => "Plus propre que la maison. Rare, chère, et réclamée."],
                'pinkyple'    => ['nom' => 'Beige Royale',  'desc' => "Le haut de gamme du comptoir. Le prix va avec."],
                'skiteelz'    => ['nom' => 'Cristal Beige', 'desc' => "Ce qui se fait de mieux ici. Rare, et facturé comme tel."],
            ],
            'produit' => 'du beige', 'unite' => 'galet',
            'desc'    => "Le comptoir du beige. Il tourne tout seul plus de deux fois mieux que les autres : c'est le stand à laisser travailler pendant que tu fais autre chose.",
            'profil'  => 'Pour laisser tourner',
            'clickMult' => 1.15, 'autoMult' => 2.40, 'clientMult' => 1.30, 'costMult' => 2.20,
            'palette' => [
                'accent' => '#D8C39A', 'accentDark' => '#8A7548', 'lcd' => '#FFE9B8',
                'ciel'   => ['#F4EEDD', '#DCD0B4', '#8A7C5E', '#171410'],
                'mur'    => ['#181510', '#2A2419', '#1B1712'],
                'halo'   => '220,200,150', 'auvent' => ['#A08B5E', '#F2EAD6'],
                'ampoule'=> '255,225,170',
            ],
            'varietes' => [
                ['id'=>'galet',   'nom'=>'Galet Maison',  'mult'=>1.00, 'poids'=>45, 'phrases'=>[
                    "Le galet maison, s'il te plaît.",
                    "Comme d'habitude.",
                    "Tu me mets la base.",
                    "Je repasserai demain, mets-m'en un.",
                ]],
                ['id'=>'claire',  'nom'=>'Roche Claire',  'mult'=>1.40, 'poids'=>30, 'phrases'=>[
                    "T'as de la Roche Claire ?",
                    "On m'a envoyé pour ta Roche Claire.",
                    "La Claire, si tu en as encore.",
                ]],
                ['id'=>'royale',  'nom'=>'Beige Royale',  'mult'=>1.90, 'poids'=>17, 'phrases'=>[
                    "La Royale, c'est bien chez toi ?",
                    "Je prends de la Beige Royale.",
                    "On ne parle que de ta Royale.",
                ]],
                ['id'=>'cristal', 'nom'=>'Cristal Beige', 'mult'=>2.50, 'poids'=>8,  'phrases'=>[
                    "Il te reste du Cristal ?",
                    "Je viens de loin pour ton Cristal Beige.",
                    "Ce que tu as de plus propre : le Cristal.",
                ]],
            ],
        ],
        [
            'id' => 'white', 'name' => 'WhiteStand', 'emoji' => '⚪',
            'prefix' => 'white_', 'franchises' => 50,
            'objets' => [
                'bazekush'    => ['nom' => 'Blanche Maison',  'desc' => "Le produit maison, celui qui a lancé le comptoir. Chaque vente rapporte nettement plus."],
                'blue_static' => ['nom' => 'Poudreuse',       'desc' => "Plus fine que la maison. Rare, chère, et réclamée."],
                'pinkyple'    => ['nom' => 'Neige Éternelle', 'desc' => "Le haut de gamme du comptoir. Le prix va avec."],
                'skiteelz'    => ['nom' => 'Diamant',         'desc' => "Ce qui se fait de mieux ici. Rare, et facturé comme tel."],
            ],
            'produit' => 'de la blanche', 'unite' => 'pochon',
            'desc'    => "Le comptoir de la blanche. Tout rapporte presque trois fois plus, au clic comme en automatique — mais la clientèle est rare et paie moins par passage.",
            'profil'  => 'Gros gains, peu de monde',
            'clickMult' => 2.80, 'autoMult' => 2.80, 'clientMult' => 0.55, 'costMult' => 3.20,
            'palette' => [
                'accent' => '#E3E9F2', 'accentDark' => '#6E7A8A', 'lcd' => '#DFF2FF',
                'ciel'   => ['#F2F6FA', '#D8E1EA', '#6E7A8A', '#0B0F14'],
                'mur'    => ['#0C1015', '#1B2129', '#0E1218'],
                'halo'   => '180,210,240', 'auvent' => ['#8792A3', '#F4F8FC'],
                'ampoule'=> '210,230,255',
            ],
            'varietes' => [
                ['id'=>'maison',  'nom'=>'Blanche Maison',  'mult'=>1.00, 'poids'=>42, 'phrases'=>[
                    "La maison, comme d'habitude.",
                    "Tu me mets un pochon ?",
                    "Rapide, je suis garé en double file.",
                    "Je prends la basique.",
                ]],
                ['id'=>'poudreuse','nom'=>'Poudreuse',      'mult'=>1.50, 'poids'=>29, 'phrases'=>[
                    "T'aurais de la Poudreuse ?",
                    "La Poudreuse, on m'a dit que c'était la bonne.",
                    "Je reste sur la Poudreuse.",
                ]],
                ['id'=>'neige',   'nom'=>'Neige Éternelle', 'mult'=>2.10, 'poids'=>19, 'phrases'=>[
                    "La Neige Éternelle, c'est toi ?",
                    "Je viens pour la Neige.",
                    "On m'a dit du bien de ta Neige Éternelle.",
                ]],
                ['id'=>'diamant', 'nom'=>'Diamant',         'mult'=>3.00, 'poids'=>10, 'phrases'=>[
                    "Il te reste du Diamant ?",
                    "Ce que tu as de mieux : le Diamant.",
                    "J'ai traversé la ville pour ton Diamant.",
                ]],
            ],
        ],
    ];
}

/** Les identifiants de stands connus, dans l'ordre de déblocage. */
function greenstand_stand_ids(): array {
    return array_column(greenstand_stands(), 'id');
}

/** Un stand par son id. Un id inconnu renvoie le GreenStand : jamais d'erreur. */
function greenstand_stand(string $id): array {
    foreach (greenstand_stands() as $stand) {
        if ($stand['id'] === $id) return $stand;
    }
    return greenstand_stands()[0];
}

/**
 * Le stand qu'un joueur a le DROIT de tenir, quoi qu'en dise sa sauvegarde.
 *
 * C'est la seule fonction que le serveur utilise pour calculer des gains : un
 * navigateur modifié peut écrire 'white' dans sa sauvegarde à zéro franchise,
 * il n'obtiendra jamais les multiplicateurs du WhiteStand ici.
 */
function greenstand_stand_autorise(?string $id, int $franchises): array {
    $choisi = greenstand_stand((string) $id);
    if ($franchises >= (int) $choisi['franchises']) return $choisi;
    // Pas assez de franchises pour celui-ci : on redescend au meilleur qu'il a.
    $ok = greenstand_stands()[0];
    foreach (greenstand_stands() as $stand) {
        if ($franchises >= (int) $stand['franchises']) $ok = $stand;
    }
    return $ok;
}

/**
 * Ce que coûtent les améliorations et les gérants, pour un joueur qui a ouvert
 * $franchises franchises : le tarif du MEILLEUR stand qu'il a débloqué.
 *
 * Volontairement indépendant du stand affiché — voir « CE QUE ÇA COÛTE » en tête
 * de section pour la raison.
 */
function greenstand_cout_mult(int $franchises): float {
    $mult = 1.0;
    foreach (greenstand_stands() as $stand) {
        if ($franchises >= (int) $stand['franchises']) $mult = (float) $stand['costMult'];
    }
    return $mult;
}

/** Combien de franchises ce joueur a ouvertes. Mémorisé : appelé plusieurs fois par requête. */
function greenstand_franchises_of(string $username): int {
    static $cache = [];
    if ($username === '') return 0;
    if (array_key_exists($username, $cache)) return $cache[$username];
    $stmt = db()->prepare('SELECT COALESCE(greenstand_franchises, 0) FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    return $cache[$username] = (int) $stmt->fetchColumn();
}

/** Le stand effectivement retenu pour une sauvegarde donnée, franchises comprises. */
function greenstand_stand_du_save(?array $save, string $username = ''): array {
    $id = is_string($save['stand'] ?? null) ? (string) $save['stand'] : 'green';
    return greenstand_stand_autorise($id, $username !== '' ? greenstand_franchises_of($username) : 0);
}

function greenstand_managers(): array {
    return [
        ['id'=>'cashier',    'name'=>'Gérant de caisse',      'desc'=>'Optimise chaque vente manuelle.',                  'emoji'=>"🧑\u{200d}💼", 'effect'=>'click',        'baseCost'=>5000,   'costMult'=>1.6,  'mult'=>1.12, 'maxLevel'=>50],
        ['id'=>'stocker',    'name'=>'Responsable stock',     'desc'=>'Fluidifie tout le revenu automatique.',            'emoji'=>'📋',           'effect'=>'auto',         'baseCost'=>5000,   'costMult'=>1.6,  'mult'=>1.12, 'maxLevel'=>50],
        ['id'=>'marketer',   'name'=>'Community manager',     'desc'=>'Fait connaître le stand : booste tous les gains.', 'emoji'=>'📣',           'effect'=>'global',       'baseCost'=>50000,  'costMult'=>1.9,  'mult'=>1.08, 'maxLevel'=>40],
        ['id'=>'accountant', 'name'=>'Comptable',             'desc'=>'Négocie de meilleurs prix chez les fournisseurs.', 'emoji'=>'🧮',           'effect'=>'cost',         'baseCost'=>80000,  'costMult'=>2.0,  'mult'=>0.97, 'maxLevel'=>10],
        // Ce gérant boostait le bonus de prestige. Le prestige ayant été retiré, son
        // effet est devenu global : il multiplie tous les gains, comme le community
        // manager, mais plus cher et plus fort. Les joueurs qui l'avaient gardent leurs
        // niveaux et y gagnent au change.
        ['id'=>'strategist', 'name'=>'Investisseur stratège', 'desc'=>'Réinvestit les bénéfices du stand : multiplie tous tes gains.', 'emoji'=>'📈', 'effect'=>'global', 'baseCost'=>250000, 'costMult'=>1.85, 'mult'=>1.06, 'maxLevel'=>25],
        ['id'=>'nightwatch', 'name'=>'Veilleur de nuit',      'desc'=>"Ton stand reste rentable même loin de toi : augmente le taux de gains pendant ton absence.", 'emoji'=>'🌙', 'effect'=>'offline_rate', 'baseCost'=>150000, 'costMult'=>1.7, 'mult'=>1.10, 'maxLevel'=>25],
    ];
}

/**
 * Somme des gains de tous les niveaux déjà achetés d'un objet (série géométrique) —
 * exactement la même formule que itemTotalContribution() côté JS.
 */
function greenstand_item_contribution(array $item, int $level): float {
    if ($level <= 0) return 0.0;
    $base = (float) ($item['clickAdd'] ?? 0) + (float) ($item['autoAdd'] ?? 0) + (float) ($item['offlineCapAdd'] ?? 0);
    $r = (float) ($item['gainMult'] ?? 1);
    if ($r == 1.0) return $base * $level;
    return $base * (pow($r, $level) - 1) / ($r - 1);
}

/**
 * Le revenu par seconde qu'un joueur est CENSÉ produire, calculé depuis les niveaux
 * qu'il a en base. C'est ce chiffre — et lui seul — qui plafonne un dépôt.
 *
 * Un script qui annonce des millions n'obtient donc plus que ce que son stand produit
 * vraiment. Un joueur de fin de partie, lui, n'est plus bridé par une constante ronde :
 * son plafond monte avec son stand. C'est le même calcul que recomputeDerived() en JS.
 */
function greenstand_achievement_bonus(?array $save): float {
    $ids = is_array($save['unlockedAchievements'] ?? null) ? $save['unlockedAchievements'] : [];
    if (!$ids) return 1.0;
    $bonus = 0.0;
    foreach (gs_badges() as $b) {
        if (in_array($b['id'], $ids, true)) $bonus += (float) ($b['mult'] ?? 0);
    }
    return 1.0 + $bonus;
}

/**
 * Le bonus de succès MAXIMAL possible, tous badges débloqués. Sert au plafond de
 * dépôt : on l'accorde en entier, c'est une marge en faveur du joueur, et un badge
 * ajouté par le panel admin est donc pris en compte tout seul.
 */
function greenstand_achievement_bonus_max(): float {
    $bonus = 0.0;
    foreach (gs_badges() as $b) $bonus += (float) ($b['mult'] ?? 0);
    return 1.0 + $bonus;
}

/**
 * @param string     $username     Nécessaire pour les boosts de franchise. Vide = aucun boost
 *                                 compté (le calcul reste valable, il est juste prudent).
 * @param float|null $globalBonus  Bonus de succès à appliquer. Par défaut celui que le
 *                                 joueur a réellement débloqué ; le plafond de dépôt
 *                                 passe le maximum pour rester généreux.
 */
function greenstand_per_sec(?array $save, string $username = '', ?float $globalBonus = null): float {
    if (!$save) return 0.0;
    $items    = greenstand_items();
    $managers = greenstand_managers();
    // Le stand tenu (borné par les franchises réellement ouvertes) décide AUSSI des
    // niveaux à lire : chaque comptoir a les siens depuis qu'améliorer l'un
    // n'améliore plus les autres.
    $stand    = greenstand_stand_du_save($save, $username);
    $levels   = greenstand_levels_du_stand($save, $stand['id'], 'levels');
    $mLevels  = greenstand_levels_du_stand($save, $stand['id'], 'managerLevels');

    $ps = 0.0;
    foreach ($items as $i => $item) {
        $lvl = max(0, min((int) ($levels[$i] ?? 0), (int) ($item['maxLevel'] ?? GREENSTAND_MAX_LEVEL)));
        if (($item['autoAdd'] ?? 0) > 0) $ps += greenstand_item_contribution($item, $lvl);
    }

    $mult = function(string $effect) use ($managers, $mLevels): float {
        $acc = 1.0;
        foreach ($managers as $i => $m) {
            if (($m['effect'] ?? '') !== $effect) continue;
            $lvl = max(0, min((int) ($mLevels[$i] ?? 0), (int) ($m['maxLevel'] ?? 50)));
            if ($lvl > 0) $acc *= pow((float) $m['mult'], $lvl);
        }
        return $acc;
    };

    // Le bonus des succès est LU, plus deviné : il était figé à 1.16 alors que les
    // badges (modifiables depuis le panel admin, data/greenstand_badges.json) valent
    // déjà +19% par défaut. Un chiffre en dur ici, c'est un classement « Roi du Stand »
    // qui affiche moins que le compteur du joueur, et un plafond de dépôt trop bas.
    if ($globalBonus === null) $globalBonus = greenstand_achievement_bonus($save);

    // La Serre en or est un boost de franchise : elle survit aux reventes et compte
    // dans le €/s. $username était utilisé ici sans jamais être reçu en paramètre :
    // PHP le lisait donc comme null, et AUCUN boost de franchise n'était compté.
    $boost = $username !== '' ? greenstand_boost_factor($username, 'persec') : 1.0;

    // Le stand tenu multiplie le revenu automatique (le BeigeStand produit 2,4 fois
    // plus tout seul, le BrownStand un peu moins). On prend le stand AUTORISÉ par
    // les franchises du joueur, jamais celui que sa sauvegarde annonce ($stand,
    // calculé en tête de fonction).
    return $ps * $globalBonus * $mult('auto') * $mult('global') * $boost * (float) $stand['autoMult'];
}

/**
 * Ce qu'un joueur peut déposer au maximum sur une fenêtre de $elapsed secondes.
 *
 * Le revenu automatique est plafonné par ce que son stand produit ; on y ajoute une
 * enveloppe pour les ventes à la main (plafonnée à GREENSTAND_HUMAN_CPS clics/seconde) et
 * une marge de sécurité, parce qu'un plafond trop juste vole des gains légitimes.
 */
function greenstand_deposit_cap(?array $save, int $elapsed, string $username = '', ?int $clickBudget = null): float {
    // Plafond : on prend le bonus de succès maximal, pas seulement celui déjà
    // débloqué. Un plafond trop juste refuse des gains légitimes.
    $perSec = greenstand_per_sec($save, $username, greenstand_achievement_bonus_max());

    // Enveloppe clics : valeur d'un clic x cadence humaine max.
    $items  = greenstand_items();
    // Mêmes niveaux que ceux qui produisent vraiment : ceux du comptoir tenu.
    $standCourant = greenstand_stand_du_save($save, $username);
    $levels = greenstand_levels_du_stand($save, $standCourant['id'], 'levels');
    $clickValue = 0.10;
    foreach ($items as $i => $item) {
        if (($item['clickAdd'] ?? 0) <= 0) continue;
        $lvl = max(0, min((int) ($levels[$i] ?? 0), (int) ($item['maxLevel'] ?? GREENSTAND_MAX_LEVEL)));
        $clickValue += greenstand_item_contribution($item, $lvl);
    }
    // La Souris en or augmente définitivement les gains au clic. Elle est
    // également prise en compte dans le plafond serveur, sinon les gains légitimes
    // seraient refusés après son achat.
    $clickValue *= $username !== '' ? greenstand_boost_factor($username, 'click') : 1.0;
    $clickValue *= greenstand_achievement_bonus_max();

    // Le stand tenu, encore une fois borné par les franchises réellement ouvertes.
    // On retient le plus favorable de ses deux multiplicateurs de vente (clic et
    // client servi) : un plafond trop juste refuserait des gains légitimes, alors
    // qu'un plafond un peu large ne donne rien de plus à personne — le joueur ne
    // dépose jamais que ce qu'il a vraiment gagné.
    $stand = $standCourant;
    $clickValue *= (float) $stand['clickMult'] * max(1.0, (float) $stand['clientMult']);

    // « client » ne passe pas par clickAdd (boucle ci-dessus) : son niveau
    // multiplie uniquement ce que rapporte un client servi au comptoir (voir
    // recomputeDerived()/servirClient() côté JS). Le plafond doit en tenir
    // compte lui aussi — même raison que la Souris en or juste au-dessus.
    foreach ($items as $i => $item) {
        if (($item['id'] ?? '') !== 'client') continue;
        $lvl = max(0, min((int) ($levels[$i] ?? 0), (int) ($item['maxLevel'] ?? GREENSTAND_MAX_LEVEL)));
        $clickValue *= pow($item['clientMult'] ?? 1, $lvl);
        break;
    }

    // Enveloppe « ventes manuelles ». Avant, elle valait toujours un forfait par
    // seconde écoulée : un auto-clicker calé juste sous le seuil du filtre JS
    // touchait l'enveloppe entière, et un client modifié qui annonçait
    // n'importe quel montant aussi. Désormais elle vaut EXACTEMENT les clics que le
    // serveur a lui-même comptés et jugés humains (greenstand_click_audit), et rien
    // de plus : les clics d'une macro ne financent rien.
    $clics = $clickBudget !== null
        ? max(0, $clickBudget)
        : GREENSTAND_HUMAN_CPS * $elapsed;   // appels historiques, sans registre

    // La marge x3 ne s'applique QU'AU revenu automatique. Depuis que les trois variétés
    // valent le prix d'un clic, l'enveloppe clic pèse déjà lourd : la multiplier encore
    // par trois ouvrirait un boulevard sans rien apporter à un joueur honnête.
    $parFenetre = $perSec * GREENSTAND_DEPOSIT_MARGIN * $elapsed + $clickValue * $clics;
    return max(GREENSTAND_DEPOSIT_FLOOR * $elapsed, $parFenetre);
}

/**
 * Dépôt : l'argent gagné au stand arrive dans la banque en euros du joueur.
 *
 * Il ne devient PLUS des jetons tout seul. C'est le Distributeur (greenstand_exchange)
 * qui convertit, quand le joueur le décide.
 */
function greenstand_deposit(string $username, float $reported_eur_delta): array {
    greenstand_maybe_reset_week();

    // Suspension en cours : rien n'entre en banque. Le blocage ne tient pas au seul
    // écran du joueur — retirer l'overlay dans son navigateur ne débloque rien ici.
    $suspension = greenstand_autoclick_status($username);
    if (!empty($suspension['blocked'])) {
        return ['ok' => false, 'autoclick' => $suspension,
                'error' => 'Accès au stand suspendu : auto-clic détecté. Réessaie dans '
                           . ceil($suspension['seconds'] / 60) . ' minute(s).'];
    }
    $reported_eur_delta = is_finite($reported_eur_delta) ? max(0.0, $reported_eur_delta) : 0.0;

    $stmt = db()->prepare("SELECT greenstand_last_sync, greenstand_eur_bank FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row) return ['ok' => false, 'error' => "Joueur introuvable."];

    $now     = new DateTime();
    $last    = !empty($row['greenstand_last_sync']) ? new DateTime($row['greenstand_last_sync']) : null;
    $elapsed = $last ? ($now->getTimestamp() - $last->getTimestamp()) : GREENSTAND_MAX_SYNC_GAP;
    $elapsed = max(1, min($elapsed, GREENSTAND_MAX_SYNC_GAP));

    // La réserve de clics humains est consommée ici : ce que la macro a produit en
    // trop n'y a jamais été versé, donc il ne peut pas être déposé. Un poste
    // dispensé (IP d'administration) garde l'ancienne enveloppe large.
    $exempt    = greenstand_autoclick_exempt();
    $etatClics = greenstand_autoclick_status($username);
    $budget    = $exempt ? null : greenstand_click_take($username);

    $cap       = greenstand_deposit_cap(greenstand_load_save($username), $elapsed, $username, $budget);
    $credited  = min($reported_eur_delta, $cap);

    db()->prepare("
        UPDATE users
        SET greenstand_eur_bank       = LEAST(greenstand_eur_bank + ?, 9999999999999.99),
            greenstand_eur_earned     = greenstand_eur_earned + ?,
            greenstand_best_week_eur  = GREATEST(greenstand_best_week_eur, greenstand_week_eur + ?),
            greenstand_week_eur       = greenstand_week_eur + ?,
            greenstand_last_sync      = NOW()
        WHERE username = ?
    ")->execute([$credited, $credited, $credited, $credited, $username]);

    return [
        'ok'          => true,
        'credited_eur'=> round($credited, 2),
        'capped'      => $credited < $reported_eur_delta - 0.005,
        'cap_eur'     => round($cap, 2),
        'bank_eur'    => round((float) $row['greenstand_eur_bank'] + $credited, 2),
        // Permet à l'interface de dire au joueur POURQUOI son dépôt est rogné,
        // plutôt que de le laisser croire à un bug.
        'autoclick'   => $etatClics,
    ];
}

/**
 * Le Distributeur : convertit les euros de la banque en monnaie du site.
 *
 * Les taux suivent EXACTEMENT le comptoir de change du site (6 000 jetons = 1 gemme,
 * 30 gemmes = 1 clé) : impossible de gagner quoi que ce soit en passant par un chemin
 * plutôt qu'un autre.
 *
 * Toute la transaction tient dans un verrou de ligne : deux onglets ouverts ne peuvent
 * pas dépenser deux fois les mêmes euros.
 */
function greenstand_exchange(string $username, string $to, int $qty): array {
    greenstand_maybe_reset_week();

    $suspension = greenstand_autoclick_status($username);
    if (!empty($suspension['blocked'])) {
        return ['ok' => false, 'autoclick' => $suspension,
                'error' => 'Distributeur fermé pendant la suspension. Réessaie dans '
                           . ceil($suspension['seconds'] / 60) . ' minute(s).'];
    }
    if (!isset(GREENSTAND_RATES[$to])) return ['ok' => false, 'error' => "Ce change n'existe pas."];

    // Plus aucun plafond « par échange » : GREENSTAND_EXCHANGE_QTY_CAP vaut 0, et
    // ce test ne sert plus qu'à qui voudrait en remettre un. La seule limite qui
    // reste est la place restante sur le solde de destination, vérifiée plus bas —
    // celle-là protège la base, pas le joueur.
    if (GREENSTAND_EXCHANGE_QTY_CAP > 0 && $qty > GREENSTAND_EXCHANGE_QTY_CAP) {
        return ['ok' => false, 'error' => "Maximum " . number_format(GREENSTAND_EXCHANGE_QTY_CAP, 0, ',', ' ')
            . " par échange — refais-en un autre juste après.", 'max_qty' => GREENSTAND_EXCHANGE_QTY_CAP];
    }
    $qty  = max(1, $qty);
    // L'Alambic du Distributeur (boost de franchise) baisse le coût en euros d'une
    // conversion, jusqu'à -50% au niveau maximum.
    $rate = GREENSTAND_RATES[$to] * greenstand_boost_factor($username, 'distributeur');
    $cost = $rate * $qty;
    // La banque est un DECIMAL(18,2) : au-delà, le coût ne serait de toute façon
    // jamais finançable. Ce n'est pas une limite de jeu, c'est une limite de colonne.
    if ($cost > 9999999999999999.0) return ['ok' => false, 'error' => "Quantité trop grande pour un seul échange."];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Le nom de colonne vient de CURRENCY_COLUMNS, écrit en dur dans common.php :
        // jamais d'une valeur fournie par l'utilisateur.
        $col  = CURRENCY_COLUMNS[$to];
        $stmt = $pdo->prepare("SELECT greenstand_eur_bank, {$col} AS solde_dest FROM users WHERE username = ? FOR UPDATE");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row) { $pdo->rollBack(); return ['ok' => false, 'error' => "Compte introuvable."]; }

        // Le solde de destination est un INT UNSIGNED : au-delà de 4,29 milliards il
        // déborde. On refuse proprement, en disant combien il reste de place.
        // Le plafond est LU sur la colonne (gs_currency_max) : élargie en BIGINT
        // UNSIGNED, elle ne borne plus rien en pratique ; restée en INT UNSIGNED,
        // elle refuse toujours proprement au lieu de laisser MySQL tronquer.
        $plafond = gs_currency_max($to);
        $place = $plafond - (int) $row['solde_dest'];
        if ($qty > $place) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => "Ton solde ne peut pas dépasser "
                . number_format($plafond, 0, ',', ' ') . " — il reste de la place pour "
                . number_format(max(0, $place), 0, ',', ' ') . ".", 'max_qty' => max(0, $place)];
        }

        $bank = (float) $row['greenstand_eur_bank'];
        if ($bank + 0.005 < $cost) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => "Il te faut " . number_format($cost, 2, ',', ' ') . " € pour "
                . currency_amount_text($qty, $to) . " — tu en as " . number_format($bank, 2, ',', ' ') . "."];
        }

        $pdo->prepare("UPDATE users SET greenstand_eur_bank = greenstand_eur_bank - ? WHERE username = ?")
            ->execute([$cost, $username]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('[greenstand_exchange] ' . $e->getMessage());
        return ['ok' => false, 'error' => "Erreur serveur pendant le change."];
    }

    // Le crédit passe par les points d'entrée du site : ils tiennent les compteurs
    // "à vie" sur lesquels s'appuient les succès et les classements.
    if ($to === 'jetons')      add_jetons($username, $qty);
    elseif ($to === 'gemmes')  add_gemmes($username, $qty);
    else                       add_cles($username, $qty);

    // Seuls les jetons alimentent le classement des meilleurs vendeurs : c'est la
    // monnaie que le stand produit, les deux autres n'en sont qu'une conversion.
    if ($to === 'jetons') {
        db()->prepare("
            UPDATE users
            SET greenstand_dt_earned    = greenstand_dt_earned + ?,
                greenstand_week_dt      = greenstand_week_dt + ?,
                greenstand_best_week_dt = GREATEST(greenstand_best_week_dt, greenstand_week_dt + ?)
            WHERE username = ?
        ")->execute([$qty, $qty, $qty, $username]);
    }

    $fresh = find_user_by_name($username);
    return [
        'ok'       => true,
        'gained'   => $qty,
        'to'       => $to,
        'spent_eur'=> round($cost, 2),
        'bank_eur' => round(max(0.0, $bank - $cost), 2),
        'wallet'   => user_wallet($fresh),
    ];
}

/** La banque en euros d'un joueur. */
/** Le taux de récolte hors-ligne d'un joueur, boost de franchise compris (plafonné à 100%). */
function greenstand_offline_rate(string $username): float {
    return min(1.0, 0.5 * greenstand_boost_factor($username, 'offline'));
}

function greenstand_bank(string $username): float {
    $stmt = db()->prepare("SELECT greenstand_eur_bank FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    return (float) ($stmt->fetchColumn() ?: 0);
}

/* ======================================================================
   LA FRANCHISE — le prestige, version finale
   ======================================================================
   Le joueur sacrifie l'argent du stand et le niveau de ses améliorations,
   mais garde ses jetons, gemmes et clés : ce sont des monnaies du SITE,
   pas du mini-jeu, et les avoir déjà converties était une décision.

   En échange il reçoit des FEUILLES D'OR, calculées sur les euros qu'il
   avait au moment de la revente, et qui achètent trois boosts permanents.

   La courbe est volontairement sous-linéaire (racine carrée). L'ancien
   système de graines était linéaire, donc il s'emballait : plus le bonus
   montait, plus on gagnait, donc plus on récoltait. Ici, multiplier ses
   euros par cent ne multiplie ses feuilles que par dix.
   ====================================================================== */

// Ce que coûte la PREMIÈRE franchise, en euros de caisse.
if (!defined('GREENSTAND_GOLD_SEUIL')) define('GREENSTAND_GOLD_SEUIL', 1000000);
// Chaque franchise suivante coûte ce multiple de la précédente : 1 M, 3 M, 9 M, 27 M…
// Une franchise toujours au même prix, c'est un joueur qui la rouvre en boucle dès
// qu'il repasse le million ; le prix qui monte fait que la suivante se mérite.
if (!defined('GREENSTAND_FRANCHISE_COST_MULT')) define('GREENSTAND_FRANCHISE_COST_MULT', 3.0);
// Garde-fou : au-delà, le prix ne monte plus (sinon il devient INF et plus rien
// n'est ouvrable). 1 quadrillion d'euros, personne ne l'atteindra.
if (!defined('GREENSTAND_FRANCHISE_COST_MAX')) define('GREENSTAND_FRANCHISE_COST_MAX', 1e15);

/**
 * Ce que coûte la prochaine franchise, pour un joueur qui en a déjà ouvert $franchises.
 */
function greenstand_franchise_cost(int $franchises): float {
    $franchises = max(0, $franchises);
    $cost = GREENSTAND_GOLD_SEUIL * pow(GREENSTAND_FRANCHISE_COST_MULT, $franchises);
    return is_finite($cost) ? min($cost, GREENSTAND_FRANCHISE_COST_MAX) : GREENSTAND_FRANCHISE_COST_MAX;
}

/**
 * Les Feuilles de Platine que rapporte une revente. La courbe reste une racine carrée,
 * mais RELATIVE au prix de la franchise en cours : au prix exact, 1 platine ; quatre
 * fois le prix, 2 ; neuf fois, 3. Attendre paie, sans jamais s'emballer.
 */
function greenstand_platine_for(float $eur, int $franchises): int {
    $cost = greenstand_franchise_cost($franchises);
    if ($eur < $cost) return 0;
    return max(1, (int) floor(sqrt($eur / $cost)));
}


/**
 * Les trois boosts permanents, achetables en feuilles d'or.
 *
 * Chacun agit sur un levier différent, pour qu'aucun ne rende les autres
 * inutiles : ce qu'on convertit, ce qu'on gagne absent, ce qu'on paie.
 */
function greenstand_boosts(): array {
    // Les prix doublent à chaque niveau : 1, 2, 4, 8… feuilles. Chaque achat
    // ne prend qu'un niveau, pour que le joueur choisisse précisément où investir.
    return [
        [
            'id'=>'alambic', 'name'=>'Alambic du Distributeur',
            'desc'=>'Chaque niveau rend le Distributeur plus efficace : tu obtiens plus de jetons, de gemmes et de clés pour les mêmes euros.',
            'emoji'=>'⚗️', 'effect'=>'distributeur', 'step'=>0.025, 'maxLevel'=>20, 'baseCost'=>1, 'costMult'=>2,
        ],
        [
            'id'=>'veilleuse', 'name'=>'Veilleuse permanente',
            'desc'=>"Ton stand rapporte davantage pendant que tu n'es pas là : chaque niveau relève le taux de récolte hors-ligne.",
            'emoji'=>'🌙', 'effect'=>'offline', 'step'=>0.05, 'maxLevel'=>20, 'baseCost'=>1, 'costMult'=>2,
        ],
        [
            'id'=>'comptable', 'name'=>"Comptable de l'ombre",
            'desc'=>'Négocie tes fournisseurs pour de bon : chaque niveau baisse le prix de toutes les améliorations et de tous les gérants.',
            'emoji'=>'🕴️', 'effect'=>'cost', 'step'=>0.02, 'maxLevel'=>20, 'baseCost'=>1, 'costMult'=>2,
        ],
        [
            'id'=>'souris', 'name'=>'Souris en or',
            'desc'=>'Chaque niveau augmente définitivement les gains de tes clics souris.',
            'emoji'=>'🖱️', 'effect'=>'click', 'step'=>0.05, 'maxLevel'=>20, 'baseCost'=>1, 'costMult'=>2,
        ],
        [
            'id'=>'production', 'name'=>'Serre en or',
            'desc'=>'Chaque niveau augmente définitivement tes gains automatiques en €/s.',
            'emoji'=>'🌿', 'effect'=>'persec', 'step'=>0.05, 'maxLevel'=>20, 'baseCost'=>1, 'costMult'=>2,
        ],
    ];
}
/** Le coût, en feuilles d'or, du prochain niveau d'un boost. */
function greenstand_boost_cost(array $boost, int $level): int {
    return (int) ceil($boost['baseCost'] * pow($boost['costMult'], $level));
}

/**
 * Les niveaux de boosts d'un joueur, lus en base.
 * @return array<string,int>  id du boost => niveau
 */
function greenstand_boost_levels(string $username): array {
    $stmt = db()->prepare("SELECT greenstand_boosts FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $raw = (string) ($stmt->fetchColumn() ?: '');
    $data = $raw !== '' ? json_decode($raw, true) : null;

    $out = [];
    foreach (greenstand_boosts() as $b) {
        $lvl = is_array($data) ? (int) ($data[$b['id']] ?? 0) : 0;
        $out[$b['id']] = max(0, min($lvl, (int) $b['maxLevel']));
    }
    return $out;
}

/** Le multiplicateur apporté par un boost donné, pour un joueur. */
function greenstand_boost_factor(string $username, string $effect): float {
    $levels = greenstand_boost_levels($username);
    foreach (greenstand_boosts() as $b) {
        if ($b['effect'] !== $effect) continue;
        $lvl = $levels[$b['id']] ?? 0;
        // Les remises réduisent ; les boosts de production augmentent.
        return in_array($effect, ['offline', 'click', 'persec'], true)
            ? (1 + $b['step'] * $lvl)
            : max(0.1, 1 - $b['step'] * $lvl);
    }
    return 1.0;
}

/**
 * Ouvrir une franchise : le grand reset du stand.
 *
 * Le montant sacrifié est celui que le SERVEUR a en base (la sauvegarde du
 * joueur), jamais celui que le navigateur annonce — sinon il suffirait de
 * prétendre avoir mille milliards pour récolter des feuilles.
 */
function greenstand_franchise(string $username): array {
    $save = greenstand_load_save($username);
    if (!$save) return ['ok' => false, 'error' => "Aucune partie en cours à revendre."];

    $etat  = greenstand_gold_state($username);
    $cost  = greenstand_franchise_cost((int) $etat['franchises']);
    $eur   = max(0.0, (float) ($save['money'] ?? 0));
    $gain  = greenstand_platine_for($eur, (int) $etat['franchises']);
    if ($gain < 1) {
        return ['ok' => false, 'error' => "Il te faut au moins "
            . number_format($cost, 0, ',', ' ') . " € en caisse pour ouvrir ta franchise n°"
            . ((int) $etat['franchises'] + 1) . "."];
    }

    // Le stand repart à zéro : argent, total, compteurs de session et niveaux.
    // Les compteurs "à vie", les succès et le curseur de dépôt survivent.
    $save['money']        = 0;
    $save['totalEarned']  = 0;
    $save['manualSales']  = 0;
    $save['itemsBought']  = 0;
    $save['levels']       = array_fill(0, count($save['levels'] ?? []), 0);
    $save['managerLevels']= array_fill(0, count($save['managerLevels'] ?? []), 0);
    // La revente remet à zéro LES QUATRE comptoirs, pas seulement celui qu'on tient :
    // sinon il suffirait de revendre depuis un stand vide pour garder les niveaux des
    // trois autres et encaisser les feuilles quand même.
    foreach (greenstand_stand_ids() as $sid) {
        $save['standLevels'][$sid] = ['levels' => $save['levels'], 'managerLevels' => $save['managerLevels']];
    }
    greenstand_store_save($username, $save);

    // Une Feuille d'Or par franchise : c'est un trophée, il ne se dépense pas et ne
    // dépend pas de la somme sacrifiée. Le platine, lui, récompense l'attente.
    db()->prepare("
        UPDATE users
        SET greenstand_gold          = COALESCE(greenstand_gold, 0) + 1,
            greenstand_gold_life     = COALESCE(greenstand_gold_life, 0) + 1,
            greenstand_platine       = COALESCE(greenstand_platine, 0) + ?,
            greenstand_platine_life  = COALESCE(greenstand_platine_life, 0) + ?,
            greenstand_franchises    = COALESCE(greenstand_franchises, 0) + 1
        WHERE username = ?
    ")->execute([$gain, $gain, $username]);

    return [
        'ok'      => true,
        'gain'    => $gain,          // feuilles de platine
        'gain_or' => 1,
        'spent'   => round($eur, 2),
        'state'   => greenstand_load_save($username),
        'gold'    => greenstand_gold_state($username),
    ];
}

/** Acheter un niveau de boost, payé en feuilles d'or. Verrou de ligne, comme le change. */
function greenstand_buy_boost(string $username, string $boostId): array {
    $boost = null;
    foreach (greenstand_boosts() as $b) if ($b['id'] === $boostId) $boost = $b;
    if (!$boost) return ['ok' => false, 'error' => "Ce boost n'existe pas."];

    // Hors transaction : la réserve doit refléter les feuilles réellement gagnées
    // avant qu'on décide si le joueur peut payer (cf. greenstand_reconcile_platine).
    greenstand_reconcile_platine($username);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT greenstand_platine, greenstand_boosts FROM users WHERE username = ? FOR UPDATE");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row) { $pdo->rollBack(); return ['ok' => false, 'error' => "Compte introuvable."]; }

        $data   = json_decode((string) ($row['greenstand_boosts'] ?: ''), true);
        $levels = is_array($data) ? $data : [];
        $lvl    = max(0, (int) ($levels[$boostId] ?? 0));

        if ($lvl >= (int) $boost['maxLevel']) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => "Ce boost est déjà au niveau maximum."];
        }
        $cost = greenstand_boost_cost($boost, $lvl);
        if ((int) $row['greenstand_platine'] < $cost) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => "Il te faut " . $cost . " Feuille(s) de Platine."];
        }

        $levels[$boostId] = $lvl + 1;
        $pdo->prepare("UPDATE users SET greenstand_platine = greenstand_platine - ?, greenstand_boosts = ? WHERE username = ?")
            ->execute([$cost, json_encode($levels), $username]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('[greenstand_buy_boost] ' . $e->getMessage());
        return ['ok' => false, 'error' => "Erreur serveur pendant l'achat."];
    }

    return ['ok' => true, 'gold' => greenstand_gold_state($username)];
}

/** L'état "prestige" d'un joueur : feuilles en réserve, niveaux de boosts, franchises. */
function greenstand_gold_state(string $username): array {
    // Avant de répondre, on s'assure que la réserve correspond bien à ce que le
    // joueur a gagné moins ce qu'il a dépensé : sinon une feuille visible au
    // classement resterait inutilisable dans l'onglet Franchise.
    greenstand_reconcile_platine($username);
    $stmt = db()->prepare("SELECT greenstand_gold, greenstand_gold_life, greenstand_franchises,
                                  greenstand_platine, greenstand_platine_life
                           FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch() ?: [];
    $franchises = (int) ($row['greenstand_franchises'] ?? 0);
    return [
        // Les Feuilles d'Or : le trophée du classement, jamais dépensé.
        'gold'          => (int) ($row['greenstand_gold'] ?? 0),
        'lifetime'      => (int) ($row['greenstand_gold_life'] ?? 0),
        // Les Feuilles de Platine : la monnaie des bonus permanents.
        'platine'       => (int) ($row['greenstand_platine'] ?? 0),
        'platine_life'  => (int) ($row['greenstand_platine_life'] ?? 0),
        'franchises'    => $franchises,
        // Le prix de la prochaine franchise est calculé ICI : le navigateur l'affiche
        // sans avoir à rejouer la formule, donc les deux ne peuvent pas diverger.
        'next_cost'     => greenstand_franchise_cost($franchises),
        'levels'        => greenstand_boost_levels($username),
    ];
}

/**
 * Mémorise le meilleur €/s atteint : c'est la colonne du classement « Roi du Stand ».
 * Recalculé côté serveur depuis la sauvegarde, jamais pris du navigateur.
 */
function greenstand_touch_persec(string $username, ?array $save = null): void {
    $perSec = greenstand_per_sec($save ?? greenstand_load_save($username), $username);
    if ($perSec <= 0) return;
    db()->prepare("UPDATE users SET greenstand_best_persec = GREATEST(greenstand_best_persec, ?) WHERE username = ?")
        ->execute([$perSec, $username]);
}

// ---- Les trois classements ----
function greenstand_board_production(int $limit = GREENSTAND_BOARD_LIMIT, ?string $me = null): array {
    return greenstand_board_by('greenstand_best_persec', 'persec', $limit, $me);
}
function greenstand_board_parrain(int $limit = GREENSTAND_BOARD_LIMIT, ?string $me = null): array {
    return greenstand_board_by('greenstand_gold_life', 'gold', $limit, $me,
        ['franchises' => 'greenstand_franchises']);
}
function greenstand_board_investisseur(int $limit = GREENSTAND_BOARD_LIMIT, ?string $me = null): array {
    return greenstand_board_by('jetons', 'jetons', $limit, $me, ['gemmes' => 'gemmes']);
}

/* ======================================================================
   PANEL ADMIN — envoyer des Feuilles d'Or
   ======================================================================
   Le filet de sécurité quand un compte n'a pas reçu ce qu'il devait :
   l'admin voit les compteurs réels du joueur et corrige lui-même, sans
   passer par la base.
   ====================================================================== */

// Un envoi ne peut pas dépasser ça, dans un sens comme dans l'autre : une faute de
// frappe sur le pavé numérique ne doit pas créer un million de feuilles.
if (!defined('GREENSTAND_GOLD_GRANT_MAX')) define('GREENSTAND_GOLD_GRANT_MAX', 100000);

/**
 * Les joueurs, avec leurs compteurs de feuilles, pour le sélecteur du panel admin.
 * $q filtre sur le pseudo ; vide, on renvoie ceux qui ont déjà joué au stand.
 *
 * @return array<int,array<string,mixed>>
 */
function gs_admin_players(string $q = '', int $limit = 40): array {
    $limit = max(1, min($limit, 200));
    $q = trim($q);
    if ($q !== '') {
        $stmt = db()->prepare("
            SELECT username, display_name, greenstand_gold, greenstand_gold_life,
                   greenstand_platine, greenstand_platine_life,
                   greenstand_franchises, greenstand_boosts
            FROM users WHERE username LIKE ? OR display_name LIKE ?
            ORDER BY username ASC LIMIT ?
        ");
        $motif = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $stmt->bindValue(1, $motif);
        $stmt->bindValue(2, $motif);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    } else {
        // Sans recherche : ceux qui ont une partie en cours ou des feuilles, les plus
        // actifs d'abord. Lister toute la table n'aiderait personne.
        $stmt = db()->prepare("
            SELECT u.username, u.display_name, u.greenstand_gold, u.greenstand_gold_life,
                   u.greenstand_platine, u.greenstand_platine_life,
                   u.greenstand_franchises, u.greenstand_boosts
            FROM users u
            LEFT JOIN greenstand_saves s ON s.username = u.username
            WHERE s.username IS NOT NULL OR COALESCE(u.greenstand_gold_life, 0) > 0
            ORDER BY COALESCE(u.greenstand_gold_life, 0) DESC, s.updated_at DESC, u.username ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    }
    $stmt->execute();

    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $data = json_decode((string) ($r['greenstand_boosts'] ?? ''), true);
        $out[] = [
            'username'   => $r['username'],
            'display'    => $r['display_name'] ?: $r['username'],
            'gold'         => (int) $r['greenstand_gold'],
            'lifetime'     => (int) $r['greenstand_gold_life'],
            'platine'      => (int) $r['greenstand_platine'],
            'platine_life' => (int) $r['greenstand_platine_life'],
            'franchises'   => (int) $r['greenstand_franchises'],
            'spent'        => greenstand_gold_spent(is_array($data) ? $data : []),
        ];
    }
    return $out;
}

/**
 * Créditer (ou retirer) des Feuilles d'Or à un joueur.
 *
 * La réserve ET le total à vie bougent ensemble, pour que l'onglet Franchise et le
 * classement « Parrain de la Weed » racontent la même histoire : le total à vie est
 * recalculé comme réserve + feuilles déjà dépensées en boosts, c'est-à-dire
 * l'invariant de greenstand_reconcile_gold(). Un montant négatif retire, sans jamais
 * descendre sous zéro ni effacer les boosts déjà achetés.
 */
function gs_admin_gold_grant(string $username, int $amount, string $devise = 'platine'): array {
    $username = trim($username);
    $devise   = $devise === 'or' ? 'or' : 'platine';
    if ($username === '') return ['ok' => false, 'error' => "Choisis un joueur."];
    if ($amount === 0)    return ['ok' => false, 'error' => "Indique un nombre de feuilles."];
    if (abs($amount) > GREENSTAND_GOLD_GRANT_MAX) {
        return ['ok' => false, 'error' => "Au maximum " . GREENSTAND_GOLD_GRANT_MAX . " feuilles à la fois."];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT username, greenstand_gold, greenstand_gold_life,
                                      greenstand_platine, greenstand_platine_life, greenstand_boosts
                               FROM users WHERE username = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row) { $pdo->rollBack(); return ['ok' => false, 'error' => "Joueur introuvable : " . $username]; }

        // On repart du pseudo exact de la base : la comparaison SQL ignore la casse,
        // et écrire ensuite avec la graphie tapée par l'admin viserait une autre ligne.
        $exact = (string) $row['username'];

        if ($devise === 'or') {
            // Le trophée ne se dépense pas : réserve et total à vie sont le même nombre.
            $avant = (int) $row['greenstand_gold'];
            $apres = max(0, $avant + $amount);
            $life  = $apres;
            $pdo->prepare("UPDATE users SET greenstand_gold = ?, greenstand_gold_life = ? WHERE username = ?")
                ->execute([$apres, $life, $exact]);
        } else {
            // Le platine se dépense : le total gagné reste réserve + déjà dépensé, sinon
            // la réparation automatique reprendrait ce qu'on vient de donner.
            $data  = json_decode((string) ($row['greenstand_boosts'] ?? ''), true);
            $spent = greenstand_gold_spent(is_array($data) ? $data : []);
            $avant = (int) $row['greenstand_platine'];
            $apres = max(0, $avant + $amount);
            $life  = $apres + $spent;
            $pdo->prepare("UPDATE users SET greenstand_platine = ?, greenstand_platine_life = ? WHERE username = ?")
                ->execute([$apres, $life, $exact]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[gs_admin_gold_grant] ' . $e->getMessage());
        return ['ok' => false, 'error' => "Erreur serveur pendant l'envoi."];
    }

    if (function_exists('log_admin_action')) log_admin_action('greenstand_gold_grant', null, $exact . ' ' . $devise . ' ' . $amount);
    error_log('[GreenStand] Feuilles (' . $devise . ') envoyées à ' . $exact . ' : ' . $avant . ' -> ' . $apres
        . ' (total à vie ' . $life . ').');

    return [
        'ok'       => true,
        'username' => $exact,
        'devise'   => $devise,
        'avant'    => $avant,
        'apres'    => $apres,
        'lifetime' => $life,
        'players'  => gs_admin_players(),
    ];
}

/* ======================================================================
   PANEL ADMIN — les visuels du jeu
   ======================================================================
   Ajouter, remplacer ou supprimer les images des améliorations, des
   gérants et des boosts de franchise, sans toucher au code.

   Un envoi de fichier est la surface la plus dangereuse d'un site. Trois
   verrous, dans cet ordre :

     1. le NOM est choisi par le serveur, pas par l'envoyeur : il doit
        figurer dans la liste des visuels que le jeu connaît. Impossible
        d'écrire « ../../config.php » ou « truc.php ».
     2. le CONTENU est vérifié par getimagesize(), pas par l'extension ni
        par le type annoncé par le navigateur — les deux se falsifient.
     3. l'extension écrite est TOUJOURS .webp, quel que soit l'envoi.
   ====================================================================== */

if (!defined('GS_ASSET_DIR'))      define('GS_ASSET_DIR', __DIR__ . '/assets/greenstand');
if (!defined('GS_ASSET_MAX_SIZE')) define('GS_ASSET_MAX_SIZE', 3 * 1024 * 1024);   // 3 Mo

/**
 * Les visuels d'UN stand : son décor, ses objets, et les paliers de chacun.
 *
 * Le GreenStand a un préfixe vide — ce sont les noms historiques (`banner`,
 * `bag`, `bag_t2`…), qu'on ne renomme pas : les images déjà en place le sont
 * sous ces noms-là. Les stands suivants préfixent tout (`brown_banner`,
 * `brown_bag`…), ce qui les range naturellement et évite toute collision.
 */
function gs_stand_asset_names(array $stand): array {
    $p    = (string) $stand['prefix'];
    $noms = [$p . 'banner', $p . 'client', $p . 'nug_big'];
    foreach (greenstand_items() as $it) {
        // La clé d'image d'un objet, telle que le JS la résout (ICON_ASSET).
        $map = ['papers' => 'raw_papers', 'ashtray' => 'ashtray_joint', 'tin' => 'tin_nugs', 'bazekush' => 'nug_big'];
        $base   = $p . ($map[$it['icon']] ?? $it['icon']);
        $noms[] = $base;
        // Les paliers visuels : le modèle change aux niveaux 10, 25 et 50. Aucune
        // n'est obligatoire — le jeu descend au palier dont l'image existe.
        foreach (['t2', 't3', 't4'] as $palier) $noms[] = $base . '_' . $palier;
    }
    return array_values(array_unique(array_filter($noms)));
}

/** Les visuels que le jeu sait afficher : le nom d'un envoi doit être dans cette liste. */
function gs_admin_asset_names(): array {
    $noms = [];
    // Un jeu d'images complet par stand (GreenStand en tête, sans préfixe).
    foreach (greenstand_stands() as $stand) {
        foreach (gs_stand_asset_names($stand) as $n) $noms[] = $n;
    }
    // Ceux-là ne dépendent pas du stand : ils sont les mêmes partout.
    // Les têtes du répertoire : le jeu en dessine une par défaut, mais l'admin
    // peut poser une vraie photo à la place (droits d'image : les siennes, ou
    // libres de droits — un résultat de recherche n'est pas une licence).
    foreach (greenstand_tel_contacts() as $c) $noms[] = 'contact_' . $c['id'];
    foreach (greenstand_boosts() as $b)   $noms[] = 'boost_' . $b['id'];
    foreach (greenstand_managers() as $m) $noms[] = 'manager_' . $m['id'];
    foreach (gs_badges_mains() as $b)     $noms[] = 'badge_' . $b['id'];
    return array_values(array_unique(array_filter($noms)));
}

/**
 * À quel stand appartient un visuel — c'est ce qui donne au panel admin un
 * onglet par stand au lieu d'une seule grille de 160 vignettes.
 *
 * Les visuels communs (badges, gérants, bonus de franchise) sont rattachés au
 * GreenStand : ils s'affichent partout dans le jeu, quel que soit le comptoir.
 */
function gs_admin_asset_stand(string $nom): string {
    foreach (greenstand_stands() as $stand) {
        $p = (string) $stand['prefix'];
        if ($p !== '' && str_starts_with($nom, $p)) return $stand['id'];
    }
    return 'green';
}

/** L'inventaire des visuels : ceux qui existent, ceux qui manquent, leur poids. */
/** La catégorie d'un visuel, pour ranger le panel admin au lieu d'aligner 37 vignettes. */
function gs_admin_asset_category(string $nom): string {
    // On raisonne sur le nom SANS son préfixe de stand : `brown_banner` est du
    // décor exactement comme `banner`, et `brown_bag_t2` est un palier.
    $stand = greenstand_stand(gs_admin_asset_stand($nom));
    $court = (string) $stand['prefix'] !== '' ? substr($nom, strlen((string) $stand['prefix'])) : $nom;

    // Les paliers ont leur propre rayon : sinon ils noient les modèles de base
    // sous trois fois plus de vignettes.
    if (preg_match('/_t[234]$/', $court))    return 'Paliers · niveaux 10, 25 et 50';
    if (str_starts_with($court, 'contact_')) return 'Téléphone · le répertoire';
    if (str_starts_with($court, 'badge_'))   return 'Badges';
    if (str_starts_with($court, 'boost_'))   return 'Franchise';
    if (str_starts_with($court, 'manager_')) return 'Gérants';
    if (in_array($court, ['banner', 'client'], true)) return 'Décor';
    return 'Améliorations';
}

function gs_admin_list_assets(): array {
    $out = [];
    foreach (gs_admin_asset_names() as $nom) {
        $f = GS_ASSET_DIR . '/' . $nom . '.webp';
        $existe = is_file($f);
        $out[] = [
            'name'    => $nom,
            'cat'     => gs_admin_asset_category($nom),
            // Le stand auquel cette vignette appartient : le panel admin s'en sert
            // pour n'afficher, dans l'onglet d'un stand, que SES images.
            'stand'   => gs_admin_asset_stand($nom),
            'exists'  => $existe,
            'url'     => $existe ? ('assets/greenstand/' . $nom . '.webp?v=' . (int) @filemtime($f)) : null,
            'size'    => $existe ? (int) @filesize($f) : 0,
            'updated' => $existe ? date('c', (int) @filemtime($f)) : null,
        ];
    }
    return $out;
}

/** Remplace (ou crée) le visuel $name à partir d'un fichier envoyé. */
function gs_admin_upload_asset(string $name, $file): array {
    if (!in_array($name, gs_admin_asset_names(), true)) {
        return ['ok' => false, 'error' => "Visuel inconnu : « " . $name . " »."];
    }
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => "Aucun fichier reçu (ou envoi interrompu)."];
    }
    if (($file['size'] ?? 0) > GS_ASSET_MAX_SIZE) {
        return ['ok' => false, 'error' => "Image trop lourde : 3 Mo maximum."];
    }
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['ok' => false, 'error' => "Envoi invalide."];
    }

    // On regarde le CONTENU. L'extension et le type annoncé par le navigateur se
    // falsifient tous les deux ; les octets d'en-tête, non.
    $info = @getimagesize($file['tmp_name']);
    $ok   = [IMAGETYPE_WEBP, IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF];
    if (!$info || !in_array($info[2] ?? 0, $ok, true)) {
        return ['ok' => false, 'error' => "Ce fichier n'est pas une image (webp, png, jpg ou gif)."];
    }

    if (!is_dir(GS_ASSET_DIR) && !@mkdir(GS_ASSET_DIR, 0755, true)) {
        return ['ok' => false, 'error' => "Dossier assets/greenstand/ introuvable et impossible à créer."];
    }

    $dest = GS_ASSET_DIR . '/' . $name . '.webp';
    // Conversion en WebP quand PHP sait le faire : le jeu ne sert que du .webp, et
    // renommer un PNG en .webp donnerait une image que certains navigateurs refusent.
    $converti = false;
    if (function_exists('imagewebp') && ($info[2] ?? 0) !== IMAGETYPE_WEBP) {
        $src = match ($info[2]) {
            IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
            IMAGETYPE_GIF  => @imagecreatefromgif($file['tmp_name']),
            default        => null,
        };
        if ($src) {
            imagepalettetotruecolor($src);
            imagealphablending($src, false);
            imagesavealpha($src, true);
            $converti = @imagewebp($src, $dest, 88);
            imagedestroy($src);
        }
    }
    if (!$converti && !@move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => "Écriture impossible dans assets/greenstand/ (droits du dossier ?)."];
    }

    if (function_exists('log_admin_action')) log_admin_action('greenstand_asset_upload', null, $name);
    clearstatcache(true, $dest);
    return ['ok' => true, 'name' => $name,
            'url' => 'assets/greenstand/' . $name . '.webp?v=' . (int) @filemtime($dest),
            'assets' => gs_admin_list_assets()];
}

/** Supprime le visuel $name. Le jeu retombe alors sur son pictogramme maison. */
function gs_admin_delete_asset(string $name): array {
    if (!in_array($name, gs_admin_asset_names(), true)) {
        return ['ok' => false, 'error' => "Visuel inconnu : « " . $name . " »."];
    }
    $f = GS_ASSET_DIR . '/' . $name . '.webp';
    if (is_file($f) && !@unlink($f)) {
        return ['ok' => false, 'error' => "Suppression impossible (droits du fichier ?)."];
    }
    if (function_exists('log_admin_action')) log_admin_action('greenstand_asset_delete', null, $name);
    return ['ok' => true, 'name' => $name, 'assets' => gs_admin_list_assets()];
}

/* ======================================================================
   LA BOÎTE DU SUJET — où se trouve vraiment le produit dans son image
   ======================================================================
   Une image de stand, c'est un produit détouré sur un fond transparent. Rien
   ne garantit que le produit remplisse sa toile : les visuels d'origine sont
   des découpes serrées (le sachet occupe 98 % de la hauteur), ceux générés
   pour les nouveaux stands sont des carrés 1536x1024 où l'objet flotte au
   milieu, avec parfois 16 % de vide en dessous.

   Le jeu, lui, posait le PLAN entier sur le comptoir et lui donnait la
   hauteur voulue. Résultat sur un visuel à marges : le produit paraissait
   deux fois trop petit, trop large, et surtout il flottait au-dessus du
   comptoir — le vide sous lui faisait office de pied invisible.

   On mesure donc une fois pour toutes, ici, la boîte des pixels réellement
   visibles de chaque image, en fractions de sa toile (0 à 1). Le navigateur
   s'en sert pour donner la bonne taille au SUJET et le poser sur le
   comptoir, quelle que soit la toile autour. C'est ce qui permet d'envoyer
   n'importe quelle image depuis le panel admin sans avoir à la recadrer à la
   main : le jeu la mesure et s'adapte.

   Le calcul (lire les pixels un par un) coûte ~30 ms par image : bien trop
   pour le faire à chaque page. Il est donc gardé dans data/greenstand_box.json,
   avec la date du fichier — une image remplacée est remesurée toute seule, les
   autres sont relues telles quelles.
   ====================================================================== */

if (!defined('GS_BOX_FILE')) define('GS_BOX_FILE', __DIR__ . '/data/greenstand_box.json');

/**
 * La boîte des pixels visibles d'une image, en fractions de sa toile :
 * [x0, y0, x1, y1] avec 0,0 en haut à gauche. Renvoie null si l'image est
 * illisible ou entièrement transparente.
 *
 * On réduit l'image à 160 px avant de la scanner : la boîte n'a besoin que
 * d'être juste au pour-cent près, et scanner 1,5 million de pixels pour ça
 * serait absurde.
 */
function gs_box_mesurer(string $fichier, int $seuilAlpha = 32): ?array {
    $img = @imagecreatefromwebp($fichier);
    if (!$img) return null;
    $W = imagesx($img); $H = imagesy($img);
    $echelle = min(1.0, 160 / max($W, $H));
    $w = max(1, (int) round($W * $echelle));
    $h = max(1, (int) round($H * $echelle));
    $petit = ($w === $W && $h === $H) ? $img : @imagescale($img, $w, $h, IMG_NEAREST_NEIGHBOUR);
    if ($petit !== $img) imagedestroy($img);
    if (!$petit) return null;

    $x0 = $w; $y0 = $h; $x1 = -1; $y1 = -1;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            // GD range la transparence de 0 (opaque) à 127 (invisible).
            $alpha = 127 - ((imagecolorat($petit, $x, $y) >> 24) & 0x7F);
            if ($alpha * 2 < $seuilAlpha) continue;
            if ($x < $x0) $x0 = $x;
            if ($x > $x1) $x1 = $x;
            if ($y < $y0) $y0 = $y;
            if ($y > $y1) $y1 = $y;
        }
    }
    imagedestroy($petit);
    if ($x1 < 0) return null;
    return [round($x0 / $w, 4), round($y0 / $h, 4), round(($x1 + 1) / $w, 4), round(($y1 + 1) / $h, 4)];
}

/**
 * Les boîtes de TOUS les visuels, mesurées une fois et gardées en cache.
 *
 * Le cache est indexé par nom, avec la date de modification du fichier :
 * remplacer une image depuis le panel admin la fait remesurer au prochain
 * chargement, sans toucher aux trente-six autres. Si le dossier data/ n'est
 * pas inscriptible, tout continue de marcher — c'est juste recalculé à
 * chaque fois (et le jeu, lui, ne voit pas la différence).
 */
function gs_asset_boxes(): array {
    static $boxes = null;
    if ($boxes !== null) return $boxes;

    $cache = is_file(GS_BOX_FILE) ? json_decode((string) file_get_contents(GS_BOX_FILE), true) : null;
    if (!is_array($cache)) $cache = [];

    $boxes = [];
    $neuf  = false;
    foreach (glob(GS_ASSET_DIR . '/*.webp') ?: [] as $fichier) {
        $nom = basename($fichier, '.webp');
        $mtime = (int) @filemtime($fichier);
        $vieux = $cache[$nom] ?? null;
        if (is_array($vieux) && ($vieux['t'] ?? -1) === $mtime) {
            $boxes[$nom] = $vieux['b'];
            continue;
        }
        $box = gs_box_mesurer($fichier);
        $boxes[$nom] = $box;
        $cache[$nom] = ['t' => $mtime, 'b' => $box];
        $neuf = true;
    }
    // Les images supprimées sortent du cache, sinon il enfle indéfiniment.
    foreach (array_keys($cache) as $nom) {
        if (!array_key_exists($nom, $boxes)) { unset($cache[$nom]); $neuf = true; }
    }
    if ($neuf) gs_boxes_ecrire($cache);
    return $boxes;
}

function gs_boxes_ecrire(array $cache): bool {
    $dir = dirname(GS_BOX_FILE);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
    $tmp = GS_BOX_FILE . '.tmp';
    $ok = @file_put_contents($tmp, json_encode($cache, JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
    return $ok && @rename($tmp, GS_BOX_FILE);
}

/* ======================================================================
   LA BOUTIQUE
   ======================================================================
   Jusqu'ici, l'argent du stand n'avait qu'une sortie : le Distributeur, qui le
   convertit en monnaie du site. La boutique en est la seconde — la seule qui
   dépense les euros À L'INTÉRIEUR du jeu.

   CE QU'ON PEUT VENDRE, ET CE QU'ON NE PEUT PAS. Le serveur plafonne les dépôts
   d'après ce qu'il sait du stand d'un joueur (greenstand_deposit_cap). Vendre
   « x2 sur tous les gains pendant 10 minutes » ferait donc produire au navigateur
   un argent que le serveur refuserait ensuite : le joueur verrait ses gains
   disparaître sans comprendre. C'est exactement la raison pour laquelle les
   ÉVÉNEMENTS ne touchent qu'à trois choses — le rythme des clients, le prix des
   améliorations, le temps d'ouverture — et la boutique s'en tient à la même règle.

   Une seule exception, et elle est sûre : ce que le SERVEUR verse lui-même en
   banque ne passe pas par le plafond (c'est déjà comme ça que les lots du podium
   arrivent). D'où la caisse du fournisseur, dont le gain est tiré et crédité ici.

   LES PRIX suivent la production du joueur, comme les objectifs du jour : un prix
   fixe serait infranchissable au début et gratuit à la fin. Un article coûte donc
   « tant de secondes de production », avec un plancher pour les tout petits stands.
   ====================================================================== */

if (!defined('GREENSTAND_BOUTIQUE_PLANCHER')) define('GREENSTAND_BOUTIQUE_PLANCHER', 200.0);

/**
 * Le catalogue. `secondes` est le prix, exprimé en secondes de production du
 * joueur ; `limite` est le nombre d'achats par jour.
 *
 * `effet` désigne l'un des quatre leviers que le navigateur sait appliquer (voir
 * gsEffets côté JS) : arrivees, patience, cout, ferme. Rien d'autre n'est
 * acceptable ici — un effet inventé serait ignoré par la page, et surtout il
 * sortirait du domaine que le plafond de dépôt tolère.
 */
function greenstand_boutique(): array {
    return [
        [
            'id' => 'pointe', 'nom' => 'Heure de pointe', 'emoji' => '🔥',
            'desc' => "Le bouche-à-oreille s'emballe : trois fois plus de clients au comptoir pendant 5 minutes.",
            'effet' => 'arrivees', 'mult' => 3.0, 'duree' => 300,
            'secondes' => 240, 'limite' => 3,
        ],
        [
            'id' => 'cafe', 'nom' => 'Café pour tout le monde', 'emoji' => '☕',
            'desc' => "Les clients patientent deux fois plus longtemps avant de repartir. 10 minutes.",
            'effet' => 'patience', 'mult' => 2.0, 'duree' => 600,
            'secondes' => 180, 'limite' => 3,
        ],
        [
            'id' => 'lot', 'nom' => 'Lot fournisseur', 'emoji' => '📦',
            'desc' => "Un arrivage négocié : 30 % de remise sur toutes les améliorations et tous les gérants pendant 10 minutes.",
            'effet' => 'cout', 'mult' => 0.7, 'duree' => 600,
            'secondes' => 300, 'limite' => 2,
        ],
        [
            'id' => 'videur', 'nom' => 'Videur à l\'entrée', 'emoji' => '🛡️',
            'desc' => "Personne ne s'impatiente ni ne repart fâché pendant 15 minutes.",
            'effet' => 'patience', 'mult' => 6.0, 'duree' => 900,
            'secondes' => 420, 'limite' => 2,
        ],
        [
            'id' => 'potdevin', 'nom' => 'Pot-de-vin', 'emoji' => '🔓',
            'desc' => "Ton stand est fermé ? Deux billets bien placés, et le rideau se relève tout de suite.",
            'effet' => 'reouvrir', 'secondes' => 900, 'limite' => 3,
        ],
        [
            'id' => 'carnet', 'nom' => "Carnet d'adresses", 'emoji' => '📇',
            'desc' => "Trois livraisons de plus au téléphone aujourd'hui. Le carnet se referme à minuit.",
            'effet' => 'quota_tel', 'valeur' => 3, 'secondes' => 1500, 'limite' => 2,
        ],
        [
            'id' => 'caisse', 'nom' => 'Caisse du fournisseur', 'emoji' => '🎁',
            'desc' => "Une caisse scellée. Tu paies, tu ouvres, tu vois. Les chances sont affichées et le tirage se fait sur le serveur.",
            'effet' => 'caisse', 'secondes' => 600, 'limite' => 10,
        ],
    ];
}

/**
 * La caisse du fournisseur : le tirage, et rien d'autre.
 *
 * Les chances sont ÉCRITES ICI et affichées telles quelles au joueur — pas de
 * table cachée, pas de « chance qui s'améliore » invisible. L'espérance vaut
 * 0,906 : sur la durée, la caisse REPREND de l'argent. C'est voulu — la boutique
 * est un puits, pas une source ; ce qu'on achète, c'est le pari, et le jeu le dit
 * en toutes lettres au lieu de le laisser deviner.
 */
function greenstand_boutique_caisse_table(): array {
    return [
        ['chance' => 45, 'mult' => 0.4, 'texte' => 'Caisse à moitié vide'],
        ['chance' => 30, 'mult' => 0.8, 'texte' => 'Marchandise correcte'],
        ['chance' => 15, 'mult' => 1.4, 'texte' => 'Bonne pioche'],
        ['chance' => 8,  'mult' => 2.2, 'texte' => 'Arrivage exceptionnel'],
        ['chance' => 2,  'mult' => 5.0, 'texte' => 'JACKPOT — la caisse du patron'],
    ];
}

/** L'espérance de la caisse, calculée depuis la table (jamais recopiée à la main). */
function greenstand_boutique_caisse_esperance(): float {
    $total = 0.0; $poids = 0;
    foreach (greenstand_boutique_caisse_table() as $l) {
        $total += $l['chance'] * $l['mult'];
        $poids += $l['chance'];
    }
    return $poids > 0 ? $total / $poids : 1.0;
}

/** Le prix d'un article pour ce joueur : ses secondes de production, avec plancher. */
function greenstand_boutique_prix(array $article, float $perSec): float {
    $ref = max(GREENSTAND_BOUTIQUE_PLANCHER, min($perSec, (float) GREENSTAND_MAX_EUR_PER_SEC));
    return round($ref * (float) $article['secondes'], 2);
}

/** Les achats du jour, par article. Une ligne par joueur, remise à zéro chaque jour. */
function greenstand_boutique_achats(string $username): array {
    $stmt = db()->prepare('SELECT day, achats_json FROM greenstand_boutique WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || (string) $row['day'] !== date('Y-m-d')) return [];
    $d = json_decode((string) $row['achats_json'], true);
    return is_array($d) ? $d : [];
}

/**
 * Les effets que le serveur connaît : ceux de la boutique, et les fermetures de
 * stand décidées par un événement.
 *
 * `ferme` DOIT passer par ici. Un stand fermé n'existait que dans la mémoire de
 * la page : il suffisait d'actualiser pour rouvrir aussitôt, et la sanction du
 * contrôle ne coûtait rien. Les bonus, eux, étaient déjà enregistrés (sinon on
 * perdait ce qu'on venait d'acheter en changeant de page) : c'est le même
 * mécanisme, avec les mêmes bornes.
 */
function gs_effets_permis(): array {
    return [
        // nom => [multiplicateur maximum accepté, durée maximale en secondes]
        'arrivees' => [4.0,  1800],
        'patience' => [8.0,  1800],
        'cout'     => [1.0,  1800],
        'ferme'    => [1.0,  120],
    ];
}

/**
 * Enregistre un effet pour ce joueur. Racheter (ou re-subir) un effet en cours le
 * PROLONGE au lieu de l'écraser.
 *
 * Tout est borné ici : un nom hors de la liste, un multiplicateur ou une durée
 * au-delà de ce que le jeu produit sont refusés. La page peut demander, elle ne
 * décide pas.
 */
function greenstand_effet_poser(string $username, string $nom, float $mult, int $secondes): array {
    $permis = gs_effets_permis();
    if (!isset($permis[$nom])) return ['ok' => false, 'error' => "Effet inconnu."];
    [$multMax, $dureeMax] = $permis[$nom];
    $mult = max(0.0, min($mult, $multMax));
    $secondes = max(1, min($secondes, $dureeMax));

    $pdo = db();
    $stmt = $pdo->prepare('SELECT day, achats_json, effets_json FROM greenstand_boutique WHERE username = ? FOR UPDATE');
    $pdo->beginTransaction();
    try {
        $stmt->execute([$username]);
        $ligne = $stmt->fetch();
        $effets = ($ligne && isset($ligne['effets_json']))
            ? (json_decode((string) $ligne['effets_json'], true) ?: [])
            : [];
        foreach ($effets as $k => $e) {
            if (!is_array($e) || (int) ($e['fin'] ?? 0) <= time()) unset($effets[$k]);
        }
        $depart = max(time(), (int) ($effets[$nom]['fin'] ?? 0));
        $effets[$nom] = ['mult' => max($mult, (float) ($effets[$nom]['mult'] ?? 0)), 'fin' => $depart + $secondes];

        $pdo->prepare("
            INSERT INTO greenstand_boutique (username, day, achats_json, effets_json, updated_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE effets_json = VALUES(effets_json), updated_at = NOW()
        ")->execute([$username, date('Y-m-d'),
                     ($ligne && isset($ligne['achats_json'])) ? (string) $ligne['achats_json'] : '{}',
                     json_encode($effets, JSON_UNESCAPED_UNICODE)]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('[greenstand_effet_poser] ' . $e->getMessage());
        return ['ok' => false, 'error' => "Impossible d'enregistrer l'effet."];
    }
    return ['ok' => true, 'effets' => greenstand_boutique_effets_actifs($username)];
}

/**
 * Les effets achetés encore en cours, et le temps qu'il leur reste.
 *
 * Ils sont gardés CÔTÉ SERVEUR, et pas dans la page : la boutique et le stand sont
 * deux pages différentes. Un effet posé en mémoire au moment de l'achat mourrait
 * avec la page de la boutique, et le joueur ne verrait jamais ce qu'il a payé.
 */
function greenstand_boutique_effets_actifs(string $username): array {
    $stmt = db()->prepare('SELECT effets_json FROM greenstand_boutique WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $brut = json_decode((string) ($stmt->fetchColumn() ?: ''), true);
    if (!is_array($brut)) return [];

    $now = time();
    $out = [];
    foreach ($brut as $nom => $e) {
        if (!is_array($e)) continue;
        $restant = (int) ($e['fin'] ?? 0) - $now;
        if ($restant <= 0) continue;
        $out[] = ['nom' => (string) $nom, 'mult' => (float) ($e['mult'] ?? 1), 'restant' => $restant];
    }
    return $out;
}

/** Le catalogue tel que ce joueur le voit : prix, achats restants, et son solde. */
function greenstand_boutique_etat(string $username): array {
    $perSec = greenstand_per_sec(greenstand_load_save($username), $username);
    $achats = greenstand_boutique_achats($username);

    $articles = [];
    foreach (greenstand_boutique() as $a) {
        $faits = (int) ($achats[$a['id']] ?? 0);
        $articles[] = [
            'id'      => $a['id'],
            'nom'     => $a['nom'],
            'emoji'   => $a['emoji'],
            'desc'    => $a['desc'],
            'effet'   => $a['effet'],
            'mult'    => (float) ($a['mult'] ?? 0),
            'duree'   => (int) ($a['duree'] ?? 0),
            'valeur'  => (int) ($a['valeur'] ?? 0),
            'prix'    => greenstand_boutique_prix($a, $perSec),
            'limite'  => (int) $a['limite'],
            'restant' => max(0, (int) $a['limite'] - $faits),
        ];
    }
    return [
        'articles'  => $articles,
        'bank_eur'  => greenstand_bank($username),
        'caisse'    => greenstand_boutique_caisse_table(),
        'esperance' => round(greenstand_boutique_caisse_esperance(), 3),
        'effets'    => greenstand_boutique_effets_actifs($username),
    ];
}

/**
 * Un achat. Tout est décidé ici : le prix (jamais celui annoncé par la page), le
 * solde, la limite du jour, et le tirage de la caisse. La page ne fait qu'appliquer
 * l'effet renvoyé.
 */
function greenstand_boutique_acheter(string $username, string $id): array {
    $article = null;
    foreach (greenstand_boutique() as $a) if ($a['id'] === $id) { $article = $a; break; }
    if (!$article) return ['ok' => false, 'error' => "Cet article n'existe pas."];

    // Stand suspendu pour auto-clic : la boutique ferme aussi, comme le Distributeur.
    $suspension = greenstand_autoclick_status($username);
    if (!empty($suspension['blocked'])) {
        return ['ok' => false, 'autoclick' => $suspension,
                'error' => 'Boutique fermée pendant la suspension. Réessaie dans '
                           . ceil($suspension['seconds'] / 60) . ' minute(s).'];
    }

    $perSec = greenstand_per_sec(greenstand_load_save($username), $username);
    $prix   = greenstand_boutique_prix($article, $perSec);
    $today  = date('Y-m-d');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT greenstand_eur_bank FROM users WHERE username = ? FOR UPDATE");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row) { $pdo->rollBack(); return ['ok' => false, 'error' => "Compte introuvable."]; }

        // La limite du jour se lit DANS la transaction : deux onglets qui cliquent
        // en même temps ne doivent pas passer tous les deux.
        $stmt = $pdo->prepare('SELECT day, achats_json, effets_json FROM greenstand_boutique WHERE username = ? FOR UPDATE');
        $stmt->execute([$username]);
        $ligne  = $stmt->fetch();
        $achats = ($ligne && (string) $ligne['day'] === $today)
            ? (json_decode((string) $ligne['achats_json'], true) ?: [])
            : [];
        $faits = (int) ($achats[$id] ?? 0);
        if ($faits >= (int) $article['limite']) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => "Tu as déjà pris « " . $article['nom'] . " » "
                . $article['limite'] . " fois aujourd'hui. Ça revient demain."];
        }

        $bank = (float) $row['greenstand_eur_bank'];
        if ($bank + 0.005 < $prix) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => "Il te faut " . number_format($prix, 2, ',', ' ')
                . " € en banque — tu en as " . number_format($bank, 2, ',', ' ') . "."];
        }

        // Le pot-de-vin ne se vend pas à un stand déjà ouvert : ce serait prendre
        // l'argent du joueur pour ne rien faire.
        if (($article['effet'] ?? '') === 'reouvrir') {
            $ferme = false;
            foreach (greenstand_boutique_effets_actifs($username) as $e) {
                if ($e['nom'] === 'ferme') { $ferme = true; break; }
            }
            if (!$ferme) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Ton stand est déjà ouvert — garde ton argent."];
            }
        }

        // Le gain de la caisse est tiré ICI, côté serveur : la page ne fait
        // qu'afficher le résultat. random_int, pas rand() : c'est le tirage d'une
        // récompense, pas une animation.
        $gain = 0.0; $lot = null;
        if (($article['effet'] ?? '') === 'caisse') {
            $table = greenstand_boutique_caisse_table();
            $total = 0; foreach ($table as $l) $total += $l['chance'];
            $tirage = random_int(1, max(1, $total));
            foreach ($table as $l) {
                $tirage -= $l['chance'];
                if ($tirage <= 0) { $lot = $l; break; }
            }
            $lot  = $lot ?: $table[0];
            $gain = round($prix * (float) $lot['mult'], 2);
        }

        // L'effet est écrit en base, pas seulement renvoyé à la page : la boutique
        // et le stand sont deux URL différentes, et c'est le stand qui doit le
        // retrouver en arrivant. Racheter un effet déjà en cours le PROLONGE au lieu
        // de l'écraser : personne ne doit perdre ce qu'il vient de payer.
        $effets = ($ligne && isset($ligne['effets_json']))
            ? (json_decode((string) $ligne['effets_json'], true) ?: [])
            : [];
        foreach ($effets as $nom => $e) {
            if (!is_array($e) || (int) ($e['fin'] ?? 0) <= time()) unset($effets[$nom]);
        }
        $effetArticle = (string) ($article['effet'] ?? '');
        if ($effetArticle === 'reouvrir') {
            // On lève la fermeture au lieu d'en poser une.
            unset($effets['ferme']);
        } elseif ($effetArticle !== 'caisse' && $effetArticle !== 'quota_tel') {
            $depart  = max(time(), (int) ($effets[$effetArticle]['fin'] ?? 0));
            $effets[$effetArticle] = [
                'mult' => max((float) $article['mult'], (float) ($effets[$effetArticle]['mult'] ?? 0)),
                'fin'  => $depart + (int) $article['duree'],
            ];
        }

        $achats[$id] = $faits + 1;
        $pdo->prepare("
            INSERT INTO greenstand_boutique (username, day, achats_json, effets_json, updated_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE day = VALUES(day), achats_json = VALUES(achats_json),
                                    effets_json = VALUES(effets_json), updated_at = NOW()
        ")->execute([$username, $today, json_encode($achats, JSON_UNESCAPED_UNICODE),
                      json_encode($effets, JSON_UNESCAPED_UNICODE)]);

        $pdo->prepare("
            UPDATE users
            SET greenstand_eur_bank = LEAST(GREATEST(greenstand_eur_bank - ? + ?, 0), 9999999999999.99)
            WHERE username = ?
        ")->execute([$prix, $gain, $username]);

        // Le carnet d'adresses ne pose pas d'effet : il relève le quota de
        // livraisons du téléphone, qui vit dans sa propre table.
        if ($effetArticle === 'quota_tel') {
            $pdo->prepare("
                INSERT INTO greenstand_telephone (username, stock, commandes, day, livrees, dernier_appel, quota_bonus, updated_at)
                VALUES (?, 0, '[]', ?, 0, 0, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    quota_bonus = IF(day = VALUES(day), quota_bonus + VALUES(quota_bonus), VALUES(quota_bonus)),
                    livrees     = IF(day = VALUES(day), livrees, 0),
                    day         = VALUES(day),
                    updated_at  = NOW()
            ")->execute([$username, $today, (int) $article['valeur']]);
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('[greenstand_boutique_acheter] ' . $e->getMessage());
        return ['ok' => false, 'error' => "Achat impossible pour le moment."];
    }

    $res = [
        'ok'       => true,
        'id'       => $id,
        'nom'      => $article['nom'],
        'prix'     => $prix,
        'bank_eur' => greenstand_bank($username),
        'etat'     => greenstand_boutique_etat($username),
    ];
    if (($article['effet'] ?? '') === 'caisse') {
        $res['caisse'] = ['gain' => $gain, 'mult' => (float) $lot['mult'], 'texte' => (string) $lot['texte']];
    } elseif (($article['effet'] ?? '') === 'reouvrir') {
        $res['reouvert'] = true;
    } elseif (($article['effet'] ?? '') === 'quota_tel') {
        $res['quota'] = (int) $article['valeur'];
    } else {
        // L'effet que la page doit poser. Il ne touche qu'au rythme des clients, à
        // leur patience ou au prix des améliorations : rien qui puisse gonfler un
        // dépôt au-delà de ce que le serveur accepte.
        $res['effet'] = ['nom' => $article['effet'], 'mult' => (float) $article['mult'],
                         'secondes' => (int) $article['duree']];
    }
    if (function_exists('log_admin_action')) log_admin_action('greenstand_boutique', null, $username . ':' . $id);
    return $res;
}

/* ======================================================================
   LE TÉLÉPHONE — le répertoire, la marchandise et les livraisons
   ======================================================================
   Le stand attend le client. Le téléphone, lui, va le chercher : des contacts
   qui s'ajoutent au répertoire à mesure que le stand grandit, des dealers chez
   qui on achète (et à qui on revend) de la marchandise, et des commandes à
   livrer sur la carte du quartier.

   LA BOUCLE. On achète du stock chez un dealer, un client appelle, on livre à
   l'adresse indiquée, on encaisse plus cher que le prix d'achat. Le bénéfice
   vient de la LIVRAISON, jamais de l'aller-retour entre deux dealers : chaque
   dealer rachète moins cher qu'il ne vend, toujours.

   POURQUOI TOUT EST CALCULÉ ICI. Le téléphone verse de l'argent en banque. Comme
   les lots du podium et les objectifs du jour, c'est le SERVEUR qui verse, donc
   ça ne passe pas par le plafond de dépôt — mais du coup, rien de ce qui décide
   d'un montant ne doit venir du navigateur : les prix, les quantités, les
   adresses, les délais et le nombre de livraisons par jour sont décidés et
   vérifiés ici. La page ne fait qu'afficher et demander.

   CE QUE ÇA NE FAIT PAS : aucune modification du stand, des niveaux ou des
   gains par seconde. Le téléphone est une source d'euros à part, plafonnée par
   le nombre de livraisons quotidiennes.
   ====================================================================== */

if (!defined('GREENSTAND_TEL_STOCK_MAX'))     define('GREENSTAND_TEL_STOCK_MAX', 400);
if (!defined('GREENSTAND_TEL_CMD_MAX'))       define('GREENSTAND_TEL_CMD_MAX', 4);      // commandes en attente
// Le nombre de livraisons quotidiennes est le SEUL vrai garde-fou du téléphone :
// c'est lui qui borne ce que cette page peut rapporter dans une journée. Un test
// (tests/test_telephone.php) recalcule ce plafond à partir des chiffres du
// répertoire — si tu montes ce nombre ou les quantités des clients, il te dira
// combien d'heures de stand tu viens d'offrir.
if (!defined('GREENSTAND_TEL_LIVRAISONS_JOUR'))define('GREENSTAND_TEL_LIVRAISONS_JOUR', 12);
if (!defined('GREENSTAND_TEL_DELAI_APPEL'))   define('GREENSTAND_TEL_DELAI_APPEL', 75); // secondes entre deux appels
if (!defined('GREENSTAND_TEL_PLANCHER'))      define('GREENSTAND_TEL_PLANCHER', 120.0); // €/s pris en compte pour un tout petit stand
// Ce que vaut UNE unité de marchandise, en secondes de production. C'est le
// curseur qui décide du poids de tout le téléphone dans l'économie : à 30
// secondes, une seule tournée valait une journée entière de stand.
if (!defined('GREENSTAND_TEL_SECONDES_UNITE')) define('GREENSTAND_TEL_SECONDES_UNITE', 5);

/**
 * Le niveau du stand vu par le serveur — la même formule que standLevel() côté JS
 * (1 + total des niveaux / 3), pour que « débloqué à partir du niveau 8 » veuille
 * dire la même chose des deux côtés.
 */
function greenstand_stand_niveau(?array $save): int {
    return 1 + intdiv(greenstand_total_niveaux($save), 3);
}

/**
 * Le total de niveaux d'améliorations du joueur — celui de son MEILLEUR comptoir.
 *
 * Depuis que chaque stand a ses propres niveaux, ce total ne peut plus être celui du
 * comptoir tenu : passer au BrownStand tout neuf ferait retomber le « niveau de stand »
 * à 1, et avec lui les contacts du téléphone, les objectifs du jour et les succès déjà
 * en cours. Ce qui a été atteint reste atteint : on retient le plus haut des quatre.
 */
function greenstand_total_niveaux(?array $save): int {
    if (!$save) return 0;
    $somme = function(mixed $levels): int {
        $t = 0;
        foreach (is_array($levels) ? $levels : [] as $lvl) $t += max(0, (int) $lvl);
        return $t;
    };
    $best  = $somme($save['levels'] ?? []);
    $carte = is_array($save['standLevels'] ?? null) ? $save['standLevels'] : [];
    foreach ($carte as $entree) {
        if (is_array($entree)) $best = max($best, $somme($entree['levels'] ?? []));
    }
    return $best;
}

/**
 * Le répertoire. `niveau` est le niveau de stand à partir duquel le numéro
 * apparaît ; les clients passent des commandes à livrer, les dealers vendent et
 * rachètent de la marchandise.
 *
 * Pour un CLIENT : `mult` est ce qu'il paie l'unité (en multiple du prix de
 * référence), `qte` la fourchette de sa commande, `delai` le temps qu'il accepte
 * d'attendre, `poids` sa fréquence d'appel.
 *
 * Pour un DEALER : `achat` est ce que TU paies l'unité, `vente` ce qu'il te
 * rachète. `vente` est toujours inférieur à `achat` — personne ne gagne d'argent
 * en faisant l'aller-retour entre deux dealers, c'est la livraison qui paie.
 */
function greenstand_tel_contacts(): array {
    return [
        ['id'=>'momo',    'nom'=>'Momo',            'surnom'=>'« du 4e »',        'emoji'=>'🧢', 'type'=>'client', 'niveau'=>1,
         'desc'=>"Le voisin du dessus. Petites commandes, jamais pressé.",
         'look'=>['fond'=>'#3E6B33', 'peau'=>'#C58A5B', 'cheveux'=>'#1F1B16', 'coiffe'=>'casquette', 'accessoire'=>'', 'vetement'=>'#2B4A24'],
         'mult'=>1.35, 'qte'=>[2, 5],   'delai'=>900, 'poids'=>30],
        ['id'=>'sonia',   'nom'=>'Sonia',           'surnom'=>'« la Grande »',    'emoji'=>'💅', 'type'=>'client', 'niveau'=>3,
         'desc'=>"Connaît du monde. Paie bien, et elle revient.",
         'look'=>['fond'=>'#8B5FBF', 'peau'=>'#E0AE86', 'cheveux'=>'#4A2418', 'coiffe'=>'longs', 'accessoire'=>'boucles', 'vetement'=>'#5B3A7A'],
         'mult'=>1.60, 'qte'=>[4, 9],   'delai'=>780, 'poids'=>24],
        ['id'=>'grec',    'nom'=>'Le Grec',         'emoji'=>'🥙', 'type'=>'dealer', 'niveau'=>5,
         'surnom'=>'Yannis',
         'desc'=>"Grossiste du quartier. Rien d'extraordinaire, mais il a toujours du stock.",
         'look'=>['fond'=>'#B07A2E', 'peau'=>'#C98F5E', 'cheveux'=>'#2A2320', 'coiffe'=>'courts', 'accessoire'=>'moustache', 'vetement'=>'#7A5320'],
         'achat'=>1.00, 'vente'=>0.72, 'lot'=>[5, 60]],
        ['id'=>'karim',   'nom'=>'Karim',           'surnom'=>'« Scooter »',      'emoji'=>'🛵', 'type'=>'client', 'niveau'=>8,
         'desc'=>"Livreur de nuit. Grosses commandes, souvent à l'autre bout du quartier.",
         'look'=>['fond'=>'#2F6E7A', 'peau'=>'#A9713C', 'cheveux'=>'#141210', 'coiffe'=>'capuche', 'accessoire'=>'', 'vetement'=>'#1E4C55'],
         'mult'=>1.85, 'qte'=>[8, 16],  'delai'=>720, 'poids'=>18],
        ['id'=>'vlad',    'nom'=>'Vlad',            'emoji'=>'🧊', 'type'=>'dealer', 'niveau'=>12,
         'surnom'=>'« Glaçon »',
         'desc'=>"Vend moins cher, mais seulement par gros lots. Rachète correctement.",
         'look'=>['fond'=>'#4A6E8C', 'peau'=>'#D8B48F', 'cheveux'=>'#8E8B84', 'coiffe'=>'rase', 'accessoire'=>'lunettes', 'vetement'=>'#2E4658'],
         'achat'=>0.86, 'vente'=>0.68, 'lot'=>[25, 150]],
        ['id'=>'rose',    'nom'=>'Madame Rose',     'surnom'=>'la patronne',      'emoji'=>'🌹', 'type'=>'client', 'niveau'=>18,
         'desc'=>"Très bonne cliente, très impatiente. On ne la fait pas attendre.",
         'look'=>['fond'=>'#A8385A', 'peau'=>'#EFC7A6', 'cheveux'=>'#B8B0A6', 'coiffe'=>'chignon', 'accessoire'=>'boucles', 'vetement'=>'#7A2440'],
         'mult'=>2.30, 'qte'=>[10, 20], 'delai'=>420, 'poids'=>12],
        ['id'=>'chimiste','nom'=>'Le Chimiste',     'emoji'=>'⚗️', 'type'=>'dealer', 'niveau'=>25,
         'surnom'=>'on ne sait pas son nom',
         'desc'=>"Prix de gros, pour ceux qui tournent vraiment. Rachète au meilleur prix.",
         'look'=>['fond'=>'#4F7A3A', 'peau'=>'#D9BE9C', 'cheveux'=>'#3A3A3A', 'coiffe'=>'courts', 'accessoire'=>'lunettes', 'vetement'=>'#E8E4D8'],
         'achat'=>0.74, 'vente'=>0.62, 'lot'=>[50, 300]],
        ['id'=>'inconnu', 'nom'=>'Numéro inconnu',  'surnom'=>'« ne rappelle jamais »', 'emoji'=>'❓', 'type'=>'client', 'niveau'=>35,
         'desc'=>"Personne ne sait qui c'est. Les commandes sont énormes et le délai est court.",
         'look'=>['fond'=>'#2A2E33', 'peau'=>'#3A4048', 'cheveux'=>'#22262B', 'coiffe'=>'capuche', 'accessoire'=>'ombre', 'vetement'=>'#1A1D21'],
         'mult'=>2.80, 'qte'=>[18, 32], 'delai'=>360, 'poids'=>8],
    ];
}

function greenstand_tel_contact(string $id): ?array {
    foreach (greenstand_tel_contacts() as $c) if ($c['id'] === $id) return $c;
    return null;
}

/**
 * LE QUARTIER — les rues de la mini-carte.
 *
 * Elles sont décrites ICI et pas dans la page : une adresse de livraison est
 * tirée par le serveur, et il faut bien que les deux bouts parlent des mêmes
 * rues. La carte fait 100 x 100 ; `axe` vaut h (horizontale, à la hauteur `pos`)
 * ou v (verticale, à l'abscisse `pos`). Le stand est au croisement du centre.
 *
 * Le tracé est volontairement irrégulier — deux avenues larges, des rues plus
 * serrées d'un côté, une place et un canal : une grille parfaitement régulière
 * ne ressemble à aucune ville.
 */
function greenstand_tel_rues(): array {
    return [
        ['id'=>'h1', 'axe'=>'h', 'pos'=>11, 'nom'=>'Boulevard des Docks',   'large'=>true],
        ['id'=>'h2', 'axe'=>'h', 'pos'=>29, 'nom'=>'Rue de la Fonderie'],
        ['id'=>'h3', 'axe'=>'h', 'pos'=>50, 'nom'=>'Avenue Centrale',       'large'=>true],
        ['id'=>'h4', 'axe'=>'h', 'pos'=>67, 'nom'=>'Rue des Peupliers'],
        ['id'=>'h5', 'axe'=>'h', 'pos'=>81, 'nom'=>'Impasse du Marché'],
        ['id'=>'h6', 'axe'=>'h', 'pos'=>92, 'nom'=>'Chemin des Écluses'],
        ['id'=>'v1', 'axe'=>'v', 'pos'=>13, 'nom'=>'Rue Saint-Gilles'],
        ['id'=>'v2', 'axe'=>'v', 'pos'=>27, 'nom'=>'Rue des Tanneurs'],
        ['id'=>'v3', 'axe'=>'v', 'pos'=>50, 'nom'=>'Avenue du Parc',        'large'=>true],
        ['id'=>'v4', 'axe'=>'v', 'pos'=>64, 'nom'=>'Rue Basse'],
        ['id'=>'v5', 'axe'=>'v', 'pos'=>78, 'nom'=>'Rue du Vieux Pont'],
        ['id'=>'v6', 'axe'=>'v', 'pos'=>90, 'nom'=>'Quai des Brumes'],
    ];
}

/** Le point (x, y) d'une adresse : une rue, et un avancement `t` le long de cette rue. */
function greenstand_tel_point(array $rue, float $t): array {
    $t = max(0.06, min(0.94, $t));
    return $rue['axe'] === 'h'
        ? ['x' => round($t * 100, 1), 'y' => (float) $rue['pos']]
        : ['x' => (float) $rue['pos'], 'y' => round($t * 100, 1)];
}

/** Tire une adresse au hasard : une rue, un numéro, un point sur la carte. */
function greenstand_tel_adresse(): array {
    $rues = greenstand_tel_rues();
    $rue  = $rues[random_int(0, count($rues) - 1)];
    $t    = random_int(8, 92) / 100;
    $pt   = greenstand_tel_point($rue, $t);
    return [
        'rue'    => $rue['id'],
        'nom'    => $rue['nom'],
        'numero' => random_int(1, 89),
        't'      => $t,
        'x'      => $pt['x'],
        'y'      => $pt['y'],
    ];
}

/** La distance à parcourir depuis le stand (au centre), en suivant les rues. */
function greenstand_tel_distance(array $adresse): float {
    return (abs((float) $adresse['x'] - 50) + abs((float) $adresse['y'] - 50)) / 100;
}

/** Le prix de référence d'une unité de marchandise, pour ce joueur. */
function greenstand_tel_prix_unite(float $perSec): float {
    $ref = max(GREENSTAND_TEL_PLANCHER, min($perSec, (float) GREENSTAND_MAX_EUR_PER_SEC));
    return round($ref * GREENSTAND_TEL_SECONDES_UNITE, 2);
}

/** La ligne du joueur, créée au besoin. Le jour sert de remise à zéro au compteur. */
function greenstand_tel_ligne(string $username): array {
    $stmt = db()->prepare('SELECT * FROM greenstand_telephone WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    $today = date('Y-m-d');
    if (!$row) {
        db()->prepare("INSERT IGNORE INTO greenstand_telephone
                       (username, stock, commandes, day, livrees, dernier_appel, updated_at)
                       VALUES (?, 0, '[]', ?, 0, 0, NOW())")->execute([$username, $today]);
        return ['stock' => 0, 'commandes' => [], 'day' => $today, 'livrees' => 0,
                'dernier_appel' => 0, 'quota_bonus' => 0];
    }
    $cmd = json_decode((string) $row['commandes'], true);
    return [
        'stock'         => (int) $row['stock'],
        'commandes'     => is_array($cmd) ? $cmd : [],
        'day'           => (string) $row['day'],
        // Un jour nouveau : le compteur de livraisons repart, le stock reste (c'est
        // de la marchandise achetée, elle n'a pas à s'évaporer à minuit).
        'livrees'       => ((string) $row['day'] === $today) ? (int) $row['livrees'] : 0,
        'dernier_appel' => (int) $row['dernier_appel'],
        // Le carnet d'adresses acheté à la boutique : il vaut pour la journée.
        'quota_bonus'   => ((string) $row['day'] === $today) ? (int) ($row['quota_bonus'] ?? 0) : 0,
    ];
}

function greenstand_tel_ecrire(string $username, array $l): void {
    db()->prepare("
        INSERT INTO greenstand_telephone (username, stock, commandes, day, livrees, dernier_appel, quota_bonus, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE stock = VALUES(stock), commandes = VALUES(commandes),
                                day = VALUES(day), livrees = VALUES(livrees),
                                dernier_appel = VALUES(dernier_appel),
                                quota_bonus = VALUES(quota_bonus), updated_at = NOW()
    ")->execute([$username, max(0, (int) $l['stock']),
                 json_encode(array_values($l['commandes']), JSON_UNESCAPED_UNICODE),
                 date('Y-m-d'), max(0, (int) $l['livrees']), (int) $l['dernier_appel'],
                 max(0, (int) ($l['quota_bonus'] ?? 0))]);
}

/**
 * Fait sonner le téléphone si c'est le moment : au plus une commande toutes les
 * GREENSTAND_TEL_DELAI_APPEL secondes, et jamais plus de GREENSTAND_TEL_CMD_MAX en
 * attente. Les commandes périmées disparaissent au passage.
 *
 * Le tirage est pondéré par contact : un très bon client appelle plus rarement.
 */
function greenstand_tel_sonner(array &$ligne, int $niveau, float $prixUnite): bool {
    $now = time();
    // Les périmées s'en vont d'abord — sinon elles occupent les places libres.
    $ligne['commandes'] = array_values(array_filter($ligne['commandes'],
        fn($c) => is_array($c) && (int) ($c['fin'] ?? 0) > $now));

    if (count($ligne['commandes']) >= GREENSTAND_TEL_CMD_MAX) return false;
    if ($now - (int) $ligne['dernier_appel'] < GREENSTAND_TEL_DELAI_APPEL) return false;

    $dispo = array_values(array_filter(greenstand_tel_contacts(),
        fn($c) => $c['type'] === 'client' && $niveau >= (int) $c['niveau']));
    if (!$dispo) return false;

    $total = 0; foreach ($dispo as $c) $total += (int) $c['poids'];
    $tirage = random_int(1, max(1, $total));
    $choisi = $dispo[0];
    foreach ($dispo as $c) { $tirage -= (int) $c['poids']; if ($tirage <= 0) { $choisi = $c; break; } }

    $adresse = greenstand_tel_adresse();
    $qte = random_int((int) $choisi['qte'][0], (int) $choisi['qte'][1]);
    // Plus c'est loin, mieux c'est payé : jusqu'à +50 % au bout du quartier.
    $prime = 1 + greenstand_tel_distance($adresse) * 0.5;

    $ligne['commandes'][] = [
        'id'      => bin2hex(random_bytes(6)),
        'contact' => $choisi['id'],
        'qte'     => $qte,
        'unite'   => round($prixUnite * (float) $choisi['mult'] * $prime, 2),
        'fin'     => $now + (int) $choisi['delai'],
        'adresse' => $adresse,
    ];
    $ligne['dernier_appel'] = $now;
    return true;
}

/**
 * Juste de quoi allumer la pastille rouge du menu : combien de clients attendent,
 * et dans combien de temps le téléphone peut resonner.
 *
 * C'est un point d'entrée SÉPARÉ de greenstand_tel_etat() parce qu'il est appelé
 * depuis toutes les pages, toutes les trente secondes : inutile de construire le
 * répertoire, les prix et les douze rues du quartier pour afficher un chiffre.
 *
 * Il fait quand même sonner le téléphone (greenstand_tel_sonner) : sans ça, les
 * commandes ne naîtraient qu'en ouvrant la page, et la notification n'aurait rien
 * à annoncer.
 */
function greenstand_tel_badge(string $username): array {
    $save   = greenstand_load_save($username);
    $niveau = greenstand_stand_niveau($save);
    $prix   = greenstand_tel_prix_unite(greenstand_per_sec($save, $username));

    $ligne = greenstand_tel_ligne($username);
    if (greenstand_tel_sonner($ligne, $niveau, $prix)) greenstand_tel_ecrire($username, $ligne);

    $now = time();
    $attente = 0;
    $plusCourt = null;
    foreach ($ligne['commandes'] as $c) {
        if (!is_array($c) || (int) ($c['fin'] ?? 0) <= $now) continue;
        $attente++;
        $reste = (int) $c['fin'] - $now;
        if ($plusCourt === null || $reste < $plusCourt) $plusCourt = $reste;
    }
    return [
        'attente'  => $attente,
        'urgence'  => $plusCourt,          // le client le plus pressé, en secondes
        'prochain' => max(0, GREENSTAND_TEL_DELAI_APPEL - ($now - (int) $ligne['dernier_appel'])),
    ];
}

/** Tout ce que la page du téléphone a besoin de savoir. */
function greenstand_tel_etat(string $username): array {
    $save   = greenstand_load_save($username);
    $niveau = greenstand_stand_niveau($save);
    $perSec = greenstand_per_sec($save, $username);
    $prix   = greenstand_tel_prix_unite($perSec);

    $ligne = greenstand_tel_ligne($username);
    if (greenstand_tel_sonner($ligne, $niveau, $prix)) greenstand_tel_ecrire($username, $ligne);

    $now = time();
    $contacts = [];
    foreach (greenstand_tel_contacts() as $c) {
        $ouvert = $niveau >= (int) $c['niveau'];
        $entree = [
            'id' => $c['id'], 'nom' => $c['nom'], 'emoji' => $c['emoji'], 'type' => $c['type'],
            'surnom' => (string) ($c['surnom'] ?? ''),
            'niveau' => (int) $c['niveau'], 'desc' => $c['desc'], 'debloque' => $ouvert,
            // De quoi dessiner sa tête. Si l'admin a envoyé une vraie photo
            // (visuel « contact_<id> »), c'est elle qui sera affichée à la place.
            'look' => $c['look'] ?? [],
        ];
        if ($c['type'] === 'dealer' && $ouvert) {
            $entree['prix_achat'] = round($prix * (float) $c['achat'], 2);
            $entree['prix_vente'] = round($prix * (float) $c['vente'], 2);
            $entree['lot_min']    = (int) $c['lot'][0];
            $entree['lot_max']    = (int) $c['lot'][1];
        }
        $contacts[] = $entree;
    }

    $commandes = [];
    foreach ($ligne['commandes'] as $c) {
        $contact = greenstand_tel_contact((string) $c['contact']);
        if (!$contact || (int) $c['fin'] <= $now) continue;
        $commandes[] = [
            'id'      => (string) $c['id'],
            'contact' => $contact['id'],
            'nom'     => $contact['nom'],
            'emoji'   => $contact['emoji'],
            'look'    => $contact['look'] ?? [],
            'qte'     => (int) $c['qte'],
            'unite'   => (float) $c['unite'],
            'total'   => round((float) $c['unite'] * (int) $c['qte'], 2),
            'restant' => (int) $c['fin'] - $now,
            'adresse' => $c['adresse'],
        ];
    }

    return [
        'niveau'      => $niveau,
        'stock'       => (int) $ligne['stock'],
        'stock_max'   => GREENSTAND_TEL_STOCK_MAX,
        'prix_unite'  => $prix,
        'contacts'    => $contacts,
        'commandes'   => $commandes,
        'rues'        => greenstand_tel_rues(),
        'bank_eur'    => greenstand_bank($username),
        'livrees'     => (int) $ligne['livrees'],
        'livrees_max' => GREENSTAND_TEL_LIVRAISONS_JOUR + (int) ($ligne['quota_bonus'] ?? 0),
        'quota_bonus' => (int) ($ligne['quota_bonus'] ?? 0),
        'prochain'    => max(0, GREENSTAND_TEL_DELAI_APPEL - ($now - (int) $ligne['dernier_appel'])),
    ];
}

/**
 * Acheter ou revendre chez un dealer. `sens` vaut 'achat' (tu paies, tu reçois de
 * la marchandise) ou 'vente' (tu donnes de la marchandise, il paie).
 */
function greenstand_tel_dealer(string $username, string $id, string $sens, int $qte): array {
    $contact = greenstand_tel_contact($id);
    if (!$contact || $contact['type'] !== 'dealer') return ['ok' => false, 'error' => "Ce numéro n'existe pas."];
    if (!in_array($sens, ['achat', 'vente'], true)) return ['ok' => false, 'error' => "Opération inconnue."];

    $save   = greenstand_load_save($username);
    $niveau = greenstand_stand_niveau($save);
    if ($niveau < (int) $contact['niveau']) {
        return ['ok' => false, 'error' => $contact['nom'] . " ne répond qu'à partir du niveau " . $contact['niveau'] . "."];
    }
    $qte = max(1, min($qte, GREENSTAND_TEL_STOCK_MAX));
    if ($sens === 'achat' && ($qte < (int) $contact['lot'][0] || $qte > (int) $contact['lot'][1])) {
        return ['ok' => false, 'error' => $contact['nom'] . " vend par lots de " . $contact['lot'][0]
            . " à " . $contact['lot'][1] . " unités."];
    }

    $prix    = greenstand_tel_prix_unite(greenstand_per_sec($save, $username));
    $unitaire= round($prix * (float) ($sens === 'achat' ? $contact['achat'] : $contact['vente']), 2);
    $montant = round($unitaire * $qte, 2);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT greenstand_eur_bank FROM users WHERE username = ? FOR UPDATE");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row) { $pdo->rollBack(); return ['ok' => false, 'error' => "Compte introuvable."]; }

        $stmt = $pdo->prepare('SELECT stock FROM greenstand_telephone WHERE username = ? FOR UPDATE');
        $stmt->execute([$username]);
        $stock = (int) ($stmt->fetchColumn() ?: 0);

        if ($sens === 'achat') {
            if ($stock + $qte > GREENSTAND_TEL_STOCK_MAX) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Tu ne peux pas stocker plus de "
                    . GREENSTAND_TEL_STOCK_MAX . " unités — il te reste de la place pour "
                    . max(0, GREENSTAND_TEL_STOCK_MAX - $stock) . "."];
            }
            if ((float) $row['greenstand_eur_bank'] + 0.005 < $montant) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Il te faut " . number_format($montant, 2, ',', ' ')
                    . " € en banque pour ce lot."];
            }
            $nouveauStock = $stock + $qte;
            $delta = -$montant;
        } else {
            if ($stock < $qte) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Tu n'as que " . $stock . " unité(s) en stock."];
            }
            $nouveauStock = $stock - $qte;
            $delta = $montant;
        }

        $pdo->prepare("UPDATE greenstand_telephone SET stock = ?, updated_at = NOW() WHERE username = ?")
            ->execute([$nouveauStock, $username]);
        $pdo->prepare("UPDATE users SET greenstand_eur_bank =
                       LEAST(GREATEST(greenstand_eur_bank + ?, 0), 9999999999999.99) WHERE username = ?")
            ->execute([$delta, $username]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('[greenstand_tel_dealer] ' . $e->getMessage());
        return ['ok' => false, 'error' => "Opération impossible pour le moment."];
    }

    return ['ok' => true, 'sens' => $sens, 'qte' => $qte, 'montant' => $montant,
            'nom' => $contact['nom'], 'etat' => greenstand_tel_etat($username)];
}

/**
 * Livrer une commande. Il faut la marchandise en stock, la commande doit exister
 * et ne pas être périmée, et le quota du jour ne doit pas être atteint.
 *
 * Le montant vient de la commande enregistrée en base, pas de ce que la page
 * annonce : c'est le serveur qui a fixé le prix au moment de l'appel.
 */
function greenstand_tel_livrer(string $username, string $commandeId): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM greenstand_telephone WHERE username = ? FOR UPDATE');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row) { $pdo->rollBack(); return ['ok' => false, 'error' => "Aucune commande en cours."]; }

        $cmds  = json_decode((string) $row['commandes'], true) ?: [];
        $today = date('Y-m-d');
        $memeJour = ((string) $row['day'] === $today);
        $livrees  = $memeJour ? (int) $row['livrees'] : 0;
        // Le quota du jour, carnets d'adresses compris (voir la boutique).
        $quota = GREENSTAND_TEL_LIVRAISONS_JOUR + ($memeJour ? (int) ($row['quota_bonus'] ?? 0) : 0);
        if ($livrees >= $quota) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => "Tu as déjà fait tes " . $quota
                . " livraisons du jour. Ça repart demain."];
        }

        $now = time(); $cible = null; $reste = [];
        foreach ($cmds as $c) {
            if (is_array($c) && (string) ($c['id'] ?? '') === $commandeId && (int) $c['fin'] > $now) { $cible = $c; continue; }
            if (is_array($c) && (int) ($c['fin'] ?? 0) > $now) $reste[] = $c;
        }
        if (!$cible) { $pdo->rollBack(); return ['ok' => false, 'error' => "Cette commande n'est plus valable."]; }

        $stock = (int) $row['stock'];
        $qte   = (int) $cible['qte'];
        if ($stock < $qte) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => "Il te faut " . $qte . " unités en stock — tu en as " . $stock . "."];
        }

        $gain = round((float) $cible['unite'] * $qte, 2);
        $pdo->prepare("UPDATE greenstand_telephone SET stock = ?, commandes = ?, day = ?, livrees = ?, updated_at = NOW()
                       WHERE username = ?")
            ->execute([$stock - $qte, json_encode($reste, JSON_UNESCAPED_UNICODE), $today, $livrees + 1, $username]);
        $pdo->prepare("UPDATE users SET greenstand_eur_bank =
                       LEAST(greenstand_eur_bank + ?, 9999999999999.99) WHERE username = ?")
            ->execute([$gain, $username]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('[greenstand_tel_livrer] ' . $e->getMessage());
        return ['ok' => false, 'error' => "Livraison impossible pour le moment."];
    }

    $contact = greenstand_tel_contact((string) $cible['contact']);
    return ['ok' => true, 'gain' => $gain, 'qte' => $qte,
            'nom' => $contact['nom'] ?? '', 'adresse' => $cible['adresse'],
            'etat' => greenstand_tel_etat($username)];
}

/* ======================================================================
   HISTORIQUE DES SAUVEGARDES
   ======================================================================
   greenstand_saves ne contient qu'une ligne par joueur, réécrite toutes les
   cinq secondes. Une progression effacée — bug, mauvaise manipulation, clic
   sur « réinitialiser » — ne pouvait donc pas être rendue.

   On garde ici cinq clichés espacés. Espacés, parce qu'enregistrer chacune
   des sauvegardes de cinq secondes ferait cinq clichés couvrant vingt-cinq
   secondes : inutile. Un cliché toutes les dix minutes couvre presque une
   heure de jeu, ce qui est la fenêtre qui compte quand quelqu'un signale une
   perte de progression.
   ====================================================================== */

// Intervalle minimum entre deux clichés automatiques, en secondes.
if (!defined('GREENSTAND_SNAPSHOT_EVERY')) define('GREENSTAND_SNAPSHOT_EVERY', 600);
// Nombre de clichés conservés par joueur. Au-delà, les plus vieux tombent.
if (!defined('GREENSTAND_SNAPSHOT_KEEP'))  define('GREENSTAND_SNAPSHOT_KEEP', 5);

/**
 * Range un cliché de la sauvegarde. $reason = 'auto' respecte l'espacement ;
 * n'importe quelle autre raison force l'enregistrement (remise à zéro,
 * restauration), ce sont justement les moments où l'on veut un filet.
 */
function greenstand_snapshot_save(string $username, string $json, string $reason = 'auto'): void {
    if ($reason === 'auto') {
        $stmt = db()->prepare('SELECT created_at FROM greenstand_save_history WHERE username = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$username]);
        $dernier = $stmt->fetchColumn();
        if ($dernier && (time() - strtotime((string) $dernier)) < GREENSTAND_SNAPSHOT_EVERY) return;
    }

    db()->prepare('INSERT INTO greenstand_save_history (username, state_json, reason, created_at) VALUES (?, ?, ?, NOW())')
        ->execute([$username, $json, mb_substr($reason, 0, 32)]);

    // On ne garde que les derniers. DELETE ... NOT IN (SELECT ... LIMIT) est
    // refusé par MySQL : on lit les identifiants à garder, puis on supprime le reste.
    $stmt = db()->prepare('SELECT id FROM greenstand_save_history WHERE username = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int) GREENSTAND_SNAPSHOT_KEEP);
    $stmt->execute([$username]);
    $garder = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$garder) return;
    $trous = implode(',', array_fill(0, count($garder), '?'));
    db()->prepare("DELETE FROM greenstand_save_history WHERE username = ? AND id NOT IN ($trous)")
        ->execute(array_merge([$username], $garder));
}

/** Les clichés d'un joueur, du plus récent au plus ancien, résumés pour l'admin. */
function greenstand_snapshots(string $username): array {
    $stmt = db()->prepare('SELECT id, state_json, reason, created_at FROM greenstand_save_history WHERE username = ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([$username]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $etat = greenstand_normalize_save_state($row['state_json']);
        $out[] = [
            'id'       => (int) $row['id'],
            'reason'   => (string) $row['reason'],
            'date'     => (string) $row['created_at'],
            'age'      => max(0, time() - strtotime((string) $row['created_at'])),
            // Le résumé permet de reconnaître le bon cliché sans le restaurer d'abord.
            'money'    => $etat ? round((float) ($etat['money'] ?? 0), 2) : null,
            'earned'   => $etat ? round((float) ($etat['lifetimeTotalEarned'] ?? 0), 2) : null,
            'clicks'   => $etat ? (int) ($etat['lifetimeManualSales'] ?? 0) : null,
            'per_sec'  => $etat ? round(greenstand_per_sec($etat, $username), 2) : null,
            'levels'   => $etat ? array_sum($etat['levels'] ?? []) : null,
        ];
    }
    return $out;
}

/** Remet un cliché en place. L'état actuel est mis de côté avant d'être écrasé. */
function gs_admin_save_restore(string $username, int $id): array {
    $stmt = db()->prepare('SELECT state_json FROM greenstand_save_history WHERE id = ? AND username = ? LIMIT 1');
    $stmt->execute([$id, $username]);
    $json = $stmt->fetchColumn();
    if (!$json) return ['ok' => false, 'error' => "Cliché introuvable pour ce joueur."];

    $etat = greenstand_normalize_save_state($json);
    if ($etat === null) return ['ok' => false, 'error' => "Ce cliché est illisible, restauration refusée."];

    // Filet du filet : ce qu'on écrase part dans l'historique. Une restauration
    // faite sur le mauvais joueur reste rattrapable.
    $actuel = db()->prepare('SELECT state_json FROM greenstand_saves WHERE username = ? LIMIT 1');
    $actuel->execute([$username]);
    $avant = $actuel->fetchColumn();
    if ($avant) greenstand_snapshot_save($username, (string) $avant, 'avant_restauration');

    $propre = json_encode($etat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    db()->prepare("
        INSERT INTO greenstand_saves (username, state_json, updated_at) VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = NOW()
    ")->execute([$username, $propre]);

    greenstand_touch_persec($username, $etat);
    if (function_exists('log_admin_action')) log_admin_action('greenstand_save_restore', null, $username . ' #' . $id);
    return ['ok' => true, 'username' => $username, 'snapshots' => greenstand_snapshots($username)];
}

/* ======================================================================
   SURVEILLANCE — ce que l'anti auto-clicker a relevé
   ======================================================================
   Le registre greenstand_clicks accumulait les avertissements sans que rien
   ne les montre : il fallait lire les journaux PHP du serveur. Cette liste
   les remonte dans le panel, avec de quoi lever une sanction prise à tort.
   ====================================================================== */

// Au-delà de cet âge, la cadence affichée n'est plus « en direct » : elle date de
// la dernière mesure. Le jeu sauvegarde toutes les 5 s, on laisse le triple.
if (!defined('GREENSTAND_LIVE_FRESH')) define('GREENSTAND_LIVE_FRESH', 15);
// Un joueur reste dans la liste « en ligne » tant qu'il a donné signe de vie ici.
if (!defined('GREENSTAND_LIVE_WINDOW')) define('GREENSTAND_LIVE_WINDOW', 300);

/**
 * Les comptes à surveiller : ceux qui jouent EN CE MOMENT, plus tous ceux qui
 * traînent un avertissement.
 *
 * La liste ne montrait que les comptes déjà signalés : impossible d'y voir la
 * cadence de quelqu'un pendant qu'il joue, ce qui est justement le moment où
 * elle sert à quelque chose. On y ajoute les joueurs actifs, avec l'âge de la
 * mesure pour que l'interface distingue une cadence en direct d'un vieux relevé.
 */
function gs_admin_watchlist(int $limit = 40): array {
    $limit  = max(1, min($limit, 100));
    $fenetre = (int) GREENSTAND_LIVE_WINDOW;
    $stmt   = db()->query("
        SELECT username, last_clicks, pending_clicks, checked_at,
               strikes, blocked_until, last_rate, flagged_at,
               TIMESTAMPDIFF(SECOND, checked_at, NOW()) AS age
        FROM greenstand_clicks
        WHERE strikes > 0 OR blocked_until IS NOT NULL OR flagged_at IS NOT NULL
           OR checked_at > (NOW() - INTERVAL {$fenetre} SECOND)
        ORDER BY (blocked_until IS NOT NULL AND blocked_until > NOW()) DESC,
                 (checked_at > (NOW() - INTERVAL {$fenetre} SECOND)) DESC,
                 last_rate DESC, strikes DESC
        LIMIT {$limit}
    ");

    $now = time();
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $till = $row['blocked_until'] ? strtotime((string) $row['blocked_until']) : 0;
        $age  = max(0, (int) $row['age']);
        $out[] = [
            'username'  => (string) $row['username'],
            'strikes'   => (int) $row['strikes'],
            'rate'      => (float) $row['last_rate'],
            'human_cps' => GREENSTAND_HUMAN_CPS,
            // « en direct » : la mesure a moins de quinze secondes.
            'age'       => $age,
            'live'      => $age <= GREENSTAND_LIVE_FRESH,
            'online'    => $age <= GREENSTAND_LIVE_WINDOW,
            'blocked'   => $till > $now,
            'seconds'   => $till > $now ? $till - $now : 0,
            'flagged'   => $row['flagged_at'] ? (string) $row['flagged_at'] : null,
            'flagged_age' => $row['flagged_at'] ? max(0, $now - strtotime((string) $row['flagged_at'])) : null,
            'checked'   => (string) $row['checked_at'],
        ];
    }
    return $out;
}

/** Efface les avertissements d'un compte et lève la sanction en cours. */
function gs_admin_watch_clear(string $username): array {
    $stmt = db()->prepare('SELECT username FROM greenstand_clicks WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if (!$stmt->fetchColumn()) return ['ok' => false, 'error' => "Ce joueur n'a rien dans le registre."];

    db()->prepare("
        UPDATE greenstand_clicks
        SET strikes = 0, blocked_until = NULL, flagged_at = NULL, checked_at = NOW()
        WHERE username = ?
    ")->execute([$username]);

    if (function_exists('log_admin_action')) log_admin_action('greenstand_watch_clear', null, $username);
    return ['ok' => true, 'username' => $username, 'watchlist' => gs_admin_watchlist()];
}

/* ======================================================================
   FREIN DE CADENCE SUR L'ENDPOINT
   ======================================================================
   Chaque sauvegarde écrit dans deux tables et relit le barème. Le jeu, lui,
   n'a besoin que d'une poignée de requêtes par fenêtre : une sauvegarde
   toutes les cinq secondes, un dépôt, un classement de temps en temps. Le
   plafond ci-dessous est très au-dessus de ce rythme — il n'arrête qu'une
   boucle de script.
   ====================================================================== */

// Fenêtre d'observation, en secondes, et nombre de requêtes tolérées dedans.
if (!defined('GREENSTAND_RATE_WINDOW')) define('GREENSTAND_RATE_WINDOW', 10);
if (!defined('GREENSTAND_RATE_MAX'))    define('GREENSTAND_RATE_MAX', 40);
// Mise à l'écart quand le plafond est crevé, en secondes.
if (!defined('GREENSTAND_RATE_PAUSE'))  define('GREENSTAND_RATE_PAUSE', 20);

/**
 * Retourne le nombre de secondes à attendre, ou 0 si la requête peut passer.
 * Compté par joueur (la session est l'identité de l'endpoint), jamais par IP :
 * deux joueurs derrière la même box ne se gênent pas.
 */
function greenstand_rate_wait(string $username): int {
    // Volontairement l'adresse, pas greenstand_autoclick_exempt() : éteindre
    // l'anti auto-clicker ne doit pas ouvrir l'endpoint aux boucles de script.
    if (greenstand_ip_dispensee()) return 0;       // poste dispensé

    $now  = time();
    $stmt = db()->prepare('SELECT window_start, hits, blocked_until, trips FROM greenstand_rate WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if (!$row) {
        db()->prepare('INSERT INTO greenstand_rate (username, window_start, hits) VALUES (?, NOW(), 1)
                       ON DUPLICATE KEY UPDATE hits = hits + 1')->execute([$username]);
        return 0;
    }

    $bloque = $row['blocked_until'] ? strtotime((string) $row['blocked_until']) : 0;
    if ($bloque > $now) return $bloque - $now;

    $debut = strtotime((string) $row['window_start']);
    $hits  = (int) $row['hits'] + 1;
    $trips = (int) $row['trips'];

    if (($now - $debut) >= GREENSTAND_RATE_WINDOW) {
        // Fenêtre écoulée : on repart à zéro.
        db()->prepare('UPDATE greenstand_rate SET window_start = NOW(), hits = 1, blocked_until = NULL WHERE username = ?')
            ->execute([$username]);
        return 0;
    }

    if ($hits > GREENSTAND_RATE_MAX) {
        $trips += 1;
        // La pause s'allonge à chaque récidive, plafonnée à cinq minutes.
        $pause = min(GREENSTAND_RATE_PAUSE * $trips, 300);
        db()->prepare('UPDATE greenstand_rate SET hits = ?, blocked_until = ?, trips = ? WHERE username = ?')
            ->execute([$hits, date('Y-m-d H:i:s', $now + $pause), $trips, $username]);
        error_log(sprintf('[GreenStand] Cadence de requêtes dépassée : %s — %d requêtes en %ds, pause de %ds.',
            $username, $hits, GREENSTAND_RATE_WINDOW, $pause));
        return $pause;
    }

    db()->prepare('UPDATE greenstand_rate SET hits = ? WHERE username = ?')->execute([$hits, $username]);
    return 0;
}

/* ======================================================================
   LES OBJECTIFS DU JOUR
   ======================================================================
   Trois objectifs tirés chaque jour, dans le même vocabulaire que les badges :
   une statistique, un seuil. La différence est qu'ils portent sur la JOURNÉE,
   pas sur toute la partie : on retient les compteurs au moment du tirage, et
   la progression est la différence depuis.

   La récompense est créditée PAR LE SERVEUR, directement en banque. Le client
   ne l'annonce jamais et ne peut pas la réclamer : il demande l'état, le
   serveur constate et paie. C'est ce qui la distingue d'un revenu de jeu, qui
   lui passe par le plafond de dépôt.

   Le montant suit la production du stand — deux minutes de revenu — pour rester
   intéressant à tous les niveaux, mais reste borné par GREENSTAND_MAX_EUR_PER_SEC :
   le €/s est calculé depuis les niveaux annoncés par le navigateur, et une
   sauvegarde gonflée ne doit pas se transformer en récompense gonflée.
   ====================================================================== */

if (!defined('GREENSTAND_DAILY_COUNT'))  define('GREENSTAND_DAILY_COUNT', 3);
// Récompense : ce que le stand produit en autant de secondes.
if (!defined('GREENSTAND_DAILY_SECONDS')) define('GREENSTAND_DAILY_SECONDS', 120);
// Plancher, pour qu'un débutant touche quelque chose de visible.
if (!defined('GREENSTAND_DAILY_FLOOR'))   define('GREENSTAND_DAILY_FLOOR', 50.0);

/**
 * Le vivier d'objectifs. Chaque entrée dit sur quelle statistique elle porte et
 * quel seuil viser ; le seuil peut dépendre du niveau du stand pour rester à la
 * portée d'un débutant sans devenir dérisoire pour un joueur avancé.
 */
function greenstand_daily_pool(int $niveau, float $perSec): array {
    $n = max(1, $niveau);
    // Les seuils grandissent avec le stand, mais sont PLAFONNÉS : un client arrive
    // toutes les onze secondes en moyenne, en demander soixante revient à exiger
    // une présence continue impossible à tenir. Un objectif hors de portée ne
    // motive personne, il donne juste l'impression que le jeu est cassé.
    return [
        ['id'=>'clients', 'stat'=>'clientsServed',       'libelle'=>'Servir %d clients au comptoir',
         'seuil'=> min(30, 5 + $n * 3)],
        ['id'=>'ventes',  'stat'=>'lifetimeManualSales', 'libelle'=>'Faire %d ventes à la main',
         'seuil'=> min(1500, 100 + $n * 60)],
        ['id'=>'achats',  'stat'=>'lifetimeItemsBought', 'libelle'=>'Acheter %d améliorations',
         'seuil'=> min(20, 3 + intdiv($n, 2))],
        ['id'=>'crit',    'stat'=>'critCount',           'libelle'=>'Décrocher %d ventes critiques',
         'seuil'=> min(40, 5 + $n * 2)],
        ['id'=>'euros',   'stat'=>'lifetimeTotalEarned', 'libelle'=>'Gagner %s € dans la journée',
         'seuil'=> max(500.0, $perSec * 600), 'argent'=>true],
    ];
}

/** Les compteurs du jour, tels qu'on les compare. */
function greenstand_daily_snapshot(?array $save): array {
    $out = [];
    foreach (['clientsServed','lifetimeManualSales','lifetimeItemsBought','critCount','lifetimeTotalEarned'] as $k) {
        $out[$k] = (float) ($save[$k] ?? 0);
    }
    return $out;
}

/**
 * L'état des objectifs du jour. Tire ceux du jour si besoin, mesure la
 * progression, crédite ce qui vient d'être atteint, et retourne le tout.
 */
function greenstand_daily(string $username): array {
    $save   = greenstand_load_save($username);
    // Le meilleur comptoir, pas celui qu'on tient : changer de stand ne doit pas
    // faire retomber les objectifs du jour au niveau d'un débutant.
    $niveau = intdiv(greenstand_total_niveaux($save), 8);   // grosse maille du « niveau de stand »
    $perSec = greenstand_per_sec($save, $username);
    $today  = date('Y-m-d');

    $stmt = db()->prepare('SELECT * FROM greenstand_daily WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    // Une remise à zéro (ou une franchise) fait retomber les compteurs SOUS la photo
    // prise au tirage. Les objectifs deviennent alors inatteignables et le joueur voit
    // « Servir 53 clients » au-dessus d'un stand de niveau 1, comme si tout buguait.
    // Dans ce cas on retire, avec le niveau du moment.
    $repartiDeZero = false;
    if ($row) {
        $ancienneBase = json_decode((string) $row['base_json'], true) ?: [];
        $maintenant   = greenstand_daily_snapshot($save);
        foreach ($ancienneBase as $cle => $valeur) {
            if (($maintenant[$cle] ?? 0) + 0.001 < (float) $valeur) { $repartiDeZero = true; break; }
        }
    }

    // Nouveau jour, première visite, ou progression repartie de zéro : on tire trois
    // objectifs et on note les compteurs de départ. C'est cette photo qui rend la
    // mesure « journalière ».
    if (!$row || (string) $row['day'] !== $today || $repartiDeZero) {
        $pool = greenstand_daily_pool($niveau, $perSec);
        shuffle($pool);
        $choisis = array_slice($pool, 0, GREENSTAND_DAILY_COUNT);
        $base    = greenstand_daily_snapshot($save);

        db()->prepare("
            INSERT INTO greenstand_daily (username, day, base_json, objectifs_json, paid_json, updated_at)
            VALUES (?, ?, ?, ?, '[]', NOW())
            ON DUPLICATE KEY UPDATE day = VALUES(day), base_json = VALUES(base_json),
                                    objectifs_json = VALUES(objectifs_json), paid_json = '[]', updated_at = NOW()
        ")->execute([$username, $today,
                     json_encode($base, JSON_UNESCAPED_UNICODE),
                     json_encode($choisis, JSON_UNESCAPED_UNICODE)]);

        $stmt->execute([$username]);
        $row = $stmt->fetch();
    }

    $base      = json_decode((string) $row['base_json'], true) ?: [];
    $objectifs = json_decode((string) $row['objectifs_json'], true) ?: [];
    $payes     = json_decode((string) $row['paid_json'], true) ?: [];
    $courant   = greenstand_daily_snapshot($save);

    // Une récompense vaut deux minutes de production, bornée par le débit maximum
    // prévu par le jeu : le €/s vient des niveaux annoncés par le navigateur.
    $recompense = max(GREENSTAND_DAILY_FLOOR,
                      min($perSec, (float) GREENSTAND_MAX_EUR_PER_SEC) * GREENSTAND_DAILY_SECONDS);

    $sortie = [];
    $aCrediter = 0.0;
    foreach ($objectifs as $o) {
        $stat  = (string) ($o['stat'] ?? '');
        $seuil = (float) ($o['seuil'] ?? 0);
        // Un compteur qui recule (franchise, remise à zéro) ne doit pas donner une
        // progression négative ni bloquer l'objectif : on repart de zéro.
        $fait  = max(0.0, ($courant[$stat] ?? 0) - ($base[$stat] ?? 0));
        $ok    = $seuil > 0 && $fait >= $seuil;
        $paye  = in_array($o['id'], $payes, true);

        if ($ok && !$paye) {
            $aCrediter += $recompense;
            $payes[]    = $o['id'];
            $paye       = true;
            if (function_exists('log_admin_action')) log_admin_action('greenstand_daily_paid', null, $username . ':' . $o['id']);
        }

        $sortie[] = [
            'id'      => (string) $o['id'],
            'texte'   => !empty($o['argent'])
                         ? sprintf((string) $o['libelle'], number_format($seuil, 0, ',', ' '))
                         : sprintf((string) $o['libelle'], (int) $seuil),
            'fait'    => round($fait, 2),
            'seuil'   => round($seuil, 2),
            'pct'     => $seuil > 0 ? min(100, round($fait / $seuil * 100)) : 0,
            'termine' => $ok,
            'paye'    => $paye,
        ];
    }

    if ($aCrediter > 0) {
        db()->prepare("
            UPDATE users
            SET greenstand_eur_bank = LEAST(greenstand_eur_bank + ?, 9999999999999.99)
            WHERE username = ?
        ")->execute([$aCrediter, $username]);
    }
    db()->prepare('UPDATE greenstand_daily SET paid_json = ?, updated_at = NOW() WHERE username = ?')
        ->execute([json_encode(array_values(array_unique($payes))), $username]);

    return [
        'objectifs'  => $sortie,
        'recompense' => round($recompense, 2),
        'credite'    => round($aCrediter, 2),
        'bank_eur'   => greenstand_bank($username),
        // Secondes restantes avant le prochain tirage, pour l'afficher au joueur.
        'reste'      => strtotime('tomorrow') - time(),
    ];
}

/* ======================================================================
   LES BADGES (succès) — modifiables depuis le panel admin
   ======================================================================
   Avant, la liste vivait en dur dans le JavaScript de la page : la
   toucher demandait de rouvrir le fichier du jeu. Elle vit maintenant
   dans data/greenstand_badges.json, et le panel admin l'édite.

   Une condition n'est PAS du code : c'est un couple (statistique, seuil)
   choisi dans une liste fermée. Personne ne peut donc injecter du code
   exécutable en créant un badge depuis le panel.
   ====================================================================== */

if (!defined('GS_BADGES_FILE')) define('GS_BADGES_FILE', __DIR__ . '/data/greenstand_badges.json');

/** Les statistiques sur lesquelles un badge peut se déclencher. Liste fermée. */
function gs_badge_stats(): array {
    return [
        'lifetimeManualSales'  => 'Ventes à la main (à vie)',
        'lifetimeItemsBought'  => 'Améliorations achetées (à vie)',
        'lifetimeTotalEarned'  => 'Euros gagnés (à vie)',
        'critCount'            => 'Ventes critiques',
        'clientsServed'        => 'Clients servis au comptoir',
        'standLevel'           => 'Niveau du stand',
        'managerLevels'        => 'Niveaux de gérants cumulés',
        'franchises'           => 'Franchises ouvertes',
        'clientsLost'          => 'Clients partis fâchés',
    ];
}

/** Les badges livrés d'origine, servant de graine au premier démarrage. */
function gs_badges_defaut(): array {
    return [
        ['id'=>'first_sale', 'name'=>'Première vente',   'desc'=>'Vends une fois à la main.',      'emoji'=>'🌿', 'stat'=>'lifetimeManualSales', 'value'=>1,      'cash'=>5,    'mult'=>0],
        ['id'=>'sales_100',  'name'=>'Vendeur aguerri',  'desc'=>'100 ventes manuelles.',          'emoji'=>'🤝', 'stat'=>'lifetimeManualSales', 'value'=>100,    'cash'=>200,  'mult'=>0],
        ['id'=>'sales_1000', 'name'=>'Machine à vendre', 'desc'=>'1 000 ventes manuelles.',        'emoji'=>'⚡', 'stat'=>'lifetimeManualSales', 'value'=>1000,   'cash'=>0,    'mult'=>0.02],
        ['id'=>'first_item', 'name'=>'Premier arrivage', 'desc'=>'Achète ta première amélioration.','emoji'=>'📦','stat'=>'lifetimeItemsBought', 'value'=>1,      'cash'=>10,   'mult'=>0],
        ['id'=>'items_20',   'name'=>'Stand bien garni', 'desc'=>'20 améliorations achetées.',     'emoji'=>'🛍️', 'stat'=>'lifetimeItemsBought', 'value'=>20,     'cash'=>500,  'mult'=>0],
        ['id'=>'items_50',   'name'=>'Empire naissant',  'desc'=>'50 améliorations achetées.',     'emoji'=>'🏪', 'stat'=>'lifetimeItemsBought', 'value'=>50,     'cash'=>0,    'mult'=>0.03],
        ['id'=>'earn_1k',    'name'=>'Premier millier',  'desc'=>'1 000 € de total gagné.',        'emoji'=>'💶', 'stat'=>'lifetimeTotalEarned', 'value'=>1000,   'cash'=>100,  'mult'=>0],
        ['id'=>'earn_10k',   'name'=>'Dix mille',        'desc'=>'10 000 € de total gagné.',       'emoji'=>'💰', 'stat'=>'lifetimeTotalEarned', 'value'=>10000,  'cash'=>1000, 'mult'=>0],
        ['id'=>'earn_100k',  'name'=>'Cent mille',       'desc'=>'100 000 € de total gagné.',      'emoji'=>'👑', 'stat'=>'lifetimeTotalEarned', 'value'=>100000, 'cash'=>0,    'mult'=>0.05],
        ['id'=>'crit_10',    'name'=>'Chanceux',         'desc'=>'10 ventes critiques.',           'emoji'=>'✨', 'stat'=>'critCount',           'value'=>10,     'cash'=>300,  'mult'=>0],
        ['id'=>'stand_lvl5', 'name'=>'Boutique complète','desc'=>'Atteins le niveau 5 du stand.',  'emoji'=>'🏆', 'stat'=>'standLevel',          'value'=>5,      'cash'=>2000, 'mult'=>0],
        ['id'=>'first_mgr',  'name'=>'Premier gérant',   'desc'=>'Embauche ton premier gérant.',   'emoji'=>'🧑‍💼','stat'=>'managerLevels',      'value'=>1,      'cash'=>1000, 'mult'=>0],
        ['id'=>'managers_10','name'=>'Petite équipe',    'desc'=>'10 niveaux de gérants cumulés.', 'emoji'=>'👥', 'stat'=>'managerLevels',       'value'=>10,     'cash'=>0,    'mult'=>0.04],
        ['id'=>'first_fr',   'name'=>'Première franchise','desc'=>"Ouvre ta première franchise.",  'emoji'=>'🍁', 'stat'=>'franchises',          'value'=>1,      'cash'=>0,    'mult'=>0.05],
    ];
}

/** Nettoie un badge venu du fichier ou du formulaire admin. Renvoie null s'il est inexploitable. */
function gs_badge_normalise(array $b): ?array {
    $id = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($b['id'] ?? ''))));
    if ($id === '' || strlen($id) > 40) return null;
    $stat = (string) ($b['stat'] ?? '');
    if (!isset(gs_badge_stats()[$stat])) return null;

    return [
        'id'    => $id,
        'name'  => mb_substr(trim((string) ($b['name'] ?? $id)), 0, 60),
        'desc'  => mb_substr(trim((string) ($b['desc'] ?? '')), 0, 160),
        'emoji' => mb_substr(trim((string) ($b['emoji'] ?? '🏅')), 0, 8),
        'stat'  => $stat,
        'value' => max(0.0, min((float) ($b['value'] ?? 0), 1e15)),
        'cash'  => max(0.0, min((float) ($b['cash']  ?? 0), 1e12)),
        'mult'  => max(0.0, min((float) ($b['mult']  ?? 0), 1.0)),
    ];
}


/* ======================================================================
   LES MILLE SUCCÈS
   ======================================================================
   Les badges écrits à la main (data/greenstand_badges.json, éditables depuis le
   panel) restent le cœur : ce sont ceux qui portent une vraie récompense et une
   vraie image. À côté, le jeu en GÉNÈRE mille : neuf familles, chacune une
   échelle de paliers sur une statistique, du premier pas jusqu'à des chiffres
   qu'on n'atteindra jamais.

   POURQUOI GÉNÉRÉS, ET PAS ÉCRITS. Mille badges dans un fichier JSON, c'est un
   fichier que personne ne relit et qu'on ne peut plus rééquilibrer. Ici, changer
   une échelle change cent paliers d'un coup, et le total est vérifié par un test.

   CE QU'ILS RAPPORTENT — le point délicat. Le bonus permanent des succès entre
   dans le PLAFOND DE DÉPÔT (greenstand_deposit_cap prend le bonus maximum
   possible) : mille succès à +1 % chacun rendraient ce plafond dix fois trop
   large, et l'anti-triche avec. Les paliers générés ne donnent donc presque rien :
   un peu d'argent sur les paliers ronds, et +0,2 % seulement sur un palier sur
   dix. Le total de tous les succès générés est borné à +10 % (voir le test
   tests/test_succes.php, qui refait la somme).

   LES IMAGES. Aucune image nouvelle : chaque famille réutilise un visuel du jeu
   (`img`), et l'admin peut toujours poser un `badge_<id>` par-dessus.
   ====================================================================== */

/** Les rangs, du premier palier au dernier. Le nom d'un succès en vient. */
function gs_succes_rangs(): array {
    return ['Débutant', 'Apprenti', 'Habitué', 'Sérieux', 'Confirmé', 'Chevronné',
            'Vétéran', 'Maître', 'Grand Maître', 'Légende', 'Mythe', 'Immortel'];
}

/**
 * Les familles de succès générés. Pour chacune : la statistique suivie, combien
 * de paliers, et comment on passe d'un palier au suivant.
 *
 *   'depart'  la valeur du premier palier
 *   'facteur' de combien on multiplie à chaque palier (échelle géométrique)
 *   'pas'     ou, pour les statistiques bornées (niveaux, franchises), le pas fixe
 *   'img'     le visuel du jeu qui sert d'icône (aucune image nouvelle)
 */
function gs_succes_familles(): array {
    return [
        ['cle'=>'v', 'stat'=>'lifetimeManualSales', 'n'=>150, 'depart'=>5,    'facteur'=>1.20,
         'nom'=>'Vendeur',      'unite'=>'ventes à la main',       'emoji'=>'🖐️', 'img'=>'nug_big'],
        // Facteur 1,20 et pas davantage : à 1,35, le dernier palier demandait 10^22 €,
        // très au-delà du plafond de gs_badge_normalise() (10^15) — les vingt derniers
        // paliers auraient tous été ramenés au même seuil, donc identiques.
        ['cle'=>'e', 'stat'=>'lifetimeTotalEarned', 'n'=>150, 'depart'=>100,  'facteur'=>1.20,
         'nom'=>'Fortune',      'unite'=>'gagnés au total',        'emoji'=>'💶', 'img'=>'banner', 'argent'=>true],
        ['cle'=>'c', 'stat'=>'clientsServed',       'n'=>140, 'depart'=>3,    'facteur'=>1.18,
         'nom'=>'Comptoir',     'unite'=>'clients servis',         'emoji'=>'🤝', 'img'=>'client'],
        ['cle'=>'k', 'stat'=>'critCount',           'n'=>130, 'depart'=>2,    'facteur'=>1.19,
         'nom'=>'Coup de maître', 'unite'=>'ventes critiques',     'emoji'=>'⚡', 'img'=>'skiteelz'],
        ['cle'=>'a', 'stat'=>'lifetimeItemsBought', 'n'=>120, 'depart'=>2,    'facteur'=>1.14,
         'nom'=>'Équipement',   'unite'=>'améliorations achetées', 'emoji'=>'🧰', 'img'=>'tin_nugs'],
        ['cle'=>'s', 'stat'=>'standLevel',          'n'=>115, 'pas'=>2,       'depart'=>2,
         'nom'=>'Le stand',     'unite'=>'de niveau de stand',     'emoji'=>'🏪', 'img'=>'bag'],
        ['cle'=>'g', 'stat'=>'managerLevels',       'n'=>100, 'pas'=>2,       'depart'=>1,
         'nom'=>'Patron',       'unite'=>'niveaux de gérants',     'emoji'=>'🧑‍💼', 'img'=>'scale'],
        ['cle'=>'f', 'stat'=>'franchises',          'n'=>60,  'pas'=>1,       'depart'=>1,
         'nom'=>'Franchise',    'unite'=>'franchises ouvertes',    'emoji'=>'🍁', 'img'=>'jar'],
        ['cle'=>'p', 'stat'=>'clientsLost',         'n'=>35,  'depart'=>5,    'facteur'=>1.45,
         'nom'=>'Mauvaise réputation', 'unite'=>'clients partis fâchés', 'emoji'=>'💨', 'img'=>'joint'],
    ];
}

/**
 * L'échelle complète d'une famille : tous ses seuils, dans l'ordre.
 *
 * Calculée d'un bloc et pas palier par palier, parce qu'un seuil dépend du
 * précédent : arrondir 5, puis 7,25, puis 10,5 à deux chiffres significatifs
 * donne 5, 7 puis 10 — mais sur une échelle plus raide, deux paliers voisins
 * tombent sur le même chiffre rond, et le jeu se retrouve avec deux succès
 * identiques qu'on décroche en même temps. On force donc chaque palier à
 * dépasser le précédent.
 */
function gs_succes_seuils(array $f): array {
    static $cache = [];
    $cle = $f['cle'];
    if (isset($cache[$cle])) return $cache[$cle];

    $out = [];
    $precedent = 0.0;
    for ($i = 0; $i < (int) $f['n']; $i++) {
        if (isset($f['pas'])) {
            $v = (float) ($f['depart'] + $i * $f['pas']);
        } else {
            $v = $f['depart'] * pow($f['facteur'], $i);
            // Deux chiffres significatifs : 1 234 567 n'est pas un objectif,
            // 1 200 000 si.
            $rond = pow(10, max(0, (int) floor(log10(max(1, $v))) - 1));
            $v = round($v / $rond) * $rond;
        }
        $v = max($v, $precedent + 1);
        $out[] = (float) $v;
        $precedent = $v;
    }
    return $cache[$cle] = $out;
}

/** Le seuil du palier n° $i (0-indexé) d'une famille. */
function gs_succes_seuil(array $f, int $i): float {
    $seuils = gs_succes_seuils($f);
    return $seuils[$i] ?? (float) $f['depart'];
}

/**
 * Les mille succès générés. Déterministe : la même liste à chaque appel, sur
 * chaque serveur — c'est indispensable, les identifiants sont enregistrés dans
 * les sauvegardes des joueurs.
 */
function gs_badges_generes(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $rangs = gs_succes_rangs();
    $out = [];
    foreach (gs_succes_familles() as $f) {
        $n = (int) $f['n'];
        for ($i = 0; $i < $n; $i++) {
            $seuil = gs_succes_seuil($f, $i);
            $rang  = $rangs[min(count($rangs) - 1, (int) floor($i / max(1, $n / count($rangs))))];
            $valeur = !empty($f['argent'])
                ? number_format($seuil, 0, ',', ' ') . ' €'
                : number_format($seuil, 0, ',', ' ');

            // Un palier sur dix donne un petit bonus permanent ; les autres, rien
            // ou un peu d'argent. Voir l'avertissement en tête de section.
            $mult = ($i % 10 === 9) ? 0.001 : 0.0;
            $cash = ($i % 5 === 4)  ? round($seuil * 0.05, 2) : 0.0;

            $out[] = [
                'id'    => $f['cle'] . $i,
                'name'  => $f['nom'] . ' ' . $rang,
                // Court exprès : cette phrase s'affiche dans une carte de 158 px.
                'desc'  => $valeur . ' ' . $f['unite'],
                'emoji' => $f['emoji'],
                'img'   => $f['img'],
                'stat'  => $f['stat'],
                'value' => $seuil,
                'cash'  => min($cash, 1e9),
                'mult'  => $mult,
                'auto'  => true,
                'famille' => $f['nom'],
            ];
        }
    }
    return $cache = $out;
}

/** La liste des badges. Créée depuis gs_badges_defaut() au premier appel. */
/**
 * Les badges ÉCRITS À LA MAIN : ceux du fichier, que le panel admin crée et
 * modifie, et les seuls qui ont un emplacement d'image à eux.
 *
 * Séparés des mille paliers générés parce que tout ce qui s'adresse à l'admin ne
 * parle que de ceux-là : lui proposer mille emplacements d'images vides ou mille
 * lignes à modifier ne rendrait service à personne.
 */
function gs_badges_mains(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $brut = is_file(GS_BADGES_FILE) ? json_decode((string) file_get_contents(GS_BADGES_FILE), true) : null;
    if (!is_array($brut) || !$brut) {
        $brut = gs_badges_defaut();
        gs_badges_ecrire($brut);
    }
    $cache = [];
    $vus = [];
    foreach ($brut as $b) {
        if (!is_array($b)) continue;
        $n = gs_badge_normalise($b);
        if ($n && !isset($vus[$n['id']])) { $cache[] = $n; $vus[$n['id']] = true; }
    }
    return $cache;
}

function gs_badges(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $brut = [];
    $cache = [];
    $vus = [];
    foreach (gs_badges_mains() as $n) { $cache[] = $n; $vus[$n['id']] = true; }
    // Puis les mille paliers générés. Les badges écrits à la main passent en
    // PREMIER et gagnent en cas d'identifiant identique : ce sont eux que l'admin
    // a créés, ils ne doivent jamais être écrasés par un palier automatique.
    foreach (gs_badges_generes() as $b) {
        $n = gs_badge_normalise($b);
        if (!$n || isset($vus[$n['id']])) continue;
        // gs_badge_normalise() ne connaît que les champs d'un badge d'admin : on
        // remet ceux qui n'appartiennent qu'aux succès générés.
        $n['img']     = (string) $b['img'];
        $n['auto']    = true;
        $n['famille'] = (string) $b['famille'];
        $cache[] = $n;
        $vus[$n['id']] = true;
    }
    return $cache;
}

/**
 * Ce que la page reçoit : les badges d'admin en entier, et les mille paliers sous
 * forme compacte.
 *
 * Envoyer les mille en entier, c'est 208 Ko de JSON dans CHAQUE page — un HTML
 * dynamique n'est pas mis en cache, le joueur les retéléchargerait à chaque
 * visite. Sous forme de lignes courtes [id, stat, seuil, argent, bonus, famille,
 * rang] et de deux petites tables, il en reste 36 Ko, et le navigateur recompose
 * les noms lui-même.
 */
function gs_badges_pour_page(): array {
    $stats = array_keys(gs_badge_stats());
    $rangs = gs_succes_rangs();
    $familles = gs_succes_familles();
    $indexFamille = [];
    foreach ($familles as $i => $f) $indexFamille[$f['cle']] = $i;

    $autos = [];
    foreach ($familles as $iF => $f) {
        $n = (int) $f['n'];
        for ($i = 0; $i < $n; $i++) {
            $id = $f['cle'] . $i;
            $seuil = gs_succes_seuil($f, $i);
            $autos[] = [
                $id,
                array_search($f['stat'], $stats, true),
                min($seuil, 1e15),
                ($i % 5 === 4)  ? round($seuil * 0.05, 2) : 0,
                ($i % 10 === 9) ? 0.001 : 0,
                $iF,
                min(count($rangs) - 1, (int) floor($i / max(1, $n / count($rangs)))),
            ];
        }
    }

    return [
        'mains'    => gs_badges_mains(),
        'autos'    => $autos,
        'stats'    => $stats,
        'rangs'    => $rangs,
        'familles' => array_map(fn($f) => [
            'nom' => $f['nom'], 'unite' => $f['unite'], 'emoji' => $f['emoji'],
            'img' => $f['img'], 'argent' => !empty($f['argent']),
        ], $familles),
    ];
}

function gs_badges_ecrire(array $badges): bool {
    $dir = dirname(GS_BADGES_FILE);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
    $tmp = GS_BADGES_FILE . '.tmp';
    $ok = file_put_contents($tmp, json_encode(array_values($badges),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
    return $ok && rename($tmp, GS_BADGES_FILE);
}

/** Crée ou modifie un badge (le même id écrase). */
function gs_admin_badge_save(array $champs): array {
    $n = gs_badge_normalise($champs);
    if (!$n) return ['ok' => false, 'error' => "Identifiant ou statistique invalide."];

    $badges = gs_badges();
    $trouve = false;
    foreach ($badges as $i => $b) {
        if ($b['id'] === $n['id']) { $badges[$i] = $n; $trouve = true; break; }
    }
    if (!$trouve) $badges[] = $n;
    if (!gs_badges_ecrire($badges)) return ['ok' => false, 'error' => "Écriture de data/greenstand_badges.json impossible."];

    if (function_exists('log_admin_action')) log_admin_action('greenstand_badge_save', null, $n['id']);
    return ['ok' => true, 'created' => !$trouve, 'badges' => gs_badges_relire()];
}

function gs_admin_badge_delete(string $id): array {
    $badges = array_values(array_filter(gs_badges(), fn($b) => $b['id'] !== $id));
    if (!gs_badges_ecrire($badges)) return ['ok' => false, 'error' => "Écriture impossible."];
    @unlink(GS_ASSET_DIR . '/badge_' . preg_replace('/[^a-z0-9_]/', '', $id) . '.webp');
    if (function_exists('log_admin_action')) log_admin_action('greenstand_badge_delete', null, $id);
    return ['ok' => true, 'badges' => gs_badges_relire()];
}

/** Relit le fichier en contournant le cache statique de gs_badges(). */
function gs_badges_relire(): array {
    $brut = is_file(GS_BADGES_FILE) ? json_decode((string) file_get_contents(GS_BADGES_FILE), true) : [];
    $out = [];
    foreach (is_array($brut) ? $brut : [] as $b) {
        if (!is_array($b)) continue;
        $n = gs_badge_normalise($b);
        if ($n) $out[] = $n;
    }
    return $out;
}
