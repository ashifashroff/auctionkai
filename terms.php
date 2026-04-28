<?php
header("Content-Security-Policy: default-src 'self'; connect-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com;");

require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/constants.php';

$db = db();
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Terms of Use — AuctionKai</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=3.2">
<?php include 'css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen">

<!-- Topbar -->
<div class="bg-ak-bg2 border-b border-ak-border px-7 py-3 flex items-center gap-6 sticky top-0 z-50">
  <div class="shrink-0">
    <div class="text-ak-gold font-bold text-lg tracking-tight">⚡ AuctionKai</div>
    <div class="text-ak-muted text-[11px]">Settlement Management System</div>
  </div>
  <div class="ml-auto">
    <a href="index.php" class="text-ak-muted text-xs hover:text-ak-gold transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">← Back to App</a>
  </div>
</div>

<!-- Content -->
<div class="p-7 max-w-[900px] mx-auto animate-fade-in">
  <h1 class="text-2xl font-bold text-ak-gold mb-2">Terms of Use</h1>
  <p class="text-ak-muted text-sm mb-8">Last updated: <?= date('F Y') ?></p>

  <!-- 1. Introduction -->
  <div class="bg-ak-card border border-ak-border rounded-xl p-6 mb-5">
    <h2 class="text-ak-text font-bold text-sm uppercase tracking-wider mb-3">1. Introduction</h2>
    <p class="text-ak-text2 text-sm leading-relaxed">These Terms of Use govern your use of AuctionKai, a settlement management system operated by Mirai Global Solutions. By accessing or using this system, you agree to these terms.</p>
  </div>

  <!-- 2. Permitted Use -->
  <div class="bg-ak-card border border-ak-border rounded-xl p-6 mb-5">
    <h2 class="text-ak-text font-bold text-sm uppercase tracking-wider mb-3">2. Permitted Use</h2>
    <ul class="text-ak-text2 text-sm leading-relaxed space-y-2">
      <li>• AuctionKai is intended for use by licensed vehicle auction operators only</li>
      <li>• Each account is for a single auction house operator</li>
      <li>• You must not share your login credentials with unauthorized persons</li>
      <li>• You are responsible for all activity that occurs under your account</li>
    </ul>
  </div>

  <!-- 3. Data Responsibility -->
  <div class="bg-ak-card border border-ak-border rounded-xl p-6 mb-5">
    <h2 class="text-ak-text font-bold text-sm uppercase tracking-wider mb-3">3. Data Responsibility</h2>
    <ul class="text-ak-text2 text-sm leading-relaxed space-y-2">
      <li>• You are responsible for the accuracy of member and vehicle data you enter</li>
      <li>• You must obtain appropriate consent from members (sellers) before storing their personal information</li>
      <li>• You must comply with applicable data protection laws in your jurisdiction</li>
      <li>• AuctionKai stores data on your self-hosted server — you are responsible for server security</li>
    </ul>
  </div>

  <!-- 4. Prohibited Actions -->
  <div class="bg-ak-card border border-ak-border rounded-xl p-6 mb-5">
    <h2 class="text-ak-text font-bold text-sm uppercase tracking-wider mb-3">4. Prohibited Actions</h2>
    <ul class="text-ak-text2 text-sm leading-relaxed space-y-2">
      <li>• Attempting to access other users' data</li>
      <li>• Using the system to store fraudulent or misleading settlement records</li>
      <li>• Reverse engineering or modifying the system without authorization</li>
      <li>• Using automated tools to scrape or extract data from the system</li>
    </ul>
  </div>

  <!-- 5. Disclaimer of Warranties -->
  <div class="bg-ak-card border border-ak-border rounded-xl p-6 mb-5">
    <h2 class="text-ak-text font-bold text-sm uppercase tracking-wider mb-3">5. Disclaimer of Warranties</h2>
    <p class="text-ak-text2 text-sm leading-relaxed">AuctionKai is provided as-is without warranties of any kind. Mirai Global Solutions does not guarantee uninterrupted access or freedom from errors. Settlement calculations should be verified by the operator before distribution.</p>
  </div>

  <!-- 6. Limitation of Liability -->
  <div class="bg-ak-card border border-ak-border rounded-xl p-6 mb-5">
    <h2 class="text-ak-text font-bold text-sm uppercase tracking-wider mb-3">6. Limitation of Liability</h2>
    <p class="text-ak-text2 text-sm leading-relaxed">Mirai Global Solutions shall not be liable for any financial losses, disputes, or damages arising from the use of settlement statements generated by this system. The operator is solely responsible for verifying all calculations before payment.</p>
  </div>

  <!-- 7. Changes to Terms -->
  <div class="bg-ak-card border border-ak-border rounded-xl p-6 mb-5">
    <h2 class="text-ak-text font-bold text-sm uppercase tracking-wider mb-3">7. Changes to Terms</h2>
    <p class="text-ak-text2 text-sm leading-relaxed">These terms may be updated at any time. Continued use of the system after changes constitutes acceptance of the new terms.</p>
  </div>

  <!-- 8. Contact -->
  <div class="bg-ak-card border border-ak-border rounded-xl p-6 mb-5">
    <h2 class="text-ak-text font-bold text-sm uppercase tracking-wider mb-3">8. Contact</h2>
    <p class="text-ak-text2 text-sm leading-relaxed">Mirai Global Solutions<br>Email: admin@miraiglobal.com</p>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
