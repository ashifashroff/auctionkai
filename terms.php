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
<title>AuctionKai — Terms of Use</title>
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
    <div class="text-ak-muted text-[11px]">Terms of Use</div>
  </div>
  <div class="flex items-center gap-3 shrink-0 ml-auto">
    <a href="index.php" class="text-ak-muted text-xs hover:text-ak-gold transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">← Back to App</a>
  </div>
</div>

<!-- ─── CONTENT ─────────────────────────────────────── -->
<div class="p-7 max-w-[900px] mx-auto space-y-6">

<h1 class="text-2xl font-bold text-ak-gold mb-2">📜 Terms of Use</h1>
<p class="text-ak-muted text-sm mb-6">Last updated: May 2026</p>

<!-- 1. Acceptance -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">1. Acceptance of Terms</h2>
  <p class="text-ak-text2 text-sm leading-relaxed">
    By using AuctionKai, you agree to these terms. If you do not agree, do not use the system.
    AuctionKai is a settlement management tool for Japanese auto auction houses, operated by Mirai Global Solutions.
  </p>
</div>

<!-- 2. Account Usage -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">2. Account Usage</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>Each user must register with a unique username and email address</li>
    <li>You are responsible for keeping your password secure. Passwords must be at least 8 characters</li>
    <li>Accounts can be suspended by administrators for misuse or policy violations</li>
    <li>Suspended users cannot log in for the duration of their suspension period</li>
    <li>You may permanently delete your account at any time from the Profile page. Deletion is immediate and irreversible — all auctions, members, vehicles, fees, payment records, and activity logs will be destroyed</li>
    <li>After 5 failed login attempts, a 30-second cooldown is enforced</li>
    <li>Sessions expire after 30 minutes of inactivity (configurable by admin)</li>
  </ul>
</div>

<!-- 3. Data Accuracy -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">3. Data Accuracy</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>You are solely responsible for the accuracy of all data entered — member names, phone numbers, emails, vehicle details, sale prices, and fees</li>
    <li><b>Special fees</b> — Deductions and additions are user-entered values. The system does not validate fee appropriateness, only that amounts are positive numbers. You are responsible for verifying that all special fees are correct before generating statements</li>
    <li><b>Payment statuses</b> — Marking a member as Paid, Partial, or Unpaid is an operator action. The system does not verify actual payment receipt. You are responsible for ensuring payment statuses accurately reflect reality</li>
    <li><b>CSV imports</b> — When importing members via CSV, you are responsible for the accuracy and legality of the imported data. Duplicate entries are automatically skipped</li>
    <li>AuctionKai calculates net payouts based on the data you provide. Incorrect input will produce incorrect statements</li>
  </ul>
</div>

<!-- 4. Email -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">4. Email</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>Password reset emails are sent to the email address on file when requested via the "Forgot Password" page. Reset links expire after 1 hour</li>
    <li>You may send settlement statement emails to members through the system using the email provider configured by the administrator</li>
    <li>You are responsible for the content of emails sent through the system</li>
    <li>Email sending is rate-limited (10 per minute per user) to prevent abuse</li>
    <li>The system uses the email credentials configured in the Admin Panel. You are responsible for keeping those credentials secure</li>
  </ul>
</div>

<!-- 5. Admin Privileges -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">5. Admin Privileges</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>Administrators can manage all user accounts, view activity logs, configure email settings, and download database backups</li>
    <li>Admins may impersonate regular users for support purposes. Impersonation sessions expire after 1 hour and all actions are logged with the admin's identity</li>
    <li>Admins cannot impersonate other admins</li>
    <li>Admin actions (user creation, suspension, deletion, impersonation, backup downloads) are recorded in the activity log</li>
    <li>Misuse of admin privileges — including unauthorized access to user data or manipulation of financial records — is the sole responsibility of the administrator</li>
  </ul>
</div>

<!-- 6. Backups -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">6. Backups</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>Database backups can be created and downloaded by administrators at any time</li>
    <li>Backups contain all system data including user accounts, member information, financial records, and activity logs</li>
    <li>Automated backups can be configured via cron jobs on the server</li>
    <li>You are responsible for securing downloaded backup files — they contain sensitive financial and personal data</li>
    <li>Mirai Global Solutions is not liable for data loss if backups are not maintained</li>
  </ul>
</div>

<!-- 7. Fee Calculations -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">7. Fee Calculations</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>Commission is a flat fee per member (default ¥3,300), configurable per auction</li>
    <li>10% consumption tax is auto-calculated on sold prices</li>
    <li>Nagare fees apply only to unsold vehicles</li>
    <li>Special fees (deductions and additions) are per-member, per-auction, and user-entered</li>
    <li>Net payout = Total Received − Total Deductions (including special fee deductions) + Special Fee Additions</li>
    <li>The system enforces that all fee amounts are non-negative at both the application and database level</li>
    <li>The calculation engine is provided as-is. Verify critical financial outputs before distributing statements</li>
  </ul>
</div>

<!-- 8. PDF Statements -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">8. PDF Statements</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>PDF settlement statements are generated based on the data in the system at the time of generation</li>
    <li>PAID, PARTIAL, or UNPAID stamps appear on PDFs based on the payment status set by the operator</li>
    <li>PDF content is the operator's responsibility. Verify accuracy before sharing with members</li>
    <li>Bulk ZIP downloads contain individual PDF statements for all members with sold vehicles</li>
  </ul>
</div>

<!-- 9. Disclaimers -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">9. Disclaimers</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>AuctionKai is a tool — it does not provide financial, legal, or tax advice</li>
    <li>Settlement calculations are based on user-entered data and may contain errors if input is incorrect</li>
    <li>The system is self-hosted. You are responsible for server security, SSL certificates, and data protection</li>
    <li>Mirai Global Solutions is not liable for any financial loss resulting from incorrect data entry, system misconfiguration, or unauthorized access</li>
    <li>Service availability depends on your hosting provider. No uptime guarantee is provided</li>
  </ul>
</div>

<!-- 10. Changes -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">10. Changes to These Terms</h2>
  <p class="text-ak-text2 text-sm leading-relaxed">
    We may update these terms from time to time. Continued use of the system after changes constitutes acceptance of the updated terms.
    Major changes will be reflected in the "Last updated" date above.
  </p>
</div>

<!-- 11. Contact -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">11. Contact</h2>
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
