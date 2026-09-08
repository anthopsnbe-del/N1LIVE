<?php
declare(strict_types=1);

/* Saisons de l'arène : quatorze jours, puis remise à niveau des classements.
 *
 * Sans remise à zéro, un classement d'arène se fige : les premiers inscrits
 * gardent leur avance et les nouveaux venus n'ont plus rien à viser. À la
 * clôture, le serveur fige le classement, prépare les récompenses (versées au
 * dépôt) et rapproche chaque cote de 1000 — une remise à niveau douce, qui
 * garde une trace du niveau atteint sans repartir de zéro.
 *
 * Une saison par monde, comme les cotes elles-mêmes.
 */

const SEASON_LENGTH = 14 * 86400000;   // quatorze jours

function season_install(PDO $db): void
{
    $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $tables = [
        'social_seasons (id VARCHAR(32) PRIMARY KEY, world VARCHAR(8) NOT NULL, number INTEGER NOT NULL,
          started_at BIGINT NOT NULL, ends_at BIGINT NOT NULL, closed INTEGER NOT NULL DEFAULT 0)',
        'social_season_rewards (season_id VARCHAR(32) NOT NULL, user_id INTEGER NOT NULL,
          world VARCHAR(8) NOT NULL, number INTEGER NOT NULL, rank INTEGER NOT NULL, rating INTEGER NOT NULL,
          crystals INTEGER NOT NULL, claimed INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(season_id, user_id))'
    ];
    foreach ($tables as $sql) {
        $db->exec('CREATE TABLE IF NOT EXISTS ' . $sql . $suffix);
    }
}

/* Barème de fin de saison : le podium est nettement récompensé, mais toute
   personne ayant gagné un duel repart avec quelque chose. */
function season_reward(int $rank, int $wins): int
{
    if ($wins < 1) {
        return 0;
    }
    if ($rank === 1) return 120;
    if ($rank === 2) return 80;
    if ($rank === 3) return 60;
    if ($rank <= 10) return 35;
    if ($rank <= 50) return 15;
    return 5;
}

function season_close(PDO $db, array $season, int $now): void
{
    $rows = sq($db, 'SELECT user_id, rating, wins FROM social_ratings WHERE world = ?
                     ORDER BY rating DESC, wins DESC, user_id', [$season['world']])->fetchAll(PDO::FETCH_ASSOC);
    $rank = 0;
    foreach ($rows as $row) {
        $rank++;
        $crystals = season_reward($rank, (int) $row['wins']);
        if ($crystals < 1) {
            continue;
        }
        sq($db, 'INSERT INTO social_season_rewards (season_id, user_id, world, number, rank, rating, crystals)
                 VALUES (?,?,?,?,?,?,?)',
            [$season['id'], (int) $row['user_id'], $season['world'], (int) $season['number'],
             $rank, (int) $row['rating'], $crystals]);
    }
    // Remise à niveau douce : on garde la moitié de l'écart à 1000.
    foreach ($rows as $row) {
        $rating = 1000 + (int) round(((int) $row['rating'] - 1000) / 2);
        sq($db, 'UPDATE social_ratings SET rating = ?, wins = 0, losses = 0 WHERE user_id = ? AND world = ?',
            [$rating, (int) $row['user_id'], $season['world']]);
    }
    sq($db, 'UPDATE social_seasons SET closed = 1 WHERE id = ?', [$season['id']]);
}

function season_current(PDO $db, string $world, int $now): array
{
    $season = sq($db, 'SELECT * FROM social_seasons WHERE world = ? ORDER BY number DESC LIMIT 1',
        [$world])->fetch(PDO::FETCH_ASSOC);
    if ($season && (int) $season['closed'] === 0 && $now < (int) $season['ends_at']) {
        return $season;
    }
    $number = 1;
    if ($season) {
        if ((int) $season['closed'] === 0) {
            season_close($db, $season, $now);
        }
        $number = (int) $season['number'] + 1;
    }
    $fresh = [
        'id' => bin2hex(random_bytes(16)),
        'world' => $world,
        'number' => $number,
        'started_at' => $now,
        'ends_at' => $now + SEASON_LENGTH,
        'closed' => 0
    ];
    sq($db, 'INSERT INTO social_seasons (id, world, number, started_at, ends_at) VALUES (?,?,?,?,?)',
        [$fresh['id'], $world, $number, $fresh['started_at'], $fresh['ends_at']]);
    return $fresh;
}

function season_standings(PDO $db, string $world): array
{
    $rows = sq($db, 'SELECT r.user_id, r.rating, r.wins, r.losses, p.pseudo, c.tag
                     FROM social_ratings r JOIN players p ON p.id = r.user_id
                     LEFT JOIN clans c ON c.id = p.clan_id
                     WHERE r.world = ? ORDER BY r.rating DESC, r.wins DESC, r.user_id LIMIT 20',
        [$world])->fetchAll(PDO::FETCH_ASSOC);
    return array_map(fn($row) => [
        'id' => (int) $row['user_id'],
        'pseudo' => $row['pseudo'],
        'clan' => $row['tag'],
        'rating' => (int) $row['rating'],
        'wins' => (int) $row['wins'],
        'losses' => (int) $row['losses']
    ], $rows);
}

function season_rank(PDO $db, string $world, int $user): int
{
    $mine = sq($db, 'SELECT rating FROM social_ratings WHERE user_id = ? AND world = ?',
        [$user, $world])->fetch(PDO::FETCH_ASSOC);
    if (!$mine) {
        return 0;
    }
    $row = sq($db, 'SELECT COUNT(*) AS n FROM social_ratings WHERE world = ? AND rating > ?',
        [$world, (int) $mine['rating']])->fetch(PDO::FETCH_ASSOC);
    return (int) $row['n'] + 1;
}

function season_view(PDO $db, array $season, string $world, int $user, int $now): array
{
    $mine = sq($db, 'SELECT rating, wins, losses FROM social_ratings WHERE user_id = ? AND world = ?',
        [$user, $world])->fetch(PDO::FETCH_ASSOC) ?: ['rating' => 1000, 'wins' => 0, 'losses' => 0];
    $pending = sq($db, 'SELECT number, rank, rating, crystals FROM social_season_rewards
                        WHERE user_id = ? AND claimed = 0 ORDER BY number', [$user])->fetchAll(PDO::FETCH_ASSOC);
    return [
        'season' => [
            'number' => (int) $season['number'],
            'world' => $world,
            'startedAt' => (int) $season['started_at'],
            'endsAt' => (int) $season['ends_at']
        ],
        'you' => [
            'rating' => (int) $mine['rating'],
            'wins' => (int) $mine['wins'],
            'losses' => (int) $mine['losses'],
            'rank' => season_rank($db, $world, $user)
        ],
        'standings' => season_standings($db, $world),
        'pending' => array_map(fn($row) => [
            'number' => (int) $row['number'],
            'rank' => (int) $row['rank'],
            'rating' => (int) $row['rating'],
            'crystals' => (int) $row['crystals']
        ], $pending),
        'serverNow' => $now
    ];
}

function season_handle(PDO $db, array $in, array $player, string $world, int $now): ?array
{
    $action = $in['action'] ?? '';
    if (!in_array($action, ['season_state', 'season_claim'], true)) {
        return null;
    }
    season_install($db);
    wallet_install($db);
    $user = (int) $player['id'];
    $season = season_current($db, $world, $now);

    if ($action === 'season_claim') {
        $rows = sq($db, 'SELECT season_id, crystals, number FROM social_season_rewards
                         WHERE user_id = ? AND claimed = 0', [$user])->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            social_error('Aucune récompense de saison en attente.');
        }
        $total = 0;
        foreach ($rows as $row) {
            $total += (int) $row['crystals'];
            sq($db, 'UPDATE social_season_rewards SET claimed = 1 WHERE season_id = ? AND user_id = ?',
                [$row['season_id'], $user]);
        }
        wallet_add($db, $user, $total, 'saison ' . (int) $rows[0]['number'], $now);
        $view = season_view($db, $season, $world, $user, $now);
        $view['credited'] = $total;
        $view['wallet'] = wallet_view($db, $user)['wallet'];
        return $view;
    }

    return season_view($db, $season, $world, $user, $now);
}
