<?php
require_once __DIR__ . '/../../backend/bootstrap.php';
$user = require_admin();

$dataset = clean_string($_GET['dataset'] ?? 'applications', 40);
if (!allowed_dataset($dataset)) json_response(['success'=>false,'message'=>'Invalid dataset.'], 422);
if (!can_view_dataset($user, $dataset)) json_response(['success'=>false,'message'=>'Your account cannot view this dataset.'], 403);

$q = clean_string($_GET['q'] ?? '', 200);
$status = clean_string($_GET['status'] ?? '', 80);
$sql = 'SELECT s.* FROM submissions s WHERE s.dataset = ?';
$params = [$dataset];

if ($status !== '') {
    $sql .= ' AND s.status = ?';
    $params[] = $status;
}

if ($q !== '') {
    $sql .= ' AND (s.reference LIKE ? OR s.name LIKE ? OR s.phone LIKE ? OR s.email LIKE ? OR s.community LIKE ? OR s.ward LIKE ? OR s.category LIKE ? OR s.payload_json LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
}

$sql .= ' ORDER BY s.created_at DESC LIMIT 500';
$stmt = db()->prepare($sql);
$stmt->execute($params);

$records = [];
foreach ($stmt->fetchAll() as $r) {
    $dup = duplicate_details($r);
    $records[] = [
        'reference'=>$r['reference'],
        'dataset'=>$r['dataset'],
        'type'=>$r['type'],
        'category'=>$r['category'],
        'status'=>$r['status'],
        'name'=>$r['name'],
        'phone'=>$r['phone'],
        'email'=>$r['email'],
        'community'=>$r['community'],
        'ward'=>$r['ward'],
        'internal_notes'=>$r['internal_notes'],
        'duplicate_count'=>(int)$dup['count'],
        'duplicate_reasons'=>$dup['reasons'],
        'createdAt'=>date('c', strtotime($r['created_at']))
    ];
}

json_response([
    'success'=>true,
    'records'=>$records,
    'user'=>public_user_payload($user),
    'canUpdate'=>can_update_dataset($user, $dataset)
]);
