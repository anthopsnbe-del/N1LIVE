<?php
declare(strict_types=1);
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) { http_response_code(405); header('Allow: GET, HEAD'); exit; }
require_once __DIR__ . '/release-lib.php';
try { $release = aot_release(); }
catch (Throwable $error) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') echo 'Le jeu est temporairement indisponible. Réessayez dans quelques instants.';
    exit;
}
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $release['file'] . '"');
header('Content-Length: ' . $release['size']);
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') readfile($release['path']);
