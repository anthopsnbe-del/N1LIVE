<?php
declare(strict_types=1);
// Additive tables only. Existing players, tokens and clan memberships are reused.
function social_install(PDO $db): void {
    $tables = [
      'social_lock (id INTEGER PRIMARY KEY)',
      "social_accounts (user_id INTEGER PRIMARY KEY, world VARCHAR(8) NOT NULL DEFAULT 'EU1', portrait INTEGER NOT NULL DEFAULT 0, last_seen BIGINT NOT NULL DEFAULT 0, active_match VARCHAR(32) NULL)",
      'social_ratings (user_id INTEGER NOT NULL, world VARCHAR(8) NOT NULL, rating INTEGER NOT NULL DEFAULT 1000, wins INTEGER NOT NULL DEFAULT 0, losses INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(user_id,world))',
      'social_friends (a INTEGER NOT NULL,b INTEGER NOT NULL,requester INTEGER NOT NULL,accepted INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(a,b))',
      'social_blocks (user_id INTEGER NOT NULL,target_id INTEGER NOT NULL, PRIMARY KEY(user_id,target_id))',
      'social_chat (id VARCHAR(32) PRIMARY KEY,user_id INTEGER NOT NULL,world VARCHAR(8) NOT NULL,clan_id INTEGER NOT NULL DEFAULT 0,body VARCHAR(500) NOT NULL,created_at BIGINT NOT NULL)',
      'social_reports (user_id INTEGER NOT NULL,message_id VARCHAR(32) NOT NULL,created_at BIGINT NOT NULL, PRIMARY KEY(user_id,message_id))',
      'social_queue (user_id INTEGER PRIMARY KEY,world VARCHAR(8) NOT NULL,created_at BIGINT NOT NULL)',
      'social_invites (id VARCHAR(32) PRIMARY KEY,sender INTEGER NOT NULL,recipient INTEGER NOT NULL,world VARCHAR(8) NOT NULL,created_at BIGINT NOT NULL)',
      'social_matches (id VARCHAR(32) PRIMARY KEY,world VARCHAR(8) NOT NULL,player_a INTEGER NOT NULL,player_b INTEGER NOT NULL,ranked INTEGER NOT NULL,state TEXT NOT NULL,created_at BIGINT NOT NULL)'
    ];
    foreach ($tables as $sql) $db->exec('CREATE TABLE IF NOT EXISTS '.$sql.($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':''));
    if (!$db->query('SELECT id FROM social_lock WHERE id=1')->fetch()) {
        try {$db->exec('INSERT INTO social_lock (id) VALUES (1)');} catch(PDOException $e) {}
    }
}
/* Diagnostic sans compte : dit quelle version du service tourne et quelles
   tables existent. Sert à vérifier un transfert FTP en un appel. */
function social_ping(PDO $db): array {
    $tables=['social_accounts','social_boss','social_wars','social_wallet','social_items','social_market','social_seasons','social_clan_log'];
    $found=[];
    foreach($tables as $name){
        try{$db->query('SELECT 1 FROM '.$name.' LIMIT 1');$found[$name]=true;}catch(Throwable $e){$found[$name]=false;}
    }
    return ['service'=>'AOT IDLE','version'=>'6.7','modules'=>[
        'boss'=>function_exists('boss_handle'),'war'=>function_exists('war_handle'),
        'wallet'=>function_exists('wallet_handle'),'season'=>function_exists('season_handle'),
        'market'=>function_exists('market_handle'),
        'clan'=>function_exists('clan_handle')],'tables'=>$found];
}

function sq(PDO $db,string $sql,array $args=[]): PDOStatement {$q=$db->prepare($sql);$q->execute($args);return $q;}
function social_error(string $s): void {throw new DomainException($s);}
function social_account(PDO $db,int $id): array {
    $row=sq($db,'SELECT * FROM social_accounts WHERE user_id=?',[$id])->fetch(PDO::FETCH_ASSOC);
    if (!$row) {sq($db,'INSERT INTO social_accounts(user_id) VALUES(?)',[$id]);return social_account($db,$id);}return $row;
}
function social_rating(PDO $db,int $id,string $world): array {
    $r=sq($db,'SELECT * FROM social_ratings WHERE user_id=? AND world=?',[$id,$world])->fetch(PDO::FETCH_ASSOC);
    if (!$r){sq($db,'INSERT INTO social_ratings(user_id,world) VALUES(?,?)',[$id,$world]);return social_rating($db,$id,$world);}return $r;
}
function social_profile(PDO $db,int $id,string $world): array {
    $p=sq($db,'SELECT id,pseudo,chapter,level,clan_id FROM players WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC);
    if (!$p) social_error('Joueur introuvable.');$a=social_account($db,$id);$r=sq($db,'SELECT * FROM social_ratings WHERE user_id=? AND world=?',[$id,$world])->fetch(PDO::FETCH_ASSOC) ?: ['rating'=>1000,'wins'=>0,'losses'=>0];
    return ['id'=>$id,'pseudo'=>$p['pseudo'],'chapter'=>(int)$p['chapter'],'level'=>(int)$p['level'],'clanId'=>(int)$p['clan_id'],'world'=>$a['world'],'portrait'=>(int)$a['portrait'],'rating'=>(int)$r['rating'],'wins'=>(int)$r['wins'],'losses'=>(int)$r['losses'],'online'=>((int)$a['last_seen']>social_now()-20000)];
}
function social_now(): int {return (int)floor(microtime(true)*1000);}
function social_blocked(PDO $db,int $a,int $b): bool {return (bool)sq($db,'SELECT user_id FROM social_blocks WHERE (user_id=? AND target_id=?) OR (user_id=? AND target_id=?)',[$a,$b,$b,$a])->fetch();}
function social_create_match(PDO $db,int $a,int $b,string $world,bool $ranked,int $now): array {
    foreach([$a,$b] as $id){$account=social_account($db,$id);if($account['active_match'])social_error('Un joueur a déjà un combat à fermer.');}
    $id=bin2hex(random_bytes(16));$state=['start'=>$now+3000,'tick'=>$now+3000,'hp'=>[180,180],'seen'=>[$now,$now],'cooldowns'=>[[],[]],'guard'=>[0,0],'events'=>[],'finished'=>false,'winner'=>null,'reason'=>'','ratingDelta'=>[0,0]];
    sq($db,'INSERT INTO social_matches(id,world,player_a,player_b,ranked,state,created_at) VALUES(?,?,?,?,?,?,?)',[$id,$world,$a,$b,$ranked?1:0,json_encode($state),$now]);
    sq($db,'UPDATE social_accounts SET active_match=? WHERE user_id=? OR user_id=?',[$id,$a,$b]);sq($db,'DELETE FROM social_queue WHERE user_id=? OR user_id=?',[$a,$b]);
    return ['id'=>$id,'world'=>$world,'player_a'=>$a,'player_b'=>$b,'ranked'=>$ranked?1:0,'state'=>json_encode($state)];
}
function social_finish(PDO $db,array $m,array &$s,?int $winner,string $reason): void {
    if($s['finished'])return;$s['finished']=true;$s['winner']=$winner;$s['reason']=$reason;
    if((int)$m['ranked']===1 && $winner!==null){
      $ids=[(int)$m['player_a'],(int)$m['player_b']];$ra=social_rating($db,$ids[0],$m['world']);$rb=social_rating($db,$ids[1],$m['world']);
      $expect=1/(1+pow(10,((int)$rb['rating']-(int)$ra['rating'])/400));$delta=(int)round(24*(($winner===0?1:0)-$expect));
      foreach([0,1] as $i){$d=$i===0?$delta:-$delta;$s['ratingDelta'][$i]=$d;sq($db,'UPDATE social_ratings SET rating=rating+?,wins=wins+?,losses=losses+? WHERE user_id=? AND world=?',[$d,$winner===$i?1:0,$winner===$i?0:1,$ids[$i],$m['world']]);}
    }
}
function social_match(PDO $db,array $m,int $user,int $now,?string $action=null): array {
    $i=(int)$m['player_a']===$user?0:1;$ids=[(int)$m['player_a'],(int)$m['player_b']];
    if(!in_array($user,$ids,true))social_error('Ce combat ne vous appartient pas.');
    $s=json_decode($m['state'],true);$other=1-$i;
    if(!$s['finished']){
      // A client cannot return after a long absence to retroactively apply attacks.
      $absent=[($now-$s['seen'][0])>15000,($now-$s['seen'][1])>15000];
      if($absent[0]&&$absent[1])social_finish($db,$m,$s,null,'Les deux joueurs sont déconnectés.');
      elseif($absent[0]||$absent[1])social_finish($db,$m,$s,$absent[0]?1:0,'Déconnexion de l’adversaire.');
      $s['seen'][$i]=$now;
      while(!$s['finished'] && $s['tick']+1000<=$now){
        $s['tick']+=1000;
        foreach([0,1] as $t){$damage=$s['guard'][$t]>=$s['tick']?2:5;$s['hp'][$t]=max(0,$s['hp'][$t]-$damage);}
        if($s['hp'][0]===0||$s['hp'][1]===0)social_finish($db,$m,$s,$s['hp'][0]===$s['hp'][1]?null:($s['hp'][0]>0?0:1),'Combat terminé.');
      }
      if($action && !$s['finished']){
        if($now<$s['start'])social_error('Le combat commence dans un instant.');
        $cd=['strike'=>2000,'burst'=>8000,'guard'=>6500,'heal'=>12000];
        if(!isset($cd[$action]))social_error('Action inconnue.');
        if(($s['cooldowns'][$i][$action]??0)>$now)social_error('Compétence en récupération.');
        $s['cooldowns'][$i][$action]=$now+$cd[$action];
        if($action==='guard')$s['guard'][$i]=$now+3000;
        elseif($action==='heal')$s['hp'][$i]=min(180,$s['hp'][$i]+16);
        else{$damage=$action==='burst'?24:9;if($s['guard'][$other]>$now)$damage=(int)ceil($damage*.35);$s['hp'][$other]=max(0,$s['hp'][$other]-$damage);}
        $s['events'][]=['player'=>$i,'action'=>$action,'time'=>$now];$s['events']=array_slice($s['events'],-12);
        if($s['hp'][$other]===0)social_finish($db,$m,$s,$i,'Victoire par KO.');
      }
    }
    sq($db,'UPDATE social_matches SET state=? WHERE id=?',[json_encode($s),$m['id']]);
    return ['id'=>$m['id'],'world'=>$m['world'],'ranked'=>(bool)$m['ranked'],'self'=>$i,'players'=>[social_profile($db,$ids[0],$m['world']),social_profile($db,$ids[1],$m['world'])],'state'=>$s,'serverNow'=>$now];
}
function social_handle(PDO $db,array $in): array {
    $token=$in['token']??'';if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token))social_error('Connectez-vous pour jouer en ligne.');
    $p=sq($db,'SELECT id,pseudo,clan_id,power,level FROM players WHERE token=?',[$token])->fetch(PDO::FETCH_ASSOC);if(!$p)social_error('Session expirée. Reconnectez-vous.');
    $id=(int)$p['id'];$now=social_now();$a=social_account($db,$id);$world=$a['world'];$clan=(int)$p['clan_id'];$action=$in['action']??'';
    sq($db,'UPDATE social_accounts SET last_seen=? WHERE user_id=?',[$now,$id]);social_rating($db,$id,$world);
    sq($db,'DELETE FROM social_queue WHERE created_at<?',[$now-20000]);sq($db,'DELETE FROM social_invites WHERE created_at<?',[$now-60000]);
    $match=$a['active_match']?sq($db,'SELECT * FROM social_matches WHERE id=?',[$a['active_match']])->fetch(PDO::FETCH_ASSOC):null;
    if($action==='profile')return ['profile'=>social_profile($db,max(1,(int)($in['userId']??$id)),$world)];
    if($action==='rename'){
      $name=trim((string)($in['pseudo']??''));if(!preg_match('/^[\p{L}\p{N}_.-]{3,18}$/uD',$name))social_error('Pseudo : 3 à 18 lettres, chiffres, points, tirets ou soulignés.');
      if(sq($db,'SELECT id FROM players WHERE pseudo=? AND id<>?',[$name,$id])->fetch())social_error('Ce pseudo est déjà utilisé.');
      sq($db,'UPDATE players SET pseudo=? WHERE id=?',[$name,$id]);return ['profile'=>social_profile($db,$id,$world)];
    }
    if($action==='portrait'){ $portrait=(int)($in['portrait']??-1);if($portrait<0||$portrait>23)social_error('Portrait inconnu.');sq($db,'UPDATE social_accounts SET portrait=? WHERE user_id=?',[$portrait,$id]);return [];}
    if($action==='world'){
      $target=$in['world']??'';if(!in_array($target,['EU1','EU2','ASIE1','ASIE2'],true))social_error('Monde inconnu.');
      if($clan)social_error('Quittez votre clan avant de changer de monde.');if($match&&!json_decode($match['state'],true)['finished'])social_error('Terminez le duel avant de changer de monde.');
      sq($db,'DELETE FROM social_queue WHERE user_id=?',[$id]);sq($db,'DELETE FROM social_invites WHERE sender=? OR recipient=?',[$id,$id]);sq($db,'UPDATE social_accounts SET world=?,active_match=NULL WHERE user_id=?',[$target,$id]);return ['profile'=>social_profile($db,$id,$target)];
    }
    if($action==='search'){
      $q=trim((string)($in['query']??''));if(strlen($q)<2)return ['players'=>[]];$q=str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q);
      $rows=sq($db,'SELECT id FROM players WHERE pseudo LIKE ? AND id<>? ORDER BY pseudo LIMIT 20',['%'.$q.'%',$id])->fetchAll(PDO::FETCH_COLUMN);return ['players'=>array_map(fn($n)=>social_profile($db,(int)$n,$world),$rows)];
    }
    if(in_array($action,['friend_add','friend_accept','friend_remove','block','unblock','duel_invite'],true)){
      $target=(int)($in['userId']??0);if($target===$id||$target<1)social_error('Joueur invalide.');social_profile($db,$target,$world);$low=min($id,$target);$high=max($id,$target);
      $f=sq($db,'SELECT * FROM social_friends WHERE a=? AND b=?',[$low,$high])->fetch(PDO::FETCH_ASSOC);
      if($action==='block'){if(!sq($db,'SELECT user_id FROM social_blocks WHERE user_id=? AND target_id=?',[$id,$target])->fetch())sq($db,'INSERT INTO social_blocks VALUES(?,?)',[$id,$target]);sq($db,'DELETE FROM social_friends WHERE a=? AND b=?',[$low,$high]);return [];}
      if($action==='unblock'){sq($db,'DELETE FROM social_blocks WHERE user_id=? AND target_id=?',[$id,$target]);return [];}
      if($action==='friend_remove'){sq($db,'DELETE FROM social_friends WHERE a=? AND b=?',[$low,$high]);return [];}
      if(social_blocked($db,$id,$target))social_error('Interaction indisponible.');
      if($action==='friend_accept'){if(!$f||(int)$f['requester']===$id)social_error('Invitation entrante introuvable.');sq($db,'UPDATE social_friends SET accepted=1 WHERE a=? AND b=?',[$low,$high]);return [];}
      if($action==='friend_add'){if(!$f)sq($db,'INSERT INTO social_friends(a,b,requester) VALUES(?,?,?)',[$low,$high,$id]);return [];}
      if(!$f||!(int)$f['accepted'])social_error('Ajoutez ce joueur en ami avant de le défier.');$ta=social_account($db,$target);
      if($ta['world']!==$world)social_error('Rejoignez le même monde pour vous affronter.');if((int)$ta['last_seen']<$now-20000)social_error('Cet ami est hors ligne.');if($a['active_match']||$ta['active_match'])social_error('Un joueur doit fermer son combat précédent.');
      if(sq($db,'SELECT id FROM social_invites WHERE sender=?',[$id])->fetch())social_error('Une invitation est déjà en attente.');
      sq($db,'INSERT INTO social_invites VALUES(?,?,?,?,?)',[bin2hex(random_bytes(16)),$id,$target,$world,$now]);return [];
    }
    if($action==='chat_send'){
      $channel=$in['channel']??'global';$group=$channel==='clan'?$clan:0;if($channel==='clan'&&!$clan)social_error('Rejoignez un clan pour accéder à son chat.');
      $text=trim((string)($in['message']??''));if(!preg_match('/^[\s\S]{1,500}$/uD',$text))social_error('Message : 1 à 500 caractères.');
      if(sq($db,'SELECT id FROM social_chat WHERE user_id=? AND created_at>?',[$id,$now-3000])->fetch())social_error('Attendez 3 secondes entre deux messages.');
      sq($db,'INSERT INTO social_chat VALUES(?,?,?,?,?,?)',[bin2hex(random_bytes(16)),$id,$world,$group,$text,$now]);return [];
    }
    if($action==='chat_read'){
      $group=($in['channel']??'global')==='clan'?$clan:0;if(($in['channel']??'')==='clan'&&!$clan)social_error('Rejoignez un clan pour accéder à son chat.');
      $rows=sq($db,'SELECT c.id,c.user_id,c.body,c.created_at,p.pseudo FROM social_chat c JOIN players p ON p.id=c.user_id WHERE c.world=? AND c.clan_id=? AND NOT EXISTS(SELECT 1 FROM social_blocks b WHERE b.user_id=? AND b.target_id=c.user_id) ORDER BY c.created_at DESC,c.id DESC LIMIT 50',[$world,$group,$id])->fetchAll(PDO::FETCH_ASSOC);return ['messages'=>array_reverse($rows)];
    }
    if($action==='report'){
      $message=(string)($in['messageId']??'');$row=sq($db,'SELECT id FROM social_chat WHERE id=? AND world=? AND (clan_id=0 OR clan_id=?)',[$message,$world,$clan])->fetch();if(!$row)social_error('Message introuvable.');
      if(!sq($db,'SELECT user_id FROM social_reports WHERE user_id=? AND message_id=?',[$id,$message])->fetch())sq($db,'INSERT INTO social_reports VALUES(?,?,?)',[$id,$message,$now]);return [];
    }
    if($action==='rankings'){
      $rows=sq($db,'SELECT user_id FROM social_ratings WHERE world=? ORDER BY rating DESC,wins DESC,user_id LIMIT 50',[$world])->fetchAll(PDO::FETCH_COLUMN);return ['players'=>array_map(fn($n)=>social_profile($db,(int)$n,$world),$rows)];
    }
    if($action==='duel_answer'){
      $inv=sq($db,'SELECT * FROM social_invites WHERE id=? AND recipient=?',[(string)($in['inviteId']??''),$id])->fetch(PDO::FETCH_ASSOC);if(!$inv)social_error('Invitation expirée.');
      sq($db,'DELETE FROM social_invites WHERE id=?',[$inv['id']]);
      if(empty($in['accept']))return [];$sender=social_account($db,(int)$inv['sender']);if($inv['world']!==$world||$sender['world']!==$world||social_blocked($db,$id,(int)$inv['sender']))social_error('Invitation indisponible.');
      return ['match'=>social_match($db,social_create_match($db,(int)$inv['sender'],$id,$world,false,$now),$id,$now)];
    }
    if($action==='queue_cancel'){sq($db,'DELETE FROM social_queue WHERE user_id=?',[$id]);return [];}
    if($action==='queue'){
      if($match)return ['match'=>social_match($db,$match,$id,$now)];
      $rows=sq($db,'SELECT q.user_id FROM social_queue q JOIN social_accounts a ON a.user_id=q.user_id WHERE q.world=? AND q.user_id<>? AND a.active_match IS NULL AND a.last_seen>? ORDER BY q.created_at',[$world,$id,$now-5000])->fetchAll(PDO::FETCH_COLUMN);
      foreach($rows as $candidate)if(!social_blocked($db,$id,(int)$candidate))return ['match'=>social_match($db,social_create_match($db,(int)$candidate,$id,$world,true,$now),$id,$now)];
      if(sq($db,'SELECT user_id FROM social_queue WHERE user_id=?',[$id])->fetch())sq($db,'UPDATE social_queue SET created_at=? WHERE user_id=?',[$now,$id]);else sq($db,'INSERT INTO social_queue VALUES(?,?,?)',[$id,$world,$now]);return ['queued'=>true];
    }
    if($action==='match_action'){if(!$match)social_error('Aucun duel en cours.');return ['match'=>social_match($db,$match,$id,$now,(string)($in['skill']??''))];}
    if($action==='match_leave'){
      if($match){$s=json_decode($match['state'],true);$self=(int)$match['player_a']===$id?0:1;social_finish($db,$match,$s,1-$self,'Abandon.');sq($db,'UPDATE social_matches SET state=? WHERE id=?',[json_encode($s),$match['id']]);}
      sq($db,'UPDATE social_accounts SET active_match=NULL WHERE user_id=?',[$id]);return [];
    }
    if($action==='state'){
      $friends=sq($db,'SELECT * FROM social_friends WHERE a=? OR b=?',[$id,$id])->fetchAll(PDO::FETCH_ASSOC);$list=[];
      foreach($friends as $f){$other=(int)$f['a']===$id?(int)$f['b']:(int)$f['a'];$list[]=['profile'=>social_profile($db,$other,$world),'accepted'=>(bool)$f['accepted'],'incoming'=>(int)$f['requester']!==$id];}
      $invites=sq($db,'SELECT id,sender FROM social_invites WHERE recipient=? AND world=?',[$id,$world])->fetchAll(PDO::FETCH_ASSOC);foreach($invites as &$inv)$inv['profile']=social_profile($db,(int)$inv['sender'],$world);unset($inv);
      return ['profile'=>social_profile($db,$id,$world),'friends'=>$list,'invites'=>$invites,'match'=>$match?social_match($db,$match,$id,$now):null,'serverNow'=>$now];
    }
    // Boss mondial et guerres de clans : actions ajoutées par boss-core.php et war-core.php.
    if(function_exists('boss_handle')){$boss=boss_handle($db,$in,$p,$world,$clan,$now);if($boss!==null)return $boss;}
    if(function_exists('war_handle')){$war=war_handle($db,$in,$p,$world,$clan,$now);if($war!==null)return $war;}
    if(function_exists('season_handle')){$season=season_handle($db,$in,$p,$world,$now);if($season!==null)return $season;}
    if(function_exists('market_handle')){$market=market_handle($db,$in,$p,$world,$now);if($market!==null)return $market;}
    if(function_exists('clan_handle')){$journal=clan_handle($db,$in,$p,$clan,$now);if($journal!==null)return $journal;}
    if(function_exists('wallet_handle')){$wallet=wallet_handle($db,$in,$p,$now);if($wallet!==null)return $wallet;}
    social_error('Action inconnue.');return [];
}
