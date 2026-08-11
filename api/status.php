<?php
require_once __DIR__ . '/../backend/bootstrap.php';
$reference = strtoupper(clean_string($_GET['reference'] ?? '', 50));
$phone = normalize_phone($_GET['phone'] ?? '');
if ($reference === '' || $phone === '') json_response(['success'=>false,'message'=>'Reference number and phone number are required.'], 422);
$stmt = db()->prepare('SELECT reference, dataset, type, category, status, name, phone, community, ward, created_at FROM submissions WHERE reference = ? AND phone_normalized = ? LIMIT 1');
$stmt->execute([$reference, $phone]);
$row = $stmt->fetch();
if (!$row) json_response(['success'=>false,'message'=>'No matching record found. Please confirm your reference number and phone number.'], 404);
json_response(['success'=>true,'submission'=>[
    'reference'=>$row['reference'], 'dataset'=>$row['dataset'], 'type'=>$row['type'], 'category'=>$row['category'], 'status'=>$row['status'], 'name'=>$row['name'], 'phone'=>$row['phone'], 'community'=>$row['community'], 'ward'=>$row['ward'], 'createdAt'=>date('c', strtotime($row['created_at']))
]]);
