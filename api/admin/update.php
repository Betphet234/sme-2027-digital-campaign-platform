<?php

require_once __DIR__ . '/../../backend/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = require_admin();

    require_post();

    $data = input_json();

    if (!is_array($data)) {
        json_response(
            [
                'success' => false,
                'message' => 'Invalid JSON request.'
            ],
            400
        );
        exit;
    }

    $dataset = clean_string(
        $data['dataset'] ?? '',
        40
    );

    $reference = strtoupper(
        clean_string(
            $data['reference'] ?? '',
            50
        )
    );

    if (
        !allowed_dataset($dataset)
        || $reference === ''
    ) {
        json_response(
            [
                'success' => false,
                'message' => 'Invalid update request.'
            ],
            422
        );
        exit;
    }

    if (!can_update_dataset($user, $dataset)) {
        json_response(
            [
                'success' => false,
                'message' => 'Your account cannot update this dataset.'
            ],
            403
        );
        exit;
    }

    /*
     * Find the existing submission.
     */
    $selectStatement = db()->prepare(
        'SELECT *
         FROM submissions
         WHERE dataset = ?
           AND reference = ?
         LIMIT 1'
    );

    $selectStatement->execute([
        $dataset,
        $reference
    ]);

    $before = $selectStatement->fetch();

    if (!$before) {
        json_response(
            [
                'success' => false,
                'message' => 'Record not found.'
            ],
            404
        );
        exit;
    }

    $fields = [];
    $parameters = [];

    $statusChanged = false;
    $newStatus = (string) ($before['status'] ?? '');

    /*
     * Update the application status when supplied.
     */
    if (array_key_exists('status', $data)) {
        $newStatus = clean_string(
            $data['status'],
            80
        );

        if ($newStatus === '') {
            json_response(
                [
                    'success' => false,
                    'message' => 'Please select a valid status.'
                ],
                422
            );
            exit;
        }

        $fields[] = 'status = ?';
        $parameters[] = $newStatus;

        $statusChanged =
            $newStatus !== (string) ($before['status'] ?? '');
    }

    /*
     * Update internal notes when supplied.
     */
    if (array_key_exists('internal_notes', $data)) {
        $internalNotes = clean_string(
            $data['internal_notes'],
            10000
        );

        $fields[] = 'internal_notes = ?';
        $parameters[] = $internalNotes;
    }

    if (!$fields) {
        json_response(
            [
                'success' => false,
                'message' => 'Nothing to update.'
            ],
            422
        );
        exit;
    }

    $parameters[] = $dataset;
    $parameters[] = $reference;

    /*
     * Save the requested changes.
     */
    $updateStatement = db()->prepare(
        'UPDATE submissions
         SET ' . implode(', ', $fields) . '
         WHERE dataset = ?
           AND reference = ?'
    );

    $updateStatement->execute(
        $parameters
    );

    /*
     * Audit logging should not cause a successful update to fail.
     */
    $auditSaved = true;

    try {
        audit_log(
            'submission_updated',
            $dataset,
            $reference,
            $data
        );
    } catch (Throwable $auditException) {
        $auditSaved = false;

        error_log(
            'Admin update audit-log error for '
            . $reference
            . ': '
            . $auditException->getMessage()
        );
    }

    /*
     * Notify the applicant only when the application status changed.
     * Notification failure must not undo the database update.
     */
    $notificationSent = null;

    if (
        $dataset === 'applications'
        && $statusChanged
    ) {
        $after = $before;
        $after['status'] = $newStatus;

        if (array_key_exists('internal_notes', $data)) {
            $after['internal_notes'] = $internalNotes ?? '';
        }

        try {
            notify_applicant_status(
                $after,
                (string) ($before['status'] ?? '')
            );

            $notificationSent = true;
        } catch (Throwable $notificationException) {
            $notificationSent = false;

            error_log(
                'Applicant status notification failed for '
                . $reference
                . ': '
                . $notificationException->getMessage()
            );
        }
    }

    $message = 'Record updated successfully.';

    if ($notificationSent === false) {
        $message =
            'Status updated successfully, but the applicant notification could not be sent.';
    }

    json_response(
        [
            'success' => true,
            'message' => $message,
            'reference' => $reference,
            'status' => $newStatus,
            'status_changed' => $statusChanged,
            'notification_sent' => $notificationSent,
            'audit_saved' => $auditSaved
        ]
    );

    exit;

} catch (PDOException $exception) {
    error_log(
        'Admin database update error: '
        . $exception->getMessage()
    );

    json_response(
        [
            'success' => false,
            'message' =>
                'The record could not be updated because of a database error.'
        ],
        500
    );

    exit;

} catch (Throwable $exception) {
    error_log(
        'Admin update error: '
        . $exception->getMessage()
    );

    json_response(
        [
            'success' => false,
            'message' =>
                'The backend could not complete the update. Check the server error log for details.'
        ],
        500
    );

    exit;
}