<?php
// Copy this file to config.php on the server and edit these values.
// For production, use strong database credentials and do not share config.php.

define('APP_NAME', 'SME for Esan Central 2027');
define('APP_URL', 'https://sme2027edha.com');
define('APP_ENV', 'production');
define('SETUP_KEY', 'CHANGE_THIS_SETUP_KEY_BEFORE_RUNNING_SETUP');

define('DB_HOST', 'localhost');
define('DB_NAME', 'vgzhhnot_sme_campaign');
define('DB_USER', 'vgzhhnot_smeuser');
define('DB_PASS', '1234456');
define('DB_CHARSET', 'utf8mb4');

define('MAX_UPLOAD_BYTES', 8 * 1024 * 1024);
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('SITE_EMAIL', 'talktosme@gmail.com');

define('CAMPAIGN_NAME', 'SME for Esan Central 2027');
define('CAMPAIGN_PHONE', '0807 943 6049');

// Email uses PHP mail(). Some free hosts restrict this, so confirm with your host.
define('ENABLE_EMAIL_NOTIFICATIONS', false);

// SMS requires a real SMS provider API. Leave disabled until API details are added.
define('ENABLE_SMS_NOTIFICATIONS', false);
define('SMS_API_URL', '');
define('SMS_API_TOKEN', '');
define('SMS_API_SENDER', 'SME2027');

// Payment/SMS settings are now kept in backend/payment_sms_config.php.
// Do not put Paystack secret keys or Twilio auth tokens in public HTML or JavaScript.
