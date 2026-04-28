<?php
header("Content-Security-Policy: default-src 'self'; connect-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AuctionKai — Privacy Policy</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=3.2">
<?php include 'css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen flex flex-col"><div class="flex-1 flex flex-col">

<!-- ─── TOP BAR ─────────────────────────────────────── -->
<div class="bg-ak-bg2 border-b border-ak-border px-7 py-3 flex items-center gap-6 sticky top-0 z-50">
  <div class="shrink-0">
    <div class="text-ak-gold font-bold text-lg tracking-tight">⚡ AuctionKai</div>
    <div class="text-ak-muted text-[11px]">Privacy Policy</div>
  </div>
  <div class="flex items-center gap-3 shrink-0 ml-auto">
    <a href="index.php" class="text-ak-muted text-xs hover:text-ak-gold transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">← Back to App</a>
  </div>
</div>

<!-- ─── CONTENT ─────────────────────────────────────── -->
<div class="p-7 max-w-[900px] mx-auto space-y-6">

<h1 class="text-2xl font-bold text-ak-gold mb-2">🔒 Privacy Policy</h1>
<p class="text-ak-muted text-sm mb-6">Last updated: <?= date('F Y') ?></p>

<!-- 1. Introduction -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">1. Introduction</h2>
  <p class="text-ak-text2 text-sm leading-relaxed">
    AuctionKai is operated by Mirai Global Solutions. This policy explains what personal data we collect,
    how we use it, and how we protect it.
  </p>
</div>

<!-- 2. Data We Collect -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">2. Data We Collect</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>Member names, phone numbers, and email addresses entered by auction house operators</li>
    <li>User account information (username, email)</li>
    <li>Vehicle and transaction data entered by operators</li>
  </ul>
</div>

<!-- 3. How We Use Your Data -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">3. How We Use Your Data</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>To generate settlement statements for auction members</li>
    <li>To calculate fees and net payouts</li>
    <li>To produce PDF documents for record-keeping</li>
  </ul>
</div>

<!-- 4. Data Storage -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">4. Data Storage</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>All data is stored in a MySQL database on your self-hosted server</li>
    <li>AuctionKai does not transmit personal data to any third-party services</li>
    <li>Auction house operators are responsible for the security of their own server</li>
  </ul>
</div>

<!-- 5. Data Retention -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">5. Data Retention</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>Active auction data is retained for the duration of the auction period</li>
    <li>Member records are retained until manually deleted by the operator</li>
  </ul>
</div>

<!-- 6. Your Rights -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">6. Your Rights</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>Auction operators can delete member records at any time from the Members tab</li>
    <li>Contact Mirai Global Solutions for data inquiries</li>
  </ul>
</div>

<!-- 7. Contact -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">7. Contact</h2>
  <p class="text-ak-text2 text-sm leading-relaxed">
    Mirai Global Solutions<br>
    Email: <a href="mailto:admin@miraiglobal.com" class="text-ak-gold hover:underline">admin@miraiglobal.com</a>
  </p>
</div>

</div>

<?php require_once 'includes/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
</body>
</html>