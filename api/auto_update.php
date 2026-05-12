<?php
/**
 * Auto-updater — downloads and installs the latest GitHub release.
 * Called from Admin Panel → Updates tab.
 */
require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/activity.php';

header('Content-Type: application/json');

// Only POST, only admin (api_bootstrap handles CSRF + auth)
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$action = ($GLOBALS['_json_input']['action'] ?? '') ?: ($_GET['action'] ?? '');

switch ($action) {

    case 'install':
        echo installUpdate($db, $userId);
        exit;

    case 'check':
    default:
        echo checkForUpdate($db);
        exit;
}

// ── Check for update ──────────────────────────
function checkForUpdate(PDO $db): string {
    try {
        $repo = 'ashifashroff/auctionkai';
        $url = "https://api.github.com/repos/{$repo}/releases/latest";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'AuctionKai-UpdateChecker/1.0',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return json_encode(['success' => false, 'message' => 'Could not reach GitHub API']);
        }

        $release = json_decode($response, true);
        if (!$release || empty($release['tag_name'])) {
            return json_encode(['success' => false, 'message' => 'Invalid release data']);
        }

        $latestVersion = ltrim($release['tag_name'], 'v');
        $currentVersion = ltrim(APP_VERSION, 'v');

        $hasUpdate = version_compare($latestVersion, $currentVersion, '>');

        // Cache result
        $cache = [
            'tag' => $release['tag_name'],
            'version' => $latestVersion,
            'name' => $release['name'] ?? '',
            'body' => $release['body'] ?? '',
            'url' => $release['html_url'] ?? '',
            'zipball_url' => $release['zipball_url'] ?? '',
            'published_at' => $release['published_at'] ?? '',
            'checked_at' => date('Y-m-d H:i:s'),
        ];

        $db->prepare("INSERT INTO settings (`key`, `value`) VALUES ('update_check_cache', ?) ON DUPLICATE KEY UPDATE value = ?")
           ->execute([json_encode($cache), json_encode($cache)]);

        if ($hasUpdate) {
            $db->prepare("DELETE FROM settings WHERE `key` = 'update_dismissed_version'")->execute();
        }

        return json_encode([
            'success' => true,
            'has_update' => $hasUpdate,
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'release' => $cache,
        ]);

    } catch (Exception $e) {
        return json_encode(['success' => false, 'message' => 'Update check failed: ' . $e->getMessage()]);
    }
}

// ── Install update ────────────────────────────
function installUpdate(PDO $db, int $userId): string {
    // Prevent concurrent installs
    $lockFile = sys_get_temp_dir() . '/ak_update.lock';
    if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 300) {
        return json_encode(['success' => false, 'message' => 'Update already in progress. Please wait.']);
    }
    file_put_contents($lockFile, time());

    try {
        // ── Step 0: Pre-update backup ───────────────
        $appRoot = dirname(__DIR__);
        $backupDir = $appRoot . '/backups/pre_update';
        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

        // 0a. Snapshot current project files (excluding vendor, .git, backups)
        $snapshotLabel = 'snapshot_v' . ltrim(APP_VERSION, 'v') . '_' . date('Y-m-d_His');
        $snapshotFile = $backupDir . '/' . $snapshotLabel . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($snapshotFile, ZipArchive::CREATE) === true) {
            $skipDirs = ['vendor', '.git', 'backups'];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($appRoot, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                $rel = substr($item->getPathname(), strlen($appRoot) + 1);
                $top = explode('/', $rel)[0];
                if (in_array($top, $skipDirs)) continue;
                if ($item->isDir()) {
                    $zip->addEmptyDir($rel);
                } else {
                    $zip->addFile($item->getPathname(), $rel);
                }
            }
            $zip->close();
        }

        // 0b. Database backup
        $dbBackupFile = $backupDir . '/db_v' . ltrim(APP_VERSION, 'v') . '_' . date('Y-m-d_His') . '.sql';
        $dbBackupData = generateDbBackup($db);
        file_put_contents($dbBackupFile, $dbBackupData);

        // 0c. Schedule cleanup of backups older than 14 days
        // (handled by cleanup_expired.php or on next update)
        cleanupOldBackups($backupDir, 14);

        // 1. Get latest release info
        $stmt = $db->query("SELECT value FROM settings WHERE `key` = 'update_check_cache'");
        $cacheJson = $stmt ? $stmt->fetchColumn() : null;
        $cache = $cacheJson ? json_decode($cacheJson, true) : null;

        if (!$cache || empty($cache['zipball_url'])) {
            // Fetch fresh
            $repo = 'ashifashroff/auctionkai';
            $url = "https://api.github.com/repos/{$repo}/releases/latest";
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'AuctionKai-UpdateChecker/1.0',
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            $release = json_decode($response, true);
            if (!$release || empty($release['zipball_url'])) {
                @unlink($lockFile);
                return json_encode(['success' => false, 'message' => 'Could not fetch release info from GitHub']);
            }
            $zipUrl = $release['zipball_url'];
            $newVersion = ltrim($release['tag_name'], 'v');
        } else {
            $zipUrl = $cache['zipball_url'];
            $newVersion = $cache['version'] ?? 'unknown';
        }

        $currentVersion = ltrim(APP_VERSION, 'v');

        if (!version_compare($newVersion, $currentVersion, '>')) {
            @unlink($lockFile);
            return json_encode(['success' => false, 'message' => 'Already on the latest version (v' . $currentVersion . ')']);
        }

        // 2. Download the zip
        $tempDir = sys_get_temp_dir() . '/ak_update_' . uniqid();
        mkdir($tempDir, 0755, true);
        $zipFile = $tempDir . '/release.zip';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $zipUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'AuctionKai-UpdateChecker/1.0',
        ]);
        $zipData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($zipData === false || $httpCode !== 200) {
            cleanup($tempDir, $lockFile);
            return json_encode(['success' => false, 'message' => 'Failed to download update from GitHub']);
        }

        file_put_contents($zipFile, $zipData);

        // 3. Extract
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            cleanup($tempDir, $lockFile);
            return json_encode(['success' => false, 'message' => 'Failed to open update archive']);
        }

        $extractDir = $tempDir . '/extracted';
        mkdir($extractDir, 0755, true);
        $zip->extractTo($extractDir);
        $zip->close();

        // GitHub zipballs have a single root directory like "auctionkai-abc1234/"
        $extractedDirs = glob($extractDir . '/*', GLOB_ONLYDIR);
        if (empty($extractedDirs)) {
            cleanup($tempDir, $lockFile);
            return json_encode(['success' => false, 'message' => 'Invalid archive structure']);
        }
        $sourceDir = $extractedDirs[0];

        // 4. Protected files/dirs that must NOT be overwritten
        $protected = [
            'config.php',
            '.env',
            'vendor',
            'backups',
            '.git',
        ];

        // 5. Copy files to app root
        $copied = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($sourceDir) + 1);

            // Skip protected files
            $topDir = explode('/', $relativePath)[0];
            if (in_array($topDir, $protected) || in_array($relativePath, $protected)) {
                continue;
            }

            $dest = $appRoot . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($dest)) mkdir($dest, 0755, true);
            } else {
                $destDir = dirname($dest);
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                copy($item->getPathname(), $dest);
                $copied++;
            }
        }

        // 6. Run migrations if migrations.sql exists in the update
        $migrationFile = $appRoot . '/migrations.sql';
        if (file_exists($migrationFile)) {
            try {
                $migrationContent = file_get_contents($migrationFile);
                if (!empty(trim($migrationContent))) {
                    // Run each statement (migrations are idempotent)
                    $statements = array_filter(
                        array_map('trim', explode(";\n", $migrationContent)),
                        fn($s) => !empty($s) && !str_starts_with($s, '--')
                    );
                    foreach ($statements as $sql) {
                        if (!empty(trim($sql))) {
                            $db->exec($sql);
                        }
                    }
                }
            } catch (Exception $e) {
                // Log but don't fail — migrations may already be applied
                error_log('[AuctionKai] Migration notice during update: ' . $e->getMessage());
            }
        }

        // 7. Clear OPcache
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        // 8. Clear update cache so it re-checks
        $db->prepare("DELETE FROM settings WHERE `key` IN ('update_check_cache', 'update_dismissed_version')")->execute();

        // 9. Log
        logActivity($db, $userId, 'system.update', 'system', 0, "Updated from v{$currentVersion} to v{$newVersion} ({$copied} files)");

        // 10. Cleanup
        cleanup($tempDir, $lockFile);

        return json_encode([
            'success' => true,
            'message' => "Updated to v{$newVersion}! ({$copied} files updated). Pre-update snapshot & DB backup saved in backups/pre_update/ — auto-deleted after 14 days.",
            'old_version' => $currentVersion,
            'new_version' => $newVersion,
            'files_updated' => $copied,
        ]);

    } catch (Exception $e) {
        @unlink($lockFile);
        logError($db, 'error', 'Auto-update failed: ' . $e->getMessage(), __FILE__, __LINE__);
        return json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
    }
}

function cleanup(string $tempDir, string $lockFile): void {
    // Remove temp directory
    if (is_dir($tempDir)) {
        $it = new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($tempDir);
    }
    @unlink($lockFile);
}

/**
 * Generate database backup SQL string.
 */
function generateDbBackup(PDO $db): string {
    $out = "-- AuctionKai Pre-Update Database Backup\n";
    $out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET NAMES utf8mb4;\n\n";

    $tables = ['users', 'auction', 'members', 'vehicles', 'fees', 'custom_deductions', 'member_fees', 'payment_status', 'password_resets', 'settings', 'activity_log', 'statement_links', 'statement_history', 'login_history', 'error_logs'];

    foreach ($tables as $table) {
        try {
            $check = $db->query("SHOW TABLES LIKE '$table'")->fetch();
            if (!$check) continue;

            $out .= "-- Table: $table\n";
            $createStmt = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $out .= "DROP TABLE IF EXISTS `$table`;\n" . $createStmt[1] . ";\n\n";

            $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
            if (empty($rows)) { $out .= "-- (no data)\n\n"; continue; }

            $cols = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            $colList = '`' . implode('`, `', $cols) . '`';

            $batch = [];
            foreach ($rows as $row) {
                $values = array_map(fn($v) => $v === null ? 'NULL' : $db->quote((string)$v), $row);
                $batch[] = '(' . implode(', ', $values) . ')';
                if (count($batch) >= 100) {
                    $out .= "INSERT INTO `$table` ($colList) VALUES\n" . implode(",\n", $batch) . ";\n";
                    $batch = [];
                }
            }
            if (!empty($batch)) {
                $out .= "INSERT INTO `$table` ($colList) VALUES\n" . implode(",\n", $batch) . ";\n";
            }
            $out .= "\n";
        } catch (Exception $e) {
            $out .= "-- Error backing up $table: " . $e->getMessage() . "\n\n";
        }
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
}

/**
 * Delete pre-update backups older than N days.
 */
function cleanupOldBackups(string $dir, int $days): void {
    if (!is_dir($dir)) return;
    $cutoff = time() - ($days * 86400);
    foreach (glob($dir . '/*') as $file) {
        if (is_file($file) && filemtime($file) < $cutoff) {
            @unlink($file);
        }
    }
}
