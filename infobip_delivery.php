<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
$data=json_decode(file_get_contents('php://input') ?: '',true);
if(!is_array($data)){http_response_code(400);echo json_encode(['ok'=>false]);exit;}
$results=$data['results']??[];
if(!is_array($results))$results=[];
foreach($results as $r){
    $messageId=(string)($r['messageId']??'');
    if($messageId===''||strlen($messageId)>120)continue;
    $status=(string)($r['status']['name']??$r['status']['groupName']??'');
    $description=(string)($r['status']['description']??'');
    $error=(string)($r['error']['description']??'');
    $doneAt=(string)($r['doneAt']??'');
    $deliveredAt=null;
    if(stripos($status,'DELIVERED_TO_HANDSET')!==false||strtoupper((string)($r['status']['groupName']??''))==='DELIVERED'){
        $deliveredAt=date('Y-m-d H:i:s');
        if($doneAt!==''){ $ts=strtotime($doneAt); if($ts!==false)$deliveredAt=date('Y-m-d H:i:s',$ts); }
    }
    $q=$pdo->prepare("UPDATE otp_requests SET infobip_status=?,infobip_status_description=?,infobip_error=?,infobip_delivered_at=COALESCE(?,infobip_delivered_at) WHERE infobip_message_id=?");
    $q->execute([$status?:null,$description?:null,$error?:null,$deliveredAt,$messageId]);
    $dir=__DIR__.'/logs';if(!is_dir($dir))@mkdir($dir,0750,true);
    @file_put_contents($dir.'/infobip.log',json_encode(['time'=>date('c'),'event'=>'delivery_report_webhook','data'=>['message_id'=>$messageId,'status'=>$status,'description'=>$description,'error'=>$error?:null,'done_at'=>$doneAt?:null]],JSON_UNESCAPED_SLASHES).PHP_EOL,FILE_APPEND|LOCK_EX);
}
http_response_code(200);echo json_encode(['ok'=>true]);
