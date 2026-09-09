<?php
declare(strict_types=1);
/**
 * Copie ce fichier en config.php et remplis-le. config.php n'est pas versionne.
 */
return [
    // Domaine dont tu es proprietaire, avec un catch-all actif.
    'domaine' => 'asylum-games.fr',

    // Boite catch-all : ces identifiants ne quittent JAMAIS le serveur.
    'imap' => [
        'hote' => 'mail.asylum-games.fr',
        'port' => 993,
        'ssl' => true,
        'utilisateur' => 'catchall@asylum-games.fr',
        'mot_de_passe' => '',      // ou laisse vide et utilise la variable d'env DT_IMAP_MDP
        'dossier' => 'INBOX',
    ],

    // Stockage des alias. SQLite par defaut : indique un chemin HORS du web.
    'base' => [
        'dsn' => 'sqlite:' . __DIR__ . '/donnees/alias.sqlite',
        // MySQL (LWS) : 'mysql:host=localhost;dbname=xxx;charset=utf8mb4'
        'utilisateur' => null,
        'mot_de_passe' => null,
    ],

    'duree' => [
        'defaut' => 3600,          // 1 heure
        'minimum' => 300,          // 5 minutes
        'maximum' => 86400,        // 24 heures : plafond impose au client
    ],

    'limites' => [
        'creations_par_ip_par_heure' => 20,
        'alias_actifs_max' => 5000,
        'messages_par_releve' => 50,
    ],

    // Cle secrete pour declencher /purger depuis le cron LWS. Genere-la au hasard.
    'cle_purge' => 'change-moi',
];
