<?php
declare(strict_types=1);
require_once __DIR__.'/plantation_lib.php';
/** A collection score. Spending points or buds never reduces it. */
function gp_metrics(array $s): array {
    // Une recette rare vaut plus cher qu'une commune : la difficulté se voit au classement.
    $weight=[250,400,650,1100,2000];
    $varieties=0;$hybrids=0;$harvests=0;$collection=0;
    foreach(gp_catalog() as $id=>$v){
        $n=max(0,min(100000000,(int)($s['harvests'][$id]??0)));
        if($n>0)$varieties++;
        $harvests+=$n;
        if($v['parents']&&!empty($s['discoveries'][$id])){$hybrids++;$collection+=$weight[gp_tier($v)];}
    }
    return ['score'=>$varieties*100+$collection+min(100,$harvests),
        'varieties'=>$varieties,'hybrids'=>$hybrids,'harvests'=>$harvests];
}
function gp_rank_rows(array $rows,string $viewer,int $limit=20): array {
    usort($rows,fn($a,$b)=>($b['board_value']<=>$a['board_value'])?:strcmp($a['username'],$b['username']));
    $rank=0;$previous=null;$me=null;
    foreach($rows as $i=>&$r){if($previous!==$r['board_value'])$rank=$i+1;$previous=$r['board_value'];$r['rank']=$rank;$r['is_me']=strcasecmp($r['username'],$viewer)===0;if($r['is_me'])$me=$r;}unset($r);
    $top=array_slice($rows,0,max(1,min(100,$limit)));
    if($me&&!array_filter($top,fn($r)=>$r['is_me']))$top[]=$me;
    return ['rows'=>$top,'total'=>count($rows),'me'=>$me];
}
function gp_leaderboard(PDO $pdo,string $viewer,int $limit=20): array {
    // No inventory or save data leaves this function. Existing V1 saves count immediately.
    $exists=$pdo->query("SHOW TABLES LIKE 'greenstand_plantation'")->fetchColumn();
    if(!$exists)return ['rows'=>[],'total'=>0,'me'=>null];
    $stmt=$pdo->query('SELECT p.username,p.state_json FROM greenstand_plantation p INNER JOIN users u ON u.username=p.username');
    $rows=[];
    while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
        $s=json_decode($row['state_json'],true);if(!is_array($s))continue;$m=gp_metrics($s);if(!$m['score'])continue;
        $rows[]=['username'=>$row['username'],'display_name'=>$row['username'],'board_value'=>$m['score'],'extra_varieties'=>$m['varieties'],'extra_hybrids'=>$m['hybrids'],'extra_harvests'=>$m['harvests']];
    }
    return gp_rank_rows($rows,$viewer,$limit);
}
