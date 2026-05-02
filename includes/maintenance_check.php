<?php
/**
 * Call this AFTER auth_check.php and db().
 * Admins bypass maintenance mode.
 * Non-admin users see maintenance page.
 */
function checkMaintenanceMode(PDO $db, string $userRole): void {
    if ($userRole === 'admin') return;

    try {
        $rows = $db->query("SELECT `key`, value FROM settings WHERE `key` IN ('maintenance_mode','maintenance_title','maintenance_message','maintenance_eta')")->fetchAll(PDO::FETCH_KEY_PAIR);

        if (($rows['maintenance_mode'] ?? '0') !== '1') return;

        $title = $rows['maintenance_title'] ?? 'System Maintenance';
        $message = $rows['maintenance_message'] ?? 'We are under maintenance.';
        $eta = $rows['maintenance_eta'] ?? '';

        http_response_code(503);
        header('Retry-After: 3600');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="refresh" content="60">
        <title><?= htmlspecialchars($title) ?> — AuctionKai</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
        <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Noto Sans JP', sans-serif; background: #0A1420; color: #E8DCC8; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #111E2D; border: 1px solid #1E3A5F; border-radius: 20px; padding: 52px 48px; max-width: 520px; width: 100%; text-align: center; box-shadow: 0 24px 64px rgba(0,0,0,.5); }
        .gear { font-size: 64px; margin-bottom: 24px; display: block; animation: spin 8s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .badge { display: inline-block; background: #D4A84B20; border: 1px solid #D4A84B40; color: #D4A84B; padding: 4px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 20px; font-family: 'Space Mono', monospace; }
        h1 { font-size: 26px; font-weight: 700; color: #F0E4C8; margin-bottom: 14px; }
        p { color: #6A88A0; font-size: 14px; line-height: 1.7; margin-bottom: 28px; }
        .eta { background: #0A1724; border: 1px solid #1E3A5F; border-radius: 10px; padding: 14px 20px; font-size: 13px; color: #A8C4D8; margin-bottom: 28px; }
        .eta b { color: #D4A84B; }
        .footer-txt { font-size: 11px; color: #3A5570; margin-top: 32px; }
        .footer-txt b { color: #5A7A90; }
        .auto-refresh { font-size: 11px; color: #3A5570; margin-top: 8px; }
        </style>
        </head>
        <body>
        <div class="card">
          <span class="gear">⚙️</span>
          <div class="badge">Maintenance</div>
          <h1><?= htmlspecialchars($title) ?></h1>
          <p><?= nl2br(htmlspecialchars($message)) ?></p>
          <?php if (!empty($eta)): ?>
          <div class="eta"><b>Estimated completion:</b><br><?= htmlspecialchars($eta) ?></div>
          <?php endif; ?>
          <div class="auto-refresh">🔄 Page auto-refreshes every 60 seconds</div>
          <div class="footer-txt">⚡ <b>AuctionKai</b> · Designed &amp; Developed by Mirai Global Solutions</div>
        </div>
        </body>
        </html>
        <?php
        exit;
    } catch (Exception $e) {
        error_log('Maintenance check error: ' . $e->getMessage());
    }
}
