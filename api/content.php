<?php
require_once __DIR__ . '/../backend/bootstrap.php';

$type = clean_string($_GET['type'] ?? '', 40);
$slug = clean_string($_GET['slug'] ?? '', 255);
$id = (int)($_GET['id'] ?? 0);

$params = [];
$sql = 'SELECT id, title, slug, type, excerpt, body, featured_image, media_json, is_published, published_at, created_at FROM content_posts WHERE is_published = 1';

if ($slug !== '') {
    $sql .= ' AND slug = ?';
    $params[] = $slug;
} elseif ($id > 0) {
    $sql .= ' AND id = ?';
    $params[] = $id;
} elseif ($type !== '') {
    $sql .= ' AND type = ?';
    $params[] = $type;
}

$sql .= ' ORDER BY COALESCE(published_at, created_at) DESC LIMIT 100';
$stmt = db()->prepare($sql);
$stmt->execute($params);

$posts = [];
foreach ($stmt->fetchAll() as $row) {
    $posts[] = [
        'id'=>(int)$row['id'],
        'title'=>$row['title'],
        'slug'=>$row['slug'],
        'type'=>$row['type'],
        'excerpt'=>$row['excerpt'],
        'body'=>$row['body'],
        'featured_image'=>$row['featured_image'],
        'media'=>json_decode($row['media_json'] ?? '[]', true) ?: [],
        'published_at'=>$row['published_at'],
        'created_at'=>$row['created_at'],
    ];
}

json_response(['success'=>true,'posts'=>$posts]);
