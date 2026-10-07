<?php
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['admin'])) { header('Location:login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals((string)($_SESSION['admin_csrf_token']??''),(string)($_POST['csrf_token']??''))) { http_response_code(403); exit('Security validation failed.'); }
$_SESSION=[];
if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',$p['secure'],$p['httponly']); }
session_destroy();
header('Clear-Site-Data: "cache", "cookies", "storage"');
header('Location:login.php?logged_out=1'); exit;
?>