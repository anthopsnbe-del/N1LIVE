<?php
declare(strict_types=1);
/** Suite hors-ligne : règles du jeu et surtout non-régression des sauvegardes existantes.
 *  Usage : php tests/plantation_test.php */
require __DIR__.'/../plantation_lib.php';
require __DIR__.'/../plantation_ranking.php';

$passed = 0; $failed = 0;
function ok(string $label, bool $condition): void {
    global $passed, $failed;
    if ($condition) { $passed++; echo "  ok   $label\n"; }
    else { $failed++; echo "  FAIL $label\n"; }
}
function fails(string $label, callable $fn): void {
    try { $fn(); ok($label.' (refusé)', false); }
    catch (DomainException $e) { ok($label.' (refusé : '.$e->getMessage().')', true); }
}

echo "Catalogue\n";
$catalog = gp_catalog();
$bases = array_filter($catalog, fn($v) => !$v['parents']);
$hybrids = array_filter($catalog, fn($v) => (bool)$v['parents']);
ok('14 fondatrices', count($bases) === 14);
ok('91 hybrides', count($hybrids) === 91);
foreach ($hybrids as $id => $v) {
    $parents = $v['parents']; sort($parents, SORT_STRING);
    if (implode('--', $parents) !== $id) { ok("identifiant cohérent pour $id", false); break; }
}
ok('identifiants hybrides cohérents avec leurs parents', true);

echo "\nCycle de culture\n";
$now = 1000;
$s = gp_initial();
$s = gp_apply($s, 'soil', ['pot' => '0'], $now);
ok('terreau consommé', $s['soil'] === 11 && $s['pots'][0]['soil'] === true);
$s = gp_apply($s, 'plant', ['pot' => '0', 'seed' => 'emeraude'], $now);
ok('graine semée', $s['seeds']['emeraude'] === 1 && $s['pots'][0]['plant']['id'] === 'emeraude');
$s = gp_apply($s, 'water', ['pot' => '0'], $now);
ok('pousse lancée', $s['water'] === 5 && $s['pots'][0]['plant']['ready'] === $now + $catalog['emeraude']['duration']);
fails('récolte avant maturité', fn() => gp_apply($s, 'harvest', ['pot' => '0'], $now + 10));
$s = gp_apply($s, 'feed', ['pot' => '0'], $now + 10);
$ready = $s['pots'][0]['plant']['ready'];
$credits = $s['credits'];
$s = gp_apply($s, 'harvest', ['pot' => '0'], $ready);
ok('récolte étoilée : 3 buds', $s['buds']['emeraude'] === 3);
ok('récolte : 2 graines rendues', $s['seeds']['emeraude'] === 3);
ok('récolte : points crédités', $s['credits'] === $credits + $catalog['emeraude']['reward'] + 15);
ok('pot libéré', $s['pots'][0] === ['soil' => false, 'plant' => null]);
ok('aucune référence PHP ne fuit dans l’état', json_encode($s) === json_encode(json_decode(json_encode($s), true)));

echo "\nLaboratoire\n";
fails('croisement sans parent étudié', fn() => gp_apply($s, 'cross', ['a' => 'emeraude', 'b' => 'citron'], $now));
$t = $s;
$t['harvests']['citron'] = 1;
$t['credits'] = 100;
$t = gp_apply($t, 'cross', ['a' => 'emeraude', 'b' => 'citron'], $now);
ok('graine hybride créée', ($t['seeds']['citron--emeraude'] ?? 0) === 1);
ok('hybride marqué comme découvert', !empty($t['discoveries']['citron--emeraude']));
ok('coût du croisement', $t['credits'] === 40);
fails('croisement de deux hybrides', fn() => gp_apply($t, 'cross', ['a' => 'citron--emeraude', 'b' => 'emeraude'], $now));

echo "\nBoutique et serre\n";
fails('achat sans points', function () use ($now) { $x = gp_initial(); $x['credits'] = 0; gp_apply($x, 'buy', ['item' => 'soil'], $now); });
$b = gp_initial();
$b = gp_apply($b, 'buy', ['item' => 'soil'], $now);
ok('5 sacs de terreau pour 15 points', $b['soil'] === 17 && $b['credits'] === 235);
$b = gp_apply($b, 'pot', [], $now);
ok('5e pot ouvert pour 150 points', count($b['pots']) === 5 && $b['credits'] === 85);
fails('accessoire déjà installé', function () use ($now) {
    $x = gp_initial(); $x['accessories']['fan'] = true;
    gp_apply($x, 'accessory', ['item' => 'fan'], $now);
});

echo "\nSauvegardes existantes (aucune remise à zéro)\n";
$old = ['version' => 1, 'revision' => 57, 'credits' => 4210, 'soil' => 3, 'water' => 2, 'feed' => 1,
    'seeds' => ['emeraude' => 7, 'citron--emeraude' => 2], 'pots' => [['soil' => true, 'plant' => null], ['soil' => false, 'plant' => null]],
    'buds' => ['emeraude' => 31], 'harvests' => ['emeraude' => 12, 'citron' => 4], 'discoveries' => ['citron--emeraude' => true],
    'total' => 16, 'history' => [], 'seen' => []];
$up = gp_upgrade($old);
ok('points conservés', $up['credits'] === 4210);
ok('graines conservées', $up['seeds']['emeraude'] === 7 && $up['seeds']['citron--emeraude'] === 2);
ok('récoltes conservées', $up['harvests'] === ['emeraude' => 12, 'citron' => 4]);
ok('buds conservés', $up['buds']['emeraude'] === 31);
ok('découvertes conservées', $up['discoveries']['citron--emeraude'] === true);
ok('pots conservés (pas de retour à 4)', count($up['pots']) === 2 && $up['pots'][0]['soil'] === true);
ok('révision conservée', $up['revision'] === 57);
ok('cadeau v2 accordé une fois', $up['seeds']['diesel'] === 2);
$twice = gp_upgrade($up);
ok('cadeau v2 non redonné', $twice['seeds']['diesel'] === 2 && $twice === $up);

$partial = ['version' => 2, 'credits' => 900, 'seeds' => ['emeraude' => 1], 'pots' => [['soil' => false, 'plant' => null]]];
$fixed = gp_upgrade($partial);
ok('sauvegarde incomplète complétée sans perte', $fixed['credits'] === 900 && $fixed['seeds']['emeraude'] === 1
    && $fixed['buds'] === [] && $fixed['harvests'] === [] && count($fixed['pots']) === 1);

echo "\nClassement\n";
$m = gp_metrics($up);
ok('score = variétés×100 + hybrides×250 + récoltes plafonnées',
    $m['score'] === 2 * 100 + 1 * 250 + min(100, 16));
$spent = $up; $spent['credits'] = 0; $spent['buds'] = [];
ok('dépenser ne réduit jamais le score', gp_metrics($spent)['score'] === $m['score']);
$board = gp_rank_rows([
    ['username' => 'zoe', 'board_value' => 500], ['username' => 'ana', 'board_value' => 500], ['username' => 'bob', 'board_value' => 900],
], 'ana');
ok('égalité = même rang', $board['rows'][1]['rank'] === 2 && $board['rows'][2]['rank'] === 2);
ok('joueur identifié', $board['me']['username'] === 'ana');

echo "\n$passed réussis, $failed échoués\n";
exit($failed ? 1 : 0);
