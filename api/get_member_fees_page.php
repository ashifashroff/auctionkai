<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$auctionId = (int)($_GET['auction_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, min(100, (int)($_GET['per_page'] ?? 10)));
$search = trim($_GET['search'] ?? '');

if (!$auctionId) {
    echo json_encode(['success' => false, 'message' => 'Missing auction ID']);
    exit;
}

// Verify auction belongs to user
$stmt = $db->prepare("SELECT id FROM auction WHERE id=? AND user_id=?");
$stmt->execute([$auctionId, $userId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Auction not found']);
    exit;
}

$offset = ($page - 1) * $perPage;

// Build WHERE
$where = ["mf.auction_id = ?"];
$params = [$auctionId];

if ($search !== '') {
    $where[] = "(m.name LIKE ? OR mf.fee_name LIKE ? OR mf.notes LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSQL = implode(' AND ', $where);

// Count
$countSQL = "SELECT COUNT(*) FROM member_fees mf JOIN members m ON mf.member_id = m.id WHERE {$whereSQL}";
$stmt = $db->prepare($countSQL);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// Fetch
$dataSQL = "SELECT mf.*, m.name as member_name FROM member_fees mf JOIN members m ON mf.member_id = m.id WHERE {$whereSQL} ORDER BY m.name ASC, mf.created_at ASC LIMIT {$perPage} OFFSET {$offset}";
$stmt = $db->prepare($dataSQL);
$stmt->execute($params);
$fees = $stmt->fetchAll();

$lastPage = max(1, ceil($total / $perPage));

echo json_encode([
    'success' => true,
    'total' => $total,
    'page' => $page,
    'lastPage' => $lastPage,
    'fees' => $fees,
]);
