<?php
require '../config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$enableFile=__DIR__.'/setup.enable';
$lockFile=__DIR__.'/setup.lock';
if (!is_file($enableFile) || is_file($lockFile) || admin_password_uses_environment()) { http_response_code(404); exit('Setup is disabled.'); }
if (!isset($_SESSION['admin_setup_csrf'])) $_SESSION['admin_setup_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['admin_setup_csrf']; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token']??''))) $error='Security validation failed. Please refresh and try again.';
    else {
        $new=(string)($_POST['new_password']??''); $confirm=(string)($_POST['confirm_password']??'');
        if (strlen($new)<8) $error='Password must be at least 8 characters.';
        elseif ($new!==$confirm) $error='Password and confirmation do not match.';
        else {
            try {
                admin_set_password_hash($new);
                @file_put_contents($lockFile, date('c')."\n", LOCK_EX);
                @unlink($enableFile); // nosemgrep: php.lang.security.unlink-use.unlink-use -- setup flag is fixed internal path and is removed only after CSRF-protected password setup
                unset($_SESSION['admin_setup_csrf']);
                session_regenerate_id(true);
                $_SESSION['admin']=1;
                $_SESSION['admin_csrf_token']=bin2hex(random_bytes(32));
                header('Location:index.php?password_set=1'); exit;
            } catch (Throwable $e) {
                error_log('Admin initial password setup error: '.$e->getMessage());
                $error='Unable to save the password. Make sure the admin folder is writable by PHP.';
            }
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Set Admin Password | Lal Mejhe Adda</title><link rel="stylesheet" href="../assets/style-modern-v3.css?v=4.0.0"></head><body>
<section class="admin-login"><form method="post" autocomplete="off"><span class="eyebrow">LAL MEJHE ADDA</span><h2>Set Admin Password</h2><p>Create your admin password. This setup page locks itself after a successful setup.</p><?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?><input type="hidden" name="csrf_token" value="<?=e($csrf)?>"><input type="password" name="new_password" placeholder="New Admin Password (8+ characters)" required minlength="8" autocomplete="new-password"><input type="password" name="confirm_password" placeholder="Confirm Password" required minlength="8" autocomplete="new-password"><button class="btn" type="submit">SET PASSWORD</button><small>Password is stored only as a secure bcrypt hash in a web-blocked server file.</small></form></section>
<style>.admin-login{min-height:100vh;background:#fff;display:grid;place-items:center;padding:20px}.admin-login form{width:min(430px,100%);padding:42px;border:1px solid #ececec;border-radius:24px;box-shadow:0 18px 55px #0002;text-align:center}.admin-login h2{font-size:38px;margin:16px 0 8px}.admin-login p{color:#6f7378}.admin-login input{width:100%;padding:15px;border:1px solid #ddd;border-radius:12px;margin:8px 0}.admin-login .btn{width:100%;cursor:pointer;margin-top:8px}.admin-login small{display:block;margin-top:18px;color:#888}.notice{background:#fff0f1;color:#a71f28;padding:12px;border-radius:10px;margin:15px 0}</style></body></html>
