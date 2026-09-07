<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require_once __DIR__.'/plantation_lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function gp_json(array $data,int $status=200): never {http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')gp_json(['ok'=>false,'error'=>'Méthode non autorisée.'],405);
$me=current_user();
if(!$me||empty($me['username']))gp_json(['ok'=>false,'error'=>'Connecte-toi pour ouvrir ta plantation.'],401);
$csrf=$_POST['csrf']??null;
if(!is_string($csrf)||$csrf===''||!hash_equals((string)csrf_token(),$csrf))gp_json(['ok'=>false,'error'=>'Session expirée. Recharge la page.'],403);
$pdo=null;
try {
    $pdo=db();gp_schema($pdo);
    $username=(string)$me['username'];
    $stmt=$pdo->prepare('INSERT IGNORE INTO greenstand_plantation(username,state_json,updated_at) VALUES(?,?,NOW())');
    $stmt->execute([$username,json_encode(gp_initial(),JSON_THROW_ON_ERROR)]);
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('SELECT state_json FROM greenstand_plantation WHERE username=? FOR UPDATE');$stmt->execute([$username]);
    $s=json_decode((string)$stmt->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
    $upgrade=gp_upgrade($s);
    if($upgrade!==$s){$s=$upgrade;$save=$pdo->prepare('UPDATE greenstand_plantation SET state_json=?,updated_at=NOW() WHERE username=?');$save->execute([json_encode($s,JSON_THROW_ON_ERROR),$username]);}
    $now=time();$action=$_POST['action']??'state';
    gp_check(is_string($action),'Action invalide.');
    if($action!=='state') {
        $request=$_POST['request_id']??'';
        gp_check(is_string($request)&&preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$request)===1,'Identifiant de requête invalide.');
        if(!in_array($request,$s['seen'],true)) {
            $revision=$_POST['revision']??'';
            if(!is_string($revision)||!ctype_digit($revision)||(int)$revision!==$s['revision']) {
                $pdo->rollBack();gp_json(['ok'=>false,'error'=>'Ta plantation a changé dans un autre onglet. Elle vient d’être actualisée.']+gp_public($s,$now),409);
            }
            foreach(['pot','seed','item','a','b'] as $key)if(isset($_POST[$key]))gp_check(is_string($_POST[$key]),'Paramètre invalide.');
            $s=gp_apply($s,$action,$_POST,$now);
            $s['seen'][]=$request;$s['seen']=array_slice($s['seen'],-24);
            $stmt=$pdo->prepare('UPDATE greenstand_plantation SET state_json=?,updated_at=NOW() WHERE username=?');
            $stmt->execute([json_encode($s,JSON_THROW_ON_ERROR),$username]);
        }
    }
    $pdo->commit();gp_json(['ok'=>true]+gp_public($s,$now));
}catch(DomainException $e){if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();gp_json(['ok'=>false,'error'=>$e->getMessage()],422);
}catch(Throwable $e){if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();error_log('Plantation: '.$e->getMessage());gp_json(['ok'=>false,'error'=>'La sauvegarde est indisponible. Réessaie dans un instant.'],503);}
