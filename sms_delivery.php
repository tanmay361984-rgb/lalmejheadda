<?php
require '../config.php'; require '../sms.php';
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['admin'])){http_response_code(403);echo json_encode(['ok'=>false]);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['ok'=>false]);exit;}
if(empty($_SESSION['sms_test_csrf'])||!hash_equals($_SESSION['sms_test_csrf'],(string)($_POST['csrf_token']??''))){http_response_code(403);echo json_encode(['ok'=>false]);exit;}
$messageId=trim((string)($_POST['message_id']??''));
if($messageId===''||strlen($messageId)>120||!preg_match('/^[A-Za-z0-9._:-]+$/',$messageId)){http_response_code(422);echo json_encode(['ok'=>false]);exit;}
$r=infobip_get_delivery_report($messageId);
echo json_encode(['ok'=>$r['ok'],'http_status'=>$r['status']??0,'message_id'=>$messageId,'provider'=>$r['provider']??[],'diagnostic'=>$r['diagnostic']??null]);
