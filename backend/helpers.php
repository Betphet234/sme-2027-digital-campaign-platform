<?php
function input_json(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function clean_string($value, int $max = 5000): string {
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}
function normalize_phone($phone): string {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if (substr($digits, 0, 3) === '234') return '0' . substr($digits, 3);
    return $digits;
}
function generate_reference(string $prefix): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $pdo = db();
    do {
        $code = '';
        for ($i=0; $i<6; $i++) $code .= $chars[random_int(0, strlen($chars)-1)];
        $ref = $prefix . '-2027-' . $code;
        $stmt = $pdo->prepare('SELECT id FROM submissions WHERE reference = ? LIMIT 1');
        $stmt->execute([$ref]);
    } while ($stmt->fetch());
    return $ref;
}
function dataset_from_type(string $type, string $store = ''): array {
    $map = [
        'opportunity' => ['applications','SME','Application Received'],
        'community_need' => ['needs','NEED','Received'],
        'volunteer' => ['volunteers','VOL','Received'],
        'supporter' => ['supporters','SUP','Received'],
        'contact_message' => ['messages','MSG','Received'],
        'donor_pledge' => ['donors','DON','Received'],
    ];
    if (isset($map[$type])) return $map[$type];
    $allowed = ['applications','needs','volunteers','supporters','messages','donors'];
    $dataset = in_array($store, $allowed, true) ? $store : 'messages';
    return [$dataset, 'MSG', 'Received'];
}
function first_value(array $data, array $keys): string {
    foreach ($keys as $key) {
        if (isset($data[$key]) && trim((string)$data[$key]) !== '') return clean_string($data[$key], 300);
    }
    return '';
}
function audit_log(string $action, ?string $targetType = null, ?string $targetReference = null, array $details = []): void {
    try {
        $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, target_type, target_reference, details_json, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$_SESSION['user_id'] ?? null, $action, $targetType, $targetReference, json_encode($details), $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {}
}
function save_uploaded_files(): array {
    if (empty($_FILES)) return [];
    $saved = [];
    $allowed = [
        'image/jpeg','image/png','image/webp','image/gif','application/pdf',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'video/mp4','video/quicktime'
    ];
    $base = rtrim(UPLOAD_DIR, '/');
    $monthDir = date('Y/m');
    $targetDir = $base . '/' . $monthDir;
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($_FILES as $field => $file) {
        $names = is_array($file['name']) ? $file['name'] : [$file['name']];
        $tmpNames = is_array($file['tmp_name']) ? $file['tmp_name'] : [$file['tmp_name']];
        $errors = is_array($file['error']) ? $file['error'] : [$file['error']];
        $sizes = is_array($file['size']) ? $file['size'] : [$file['size']];
        foreach ($names as $i => $originalName) {
            if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if (($errors[$i] ?? 0) !== UPLOAD_ERR_OK) throw new RuntimeException('File upload failed for ' . $field);
            if (($sizes[$i] ?? 0) > MAX_UPLOAD_BYTES) throw new RuntimeException('Uploaded file is too large.');
            $tmp = $tmpNames[$i];
            $mime = $finfo->file($tmp) ?: 'application/octet-stream';
            if (!in_array($mime, $allowed, true)) throw new RuntimeException('File type not allowed: ' . $mime);
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $safeExt = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
            $storedName = bin2hex(random_bytes(12)) . '.' . $safeExt;
            $storedPath = $targetDir . '/' . $storedName;
            if (!move_uploaded_file($tmp, $storedPath)) throw new RuntimeException('Could not save uploaded file.');
            $saved[] = [
                'field' => $field,
                'original_name' => clean_string($originalName, 255),
                'stored_name' => $storedName,
                'relative_path' => 'uploads/' . $monthDir . '/' . $storedName,
                'mime' => $mime,
                'size' => (int)$sizes[$i],
            ];
        }
    }
    return $saved;
}
function require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'POST method required.'], 405);
}
function allowed_dataset(string $dataset): bool {
    return in_array($dataset, ['applications','needs','volunteers','supporters','messages','donors'], true);
}

function site_constant(string $name, string $default = ''): string {
    return defined($name) ? (string)constant($name) : $default;
}

function app_link(string $path = ''): string {
    $base = rtrim(site_constant('APP_URL', ''), '/');
    if ($base === '') return $path;
    return $base . '/' . ltrim($path, '/');
}

function campaign_name(): string {
    return site_constant('CAMPAIGN_NAME', 'SME for Esan Central 2027');
}

function campaign_phone(): string {
    return site_constant('CAMPAIGN_PHONE', '0807 943 6049');
}

function send_email_message(string $to, string $subject, string $body): bool {
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    if (!defined('ENABLE_EMAIL_NOTIFICATIONS') || ENABLE_EMAIL_NOTIFICATIONS !== true) return false;

    $from = site_constant('SITE_EMAIL', 'no-reply@example.com');
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/plain; charset=UTF-8';
    $headers[] = 'From: ' . campaign_name() . ' <' . $from . '>';
    $headers[] = 'Reply-To: ' . $from;

    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

function phone_to_e164(string $phone, string $defaultCountryCode = '+234'): string {
    $phone = trim($phone);
    if ($phone === '') return '';

    // Already international: +234..., +1..., etc.
    if (strpos($phone, '+') === 0) {
        return '+' . preg_replace('/\D+/', '', substr($phone, 1));
    }

    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === '') return '';

    // Nigerian local mobile format: 080... -> +23480...
    if (strpos($digits, '0') === 0 && $defaultCountryCode === '+234') {
        return '+234' . substr($digits, 1);
    }

    // Nigerian country code without plus: 23480... -> +23480...
    if (strpos($digits, '234') === 0 && $defaultCountryCode === '+234') {
        return '+' . $digits;
    }

    return rtrim($defaultCountryCode, '+') === '' ? '+' . $digits : $defaultCountryCode . $digits;
}

function twilio_configured(): bool {
    return defined('TWILIO_ENABLED') && TWILIO_ENABLED === true
        && defined('TWILIO_ACCOUNT_SID') && TWILIO_ACCOUNT_SID !== '' && strpos(TWILIO_ACCOUNT_SID, 'PUT_') === false
        && defined('TWILIO_AUTH_TOKEN') && TWILIO_AUTH_TOKEN !== '' && strpos(TWILIO_AUTH_TOKEN, 'PUT_') === false
        && (
            (defined('TWILIO_FROM_NUMBER') && TWILIO_FROM_NUMBER !== '' && strpos(TWILIO_FROM_NUMBER, 'PUT_') === false)
            || (defined('TWILIO_MESSAGING_SERVICE_SID') && TWILIO_MESSAGING_SERVICE_SID !== '' && strpos(TWILIO_MESSAGING_SERVICE_SID, 'PUT_') === false)
        );
}

function send_twilio_sms(string $phone, string $message): bool {
    if (!function_exists('curl_init') || !twilio_configured()) return false;

    $defaultCountry = defined('DEFAULT_SMS_COUNTRY_CODE') ? DEFAULT_SMS_COUNTRY_CODE : '+234';
    $to = phone_to_e164($phone, $defaultCountry);
    if ($to === '' || $message === '') return false;

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(TWILIO_ACCOUNT_SID) . '/Messages.json';
    $payload = [
        'To' => $to,
        'Body' => $message,
    ];

    if (defined('TWILIO_MESSAGING_SERVICE_SID') && TWILIO_MESSAGING_SERVICE_SID !== '' && strpos(TWILIO_MESSAGING_SERVICE_SID, 'PUT_') === false) {
        $payload['MessagingServiceSid'] = TWILIO_MESSAGING_SERVICE_SID;
    } else {
        $payload['From'] = TWILIO_FROM_NUMBER;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_USERPWD => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $result = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $result !== false && $status >= 200 && $status < 300;
}

function send_sms_message(string $phone, string $message): bool {
    if ($phone === '' || !defined('ENABLE_SMS_NOTIFICATIONS') || ENABLE_SMS_NOTIFICATIONS !== true) return false;

    // Preferred provider for this project: Twilio.
    if (twilio_configured()) {
        return send_twilio_sms($phone, $message);
    }

    // Backward-compatible generic SMS API fallback.
    if (!defined('SMS_API_URL') || SMS_API_URL === '' || !function_exists('curl_init')) return false;

    $payload = [
        'to' => $phone,
        'message' => $message,
        'sender' => site_constant('SMS_API_SENDER', 'SME2027'),
    ];

    $ch = curl_init(SMS_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => array_filter([
            'Content-Type: application/json',
            defined('SMS_API_TOKEN') && SMS_API_TOKEN !== '' ? 'Authorization: Bearer ' . SMS_API_TOKEN : null,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
    ]);
    $result = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $result !== false && $status >= 200 && $status < 300;
}

function notification_log(string $channel, string $recipient, string $subject, string $message, bool $sent, ?string $reference = null): void {
    try {
        $stmt = db()->prepare('INSERT INTO notification_logs (channel, recipient, subject, message, reference, was_sent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$channel, $recipient, $subject, $message, $reference, $sent ? 1 : 0]);
    } catch (Throwable $e) {}
}

function notify_applicant_submission(array $submission): void {
    $reference = $submission['reference'] ?? '';
    $name = $submission['name'] ?: 'Applicant';
    $category = $submission['category'] ?: ($submission['type'] ?? 'Application');
    $status = $submission['status'] ?: 'Application Received';
    $date = $submission['createdAt'] ?? date('c');
    $statusUrl = app_link('application-status.html');

    $subject = 'Application received - ' . $reference;
    $body = "Dear {$name},\n\nYour application has been received successfully.\n\nReference Number: {$reference}\nCategory: {$category}\nDate Submitted: {$date}\nCurrent Status: {$status}\n\nYou can check your application status using your reference number and phone number here:\n{$statusUrl}\n\nPlease keep your reference number safe.\n\n" . campaign_name();

    if (!empty($submission['email'])) {
        $sent = send_email_message($submission['email'], $subject, $body);
        notification_log('email', $submission['email'], $subject, $body, $sent, $reference);
    }

    if (!empty($submission['phone'])) {
        $sms = "SME 2027: Your application was received. Reference: {$reference}. Check status: {$statusUrl}";
        $sent = send_sms_message($submission['phone'], $sms);
        notification_log('sms', $submission['phone'], 'Application received', $sms, $sent, $reference);
    }
}

function notify_applicant_status(array $submission, string $oldStatus = ''): void {
    $reference = $submission['reference'] ?? '';
    $name = $submission['name'] ?: 'Applicant';
    $status = $submission['status'] ?: 'Updated';
    $statusUrl = app_link('application-status.html');

    $subject = 'Application status update - ' . $reference;
    $body = "Dear {$name},\n\nYour application status has been updated.\n\nReference Number: {$reference}\nNew Status: {$status}\n\nYou can check your status using your reference number and phone number here:\n{$statusUrl}\n\n" . campaign_name();

    if (!empty($submission['email'])) {
        $sent = send_email_message($submission['email'], $subject, $body);
        notification_log('email', $submission['email'], $subject, $body, $sent, $reference);
    }

    if (!empty($submission['phone'])) {
        $sms = "SME 2027: Status update for {$reference}: {$status}. Check: {$statusUrl}";
        $sent = send_sms_message($submission['phone'], $sms);
        notification_log('sms', $submission['phone'], 'Status update', $sms, $sent, $reference);
    }
}

function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'post';
}

function unique_slug(string $title, ?int $ignoreId = null): string {
    $base = slugify($title);
    $slug = $base;
    $i = 2;
    while (true) {
        if ($ignoreId) {
            $stmt = db()->prepare('SELECT id FROM content_posts WHERE slug = ? AND id <> ? LIMIT 1');
            $stmt->execute([$slug, $ignoreId]);
        } else {
            $stmt = db()->prepare('SELECT id FROM content_posts WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
        }
        if (!$stmt->fetch()) return $slug;
        $slug = $base . '-' . $i++;
    }
}

function duplicate_details(array $row): array {
    $reasons = [];
    $params = [];
    $conditions = [];
    $id = (int)($row['id'] ?? 0);
    $dataset = $row['dataset'] ?? '';

    if (!empty($row['phone_normalized'])) {
        $conditions[] = '(phone_normalized = ? AND phone_normalized <> "")';
        $params[] = $row['phone_normalized'];
        $reasons[] = 'same phone number';
    }
    if (!empty($row['email'])) {
        $conditions[] = '(email = ? AND email <> "")';
        $params[] = $row['email'];
        $reasons[] = 'same email address';
    }
    if (!empty($row['name']) && (!empty($row['community']) || !empty($row['ward']))) {
        $conditions[] = '(LOWER(name) = LOWER(?) AND (community = ? OR ward = ?))';
        $params[] = $row['name'];
        $params[] = $row['community'] ?? '';
        $params[] = $row['ward'] ?? '';
        $reasons[] = 'same name and community/ward';
    }

    if (!$conditions || $dataset === '') return ['count'=>0,'reasons'=>[]];

    array_unshift($params, $dataset, $id);
    $sql = 'SELECT COUNT(*) FROM submissions WHERE dataset = ? AND id <> ? AND (' . implode(' OR ', $conditions) . ')';
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $count = (int)$stmt->fetchColumn();
        return ['count'=>$count, 'reasons'=>$count > 0 ? $reasons : []];
    } catch (Throwable $e) {
        return ['count'=>0,'reasons'=>[]];
    }
}
