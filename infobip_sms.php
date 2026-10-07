<?php
/**
 * Infobip SMS gateway for India DLT OTP.
 * Keep INFOBIP_API_KEY server-side. Do not expose it to the browser.
 */
require_once __DIR__ . '/config.php';

function sendInfobipOtpSms(string $recipient, string $otp): array {
    $endpoint = INFOBIP_SMS_ENDPOINT;
    $payload = [
        'messages' => [[
            'sender' => INFOBIP_SENDER,
            'destinations' => [['to' => $recipient]],
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

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: App ' . INFOBIP_API_KEY,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return ['ok'=>false, 'status'=>$status, 'error'=>$error];
    }

    $decoded = json_decode($response ?: '', true);
    $ok = $status >= 200 && $status < 300;
    return [
        'ok'=>$ok,
        'status'=>$status,
        'response'=>$decoded ?: $response
    ];
}
