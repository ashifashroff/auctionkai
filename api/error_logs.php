<?php
require_once '../includes/auth_check.php';
require_once '../includes/db.php';
require_once '../includes/activity.php';
require_once '../includes/error_handler.php';

header('Content-Type: application/json');

// Admin-only
if (($currentUser['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'resolve':
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Missing error ID']);
                exit;
            }
            $db->prepare("UPDATE error_logs SET is_resolved = 1, resolved_at = NOW(), resolved_by = ? WHERE id = ?")
               ->execute([$userId, $id]);
            logActivity($db, $userId, 'admin.error_resolve', 'error_log', $id, "Resolved error #{$id}");
            echo json_encode(['success' => true]);
            break;

        case 'resolve_all':
            $severity = $data['severity'] ?? null;
            if ($severity && in_array($severity, ['error', 'warning', 'notice', 'critical'])) {
                $db->prepare("UPDATE error_logs SET is_resolved = 1, resolved_at = NOW(), resolved_by = ? WHERE is_resolved = 0 AND severity = ?")
                   ->execute([$userId, $severity]);
            } else {
                $db->prepare("UPDATE error_logs SET is_resolved = 1, resolved_at = NOW(), resolved_by = ? WHERE is_resolved = 0")
                   ->execute([$userId]);
            }
            logActivity($db, $userId, 'admin.error_resolve_all', 'error_log', 0, "Resolved all errors" . ($severity ? " ({$severity})" : ''));
            echo json_encode(['success' => true]);
            break;

        case 'delete_old':
            $days = (int)($data['days'] ?? 30);
            $stmt = $db->prepare("DELETE FROM error_logs WHERE is_resolved = 1 AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$days]);
            $deleted = $stmt->rowCount();
            logActivity($db, $userId, 'admin.error_cleanup', 'error_log', 0, "Deleted {$deleted} resolved errors older than {$days} days");
            echo json_encode(['success' => true, 'deleted' => $deleted]);
            break;

        case 'list':
        default:
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;
            $severity = $_GET['severity'] ?? null;
            $resolved = $_GET['resolved'] ?? null;

            $where = [];
            $params = [];
            if ($severity && in_array($severity, ['error', 'warning', 'notice', 'critical'])) {
                $where[] = 'severity = ?';
                $params[] = $severity;
            }
            if ($resolved === '0') {
                $where[] = 'is_resolved = 0';
            } elseif ($resolved === '1') {
                $where[] = 'is_resolved = 1';
            }
            $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $total = $db->prepare("SELECT COUNT(*) FROM error_logs {$whereSQL}");
            $total->execute($params);
            $totalCount = (int)$total->fetchColumn();

            $stmt = $db->prepare("SELECT * FROM error_logs {$whereSQL} ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->execute($params);
            $errors = $stmt->fetchAll();

            // Summary counts
            $summary = $db->query("
                SELECT 
                    severity,
                    COUNT(*) as total,
                    SUM(is_resolved = 0) as unresolved
                FROM error_logs
                GROUP BY severity
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $errors,
                'total' => $totalCount,
                'page' => $page,
                'per_page' => $perPage,
                'summary' => $summary,
            ]);
            break;
    }
} catch (Exception $e) {
    logError($db, 'error', 'Error log API error: ' . $e->getMessage(), __FILE__, __LINE__);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
