<?php
function paystack_enabled(): bool {
    return defined('PAYSTACK_ENABLED') && PAYSTACK_ENABLED === true
        && defined('PAYSTACK_SECRET_KEY') && PAYSTACK_SECRET_KEY !== '' && strpos(PAYSTACK_SECRET_KEY, 'PUT_') === false;
}

function payment_error_redirect(string $message, string $reference = ''): void {
    $params = ['payment' => 'error', 'message' => $message];
    if ($reference !== '') $params['reference'] = $reference;
    header('Location: ' . app_link('donate.html?' . http_build_query($params)));
    exit;
}

function payment_success_redirect(string $reference): void {
    header('Location: ' . app_link('donate.html?payment=success&reference=' . urlencode($reference)));
    exit;
}

function payment_failed_redirect(string $reference): void {
    header('Location: ' . app_link('donate.html?payment=failed&reference=' . urlencode($reference)));
    exit;
}

function generate_payment_reference(): string {
    $pdo = db();
    do {
        $reference = 'SME-DON-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $pdo->prepare('SELECT id FROM donations WHERE payment_reference = ? LIMIT 1');
        $stmt->execute([$reference]);
    } while ($stmt->fetch());
    return $reference;
}

function paystack_request(string $method, string $endpoint, array $payload = null): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL is not enabled on this hosting account.');
    }
    if (!paystack_enabled()) {
        throw new RuntimeException('Paystack is not enabled or the secret key has not been configured.');
    }

    $ch = curl_init('https://api.paystack.co/' . ltrim($endpoint, '/'));
    $headers = [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json',
    ];

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ];

    if (strtoupper($method) === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($payload ?: []);
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Paystack connection failed: ' . $error);
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        throw new RuntimeException('Paystack returned an invalid response.');
    }

    $json['_http_status'] = $status;
    $json['_raw_response'] = $response;
    return $json;
}

function create_paid_donor_submission(array $donation): string {
    if (!empty($donation['submission_reference'])) {
        return $donation['submission_reference'];
    }

    $reference = generate_reference('DON');
    $payload = [
        'Full Name' => $donation['donor_name'] ?? '',
        'Phone Number' => $donation['phone'] ?? '',
        'Email' => $donation['email'] ?? '',
        'Donation Amount' => $donation['amount'] ?? '',
        'Currency' => $donation['currency'] ?? 'NGN',
        'Payment Reference' => $donation['payment_reference'] ?? '',
        'Payment Provider' => 'Paystack',
        'Message' => $donation['message'] ?? '',
    ];

    $stmt = db()->prepare('INSERT INTO submissions (reference, dataset, type, category, status, name, phone, phone_normalized, email, community, ward, payload_json, files_json, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $reference,
        'donors',
        'paid_donation',
        'Online Donation',
        'Paid',
        $donation['donor_name'] ?? '',
        $donation['phone'] ?? '',
        normalize_phone($donation['phone'] ?? ''),
        $donation['email'] ?? '',
        '',
        '',
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        json_encode([], JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
    ]);

    $stmt = db()->prepare('UPDATE donations SET submission_reference = ? WHERE id = ?');
    $stmt->execute([$reference, $donation['id']]);

    audit_log('paid_donation_recorded', 'donors', $reference, ['payment_reference' => $donation['payment_reference'] ?? '']);

    return $reference;
}

function notify_paid_donor(array $donation): void {
    $reference = $donation['payment_reference'] ?? '';
    $amount = number_format((float)($donation['amount'] ?? 0), 2);
    $currency = $donation['currency'] ?? 'NGN';
    $name = $donation['donor_name'] ?: 'Supporter';

    $subject = 'Donation received - ' . $reference;
    $body = "Dear {$name},\n\nThank you for supporting SME 2027.\n\nDonation Amount: {$currency} {$amount}\nPayment Reference: {$reference}\nStatus: Paid\n\n" . campaign_name();

    if (!empty($donation['email'])) {
        $sent = send_email_message($donation['email'], $subject, $body);
        notification_log('email', $donation['email'], $subject, $body, $sent, $reference);
    }

    if (!empty($donation['phone'])) {
        $sms = "SME 2027: Thank you for your donation of {$currency} {$amount}. Reference: {$reference}.";
        $sent = send_sms_message($donation['phone'], $sms);
        notification_log('sms', $donation['phone'], 'Donation received', $sms, $sent, $reference);
    }
}
