<?php
require_once __DIR__ . '/../backend/bootstrap.php';
require_post();
$data = input_json();
$email = filter_var($data['email'] ?? ($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
if (!$email) json_response(['success'=>false,'message'=>'Please enter a valid email address.'], 422);
$stmt = db()->prepare('INSERT INTO newsletter_subscribers (email, ip_address) VALUES (?, ?) ON DUPLICATE KEY UPDATE email = VALUES(email)');
$stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? null]);
json_response(['success'=>true,'message'=>'Thank you. Your subscription has been received.']);
