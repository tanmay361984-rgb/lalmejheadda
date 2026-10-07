<?php
/* V102.5.2 — Signup endpoint hardened to ALWAYS return JSON, including PHP/config failures. */
ob_start();
$signup_json_sent = false;
function signup_json(string $message, int $status = 200, array $extra = []): void {
    global $signup_json_sent;
    $signup_json_sent = true;
    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(array_merge(['ok' => $status >= 200 && $status < 300, 'message' => $message], $extra), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function signup_error(string $message, int $status): void { signup_json($message, $status); }

/* Convert otherwise blank/fatal PHP responses into a JSON response the browser can parse. */
register_shutdown_function(function () use (&$signup_json_sent) {
    $err = error_get_last();
    if (!$signup_json_sent && $err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        error_log('V102.5.2 signup fatal: '.$err['message'].' at '.$err['file'].':'.$err['line']);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'message'=>'Unable to complete sign up right now. Please try again.']);
    }
});

try {
    require_once __DIR__ . '/config.php';
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') signup_error('Invalid request.', 405);

    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrf)) signup_error('Security validation failed. Please refresh the page.', 403);

    if (empty($_SESSION['mobile_verified']) || empty($_SESSION['verified_mobile'])) {
        signup_error('Please verify your mobile number first.', 401);
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $consent = (string)($_POST['terms_privacy'] ?? '') === '1';
    $mobile = function_exists('sms_normalize_mobile')
        ? sms_normalize_mobile($_SESSION['verified_mobile'])
        : preg_replace('/\D+/', '', (string)$_SESSION['verified_mobile']);

    if ($name === '' || mb_strlen($name) > 100) signup_error('Please enter your full name.', 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) signup_error('Please enter a valid email address.', 422);
    if (!$consent) signup_error('Please accept the Terms & Conditions and Privacy Policy to continue.', 422);
    if (!preg_match('/^91[6-9]\d{9}$/', $mobile)) signup_error('Verified mobile number is invalid. Please start again.', 422);

    /* Make the registrations table compatible with both old and V102.5 schemas. */
    $columns = $pdo->query('SHOW COLUMNS FROM registrations')->fetchAll(PDO::FETCH_COLUMN, 0);
    $required = [
        'name' => 'VARCHAR(100) NULL',
        'mobile' => 'VARCHAR(20) NULL',
        'email' => 'VARCHAR(190) NULL',
        'terms_accepted_at' => 'DATETIME NULL',
        'privacy_accepted_at' => 'DATETIME NULL',
        'verified_at' => 'DATETIME NULL',
        'created_at' => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];
    foreach ($required as $col => $def) {
        if (!in_array($col, $columns, true)) {
            $pdo->exec("ALTER TABLE registrations ADD COLUMN `{$col}` {$def}");
        }
    }

    $now = date('Y-m-d H:i:s');
    $st = $pdo->prepare('SELECT id FROM registrations WHERE mobile=? LIMIT 1');
    $st->execute([$mobile]);
    $id = $st->fetchColumn();

    if ($id) {
        $up = $pdo->prepare('UPDATE registrations SET name=?,email=?,terms_accepted_at=?,privacy_accepted_at=?,verified_at=COALESCE(verified_at,?) WHERE id=?');
        $up->execute([$name, $email, $now, $now, $now, (int)$id]);
    } else {
        $in = $pdo->prepare('INSERT INTO registrations(name,mobile,email,terms_accepted_at,privacy_accepted_at,verified_at) VALUES(?,?,?,?,?,?)');
        $in->execute([$name, $mobile, $email, $now, $now, $now]);
        $id = (int)$pdo->lastInsertId();
    }

    session_regenerate_id(true);
    $_SESSION['user_authenticated'] = true;
    $_SESSION['user_mobile'] = $mobile;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['registration_id'] = (int)$id;
    unset($_SESSION['mobile_verified'], $_SESSION['verified_mobile'], $_SESSION['otp_verified']);

    signup_json('Sign up completed successfully.', 200, [
        'name' => $name,
        'mobile' => $mobile,
        'email' => $email
    ]);
} catch (Throwable $e) {
    error_log('V102.5.2 signup save exception: '.$e->getMessage().' ['.get_class($e).']');
    signup_error('Unable to complete sign up right now. Please try again.', 500);
}
