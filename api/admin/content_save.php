<?php
require_once __DIR__ . '/../../backend/bootstrap.php';
$user = require_permission('manage_content');
require_post();

$id = (int)($_POST['id'] ?? 0);
$title = clean_string($_POST['title'] ?? '', 255);
$type = clean_string($_POST['type'] ?? 'news', 40);
$excerpt = clean_string($_POST['excerpt'] ?? '', 1000);
$body = clean_string($_POST['body'] ?? '', 50000);
$isPublished = !empty($_POST['is_published']) ? 1 : 0;
$publishedAt = $isPublished ? date('Y-m-d H:i:s') : null;

$allowedTypes = ['news','press_statement','speech','article','community_visit','endorsement','photograph','video','interview','campaign_material'];
if (!in_array($type, $allowedTypes, true)) $type = 'news';
if ($title === '') json_response(['success'=>false,'message'=>'Title is required.'], 422);

$files = save_uploaded_files();
$featuredImage = clean_string($_POST['existing_featured_image'] ?? '', 255);
$media = [];

if ($id > 0) {
    $stmt = db()->prepare('SELECT featured_image, media_json FROM content_posts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $featuredImage = $featuredImage ?: ($existing['featured_image'] ?? '');
        $media = json_decode($existing['media_json'] ?? '[]', true) ?: [];
    }
}

foreach ($files as $file) {
    if ($file['field'] === 'featured_image') {
        $featuredImage = $file['relative_path'];
    } else {
        $media[] = $file;
    }
}

$slug = unique_slug($title, $id ?: null);

if ($id > 0) {
    $stmt = db()->prepare('UPDATE content_posts SET title=?, slug=?, type=?, excerpt=?, body=?, featured_image=?, media_json=?, is_published=?, published_at=IF(?=1 AND published_at IS NULL, NOW(), published_at), updated_at=NOW() WHERE id=?');
    $stmt->execute([$title, $slug, $type, $excerpt, $body, $featuredImage, json_encode($media, JSON_UNESCAPED_UNICODE), $isPublished, $isPublished, $id]);
    audit_log('content_updated', 'content_post', (string)$id, ['title'=>$title]);
} else {
    $stmt = db()->prepare('INSERT INTO content_posts (title, slug, type, excerpt, body, featured_image, media_json, is_published, published_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$title, $slug, $type, $excerpt, $body, $featuredImage, json_encode($media, JSON_UNESCAPED_UNICODE), $isPublished, $publishedAt, $user['id']]);
    $id = (int)db()->lastInsertId();
    audit_log('content_created', 'content_post', (string)$id, ['title'=>$title]);
}

json_response(['success'=>true,'message'=>'Content saved.','post'=>['id'=>$id,'title'=>$title,'slug'=>$slug]]);
