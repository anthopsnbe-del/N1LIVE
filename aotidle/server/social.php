<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Access-Control-Allow-Origin: *');header('Access-Control-Allow-Headers: Content-Type');header('Access-Control-Allow-Methods: POST, OPTIONS');header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
require_once __DIR__.'/db.php';require_once __DIR__.'/social-core.php';require_once __DIR__.'/boss-core.php';
try{
  $raw=file_get_contents('php://input',false,null,0,8193);if(strlen($raw)>8192)throw new DomainException('Requête trop longue.');
  $in=json_decode($raw,true);if(!is_array($in))throw new DomainException('Requête illisible.');
  $pdo=db();social_install($pdo);
  $pdo->beginTransaction();$sql='SELECT id FROM social_lock WHERE id=1'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'');$pdo->query($sql)->fetch();
  $result=social_handle($pdo,$in);$pdo->commit();echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(DomainException $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();http_response_code(503);echo json_encode(['ok'=>false,'error'=>'Service multijoueur indisponible. Réessayez plus tard.']);}
