<?php

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../../backend/payment_helpers.php';

/*
 * Flutterwave payment verification
 *
 * Flutterwave redirects to this file with query parameters such as:
 *
 * status=successful
 * tx_ref=YOUR_PAYMENT_REFERENCE
 * transaction_id=123456789
 */

$reference = '';
$transactionId = '';

try {
    /*
     * Read the transaction reference returned by Flutterwave.
     *
     * tx_ref is Flutterwave's standard redirect parameter.
     * reference is included as a fallback for manual testing or older links.
     */
    $reference = clean_string(
        (string) (
            $_GET['tx_ref']
            ?? $_GET['reference']
            ?? ''
        ),
        100
    );

    /*
     * Read the Flutterwave transaction ID.
     */
    $transactionId = clean_string(
        (string) ($_GET['transaction_id'] ?? ''),
        100
    );

    /*
     * Read the redirect status.
     *
     * Do not trust this value alone. A server-side verification request
     * will still be required before the donation is marked as paid.
     */
    $redirectStatus = strtolower(
        clean_string(
            (string) ($_GET['status'] ?? ''),
            50
        )
    );

    if ($reference === '') {
        payment_error_redirect(
            'Missing payment reference.'
        );

        exit;
    }

    /*
     * Find the pending donation in the local database.
     */
    $donationStatement = db()->prepare(
        'SELECT *
         FROM donations
         WHERE payment_reference = ?
         LIMIT 1'
    );

    $donationStatement->execute([
        $reference
    ]);

    $donation = $donationStatement->fetch();

    if (!$donation) {
        payment_error_redirect(
            'Donation record was not found.',
            $reference
        );

        exit;
    }

    /*
     * Avoid sending duplicate notifications if this callback URL
     * is opened again after the transaction has already been verified.
     */
    if (
        strtolower(
            (string) ($donation['payment_status'] ?? '')
        ) === 'paid'
    ) {
        payment_success_redirect(
            $reference
        );

        exit;
    }

    /*
     * Handle cancellations or failed checkouts that do not include
     * a Flutterwave transaction ID.
     */
    if (
        $transactionId === ''
        && in_array(
            $redirectStatus,
            ['cancelled', 'canceled', 'failed'],
            true
        )
    ) {
        $failedStatus = in_array(
            $redirectStatus,
            ['cancelled', 'canceled'],
            true
        )
            ? 'cancelled'
            : 'failed';

        $redirectResponse = json_encode(
            [
                'source' => 'flutterwave_redirect',
                'status' => $redirectStatus,
                'tx_ref' => $reference,
                'transaction_id' => null
            ],
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

        $failedStatement = db()->prepare(
            'UPDATE donations
             SET payment_status = ?,
                 provider_response = ?
             WHERE payment_reference = ?
               AND payment_status <> ?'
        );

        $failedStatement->execute([
            $failedStatus,
            $redirectResponse,
            $reference,
            'paid'
        ]);

        payment_failed_redirect(
            $reference
        );

        exit;
    }

    /*
     * A successful Flutterwave redirect should include a numeric
     * transaction ID.
     */
    if (
        $transactionId === ''
        || !preg_match('/^[0-9]+$/', $transactionId)
    ) {
        payment_error_redirect(
            'Flutterwave did not return a valid transaction ID.',
            $reference
        );

        exit;
    }

    /*
     * Read the Flutterwave secret key.
     *
     * The key may come from a server environment variable or from
     * backend/payment_sms_config.php.
     */
    $environmentSecretKey = getenv(
        'FLUTTERWAVE_SECRET_KEY'
    );

    if (
        $environmentSecretKey !== false
        && trim($environmentSecretKey) !== ''
    ) {
        $flutterwaveSecretKey = trim(
            $environmentSecretKey
        );
    } elseif (defined('FLUTTERWAVE_SECRET_KEY')) {
        $flutterwaveSecretKey = trim(
            (string) FLUTTERWAVE_SECRET_KEY
        );
    } elseif (defined('FLW_SECRET_KEY')) {
        $flutterwaveSecretKey = trim(
            (string) FLW_SECRET_KEY
        );
    } else {
        $flutterwaveSecretKey = '';
    }

    if ($flutterwaveSecretKey === '') {
        payment_error_redirect(
            'Flutterwave is not configured. Please add the Flutterwave secret key to backend/payment_sms_config.php.',
            $reference
        );

        exit;
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'PHP cURL is not enabled on this server.'
        );
    }

    /*
     * Verify the transaction directly with Flutterwave.
     *
     * Flutterwave verification endpoint:
     * GET /v3/transactions/{transaction_id}/verify
     */
    $verificationUrl =
        'https://api.flutterwave.com/v3/transactions/'
        . rawurlencode($transactionId)
        . '/verify';

    $curl = curl_init(
        $verificationUrl
    );

    curl_setopt_array(
        $curl,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '
                    . $flutterwaveSecretKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]
    );

    $rawResponse = curl_exec(
        $curl
    );

    $curlError = curl_error(
        $curl
    );

    $httpStatus = (int) curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    curl_close(
        $curl
    );

    if ($rawResponse === false) {
        throw new RuntimeException(
            'Flutterwave connection error: '
            . (
                $curlError !== ''
                    ? $curlError
                    : 'Unknown connection error.'
            )
        );
    }

    /*
     * Decode Flutterwave's JSON response.
     */
    $result = json_decode(
        $rawResponse,
        true
    );

    if (!is_array($result)) {
        throw new RuntimeException(
            'Flutterwave returned an invalid verification response.'
        );
    }

    /*
     * Check whether Flutterwave successfully processed the
     * verification request itself.
     */
    $requestWasSuccessful =
        $httpStatus >= 200
        && $httpStatus < 300
        && strtolower(
            (string) ($result['status'] ?? '')
        ) === 'success'
        && isset($result['data'])
        && is_array($result['data']);

    if (!$requestWasSuccessful) {
        /*
         * Keep the donation pending when Flutterwave's verification
         * service is temporarily unavailable. This allows it to be
         * verified later through another callback or webhook.
         */
        $verificationErrorStatement = db()->prepare(
            'UPDATE donations
             SET provider_response = ?
             WHERE payment_reference = ?
               AND payment_status <> ?'
        );

        $verificationErrorStatement->execute([
            $rawResponse,
            $reference,
            'paid'
        ]);

        $providerMessage = clean_string(
            (string) (
                $result['message']
                ?? 'Flutterwave could not verify the payment.'
            ),
            500
        );

        payment_error_redirect(
            $providerMessage !== ''
                ? $providerMessage
                : 'Flutterwave could not verify the payment.',
            $reference
        );

        exit;
    }

    $transactionData = $result['data'];

    /*
     * Extract the verified transaction values.
     */
    $verifiedReference = clean_string(
        (string) ($transactionData['tx_ref'] ?? ''),
        100
    );

    $verifiedStatus = strtolower(
        clean_string(
            (string) ($transactionData['status'] ?? ''),
            50
        )
    );

    $verifiedCurrency = strtoupper(
        clean_string(
            (string) ($transactionData['currency'] ?? ''),
            10
        )
    );

    $verifiedAmount = is_numeric(
        $transactionData['amount'] ?? null
    )
        ? (float) $transactionData['amount']
        : 0.0;

    /*
     * Read the expected currency and amount from the local donation.
     */
    $expectedCurrency = strtoupper(
        clean_string(
            (string) (
                $donation['currency']
                ?? (
                    defined('FLUTTERWAVE_CURRENCY')
                        ? FLUTTERWAVE_CURRENCY
                        : 'NGN'
                )
            ),
            10
        )
    );

    if (
        isset($donation['amount'])
        && is_numeric($donation['amount'])
    ) {
        $expectedAmount = (float) $donation['amount'];
    } elseif (
        isset($donation['amount_kobo'])
        && is_numeric($donation['amount_kobo'])
    ) {
        $expectedAmount =
            ((float) $donation['amount_kobo']) / 100;
    } else {
        $expectedAmount = 0.0;
    }

    /*
     * Validate all important transaction information.
     *
     * The redirect parameters alone are not trusted.
     */
    $referenceMatches =
        $verifiedReference !== ''
        && hash_equals(
            $reference,
            $verifiedReference
        );

    $statusIsSuccessful =
        $verifiedStatus === 'successful';

    $currencyMatches =
        $verifiedCurrency !== ''
        && hash_equals(
            $expectedCurrency,
            $verifiedCurrency
        );

    /*
     * Flutterwave recommends confirming that the paid amount is
     * greater than or equal to the expected amount.
     *
     * A small tolerance prevents floating-point comparison errors.
     */
    $amountIsValid =
        $expectedAmount > 0
        && ($verifiedAmount + 0.00001) >= $expectedAmount;

    /*
     * The payment is accepted only when every verification
     * requirement passes.
     */
    if (
        $referenceMatches
        && $statusIsSuccessful
        && $currencyMatches
        && $amountIsValid
    ) {
        /*
         * Atomically mark the donation as paid.
         *
         * The payment_status condition prevents duplicate email,
         * SMS or donor-submission creation when Flutterwave calls
         * this URL more than once.
         */
        $paidStatement = db()->prepare(
            'UPDATE donations
             SET payment_status = ?,
                 provider_response = ?,
                 paid_at = COALESCE(paid_at, NOW())
             WHERE payment_reference = ?
               AND payment_status <> ?'
        );

        $paidStatement->execute([
            'paid',
            $rawResponse,
            $reference,
            'paid'
        ]);

        $wasNewlyMarkedPaid =
            $paidStatement->rowCount() > 0;

        /*
         * Reload the updated donation.
         */
        $reloadStatement = db()->prepare(
            'SELECT *
             FROM donations
             WHERE payment_reference = ?
             LIMIT 1'
        );

        $reloadStatement->execute([
            $reference
        ]);

        $paidDonation = $reloadStatement->fetch();

        /*
         * Create the donor submission and send notifications only
         * on the first successful verification.
         */
        if (
            $wasNewlyMarkedPaid
            && $paidDonation
        ) {
            try {
                create_paid_donor_submission(
                    $paidDonation
                );
            } catch (Throwable $submissionException) {
                error_log(
                    'Unable to create paid donor submission for '
                    . $reference
                    . ': '
                    . $submissionException->getMessage()
                );
            }

            try {
                notify_paid_donor(
                    $paidDonation
                );
            } catch (Throwable $notificationException) {
                error_log(
                    'Unable to notify paid donor for '
                    . $reference
                    . ': '
                    . $notificationException->getMessage()
                );
            }
        }

        payment_success_redirect(
            $reference
        );

        exit;
    }

    /*
     * The transaction was returned by Flutterwave, but one or more
     * verification checks failed.
     */
    if (
        in_array(
            $verifiedStatus,
            [
                'cancelled',
                'canceled'
            ],
            true
        )
    ) {
        $newStatus = 'cancelled';
    } elseif (
        in_array(
            $verifiedStatus,
            [
                'failed',
                'declined'
            ],
            true
        )
    ) {
        $newStatus = 'failed';
    } else {
        $newStatus = 'verification_failed';
    }

    /*
     * Store both Flutterwave's response and a local explanation of
     * which checks succeeded or failed.
     */
    $verificationFailureDetails = [
        'flutterwave_response' => $result,

        'local_verification' => [
            'expected_reference' => $reference,
            'received_reference' => $verifiedReference,
            'reference_matches' => $referenceMatches,

            'expected_status' => 'successful',
            'received_status' => $verifiedStatus,
            'status_matches' => $statusIsSuccessful,

            'expected_currency' => $expectedCurrency,
            'received_currency' => $verifiedCurrency,
            'currency_matches' => $currencyMatches,

            'expected_amount' => $expectedAmount,
            'received_amount' => $verifiedAmount,
            'amount_is_valid' => $amountIsValid,

            'transaction_id' => $transactionId
        ]
    ];

    $verificationFailureJson = json_encode(
        $verificationFailureDetails,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    if ($verificationFailureJson === false) {
        $verificationFailureJson = $rawResponse;
    }

    $failedVerificationStatement = db()->prepare(
        'UPDATE donations
         SET payment_status = ?,
             provider_response = ?
         WHERE payment_reference = ?
           AND payment_status <> ?'
    );

    $failedVerificationStatement->execute([
        $newStatus,
        $verificationFailureJson,
        $reference,
        'paid'
    ]);

    payment_failed_redirect(
        $reference
    );

    exit;

} catch (Throwable $exception) {
    /*
     * Record the full error in the private PHP error log.
     * Do not expose sensitive technical information to visitors.
     */
    error_log(
        'Flutterwave verification error'
        . (
            $reference !== ''
                ? ' for ' . $reference
                : ''
        )
        . ': '
        . $exception->getMessage()
    );

    /*
     * Keep the payment record pending when an unexpected server
     * or network error prevents reliable verification.
     */
    if ($reference !== '') {
        try {
            $errorResponse = json_encode(
                [
                    'source' => 'flutterwave_verification',
                    'transaction_id' => $transactionId,
                    'error' => $exception->getMessage()
                ],
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );

            $errorStatement = db()->prepare(
                'UPDATE donations
                 SET provider_response = ?
                 WHERE payment_reference = ?
                   AND payment_status <> ?'
            );

            $errorStatement->execute([
                $errorResponse,
                $reference,
                'paid'
            ]);
        } catch (Throwable $databaseException) {
            error_log(
                'Unable to save Flutterwave verification error: '
                . $databaseException->getMessage()
            );
        }
    }

    payment_error_redirect(
        'We could not confirm your Flutterwave payment at this time. Please contact the campaign team before attempting another payment.',
        $reference
    );

    exit;
}