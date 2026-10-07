<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
include 'header.php';
$slug=$_GET['slug']??'';
$st=$pdo->prepare("SELECT * FROM locations WHERE slug=? AND active=1");$st->execute([$slug]);$loc=$st->fetch(PDO::FETCH_ASSOC);if(!$loc)die('Location not found');
$slots=$pdo->prepare("SELECT * FROM slots WHERE location_id=? AND active=1 AND slot_date>=CURDATE() ORDER BY slot_date, STR_TO_DATE(SUBSTRING_INDEX(slot_time, ' - ', 1), '%h:%i %p')");$slots->execute([$loc['id']]);$slots=$slots->fetchAll(PDO::FETCH_ASSOC);
$banner=$loc['banner']?:'assets/kolkata-hero.png';
$mapSrc=$loc['map_embed']?:'https://www.google.com/maps?q='.urlencode($loc['name'].', Kolkata').'&z=15&output=embed';
$dir=$loc['direction_url']?:'https://www.google.com/maps/dir/?api=1&destination='.urlencode($loc['name'].', Kolkata');
?>
<style>
/* LOCATION PAGE COCA-COLA BRANDING — intentionally shown */
.kx-location-brand{display:flex;flex-direction:column;align-items:flex-start;gap:8px;margin-bottom:22px}
.kx-location-brand img{display:block;width:220px;max-width:48vw;height:auto;max-height:72px;object-fit:contain;object-position:left center}
.kx-location-brand-note{margin:0;color:#fff;font-size:15px;line-height:1.3;font-weight:700;letter-spacing:.1px;text-shadow:0 2px 8px rgba(0,0,0,.45)}
@media(max-width:700px){.kx-location-brand img{width:180px;max-width:60vw}.kx-location-brand-note{font-size:13px}}
</style>
<style>
/* Location booking V10 - inline so it works even if external CSS was not overwritten */
.location-booking-v10{font-family:"TCCC"!important;background:#fff;color:#151515;padding:92px 6vw 110px}.location-booking-v10 *{font-family:"TCCC"!important}.lb-wrap{max-width:1180px;margin:auto}.lb-head{text-align:center;margin-bottom:48px}.lb-kicker{display:inline-block;color:#e41e2b;font-size:12px;font-weight:900;letter-spacing:2.5px;text-transform:uppercase;margin-bottom:13px}.lb-head h2{font-size:clamp(42px,5.4vw,72px);line-height:1;margin:0 0 17px;letter-spacing:-2px}.lb-head p{font-size:17px;color:#70757b;margin:0}.lb-flow{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:25px}.lb-step{border:1px solid #e8e8e8;border-radius:26px;padding:30px;background:#fff;box-shadow:0 15px 45px rgba(0,0,0,.055)}.lb-step.active{border-color:#e41e2b;box-shadow:0 18px 45px rgba(228,30,43,.10)}.lb-step-top{display:flex;align-items:center;gap:14px;margin-bottom:22px}.lb-num{width:52px;height:52px;border-radius:15px;background:#e41e2b;color:#fff;display:grid;place-items:center;font-size:16px;font-weight:900;flex:0 0 52px}.lb-title{font-size:21px;font-weight:900;line-height:1.15}.lb-sub{font-size:13px;color:#7c8187;margin-top:4px}.lb-date-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(105px,1fr));gap:13px}.lb-date{min-height:120px;border:2px solid #e41e2b;background:#e41e2b;color:#fff;border-radius:19px;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:13px;transition:.22s;box-shadow:0 9px 22px rgba(228,30,43,.15)}.lb-date:hover{transform:translateY(-4px);background:#c91522;border-color:#c91522}.lb-date .dow{font-size:12px;font-weight:900;letter-spacing:1.4px}.lb-date .day{font-size:40px;line-height:1;font-weight:900}.lb-date .mon{font-size:12px;font-weight:800}.lb-date.selected{background:#fff;color:#e41e2b;box-shadow:0 12px 28px rgba(228,30,43,.18)}.lb-date.selected .dow,.lb-date.selected .day,.lb-date.selected .mon{color:#e41e2b}.lb-select-wrap{position:relative}.lb-select{width:100%;height:76px;padding:0 58px 0 58px;border:2px solid #dedede;border-radius:20px;background:#fff;color:#151515;font-size:18px;font-weight:800;appearance:none;-webkit-appearance:none;outline:none;cursor:pointer;box-shadow:0 9px 25px rgba(0,0,0,.05)}.lb-select:disabled{background:#f7f7f7;color:#999;cursor:not-allowed}.lb-select:focus,.lb-select:hover:not(:disabled){border-color:#e41e2b;box-shadow:0 0 0 5px rgba(228,30,43,.09)}.lb-clock{position:absolute;left:20px;top:50%;transform:translateY(-50%);font-size:27px;color:#e41e2b;pointer-events:none}.lb-arrow{position:absolute;right:21px;top:50%;transform:translateY(-55%);font-size:29px;font-weight:900;color:#e41e2b;pointer-events:none}.lb-summary{border-radius:16px;background:#fff5f6;border:1px solid #ffd8dc;color:#791922;padding:17px 20px;font-size:14px;font-weight:800;margin:0 0 32px}.lb-seats{padding:34px;border:1px solid #e8e8e8;border-radius:26px;background:#fff;box-shadow:0 15px 45px rgba(0,0,0,.055)}.lb-seat-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:20px}.lb-seat-head h3{font-size:29px;margin:0}.lb-seat-head h3 small{font-size:13px;color:#777;font-weight:600;margin-left:8px}.lb-legend{color:#70757b;font-size:13px;white-space:nowrap}.lb-legend i{display:inline-block;width:10px;height:10px;border-radius:50%;margin:0 5px 0 14px}.lb-legend i:first-child{margin-left:0;background:#e3e3e3}.lb-legend .sel{background:#e41e2b}.lb-legend .book{background:#a5a5a5}.lb-screen{max-width:760px;margin:0 auto 35px;background:#e41e2b;color:#fff;border-radius:13px;padding:15px;text-align:center;font-size:12px;font-weight:900;letter-spacing:3px}.lb-seat-grid{display:grid;grid-template-columns:repeat(5,minmax(55px,76px));justify-content:center;gap:14px;min-height:90px}.lb-seat{height:64px;border:2px solid #e3e3e3;border-radius:16px;background:#fff;color:#222;font-size:15px;font-weight:900;cursor:pointer;transition:.18s}.lb-seat:hover{transform:translateY(-3px);border-color:#e41e2b}.lb-seat.selected{background:#e41e2b;border-color:#e41e2b;color:#fff}.lb-seat.booked,.lb-seat:disabled{background:#ececec;border-color:#ececec;color:#aaa;text-decoration:line-through;cursor:not-allowed}.lb-seat-note{text-align:center;color:#777;font-size:14px;margin:25px 0}.lb-submit{display:block;width:min(100%,360px);height:62px;margin:0 auto;background:#e41e2b;color:#fff;border:0;border-radius:16px;font-size:16px;font-weight:900;letter-spacing:.3px;box-shadow:0 14px 30px rgba(228,30,43,.22);cursor:pointer}.lb-submit:hover{background:#c91522;transform:translateY(-2px)}
@media(max-width:850px){.lb-flow{grid-template-columns:1fr}.lb-seat-head{align-items:flex-start;flex-direction:column}.lb-legend{white-space:normal}.lb-date-grid{grid-template-columns:repeat(4,1fr)}}@media(max-width:560px){.location-booking-v10{padding:65px 16px 80px}.lb-head h2{font-size:42px;letter-spacing:-1px}.lb-step,.lb-seats{padding:21px;border-radius:20px}.lb-date-grid{grid-template-columns:repeat(2,1fr)}.lb-date{min-height:106px}.lb-date .day{font-size:34px}.lb-select{height:70px;font-size:16px}.lb-seat-grid{grid-template-columns:repeat(3,70px);gap:10px}.lb-seat{height:60px}}
</style>

<style>
/* Location banner restored — V12 */
.kx-location-hero{
  position:relative;min-height:560px;display:flex;align-items:flex-end;overflow:hidden;
  color:#fff;background:#111;
}
.kx-location-hero .kx-hero-image{
  position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;
}
.kx-location-hero:after{
  content:"";position:absolute;inset:0;
  background:linear-gradient(90deg,rgba(0,0,0,.82) 0%,rgba(0,0,0,.55) 42%,rgba(0,0,0,.15) 100%),
             linear-gradient(0deg,rgba(0,0,0,.55),transparent 55%);
}
.kx-location-hero-inner{
  position:relative;z-index:2;width:min(1180px,90%);margin:0 auto;padding:100px 0 70px;
}
.kx-location-kicker{
  display:inline-flex;align-items:center;padding:8px 14px;border-radius:999px;
  background:#e41e2b;color:#fff;font-size:12px;font-weight:800;letter-spacing:2px;text-transform:uppercase;
}
.kx-location-hero h1{
  margin:18px 0 14px;color:#fff;font-size:clamp(52px,7vw,92px);line-height:.98;letter-spacing:-2px;
}
.kx-location-hero p{max-width:650px;margin:0;color:#f4f4f4;font-size:19px;line-height:1.65}
.kx-location-meta{display:flex;flex-wrap:wrap;gap:12px;margin-top:26px}
.kx-location-meta span{
  padding:11px 16px;border:1px solid rgba(255,255,255,.22);border-radius:999px;
  background:rgba(255,255,255,.10);backdrop-filter:blur(8px);font-size:13px;font-weight:700;
}
.kx-location-mapbar{background:#fff;padding:70px 6vw}
.kx-map-layout{width:min(1180px,100%);margin:auto;display:grid;grid-template-columns:.78fr 1.22fr;gap:32px;align-items:stretch}
.kx-map-copy{padding:25px 5px;display:flex;flex-direction:column;justify-content:center}
.kx-map-copy .mini{color:#e41e2b;font-size:12px;font-weight:900;letter-spacing:2px;text-transform:uppercase}
.kx-map-copy h2{font-size:clamp(35px,4vw,54px);line-height:1.05;margin:13px 0}
.kx-map-copy p{color:#666;line-height:1.7;font-size:16px}
.kx-address{font-weight:800;margin:8px 0 24px}
.kx-direction{
 display:inline-flex;width:max-content;align-items:center;gap:10px;background:#e41e2b;color:#fff!important;
 padding:16px 22px;border-radius:13px;font-weight:800;box-shadow:0 12px 25px rgba(228,30,43,.20);
}
.kx-map-frame{min-height:390px;border-radius:24px;overflow:hidden;border:1px solid #eee;box-shadow:0 18px 50px rgba(0,0,0,.10)}
.kx-map-frame iframe{width:100%;height:100%;min-height:390px;border:0;display:block}
@media(max-width:850px){
 .kx-location-hero{min-height:500px}.kx-location-hero-inner{padding:80px 0 55px}
 .kx-map-layout{grid-template-columns:1fr}.kx-map-frame,.kx-map-frame iframe{min-height:340px}
}
</style>

<section class="kx-location-hero">
  <img class="kx-hero-image" src="<?php echo htmlspecialchars($banner); ?>" alt="<?php echo htmlspecialchars($loc['name']); ?>">
  <div class="kx-location-hero-inner">
    <div class="kx-location-brand" aria-label="Coca-Cola">
      <img src="/assets/coca-cola-white.png?v=34" width="220" height="72" alt="Coca-Cola" loading="eager" decoding="async">
      <p class="kx-location-brand-note">Experience Lal Mejhe This Durga Puja*</p>
    </div>
    <span class="kx-location-kicker">Lal Mejhe Adda</span>
    <h1><?php echo htmlspecialchars($loc['name']); ?></h1>
    <p><?php echo htmlspecialchars($loc['description'] ?: 'Discover an unforgettable Kolkata experience at this iconic destination.'); ?></p>
    <div class="kx-location-meta">
      <span>📍 <?php echo htmlspecialchars($loc['address'] ?: ($loc['name'].', Kolkata')); ?></span>
      <span>⏱ 1-Hour Experience</span>
      <span>🎟 Select Your Seats</span>
    </div>
  </div>
</section>

<section class="kx-location-mapbar">
  <div class="kx-map-layout">
    <div class="kx-map-copy">
      <span class="mini">Plan Your Visit</span>
      <h2><?php echo htmlspecialchars($loc['name']); ?><br>Map & Direction</h2>
      <p>Find the experience location easily and get directions directly from Google Maps.</p>
      <div class="kx-address">📍 <?php echo htmlspecialchars($loc['address'] ?: ($loc['name'].', Kolkata')); ?></div>
      <a class="kx-direction" href="<?php echo htmlspecialchars($dir); ?>" target="_blank" rel="noopener">GET GOOGLE MAP DIRECTIONS →</a>
    </div>
    <div class="kx-map-frame">
      <iframe src="<?php echo htmlspecialchars($mapSrc); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="<?php echo htmlspecialchars($loc['name']); ?> Google Map"></iframe>
    </div>
  </div>
</section>

<section class="location-booking-v10">
<div class="lb-wrap">
<div class="lb-head"><span class="lb-kicker">Reserve Your Visit</span><h2>Choose Your Experience</h2><p>Select a date first, then choose your 1-hour time slot and seats.</p></div>
<?php if(!$slots): ?><div class="lb-summary">No booking slots are available yet. Please check back soon.</div><?php else: ?>
<form method="post" action="book.php" id="bookingForm">
<input type="hidden" name="location_id" value="<?php echo (int)$loc['id']; ?>">
<input type="hidden" name="guest_name" id="guest_name"><input type="hidden" name="guest_mobile" id="guest_mobile"><input type="hidden" name="seats" id="seatsInput"><input type="hidden" name="slot_date" id="slotDateInput">
<div class="lb-flow">
<div class="lb-step active"><div class="lb-step-top"><div class="lb-num">01</div><div><div class="lb-title">Select Date</div><div class="lb-sub">Choose your preferred visit date</div></div></div>
<div class="lb-date-grid" id="dateButtons">
<?php $dates=[];foreach($slots as $s){$d=$s['slot_date'];if(!isset($dates[$d]))$dates[$d]=true;}foreach(array_keys($dates) as $d): ?><button type="button" class="lb-date" data-date="<?php echo e($d); ?>"><span class="dow"><?php echo e(date('D',strtotime($d))); ?></span><span class="day"><?php echo e(date('d',strtotime($d))); ?></span><span class="mon"><?php echo e(date('M',strtotime($d))); ?></span></button><?php endforeach; ?>
</div></div>
<div class="lb-step" id="slotStep"><div class="lb-step-top"><div class="lb-num">02</div><div><div class="lb-title">Select 1-Hour Slot</div><div class="lb-sub">Choose a time between 11 AM and 10 PM</div></div></div>
<div class="lb-select-wrap"><span class="lb-clock">◷</span><select class="lb-select" id="slotSelect" name="slot_id" required disabled><option value="">Select date first</option></select><span class="lb-arrow">⌄</span></div></div>
</div>
<div class="lb-summary" id="selectionSummary">Select a date to see available time slots.</div>
<div class="lb-seats"><div class="lb-seat-head"><h3>03. Select Seats <small id="seatMaxLabel">Choose a slot first</small></h3><div class="lb-legend"><i></i>Available <i class="sel"></i>Selected <i class="book"></i>Booked</div></div><div class="lb-screen">EXPERIENCE AREA</div><div class="lb-seat-grid" id="seatGrid"></div><p class="lb-seat-note" id="seatInfo">Select a date and slot to see available seats.</p><button class="lb-submit" type="submit">CONFIRM MY BOOKING →</button></div>
</form>
<?php endif; ?></div></section>
<script>
(function(){
const slotData=<?php echo json_encode(array_map(function($s){return ['id'=>(int)$s['id'],'date'=>$s['slot_date'],'time'=>$s['slot_time'],'max'=>(int)$s['max_seats']];},$slots),JSON_UNESCAPED_SLASHES); ?>;
const form=document.getElementById('bookingForm'),slotSelect=document.getElementById('slotSelect'),seatGrid=document.getElementById('seatGrid'),seatInfo=document.getElementById('seatInfo'),summary=document.getElementById('selectionSummary'),seatMaxLabel=document.getElementById('seatMaxLabel'),slotDateInput=document.getElementById('slotDateInput'),seatsInput=document.getElementById('seatsInput');
let chosenDate='';
function seats(){return Array.from(seatGrid.querySelectorAll('.lb-seat.selected')).map(b=>b.dataset.seat)}
function updateInput(){seatsInput.value=seats().join(',')}
function buildSeats(max){seatGrid.innerHTML='';for(let i=1;i<=max;i++){let b=document.createElement('button');b.type='button';b.className='lb-seat';b.dataset.seat=i;b.textContent=i;b.addEventListener('click',()=>{if(b.disabled)return;b.classList.toggle('selected');updateInput();let n=seats();seatInfo.textContent=n.length?'Selected seat'+(n.length>1?'s':'')+': '+n.join(', '):'Select one or more available seats.'});seatGrid.appendChild(b)}seatMaxLabel.textContent='Maximum '+max+' seats';seatInfo.textContent='Loading seat availability…'}
async function loadBooked(){const slot=slotSelect.value;if(!slot)return;try{const r=await fetch('get_booked_seats.php?slot_id='+encodeURIComponent(slot),{cache:'no-store'});const data=await r.json();(data.seats||[]).forEach(n=>{const b=seatGrid.querySelector('[data-seat="'+n+'"]');if(b){b.disabled=true;b.classList.add('booked')}});seatInfo.textContent=seats().length?'Selected seats: '+seats().join(', '):'Select one or more available seats.'}catch(e){seatInfo.textContent='Seats are ready. Select your seats.'}}
function chooseDate(date){chosenDate=date;slotDateInput.value=date;document.querySelectorAll('.lb-date').forEach(b=>b.classList.toggle('selected',b.dataset.date===date));slotSelect.innerHTML='<option value="">Select a time slot</option>';slotSelect.disabled=false;seatGrid.innerHTML='';seatsInput.value='';seatMaxLabel.textContent='Choose a slot first';seatInfo.textContent='Select a slot to see available seats.';const rows=slotData.filter(x=>x.date===date);rows.forEach(x=>{const o=document.createElement('option');o.value=x.id;o.dataset.max=x.max;o.textContent=x.time;slotSelect.appendChild(o)});summary.textContent=rows.length+' one-hour slot'+(rows.length===1?'':'s')+' available for '+new Date(date+'T12:00:00').toLocaleDateString('en-IN',{weekday:'short',day:'2-digit',month:'short',year:'numeric'})+'.';document.getElementById('slotStep').classList.add('active');slotSelect.focus()}
document.querySelectorAll('.lb-date').forEach(b=>b.addEventListener('click',()=>chooseDate(b.dataset.date)));
slotSelect&&slotSelect.addEventListener('change',()=>{const o=slotSelect.options[slotSelect.selectedIndex];if(!slotSelect.value){seatGrid.innerHTML='';seatInfo.textContent='Select a slot to see available seats.';return}buildSeats(parseInt(o.dataset.max||0,10));summary.textContent='Date: '+new Date(chosenDate+'T12:00:00').toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'})+'  •  Slot: '+o.textContent;loadBooked()});
document.addEventListener('DOMContentLoaded',()=>{const g=JSON.parse(localStorage.getItem('guest')||'{}');const n=document.getElementById('guest_name'),m=document.getElementById('guest_mobile');if(n)n.value=g.name||'';if(m)m.value=g.mobile||''});
form&&form.addEventListener('submit',e=>{if(!chosenDate){e.preventDefault();alert('Please select a date first.');return}if(!slotSelect.value){e.preventDefault();alert('Please select a time slot.');return}if(!seatsInput.value){e.preventDefault();alert('Please select at least one seat.');return}});
})();
</script>
<?php include 'footer.php'; ?>
