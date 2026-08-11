<?php

require_once __DIR__ . '/../backend/bootstrap.php';

require_post();

try {
    /*
     * Identify the submitted form and destination dataset.
     */
    $type = clean_string(
        $_POST['submission_type'] ?? 'message',
        80
    );

    $store = clean_string(
        $_POST['store'] ?? '',
        80
    );

    [$dataset, $prefix, $status] = dataset_from_type(
        $type,
        $store
    );

    /*
     * Sanitize all submitted fields.
     */
    $payload = [];

    foreach ($_POST as $key => $value) {
        if (
            in_array(
                $key,
                ['submission_type', 'store'],
                true
            )
        ) {
            continue;
        }

        $cleanKey = clean_string(
            (string) $key,
            120
        );

        if (is_array($value)) {
            $payload[$cleanKey] = array_map(
                static function ($item) {
                    return clean_string(
                        (string) $item,
                        8000
                    );
                },
                $value
            );
        } else {
            $payload[$cleanKey] = clean_string(
                (string) $value,
                8000
            );
        }
    }

    /*
     * Extract the applicant's name.
     */
    $name = first_value(
        $payload,
        [
            'Full Name',
            'Full_Name',
            'Applicant Name',
            'Applicant_Name',
            'Owner Name',
            'Owner_Name',
            'Business Name',
            'Business_Name',
            'Name',
            'Subject'
        ]
    );

    /*
     * Extract the applicant's phone number.
     */
    $phone = first_value(
        $payload,
        [
            'Phone Number',
            'Phone_Number',
            'Phone number',
            'Phone_number',
            'Phone or WhatsApp',
            'Phone_or_WhatsApp',
            'Contact Details',
            'Contact_Details',
            'Contact details',
            'Contact_details'
        ]
    );

    /*
     * Extract the applicant's email address.
     */
    $email = first_value(
        $payload,
        [
            'Email Address',
            'Email_Address',
            'Email address',
            'Email_address',
            'Email'
        ]
    );

    $community = first_value(
        $payload,
        ['Community']
    );

    $ward = first_value(
        $payload,
        ['Ward']
    );

    $category = first_value(
        $payload,
        [
            'Application Category',
            'Application_Category',
            'Category',
            'Volunteer Role',
            'Volunteer_Role',
            'Business Sector',
            'Business_Sector'
        ]
    );

    /*
     * Normalize phone number and email.
     *
     * This allows different phone formats to be treated as the same:
     * 08151748877
     * +2348151748877
     * 2348151748877
     */
    $phoneNormalized = normalize_phone(
        $phone
    );

    $emailNormalized = strtolower(
        trim($email)
    );

    /*
     * Validate and prevent duplicate applications.
     *
     * Other website forms are not affected.
     */
    if ($dataset === 'applications') {
        if ($name === '' || $phone === '') {
            json_response(
                [
                    'success' => false,
                    'message' =>
                        'Full name and phone number are required.'
                ],
                422
            );

            exit;
        }

        if ($phoneNormalized === '') {
            json_response(
                [
                    'success' => false,
                    'message' =>
                        'Please enter a valid phone number.'
                ],
                422
            );

            exit;
        }

        if (
            $emailNormalized !== ''
            && !filter_var(
                $emailNormalized,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            json_response(
                [
                    'success' => false,
                    'message' =>
                        'Please enter a valid email address.'
                ],
                422
            );

            exit;
        }

        /*
         * Check for an existing application using the same phone
         * number or email address.
         */
        $duplicateSql = '
            SELECT
                reference,
                name,
                phone,
                email,
                status,
                created_at
            FROM submissions
            WHERE dataset = ?
              AND (
                    phone_normalized = ?
        ';

        $duplicateParameters = [
            'applications',
            $phoneNormalized
        ];

        if ($emailNormalized !== '') {
            $duplicateSql .= '
                    OR LOWER(TRIM(email)) = ?
            ';

            $duplicateParameters[] =
                $emailNormalized;
        }

        $duplicateSql .= '
              )
            ORDER BY created_at ASC
            LIMIT 1
        ';

        $duplicateStatement = db()->prepare(
            $duplicateSql
        );

        $duplicateStatement->execute(
            $duplicateParameters
        );

        $existingApplication =
            $duplicateStatement->fetch();

        if ($existingApplication) {
            json_response(
                [
                    'success' => false,
                    'duplicate' => true,
                    'message' =>
                        'You have already submitted an application using this phone number or email address. Please do not register again. Use the Status page to check your existing application.'
                ],
                409
            );

            exit;
        }
    }

    /*
     * Save uploads only after the duplicate check succeeds.
     */
    $files = save_uploaded_files();

    /*
     * Generate a new reference.
     */
    $reference = generate_reference(
        $prefix
    );

    /*
     * Insert the submission into the database.
     */
    $insertStatement = db()->prepare(
        'INSERT INTO submissions (
            reference,
            dataset,
            type,
            category,
            status,
            name,
            phone,
            phone_normalized,
            email,
            community,
            ward,
            payload_json,
            files_json,
            ip_address,
            user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $insertStatement->execute(
        [
            $reference,
            $dataset,
            $type,
            $category,
            $status,
            $name,
            $phone,
            $phoneNormalized,
            $email,
            $community,
            $ward,
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            json_encode(
                $files,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr(
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                0,
                255
            )
        ]
    );

    /*
     * Record the submission in the audit log.
     */
    audit_log(
        'submission_created',
        $dataset,
        $reference,
        [
            'type' => $type
        ]
    );

    $submissionPayload = [
        'reference' => $reference,
        'status' => $status,
        'type' => $type,
        'dataset' => $dataset,
        'category' => $category,
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'createdAt' => date('c')
    ];

    /*
     * Send applicant notification.
     *
     * Notification failure will not remove a successfully saved
     * application.
     */
    if ($dataset === 'applications') {
        try {
            notify_applicant_submission(
                $submissionPayload
            );
        } catch (Throwable $notificationException) {
            error_log(
                'Applicant notification failed for '
                . $reference
                . ': '
                . $notificationException->getMessage()
            );
        }
    }

    json_response(
        [
            'success' => true,
            'message' =>
                'Submission received successfully.',
            'submission' => $submissionPayload
        ]
    );

    exit;

} catch (PDOException $exception) {
    /*
     * Handle database duplicate-entry errors.
     */
    $databaseCode = (string) $exception->getCode();
    $databaseMessage = $exception->getMessage();

    if (
        $databaseCode === '23000'
        || strpos(
            strtolower($databaseMessage),
            'duplicate entry'
        ) !== false
    ) {
        json_response(
            [
                'success' => false,
                'duplicate' => true,
                'message' =>
                    'You have already submitted an application using this phone number or email address. Please use the Status page instead of registering again.'
            ],
            409
        );

        exit;
    }

    error_log(
        'Submission database error: '
        . $databaseMessage
    );

    json_response(
        [
            'success' => false,
            'message' =>
                'The submission could not be saved. Please try again or contact the campaign team.'
        ],
        500
    );

    exit;

} catch (Throwable $exception) {
    error_log(
        'Submission error: '
        . $exception->getMessage()
    );

    json_response(
        [
            'success' => false,
            'message' =>
                'The submission could not be completed. Please try again or contact the campaign team.'
        ],
        500
    );

    exit;
}