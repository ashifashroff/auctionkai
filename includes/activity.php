<?php
/**
 * Log an activity to the database
 */
function logActivity(
    PDO $db,
    int $userId,
    string $action,
    string $entityType = '',
    int $entityId = 0,
    string $description = ''
): void {
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
        $ip = trim(explode(',', $ip)[0]);

        $stmt = $db->prepare("
            INSERT INTO activity_log
            (user_id, action, entity_type, entity_id, description, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $action,
            $entityType ?: null,
            $entityId ?: null,
            $description ?: null,
            $ip ?: null,
        ]);
    } catch (Exception $e) {
        error_log('Activity log error: ' . $e->getMessage());
    }
}

function getActivityIcon(string $action): string {
    $icons = [
        'login' => '🔑',
        'logout' => '🚪',
        'register' => '👤',
        'auction.create' => '🏷',
        'auction.update' => '✏️',
        'auction.delete' => '🗑',
        'member.add' => '👥',
        'member.update' => '✏️',
        'member.remove' => '🗑',
        'vehicle.add' => '🚗',
        'vehicle.update' => '✏️',
        'vehicle.delete' => '🗑',
        'vehicle.sold' => '✓',
        'vehicle.unsold' => '✗',
        'pdf.generate' => '📄',
        'email.send' => '✉',
        'backup.download' => '💾',
        'admin.user_role' => '⚙️',
        'admin.user_disable' => '🚫',
        'admin.user_enable' => '✅',
        'settings.email' => '📧',
        'password.change' => '🔒',
        'password.reset' => '🔓',
        'login.failed' => '❌',
        'admin.clear_logs' => '🧹',
    ];
    return $icons[$action] ?? '📋';
}

function getActivityColor(string $action): string {
    if (str_contains($action, 'delete') ||
        str_contains($action, 'remove') ||
        str_contains($action, 'disable') ||
        str_contains($action, 'failed')) {
        return 'text-ak-red';
    }
    if (str_contains($action, 'add') ||
        str_contains($action, 'create') ||
        str_contains($action, 'register') ||
        str_contains($action, 'enable')) {
        return 'text-ak-green';
    }
    if (str_contains($action, 'login') ||
        str_contains($action, 'admin')) {
        return 'text-ak-gold';
    }
    return 'text-ak-text2';
}

function getActivityBorder(string $action): string {
    if (str_contains($action, 'delete') ||
        str_contains($action, 'remove') ||
        str_contains($action, 'disable') ||
        str_contains($action, 'failed')) {
        return 'border-l-2 border-l-ak-red';
    }
    if (str_contains($action, 'add') ||
        str_contains($action, 'create') ||
        str_contains($action, 'register') ||
        str_contains($action, 'enable')) {
        return 'border-l-2 border-l-ak-green';
    }
    if (str_contains($action, 'login') ||
        str_contains($action, 'admin')) {
        return 'border-l-2 border-l-ak-gold';
    }
    return 'border-l-2 border-l-ak-border';
}
