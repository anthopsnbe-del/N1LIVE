<?php
declare(strict_types=1);

/* Journal de clan et emotes.
 *
 * Un clan qui ne parle qu'au moment de la guerre n'existe qu'un jour sur deux.
 * Le journal garde une trace commune de ce que le clan a fait — engagement,
 * assaut décisif, victoire, butin récupéré — et les emotes laissent réagir
 * sans ouvrir un champ de texte libre de plus : la liste est fixée ici, le
 * client n'envoie qu'un identifiant. Rien de ce qui est écrit dans le journal
 * ne vient donc du joueur.
 *
 * Une seule table additive, purgée par clan pour ne pas grossir sans fin.
 */

const CLAN_LOG_KEEP    = 120;    // entrées conservées par clan
const CLAN_LOG_PAGE    = 40;     // entrées renvoyées au client
const CLAN_EMOTE_DELAY = 10000;  // dix secondes entre deux emotes

/* Emotes : identifiant → texte affiché. La liste vit côté serveur pour qu'un
   client bricolé ne puisse pas faire dire n'importe quoi au journal. */
function clan_emotes(): array
{
    return [
        'salut'   => ['Salut le bataillon !', '🫡'],
        'guerre'  => ['En avant, on lance l\'assaut !', '⚔️'],
        'aide'    => ['Besoin de renforts sur le front.', '🆘'],
        'bravo'   => ['Beau travail !', '👏'],
        'boss'    => ['Le titan du jour nous attend.', '🗡️'],
        'marche'  => ['J\'ai posté une pièce au marché.', '💠'],
        'pause'   => ['Je repasse plus tard.', '🌙'],
        'rire'    => ['Ça, c\'était du grand art.', '😂']
    ];
}

function clan_install(PDO $db): void
{
    $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $db->exec('CREATE TABLE IF NOT EXISTS social_clan_log (id VARCHAR(32) PRIMARY KEY,
        clan_id INTEGER NOT NULL, user_id INTEGER NOT NULL DEFAULT 0, kind VARCHAR(16) NOT NULL,
        body VARCHAR(160) NOT NULL, created_at BIGINT NOT NULL)' . $suffix);
}

/* Écrit une ligne au journal du clan. Appelée par les autres modules : la
   fonction est volontairement tolérante — un journal qui échoue ne doit jamais
   faire échouer un assaut. */
function clan_log(PDO $db, int $clanId, int $userId, string $kind, string $body, int $now): void
{
    if ($clanId < 1) {
        return;
    }
    try {
        sq($db, 'INSERT INTO social_clan_log (id, clan_id, user_id, kind, body, created_at) VALUES (?,?,?,?,?,?)',
            [bin2hex(random_bytes(16)), $clanId, $userId, $kind, mb_substr($body, 0, 160), $now]);
        clan_log_purge($db, $clanId);
    } catch (Throwable $e) {
        // Le journal est un confort, pas une garantie.
    }
}

function clan_log_purge(PDO $db, int $clanId): void
{
    // OFFSET n'accepte pas de paramètre lié sur MySQL : la borne est une
    // constante entière de ce fichier, insérée telle quelle.
    $old = sq($db, 'SELECT id FROM social_clan_log WHERE clan_id = ? ORDER BY created_at DESC, id
                    LIMIT 500 OFFSET ' . (int) CLAN_LOG_KEEP, [$clanId])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($old as $id) {
        sq($db, 'DELETE FROM social_clan_log WHERE id = ?', [$id]);
    }
}

function clan_journal(PDO $db, int $clanId): array
{
    $rows = sq($db, 'SELECT l.id, l.user_id, l.kind, l.body, l.created_at, p.pseudo
                     FROM social_clan_log l LEFT JOIN players p ON p.id = l.user_id
                     WHERE l.clan_id = ? ORDER BY l.created_at DESC, l.id DESC LIMIT ' . (int) CLAN_LOG_PAGE,
        [$clanId])->fetchAll(PDO::FETCH_ASSOC);
    return array_map(fn($row) => [
        'id' => $row['id'],
        'kind' => $row['kind'],
        'who' => $row['pseudo'] ?: '',
        'body' => $row['body'],
        'at' => (int) $row['created_at']
    ], array_reverse($rows));
}

function clan_view(PDO $db, int $clanId, int $now): array
{
    $emotes = [];
    foreach (clan_emotes() as $id => $entry) {
        $emotes[] = ['id' => $id, 'text' => $entry[0], 'icon' => $entry[1]];
    }
    return ['journal' => clan_journal($db, $clanId), 'emotes' => $emotes, 'serverNow' => $now];
}

function clan_handle(PDO $db, array $in, array $player, int $clanId, int $now): ?array
{
    $action = $in['action'] ?? '';
    if (!in_array($action, ['clan_journal', 'clan_emote'], true)) {
        return null;
    }
    if ($clanId < 1) {
        social_error('Rejoignez un clan pour ouvrir son journal.');
    }
    $user = (int) $player['id'];

    if ($action === 'clan_emote') {
        $emotes = clan_emotes();
        $id = (string) ($in['emote'] ?? '');
        if (!isset($emotes[$id])) {
            social_error('Emote inconnue.');
        }
        $last = sq($db, 'SELECT created_at FROM social_clan_log WHERE clan_id = ? AND user_id = ? AND kind = ?
                         ORDER BY created_at DESC LIMIT 1', [$clanId, $user, 'emote'])->fetch(PDO::FETCH_ASSOC);
        if ($last && (int) $last['created_at'] + CLAN_EMOTE_DELAY > $now) {
            social_error('Laissez passer quelques secondes entre deux emotes.');
        }
        clan_log($db, $clanId, $user, 'emote', $emotes[$id][1] . ' ' . $emotes[$id][0], $now);
    }

    return clan_view($db, $clanId, $now);
}
