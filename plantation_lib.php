<?php
declare(strict_types=1);
/** Fictional arcade rules. All rewards, inventory and time are server-owned. */
function gp_catalog(): array {
    static $data = null;
    if ($data === null) {
        $rows = json_decode(file_get_contents(__DIR__.'/assets/plantation/catalog.json'), true, 512, JSON_THROW_ON_ERROR);
        $data = array_column($rows, null, 'id');
    }
    return $data;
}
/** Les 14 fondatrices d'origine : repère pour les cadeaux de mise à jour. */
const GP_LEGACY_FOUNDERS = ['emeraude','nebuleuse','citron','velours','menthe','ambre','rose',
    'givre','mangue','onyx','diesel','peche','pin','orchidee'];
/** Plus une recette est rare, plus elle coûte cher et demande de maîtrise. */
function gp_tier_rules(int $tier): array {
    $cost    = [60, 110, 180, 280, 400];
    $mastery = [1, 2, 3, 5, 8];
    $tier = max(0, min(4, $tier));
    return ['cost'=>$cost[$tier], 'mastery'=>$mastery[$tier]];
}
function gp_tier(array $v): int { return max(0, min(4, (int)($v['tier'] ?? 0))); }
function gp_initial(): array {
    // Les fondatrices communes sont offertes ; les plus rares s'achètent.
    $seeds = [];
    foreach (gp_catalog() as $id=>$v) if (!$v['parents'] && gp_tier($v) === 0) $seeds[$id] = 2;
    return ['version'=>3,'accessories'=>[],'revision'=>0,'credits'=>250,'soil'=>12,'water'=>6,'feed'=>6,
        'seeds'=>$seeds,'pots'=>array_fill(0,4,['soil'=>false,'plant'=>null]),
        'buds'=>[],'harvests'=>[],'discoveries'=>[],'total'=>0,'history'=>[],'seen'=>[]];
}
function gp_check(bool $condition, string $message): void {
    if (!$condition) throw new DomainException($message);
}
function gp_event(array &$s, string $text, int $now): void {
    array_unshift($s['history'], ['at'=>$now,'text'=>$text]);
    $s['history'] = array_slice($s['history'],0,20);
}
/** Pure transition, also exercised by the offline test suite. */
function gp_apply(array $s, string $action, array $p, int $now): array {
    $s=gp_upgrade($s);
    $catalog=gp_catalog();
    if (in_array($action,['soil','plant','water','feed','harvest'],true)) {
        $raw=$p['pot']??null;
        gp_check(is_int($raw) || (is_string($raw)&&ctype_digit($raw)), 'Pot invalide.');
        $index=(int)$raw;
        gp_check(isset($s['pots'][$index]),'Ce pot n’est pas disponible.');
        $pot=&$s['pots'][$index];
    }
    switch ($action) {
        case 'soil':
            gp_check(!$pot['plant']&&!$pot['soil'],'Ce pot est déjà préparé.');
            gp_check($s['soil']>0,'Il te faut un sac de terreau.');
            $s['soil']--; $pot['soil']=true; break;
        case 'plant':
            $id=(string)($p['seed']??'');
            gp_check(isset($catalog[$id]),'Graine inconnue.');
            gp_check($pot['soil']&&!$pot['plant'],'Prépare d’abord un pot vide avec du terreau.');
            gp_check(($s['seeds'][$id]??0)>0,'Tu n’as plus cette graine.');
            $s['seeds'][$id]--;
            $pot['plant']=['id'=>$id,'planted'=>$now,'started'=>null,'ready'=>null,'fed'=>false];
            gp_event($s,$catalog[$id]['name'].' semée dans le pot '.($index+1).'.',$now); break;
        case 'water':
            gp_check((bool)$pot['plant'],'Sème une graine avant d’arroser.');
            gp_check($pot['plant']['started']===null,'Cette plante a déjà reçu son eau.');
            gp_check($s['water']>0,'Remplis ton arrosoir à la réserve.');
            $s['water']--; $pot['plant']['started']=$now;
            $pot['plant']['ready']=$now+$catalog[$pot['plant']['id']]['duration']; break;
        case 'feed':
            gp_check((bool)$pot['plant']&&$pot['plant']['started']!==null,'Arrose d’abord cette plante.');
            gp_check(!$pot['plant']['fed']&&$pot['plant']['ready']>$now,'Le bonus se donne une seule fois, pendant la pousse.');
            gp_check($s['feed']>0,'Il te faut un jeton de soin.');
            $s['feed']--; $pot['plant']['fed']=true; break;
        case 'harvest':
            gp_check((bool)$pot['plant']&&$pot['plant']['ready']!==null&&$now>=$pot['plant']['ready'],'La plante n’est pas encore prête.');
            $plant=$pot['plant'];$v=$catalog[$plant['id']];$id=$v['id'];
            $yield=$plant['fed']?3:2;
            $s['buds'][$id]=($s['buds'][$id]??0)+$yield;
            $s['harvests'][$id]=($s['harvests'][$id]??0)+1;
            $s['seeds'][$id]=min(9999,($s['seeds'][$id]??0)+2);
            $gain=$v['reward']+($plant['fed']?15:0);
            $s['credits']=min(9999999,$s['credits']+$gain); $s['total']++;
            $pot=['soil'=>false,'plant'=>null];
            gp_event($s,$v['name'].' : '.$yield.' buds, 2 graines et +'.$gain.' points de serre.',$now); break;
        case 'refill': $s['water']=6; break;
        case 'buy':
            $item=(string)($p['item']??'');
            if ($item==='soil') {gp_check($s['credits']>=15,'15 points nécessaires.');gp_check($s['soil']<=995,'Réserve pleine.');$s['credits']-=15;$s['soil']+=5;}
            elseif ($item==='feed') {gp_check($s['credits']>=20,'20 points nécessaires.');gp_check($s['feed']<=995,'Réserve pleine.');$s['credits']-=20;$s['feed']+=5;}
            else {gp_check(isset($catalog[$item])&&!$catalog[$item]['parents'],'Cette graine s’obtient au laboratoire.');$cost=$catalog[$item]['price'];gp_check($s['credits']>=$cost,'Pas assez de points de serre.');gp_check(($s['seeds'][$item]??0)<9999,'Réserve pleine.');$s['credits']-=$cost;$s['seeds'][$item]=($s['seeds'][$item]??0)+1;}
            break;
        case 'accessory':
            $id=(string)($p['item']??'');$prices=['fan'=>120,'humidifier'=>140,'loupe'=>100];
            gp_check(isset($prices[$id]),'Accessoire inconnu.');
            gp_check(empty($s['accessories'][$id]),'Cet accessoire est déjà installé.');
            gp_check($s['credits']>=$prices[$id],'Pas assez de points de serre.');
            $s['credits']-=$prices[$id];$s['accessories'][$id]=true;
            gp_event($s,'Nouvel accessoire installé dans la serre.',$now);break;
        case 'pot':
            $count=count($s['pots']);$cost=150*($count-3);
            gp_check($count<6,'Les six emplacements sont déjà ouverts.');
            gp_check($s['credits']>=$cost,$cost.' points nécessaires.');
            $s['credits']-=$cost;$s['pots'][]=['soil'=>false,'plant'=>null];break;
        case 'cross':
            $a=(string)($p['a']??'');$b=(string)($p['b']??'');
            gp_check($a!==$b&&isset($catalog[$a],$catalog[$b])&&!$catalog[$a]['parents']&&!$catalog[$b]['parents'],'Choisis deux graines fondatrices différentes.');
            $parents=[$a,$b];sort($parents,SORT_STRING);$id=implode('--',$parents);
            gp_check(isset($catalog[$id]),'Ces deux fondatrices ne donnent aucune recette connue.');
            $rules=gp_tier_rules(gp_tier($catalog[$id]));
            gp_check(($s['harvests'][$a]??0)>=$rules['mastery']&&($s['harvests'][$b]??0)>=$rules['mastery'],
                'Recette '.$catalog[$id]['rarity'].' : récolte '.$rules['mastery'].' fois chaque parent avant de tenter ce croisement.');
            gp_check(($s['seeds'][$a]??0)>0&&($s['seeds'][$b]??0)>0,'Il faut une graine de chaque parent.');
            gp_check($s['credits']>=$rules['cost'],'Cette recette coûte '.$rules['cost'].' points de serre.');
            gp_check(($s['seeds'][$id]??0)<9999,'Réserve pleine.');
            $s['credits']-=$rules['cost'];$s['seeds'][$a]--;$s['seeds'][$b]--;
            $s['seeds'][$id]=($s['seeds'][$id]??0)+1;$s['discoveries'][$id]=true;
            gp_event($s,'Croisement réussi : '.$catalog[$id]['name'].' ('.$catalog[$id]['rarity'].').',$now);break;
        default: throw new DomainException('Action inconnue.');
    }
    unset($pot); // la référence ne doit pas survivre dans le tableau retourné
    $s['revision']++;
    return $s;
}
function gp_public(array $s, int $now): array {unset($s['seen']);return ['state'=>$s,'server_time'=>$now];}
function gp_schema(PDO $pdo): void {
    // DDL must run before transactions (MySQL implicitly commits DDL).
    $pdo->exec('CREATE TABLE IF NOT EXISTS greenstand_plantation (username VARCHAR(24) NOT NULL PRIMARY KEY,state_json LONGTEXT NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

/** One-time upgrade; old seeds, crops, discoveries and counters are preserved. */
function gp_upgrade(array $s): array {
    if(($s['version']??1)<2){foreach(['diesel','peche','pin','orchidee'] as $id)$s['seeds'][$id]=($s['seeds'][$id]??0)+2;$s['version']=2;}
    if(($s['version']??1)<3){
        // La serre passe à 50 fondatrices : une graine offerte pour chaque nouvelle
        // fondatrice commune. Rien n'est retiré, rien n'est remis à zéro.
        foreach(gp_catalog() as $id=>$v)
            if(!$v['parents'] && gp_tier($v)===0 && !in_array($id, GP_LEGACY_FOUNDERS, true))
                $s['seeds'][$id]=($s['seeds'][$id]??0)+1;
        $s['version']=3;
    }
    // Complète uniquement ce qui manque : aucune progression existante n'est réinitialisée.
    $defaults = gp_initial();
    foreach(['revision','credits','soil','water','feed','total'] as $key)
        if(!isset($s[$key])||!is_int($s[$key])) $s[$key] = (int)($s[$key] ?? $defaults[$key]);
    foreach(['seeds','buds','harvests','discoveries','accessories','history','seen'] as $key)
        if(!isset($s[$key])||!is_array($s[$key])) $s[$key] = [];
    if(empty($s['pots'])||!is_array($s['pots'])) $s['pots'] = $defaults['pots'];
    return $s;
}
