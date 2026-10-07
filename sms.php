<?php
require_once __DIR__ . '/config.php';

/**
 * Infobip SMS helper with secure diagnostics.
 * Never logs API keys, Authorization headers, or OTP values.
 */
function sms_normalize_mobile($value): string {
    $digits = preg_replace('/\D+/', '', (string)$value);
    if (strlen($digits) === 10) $digits = '91' . $digits;
    return $digits;
}

function sms_mask_mobile(string $mobile): string {
    $d = preg_replace('/\D+/', '', $mobile);
    if (strlen($d) <= 4) return '****';
    return substr($d, 0, 2) . str_repeat('*', max(0, strlen($d)-6)) . substr($d, -4);
}

function sms_configured(): bool {
    return defined('INFOBIP_API_KEY')
        && INFOBIP_API_KEY !== ''
        && INFOBIP_API_KEY !== 'YOUR_NEW_ROTATED_INFOBIP_API_KEY'
        && defined('INFOBIP_SMS_ENDPOINT')
        && defined('INFOBIP_TEMPLATE_ID')
        && defined('INFOBIP_PRINCIPAL_ENTITY_ID')
        && defined('INFOBIP_SENDER')
        && INFOBIP_TEMPLATE_ID !== ''
        && INFOBIP_PRINCIPAL_ENTITY_ID !== ''
        && INFOBIP_SENDER !== ''
        && defined('APP_OTP_SECRET')
        && APP_OTP_SECRET !== ''
        && APP_OTP_SECRET !== 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET';
}

function otp_hash(string $otp): string {
    return hash_hmac('sha256', $otp, APP_OTP_SECRET);
}

function client_ip(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function infobip_log(string $event, array $data = []): void {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    // Explicitly remove secrets and OTPs from anything passed to the logger.
    unset($data['api_key'], $data['authorization'], $data['otp'], $data['otp_hash'], $data['headers']);
    if (isset($data['body']) && is_string($data['body'])) {
        $data['body'] = substr($data['body'], 0, 2000);
    }
    $record = [
        'time' => date('c'),
        'event' => $event,
        'ip' => client_ip(),
        'data' => $data
    ];
    @file_put_contents(
        $dir . '/infobip.log',
        json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function infobip_send_sms(string $mobile, string $otp, string $context = 'otp'): array {
    if (!sms_configured()) {
        infobip_log('gateway_not_configured', [
            'context'=>$context,
            'mobile'=>sms_mask_mobile($mobile),
            'endpoint'=>defined('INFOBIP_SMS_ENDPOINT') ? INFOBIP_SMS_ENDPOINT : null
        ]);
        return ['ok'=>false, 'message'=>'SMS gateway is not configured.', 'status'=>0, 'diagnostic'=>'NOT_CONFIGURED'];
    }

    $payload = [
        'messages' => [[
            'sender' => INFOBIP_SENDER,
            'destinations' => [['to' => $mobile]],
            'content' => [
                'text' => 'Please use the One Time Password ' . $otp . ' to login on Coke Promotion Website. Please do not share it with anyone.'
            ],
            'options' => [
                'regional' => [
                    'indiaDlt' => [
                        'contentTemplateId' => INFOBIP_TEMPLATE_ID,
                        'principalEntityId' => INFOBIP_PRINCIPAL_ENTITY_ID
                    ]
                ]
            ]
        ]]
    ];

    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $ch = curl_init(INFOBIP_SMS_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: App ' . INFOBIP_API_KEY,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $totalTime = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    $decoded = json_decode((string)$body, true);
    if ($curlError || $status < 200 || $status >= 300) {
        infobip_log('sms_send_failed', [
            'context'=>$context,
            'mobile'=>sms_mask_mobile($mobile),
            'status'=>$status,
            'curl_errno'=>$curlErrno,
            'curl_error'=>$curlError ?: null,
            'content_type'=>$contentType,
            'total_time_ms'=>round($totalTime*1000),
            'response'=>$decoded ?: null,
            'body'=>$decoded ? null : (string)$body
        ]);
        return [
            'ok'=>false,
            'message'=>'Unable to send OTP right now.',
            'status'=>$status,
            'diagnostic'=>$curlError ? 'CURL_ERROR' : 'HTTP_ERROR',
            'provider'=>$decoded ?: []
        ];
    }

    $provider = $decoded ?: [];
    $messageId = '';
    $bulkId = '';
    $initialStatus = '';
    $initialStatusDescription = '';
    $messageCount = null;
    if (isset($provider['messages'][0]) && is_array($provider['messages'][0])) {
        $m = $provider['messages'][0];
        $messageId = (string)($m['messageId'] ?? '');
        $bulkId = (string)($m['bulkId'] ?? ($provider['bulkId'] ?? ''));
        $initialStatus = (string)($m['status']['name'] ?? $m['status']['groupName'] ?? '');
        $initialStatusDescription = (string)($m['status']['description'] ?? '');
        $messageCount = $m['details']['messageCount'] ?? null;
    }
    infobip_log('sms_send_ok', [
        'context'=>$context,
        'mobile'=>sms_mask_mobile($mobile),
        'status'=>$status,
        'content_type'=>$contentType,
        'total_time_ms'=>round($totalTime*1000),
        'message_id'=>$messageId ?: null,
        'bulk_id'=>$bulkId ?: null,
        'initial_status'=>$initialStatus ?: null,
        'initial_status_description'=>$initialStatusDescription ?: null,
        'message_count'=>$messageCount,
        'response'=>$provider
    ]);
    return [
        'ok'=>true,
        'status'=>$status,
        'provider'=>$provider,
        'message_id'=>$messageId,
        'bulk_id'=>$bulkId,
        'initial_status'=>$initialStatus,
        'initial_status_description'=>$initialStatusDescription,
        'message_count'=>$messageCount
    ];
}

function infobip_get_delivery_report(string $messageId): array {
    if (!sms_configured() || $messageId === '') {
        return ['ok'=>false,'status'=>0,'diagnostic'=>'NOT_CONFIGURED_OR_NO_MESSAGE_ID'];
    }
    $parsed = parse_url(INFOBIP_SMS_ENDPOINT);
    $base = (!empty($parsed['scheme']) && !empty($parsed['host']))
        ? $parsed['scheme'].'://'.$parsed['host']
        : 'https://6jm24z.api.infobip.com';
    $url = $base.'/sms/3/reports?messageId='.rawurlencode($messageId);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: App '.INFOBIP_API_KEY,
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTime = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    $decoded = json_decode((string)$body, true);
    if ($curlError || $status < 200 || $status >= 300) {
        infobip_log('delivery_report_failed', [
            'message_id'=>$messageId,
            'status'=>$status,
            'curl_errno'=>$curlErrno,
            'curl_error'=>$curlError ?: null,
            'total_time_ms'=>round($totalTime*1000),
            'response'=>$decoded ?: null
        ]);
        return ['ok'=>false,'status'=>$status,'diagnostic'=>$curlError?'CURL_ERROR':'HTTP_ERROR','provider'=>$decoded ?: []];
    }
    infobip_log('delivery_report_ok', [
        'message_id'=>$messageId,
        'status'=>$status,
        'total_time_ms'=>round($totalTime*1000),
        'response'=>$decoded ?: null
    ]);
    return ['ok'=>true,'status'=>$status,'provider'=>$decoded ?: []];
}
?>
