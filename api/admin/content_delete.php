<?php
require_once __DIR__ . '/../../backend/bootstrap.php';
$user = require_permission('manage_content');
require_post();
$data = input_json();
$id = (int)($data['id'] ?? 0);
if ($id <= 0) json_response(['success'=>false,'message'=>'Invalid content item.'], 422);
$stmt = db()->prepare('DELETE FROM content_posts WHERE id = ?');
$stmt->execute([$id]);
audit_log('content_deleted', 'content_post', (string)$id);
json_response(['success'=>true,'message'=>'Content deleted.']);
