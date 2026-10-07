<?php require_once __DIR__.'/config.php'; if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#e41e2b">
<meta name="csrf-token" content="<?php echo e($_SESSION['csrf_token']); ?>">
<style>
@font-face{font-family:"TCCC";src:url("/assets/fonts/TCCC-UnityText-Regular.ttf") format("truetype");font-weight:400;font-style:normal;font-display:swap}
@font-face{font-family:"TCCC";src:url("/assets/fonts/TCCC-UnityText-Bold.ttf") format("truetype");font-weight:700;font-style:normal;font-display:swap}
@font-face{font-family:"TCCC";src:url("/assets/fonts/TCCC-UnityHeadline-Regular.ttf") format("truetype");font-weight:500;font-style:normal;font-display:swap}
@font-face{font-family:"TCCC";src:url("/assets/fonts/TCCC-UnityHeadline-Bold.ttf") format("truetype");font-weight:800;font-style:normal;font-display:swap}
@font-face{font-family:"TCCC";src:url("/assets/fonts/TCCC-UnityHeadline-Black.ttf") format("truetype");font-weight:900;font-style:normal;font-display:swap}
html,body,body *{font-family:"TCCC",Arial,sans-serif!important}
.user-logout-form{position:absolute;right:22px;top:50%;transform:translateY(-50%);margin:0}.user-logout-form button{border:1px solid #fff;background:transparent;color:#fff;border-radius:999px;padding:8px 14px;font-family:inherit;font-weight:800;cursor:pointer}.user-logout-form button:hover{background:#fff;color:#e41e2b}
</style>
<title>Lal Mejhe Adda | Book Your Slot</title>
<link rel="stylesheet" href="/assets/style-modern-v3.css?v=100.0.0">
<link rel="stylesheet" href="/assets/campaign.css?v=100.0.0">
<link rel="stylesheet" href="/assets/single-page-v82.css?v=100.0.0">
<link rel="stylesheet" href="/assets/page-rebuild-v95.css?v=102.5.3">
<?php if (basename($_SERVER['SCRIPT_NAME']) !== 'index.php'): ?><script src="assets/app.js" defer></script><?php endif; ?>
</head>
<body class="<?php echo basename($_SERVER['SCRIPT_NAME'])==='location.php' ? 'page-location' : (basename($_SERVER['SCRIPT_NAME'])==='index.php' ? 'page-index-v94' : ''); ?>">
<header class="site-header-v94 global-header-v94">
  <a class="brandLogo" href="/index.php" aria-label="Lal Mejhe Adda"><picture><source srcset="/assets/logo.svg?v=100" type="image/svg+xml"><img src="/assets/logo.png?v=100" alt="Lal Mejhe Adda"></picture></a>
<?php if (!empty($_SESSION['user_authenticated'])): ?><form class="user-logout-form" method="post" action="/logout.php"><input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']??''); ?>"><button type="submit">LOGOUT</button></form><?php endif; ?>
</header>
