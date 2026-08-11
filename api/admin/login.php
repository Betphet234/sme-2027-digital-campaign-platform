<?php
require_once __DIR__ . '/../../backend/bootstrap.php';

require_post();

$data = input_json();

$username = clean_string($data['username'] ?? '', 190);
$password = (string)($data['password'] ?? '');

if ($username === '' || $password === '') {
    json_response([
        'success' => false,
        'message' => 'Username/email and password are required.'
    ], 422);
}

$stmt = db()->prepare('
    SELECT id, name, email, username, password_hash, role, is_active
    FROM users
    WHERE email = ? OR username = ?
    LIMIT 1
');

$stmt->execute([$username, $username]);
$user = $stmt->fetch();

if (
    !$user ||
    (int)$user['is_active'] !== 1 ||
    !password_verify($password, $user['password_hash'])
) {
    audit_log('admin_login_failed', 'user', $username);

    json_response([
        'success' => false,
        'message' => 'Invalid login details.'
    ], 401);
}

session_regenerate_id(true);

$_SESSION['user_id'] = (int)$user['id'];

$stmt = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
$stmt->execute([$user['id']]);

audit_log('admin_login_success', 'user', (string)$user['id']);

$user['permissions'] = user_permissions($user['role']);

json_response([
    'success' => true,
    'user' => public_user_payload($user)
]);
