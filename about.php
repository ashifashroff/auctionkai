<?php
header("Content-Security-Policy: default-src 'self'; connect-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/branding.php';
$db = db();
$brand = loadBranding($db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($brand['brand_name']) ?> — About</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=3.5">
<?php include 'css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen flex flex-col"><div class="flex-1 flex flex-col">

<!-- ─── TOP BAR ─────────────────────────────────────── -->
<div class="bg-ak-bg2 border-b border-ak-border px-7 py-3 flex items-center gap-6 sticky top-0 z-50">
  <div class="shrink-0">
    <div class="text-ak-gold font-bold text-lg tracking-tight">⚡ AuctionKai</div>
    <div class="text-ak-muted text-[11px]">About</div>
  </div>
  <div class="flex items-center gap-3 shrink-0 ml-auto">
    <a href="index.php" class="text-ak-muted text-xs hover:text-ak-gold transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">← Back to App</a>
  </div>
</div>

<!-- ─── CONTENT ─────────────────────────────────────── -->
<div class="p-7 max-w-[900px] mx-auto space-y-6">

<!-- Section 1 — Hero -->
<div class="bg-ak-card border border-ak-gold/30 rounded-xl p-8 text-center">
  <div class="text-4xl font-bold text-ak-gold tracking-tight mb-2">⚡ AuctionKai</div>
  <div class="text-ak-muted text-sm mb-3">Settlement Management System</div>
  <span class="text-[10px] bg-ak-gold/20 text-ak-gold px-2 py-0.5 rounded font-mono">v3.5</span>
  <p class="text-ak-text2 text-sm leading-relaxed mt-5 max-w-2xl mx-auto">
    AuctionKai is a purpose-built settlement management system for Japanese vehicle auction operators.
    Designed to replace manual paper-based accounting, AuctionKai allows auction house owners to manage
    members, track vehicle sales, calculate fees, and generate professional settlement statements in PDF
    format — all from a single dashboard.
  </p>
</div>

<!-- Section 2 — Built for Japan -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold text-lg mb-3">🇯🇵 Built for Japan</h2>
  <p class="text-ak-text2 text-sm leading-relaxed">
    AuctionKai is designed specifically for the Japanese vehicle auction market. It supports the
    Japanese fee structure including 消費税 (consumption tax at 10%), リサイクル料 (recycle fee),
    落札手数料 (sold/commission fee), and 流れ費用 (nagare fee for unsold vehicles). All fee
    calculations follow standard Japanese auction house conventions, and PDF statements include
    Japanese headers for professional record-keeping.
  </p>
</div>

<!-- Section 3 — Technology Stack -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold text-lg mb-4">🛠 Technology Stack</h2>
  <div class="space-y-2.5">
    <div class="flex items-center gap-3"><span class="text-ak-muted text-sm w-36">Backend</span><span class="text-ak-text text-sm">PHP 8.0+ (Vanilla)</span></div>
    <div class="flex items-center gap-3"><span class="text-ak-muted text-sm w-36">Database</span><span class="text-ak-text text-sm">MySQL with PDO</span></div>
    <div class="flex items-center gap-3"><span class="text-ak-muted text-sm w-36">Frontend</span><span class="text-ak-text text-sm">Tailwind CSS + Vanilla JavaScript</span></div>
    <div class="flex items-center gap-3"><span class="text-ak-muted text-sm w-36">PDF</span><span class="text-ak-text text-sm">Browser Print API</span></div>
    <div class="flex items-center gap-3"><span class="text-ak-muted text-sm w-36">Form Validation</span><span class="text-ak-text text-sm">Parsley.js</span></div>
    <div class="flex items-center gap-3"><span class="text-ak-muted text-sm w-36">Fonts</span><span class="text-ak-text text-sm">Noto Sans JP + Space Mono</span></div>
  </div>
</div>

<!-- Section 4 — Version History -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold text-lg mb-4">📋 Version History</h2>
  <div class="space-y-3">
    <div class="flex items-start gap-3"><span class="text-ak-gold font-mono text-xs bg-ak-gold/10 px-2 py-0.5 rounded shrink-0">v3.5</span><span class="text-ak-text2 text-sm">Special fees tab redesign, PDF fixes (branding, header, stamp positioning), delete auction fix, member dropdown consistency</span></div>
    <div class="flex items-start gap-3"><span class="text-ak-muted font-mono text-xs bg-ak-border px-2 py-0.5 rounded shrink-0">v3.4</span><span class="text-ak-text2 text-sm">Login history, session timeout, GDPR deletion, payment status, health check, maintenance mode, branding, backups, statement history, ZIP download, special fees</span></div>
    <div class="flex items-start gap-3"><span class="text-ak-muted font-mono text-xs bg-ak-border px-2 py-0.5 rounded shrink-0">v3.3</span><span class="text-ak-text2 text-sm">Login history tracking, failed attempt highlighting, auto-cleanup</span></div>
    <div class="flex items-start gap-3"><span class="text-ak-muted font-mono text-xs bg-ak-border px-2 py-0.5 rounded shrink-0">v3.2</span><span class="text-ak-text2 text-sm">Bulk member CSV import, activity log system, admin log viewer</span></div>
    <div class="flex items-start gap-3"><span class="text-ak-muted font-mono text-xs bg-ak-border px-2 py-0.5 rounded shrink-0">v3.0</span><span class="text-ak-text2 text-sm">Multi-provider email support, CSRF on all APIs, paginated vehicles + members, AJAX admin, security hardening</span></div>
    <div class="flex items-start gap-3"><span class="text-ak-muted font-mono text-xs bg-ak-border px-2 py-0.5 rounded shrink-0">v2.6</span><span class="text-ak-text2 text-sm">Admin panel, user management, Parsley.js validation, auth/ restructure</span></div>
    <div class="flex items-start gap-3"><span class="text-ak-muted font-mono text-xs bg-ak-border px-2 py-0.5 rounded shrink-0">v2.3</span><span class="text-ak-text2 text-sm">Security hardening, AJAX for everything, duplicate member check</span></div>
    <div class="flex items-start gap-3"><span class="text-ak-muted font-mono text-xs bg-ak-border px-2 py-0.5 rounded shrink-0">v2.2</span><span class="text-ak-text2 text-sm">Brute force protection, CSRF tokens, session regeneration</span></div>
    <div class="flex items-start gap-3"><span class="text-ak-muted font-mono text-xs bg-ak-border px-2 py-0.5 rounded shrink-0">v2.1</span><span class="text-ak-text2 text-sm">Dashboard tab, vehicle search, PDO prepared statements</span></div>
    <div class="flex items-start gap-3"><span class="text-ak-muted font-mono text-xs bg-ak-border px-2 py-0.5 rounded shrink-0">v2.0</span><span class="text-ak-text2 text-sm">Full rewrite with authentication system</span></div>
    <div class="flex items-start gap-3"><span class="text-ak-muted font-mono text-xs bg-ak-border px-2 py-0.5 rounded shrink-0">v1.0</span><span class="text-ak-text2 text-sm">Initial release (JSON-based storage)</span></div>
  </div>
</div>

<!-- Section 5 — Developer -->
<div class="bg-ak-card border border-ak-gold/30 rounded-xl p-8 text-center">
  <div class="text-ak-muted text-sm mb-2">Designed & Developed by</div>
  <div class="text-ak-gold font-bold text-xl mb-2"><?= h($brand['brand_owner']) ?></div>
  <div class="text-ak-muted text-xs">© 2025–<?= date('Y') ?> All rights reserved.</div>
</div>

</div>

<?php require_once 'includes/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
</body>
</html>