<?php
$booking_code = $booking_code ?? ($_GET['code'] ?? 'ADDA563B7F');
$location_name = $location_name ?? 'Kolkata Experience';
$slot_time = $slot_time ?? '11:00 AM – 12:00 PM';
$booking_date = $booking_date ?? date('d M Y');
$selected_seats = $selected_seats ?? 'Seat 01';
$whatsapp_url = $whatsapp_url ?? '#';
include __DIR__.'/header.php';
?>
<link rel="stylesheet" href="assets/booking-success-modern.css">
<main class="success-page"><section class="success-wrap"><div class="success-card">
<div class="success-top"><div class="success-icon">✓</div><div class="eyebrow">BOOKING CONFIRMED</div><h1>Your Experience is <span>Booked!</span></h1><p>Your slot has been successfully reserved. Keep your booking code safe for verification.</p></div>
<div class="booking-code-box"><div class="code-label"><span>YOUR UNIQUE BOOKING CODE</span><small>Show this code at the venue</small></div><div class="code-row"><strong id="bookingCode"><?php echo htmlspecialchars($booking_code); ?></strong><button class="copy-btn" onclick="copyBookingCode()">Copy Code</button></div></div>
<div class="booking-details">
<div class="detail-card"><b>📍</b><div><span>LOCATION</span><strong><?php echo htmlspecialchars($location_name); ?></strong></div></div>
<div class="detail-card"><b>🗓️</b><div><span>DATE</span><strong><?php echo htmlspecialchars($booking_date); ?></strong></div></div>
<div class="detail-card"><b>🕒</b><div><span>YOUR SLOT</span><strong><?php echo htmlspecialchars($slot_time); ?></strong></div></div>
<div class="detail-card"><b>💺</b><div><span>SELECTED SEAT</span><strong><?php echo htmlspecialchars(is_array($selected_seats)?implode(', ',$selected_seats):$selected_seats); ?></strong></div></div>
</div>
<div class="info-strip">✓ A confirmation message with your booking code can be sent through WhatsApp for easy reference.</div>
<div class="success-actions"><a class="btn wa" href="<?php echo htmlspecialchars($whatsapp_url); ?>">◉ Send Booking to WhatsApp</a><a class="btn home" href="index.php">Back to Home →</a></div>
<div class="help-text">Need help with your booking? <a href="index.php#contact">Contact our team</a></div>
</div></section></main>
<script>function copyBookingCode(){navigator.clipboard.writeText(document.getElementById('bookingCode').innerText);let b=document.querySelector('.copy-btn');b.innerText='Copied ✓';setTimeout(()=>b.innerText='Copy Code',1500)}</script>
<?php include __DIR__.'/footer.php'; ?>