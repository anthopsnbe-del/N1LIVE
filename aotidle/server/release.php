<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) { http_response_code(405); header('Allow: GET, HEAD, OPTIONS'); exit; }
require_once __DIR__ . '/release-lib.php';
try {
    $release = aot_release();
    $public = [
        'versionCode' => $release['versionCode'],
        'versionName' => $release['versionName'],
        'packageName' => $release['packageName'],
        'downloadUrl' => 'https://asylum-games.fr/aotidle/telecharger.php',
        'notes' => (string) ($release['notes'] ?? ''),
        'sha256' => $release['sha256'],
        'size' => $release['size']
    ];
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') echo json_encode($public, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(503);
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') echo json_encode(['error' => 'Téléchargement temporairement indisponible.'], JSON_UNESCAPED_UNICODE);
}
