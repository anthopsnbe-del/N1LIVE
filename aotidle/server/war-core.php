<?php
declare(strict_types=1);

/* Guerre de clans : deux clans, un même front, vingt-quatre heures.
 *
 * Le chef d'un clan engage son clan ; le serveur l'apparie avec un autre clan
 * du même monde de taille comparable. Les deux clans frappent le même titan :
 * la barre de vie est commune, le score est compté par camp. Le clan qui a le
 * plus contribué l'emporte, soit à la chute du titan, soit à l'échéance.
 *
 * Les dégâts sont calculés par le serveur, comme pour le boss mondial
 * (`boss_strike_power`) : le client demande un assaut, il n'en annonce pas la
 * valeur. Tables additives uniquement.
 */

const WAR_STRIKE_COOLDOWN = 60000;   // une minute entre deux assauts
const WAR_MAX_HITS        = 120;     // plafond d'assauts par joueur et par guerre
const WAR_DURATION        = 86400000; // vingt-quatre heures
const WAR_MIN_HP          = 20000;
const WAR_SHARE_TARGET    = 10;      // assauts attendus par membre engagé

function war_install(PDO $db): void
{
    $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $tables = [
        'social_wars (id VARCHAR(32) PRIMARY KEY, world VARCHAR(8) NOT NULL, clan_a INTEGER NOT NULL,
          clan_b INTEGER NOT NULL, name VARCHAR(48) NOT NULL, shape VARCHAR(16) NOT NULL,
          hp BIGINT NOT NULL, max_hp BIGINT NOT NULL, score_a BIGINT NOT NULL DEFAULT 0,
          score_b BIGINT NOT NULL DEFAULT 0, started_at BIGINT NOT NULL, ends_at BIGINT NOT NULL,
          resolved INTEGER NOT NULL DEFAULT 0, winner INTEGER NOT NULL DEFAULT 0)',
        'social_war_queue (clan_id INTEGER PRIMARY KEY, world VARCHAR(8) NOT NULL, members INTEGER NOT NULL,
          created_at BIGINT NOT NULL)',
        'social_war_damage (war_id VARCHAR(32) NOT NULL, user_id INTEGER NOT NULL, clan_id INTEGER NOT NULL,
          damage BIGINT NOT NULL DEFAULT 0, hits INTEGER NOT NULL DEFAULT 0, last_hit BIGINT NOT NULL DEFAULT 0,
          claimed INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(war_id, user_id))'
    ];
    foreach ($tables as $sql) {
        $db->exec('CREATE TABLE IF NOT EXISTS ' . $sql . $suffix);
    }
}

function war_clan(PDO $db, int $clanId): ?array
{
    if ($clanId < 1) {
        return null;
    }
    $clan = sq($db, 'SELECT id, tag, name, owner_id FROM clans WHERE id = ?', [$clanId])->fetch(PDO::FETCH_ASSOC);
    if (!$clan) {
        return null;
    }
    $count = sq($db, 'SELECT COUNT(*) AS n FROM players WHERE clan_id = ?', [$clanId])->fetch(PDO::FETCH_ASSOC);
    return [
        'id' => (int) $clan['id'],
        'tag' => $clan['tag'],
        'name' => $clan['name'],
        'ownerId' => (int) $clan['owner_id'],
        'members' => (int) $count['n']
    ];
}

/* Puissance de frappe cumulée d'un clan : sert à dimensionner le front. */
function war_clan_power(PDO $db, int $clanId): int
{
    $rows = sq($db, 'SELECT power, level FROM players WHERE clan_id = ?', [$clanId])->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;
    foreach ($rows as $row) {
        $total += boss_strike_power($row);
    }
    return $total;
}

function war_active(PDO $db, int $clanId): ?array
{
    if ($clanId < 1) {
        return null;
    }
    $row = sq($db, 'SELECT * FROM social_wars WHERE (clan_a = ? OR clan_b = ?) ORDER BY started_at DESC LIMIT 1',
        [$clanId, $clanId])->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/* Une guerre s'achève à la chute du titan ou à l'échéance ; le camp qui a le
   plus contribué l'emporte, à égalité stricte la guerre est nulle. */
function war_resolve(PDO $db, array $war, int $now): array
{
    if ((int) $war['resolved'] === 1) {
        return $war;
    }
    if ((int) $war['hp'] > 0 && $now < (int) $war['ends_at']) {
        return $war;
    }
    $a = (int) $war['score_a'];
    $b = (int) $war['score_b'];
    $winner = $a === $b ? 3 : ($a > $b ? 1 : 2);
    sq($db, 'UPDATE social_wars SET resolved = 1, winner = ? WHERE id = ?', [$winner, $war['id']]);
    $war['resolved'] = 1;
    $war['winner'] = $winner;
    return $war;
}

function war_side(array $war, int $clanId): int
{
    return (int) $war['clan_a'] === $clanId ? 1 : 2;
}

/* Récompense : le camp vainqueur touche davantage, mais un clan battu qui
   s'est battu repart avec quelque chose. La part personnelle suit la
   contribution au score de son propre camp. */
function war_reward(array $war, array $mine, int $side): int
{
    if ((int) $mine['hits'] < 1) {
        return 0;
    }
    $winner = (int) $war['winner'];
    $score = $side === 1 ? (int) $war['score_a'] : (int) $war['score_b'];
    $share = $score > 0 ? (int) $mine['damage'] / $score : 0;
    $base = $winner === 3 ? 25 : ($winner === $side ? 40 : 15);
    return $base + (int) round(min(50, 100 * $share));
}

function war_board(PDO $db, string $warId, int $clanId): array
{
    $rows = sq($db, 'SELECT d.user_id, d.damage, p.pseudo FROM social_war_damage d
                     JOIN players p ON p.id = d.user_id
                     WHERE d.war_id = ? AND d.clan_id = ? ORDER BY d.damage DESC, d.user_id LIMIT 10',
        [$warId, $clanId])->fetchAll(PDO::FETCH_ASSOC);
    return array_map(fn($row) => [
        'id' => (int) $row['user_id'],
        'pseudo' => $row['pseudo'],
        'damage' => (int) $row['damage']
    ], $rows);
}

function war_row(PDO $db, string $warId, int $user): array
{
    $row = sq($db, 'SELECT * FROM social_war_damage WHERE war_id = ? AND user_id = ?',
        [$warId, $user])->fetch(PDO::FETCH_ASSOC);
    return $row ?: ['war_id' => $warId, 'user_id' => $user, 'clan_id' => 0,
        'damage' => 0, 'hits' => 0, 'last_hit' => 0, 'claimed' => 0];
}

function war_view(PDO $db, ?array $war, array $player, int $clanId, int $now): array
{
    $clan = war_clan($db, $clanId);
    if (!$war) {
        $queued = $clanId > 0
            ? sq($db, 'SELECT * FROM social_war_queue WHERE clan_id = ?', [$clanId])->fetch(PDO::FETCH_ASSOC)
            : null;
        $waiting = sq($db, 'SELECT COUNT(*) AS n FROM social_war_queue WHERE world = ?',
            [(string) social_account($db, (int) $player['id'])['world']])->fetch(PDO::FETCH_ASSOC);
        return [
            'war' => null,
            'clan' => $clan,
            'canEnroll' => $clan !== null && $clan['ownerId'] === (int) $player['id'],
            'queued' => (bool) $queued,
            'waiting' => (int) $waiting['n'],
            'serverNow' => $now
        ];
    }
    $side = war_side($war, $clanId);
    $mine = war_row($db, $war['id'], (int) $player['id']);
    return [
        'war' => [
            'id' => $war['id'],
            'name' => $war['name'],
            'shape' => $war['shape'],
            'hp' => (int) $war['hp'],
            'maxHp' => (int) $war['max_hp'],
            'endsAt' => (int) $war['ends_at'],
            'resolved' => (int) $war['resolved'] === 1,
            'winner' => (int) $war['winner'],
            'side' => $side,
            'clans' => [war_clan($db, (int) $war['clan_a']), war_clan($db, (int) $war['clan_b'])],
            'scores' => [(int) $war['score_a'], (int) $war['score_b']],
            'boards' => [war_board($db, $war['id'], (int) $war['clan_a']),
                         war_board($db, $war['id'], (int) $war['clan_b'])]
        ],
        'clan' => $clan,
        'you' => [
            'damage' => (int) $mine['damage'],
            'hits' => (int) $mine['hits'],
            'hitsLeft' => max(0, WAR_MAX_HITS - (int) $mine['hits']),
            'strikePower' => boss_strike_power($player),
            'claimed' => (int) $mine['claimed'] === 1,
            'reward' => war_reward($war, $mine, $side),
            'cooldown' => max(0, (int) $mine['last_hit'] + WAR_STRIKE_COOLDOWN - $now)
        ],
        'serverNow' => $now
    ];
}

/* Appariement : le clan en attente le plus proche en effectif, dans le même
   monde. À défaut, le clan reste en file. */
function war_match(PDO $db, array $clan, string $world, int $now): ?array
{
    $rows = sq($db, 'SELECT * FROM social_war_queue WHERE world = ? AND clan_id <> ? ORDER BY created_at',
        [$world, $clan['id']])->fetchAll(PDO::FETCH_ASSOC);
    $best = null;
    foreach ($rows as $row) {
        $other = war_clan($db, (int) $row['clan_id']);
        if (!$other || war_active($db, $other['id']) !== null) {
            sq($db, 'DELETE FROM social_war_queue WHERE clan_id = ?', [(int) $row['clan_id']]);
            continue;
        }
        $gap = abs($other['members'] - $clan['members']);
        if ($best === null || $gap < $best['gap']) {
            $best = ['clan' => $other, 'gap' => $gap];
        }
    }
    if ($best === null) {
        return null;
    }
    $other = $best['clan'];
    $power = war_clan_power($db, $clan['id']) + war_clan_power($db, $other['id']);
    $maxHp = (int) max(WAR_MIN_HP, min(900000000000, $power * WAR_SHARE_TARGET));
    $definition = BOSS_ROSTER[intdiv($now, 3600000) % count(BOSS_ROSTER)];
    $id = bin2hex(random_bytes(16));
    sq($db, 'INSERT INTO social_wars (id, world, clan_a, clan_b, name, shape, hp, max_hp, started_at, ends_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$id, $world, $other['id'], $clan['id'], $definition[0], $definition[1], $maxHp, $maxHp,
         $now, $now + WAR_DURATION]);
    sq($db, 'DELETE FROM social_war_queue WHERE clan_id = ? OR clan_id = ?', [$clan['id'], $other['id']]);
    return sq($db, 'SELECT * FROM social_wars WHERE id = ?', [$id])->fetch(PDO::FETCH_ASSOC);
}

function war_handle(PDO $db, array $in, array $player, string $world, int $clanId, int $now): ?array
{
    $action = $in['action'] ?? '';
    if (!in_array($action, ['war_state', 'war_enroll', 'war_strike', 'war_claim'], true)) {
        return null;
    }
    war_install($db);
    $user = (int) $player['id'];
    if ($clanId < 1) {
        social_error('Rejoignez un clan pour participer aux guerres de clans.');
    }
    $clan = war_clan($db, $clanId);
    if (!$clan) {
        social_error('Clan introuvable.');
    }
    $war = war_active($db, $clanId);
    if ($war) {
        $war = war_resolve($db, $war, $now);
    }
    // Une guerre terminée et déjà récompensée libère la place pour la suivante.
    if ($war && (int) $war['resolved'] === 1) {
        $mine = war_row($db, $war['id'], $user);
        $settled = (int) $mine['hits'] === 0 || (int) $mine['claimed'] === 1;
        if ($settled && $action === 'war_enroll') {
            $war = null;
        }
    }

    if ($action === 'war_enroll') {
        if ($war && (int) $war['resolved'] === 0) {
            social_error('Une guerre est déjà en cours.');
        }
        if ($clan['ownerId'] !== $user) {
            social_error('Seul le chef du clan peut engager le clan dans une guerre.');
        }
        if ($clan['members'] < 1) {
            social_error('Un clan vide ne peut pas partir en guerre.');
        }
        $started = war_match($db, $clan, $world, $now);
        if ($started) {
            return war_view($db, $started, $player, $clanId, $now);
        }
        if (!sq($db, 'SELECT clan_id FROM social_war_queue WHERE clan_id = ?', [$clanId])->fetch()) {
            sq($db, 'INSERT INTO social_war_queue (clan_id, world, members, created_at) VALUES (?,?,?,?)',
                [$clanId, $world, $clan['members'], $now]);
        }
        return war_view($db, null, $player, $clanId, $now);
    }

    if ($action === 'war_strike') {
        if (!$war || (int) $war['resolved'] === 1) {
            social_error('Aucune guerre en cours pour votre clan.');
        }
        $mine = war_row($db, $war['id'], $user);
        if ((int) $mine['last_hit'] + WAR_STRIKE_COOLDOWN > $now) {
            social_error('Le bataillon se repositionne. Réessayez dans un instant.');
        }
        if ((int) $mine['hits'] >= WAR_MAX_HITS) {
            social_error('Vous avez donné tout ce que vous pouviez sur ce front.');
        }
        $side = war_side($war, $clanId);
        $damage = min(boss_strike_power($player), (int) $war['hp']);
        $left = max(0, (int) $war['hp'] - $damage);
        $column = $side === 1 ? 'score_a' : 'score_b';
        sq($db, 'UPDATE social_wars SET hp = ?, ' . $column . ' = ' . $column . ' + ? WHERE id = ?',
            [$left, $damage, $war['id']]);
        if ((int) $mine['hits'] === 0) {
            sq($db, 'INSERT INTO social_war_damage (war_id, user_id, clan_id, damage, hits, last_hit)
                     VALUES (?,?,?,?,1,?)', [$war['id'], $user, $clanId, $damage, $now]);
        } else {
            sq($db, 'UPDATE social_war_damage SET damage = damage + ?, hits = hits + 1, last_hit = ?
                     WHERE war_id = ? AND user_id = ?', [$damage, $now, $war['id'], $user]);
        }
        $war = sq($db, 'SELECT * FROM social_wars WHERE id = ?', [$war['id']])->fetch(PDO::FETCH_ASSOC);
        $war = war_resolve($db, $war, $now);
        $view = war_view($db, $war, $player, $clanId, $now);
        $view['hit'] = ['damage' => $damage, 'killing' => $left === 0];
        return $view;
    }

    if ($action === 'war_claim') {
        if (!$war) {
            social_error('Aucune guerre à solder.');
        }
        if ((int) $war['resolved'] !== 1) {
            social_error('La guerre est en cours : la récompense est versée à son terme.');
        }
        $mine = war_row($db, $war['id'], $user);
        if ((int) $mine['hits'] < 1) {
            social_error('Participez à l\'assaut pour toucher une récompense.');
        }
        if ((int) $mine['claimed'] === 1) {
            social_error('Récompense déjà versée pour cette guerre.');
        }
        $reward = war_reward($war, $mine, war_side($war, $clanId));
        sq($db, 'UPDATE social_war_damage SET claimed = 1 WHERE war_id = ? AND user_id = ?',
            [$war['id'], $user]);
        $view = war_view($db, $war, $player, $clanId, $now);
        $view['crystals'] = $reward;
        return $view;
    }

    return war_view($db, $war, $player, $clanId, $now);
}
