<?php
require '../config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if (empty($_SESSION['admin'])) { header('Location:login.php'); exit; }
if (empty($_SESSION['admin_csrf_token'])) $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
$csrf=$_SESSION['admin_csrf_token']; $msg=''; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token']??''))) $error='Security validation failed. Please refresh and try again.';
    elseif (admin_password_uses_environment()) $error='The admin password is controlled by the ADMIN_PASSWORD_HASH server environment variable. Change it in Hostinger instead.';
    else {
        $current=(string)($_POST['current_password']??''); $new=(string)($_POST['new_password']??''); $confirm=(string)($_POST['confirm_password']??'');
        if (!password_verify($current, admin_get_password_hash())) $error='Current password is incorrect.';
        elseif (strlen($new)<8) $error='New password must be at least 8 characters.';
        elseif ($new!==$confirm) $error='New password and confirmation do not match.';
        elseif (password_verify($new, admin_get_password_hash())) $error='New password must be different from the current password.';
        else {
            try {
                admin_set_password_hash($new);
                session_regenerate_id(true);
                $_SESSION['admin']=1; $_SESSION['admin_csrf_token']=bin2hex(random_bytes(32));
                $csrf=$_SESSION['admin_csrf_token']; $msg='Admin password changed successfully.';
            } catch (Throwable $e) { error_log('Admin password change error: '.$e->getMessage()); $error='Unable to save the new password. Make sure the admin folder is writable by PHP.'; }
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Change Admin Password | Lal Mejhe Adda</title><link rel="stylesheet" href="../assets/style-modern-v3.css?v=4.0.0"></head><body><section class="admin-login"><form method="post" autocomplete="off"><a class="back" href="index.php">← Admin Dashboard</a><span class="eyebrow">LAL MEJHE ADDA</span><h2>Change Password</h2><p>Update your admin password securely.</p><?php if($msg):?><div class="notice success"><?=e($msg)?></div><?php endif;?><?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?><input type="hidden" name="csrf_token" value="<?=e($csrf)?>"><input type="password" name="current_password" placeholder="Current Password" required autocomplete="current-password"><input type="password" name="new_password" placeholder="New Password (8+ characters)" required minlength="8" autocomplete="new-password"><input type="password" name="confirm_password" placeholder="Confirm New Password" required minlength="8" autocomplete="new-password"><button class="btn" type="submit">CHANGE PASSWORD</button><small>Password is stored only as a secure bcrypt hash.</small></form></section><style>.admin-login{min-height:100vh;background:#fff;display:grid;place-items:center;padding:20px}.admin-login form{width:min(430px,100%);padding:42px;border:1px solid #ececec;border-radius:24px;box-shadow:0 18px 55px #0002;text-align:center}.admin-login h2{font-size:38px;margin:16px 0 8px}.admin-login p{color:#6f7378}.admin-login input{width:100%;padding:15px;border:1px solid #ddd;border-radius:12px;margin:8px 0}.admin-login .btn{width:100%;cursor:pointer;margin-top:8px}.admin-login small{display:block;margin-top:18px;color:#888}.notice{padding:12px;border-radius:10px;margin:15px 0;text-align:left}.success{background:#e9f8f1;color:#096941}.error{background:#fff0f1;color:#a71f28}.back{display:block;text-align:left;text-decoration:none;color:#687487;font-weight:700;font-size:14px}</style></body></html>
