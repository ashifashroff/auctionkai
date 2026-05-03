<?php
require_once __DIR__ . "/../includes/api_bootstrap.php";
require_once '../includes/auth_check.php';
require_once '../includes/helpers.php';


$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$allowedPerPage = [10, 25, 50, 100];
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, $allowedPerPage)) {
    $perPage = 25;
}
$search = trim($_GET['search'] ?? '');

// Build search condition (sanitize LIKE wildcards)
$searchWhere = '';
$searchParams = [];
if ($search !== '' && mb_strlen($search) >= 2) {
    $search = substr($search, 0, 100);
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    $searchWhere = "AND (
        m.name LIKE ? OR
        m.phone LIKE ? OR
        m.email LIKE ?
    )";
    $searchParams = [$like, $like, $like];
}

// Count total
$countSql = "SELECT COUNT(*) FROM members m WHERE m.user_id = ? $searchWhere";
$countStmt = $db->prepare($countSql);
$countStmt->execute(array_merge([$userId], $searchParams));
$total = (int)$countStmt->fetchColumn();
$lastPage = max(1, ceil($total / $perPage));
$page = min($page, $lastPage);
$offset = ($page - 1) * $perPage;

// Fetch members for this page
$sql = "
    SELECT m.*,
           (SELECT COUNT(*) FROM vehicles v WHERE v.member_id = m.id) as vehicle_count,
           (SELECT COUNT(*) FROM vehicles v WHERE v.member_id = m.id AND v.sold = 1) as sold_count
    FROM members m
    WHERE m.user_id = ?
    $searchWhere
    ORDER BY m.id DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
$stmt->execute(array_merge([$userId], $searchParams, [$perPage, $offset]));
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active auction for statement calc
$auctionId = (int)($_GET['auction_id'] ?? 0);
$auction = null;
if ($auctionId) {
    $aStmt = $db->prepare("SELECT * FROM auction WHERE id=? AND user_id=?");
    $aStmt->execute([$auctionId, $userId]);
    $auction = $aStmt->fetch();
}

// Add net payout per member
foreach ($members as &$m) {
    if ($auction) {
        $vStmt = $db->prepare("SELECT * FROM vehicles WHERE member_id=? AND auction_id=?");
        $vStmt->execute([(int)$m['id'], $auctionId]);
        $vehicles = $vStmt->fetchAll();
        $s = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_fee'] ?? 3300));
        $m['net_payout'] = $s['netPayout'];
        $m['sold_count_auction'] = $s['count'];
    } else {
        $m['net_payout'] = 0;
        $m['sold_count_auction'] = 0;
    }
}

echo json_encode([
    'success' => true,
    'members' => $members,
    'page' => $page,
    'lastPage' => $lastPage,
    'total' => $total,
    'perPage' => $perPage,
    'search' => $search,
]);
