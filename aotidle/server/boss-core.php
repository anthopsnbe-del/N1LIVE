<?php
declare(strict_types=1);

/* Boss mondial : le premier contenu réellement coopératif du jeu.
 *
 * Un boss par monde et par jour UTC. Tous les joueurs d'un même monde tapent
 * sur la même barre de vie ; les dégâts sont calculés par le serveur à partir
 * de la puissance déjà enregistrée par `sync`, jamais à partir d'un nombre
 * envoyé par le client. Un assaut par minute et par joueur.
 *
 * Tables additives uniquement, préfixe `social_` comme le reste du service.
 */

const BOSS_STRIKE_COOLDOWN = 60000;   // une minute entre deux assauts
const BOSS_MAX_HITS        = 120;     // plafond d'assauts par joueur et par boss
const BOSS_MIN_HP          = 20000;   // un monde vide garde un boss abattable
const BOSS_SHARE_TARGET    = 8;       // assauts attendus par joueur actif

/* Les boss tournent sur un cycle fixe : deux joueurs du même monde voient le
   même adversaire le même jour, sans qu'aucun état n'ait à être partagé. */
const BOSS_ROSTER = [
    ['Titan Colossal', 'colossal'],
    ['Titan Blindé', 'blinde'],
    ['Titan Bestial', 'bestial'],
    ['Titan Féminin', 'feminin'],
    ['Titan Anormal', 'anormal'],
    ['Titan du Grondement', 'colossal'],
    ['Titan de Ragako', 'titan']
];

function boss_install(PDO $db): void
{
    $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $tables = [
        'social_boss (world VARCHAR(8) PRIMARY KEY, boss_id VARCHAR(32) NOT NULL, name VARCHAR(48) NOT NULL,
          shape VARCHAR(16) NOT NULL, day INTEGER NOT NULL, hp BIGINT NOT NULL, max_hp BIGINT NOT NULL,
          started_at BIGINT NOT NULL, ends_at BIGINT NOT NULL, defeated INTEGER NOT NULL DEFAULT 0,
          defeated_at BIGINT NOT NULL DEFAULT 0)',
        'social_boss_damage (boss_id VARCHAR(32) NOT NULL, user_id INTEGER NOT NULL, clan_id INTEGER NOT NULL DEFAULT 0,
          damage BIGINT NOT NULL DEFAULT 0, hits INTEGER NOT NULL DEFAULT 0, last_hit BIGINT NOT NULL DEFAULT 0,
          claimed INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(boss_id, user_id))'
    ];
    foreach ($tables as $sql) {
        $db->exec('CREATE TABLE IF NOT EXISTS ' . $sql . $suffix);
    }
}

/* Puissance d'un joueur traduite en dégâts. Le niveau donne un plancher pour
   qu'un cadet participe malgré tout à l'effort de guerre. */
function boss_strike_power(array $player): int
{
    return (int) max(1, ((int) $player['power']) * 60 + ((int) $player['level']) * 20 + 100);
}

/* Points de vie du boss : la somme de ce que les joueurs actifs du monde
   peuvent placer en une journée. Un petit monde garde un boss à sa mesure. */
function boss_scale(PDO $db, string $world, int $now): int
{
    $rows = sq($db, 'SELECT p.power, p.level FROM players p JOIN social_accounts a ON a.user_id = p.id
                     WHERE a.world = ? AND a.last_seen > ?', [$world, $now - 7 * 86400000])->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;
    foreach ($rows as $row) {
        $total += boss_strike_power($row) * BOSS_SHARE_TARGET;
    }
    return (int) max(BOSS_MIN_HP, min(900000000000, $total));
}

function boss_day(int $now): int
{
    return (int) floor($now / 86400000);
}

/* Le boss vivant du monde, créé ou renouvelé si le jour a tourné. */
function boss_current(PDO $db, string $world, int $now): array
{
    $boss = sq($db, 'SELECT * FROM social_boss WHERE world = ?', [$world])->fetch(PDO::FETCH_ASSOC);
    $day = boss_day($now);
    if ($boss && (int) $boss['day'] === $day) {
        return $boss;
    }
    $definition = BOSS_ROSTER[$day % count(BOSS_ROSTER)];
    $maxHp = boss_scale($db, $world, $now);
    $fresh = [
        'world' => $world,
        'boss_id' => bin2hex(random_bytes(16)),
        'name' => $definition[0],
        'shape' => $definition[1],
        'day' => $day,
        'hp' => $maxHp,
        'max_hp' => $maxHp,
        'started_at' => $day * 86400000,
        'ends_at' => ($day + 1) * 86400000,
        'defeated' => 0,
        'defeated_at' => 0
    ];
    if ($boss) {
        sq($db, 'UPDATE social_boss SET boss_id = ?, name = ?, shape = ?, day = ?, hp = ?, max_hp = ?,
                 started_at = ?, ends_at = ?, defeated = 0, defeated_at = 0 WHERE world = ?',
            [$fresh['boss_id'], $fresh['name'], $fresh['shape'], $day, $maxHp, $maxHp,
             $fresh['started_at'], $fresh['ends_at'], $world]);
    } else {
        sq($db, 'INSERT INTO social_boss (world, boss_id, name, shape, day, hp, max_hp, started_at, ends_at)
                 VALUES (?,?,?,?,?,?,?,?,?)',
            [$world, $fresh['boss_id'], $fresh['name'], $fresh['shape'], $day, $maxHp, $maxHp,
             $fresh['started_at'], $fresh['ends_at']]);
    }
    return $fresh;
}

function boss_row(PDO $db, string $bossId, int $user): array
{
    $row = sq($db, 'SELECT * FROM social_boss_damage WHERE boss_id = ? AND user_id = ?',
        [$bossId, $user])->fetch(PDO::FETCH_ASSOC);
    return $row ?: ['boss_id' => $bossId, 'user_id' => $user, 'clan_id' => 0,
        'damage' => 0, 'hits' => 0, 'last_hit' => 0, 'claimed' => 0];
}

/* Récompense en cristaux : une part fixe pour avoir participé, une part liée à
   la contribution, un bonus de podium. Le serveur ne tient pas la bourse du
   joueur (elle vit dans sa sauvegarde locale) : il autorise le versement une
   seule fois et le client l'applique. */
function boss_reward(array $boss, array $mine, int $rank, int $clanRank): int
{
    if ((int) $mine['hits'] < 1) {
        return 0;
    }
    $defeated = (int) $boss['defeated'] === 1;
    $share = (int) $boss['max_hp'] > 0 ? (int) $mine['damage'] / (int) $boss['max_hp'] : 0;
    $reward = $defeated ? 12 : 5;
    $reward += (int) round(min(45, 180 * $share));
    if ($defeated) {
        if ($rank === 1)      $reward += 30;
        elseif ($rank === 2)  $reward += 18;
        elseif ($rank === 3)  $reward += 12;
        elseif ($rank <= 10)  $reward += 6;
        if ($clanRank === 1)     $reward += 10;
        elseif ($clanRank === 2) $reward += 6;
        elseif ($clanRank === 3) $reward += 3;
    }
    return $reward;
}

function boss_rank(PDO $db, string $bossId, int $damage): int
{
    if ($damage <= 0) {
        return 0;
    }
    $row = sq($db, 'SELECT COUNT(*) AS n FROM social_boss_damage WHERE boss_id = ? AND damage > ?',
        [$bossId, $damage])->fetch(PDO::FETCH_ASSOC);
    return (int) $row['n'] + 1;
}

function boss_clans(PDO $db, string $bossId): array
{
    $rows = sq($db, 'SELECT d.clan_id, c.tag, c.name, SUM(d.damage) AS damage, COUNT(*) AS members
                     FROM social_boss_damage d JOIN clans c ON c.id = d.clan_id
                     WHERE d.boss_id = ? AND d.clan_id > 0
                     GROUP BY d.clan_id, c.tag, c.name ORDER BY damage DESC LIMIT 5',
        [$bossId])->fetchAll(PDO::FETCH_ASSOC);
    return array_map(fn($row) => [
        'clanId' => (int) $row['clan_id'],
        'tag' => $row['tag'],
        'name' => $row['name'],
        'damage' => (int) $row['damage'],
        'members' => (int) $row['members']
    ], $rows);
}

function boss_top(PDO $db, string $bossId): array
{
    $rows = sq($db, 'SELECT d.user_id, d.damage, p.pseudo, c.tag FROM social_boss_damage d
                     JOIN players p ON p.id = d.user_id
                     LEFT JOIN clans c ON c.id = d.clan_id
                     WHERE d.boss_id = ? ORDER BY d.damage DESC, d.user_id LIMIT 15',
        [$bossId])->fetchAll(PDO::FETCH_ASSOC);
    return array_map(fn($row) => [
        'id' => (int) $row['user_id'],
        'pseudo' => $row['pseudo'],
        'clan' => $row['tag'],
        'damage' => (int) $row['damage']
    ], $rows);
}

function boss_view(PDO $db, array $boss, array $player, int $clan, int $now): array
{
    $user = (int) $player['id'];
    $mine = boss_row($db, $boss['boss_id'], $user);
    $rank = boss_rank($db, $boss['boss_id'], (int) $mine['damage']);
    $clans = boss_clans($db, $boss['boss_id']);
    $clanRank = 0;
    foreach ($clans as $index => $entry) {
        if ($entry['clanId'] === $clan) {
            $clanRank = $index + 1;
        }
    }
    $participants = sq($db, 'SELECT COUNT(*) AS n FROM social_boss_damage WHERE boss_id = ?',
        [$boss['boss_id']])->fetch(PDO::FETCH_ASSOC);
    return [
        'boss' => [
            'id' => $boss['boss_id'],
            'name' => $boss['name'],
            'shape' => $boss['shape'],
            'hp' => (int) $boss['hp'],
            'maxHp' => (int) $boss['max_hp'],
            'endsAt' => (int) $boss['ends_at'],
            'defeated' => (int) $boss['defeated'] === 1,
            'participants' => (int) $participants['n']
        ],
        'you' => [
            'damage' => (int) $mine['damage'],
            'hits' => (int) $mine['hits'],
            'hitsLeft' => max(0, BOSS_MAX_HITS - (int) $mine['hits']),
            'rank' => $rank,
            'strikePower' => boss_strike_power($player),
            'claimed' => (int) $mine['claimed'] === 1,
            'reward' => boss_reward($boss, $mine, $rank, $clanRank),
            'cooldown' => max(0, (int) $mine['last_hit'] + BOSS_STRIKE_COOLDOWN - $now)
        ],
        'top' => boss_top($db, $boss['boss_id']),
        'clans' => $clans,
        'serverNow' => $now
    ];
}

/* Actions ajoutées à `social.php`. Renvoie null si l'action ne la concerne
   pas, pour que social_handle continue son aiguillage. */
function boss_handle(PDO $db, array $in, array $player, string $world, int $clan, int $now): ?array
{
    $action = $in['action'] ?? '';
    if (!in_array($action, ['boss_state', 'boss_strike', 'boss_claim'], true)) {
        return null;
    }
    boss_install($db);
    $user = (int) $player['id'];
    $boss = boss_current($db, $world, $now);

    if ($action === 'boss_strike') {
        if ((int) $boss['defeated'] === 1) {
            social_error('Ce titan est déjà tombé. Le suivant arrive demain.');
        }
        $mine = boss_row($db, $boss['boss_id'], $user);
        if ((int) $mine['last_hit'] + BOSS_STRIKE_COOLDOWN > $now) {
            social_error('Le bataillon se repositionne. Réessayez dans un instant.');
        }
        if ((int) $mine['hits'] >= BOSS_MAX_HITS) {
            social_error('Vous avez donné tout ce que vous pouviez sur ce titan.');
        }
        // Les dégâts viennent de la puissance enregistrée côté serveur : un
        // client ne peut pas en proposer une autre.
        $damage = min(boss_strike_power($player), (int) $boss['hp']);
        $left = max(0, (int) $boss['hp'] - $damage);
        sq($db, 'UPDATE social_boss SET hp = ? WHERE world = ?', [$left, $world]);
        if ((int) $mine['hits'] === 0) {
            sq($db, 'INSERT INTO social_boss_damage (boss_id, user_id, clan_id, damage, hits, last_hit)
                     VALUES (?,?,?,?,1,?)', [$boss['boss_id'], $user, $clan, $damage, $now]);
        } else {
            sq($db, 'UPDATE social_boss_damage SET damage = damage + ?, hits = hits + 1, last_hit = ?, clan_id = ?
                     WHERE boss_id = ? AND user_id = ?', [$damage, $now, $clan, $boss['boss_id'], $user]);
        }
        if ($left === 0) {
            sq($db, 'UPDATE social_boss SET defeated = 1, defeated_at = ? WHERE world = ?', [$now, $world]);
        }
        $boss = sq($db, 'SELECT * FROM social_boss WHERE world = ?', [$world])->fetch(PDO::FETCH_ASSOC);
        $view = boss_view($db, $boss, $player, $clan, $now);
        $view['hit'] = ['damage' => $damage, 'killing' => $left === 0];
        return $view;
    }

    if ($action === 'boss_claim') {
        $mine = boss_row($db, $boss['boss_id'], $user);
        if ((int) $mine['hits'] < 1) {
            social_error('Participez à l\'assaut pour toucher une récompense.');
        }
        if ((int) $mine['claimed'] === 1) {
            social_error('Récompense déjà versée pour ce titan.');
        }
        // Tant que le titan tient debout et que la journée n'est pas finie, la
        // récompense n'est pas figée : on ne la verse pas d'avance.
        if ((int) $boss['defeated'] !== 1 && $now < (int) $boss['ends_at']) {
            social_error('L\'assaut est en cours : la récompense est versée à la chute du titan.');
        }
        $rank = boss_rank($db, $boss['boss_id'], (int) $mine['damage']);
        $clanRank = 0;
        foreach (boss_clans($db, $boss['boss_id']) as $index => $entry) {
            if ($entry['clanId'] === $clan) {
                $clanRank = $index + 1;
            }
        }
        $reward = boss_reward($boss, $mine, $rank, $clanRank);
        sq($db, 'UPDATE social_boss_damage SET claimed = 1 WHERE boss_id = ? AND user_id = ?',
            [$boss['boss_id'], $user]);
        $view = boss_view($db, $boss, $player, $clan, $now);
        $view['crystals'] = $reward;
        return $view;
    }

    return boss_view($db, $boss, $player, $clan, $now);
}
