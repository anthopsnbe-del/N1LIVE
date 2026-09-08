<?php
declare(strict_types=1);

/* Marché du bataillon : échange d'équipement entre joueurs, en cristaux du
 * dépôt.
 *
 * Le marché ne peut exister que sur des biens que le serveur a lui-même émis :
 * les pièces gagnées au boss mondial et aux guerres de clans (`social_items`),
 * payées avec les cristaux du dépôt (`social_wallet`). L'équipement et l'or de
 * la campagne, eux, vivent dans une sauvegarde locale invérifiable et n'entrent
 * jamais ici — sans quoi n'importe quel client pourrait fabriquer sa monnaie.
 *
 * Une pièce rapatriée dans le sac du joueur quitte définitivement le marché :
 * le retrait est à sens unique, il n'y a pas de remise en vente.
 */

const MARKET_MIN_PRICE = 5;
const MARKET_MAX_PRICE = 5000;
const MARKET_MAX_LISTINGS = 6;    // annonces simultanées par joueur
const MARKET_FEE = 0.10;          // prélèvement du bataillon sur chaque vente

function market_install(PDO $db): void
{
    $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $db->exec('CREATE TABLE IF NOT EXISTS social_market (id VARCHAR(32) PRIMARY KEY,
        item_id VARCHAR(32) NOT NULL, seller_id INTEGER NOT NULL, world VARCHAR(8) NOT NULL,
        price INTEGER NOT NULL, created_at BIGINT NOT NULL, sold_to INTEGER NOT NULL DEFAULT 0,
        sold_at BIGINT NOT NULL DEFAULT 0, cancelled INTEGER NOT NULL DEFAULT 0)' . $suffix);
}

function market_listing_row(PDO $db, string $id): ?array
{
    $row = sq($db, 'SELECT * FROM social_market WHERE id = ?', [$id])->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function market_public(PDO $db, array $listing): ?array
{
    $item = sq($db, 'SELECT * FROM social_items WHERE id = ?', [$listing['item_id']])->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        return null;
    }
    $seller = sq($db, 'SELECT pseudo FROM players WHERE id = ?', [(int) $listing['seller_id']])->fetch(PDO::FETCH_ASSOC);
    return [
        'id' => $listing['id'],
        'price' => (int) $listing['price'],
        'seller' => $seller ? $seller['pseudo'] : '—',
        'sellerId' => (int) $listing['seller_id'],
        'at' => (int) $listing['created_at'],
        'item' => item_public($item)
    ];
}

function market_open(PDO $db, string $world, int $user): array
{
    $rows = sq($db, 'SELECT * FROM social_market WHERE world = ? AND sold_to = 0 AND cancelled = 0
                     ORDER BY created_at DESC LIMIT 40', [$world])->fetchAll(PDO::FETCH_ASSOC);
    $listings = [];
    foreach ($rows as $row) {
        $entry = market_public($db, $row);
        if ($entry) {
            $entry['mine'] = (int) $row['seller_id'] === $user;
            $listings[] = $entry;
        }
    }
    return $listings;
}

function market_sales(PDO $db, int $user): array
{
    $rows = sq($db, 'SELECT * FROM social_market WHERE (seller_id = ? OR sold_to = ?) AND sold_to > 0
                     ORDER BY sold_at DESC LIMIT 10', [$user, $user])->fetchAll(PDO::FETCH_ASSOC);
    $sales = [];
    foreach ($rows as $row) {
        $entry = market_public($db, $row);
        if (!$entry) {
            continue;
        }
        $entry['sold'] = true;
        $entry['bought'] = (int) $row['sold_to'] === $user;
        $sales[] = $entry;
    }
    return $sales;
}

function market_view(PDO $db, string $world, int $user, int $now): array
{
    $wallet = wallet_view($db, $user);
    return [
        'wallet' => $wallet['wallet'],
        'items' => $wallet['items'],
        'listings' => market_open($db, $world, $user),
        'sales' => market_sales($db, $user),
        'limits' => [
            'minPrice' => MARKET_MIN_PRICE,
            'maxPrice' => MARKET_MAX_PRICE,
            'maxListings' => MARKET_MAX_LISTINGS,
            'fee' => MARKET_FEE
        ],
        'serverNow' => $now
    ];
}

function market_handle(PDO $db, array $in, array $player, string $world, int $now): ?array
{
    $action = $in['action'] ?? '';
    if (!in_array($action, ['market_state', 'market_list', 'market_cancel', 'market_buy'], true)) {
        return null;
    }
    wallet_install($db);
    market_install($db);
    $user = (int) $player['id'];

    if ($action === 'market_list') {
        $itemId = (string) ($in['itemId'] ?? '');
        $price = (int) ($in['price'] ?? 0);
        if ($price < MARKET_MIN_PRICE || $price > MARKET_MAX_PRICE) {
            social_error('Prix : entre ' . MARKET_MIN_PRICE . ' et ' . MARKET_MAX_PRICE . ' cristaux.');
        }
        $open = sq($db, 'SELECT COUNT(*) AS n FROM social_market WHERE seller_id = ? AND sold_to = 0 AND cancelled = 0',
            [$user])->fetch(PDO::FETCH_ASSOC);
        if ((int) $open['n'] >= MARKET_MAX_LISTINGS) {
            social_error('Vous avez déjà ' . MARKET_MAX_LISTINGS . ' annonces en cours.');
        }
        $item = sq($db, 'SELECT * FROM social_items WHERE id = ? AND owner_id = ? AND withdrawn = 0',
            [$itemId, $user])->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            social_error('Pièce introuvable dans votre dépôt.');
        }
        if ((int) $item['listed'] === 1) {
            social_error('Cette pièce est déjà en vente.');
        }
        sq($db, 'UPDATE social_items SET listed = 1 WHERE id = ?', [$itemId]);
        sq($db, 'INSERT INTO social_market (id, item_id, seller_id, world, price, created_at) VALUES (?,?,?,?,?,?)',
            [bin2hex(random_bytes(16)), $itemId, $user, $world, $price, $now]);
        return market_view($db, $world, $user, $now);
    }

    if ($action === 'market_cancel') {
        $listing = market_listing_row($db, (string) ($in['listingId'] ?? ''));
        if (!$listing || (int) $listing['seller_id'] !== $user) {
            social_error('Annonce introuvable.');
        }
        if ((int) $listing['sold_to'] > 0 || (int) $listing['cancelled'] === 1) {
            social_error('Cette annonce n\'est plus active.');
        }
        sq($db, 'UPDATE social_market SET cancelled = 1 WHERE id = ?', [$listing['id']]);
        sq($db, 'UPDATE social_items SET listed = 0 WHERE id = ?', [$listing['item_id']]);
        return market_view($db, $world, $user, $now);
    }

    if ($action === 'market_buy') {
        $listing = market_listing_row($db, (string) ($in['listingId'] ?? ''));
        if (!$listing || (int) $listing['sold_to'] > 0 || (int) $listing['cancelled'] === 1) {
            social_error('Cette annonce n\'est plus disponible.');
        }
        if ((int) $listing['seller_id'] === $user) {
            social_error('Vous ne pouvez pas acheter votre propre annonce.');
        }
        if ($listing['world'] !== $world) {
            social_error('Cette annonce appartient à un autre monde.');
        }
        $item = sq($db, 'SELECT * FROM social_items WHERE id = ? AND withdrawn = 0',
            [$listing['item_id']])->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            social_error('La pièce mise en vente n\'existe plus.');
        }
        $price = (int) $listing['price'];
        // L'acheteur paie, le bataillon prélève sa part, le vendeur touche le
        // reste : le prélèvement évite que les cristaux s'accumulent sans fin.
        wallet_add($db, $user, -$price, 'achat au marché', $now);
        $net = (int) max(1, round($price * (1 - MARKET_FEE)));
        wallet_add($db, (int) $listing['seller_id'], $net, 'vente au marché', $now);
        sq($db, 'UPDATE social_items SET owner_id = ?, listed = 0 WHERE id = ?', [$user, $item['id']]);
        sq($db, 'UPDATE social_market SET sold_to = ?, sold_at = ? WHERE id = ?', [$user, $now, $listing['id']]);
        $view = market_view($db, $world, $user, $now);
        $view['bought'] = item_public($item);
        return $view;
    }

    return market_view($db, $world, $user, $now);
}
