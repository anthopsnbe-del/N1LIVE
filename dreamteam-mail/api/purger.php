<?php
declare(strict_types=1);
/**
 * Purge en ligne de commande, pour la tache cron LWS :
 *   /usr/local/bin/php /home/.../api/purger.php
 * Effacer les alias expires ici garantit que la boite catch-all ne gonfle pas,
 * meme si personne n'ouvre l'application.
 */
$_GET['action'] = 'purger';
$_GET['cle'] = (require __DIR__ . '/config.php')['cle_purge'];
require __DIR__ . '/index.php';
