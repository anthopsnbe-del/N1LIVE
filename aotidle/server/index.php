<?php
// API JSON de AOT IDLE : comptes, classement, clans.
// Toutes les requêtes sont des POST JSON : {"action": "...", "token": "...", ...}

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function fail($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function ok(array $data = []) {
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$in = json_decode($raw ?: '[]', true);
if (!is_array($in)) {
    fail('requête illisible');
}
$action = isset($in['action']) ? (string) $in['action'] : '';

function field($in, $key, $max = 190) {
    $value = isset($in[$key]) ? trim((string) $in[$key]) : '';
    return mb_substr($value, 0, $max);
}

function new_token() {
    return bin2hex(random_bytes(32));
}

function player_row(PDO $pdo, $token) {
    if ($token === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT p.*, c.tag AS clan_tag FROM players p LEFT JOIN clans c ON c.id = p.clan_id WHERE p.token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function require_player(PDO $pdo, $in) {
    $player = player_row($pdo, field($in, 'token', 64));
    if (!$player) {
        fail('session expirée, reconnectez-vous', 401);
    }
    return $player;
}

function public_player($row) {
    return [
        'pseudo'  => $row['pseudo'],
        'chapter' => (int) $row['chapter'],
        'power'   => (int) $row['power'],
        'souls'   => (int) $row['souls'],
        'clan'    => isset($row['clan_tag']) ? $row['clan_tag'] : null,
    ];
}

try {
    $pdo = db();
} catch (Throwable $e) {
    fail('base de données indisponible', 500);
}

switch ($action) {

    case 'register': {
        $pseudo = field($in, 'pseudo', 18);
        $email = mb_strtolower(field($in, 'email'));
        $password = (string) ($in['password'] ?? '');
        if (mb_strlen($pseudo) < 3) fail('pseudo trop court');
        if (!preg_match('/^[\p{L}\p{N}_\-\.]+$/u', $pseudo)) fail('pseudo : lettres, chiffres, - _ . uniquement');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('e-mail invalide');
        if (strlen($password) < 6) fail('mot de passe trop court');

        $exists = $pdo->prepare('SELECT id FROM players WHERE pseudo = ? OR email = ? LIMIT 1');
        $exists->execute([$pseudo, $email]);
        if ($exists->fetch()) fail('pseudo ou e-mail déjà utilisé');

        $token = new_token();
        $stmt = $pdo->prepare('INSERT INTO players (pseudo, email, pass_hash, token, device) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$pseudo, $email, password_hash($password, PASSWORD_DEFAULT), $token, field($in, 'device', 32)]);
        ok(['token' => $token, 'player' => ['pseudo' => $pseudo, 'chapter' => 1, 'power' => 0, 'souls' => 0, 'clan' => null]]);
    }

    case 'login': {
        $login = field($in, 'pseudo', 190);
        if ($login === '') $login = field($in, 'email', 190);
        $password = (string) ($in['password'] ?? '');
        $stmt = $pdo->prepare('SELECT p.*, c.tag AS clan_tag FROM players p LEFT JOIN clans c ON c.id = p.clan_id
                               WHERE p.pseudo = ? OR p.email = ? LIMIT 1');
        $stmt->execute([$login, mb_strtolower($login)]);
        $row = $stmt->fetch();
        if (!$row || !$row['pass_hash'] || !password_verify($password, $row['pass_hash'])) {
            fail('identifiants incorrects', 401);
        }
        $token = new_token();
        $pdo->prepare('UPDATE players SET token = ?, device = ? WHERE id = ?')
            ->execute([$token, field($in, 'device', 32), $row['id']]);
        ok(['token' => $token, 'player' => public_player($row)]);
    }

    case 'google_claim': {
        $code = strtoupper(field($in, 'code', 8));
        $stmt = $pdo->prepare('SELECT * FROM google_codes WHERE code = ? AND expires_at > NOW() LIMIT 1');
        $stmt->execute([$code]);
        $entry = $stmt->fetch();
        if (!$entry) fail('code inconnu ou expiré');
        $pdo->prepare('DELETE FROM google_codes WHERE code = ?')->execute([$code]);

        $stmt = $pdo->prepare('SELECT p.*, c.tag AS clan_tag FROM players p LEFT JOIN clans c ON c.id = p.clan_id
                               WHERE p.google_sub = ? OR p.email = ? LIMIT 1');
        $stmt->execute([$entry['google_sub'], $entry['email']]);
        $row = $stmt->fetch();
        $token = new_token();
        if ($row) {
            $pdo->prepare('UPDATE players SET token = ?, google_sub = ? WHERE id = ?')
                ->execute([$token, $entry['google_sub'], $row['id']]);
            ok(['token' => $token, 'player' => public_player($row)]);
        }
        $base = $entry['name'] ? preg_replace('/[^\p{L}\p{N}]/u', '', $entry['name']) : 'Cadet';
        $pseudo = mb_substr($base, 0, 14);
        $suffix = 0;
        while (true) {
            $candidate = $suffix ? $pseudo . $suffix : $pseudo;
            $check = $pdo->prepare('SELECT id FROM players WHERE pseudo = ? LIMIT 1');
            $check->execute([$candidate]);
            if (!$check->fetch()) { $pseudo = $candidate; break; }
            $suffix++;
        }
        $pdo->prepare('INSERT INTO players (pseudo, email, google_sub, token, device) VALUES (?, ?, ?, ?, ?)')
            ->execute([$pseudo, $entry['email'], $entry['google_sub'], $token, field($in, 'device', 32)]);
        ok(['token' => $token, 'player' => ['pseudo' => $pseudo, 'chapter' => 1, 'power' => 0, 'souls' => 0, 'clan' => null]]);
    }

    case 'sync': {
        $player = require_player($pdo, $in);
        $chapter = max(1, min(1000, (int) ($in['chapter'] ?? 1)));
        $power = max(0, min(100000, (int) ($in['power'] ?? 0)));
        $souls = max(0, (int) ($in['souls'] ?? 0));
        $level = max(1, (int) ($in['level'] ?? 1));
        $kills = max(0, (int) ($in['kills'] ?? 0));
        $prestiges = max(0, (int) ($in['prestiges'] ?? 0));
        $playtime = max(0, (int) ($in['playtime'] ?? 0));
        // On ne garde que la meilleure progression : le classement ne recule pas.
        $pdo->prepare('UPDATE players SET chapter = GREATEST(chapter, ?), power = GREATEST(power, ?),
                       souls = GREATEST(souls, ?), level = GREATEST(level, ?), kills = GREATEST(kills, ?),
                       prestiges = GREATEST(prestiges, ?), playtime = GREATEST(playtime, ?) WHERE id = ?')
            ->execute([$chapter, $power, $souls, $level, $kills, $prestiges, $playtime, $player['id']]);
        $rank = $pdo->prepare('SELECT COUNT(*) + 1 AS r FROM players WHERE power > (SELECT power FROM players WHERE id = ?)');
        $rank->execute([$player['id']]);
        ok(['rank' => (int) $rank->fetch()['r']]);
    }

    case 'leaderboard': {
        $scope = field($in, 'scope', 10) === 'clan' ? 'clan' : 'global';
        if ($scope === 'clan') {
            $player = require_player($pdo, $in);
            if (!$player['clan_id']) ok(['entries' => []]);
            $stmt = $pdo->prepare('SELECT p.pseudo, p.chapter, p.power, p.souls, c.tag AS clan FROM players p
                                   LEFT JOIN clans c ON c.id = p.clan_id WHERE p.clan_id = ?
                                   ORDER BY p.power DESC LIMIT 50');
            $stmt->execute([$player['clan_id']]);
        } else {
            $stmt = $pdo->query('SELECT p.pseudo, p.chapter, p.power, p.souls, c.tag AS clan FROM players p
                                 LEFT JOIN clans c ON c.id = p.clan_id ORDER BY p.power DESC LIMIT 50');
        }
        $entries = [];
        $rank = 1;
        foreach ($stmt->fetchAll() as $row) {
            $entries[] = [
                'rank'    => $rank++,
                'pseudo'  => $row['pseudo'],
                'chapter' => (int) $row['chapter'],
                'power'   => (int) $row['power'],
                'souls'   => (int) $row['souls'],
                'clan'    => $row['clan'],
            ];
        }
        ok(['entries' => $entries]);
    }

    case 'clan': {
        $player = require_player($pdo, $in);
        if (!$player['clan_id']) ok(['clan' => null]);
        $clan = $pdo->prepare('SELECT * FROM clans WHERE id = ?');
        $clan->execute([$player['clan_id']]);
        $row = $clan->fetch();
        if (!$row) ok(['clan' => null]);
        $members = $pdo->prepare('SELECT pseudo, chapter, power FROM players WHERE clan_id = ? ORDER BY power DESC LIMIT 50');
        $members->execute([$player['clan_id']]);
        $list = $members->fetchAll();
        $score = 0;
        foreach ($list as $m) { $score += (int) $m['power']; }
        ok(['clan' => [
            'name'    => $row['name'],
            'tag'     => $row['tag'],
            'score'   => $score,
            'members' => array_map(function ($m) {
                return ['pseudo' => $m['pseudo'], 'chapter' => (int) $m['chapter'], 'power' => (int) $m['power']];
            }, $list),
        ]]);
    }

    case 'clan_create': {
        $player = require_player($pdo, $in);
        if ($player['clan_id']) fail('quittez d\'abord votre clan');
        $name = field($in, 'name', 32);
        $tag = strtoupper(field($in, 'tag', 6));
        if (mb_strlen($name) < 3) fail('nom de clan trop court');
        if (!preg_match('/^[A-Z0-9]{2,6}$/', $tag)) fail('tag : 2 à 6 lettres ou chiffres');
        $exists = $pdo->prepare('SELECT id FROM clans WHERE tag = ? LIMIT 1');
        $exists->execute([$tag]);
        if ($exists->fetch()) fail('ce tag existe déjà');
        $pdo->prepare('INSERT INTO clans (tag, name, owner_id) VALUES (?, ?, ?)')->execute([$tag, $name, $player['id']]);
        $clanId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE players SET clan_id = ? WHERE id = ?')->execute([$clanId, $player['id']]);
        ok(['clan' => ['name' => $name, 'tag' => $tag, 'score' => (int) $player['power'],
            'members' => [['pseudo' => $player['pseudo'], 'chapter' => (int) $player['chapter'], 'power' => (int) $player['power']]]]]);
    }

    case 'clan_join': {
        $player = require_player($pdo, $in);
        $tag = strtoupper(field($in, 'tag', 6));
        $clan = $pdo->prepare('SELECT * FROM clans WHERE tag = ? LIMIT 1');
        $clan->execute([$tag]);
        $row = $clan->fetch();
        if (!$row) fail('clan introuvable');
        $pdo->prepare('UPDATE players SET clan_id = ? WHERE id = ?')->execute([$row['id'], $player['id']]);
        $members = $pdo->prepare('SELECT pseudo, chapter, power FROM players WHERE clan_id = ? ORDER BY power DESC LIMIT 50');
        $members->execute([$row['id']]);
        $list = $members->fetchAll();
        $score = 0;
        foreach ($list as $m) { $score += (int) $m['power']; }
        ok(['clan' => ['name' => $row['name'], 'tag' => $row['tag'], 'score' => $score,
            'members' => array_map(function ($m) {
                return ['pseudo' => $m['pseudo'], 'chapter' => (int) $m['chapter'], 'power' => (int) $m['power']];
            }, $list)]]);
    }

    case 'clan_leave': {
        $player = require_player($pdo, $in);
        $pdo->prepare('UPDATE players SET clan_id = NULL WHERE id = ?')->execute([$player['id']]);
        $pdo->prepare('DELETE c FROM clans c LEFT JOIN players p ON p.clan_id = c.id WHERE c.id = ? AND p.id IS NULL')
            ->execute([$player['clan_id']]);
        ok();
    }

    default:
        fail('action inconnue');
}

