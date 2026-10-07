<?php
require '../config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require '../sms.php';
if (empty($_SESSION['admin'])) { header('Location:login.php'); exit; }
if (empty($_SESSION['admin_csrf_token'])) { $_SESSION['admin_csrf_token']=bin2hex(random_bytes(32)); }

$schema = otp_schema_status($pdo);
$checks = [
    'Database connection' => ['ok'=>true,'detail'=>'Connected'],
    'OTP table and columns' => ['ok'=>$schema['ok'],'detail'=>$schema['ok']?'Ready':'Missing: '.implode(', ', $schema['missing_columns'] ?? [])],
    'Infobip configuration' => ['ok'=>sms_configured(),'detail'=>sms_configured()?'Configured (key hidden)':'Incomplete'],
    'cURL extension' => ['ok'=>function_exists('curl_init'),'detail'=>function_exists('curl_init')?'Available':'Missing'],
    'PHP session' => ['ok'=>session_status() === PHP_SESSION_ACTIVE,'detail'=>session_status() === PHP_SESSION_ACTIVE?'Active':'Not active'],
];

$recent=[];
try {
    $recent=$pdo->query("SELECT id,mobile,created_at,infobip_message_id,infobip_status,infobip_status_description,infobip_sent_at,infobip_delivered_at,infobip_error FROM otp_requests ORDER BY id DESC LIMIT 15")->fetchAll();
} catch(Throwable $e) {}
function mask_health_mobile($m){$d=preg_replace('/\D+/','',(string)$m);return strlen($d)>6?substr($d,0,2).'****'.substr($d,-4):'****';}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OTP Health Check</title><style>body{font-family:Arial,sans-serif;background:#f6f6f6;margin:0;color:#111}.wrap{max-width:1100px;margin:40px auto;padding:0 20px}.card{background:#fff;border-radius:16px;padding:26px;margin-bottom:20px;box-shadow:0 8px 30px #0001}table{width:100%;border-collapse:collapse}td,th{padding:10px;border-bottom:1px solid #eee;text-align:left} .ok{color:#087a39;font-weight:700}.bad{color:#b00020;font-weight:700}code{word-break:break-word}</style></head><body><div class="wrap"><div class="card"><h1>V102.3 OTP Health Check</h1><p>This page is admin-only. Secrets and OTP values are never displayed.</p><table><tr><th>Check</th><th>Status</th><th>Details</th></tr><?php foreach($checks as $label=>$c): ?><tr><td><?=e($label)?></td><td class="<?=!empty($c['ok'])?'ok':'bad'?>"><?=!empty($c['ok'])?'OK':'FAIL'?></td><td><?=e($c['detail'])?></td></tr><?php endforeach; ?></table></div><div class="card"><h2>Recent OTP requests</h2><p>Use this to distinguish rate-limit, database, Infobip acceptance, and delivery problems. OTP hashes are not shown.</p><?php if(!$recent): ?><p>No OTP requests recorded.</p><?php else: ?><table><tr><th>ID</th><th>Mobile</th><th>Created</th><th>Infobip Message ID</th><th>Status</th><th>Sent</th><th>Delivered</th><th>Error</th></tr><?php foreach($recent as $r): ?><tr><td><?=e($r['id'])?></td><td><?=e(mask_health_mobile($r['mobile']))?></td><td><?=e($r['created_at'])?></td><td><code><?=e($r['infobip_message_id'] ?: '-')?></code></td><td><?=e($r['infobip_status'] ?: '-')?><br><small><?=e($r['infobip_status_description'] ?: '')?></small></td><td><?=e($r['infobip_sent_at'] ?: '-')?></td><td><?=e($r['infobip_delivered_at'] ?: '-')?></td><td><?=e($r['infobip_error'] ?: '-')?></td></tr><?php endforeach; ?></table><?php endif; ?></div><div class="card"><a href="sms_test.php">← SMS Gateway Diagnostic</a></div></div><div style="position:fixed;right:20px;top:20px;z-index:99"><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?=e($_SESSION['admin_csrf_token']??'')?>"><button style="background:#e41e2b;color:#fff;border:0;border-radius:9px;padding:9px 13px;font-weight:800;cursor:pointer">Logout</button></form></div></body></html>
