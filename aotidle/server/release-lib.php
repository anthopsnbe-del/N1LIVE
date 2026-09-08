<?php
declare(strict_types=1);

// Reads only the published artifact; request parameters never select a file.
function aot_release(): array {
    $raw = @file_get_contents(__DIR__ . '/release.json');
    $release = $raw === false ? null : json_decode($raw, true);
    if (!is_array($release)
        || !isset($release['versionCode'], $release['versionName'], $release['file'], $release['sha256'], $release['size'])
        || !is_int($release['versionCode']) || $release['versionCode'] < 1
        || !is_string($release['file']) || !preg_match('/^aot-idle-[0-9]+\.[0-9]+(?:\.[0-9]+)?\.apk$/D', $release['file'])
        || !is_string($release['sha256']) || !preg_match('/^[a-f0-9]{64}$/D', $release['sha256'])
        || !is_int($release['size']) || $release['size'] < 1
        || ($release['packageName'] ?? '') !== 'com.n1live.aotidl3') {
        throw new RuntimeException('Release unavailable');
    }
    $directory = realpath(__DIR__ . '/releases');
    $path = realpath(__DIR__ . '/releases/' . $release['file']);
    if (!$directory || !$path || dirname($path) !== $directory || !is_file($path) || filesize($path) !== $release['size']) {
        throw new RuntimeException('Artifact unavailable');
    }
    $release['path'] = $path;
    return $release;
}
