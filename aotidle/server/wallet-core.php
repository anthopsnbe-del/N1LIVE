<?php
declare(strict_types=1);

/* Dépôt du bataillon : la part d'économie qui vit sur le serveur.
 *
 * Le jeu reste hors ligne d'abord — or, cristaux et équipement de la campagne
 * continuent de vivre dans la sauvegarde locale. Déplacer toute l'économie
 * casserait le jeu sans réseau et invaliderait les sauvegardes existantes.
 *
 * Ce module ouvre à côté un **dépôt** dont le serveur est seul maître : il
 * n'est alimenté que par ce que le serveur a lui-même accordé (boss mondial,
 * guerres de clans, fins de saison). C'est ce qui rend le marché possible :
 * rien de ce qui s'y échange ne peut avoir été fabriqué par un client.
 *
 * Le sens est unique : dépôt -> sauvegarde locale. On ne remonte jamais des
 * cristaux locaux vers le serveur, puisqu'ils ne sont pas vérifiables.
 */

const WALLET_LOG_KEEP = 40;   // lignes d'historique conservées par joueur

function wallet_install(PDO $db): void
{
    $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $tables = [
        'social_wallet (user_id INTEGER PRIMARY KEY, crystals BIGINT NOT NULL DEFAULT 0,
          earned BIGINT NOT NULL DEFAULT 0, updated_at BIGINT NOT NULL DEFAULT 0)',
        'social_wallet_log (id VARCHAR(32) PRIMARY KEY, user_id INTEGER NOT NULL, delta BIGINT NOT NULL,
          reason VARCHAR(48) NOT NULL, created_at BIGINT NOT NULL)',
        'social_items (id VARCHAR(32) PRIMARY KEY, owner_id INTEGER NOT NULL, slot VARCHAR(16) NOT NULL,
          name VARCHAR(48) NOT NULL, rarity VARCHAR(16) NOT NULL, power INTEGER NOT NULL,
          origin VARCHAR(24) NOT NULL, listed INTEGER NOT NULL DEFAULT 0,
          withdrawn INTEGER NOT NULL DEFAULT 0, created_at BIGINT NOT NULL)'
    ];
    foreach ($tables as $sql) {
        $db->exec('CREATE TABLE IF NOT EXISTS ' . $sql . $suffix);
    }
}

function wallet_row(PDO $db, int $user): array
{
    $row = sq($db, 'SELECT * FROM social_wallet WHERE user_id = ?', [$user])->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        sq($db, 'INSERT INTO social_wallet (user_id, updated_at) VALUES (?,?)', [$user, social_now()]);
        return ['user_id' => $user, 'crystals' => 0, 'earned' => 0, 'updated_at' => social_now()];
    }
    return $row;
}

/* Tout mouvement passe par ici : le solde ne descend jamais sous zéro et
   l'historique dit d'où vient chaque cristal. */
function wallet_add(PDO $db, int $user, int $delta, string $reason, int $now): int
{
    $row = wallet_row($db, $user);
    $balance = (int) $row['crystals'] + $delta;
    if ($balance < 0) {
        social_error('Solde insuffisant dans le dépôt.');
    }
    $earned = (int) $row['earned'] + max(0, $delta);
    sq($db, 'UPDATE social_wallet SET crystals = ?, earned = ?, updated_at = ? WHERE user_id = ?',
        [$balance, $earned, $now, $user]);
    sq($db, 'INSERT INTO social_wallet_log (id, user_id, delta, reason, created_at) VALUES (?,?,?,?,?)',
        [bin2hex(random_bytes(16)), $user, $delta, $reason, $now]);
    // L'historique sert à expliquer un solde, pas à tenir une comptabilité.
    $old = sq($db, 'SELECT id FROM social_wallet_log WHERE user_id = ? ORDER BY created_at DESC, id LIMIT 500 OFFSET ?',
        [$user, WALLET_LOG_KEEP])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($old as $id) {
        sq($db, 'DELETE FROM social_wallet_log WHERE id = ?', [$id]);
    }
    return $balance;
}

function wallet_history(PDO $db, int $user): array
{
    $rows = sq($db, 'SELECT delta, reason, created_at FROM social_wallet_log WHERE user_id = ?
                     ORDER BY created_at DESC, id DESC LIMIT 12', [$user])->fetchAll(PDO::FETCH_ASSOC);
    return array_map(fn($row) => [
        'delta' => (int) $row['delta'],
        'reason' => $row['reason'],
        'at' => (int) $row['created_at']
    ], $rows);
}

/* Équipement émis par le serveur. Il n'existe que dans le dépôt tant que le
   joueur ne le rapatrie pas ; c'est la seule marchandise échangeable. */
const ITEM_SLOTS = [
    'arme' => ['Lames du bataillon', 'Lames de Ragako', 'Lames du Fondateur'],
    'armure' => ['Harnais renforcé', 'Harnais des Ailes', 'Harnais légendaire'],
    'accessoire' => ['Insigne du bataillon', 'Réserve de gaz', 'Sceau du commandant'],
    'amulette' => ['Cape du bataillon', 'Cape du Commandant', 'Cape du Fondateur']
];
const ITEM_RARITIES = [
    ['rare', 1.8, 1],
    ['épique', 3.2, 1],
    ['légendaire', 6.0, 2],
    ['mythique', 10.0, 2]
];

/* La qualité suit la contribution : un assaut isolé donne une pièce rare, un
   podium peut donner une mythique. La puissance, elle, suit la progression du
   joueur — comme le butin de campagne — pour qu'un cadet ne reçoive pas un
   équipement de fin de partie. */
function item_grant(PDO $db, array $player, float $share, bool $winner, string $origin, int $now): ?array
{
    $user = (int) $player['id'];
    $roll = $share * 100 + ($winner ? 12 : 0);
    $index = $roll >= 45 ? 3 : ($roll >= 22 ? 2 : ($roll >= 8 ? 1 : 0));
    if (!$winner && $index === 0 && $share < 0.01) {
        return null;   // une contribution symbolique ne donne pas de butin
    }
    $rarity = ITEM_RARITIES[$index];
    $slots = array_keys(ITEM_SLOTS);
    $slot = $slots[random_int(0, count($slots) - 1)];
    $names = ITEM_SLOTS[$slot];
    $power = (int) round((12 + ((int) $player['power']) * 0.9) * $rarity[1] * (1 + $share));
    $item = [
        'id' => bin2hex(random_bytes(16)),
        'owner_id' => $user,
        'slot' => $slot,
        'name' => $names[min(count($names) - 1, $rarity[2])],
        'rarity' => $rarity[0],
        'power' => max(20, $power),
        'origin' => $origin,
        'listed' => 0,
        'withdrawn' => 0,
        'created_at' => $now
    ];
    sq($db, 'INSERT INTO social_items (id, owner_id, slot, name, rarity, power, origin, created_at)
             VALUES (?,?,?,?,?,?,?,?)',
        [$item['id'], $user, $slot, $item['name'], $item['rarity'], $item['power'], $origin, $now]);
    return $item;
}

function item_public(array $row): array
{
    return [
        'id' => $row['id'],
        'slot' => $row['slot'],
        'name' => $row['name'],
        'rarity' => $row['rarity'],
        'power' => (int) $row['power'],
        'origin' => $row['origin'],
        'listed' => (int) $row['listed'] === 1
    ];
}

function wallet_items(PDO $db, int $user): array
{
    $rows = sq($db, 'SELECT * FROM social_items WHERE owner_id = ? AND withdrawn = 0
                     ORDER BY created_at DESC LIMIT 40', [$user])->fetchAll(PDO::FETCH_ASSOC);
    return array_map('item_public', $rows);
}

function wallet_view(PDO $db, int $user): array
{
    $row = wallet_row($db, $user);
    return [
        'wallet' => [
            'crystals' => (int) $row['crystals'],
            'earned' => (int) $row['earned'],
            'history' => wallet_history($db, $user)
        ],
        'items' => wallet_items($db, $user),
        'serverNow' => social_now()
    ];
}

function wallet_handle(PDO $db, array $in, array $player, int $now): ?array
{
    $action = $in['action'] ?? '';
    if (!in_array($action, ['wallet_state', 'wallet_withdraw', 'item_withdraw'], true)) {
        return null;
    }
    wallet_install($db);
    $user = (int) $player['id'];

    if ($action === 'wallet_withdraw') {
        $amount = (int) ($in['amount'] ?? 0);
        $balance = (int) wallet_row($db, $user)['crystals'];
        if ($amount < 1) {
            $amount = $balance;
        }
        if ($amount < 1) {
            social_error('Le dépôt est vide.');
        }
        if ($amount > $balance) {
            social_error('Solde insuffisant dans le dépôt.');
        }
        wallet_add($db, $user, -$amount, 'retrait', $now);
        $view = wallet_view($db, $user);
        // Le client crédite sa sauvegarde locale de ce montant, une seule fois.
        $view['crystals'] = $amount;
        return $view;
    }

    if ($action === 'item_withdraw') {
        $id = (string) ($in['itemId'] ?? '');
        $row = sq($db, 'SELECT * FROM social_items WHERE id = ? AND owner_id = ? AND withdrawn = 0',
            [$id, $user])->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            social_error('Pièce introuvable dans votre dépôt.');
        }
        if ((int) $row['listed'] === 1) {
            social_error('Retirez d\'abord cette pièce de la vente.');
        }
        sq($db, 'UPDATE social_items SET withdrawn = 1, owner_id = 0 WHERE id = ?', [$id]);
        $view = wallet_view($db, $user);
        // Le client range la pièce dans son sac ; le serveur ne la reverra plus.
        $view['item'] = item_public($row);
        return $view;
    }

    return wallet_view($db, $user);
}
