<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$id=(int)($_GET['slot_id']??0);
if($id<1){http_response_code(422);echo json_encode(['seats'=>[],'message'=>'Invalid slot']);exit;}
$slot=$pdo->prepare("SELECT id FROM slots WHERE id=? AND active=1 AND slot_date>=CURDATE() LIMIT 1");
$slot->execute([$id]);
if(!$slot->fetchColumn()){http_response_code(404);echo json_encode(['seats'=>[],'message'=>'Slot unavailable']);exit;}
$st=$pdo->prepare("SELECT seats FROM bookings WHERE slot_id=? AND status='confirmed'");
$st->execute([$id]);
$all=[];
foreach($st->fetchAll(PDO::FETCH_COLUMN) as $csv){foreach(explode(',',(string)$csv) as $n){if($n!=='' && ctype_digit(trim($n)))$all[]=(int)$n;}}
echo json_encode(['seats'=>array_values(array_unique($all))],JSON_UNESCAPED_SLASHES);
