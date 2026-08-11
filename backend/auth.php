<?php
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT id, name, email, username, role, is_active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || (int)$user['is_active'] !== 1) return null;
    $user['permissions'] = user_permissions($user['role']);
    return $user;
}

function require_admin(): array {
    $user = current_user();
    if (!$user) json_response(['success'=>false,'message'=>'Please login to continue.'], 401);
    return $user;
}

function role_labels(): array {
    return [
        'super_admin' => 'Super Administrator',
        'content_editor' => 'Content Editor',
        'application_reviewer' => 'Application Reviewer',
        'community_coordinator' => 'Community Coordinator',
        'data_viewer' => 'Data Viewer',
    ];
}

function user_permissions(string $role): array {
    $map = [
        'super_admin' => [
            'view_dashboard','view_stats','view_all_datasets','update_all_datasets','export_reports',
            'manage_content','review_applications','review_volunteers','review_complaints','send_notifications','manage_users'
        ],
        'content_editor' => [
            'view_dashboard','view_stats','manage_content'
        ],
        'application_reviewer' => [
            'view_dashboard','view_stats','view_applications','update_applications','export_reports','review_applications','send_notifications'
        ],
        'community_coordinator' => [
            'view_dashboard','view_stats','view_community','update_community','export_reports','review_volunteers','review_complaints','send_notifications'
        ],
        'data_viewer' => [
            'view_dashboard','view_stats','view_all_datasets'
        ],
    ];
    return $map[$role] ?? $map['data_viewer'];
}

function has_permission(array $user, string $permission): bool {
    $permissions = $user['permissions'] ?? user_permissions($user['role'] ?? 'data_viewer');
    return in_array($permission, $permissions, true);
}

function require_permission(string $permission): array {
    $user = require_admin();
    if (!has_permission($user, $permission)) {
        json_response(['success'=>false,'message'=>'Your account does not have permission for this action.'], 403);
    }
    return $user;
}

function can_view_dataset(array $user, string $dataset): bool {
    if (has_permission($user, 'view_all_datasets')) return true;
    if ($dataset === 'applications' && has_permission($user, 'view_applications')) return true;
    if (in_array($dataset, ['needs','volunteers','supporters','messages'], true) && has_permission($user, 'view_community')) return true;
    return false;
}

function can_update_dataset(array $user, string $dataset): bool {
    if (has_permission($user, 'update_all_datasets')) return true;
    if ($dataset === 'applications' && has_permission($user, 'update_applications')) return true;
    if (in_array($dataset, ['needs','volunteers','supporters','messages'], true) && has_permission($user, 'update_community')) return true;
    return false;
}

function can_update_records(array $user): bool {
    return has_permission($user, 'update_all_datasets') || has_permission($user, 'update_applications') || has_permission($user, 'update_community');
}

function public_user_payload(array $user): array {
    return [
        'name' => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'role' => $user['role'] ?? 'data_viewer',
        'roleLabel' => role_labels()[$user['role'] ?? 'data_viewer'] ?? 'Data Viewer',
        'permissions' => $user['permissions'] ?? user_permissions($user['role'] ?? 'data_viewer'),
    ];
}
