<?php
require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/activity.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
$filter = $_GET['filter'] ?? 'all';

$filterWhere = '';
if ($filter === 'logins') $filterWhere = " WHERE al.action LIKE '%login%' OR al.action LIKE '%logout%'";
elseif ($filter === 'vehicles') $filterWhere = " WHERE al.action LIKE '%vehicle%'";
elseif ($filter === 'members') $filterWhere = " WHERE al.action LIKE '%member%'";
elseif ($filter === 'auctions') $filterWhere = " WHERE al.action LIKE '%auction%'";
elseif ($filter === 'admin') $filterWhere = " WHERE al.action LIKE '%admin%' OR al.action LIKE '%backup%'";

$total = (int)$db->query("SELECT COUNT(*) FROM activity_log al" . $filterWhere)->fetchColumn();
$lastPage = max(1, ceil($total / $perPage));

$stmt = $db->prepare("
    SELECT al.*, u.name as user_name, u.username as username
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    {$filterWhere}
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rows = [];
foreach ($logs as $l) {
    $rows[] = [
        'time'        => date('Y-m-d H:i', strtotime($l['created_at'])),
        'username'    => $l['user_id'] ? ($l['username'] ?? '') : '',
        'user_name'   => $l['user_id'] ? ($l['user_name'] ?? '') : 'System',
        'action'      => $l['action'],
        'icon'        => getActivityIcon($l['action']),
        'color'       => getActivityColor($l['action']),
        'border'      => getActivityBorder($l['action']),
        'entity'      => trim(($l['entity_type'] ?? '') . (($l['entity_id'] ?? '') ? ' #' . $l['entity_id'] : '')),
        'description' => $l['description'] ?? '',
        'ip'          => $l['ip_address'] ?? '',
    ];
}

echo json_encode([
    'success'  => true,
    'total'    => $total,
    'page'     => $page,
    'lastPage' => $lastPage,
    'filter'   => $filter,
    'rows'     => $rows,
]);
