<?php

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../../backend/payment_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    payment_error_redirect('Invalid payment request.');
    exit;
}

$paymentReference = '';

try {
    /*
     * Read the Flutterwave secret key.
     *
     * You can define FLUTTERWAVE_SECRET_KEY inside:
     * backend/payment_sms_config.php
     *
     * Alternatively, you can create it as a server environment variable.
     */
    $environmentSecretKey = getenv('FLUTTERWAVE_SECRET_KEY');

    if ($environmentSecretKey !== false && trim($environmentSecretKey) !== '') {
        $flutterwaveSecretKey = trim($environmentSecretKey);
    } elseif (defined('FLUTTERWAVE_SECRET_KEY')) {
        $flutterwaveSecretKey = trim((string) FLUTTERWAVE_SECRET_KEY);
    } elseif (defined('FLW_SECRET_KEY')) {
        $flutterwaveSecretKey = trim((string) FLW_SECRET_KEY);
    } else {
        $flutterwaveSecretKey = '';
    }

    if ($flutterwaveSecretKey === '') {
        payment_error_redirect(
            'Flutterwave is not enabled yet. Please configure the Flutterwave secret key in backend/payment_sms_config.php.'
        );
        exit;
    }

    /*
     * Read and sanitize the donation details.
     */
    $name = clean_string(
        (string) ($_POST['name'] ?? ''),
        190
    );

    $email = clean_string(
        (string) ($_POST['email'] ?? ''),
        190
    );

    $phone = clean_string(
        (string) ($_POST['phone'] ?? ''),
        50
    );

    $message = clean_string(
        (string) ($_POST['message'] ?? ''),
        3000
    );

    $rawAmount = $_POST['amount'] ?? '';

    /*
     * Flutterwave accepts the amount in the main currency unit.
     *
     * Example:
     * ₦5,000 is sent to Flutterwave as 5000.
     */
    if (!is_numeric($rawAmount)) {
        payment_error_redirect('Please enter a valid donation amount.');
        exit;
    }

    $amount = round((float) $rawAmount, 2);

    $minAmount = defined('FLUTTERWAVE_MIN_AMOUNT')
        ? (float) FLUTTERWAVE_MIN_AMOUNT
        : 100;

    $currency = defined('FLUTTERWAVE_CURRENCY')
        ? strtoupper(trim((string) FLUTTERWAVE_CURRENCY))
        : 'NGN';

    $paymentOptions = defined('FLUTTERWAVE_PAYMENT_OPTIONS')
        ? trim((string) FLUTTERWAVE_PAYMENT_OPTIONS)
        : 'card,banktransfer,ussd';

    /*
     * Validate the donor information.
     */
    if (
        $name === '' ||
        $email === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        payment_error_redirect(
            'Please enter your full name and a valid email address.'
        );
        exit;
    }

    if ($amount < $minAmount) {
        payment_error_redirect(
            'Please enter a donation amount of at least ' .
            number_format($minAmount, 0) .
            ' ' .
            $currency .
            '.'
        );
        exit;
    }

    /*
     * Keep the minor-unit amount for your existing database column.
     *
     * This amount is not sent to Flutterwave.
     */
    $amountKobo = (int) round($amount * 100);

    /*
     * Generate a unique transaction reference.
     */
    $paymentReference = generate_payment_reference();

    /*
     * Record the pending donation before contacting Flutterwave.
     */
    $insertStatement = db()->prepare(
        'INSERT INTO donations (
            donor_name,
            email,
            phone,
            amount,
            amount_kobo,
            currency,
            payment_reference,
            payment_status,
            payment_provider,
            message,
            ip_address
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $insertStatement->execute([
        $name,
        $email,
        $phone,
        $amount,
        $amountKobo,
        $currency,
        $paymentReference,
        'pending',
        'flutterwave',
        $message,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    /*
     * Flutterwave will return the donor to this page after checkout.
     *
     * Flutterwave appends:
     * status
     * tx_ref
     * transaction_id
     */
    $callbackUrl = app_link(
        'api/payments/flutterwave_verify.php'
    );

    /*
     * Prepare the Flutterwave Standard payment request.
     */
    $payload = [
        'tx_ref' => $paymentReference,

        /*
         * Do not multiply this amount by 100.
         */
        'amount' => number_format(
            $amount,
            2,
            '.',
            ''
        ),

        'currency' => $currency,
        'redirect_url' => $callbackUrl,
        'payment_options' => $paymentOptions,

        'customer' => [
            'email' => $email,
            'phone_number' => $phone,
            'name' => $name
        ],

        'customizations' => [
            'title' => campaign_name(),
            'description' => 'Campaign donation for SME 2027'
        ],

        'meta' => [
            'donor_name' => $name,
            'phone' => $phone,
            'campaign' => campaign_name(),
            'internal_reference' => $paymentReference
        ]
    ];

    $jsonPayload = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

    if ($jsonPayload === false) {
        throw new RuntimeException(
            'The Flutterwave payment information could not be prepared.'
        );
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'PHP cURL is not enabled on this server.'
        );
    }

    /*
     * Send the initialization request to Flutterwave.
     */
    $curl = curl_init(
        'https://api.flutterwave.com/v3/payments'
    );

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $flutterwaveSecretKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $rawResponse = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpStatus = (int) curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    curl_close($curl);

    if ($rawResponse === false) {
        throw new RuntimeException(
            'Flutterwave connection error: ' .
            ($curlError !== ''
                ? $curlError
                : 'Unknown connection error.')
        );
    }

    $result = json_decode(
        $rawResponse,
        true
    );

    if (!is_array($result)) {
        $result = [
            'status' => 'error',
            'message' => 'Flutterwave returned an invalid response.'
        ];
    }

    /*
     * Confirm that Flutterwave successfully created the payment link.
     */
    $paymentLink = isset($result['data']['link'])
        ? trim((string) $result['data']['link'])
        : '';

    $initializationSuccessful =
        $httpStatus >= 200 &&
        $httpStatus < 300 &&
        ($result['status'] ?? '') === 'success' &&
        $paymentLink !== '';

    if (!$initializationSuccessful) {
        $failureResponse = $rawResponse !== ''
            ? $rawResponse
            : json_encode($result);

        $failedStatement = db()->prepare(
            'UPDATE donations
             SET payment_status = ?,
                 provider_response = ?
             WHERE payment_reference = ?'
        );

        $failedStatement->execute([
            'failed_to_initialize',
            $failureResponse,
            $paymentReference
        ]);

        $providerMessage = clean_string(
            (string) (
                $result['message'] ??
                'Flutterwave could not start the payment.'
            ),
            500
        );

        payment_error_redirect(
            $providerMessage !== ''
                ? $providerMessage
                : 'Flutterwave could not start the payment.',
            $paymentReference
        );

        exit;
    }

    /*
     * Make sure the returned payment URL is valid.
     */
    if (
        !filter_var(
            $paymentLink,
            FILTER_VALIDATE_URL
        )
    ) {
        throw new RuntimeException(
            'Flutterwave returned an invalid checkout URL.'
        );
    }

    $paymentLinkParts = parse_url($paymentLink);

    $paymentLinkScheme = strtolower(
        (string) ($paymentLinkParts['scheme'] ?? '')
    );

    $paymentLinkHost = strtolower(
        (string) ($paymentLinkParts['host'] ?? '')
    );

    $isFlutterwaveHost =
        $paymentLinkHost === 'checkout.flutterwave.com' ||
        (
            strlen($paymentLinkHost) > 16 &&
            substr($paymentLinkHost, -16) === '.flutterwave.com'
        );

    if (
        $paymentLinkScheme !== 'https' ||
        !$isFlutterwaveHost
    ) {
        throw new RuntimeException(
            'Flutterwave returned an untrusted checkout URL.'
        );
    }

    /*
     * Store the payment link and Flutterwave response.
     *
     * access_code is kept blank because Flutterwave Standard returns
     * data.link instead of Paystack's access_code.
     */
    $updateStatement = db()->prepare(
        'UPDATE donations
         SET authorization_url = ?,
             access_code = ?,
             provider_response = ?
         WHERE payment_reference = ?'
    );

    $updateStatement->execute([
        $paymentLink,
        '',
        $rawResponse,
        $paymentReference
    ]);

    /*
     * Redirect the donor to Flutterwave's hosted checkout.
     */
    header(
        'Location: ' . $paymentLink,
        true,
        302
    );

    exit;

} catch (Throwable $exception) {
    error_log(
        'Flutterwave initialization error: ' .
        $exception->getMessage()
    );

    /*
     * Mark the donation as failed if it was already created.
     */
    if ($paymentReference !== '') {
        try {
            $errorStatement = db()->prepare(
                'UPDATE donations
                 SET payment_status = ?,
                     provider_response = ?
                 WHERE payment_reference = ?'
            );

            $errorStatement->execute([
                'failed_to_initialize',
                json_encode([
                    'error' => $exception->getMessage()
                ]),
                $paymentReference
            ]);
        } catch (Throwable $databaseException) {
            error_log(
                'Unable to update failed Flutterwave donation: ' .
                $databaseException->getMessage()
            );
        }

        payment_error_redirect(
            'Flutterwave could not start the payment. Please try again or contact the campaign team.',
            $paymentReference
        );

        exit;
    }

    payment_error_redirect(
        'Flutterwave could not start the payment. Please try again or contact the campaign team.'
    );

    exit;
}