<?php
/*
  LAL MEJHE ADDA - HOSTINGER CONFIGURATION
  EASIEST METHOD: Open https://yourdomain.com/setup.php and enter your exact
  Hostinger MySQL database name, username and password.
*/
$host = 'localhost';
$db = 'YOUR_HOSTINGER_DATABASE_NAME';
$user = 'YOUR_HOSTINGER_DATABASE_USERNAME';
$pass = 'YOUR_HOSTINGER_DATABASE_PASSWORD';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('Database is not configured. Please open <a href="setup.php">setup.php</a> and enter your exact Hostinger MySQL credentials.');
}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS registrations (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL,mobile VARCHAR(20) NULL,whatsapp VARCHAR(30) NULL,verified_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $c=$pdo->query("SHOW COLUMNS FROM registrations LIKE 'mobile'")->fetch(); if(!$c)$pdo->exec("ALTER TABLE registrations ADD COLUMN mobile VARCHAR(20) NULL AFTER name");
    $pdo->exec("ALTER TABLE registrations MODIFY whatsapp VARCHAR(30) NULL");
    $c=$pdo->query("SHOW COLUMNS FROM registrations LIKE 'verified_at'")->fetch(); if(!$c)$pdo->exec("ALTER TABLE registrations ADD COLUMN verified_at DATETIME NULL AFTER whatsapp");
    $c=$pdo->query("SHOW COLUMNS FROM locations LIKE 'seat_quantity'")->fetch(); if(!$c)$pdo->exec("ALTER TABLE locations ADD COLUMN seat_quantity INT NOT NULL DEFAULT 15 AFTER direction_url");
    $c=$pdo->query("SHOW COLUMNS FROM bookings LIKE 'mobile'")->fetch(); if(!$c)$pdo->exec("ALTER TABLE bookings ADD COLUMN mobile VARCHAR(20) NULL AFTER whatsapp");
} catch(Throwable $e) {}
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Lax']); session_start(); }
define('SMS_PROVIDER', 'infobip');
define('INFOBIP_API_KEY', 'YOUR_INFOBIP_API_KEY');
define('INFOBIP_TEMPLATE_ID', 'YOUR_INFOBIP_TEMPLATE_ID');
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>

// V102 Infobip SMS OTP
define('INFOBIP_API_KEY', 'YOUR_NEW_ROTATED_INFOBIP_API_KEY');
define('INFOBIP_SMS_ENDPOINT', 'https://6jm24z.api.infobip.com/sms/3/messages');
define('INFOBIP_SENDER', 'COKETH');
define('INFOBIP_TEMPLATE_ID', '1177179084139887689');
define('INFOBIP_PRINCIPAL_ENTITY_ID', '1101393580000056521');
define('APP_OTP_SECRET', 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET');
