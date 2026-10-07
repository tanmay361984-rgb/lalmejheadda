<?php
include 'header.php';
$code = $_GET['code'] ?? '';
$booking = null;
if ($code !== '') {
    try {
        $q = $pdo->prepare('SELECT b.booking_code,b.name,COALESCE(b.mobile,b.whatsapp) AS mobile,b.seats,l.name AS location_name,s.slot_date,s.slot_time FROM bookings b JOIN locations l ON l.id=b.location_id JOIN slots s ON s.id=b.slot_id WHERE b.booking_code=? LIMIT 1');
        $q->execute([$code]);
        $booking = $q->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}
?>
<section class="success">
  <div>
    <span class="eyebrow">BOOKING CONFIRMED</span>
    <h1>Your Experience is Booked!</h1>
    <p>Your unique booking code</p>
    <h2><?php echo htmlspecialchars($code); ?></h2>
    <p>Please save this code for your booking reference.</p>

    <p><strong>Your booking is confirmed.</strong><br>Keep your booking code for your visit.</p>

    <a class="btn" href="index.php">BACK TO HOME →</a>
  </div>
</section>
<?php if ($booking): ?>
<script>
localStorage.setItem('bookingConfirmed', <?=json_encode($booking['booking_code'])?>);
localStorage.setItem('lastBookingDetails', <?=json_encode([
  'name'=>$booking['name'],
  'location'=>$booking['location_name'],
  'date'=>date('d M Y', strtotime($booking['slot_date'])),
  'time'=>$booking['slot_time'],
  'seats'=>'Seats: '.$booking['seats']
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>);
</script>
<?php endif; ?>
<?php include 'footer.php'; ?>
