<?php
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$action = $_GET['action'] ?? 'all';
$payment = $_GET['payment'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build WHERE clauses
$where = ["1=1"];
$params = [];

if (in_array($action, ['pdf', 'email'])) {
    $where[] = "sh.action = ?";
    $params[] = $action;
}

if ($payment === 'paid') {
    $where[] = "ps.status = 'paid'";
} elseif ($payment === 'unpaid') {
    $where[] = "(ps.status IS NULL OR ps.status = 'unpaid')";
} elseif ($payment === 'partial') {
    $where[] = "ps.status = 'partial'";
}

if ($search !== '') {
    $where[] = "(m.name LIKE ? OR u.name LIKE ? OR u.username LIKE ? OR a.name LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSQL = implode(' AND ', $where);

// Count total
$countSQL = "SELECT COUNT(*) FROM statement_history sh
    JOIN members m ON sh.member_id = m.id
    JOIN users u ON sh.user_id = u.id
    JOIN auction a ON sh.auction_id = a.id
    LEFT JOIN payment_status ps ON ps.auction_id = sh.auction_id AND ps.member_id = sh.member_id
    WHERE {$whereSQL}";

$stmt = $db->prepare($countSQL);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// Fetch rows
$dataSQL = "SELECT sh.*, m.name as member_name, u.name as user_name, u.username, a.name as auction_name,
    ps.status as payment_status
    FROM statement_history sh
    JOIN members m ON sh.member_id = m.id
    JOIN users u ON sh.user_id = u.id
    JOIN auction a ON sh.auction_id = a.id
    LEFT JOIN payment_status ps ON ps.auction_id = sh.auction_id AND ps.member_id = sh.member_id
    WHERE {$whereSQL}
    ORDER BY sh.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}";

$stmt = $db->prepare($dataSQL);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$lastPage = max(1, ceil($total / $perPage));

echo json_encode([
    'total' => $total,
    'page' => $page,
    'lastPage' => $lastPage,
    'rows' => array_map(function($r) {
        $isEmail = $r['action'] === 'email';
        $payStatus = $r['payment_status'] ?? 'unpaid';
        $payClass = match($payStatus) {
            'paid' => 'bg-ak-green/15 text-ak-green',
            'partial' => 'bg-yellow-500/15 text-yellow-400',
            default => 'bg-ak-red/10 text-ak-red',
        };
        $payIcon = match($payStatus) {
            'paid' => '✓ Paid',
            'partial' => '◑ Partial',
            default => '✗ Unpaid',
        };
        return [
            'time' => date('Y-m-d H:i', strtotime($r['created_at'])),
            'user_name' => $r['user_name'],
            'username' => $r['username'],
            'member_name' => $r['member_name'],
            'auction_name' => $r['auction_name'],
            'action' => $r['action'],
            'action_icon' => $isEmail ? '✉️ Email' : '📄 PDF',
            'action_class' => $isEmail ? 'bg-ak-gold/15 text-ak-gold' : 'bg-ak-text2/10 text-ak-text2',
            'net_payout' => $r['net_payout'],
            'ip_address' => $r['ip_address'] ?? '',
            'payment_status' => $payStatus,
            'payment_icon' => $payIcon,
            'payment_class' => $payClass,
        ];
    }, $rows),
]);
