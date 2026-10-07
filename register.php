<?php
// Legacy registration endpoint disabled. Customer registration must use the
// OTP + CSRF-protected save_signup.php workflow.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo json_encode(['ok'=>false,'message'=>'This registration endpoint is no longer available. Please use the secure sign-up flow.']);
exit;
