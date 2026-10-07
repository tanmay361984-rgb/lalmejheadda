<?php
require_once __DIR__ . '/sms.php';
header('Content-Type: application/json; charset=utf-8');

function otp_json_error(string $message, int $status, array $extra = []): void {
    http_response_code($status);
    echo json_encode(array_merge(['ok'=>false,'message'=>$message], $extra), JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') otp_json_error('Invalid request.', 405);

$csrf = (string)($_POST['csrf_token'] ?? '');
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrf)) {
    otp_json_error('Security validation failed. Please refresh the page.', 403);
}

$mobile = sms_normalize_mobile($_POST['mobile'] ?? '');
if (!preg_match('/^91[6-9]\d{9}$/', $mobile)) {
    otp_json_error('Please enter a valid Indian mobile number.', 422);
}
if (!sms_configured()) {
    otp_json_error('SMS gateway is not configured. Please contact the administrator.', 503);
}

$schema = otp_schema_status($pdo);
if (!$schema['ok']) {
    infobip_log('guest_otp_schema_unavailable', [
        'mobile'=>sms_mask_mobile($mobile),
        'missing_table'=>$schema['missing_table'] ?? false,
        'missing_columns'=>$schema['missing_columns'] ?? []
    ]);
    otp_json_error('OTP service is temporarily unavailable. Please try again later.', 503);
}

$ip = client_ip();
$requestId = 0;

try {
    /* Rate limits are checked before creating/sending an OTP. */
    $q = $pdo->prepare("SELECT created_at FROM otp_requests WHERE mobile=? ORDER BY id DESC LIMIT 1");
    $q->execute([$mobile]);
    $last = $q->fetch();
    if ($last && (time() - strtotime($last['created_at'])) < OTP_RESEND_SECONDS) {
        otp_json_error('Please wait '.OTP_RESEND_SECONDS.' seconds before requesting another OTP.', 429);
    }

    $q = $pdo->prepare("SELECT COUNT(*) FROM otp_requests WHERE mobile=? AND created_at >= (NOW() - INTERVAL 1 HOUR)");
    $q->execute([$mobile]);
    if ((int)$q->fetchColumn() >= OTP_MAX_REQUESTS_PER_HOUR) {
        otp_json_error('OTP request limit reached. Please try again later.', 429);
    }

    $q = $pdo->prepare("SELECT COUNT(*) FROM otp_requests WHERE ip_address=? AND created_at >= (NOW() - INTERVAL 1 HOUR)");
    $q->execute([$ip]);
    if ((int)$q->fetchColumn() >= 20) {
        otp_json_error('Too many OTP requests. Please try again later.', 429);
    }

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = otp_hash($otp);

    /*
     * V102.3: create the DB record BEFORE calling Infobip.
     * This prevents an SMS being accepted by Infobip and then being lost because
     * the following INSERT failed. The row becomes the single source of truth.
     */
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE otp_requests SET verified=1 WHERE mobile=? AND verified=0")->execute([$mobile]);
    $st = $pdo->prepare("INSERT INTO otp_requests(mobile,otp_hash,ip_address,attempts,verified,expires_at,infobip_status) VALUES(?,?,?,0,0,DATE_ADD(NOW(), INTERVAL 5 MINUTE),'CREATED')");
    $st->execute([$mobile,$hash,$ip]);
    $requestId = (int)$pdo->lastInsertId();
    $pdo->commit();

    $result = infobip_send_sms($mobile, $otp, 'guest_otp');

    if (!$result['ok']) {
        $providerError = '';
        if (!empty($result['provider']) && is_array($result['provider'])) {
            $providerError = json_encode($result['provider'], JSON_UNESCAPED_SLASHES);
        }
        $providerError = substr((string)$providerError, 0, 255);
        $up = $pdo->prepare("UPDATE otp_requests SET infobip_status='FAILED',infobip_status_description=?,infobip_error=? WHERE id=?");
        $up->execute([
            substr((string)($result['diagnostic'] ?? 'HTTP_ERROR'), 0, 255),
            $providerError ?: null,
            $requestId
        ]);
        infobip_log('guest_otp_send_failed', [
            'request_id'=>$requestId,
            'mobile'=>sms_mask_mobile($mobile),
            'status'=>$result['status'] ?? 0,
            'diagnostic'=>$result['diagnostic'] ?? null
        ]);
        otp_json_error('Unable to send OTP right now. Please try again.', 502, ['reference'=>(string)$requestId]);
    }

    $messageId = (string)($result['message_id'] ?? '');
    $initialStatus = (string)($result['initial_status'] ?? 'PENDING');
    $initialStatusDescription = (string)($result['initial_status_description'] ?? '');

    $up = $pdo->prepare("UPDATE otp_requests SET infobip_message_id=?,infobip_status=?,infobip_status_description=?,infobip_sent_at=NOW(),infobip_error=NULL WHERE id=?");
    $up->execute([
        $messageId ?: null,
        $initialStatus ?: 'PENDING',
        $initialStatusDescription ?: null,
        $requestId
    ]);

    $_SESSION['otp_mobile'] = $mobile;
    $_SESSION['otp_request_id'] = $requestId;
    $_SESSION['otp_verified'] = false;

    infobip_log('guest_otp_created', [
        'request_id'=>$requestId,
        'mobile'=>sms_mask_mobile($mobile),
        'message_id'=>$messageId ?: null,
        'initial_status'=>$initialStatus ?: null
    ]);

    echo json_encode([
        'ok'=>true,
        'message'=>'OTP sent to +'.substr($mobile,0,2).' '.substr($mobile,2,5).' '.substr($mobile,7),
        'expires_in'=>OTP_TTL_SECONDS,
        'resend_after'=>OTP_RESEND_SECONDS,
        'reference'=>(string)$requestId
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    infobip_log('guest_otp_exception', [
        'request_id'=>$requestId ?: null,
        'mobile'=>sms_mask_mobile($mobile),
        'exception'=>get_class($e),
        'message'=>substr($e->getMessage(), 0, 500)
    ]);
    error_log('V102.3 guest OTP exception: '.$e->getMessage());
    otp_json_error('Unable to send OTP right now. Please try again.', 500, $requestId ? ['reference'=>(string)$requestId] : []);
}
