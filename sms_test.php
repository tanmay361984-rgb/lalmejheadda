<?php
require '../config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require '../sms.php';

if (empty($_SESSION['admin'])) {
    header('Location:login.php');
    exit;
}
if (empty($_SESSION['sms_test_csrf'])) {
    $_SESSION['sms_test_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['sms_test_csrf'];
$result = null;
$mobile = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        $result = ['ok'=>false,'message'=>'Security validation failed. Refresh and try again.'];
    } elseif (($_POST['action'] ?? 'send') === 'check_delivery') {
        $messageId = trim((string)($_POST['message_id'] ?? ''));
        if ($messageId === '' || strlen($messageId) > 120 || !preg_match('/^[A-Za-z0-9._:-]+$/', $messageId)) {
            $result = ['ok'=>false,'message'=>'Invalid message ID.'];
        } else {
            $report = infobip_get_delivery_report($messageId);
            $result = [
                'ok'=>$report['ok'],
                'message'=>$report['ok'] ? 'Delivery report retrieved.' : 'Unable to retrieve delivery report.',
                'status'=>$report['status'] ?? 0,
                'message_id'=>$messageId,
                'provider'=>$report['provider'] ?? [],
                'diagnostic'=>$report['diagnostic'] ?? null
            ];
        }
    } else {
        $mobile = sms_normalize_mobile($_POST['mobile'] ?? '');
        if (!preg_match('/^91[6-9]\d{9}$/', $mobile)) {
            $result = ['ok'=>false,'message'=>'Enter a valid Indian mobile number.'];
        } elseif (!sms_configured()) {
            $result = ['ok'=>false,'message'=>'Infobip configuration is incomplete. Check config.php / server environment.'];
            infobip_log('diagnostic_test_not_configured', ['mobile'=>sms_mask_mobile($mobile)]);
        } else {
            $testOtp = str_pad((string)random_int(0,999999), 6, '0', STR_PAD_LEFT);
            $result = infobip_send_sms($mobile, $testOtp, 'admin_diagnostic_test');
            if ($result['ok']) {
                $result['message'] = 'Infobip accepted the test SMS. Use the Message ID below to check delivery.';
                $result['test_otp'] = null;
                $result['message_id'] = $result['message_id'] ?? '';
                $result['initial_status'] = $result['initial_status'] ?? 'PENDING';
                $result['initial_status_description'] = $result['initial_status_description'] ?? '';
            } else {
                $result['message'] = 'Infobip did not accept the test SMS. See the diagnostic log below.';
            }
        }
    }
}

$logPath = dirname(__DIR__) . '/logs/infobip.log';
$lines = [];
if (is_file($logPath)) {
    $raw = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($raw) {
        $lines = array_slice(array_reverse($raw), 0, 30);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SMS Gateway Diagnostic</title>
<style>
body{font-family:Arial,sans-serif;background:#f6f6f6;margin:0;color:#111}
.wrap{max-width:1000px;margin:40px auto;padding:0 20px}
.card{background:#fff;border-radius:16px;padding:26px;margin-bottom:20px;box-shadow:0 8px 30px #0001}
h1{margin-top:0}.ok{background:#e9f8ef;padding:14px;border-radius:10px}.bad{background:#fff0f0;padding:14px;border-radius:10px}
input,button{font:inherit;padding:12px;border-radius:9px;border:1px solid #ccc}
input{width:260px}button{background:#e41e2b;color:#fff;border:0;cursor:pointer;font-weight:700}
pre{white-space:pre-wrap;word-break:break-word;background:#111;color:#eee;padding:15px;border-radius:10px;font-size:12px}
small{color:#666}
</style>
</head>
<body>
<div class="wrap">
<div class="card">
<h1>Infobip SMS Gateway Diagnostic</h1>
<p>This page is admin-only. It sends one real test SMS and shows sanitized gateway diagnostics. API keys and OTP values are never displayed or logged.</p>
<?php if ($result): ?>
<div class="<?=!empty($result['ok'])?'ok':'bad'?>">
<strong><?=e($result['message'] ?? '')?></strong>
<?php if (isset($result['status'])): ?><br>HTTP status: <?=e($result['status'])?><?php endif; ?>
<?php if (isset($result['diagnostic'])): ?><br>Diagnostic: <?=e($result['diagnostic'])?><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($result && !empty($result['message_id'])): ?>
<div style="margin-top:16px;padding:14px;border:1px solid #ddd;border-radius:10px;background:#fafafa">
<strong>Infobip Message ID:</strong> <code><?=e($result['message_id'])?></code>
<?php if (!empty($result['initial_status'])): ?><br><strong>Initial status:</strong> <?=e($result['initial_status'])?><?php endif; ?>
<?php if (!empty($result['initial_status_description'])): ?><br><strong>Description:</strong> <?=e($result['initial_status_description'])?><?php endif; ?>
<form method="post" style="margin-top:12px">
<input type="hidden" name="csrf_token" value="<?=e($csrf)?>">
<input type="hidden" name="action" value="check_delivery">
<input type="hidden" name="message_id" value="<?=e($result['message_id'])?>">
<button type="submit" style="background:#111">CHECK DELIVERY STATUS</button>
</form>
</div>
<?php endif; ?>

<?php if ($result && isset($result['provider']) && is_array($result['provider']) && !empty($result['provider'])): ?>
<div style="margin-top:16px">
<strong>Sanitized provider response:</strong>
<pre><?=e(json_encode($result['provider'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES))?></pre>
</div>
<?php endif; ?>

<form method="post" style="margin-top:20px">
<input type="hidden" name="csrf_token" value="<?=e($csrf)?>">
<label>Indian mobile number</label><br><br>
<input name="mobile" inputmode="numeric" maxlength="10" placeholder="9876543210" value="<?=e(substr($mobile,2))?>">
<button type="submit">SEND TEST SMS</button>
</form>
</div>

<div class="card">
<h2>Configuration</h2>
<ul>
<li>Endpoint: <?=e(INFOBIP_SMS_ENDPOINT)?></li>
<li>Sender: <?=e(INFOBIP_SENDER)?></li>
<li>DLT Template ID: <?=e(INFOBIP_TEMPLATE_ID)?></li>
<li>PE ID: <?=e(INFOBIP_PRINCIPAL_ENTITY_ID)?></li>
<li>API key: <?=sms_configured() ? 'Configured (hidden)' : 'NOT CONFIGURED'?></li>
<li>OTP secret: <?= (defined('APP_OTP_SECRET') && APP_OTP_SECRET !== 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET') ? 'Configured (hidden)' : 'NOT CONFIGURED'?></li>
</ul>
</div>

<div class="card">
<h2>Latest diagnostic events</h2>
<small>Only sanitized gateway metadata is shown. HTTP 200 means accepted; delivery is asynchronous and can remain PENDING until a report is available.</small>
<?php if (!$lines): ?>
<p>No diagnostic events yet.</p>
<?php else: foreach($lines as $line): ?>
<pre><?=e($line)?></pre>
<?php endforeach; endif; ?>
</div>
</div>
<div style="position:fixed;right:20px;top:20px;z-index:99"><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?=e($_SESSION['admin_csrf_token']??'')?>"><button style="background:#e41e2b;color:#fff;border:0;border-radius:9px;padding:9px 13px;font-weight:800;cursor:pointer">Logout</button></form></div></body>
</html>
