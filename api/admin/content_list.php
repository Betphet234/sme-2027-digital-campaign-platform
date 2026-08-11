<?php
require_once __DIR__ . '/../../backend/bootstrap.php';
$user = require_permission('manage_content');

$type = clean_string($_GET['type'] ?? '', 40);
$sql = 'SELECT id, title, slug, type, excerpt, body, featured_image, media_json, is_published, published_at, created_at, updated_at FROM content_posts';
$params = [];
if ($type !== '') {
    $sql .= ' WHERE type = ?';
    $params[] = $type;
}
$sql .= ' ORDER BY created_at DESC LIMIT 200';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$posts = [];
foreach ($stmt->fetchAll() as $row) {
    $row['id'] = (int)$row['id'];
    $row['is_published'] = (int)$row['is_published'];
    $row['media'] = json_decode($row['media_json'] ?? '[]', true) ?: [];
    unset($row['media_json']);
    $posts[] = $row;
}
json_response(['success'=>true,'posts'=>$posts,'user'=>public_user_payload($user)]);
