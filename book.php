<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die('Invalid request');
$csrf=(string)($_POST['csrf_token']??'');
if (!hash_equals((string)($_SESSION['csrf_token']??''), $csrf)) { http_response_code(403); die('Security validation failed. Please refresh the page.'); }

$name = trim((string)($_SESSION['user_name'] ?? ''));
$mobile = preg_replace('/\D/', '', (string)($_SESSION['user_mobile'] ?? ''));
$seats = trim($_POST['seats'] ?? '');
$location = (int)($_POST['location_id'] ?? 0);
$slot = (int)($_POST['slot_id'] ?? 0);
$authenticated = !empty($_SESSION['user_authenticated']) && !empty($_SESSION['user_mobile']);
if (!$authenticated) die('Please verify your mobile number with OTP before booking.');

$sc = $pdo->prepare('SELECT s.max_seats,s.location_id,s.slot_date,s.slot_time,l.name AS location_name,l.address AS location_address FROM slots s LEFT JOIN locations l ON l.id=s.location_id WHERE s.id=? AND s.active=1');
$sc->execute([$slot]);
$sr = $sc->fetch();
$maxSeats = (int)($sr['max_seats'] ?? 0);

$arr = array_values(array_unique(array_filter(array_map('intval', explode(',', $seats)), fn($x) => $x >= 1 && $x <= $maxSeats)));
if (!$name || !$mobile || !$arr || count($arr) > $maxSeats || !$location || !$slot || !$sr || (int)$sr['location_id'] !== $location) {
    die('Please complete all booking details correctly.');
}

$pdo->beginTransaction();
try {
    $st = $pdo->prepare("SELECT seats FROM bookings WHERE slot_id=? AND status='confirmed' FOR UPDATE");
    $st->execute([$slot]);
    $booked = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $csv) {
        $booked = array_merge($booked, array_map('intval', explode(',', $csv)));
    }
    if (array_intersect($arr, $booked)) {
        throw new Exception('Sorry, one or more selected seats were just booked. Please go back and choose available seats.');
    }

    $code = 'ADDA' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $seatCsv = implode(',', $arr);
    $st = $pdo->prepare("INSERT INTO bookings(booking_code,name,mobile,location_id,slot_id,seats) VALUES(?,?,?,?,?,?)");
    $st->execute([$code, $name, $mobile, $location, $slot, $seatCsv]);
    $pdo->commit();

    $_SESSION['last_booking_code'] = $code;
    $_SESSION['sms_booking_status'] = ['authenticated' => !empty($_SESSION['user_authenticated'])];


    header('Location: success.php?code=' . urlencode($code));
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die(e($e->getMessage()) . '<br><br><a href="javascript:history.back()">← Go Back</a>');
}
?>
