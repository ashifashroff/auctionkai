<?php
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/activity.php';

// ── Collect System Info ───────────────────────

$phpVersion = PHP_VERSION;
$phpOS = PHP_OS;
$phpSAPI = PHP_SAPI;
$maxUpload = ini_get('upload_max_filesize');
$maxPost = ini_get('post_max_size');
$memoryLimit = ini_get('memory_limit');
$maxExecTime = ini_get('max_execution_time');
$sessionHandler = ini_get('session.save_handler');
$timezone = date_default_timezone_get();

// PHP Extensions
$requiredExt = [
    'pdo' => 'PDO (Database)',
    'pdo_mysql' => 'PDO MySQL',
    'mbstring' => 'Multibyte String',
    'json' => 'JSON',
    'curl' => 'cURL (Email)',
    'openssl' => 'OpenSSL (Security)',
    'fileinfo' => 'File Info (Uploads)',
    'zip' => 'ZIP (Backup)',
    'intl' => 'Internationalization',
];
$extStatus = [];
foreach ($requiredExt as $ext => $label) {
    $extStatus[$ext] = ['label' => $label, 'loaded' => extension_loaded($ext)];
}

// Disk Space
$diskTotal = disk_total_space(__DIR__);
$diskFree = disk_free_space(__DIR__);
$diskUsed = $diskTotal - $diskFree;
$diskPct = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100) : 0;

function formatBytes(float $bytes): string {
    if ($bytes >= 1073741824) return round($bytes/1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes/1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes/1024, 2) . ' KB';
    return $bytes . ' B';
}

// Database Info
$dbInfo = [];
try {
    $dbInfo['version'] = $db->query("SELECT VERSION()")->fetchColumn();
    $dbInfo['size'] = $db->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn() . ' MB';

    $tables = $db->query("SELECT table_name, table_rows, ROUND((data_length + index_length) / 1024, 2) as size_kb FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_rows DESC")->fetchAll();
    $dbInfo['tables'] = $tables;

    $dbInfo['connections'] = $db->query("SHOW STATUS LIKE 'Threads_connected'")->fetch()['Value'] ?? '?';

    $uptime = (int)$db->query("SHOW STATUS LIKE 'Uptime'")->fetch()['Value'];
    $days = floor($uptime / 86400);
    $hours = floor(($uptime % 86400) / 3600);
    $minutes = floor(($uptime % 3600) / 60);
    $dbInfo['uptime'] = "{$days}d {$hours}h {$minutes}m";

    $dbInfo['status'] = 'connected';
} catch (Exception $e) {
    $dbInfo['status'] = 'error';
    $dbInfo['error'] = $e->getMessage();
}

// App Stats
$appStats = [];
try {
    $appStats['users'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $appStats['auctions'] = $db->query("SELECT COUNT(*) FROM auction")->fetchColumn();
    $appStats['members'] = $db->query("SELECT COUNT(*) FROM members")->fetchColumn();
    $appStats['vehicles'] = $db->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
    $appStats['logs'] = $db->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
    $appStats['payments'] = $db->query("SELECT COUNT(*) FROM payment_status WHERE status='paid'")->fetchColumn();
    $appStats['errors'] = $db->query("SELECT COUNT(*) FROM error_logs WHERE is_resolved = 0")->fetchColumn() ?: 0;
} catch (Exception $e) {
    $appStats = [];
}

// Server Info
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$serverAddr = $_SERVER['SERVER_ADDR'] ?? 'Unknown';
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown';
$httpsEnabled = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

// Log health check access
logActivity($db, $userId, 'admin.health_check', 'system', 0, "Viewed system health check");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>System Health — AuctionKai Admin</title>
<link rel="stylesheet" href="../css/style.css?v=3.5">
<?php include '../css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen">

<!-- Topbar -->
<div class="bg-ak-bg2 border-b border-ak-border px-7 py-3 flex items-center gap-4 sticky top-0 z-50">
  <div>
    <div class="text-ak-gold font-bold text-lg">⚡ AuctionKai <span class="text-ak-muted text-sm font-normal">/ Admin / System Health</span></div>
  </div>
  <div class="ml-auto flex gap-3">
    <a href="index.php" class="btn btn-dark btn-sm">← Back to Admin</a>
    <a href="health.php" class="btn btn-gold btn-sm">🔄 Refresh</a>
  </div>
</div>

<div class="p-7 max-w-[1200px] mx-auto">

  <h2 class="text-lg font-bold mb-6">🔍 System Health Check <span class="text-ak-muted text-sm font-normal ml-2"><?= date('Y-m-d H:i:s') ?></span></h2>

  <!-- Overall Status -->
  <?php
  $allGood = $dbInfo['status'] === 'connected' && version_compare($phpVersion, '8.0.0', '>=') && $diskPct < 90 && extension_loaded('pdo_mysql');
  ?>
  <div class="rounded-xl p-5 mb-6 border flex items-center gap-4 <?= $allGood ? 'bg-ak-green/10 border-ak-green/30' : 'bg-ak-red/10 border-ak-red/30' ?>">
    <div class="text-4xl"><?= $allGood ? '✅' : '⚠️' ?></div>
    <div>
      <div class="font-bold text-lg <?= $allGood ? 'text-ak-green' : 'text-ak-red' ?>"><?= $allGood ? 'All Systems Operational' : 'Attention Required' ?></div>
      <div class="text-ak-muted text-sm">AuctionKai v3.7 · PHP <?= $phpVersion ?> · MySQL <?= $dbInfo['version'] ?? 'N/A' ?></div>
    </div>
  </div>

  <!-- App Stats Row -->
  <div class="grid grid-cols-3 md:grid-cols-7 gap-3 mb-6">
    <?php
    $statCards = [
      ['label' => 'Users', 'value' => $appStats['users'] ?? '?', 'icon' => '👤'],
      ['label' => 'Auctions', 'value' => $appStats['auctions'] ?? '?', 'icon' => '🏷'],
      ['label' => 'Members', 'value' => $appStats['members'] ?? '?', 'icon' => '👥'],
      ['label' => 'Vehicles', 'value' => $appStats['vehicles'] ?? '?', 'icon' => '🚗'],
      ['label' => 'Paid', 'value' => $appStats['payments'] ?? '?', 'icon' => '✓'],
      ['label' => 'Errors', 'value' => $appStats['errors'] ?? '?', 'icon' => '🚨'],
      ['label' => 'Log Entries', 'value' => $appStats['logs'] ?? '?', 'icon' => '📋'],
    ];
    foreach ($statCards as $card):
    ?>
    <div class="bg-ak-card rounded-xl p-4 border border-ak-border text-center">
      <div class="text-xl mb-1"><?= $card['icon'] ?></div>
      <div class="text-xl font-bold font-mono text-ak-gold"><?= $card['value'] ?></div>
      <div class="text-ak-muted text-[10px] mt-0.5"><?= $card['label'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

    <!-- PHP Info -->
    <div class="bg-ak-card rounded-xl border border-ak-border overflow-hidden">
      <div class="px-5 py-3 border-b border-ak-border bg-ak-infield"><h3 class="font-bold text-ak-text">🐘 PHP Environment</h3></div>
      <div class="p-5 space-y-2 text-sm">
        <?php
        $phpRows = [
          ['PHP Version', $phpVersion, version_compare($phpVersion, '8.0.0', '>=')],
          ['Operating System', $phpOS, true],
          ['SAPI', $phpSAPI, true],
          ['Timezone', $timezone, true],
          ['Memory Limit', $memoryLimit, true],
          ['Max Upload Size', $maxUpload, true],
          ['Max Post Size', $maxPost, true],
          ['Max Exec Time', $maxExecTime . 's', true],
          ['Session Handler', $sessionHandler, true],
          ['HTTPS', $httpsEnabled ? 'Enabled' : 'Disabled', $httpsEnabled],
        ];
        foreach ($phpRows as [$label, $value, $ok]):
        ?>
        <div class="flex justify-between items-center py-1.5 border-b border-ak-border/50 last:border-0">
          <span class="text-ak-muted"><?= $label ?></span>
          <span class="font-mono text-xs flex items-center gap-1.5 <?= $ok ? 'text-ak-text' : 'text-ak-red' ?>"><?= $ok ? '' : '⚠ ' ?><?= h((string)$value) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Database Info -->
    <div class="bg-ak-card rounded-xl border border-ak-border overflow-hidden">
      <div class="px-5 py-3 border-b border-ak-border bg-ak-infield">
        <h3 class="font-bold text-ak-text">🗄 Database
          <span class="ml-2 text-xs px-2 py-0.5 rounded-full font-mono <?= $dbInfo['status'] === 'connected' ? 'bg-ak-green/15 text-ak-green' : 'bg-ak-red/15 text-ak-red' ?>"><?= $dbInfo['status'] === 'connected' ? '● Connected' : '● Error' ?></span>
        </h3>
      </div>
      <div class="p-5 space-y-2 text-sm">
        <?php if ($dbInfo['status'] === 'connected'): ?>
          <?php
          $dbRows = [
            ['MySQL Version', $dbInfo['version'] ?? '?'],
            ['Database Size', $dbInfo['size'] ?? '?'],
            ['Connections', $dbInfo['connections'] ?? '?'],
            ['Uptime', $dbInfo['uptime'] ?? '?'],
          ];
          foreach ($dbRows as [$label, $value]):
          ?>
          <div class="flex justify-between items-center py-1.5 border-b border-ak-border/50 last:border-0">
            <span class="text-ak-muted"><?= $label ?></span>
            <span class="font-mono text-xs text-ak-text"><?= h((string)$value) ?></span>
          </div>
          <?php endforeach; ?>

          <?php if (!empty($dbInfo['tables'])): ?>
          <div class="mt-3 pt-3 border-t border-ak-border">
            <div class="text-[10px] uppercase tracking-wider text-ak-muted mb-2">Tables</div>
            <?php foreach ($dbInfo['tables'] as $t): ?>
            <div class="flex justify-between items-center py-1 text-xs">
              <span class="font-mono text-ak-text2"><?= h($t['table_name']) ?></span>
              <div class="flex gap-3 text-ak-muted"><span><?= number_format((int)$t['table_rows']) ?> rows</span><span class="font-mono"><?= $t['size_kb'] ?> KB</span></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

        <?php else: ?>
          <div class="text-ak-red text-sm">⚠ <?= h($dbInfo['error'] ?? 'Connection failed') ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Disk Space -->
    <div class="bg-ak-card rounded-xl border border-ak-border overflow-hidden">
      <div class="px-5 py-3 border-b border-ak-border bg-ak-infield"><h3 class="font-bold text-ak-text">💾 Disk Space</h3></div>
      <div class="p-5">
        <div class="flex justify-between text-sm mb-2">
          <span class="text-ak-muted">Used: <b class="text-ak-text"><?= formatBytes($diskUsed) ?></b></span>
          <span class="text-ak-muted">Free: <b class="text-ak-text"><?= formatBytes($diskFree) ?></b></span>
          <span class="text-ak-muted">Total: <b class="text-ak-text"><?= formatBytes($diskTotal) ?></b></span>
        </div>
        <div class="w-full bg-ak-infield rounded-full h-3 overflow-hidden">
          <div class="h-3 rounded-full transition-all <?= $diskPct >= 90 ? 'bg-ak-red' : ($diskPct >= 70 ? 'bg-yellow-500' : 'bg-ak-green') ?>" style="width: <?= $diskPct ?>%"></div>
        </div>
        <div class="text-right text-xs font-mono mt-1 <?= $diskPct >= 90 ? 'text-ak-red' : 'text-ak-muted' ?>"><?= $diskPct ?>% used<?= $diskPct >= 90 ? ' — ⚠ Disk almost full!' : '' ?></div>
        <?php if ($diskPct >= 90): ?>
        <div class="mt-3 bg-ak-red/10 border border-ak-red/30 rounded-lg p-3 text-ak-red text-xs">⚠ Disk space is critically low. Consider deleting old backups or upgrading storage.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- PHP Extensions -->
    <div class="bg-ak-card rounded-xl border border-ak-border overflow-hidden">
      <div class="px-5 py-3 border-b border-ak-border bg-ak-infield"><h3 class="font-bold text-ak-text">🔌 PHP Extensions</h3></div>
      <div class="p-5 space-y-2">
        <?php foreach ($extStatus as $ext => $info): ?>
        <div class="flex items-center justify-between py-1.5 border-b border-ak-border/50 last:border-0">
          <span class="text-sm text-ak-text2"><?= h($info['label']) ?></span>
          <span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $info['loaded'] ? 'bg-ak-green/15 text-ak-green' : 'bg-ak-red/15 text-ak-red' ?>"><?= $info['loaded'] ? '✓ Loaded' : '✗ Missing' ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <!-- Server Info -->
  <div class="bg-ak-card rounded-xl border border-ak-border overflow-hidden mb-6">
    <div class="px-5 py-3 border-b border-ak-border bg-ak-infield"><h3 class="font-bold text-ak-text">🖥 Server Information</h3></div>
    <div class="p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
      <?php
      $serverRows = [
        ['Server Software', $serverSoftware],
        ['Server Address', $serverAddr],
        ['Document Root', $docRoot],
        ['Current Time', date('Y-m-d H:i:s')],
      ];
      foreach ($serverRows as [$label, $value]):
      ?>
      <div>
        <div class="text-ak-muted text-xs mb-1"><?= $label ?></div>
        <div class="font-mono text-xs text-ak-text break-all"><?= h($value) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Error Logs -->
  <div class="bg-ak-card rounded-xl border border-ak-border overflow-hidden mb-6">
    <div class="px-5 py-3 border-b border-ak-border bg-ak-infield flex justify-between items-center">
      <h3 class="font-bold text-ak-text">🚨 Error Logs</h3>
      <div class="flex gap-2">
        <button onclick="loadErrorLogs('all')" class="btn btn-dark btn-sm text-[11px]" id="err-filter-all">All</button>
        <button onclick="loadErrorLogs('critical')" class="btn btn-dark btn-sm text-[11px]" id="err-filter-critical">🔴 Critical</button>
        <button onclick="loadErrorLogs('error')" class="btn btn-dark btn-sm text-[11px]" id="err-filter-error">🟠 Error</button>
        <button onclick="loadErrorLogs('warning')" class="btn btn-dark btn-sm text-[11px]" id="err-filter-warning">🟡 Warning</button>
        <button onclick="loadErrorLogs('notice')" class="btn btn-dark btn-sm text-[11px]" id="err-filter-notice">🔵 Notice</button>
        <button onclick="resolveAllErrors()" class="btn btn-gold btn-sm text-[11px]">✓ Resolve All</button>
        <button onclick="deleteOldErrors()" class="btn btn-dark btn-sm text-[11px]">🧹 Clean Old</button>
      </div>
    </div>
    <div id="error-logs-container" class="p-5">
      <div class="text-ak-muted text-sm text-center py-8">Loading error logs...</div>
    </div>
    <div id="error-logs-pagination" class="px-5 py-3 border-t border-ak-border flex justify-between items-center text-xs text-ak-muted"></div>
  </div>

  <!-- Quick Actions -->
  <div class="bg-ak-card rounded-xl border border-ak-border p-5">
    <h3 class="font-bold text-ak-text mb-4">⚡ Quick Actions</h3>
    <div class="flex gap-3 flex-wrap">
      <a href="../api/db_backup.php" class="btn btn-gold btn-sm">💾 Download Backup</a>
      <a href="index.php?tab=activity" class="btn btn-dark btn-sm">📋 View Activity Log</a>
      <a href="index.php?tab=email" class="btn btn-dark btn-sm">📧 Email Settings</a>
      <a href="health.php" class="btn btn-dark btn-sm">🔄 Refresh Health Check</a>
    </div>
  </div>

</div>

<?php require_once '../includes/footer.php'; ?>
<script src="../js/common.js?v=3.6"></script>
<script>
// ── Error Logs ──────────────────────────────
let errCurrentPage = 1;
let errCurrentFilter = 'all';

async function loadErrorLogs(severity = 'all', page = 1) {
  errCurrentPage = page;
  errCurrentFilter = severity;
  const container = document.getElementById('error-logs-container');
  const pagination = document.getElementById('error-logs-pagination');
  container.innerHTML = '<div class="text-ak-muted text-sm text-center py-8">Loading...</div>';

  let url = '../api/error_logs.php?action=list&page=' + page + '&per_page=20';
  if (severity !== 'all') url += '&severity=' + severity + '&resolved=0';

  try {
    const res = await fetch(url);
    const data = await res.json();
    if (!data.success) { container.innerHTML = '<div class="text-ak-red text-sm">Failed to load</div>'; return; }

    // Summary badges
    const summary = data.summary || [];
    const sevColors = { critical: 'bg-ak-red/15 text-ak-red', error: 'bg-orange-500/15 text-orange-400', warning: 'bg-yellow-500/15 text-yellow-500', notice: 'bg-blue-500/15 text-blue-400' };
    let summaryHTML = '<div class="flex gap-3 mb-4 flex-wrap">';
    for (const s of summary) {
      if (severity !== 'all' && s.severity !== severity) continue;
      summaryHTML += `<span class="text-xs px-2.5 py-1 rounded-full font-bold ${sevColors[s.severity] || 'bg-ak-border text-ak-muted'}">${s.severity}: ${s.unresolved} unresolved / ${s.total} total</span>`;
    }
    summaryHTML += '</div>';

    if (!data.data.length) {
      container.innerHTML = summaryHTML + '<div class="text-ak-green text-sm text-center py-8">✅ No errors found</div>';
      pagination.innerHTML = '';
      return;
    }

    let html = summaryHTML + '<div class="space-y-2">';
    for (const err of data.data) {
      const resolvedBadge = err.is_resolved ? '<span class="text-[10px] px-2 py-0.5 rounded-full bg-ak-green/15 text-ak-green font-bold">✓ Resolved</span>' : '<span class="text-[10px] px-2 py-0.5 rounded-full bg-ak-red/15 text-ak-red font-bold">Unresolved</span>';
      const sevBadge = `<span class="text-[10px] px-2 py-0.5 rounded-full font-bold ${sevColors[err.severity] || ''}">${err.severity}</span>`;
      const timeAgo = new Date(err.created_at).toLocaleString('ja-JP');
      const shortFile = err.file ? err.file.replace(/.*\//, '') : '';
      html += `
        <div class="border border-ak-border/50 rounded-lg p-3 ${err.is_resolved ? 'opacity-50' : ''}">
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">${sevBadge}${resolvedBadge}<span class="text-[10px] text-ak-muted font-mono">#${err.id}</span></div>
              <div class="text-sm text-ak-text break-all">${err.message.replace(/</g,'&lt;')}</div>
              <div class="text-[11px] text-ak-muted mt-1">${shortFile ? shortFile + ':' + (err.line || '?') : ''} ${err.url ? '· ' + err.url.replace(/</g,'&lt;') : ''}</div>
            </div>
            <div class="flex gap-2 items-start shrink-0">
              <span class="text-[10px] text-ak-muted font-mono whitespace-nowrap">${timeAgo}</span>
              ${!err.is_resolved ? `<button onclick="resolveError(${err.id})" class="text-[11px] px-2 py-0.5 rounded bg-ak-green/15 text-ak-green hover:bg-ak-green/30 font-bold cursor-pointer">Resolve</button>` : ''}
            </div>
          </div>
        </div>`;
    }
    html += '</div>';
    container.innerHTML = html;

    // Pagination
    const totalPages = Math.ceil(data.total / data.per_page);
    let pagHTML = `<span>${data.total} error(s) · Page ${data.page} of ${totalPages}</span>`;
    if (totalPages > 1) {
      pagHTML += '<div class="flex gap-2">';
      if (data.page > 1) pagHTML += `<button onclick="loadErrorLogs(errCurrentFilter, ${data.page-1})" class="btn btn-dark btn-sm text-[11px]">← Prev</button>`;
      if (data.page < totalPages) pagHTML += `<button onclick="loadErrorLogs(errCurrentFilter, ${data.page+1})" class="btn btn-dark btn-sm text-[11px]">Next →</button>`;
      pagHTML += '</div>';
    }
    pagination.innerHTML = pagHTML;

  } catch (e) {
    container.innerHTML = '<div class="text-ak-red text-sm">Connection error</div>';
  }
}

async function resolveError(id) {
  try {
    const res = await fetch('../api/error_logs.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'resolve', id})
    });
    const data = await res.json();
    if (data.success) loadErrorLogs(errCurrentFilter, errCurrentPage);
    else showToast(data.message || 'Failed', 'error');
  } catch { showToast('Connection error', 'error'); }
}

async function resolveAllErrors() {
  if (!confirm('Resolve all unresolved errors?')) return;
  try {
    const body = {action: 'resolve_all'};
    if (errCurrentFilter !== 'all') body.severity = errCurrentFilter;
    const res = await fetch('../api/error_logs.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(body)
    });
    const data = await res.json();
    if (data.success) { showToast('All errors resolved', 'success'); loadErrorLogs(errCurrentFilter, errCurrentPage); }
    else showToast(data.message || 'Failed', 'error');
  } catch { showToast('Connection error', 'error'); }
}

async function deleteOldErrors() {
  if (!confirm('Delete all resolved errors older than 30 days?')) return;
  try {
    const res = await fetch('../api/error_logs.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'delete_old', days: 30})
    });
    const data = await res.json();
    if (data.success) { showToast(`Deleted ${data.deleted} old errors`, 'success'); loadErrorLogs(errCurrentFilter, errCurrentPage); }
    else showToast(data.message || 'Failed', 'error');
  } catch { showToast('Connection error', 'error'); }
}

// Load on page ready
loadErrorLogs('all');
</script>
</body>
</html>
