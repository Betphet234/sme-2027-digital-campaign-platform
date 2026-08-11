<?php
require_once __DIR__ . '/../../backend/bootstrap.php';
$user = require_permission('view_stats');

$stats = ['applications'=>0,'needs'=>0,'volunteers'=>0,'supporters'=>0,'messages'=>0,'donors'=>0];
$stmt = db()->query('SELECT dataset, COUNT(*) AS total FROM submissions GROUP BY dataset');
foreach ($stmt->fetchAll() as $row) {
    if (isset($stats[$row['dataset']]) && can_view_dataset($user, $row['dataset'])) {
        $stats[$row['dataset']] = (int)$row['total'];
    }
}
json_response(['success'=>true,'stats'=>$stats,'user'=>public_user_payload($user)]);
