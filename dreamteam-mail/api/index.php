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
 *   POST ?action=supprimer_message alias, jeton, uid -> {supprime: bool}
 *   POST ?action=envoyer    alias, jeton, destinataire, sujet, corps -> {envoye: true}
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
    $expireA = (int) $ligne['expire_a'];
    if ($expireA !== 0 && $expireA <= time()) {
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
        $duree = champ('duree') === '' ? (int) $config['duree']['defaut'] : (int) champ('duree');
        if ($duree === 0 && ($config['duree']['a_vie_autorisee'] ?? false)) {
            $duree = 0;  // adresse conservee a vie : expire_a reste nul
        } else {
            $duree = max($config['duree']['minimum'], min($config['duree']['maximum'], $duree));
        }

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
        $expireA = $duree === 0 ? 0 : $maintenant + $duree;
        $depot->creer($alias, hash('sha256', $jeton), $maintenant, $expireA, $ip);
        repondre([
            'alias' => $alias,
            'email' => $alias . '@' . $config['domaine'],
            'jeton' => $jeton,
            'duree' => $duree,
            'expire_a' => $expireA,
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
                    $messages[] = ['uid' => (string) $uid] + Mime::analyser($brut);
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

    if ($action === 'supprimer_message') {
        $alias = champ('alias');
        autoriser($depot, $alias, champ('jeton'));
        $uid = champ('uid');
        if (!preg_match('/^\d+$/', $uid)) {
            erreur('UID invalide.', 400);
        }
        $adresse = $alias . '@' . $config['domaine'];

        $client = imap($config, false);
        try {
            // On ne supprime que si cet UID appartient bien a l'alias demande.
            if (!in_array($uid, $client->chercherPourDestinataire($adresse), true)) {
                erreur('Ce message n\'appartient pas a cet alias.', 403);
            }
            $supprime = $client->supprimerUn($uid);
        } finally {
            $client->fermer();
        }
        repondre(['supprime' => $supprime]);
    }

    if ($action === 'envoyer') {
        $alias = champ('alias');
        autoriser($depot, $alias, champ('jeton'));
        $destinataire = champ('destinataire');
        if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
            erreur('Destinataire invalide.', 400);
        }
        $sujet = mb_substr(champ('sujet'), 0, 200);
        $corps = mb_substr((string) ($_POST['corps'] ?? ''), 0, 20000);
        if (trim($corps) === '') {
            erreur('Message vide.', 400);
        }
        $de = $alias . '@' . $config['domaine'];
        // Les en-tetes sont construits ici : rien de ce que fournit le client
        // n'y est injecte tel quel (les retours a la ligne sont retires).
        $nettoyer = static fn (string $v): string => trim(str_replace(["\r", "\n"], ' ', $v));

        // Pieces jointes : piece0_nom / piece0_donnees (base64), piece1_..., etc.
        $pieces = [];
        $poids = 0;
        for ($i = 0; $i < 10; $i++) {
            $nom = (string) ($_POST["piece{$i}_nom"] ?? '');
            $donnees = (string) ($_POST["piece{$i}_donnees"] ?? '');
            if ($nom === '' || $donnees === '') {
                continue;
            }
            $binaire = base64_decode($donnees, true);
            if ($binaire === false) {
                erreur('Piece jointe illisible.', 400);
            }
            $poids += strlen($binaire);
            if ($poids > 10 * 1024 * 1024) {
                erreur('Pieces jointes trop lourdes (10 Mo au maximum).', 413);
            }
            // Le nom de fichier est reduit a sa base : pas de chemin, pas de saut de ligne.
            $pieces[] = ['nom' => basename($nettoyer($nom)), 'donnees' => $binaire];
        }

        // Des en-tetes complets (Date, Message-ID, chainage) evitent le dossier
        // indesirables : les filtres penalisent lourdement leur absence.
        $entetes = [
            'From: ' . $nettoyer($de),
            'Reply-To: ' . $nettoyer($de),
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $config['domaine'] . '>',
            'MIME-Version: 1.0',
        ];
        $repondA = $nettoyer(champ('repond_a'));
        if ($repondA !== '' && str_starts_with($repondA, '<')) {
            $entetes[] = 'In-Reply-To: ' . $repondA;
            $entetes[] = 'References: ' . $repondA;
        }

        if ($pieces === []) {
            $entetes[] = 'Content-Type: text/plain; charset=UTF-8';
            $contenu = $corps;
        } else {
            $limite = 'LIMITE-' . bin2hex(random_bytes(12));
            $entetes[] = 'Content-Type: multipart/mixed; boundary="' . $limite . '"';
            $morceaux = ["--{$limite}",
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit', '', $corps, ''];
            foreach ($pieces as $piece) {
                $morceaux[] = "--{$limite}";
                $morceaux[] = 'Content-Type: application/octet-stream; name="' . $piece['nom'] . '"';
                $morceaux[] = 'Content-Transfer-Encoding: base64';
                $morceaux[] = 'Content-Disposition: attachment; filename="' . $piece['nom'] . '"';
                $morceaux[] = '';
                $morceaux[] = chunk_split(base64_encode($piece['donnees']), 76, "\r\n");
            }
            $morceaux[] = "--{$limite}--";
            $contenu = implode("\r\n", $morceaux);
        }

        $envoye = @mail(
            $nettoyer($destinataire),
            $nettoyer($sujet !== '' ? $sujet : '(sans objet)'),
            $contenu,
            implode("\r\n", $entetes),
            '-f' . $nettoyer($de)
        );
        if (!$envoye) {
            erreur("Le serveur a refuse l'envoi.", 502);
        }
        repondre(['envoye' => true]);
    }

    if ($action === 'purger') {
        if (!hash_equals((string) $config['cle_purge'], champ('cle'))) {
            erreur('Cle de purge refusee.', 403);
        }
        $expires = $depot->expires(time());  // expire_a = 0 (a vie) est exclu
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
