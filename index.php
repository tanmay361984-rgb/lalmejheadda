<?php
require '../config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if (empty($_SESSION['admin'])) { header('Location:login.php'); exit; }

// Admin CSRF protection: one unpredictable token per authenticated session.
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}
$adminCsrfToken = $_SESSION['admin_csrf_token'];

$msg=''; $error='';

// Compatibility migration: older Hostinger databases may not have the
// hotspot columns. Add them automatically before any location INSERT/UPDATE
// or SELECT so the admin works without requiring a full database re-import.
try {
    $locationColumns = [];
    $cols = $pdo->query('SHOW COLUMNS FROM locations')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($cols as $c) $locationColumns[$c] = true;
    if (!isset($locationColumns['hotspot_left'])) {
        $pdo->exec('ALTER TABLE locations ADD COLUMN hotspot_left DECIMAL(5,2) NULL AFTER seat_quantity');
    }
    if (!isset($locationColumns['hotspot_top'])) {
        $pdo->exec('ALTER TABLE locations ADD COLUMN hotspot_top DECIMAL(5,2) NULL AFTER hotspot_left');
    }
} catch (Throwable $migrationError) {
    $error = 'Database setup error: '.$migrationError->getMessage();
}
function admin_upload_location_image($field, $old='') {
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return $old;
    $ext=strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext,['jpg','jpeg','png','webp'], true)) throw new Exception('Only JPG, JPEG, PNG or WEBP images are allowed.');
    if ($_FILES[$field]['size'] > 8*1024*1024) throw new Exception('Image must be smaller than 8 MB.');
    $tmp=$_FILES[$field]['tmp_name'];
    if (!is_uploaded_file($tmp)) throw new Exception('Invalid uploaded file.');
    $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($tmp);
    $allowed=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
    if (!isset($allowed[$ext]) || $mime!==$allowed[$ext]) throw new Exception('The uploaded file content does not match the selected image type.');
    if (@getimagesize($tmp)===false) throw new Exception('Uploaded file is not a valid image.');
    $dir=dirname(__DIR__).'/uploads'; if(!is_dir($dir)) mkdir($dir,0755,true);
    $name='loc_'.date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$ext;
    if(!move_uploaded_file($_FILES[$field]['tmp_name'],$dir.'/'.$name)) throw new Exception('Unable to upload image.');
    if ($old && str_starts_with($old,'uploads/') && is_file(dirname(__DIR__).'/'.$old)) @unlink(dirname(__DIR__).'/'.$old);
    return 'uploads/'.$name;
}

try {
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    if ($postedCsrf === '' || !hash_equals($adminCsrfToken, $postedCsrf)) {
        throw new RuntimeException('Security validation failed. Please refresh the admin page and try again.');
    }
    $action=$_POST['action']??'';

    if ($action==='location_create') {
        $name=trim($_POST['name']??''); $slug=strtolower(trim($_POST['slug']??'')); $seatQuantity=(int)($_POST['seat_quantity']??15); $hotspotLeft=trim($_POST['hotspot_left']??'')!==''?(float)$_POST['hotspot_left']:null; $hotspotTop=trim($_POST['hotspot_top']??'')!==''?(float)$_POST['hotspot_top']:null;
        if(!$name || !$slug) throw new Exception('Location name and slug are required.');
        if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)) throw new Exception('URL slug may contain only lowercase letters, numbers and hyphens.');
        if($seatQuantity<1 || $seatQuantity>100) throw new Exception('Seat quantity must be between 1 and 100.'); if($hotspotLeft!==null && ($hotspotLeft<3 || $hotspotLeft>97)) throw new Exception('Hotspot Left must be between 3 and 97.'); if($hotspotTop!==null && ($hotspotTop<3 || $hotspotTop>97)) throw new Exception('Hotspot Top must be between 3 and 97.');
        $chk=$pdo->prepare('SELECT id,name FROM locations WHERE slug=? LIMIT 1'); $chk->execute([$slug]); $duplicate=$chk->fetch(); if($duplicate) throw new Exception('Location already exists with this URL slug: '.($duplicate['name']??'').'. Click Modify in Manage Locations to edit it, or choose a different slug.');
        $banner=admin_upload_location_image('banner','');
        $pdo->prepare("INSERT INTO locations(name,slug,banner,description,address,map_embed,direction_url,seat_quantity,hotspot_left,hotspot_top,active) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$name,$slug,$banner,trim($_POST['description']??''),trim($_POST['address']??''),trim($_POST['map_embed']??''),trim($_POST['direction_url']??''),$seatQuantity,$hotspotLeft,$hotspotTop,(int)($_POST['active']??1)]);
        $msg='Location created successfully.';
    }

    if ($action==='location_update') {
        $id=(int)($_POST['id']??0); $oldStmt=$pdo->prepare('SELECT * FROM locations WHERE id=?'); $oldStmt->execute([$id]); $old=$oldStmt->fetch();
        if(!$old) throw new Exception('Location not found.');
        $name=trim($_POST['name']??''); $slug=strtolower(trim($_POST['slug']??'')); $seatQuantity=(int)($_POST['seat_quantity']??15); $hotspotLeft=trim($_POST['hotspot_left']??'')!==''?(float)$_POST['hotspot_left']:null; $hotspotTop=trim($_POST['hotspot_top']??'')!==''?(float)$_POST['hotspot_top']:null;
        if(!$name || !$slug) throw new Exception('Location name and slug are required.');
        if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)) throw new Exception('URL slug may contain only lowercase letters, numbers and hyphens.');
        if($seatQuantity<1 || $seatQuantity>100) throw new Exception('Seat quantity must be between 1 and 100.'); if($hotspotLeft!==null && ($hotspotLeft<3 || $hotspotLeft>97)) throw new Exception('Hotspot Left must be between 3 and 97.'); if($hotspotTop!==null && ($hotspotTop<3 || $hotspotTop>97)) throw new Exception('Hotspot Top must be between 3 and 97.');
        $chk=$pdo->prepare('SELECT id FROM locations WHERE slug=? AND id<>?'); $chk->execute([$slug,$id]); if($chk->fetch()) throw new Exception('This URL slug is already used by another location. Please choose a unique slug.');
        $banner=admin_upload_location_image('banner',$old['banner']);
        $pdo->prepare("UPDATE locations SET name=?,slug=?,banner=?,description=?,address=?,map_embed=?,direction_url=?,seat_quantity=?,hotspot_left=?,hotspot_top=?,active=? WHERE id=?")
            ->execute([$name,$slug,$banner,trim($_POST['description']??''),trim($_POST['address']??''),trim($_POST['map_embed']??''),trim($_POST['direction_url']??''),$seatQuantity,$hotspotLeft,$hotspotTop,(int)($_POST['active']??1),$id]);
        $pdo->prepare("UPDATE slots s SET s.max_seats=? WHERE s.location_id=? AND s.slot_date>=CURDATE() AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.slot_id=s.id AND b.status='confirmed')")->execute([$seatQuantity,$id]); $msg='Location updated successfully.';
    }

    if ($action==='location_delete') {
        $id=(int)($_POST['id']??0); $st=$pdo->prepare('SELECT banner FROM locations WHERE id=?'); $st->execute([$id]); $old=$st->fetch();
        $pdo->prepare('DELETE FROM locations WHERE id=?')->execute([$id]);
        if($old && !empty($old['banner']) && str_starts_with($old['banner'],'uploads/') && is_file(dirname(__DIR__).'/'.$old['banner'])) @unlink(dirname(__DIR__).'/'.$old['banner']);
        $msg='Location removed successfully. Related slots and bookings were also removed.';
    }

    if ($action==='slot') {
        $pdo->prepare("INSERT INTO slots(location_id,slot_date,slot_time,max_seats) SELECT ?,?,?,seat_quantity FROM locations WHERE id=?")
            ->execute([(int)$_POST['location_id'],$_POST['slot_date'],trim($_POST['slot_time']),(int)$_POST['location_id']]);
        $msg='1-hour slot created successfully.';
    }
}
} catch (Throwable $e) {
    $error = $e instanceof PDOException ? ('Database error: '.($e->errorInfo[2] ?? $e->getMessage())) : $e->getMessage();
}

$edit=null;
if(isset($_GET['edit']) && !isset($_GET['new'])) { $st=$pdo->prepare('SELECT * FROM locations WHERE id=?'); $st->execute([(int)$_GET['edit']]); $edit=$st->fetch(); }
$locs=$pdo->query('SELECT * FROM locations ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$bookings=$pdo->query("SELECT b.*,l.name location,s.slot_date,s.slot_time FROM bookings b LEFT JOIN locations l ON l.id=b.location_id LEFT JOIN slots s ON s.id=b.slot_id ORDER BY b.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
$pdo->exec("CREATE TABLE IF NOT EXISTS registrations (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, mobile VARCHAR(20) NULL, whatsapp VARCHAR(30) NULL, verified_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$registrations=$pdo->query("SELECT r.*, COUNT(DISTINCT b.id) booking_count, MAX(b.created_at) last_booking FROM registrations r LEFT JOIN bookings b ON COALESCE(b.mobile,b.whatsapp)=r.mobile GROUP BY r.id ORDER BY r.created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Booking Admin | Lal Mejhe Adda</title><link rel="stylesheet" href="../assets/style-modern-v3.css?v=4.0.0">
<style>
:root{--navy:#e41e2b;--ink:#16243a;--gold:#e41e2b;--line:#e6eaf0;--muted:#687487;--bg:#f5f7fb;--danger:#d9363e;--green:#138a5b}*{box-sizing:border-box}body{margin:0;font-family:"TCCC"!important;background:var(--bg);color:var(--ink)}.top{background:#e41e2b;color:#fff;padding:20px 5%;display:flex;align-items:center;justify-content:space-between;gap:15px}.top b{font-size:21px;letter-spacing:1px}.top small{display:block;color:#aeb8c6;margin-top:4px}.top a{color:#121a26;background:var(--gold);padding:11px 17px;border-radius:10px;font-weight:800;text-decoration:none}.navAdmin{display:flex;gap:10px;align-items:center}.top .regLink{background:#fff;color:#14243a;border:0;cursor:pointer;font:inherit}.wrap{max-width:1320px;margin:auto;padding:32px 22px 70px}.alert{padding:14px 17px;border-radius:12px;margin-bottom:20px}.success{background:#e9f8f1;color:#096941}.error{background:#fff0f1;color:#a71f28}.grid{display:grid;grid-template-columns:1.15fr .85fr;gap:24px}.card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:25px;box-shadow:0 12px 35px rgba(16,30,55,.06)}h2{margin:0 0 18px;font-size:24px}.sub{color:var(--muted);margin:-8px 0 18px;font-size:14px}.field{margin:12px 0}.field label{display:block;font-weight:700;font-size:13px;margin:0 0 7px}input,textarea,select{display:block;width:100%;padding:13px 14px;border:1px solid #d8dee8;border-radius:10px;background:#fbfcfe;color:#172235;font-size:14px}textarea{min-height:90px;resize:vertical}input:focus,textarea:focus,select:focus{outline:2px solid #ffe39a;border-color:var(--gold)}button{border:0;border-radius:10px;padding:13px 17px;background:var(--gold);font-weight:800;cursor:pointer;color:#18202a}.secondary{background:#eef2f7}.danger{background:#fff0f0;color:var(--danger)}.formGrid{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}.full{grid-column:1/-1}.currentImg{width:100%;max-height:150px;object-fit:cover;border-radius:12px;margin:8px 0;border:1px solid var(--line)}.toolbar{display:flex;justify-content:space-between;align-items:center;margin:38px 0 14px;gap:12px}.count{background:#eaf0f7;padding:7px 11px;border-radius:999px;font-size:13px;font-weight:700}.tableWrap{overflow:auto;background:#fff;border:1px solid var(--line);border-radius:18px}table{width:100%;border-collapse:collapse;min-width:760px}th,td{padding:14px 16px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px}th{background:#f9fafc;font-size:12px;text-transform:uppercase;color:#657184;letter-spacing:.5px}.thumb{width:66px;height:45px;border-radius:8px;object-fit:cover;background:#edf0f4}.status{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800}.on{background:#e8f7ef;color:#087a4c}.off{background:#fff1f1;color:#c22933}.actions{display:flex;gap:7px}.actions a,.actions button{padding:8px 10px;border-radius:8px;text-decoration:none;font-size:12px}.actions a{background:#eef2f7;color:#1b2b42;font-weight:800}.empty{padding:25px;color:var(--muted)}@media(max-width:900px){.grid{grid-template-columns:1fr}.formGrid{grid-template-columns:1fr}.full{grid-column:auto}.wrap{padding:22px 14px}.top{padding:17px 14px}.top b{font-size:16px}}
</style></head><body>
<header class="top"><div><b>LAL MEJHE ADDA</b><small>Booking Management Dashboard</small></div><div class="navAdmin"><form method="post" action="logout.php" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo e($adminCsrfToken);?>"><button type="submit" class="regLink">Logout</button></form><a href="?new=1" class="regLink">＋ Add Location</a><a href="registrations.php" class="regLink">Registration Data</a><a href="password.php" class="regLink">Change Password</a><a href="../index.php" target="_blank">View Website ↗</a></div></header>
<main class="wrap">
<?php if(isset($_GET['password_set'])): $msg='Admin password set successfully.'; endif;?><?php if($msg):?><div class="alert success"><?php echo e($msg);?></div><?php endif;?><?php if($error):?><div class="alert error"><?php echo e($error);?></div><?php endif;?>
<div class="grid">
<section class="card"><h2><?php echo $edit?'Modify Location':'Add New Location';?></h2><p class="sub">Manage image, description, Google map, direction link and visibility.</p>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo e($adminCsrfToken);?>"><input type="hidden" name="action" value="<?php echo $edit?'location_update':'location_create';?>"><?php if($edit):?><input type="hidden" name="id" value="<?php echo $edit['id'];?>"><?php endif;?>
<div class="formGrid">
<div class="field"><label>Location Name *</label><input name="name" value="<?php echo e($edit['name']??'');?>" placeholder="Victoria Memorial" required></div>
<div class="field"><label>URL Slug *</label><input name="slug" value="<?php echo e($edit['slug']??'');?>" placeholder="victoria-memorial" required></div>
<div class="field"><label>Seat Quantity *</label><input type="number" name="seat_quantity" min="1" max="100" value="<?php echo e($edit['seat_quantity']??15);?>" required></div><div class="field"><label>Hotspot Left %</label><input type="number" name="hotspot_left" min="3" max="97" step="0.1" value="<?php echo e($edit['hotspot_left']??'');?>" placeholder="Auto"></div><div class="field"><label>Hotspot Top %</label><input type="number" name="hotspot_top" min="3" max="97" step="0.1" value="<?php echo e($edit['hotspot_top']??'');?>" placeholder="Auto"></div><div class="field full"><small style="color:#687487">Leave hotspot positions blank for automatic placement on the home map.</small></div><div class="field full"><label>Experience Description</label><textarea name="description" placeholder="Write a short experience description..."><?php echo e($edit['description']??'');?></textarea></div>
<div class="field full"><label>Address / Map Location</label><input name="address" value="<?php echo e($edit['address']??'');?>" placeholder="1, Queens Way, Kolkata"></div>
<div class="field full"><label>Google Map Embed URL</label><input name="map_embed" value="<?php echo e($edit['map_embed']??'');?>" placeholder="https://www.google.com/maps?q=Victoria+Memorial+Kolkata&z=15&output=embed"><small style="color:#687487">Paste the iframe SRC URL, not the full iframe code.</small></div>
<div class="field full"><label>Google Maps Direction URL</label><input name="direction_url" value="<?php echo e($edit['direction_url']??'');?>" placeholder="https://www.google.com/maps/dir/?api=1&destination=... "></div>
<div class="field"><label>Location Image (JPG / PNG / WEBP)</label><?php if($edit&&!empty($edit['banner'])):?><img class="currentImg" src="../<?php echo e($edit['banner']);?>" alt="Current image"><?php endif;?><input type="file" name="banner" accept=".jpg,.jpeg,.png,.webp"></div>
<div class="field"><label>Website Visibility</label><select name="active"><option value="1" <?php echo (!$edit||$edit['active'])?'selected':'';?>>Active / Visible</option><option value="0" <?php echo ($edit&&!(int)$edit['active'])?'selected':'';?>>Hidden / Inactive</option></select></div>
</div><button type="submit"><?php echo $edit?'Save Location Changes':'Create Location';?></button><?php if($edit):?><a href="index.php" style="margin-left:8px;text-decoration:none;color:#526176;font-weight:700">Cancel Edit</a><?php else:?><a href="index.php?new=1" style="margin-left:10px;text-decoration:none;color:#526176;font-weight:700">Reset Form</a><?php endif;?></form></section>
<section class="card"><h2>Create 1-Hour Slot</h2><p class="sub">Each new slot automatically uses the seat quantity configured for its location.</p><form method="post"><input type="hidden" name="csrf_token" value="<?php echo e($adminCsrfToken);?>"><input type="hidden" name="action" value="slot"><div class="field"><label>Location</label><select name="location_id" required><?php foreach($locs as $l):?><option value="<?php echo $l['id'];?>"><?php echo e($l['name']);?></option><?php endforeach;?></select></div><div class="field"><label>Date</label><input type="date" name="slot_date" required></div><div class="field"><label>1-Hour Time</label><select name="slot_time" required><option value="">Select time</option><option value="11:00 AM - 12:00 PM">11:00 AM - 12:00 PM</option><option value="12:00 PM - 1:00 PM">12:00 PM - 1:00 PM</option><option value="1:00 PM - 2:00 PM">1:00 PM - 2:00 PM</option><option value="2:00 PM - 3:00 PM">2:00 PM - 3:00 PM</option><option value="3:00 PM - 4:00 PM">3:00 PM - 4:00 PM</option><option value="4:00 PM - 5:00 PM">4:00 PM - 5:00 PM</option><option value="5:00 PM - 6:00 PM">5:00 PM - 6:00 PM</option><option value="6:00 PM - 7:00 PM">6:00 PM - 7:00 PM</option><option value="7:00 PM - 8:00 PM">7:00 PM - 8:00 PM</option><option value="8:00 PM - 9:00 PM">8:00 PM - 9:00 PM</option><option value="9:00 PM - 10:00 PM">9:00 PM - 10:00 PM</option></select></div><button type="submit">Create Slot</button></form></section>
</div>
<div class="toolbar"><div><h2 style="margin:0">Manage Locations</h2><div class="sub" style="margin:5px 0 0">Modify or permanently remove any location.</div></div><span class="count"><?php echo count($locs);?> Locations</span></div>
<div class="tableWrap"><?php if(!$locs):?><div class="empty">No locations added yet.</div><?php else:?><table><tr><th>Image</th><th>Location</th><th>Slug</th><th>Seats</th><th>Hotspot</th><th>Map / Direction</th><th>Status</th><th>Actions</th></tr><?php foreach($locs as $l):?><tr><td><?php if($l['banner']):?><img class="thumb" src="../<?php echo e($l['banner']);?>" alt=""><?php endif;?></td><td><b><?php echo e($l['name']);?></b><br><small><?php echo e($l['address']);?></small></td><td><?php echo e($l['slug']);?></td><td><b><?php echo (int)$l['seat_quantity'];?></b></td><td><?php echo ($l['hotspot_left']!==null && $l['hotspot_top']!==null) ? e($l['hotspot_left'].'% / '.$l['hotspot_top'].'%') : 'Auto';?></td><td><?php echo $l['map_embed']?'✓ Map':'—';?> / <?php echo $l['direction_url']?'✓ Direction':'—';?></td><td><span class="status <?php echo $l['active']?'on':'off';?>"><?php echo $l['active']?'Active':'Hidden';?></span></td><td><div class="actions"><a href="?edit=<?php echo $l['id'];?>">Modify</a><form method="post" onsubmit="return confirm('Remove <?php echo e(addslashes($l['name']));?>? Related slots and bookings will also be removed.');"><input type="hidden" name="csrf_token" value="<?php echo e($adminCsrfToken);?>"><input type="hidden" name="action" value="location_delete"><input type="hidden" name="id" value="<?php echo $l['id'];?>"><button class="danger" type="submit">Remove</button></form></div></td></tr><?php endforeach;?></table><?php endif;?></div>
<div id="registration-data" class="toolbar"><div><h2 style="margin:0">Registration Data</h2><div class="sub" style="margin:5px 0 0">All visitors who verified their mobile number by SMS OTP before booking. <a href="registrations.php" style="color:#138a5b;font-weight:800">Open Full Registration Data →</a></div></div><span class="count"><?php echo count($registrations);?> Registered</span></div>
<div class="tableWrap"><table><tr><th>#</th><th>Name</th><th>Mobile Number</th><th>Email</th><th>Registered On</th><th>Bookings</th><th>Last Booking</th></tr><?php if(!$registrations):?><tr><td colspan="7" class="empty">No registration data yet.</td></tr><?php else:?><?php foreach($registrations as $r):?><tr><td><?php echo $r['id'];?></td><td><b><?php echo e($r['name']);?></b></td><td>+<?php echo e($r['mobile']);?></td><td><?php echo e($r['email']??'—');?></td><td><?php echo e($r['created_at']);?></td><td><?php echo (int)$r['booking_count'];?></td><td><?php echo e($r['last_booking']?:'—');?></td></tr><?php endforeach;?><?php endif;?></table></div>
<h2 style="margin:38px 0 14px">Live Booking History</h2><div class="tableWrap"><table><tr><th>Code</th><th>Guest</th><th>Mobile</th><th>Location</th><th>Slot</th><th>Seats</th><th>Status</th></tr><?php foreach($bookings as $b):?><tr><td><b><?php echo e($b['booking_code']);?></b></td><td><?php echo e($b['name']);?></td><td><?php echo e($b['mobile'] ?? $b['whatsapp']);?></td><td><?php echo e($b['location']);?></td><td><?php echo e(($b['slot_date']??'').' '.($b['slot_time']??''));?></td><td><?php echo e($b['seats']);?></td><td><?php echo e($b['status']);?></td></tr><?php endforeach;?></table></div>
</main></body></html>
