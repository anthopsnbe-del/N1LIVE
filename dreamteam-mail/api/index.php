<?php
declare(strict_types=1);

/**
 * API des adresses jetables.
 *
 * Le client n'a jamais les identifiants de la boite catch-all : il recoit un
 * alias et un jeton, qui ne donnent acces qu'au courrier de cet alias.
 *
 *   POST ?action=creer      [duree]           -> {alias, jeton, expire_a}
 *   POST ?action=relever    alias, jeton      -> {messages: [...]}
 *   POST ?action=supprimer  alias, jeton      -> {supprimes: n}
 *   GET  ?action=purger     cle               -> {purges: n}   (cron)
 *   GET  ?action=etat                         -> {ok, domaine, duree}
 */

require __DIR__ . '/lib/Imap.php';
require __DIR__ . '/lib/Mime.php';
require __DIR__ . '/lib/Depot.php';
require __DIR__ . '/lib/Alias.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$config = require __DIR__ . '/config.php';
if (($config['imap']['mot_de_passe'] ?? '') === '') {
    $config['imap']['mot_de_passe'] = (string) getenv('DT_IMAP_MDP');
}

function repondre(array $charge, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function erreur(string $message, int $code = 400): never
{
    repondre(['erreur' => $message], $code);
}

function champ(string $nom): string
{
    $valeur = $_POST[$nom] ?? $_GET[$nom] ?? '';
    return is_string($valeur) ? trim($valeur) : '';
}

function empreinteIp(): string
{
    // On stocke une empreinte, pas l'adresse : suffisant pour limiter le debit.
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '?') . '|dreamteam');
}

/** Ouvre la boite catch-all. */
function imap(array $config, bool $lectureSeule): Imap
{
    $client = new Imap(
        $config['imap']['hote'], (int) $config['imap']['port'],
        $config['imap']['utilisateur'], $config['imap']['mot_de_passe'],
        (bool) $config['imap']['ssl']
    );
    $client->connecter();
    $client->selectionner($config['imap']['dossier'] ?? 'INBOX', $lectureSeule);
    return $client;
}

/** Verifie alias + jeton et retourne la ligne, ou repond 403. */
function autoriser(Depot $depot, string $alias, string $jeton): array
{
    if (!Alias::valide($alias) || !preg_match('/^[a-f0-9]{32}$/', $jeton)) {
        erreur('Alias ou jeton invalide.', 400);
    }
    $ligne = $depot->lire($alias);
    if ($ligne === null || !hash_equals($ligne['jeton_hash'], hash('sha256', $jeton))) {
        erreur('Alias inconnu ou jeton refuse.', 403);
    }
    if ((int) $ligne['expire_a'] <= time()) {
        erreur('Alias expire.', 410);
    }
    return $ligne;
}

try {
    $depot = new Depot($config['base']);
    $action = champ('action');

    if ($action === 'etat') {
        // Aucune donnee sensible : sert au bouton « Tester » du client.
        repondre([
            'ok' => true,
            'domaine' => $config['domaine'],
            'duree' => $config['duree'],
        ]);
    }

    if ($action === 'creer') {
        $ip = empreinteIp();
        if ($depot->creationsRecentes($ip, time() - 3600) >= $config['limites']['creations_par_ip_par_heure']) {
            erreur('Trop de creations depuis cette connexion. Reessaie plus tard.', 429);
        }
        if ($depot->nombreActifs() >= $config['limites']['alias_actifs_max']) {
            erreur('Service sature, reessaie plus tard.', 503);
        }
        $duree = (int) (champ('duree') ?: $config['duree']['defaut']);
        $duree = max($config['duree']['minimum'], min($config['duree']['maximum'], $duree));

        $alias = null;
        for ($essai = 0; $essai < 40; $essai++) {
            $candidat = Alias::generer();
            if (Alias::valide($candidat) && !$depot->existe($candidat)) {
                $alias = $candidat;
                break;
            }
        }
        if ($alias === null) {
            erreur('Impossible de generer un alias libre.', 503);
        }
        $jeton = bin2hex(random_bytes(16));
        $maintenant = time();
        $depot->creer($alias, hash('sha256', $jeton), $maintenant, $maintenant + $duree, $ip);
        repondre([
            'alias' => $alias,
            'email' => $alias . '@' . $config['domaine'],
            'jeton' => $jeton,
            'duree' => $duree,
            'expire_a' => $maintenant + $duree,
        ]);
    }

    if ($action === 'relever') {
        $alias = champ('alias');
        autoriser($depot, $alias, champ('jeton'));
        $adresse = $alias . '@' . $config['domaine'];

        $client = imap($config, true);
        try {
            $uids = $client->chercherPourDestinataire($adresse);
            $uids = array_slice($uids, -1 * (int) $config['limites']['messages_par_releve']);
            $messages = [];
            foreach ($uids as $uid) {
                $brut = $client->messageBrut($uid);
                if ($brut !== null) {
                    $messages[] = Mime::analyser($brut);
                }
            }
        } finally {
            $client->fermer();
        }
        repondre(['messages' => $messages]);
    }

    if ($action === 'supprimer') {
        $alias = champ('alias');
        autoriser($depot, $alias, champ('jeton'));
        $adresse = $alias . '@' . $config['domaine'];

        $client = imap($config, false);
        try {
            $supprimes = $client->supprimer($client->chercherPourDestinataire($adresse));
        } finally {
            $client->fermer();
        }
        $depot->supprimer($alias);
        repondre(['supprimes' => $supprimes]);
    }

    if ($action === 'purger') {
        if (!hash_equals((string) $config['cle_purge'], champ('cle'))) {
            erreur('Cle de purge refusee.', 403);
        }
        $expires = $depot->expires(time());
        if ($expires === []) {
            repondre(['purges' => 0, 'messages_effaces' => 0]);
        }
        $client = imap($config, false);
        $effaces = 0;
        try {
            foreach ($expires as $alias) {
                $adresse = $alias . '@' . $config['domaine'];
                $effaces += $client->supprimer($client->chercherPourDestinataire($adresse));
                $depot->supprimer($alias);
            }
        } finally {
            $client->fermer();
        }
        repondre(['purges' => count($expires), 'messages_effaces' => $effaces]);
    }

    erreur('Action inconnue.', 404);
} catch (Throwable $e) {
    // Le detail reste dans les logs du serveur : le client n'apprend rien d'utile.
    error_log('[dreamteam-api] ' . $e->getMessage());
    erreur('Erreur interne.', 500);
}
