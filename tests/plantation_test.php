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
ok('50 fondatrices', count($bases) === 50);
ok('500 hybrides', count($hybrids) === 500);
$ids = array_keys($catalog);
ok('identifiants uniques', count($ids) === count(array_unique($ids)));
$names = array_column($catalog, 'name');
ok('noms uniques', count($names) === count(array_unique($names)));
$tones = array_map(fn($v) => implode(',', $v['tones']), $catalog);
ok('combinaisons de teintes uniques', count($tones) === count(array_unique($tones)));
ok('2 à 4 teintes par variété', !array_filter($catalog, fn($v) => count($v['tones']) < 2 || count($v['tones']) > 4));
ok('teinte principale = première teinte', !array_filter($catalog, fn($v) => $v['bud'] !== $v['tones'][0]));
$missingParent = array_filter($hybrids, fn($v) => count(array_diff($v['parents'], array_keys($bases))) > 0);
ok('tous les parents sont des fondatrices', !$missingParent);
$legacyFounders = ['emeraude','nebuleuse','citron','velours','menthe','ambre','rose','givre','mangue','onyx','diesel','peche','pin','orchidee'];
ok('les 14 fondatrices d’origine sont toujours là', !array_diff($legacyFounders, array_keys($catalog)));
$legacyPairs = 0;
foreach ($legacyFounders as $a) foreach ($legacyFounders as $b) {
    if ($a >= $b) continue;
    $pair = [$a, $b]; sort($pair, SORT_STRING);
    if (isset($catalog[implode('--', $pair)])) $legacyPairs++;
}
ok('les 91 recettes d’origine sont conservées', $legacyPairs === 91);
$tiers = array_count_values(array_column($hybrids, 'rarity'));
ok('cinq paliers de rareté', count($tiers) === 5);
ok('les légendaires et mythiques restent rares', ($tiers['Légendaire'] + $tiers['Mythique']) < count($hybrids) * .1);
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
$recipe = $catalog['citron--emeraude'];
$rules = gp_tier_rules(gp_tier($recipe));
fails('croisement sans maîtrise des parents', fn() => gp_apply($s, 'cross', ['a' => 'emeraude', 'b' => 'citron'], $now));
$t = $s;
$t['harvests']['emeraude'] = $rules['mastery'];
$t['harvests']['citron'] = $rules['mastery'];
$t['seeds']['citron'] = 1;
$t['credits'] = $rules['cost'] + 40;
$t = gp_apply($t, 'cross', ['a' => 'emeraude', 'b' => 'citron'], $now);
ok('graine hybride créée', ($t['seeds']['citron--emeraude'] ?? 0) === 1);
ok('hybride marqué comme découvert', !empty($t['discoveries']['citron--emeraude']));
ok('coût prélevé selon la rareté ('.$recipe['rarity'].' : '.$rules['cost'].' pts)', $t['credits'] === 40);
fails('croisement de deux hybrides', fn() => gp_apply($t, 'cross', ['a' => 'citron--emeraude', 'b' => 'emeraude'], $now));

// Une recette rare exige davantage de récoltes et davantage de points.
$rare = null;
foreach ($hybrids as $id => $v) if (gp_tier($v) >= 3) { $rare = $v; break; }
ok('au moins une recette légendaire existe', $rare !== null);
$rareRules = gp_tier_rules(gp_tier($rare));
ok('la légendaire coûte plus cher que la commune', $rareRules['cost'] > gp_tier_rules(0)['cost']);
ok('la légendaire demande plus de maîtrise', $rareRules['mastery'] > gp_tier_rules(0)['mastery']);
[$ra, $rb] = $rare['parents'];
$hard = gp_initial();
$hard['credits'] = 99999;
$hard['seeds'][$ra] = 5; $hard['seeds'][$rb] = 5;
$hard['harvests'][$ra] = $rareRules['mastery'] - 1;
$hard['harvests'][$rb] = $rareRules['mastery'];
fails('légendaire refusée juste sous le seuil de maîtrise',
    fn() => gp_apply($hard, 'cross', ['a' => $ra, 'b' => $rb], $now));
$hard['harvests'][$ra] = $rareRules['mastery'];
$made = gp_apply($hard, 'cross', ['a' => $ra, 'b' => $rb], $now);
ok('légendaire obtenue une fois la maîtrise atteinte', ($made['seeds'][$rare['id']] ?? 0) === 1);
$poor = $hard; $poor['credits'] = $rareRules['cost'] - 1;
fails('légendaire refusée sans les points', fn() => gp_apply($poor, 'cross', ['a' => $ra, 'b' => $rb], $now));
fails('association sans recette refusée', function () use ($now, $catalog, $bases) {
    $state = gp_initial(); $state['credits'] = 99999;
    foreach (array_keys($bases) as $x) foreach (array_keys($bases) as $y) {
        if ($x >= $y) continue;
        $pair = [$x, $y]; sort($pair, SORT_STRING);
        if (isset($catalog[implode('--', $pair)])) continue;
        $state['seeds'][$x] = 5; $state['seeds'][$y] = 5;
        $state['harvests'][$x] = 99; $state['harvests'][$y] = 99;
        gp_apply($state, 'cross', ['a' => $x, 'b' => $y], $now);
        return;
    }
    throw new RuntimeException('toutes les associations ont une recette');
});

echo "\nBoutique et serre\n";
fails('achat sans points', function () use ($now) { $x = gp_initial(); $x['credits'] = 0; gp_apply($x, 'buy', ['item' => 'soil'], $now); });
$b = gp_initial();
ok('seules les fondatrices communes sont offertes', !array_filter($b['seeds'], fn($id) => gp_tier($catalog[$id]) !== 0, ARRAY_FILTER_USE_KEY));
ok('sauvegarde neuve en version 3', $b['version'] === 3);
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
ok('montée en version 3', $up['version'] === 3);
$newCommon = array_filter($catalog, fn($v, $id) => !$v['parents'] && gp_tier($v) === 0
    && !in_array($id, ['emeraude','nebuleuse','citron','velours','menthe','ambre','rose','givre','mangue','onyx','diesel','peche','pin','orchidee'], true), ARRAY_FILTER_USE_BOTH);
ok('une graine offerte par nouvelle fondatrice commune',
    !array_filter($newCommon, fn($v, $id) => ($up['seeds'][$id] ?? 0) !== 1, ARRAY_FILTER_USE_BOTH));
ok('aucune graine offerte pour les fondatrices rares',
    !array_filter($catalog, fn($v, $id) => !$v['parents'] && gp_tier($v) > 0 && isset($up['seeds'][$id]), ARRAY_FILTER_USE_BOTH));

$partial = ['version' => 2, 'credits' => 900, 'seeds' => ['emeraude' => 1], 'pots' => [['soil' => false, 'plant' => null]]];
$fixed = gp_upgrade($partial);
ok('sauvegarde incomplète complétée sans perte', $fixed['credits'] === 900 && $fixed['seeds']['emeraude'] === 1
    && $fixed['buds'] === [] && $fixed['harvests'] === [] && count($fixed['pots']) === 1);

echo "\nClassement\n";
$m = gp_metrics($up);
$discovered = $catalog['citron--emeraude'];
$expected = 2 * 100 + [250, 400, 650, 1100, 2000][gp_tier($discovered)] + min(100, 16);
ok('score = variétés×100 + hybrides pondérés par rareté + récoltes plafonnées', $m['score'] === $expected);
$legendary = null;
foreach ($catalog as $id => $v) if ($v['parents'] && gp_tier($v) === 4) { $legendary = $id; break; }
if ($legendary) {
    $rich = $up; $rich['discoveries'] = [$legendary => true];
    $cheap = $up; $cheap['discoveries'] = ['citron--emeraude' => true];
    ok('une mythique rapporte plus qu’une commune', gp_metrics($rich)['score'] > gp_metrics($cheap)['score']);
}
$spent = $up; $spent['credits'] = 0; $spent['buds'] = [];
ok('dépenser ne réduit jamais le score', gp_metrics($spent)['score'] === $m['score']);
$board = gp_rank_rows([
    ['username' => 'zoe', 'board_value' => 500], ['username' => 'ana', 'board_value' => 500], ['username' => 'bob', 'board_value' => 900],
], 'ana');
ok('égalité = même rang', $board['rows'][1]['rank'] === 2 && $board['rows'][2]['rank'] === 2);
ok('joueur identifié', $board['me']['username'] === 'ana');

echo "\n$passed réussis, $failed échoués\n";
exit($failed ? 1 : 0);
