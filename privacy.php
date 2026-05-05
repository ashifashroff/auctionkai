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
<link rel="stylesheet" href="css/style.css?v=3.5">
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
<p class="text-ak-muted text-sm mb-6">Last updated: May 2026</p>

<!-- 1. Introduction -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">1. Introduction</h2>
  <p class="text-ak-text2 text-sm leading-relaxed">
    AuctionKai is operated by Mirai Global Solutions. This policy explains what personal data we collect,
    how we use it, and how we protect it. This applies to both auction house operators and their members.
  </p>
</div>

<!-- 2. Data We Collect -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">2. Data We Collect</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li><b>Account data</b> — username, email address, hashed password</li>
    <li><b>Member data</b> — names, phone numbers, and email addresses entered by auction house operators</li>
    <li><b>Transaction data</b> — vehicle details, sale prices, fees, special fees, and payment statuses entered by operators</li>
    <li><b>Activity data</b> — login history, actions performed (vehicle adds, edits, deletions, PDF generation, email sending, WhatsApp sharing, payment status changes), IP addresses, and timestamps</li>
    <li><b>Session data</b> — session tokens stored as cookies for authentication</li>
    <li><b>Statement link data</b> — unique tokens, PINs (derived from member phone numbers), view counts, and expiry dates for shareable statement links</li>
  </ul>
</div>

<!-- 3. How We Use Your Data -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">3. How We Use Your Data</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>To generate settlement statements and PDF documents for auction members</li>
    <li>To calculate fees, deductions, additions, and net payouts</li>
    <li>To track payment statuses (Unpaid, Partial, Paid) and paid timestamps</li>
    <li>To send email notifications (password reset links, settlement statements with PDF attachments) via the system\'s configured email provider</li>
    <li>To create shareable statement links protected by a PIN for member access to their online statement</li>
    <li>To generate pre-filled WhatsApp messages for sharing settlement details with members</li>
    <li>To maintain an audit trail of all actions for accountability and security</li>
    <li>To detect and prevent unauthorized access (brute-force protection, rate limiting, session management)</li>
  </ul>
</div>

<!-- 4. Email Communications -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">4. Email Communications</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>Password reset emails are sent when a user requests one via the "Forgot Password" page. Reset links expire after 1 hour and are invalidated after use</li>
    <li>Settlement statement emails may be sent by operators to members. These contain financial details including sale prices, fees, and net payout amounts</li>
    <li>Emails are sent through the email provider configured in the Admin Panel (SMTP, Gmail, Xserver, Sakura, etc.)</li>
    <li>Email sending is rate-limited to prevent abuse (10 emails per minute per user)</li>
  </ul>
</div>

<!-- 5. Data Storage & Security -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">5. Data Storage & Security</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>All data is stored in a MySQL database on your self-hosted server</li>
    <li>AuctionKai does not transmit personal data to any third-party analytics or tracking services</li>
    <li>Email delivery is handled by the SMTP provider configured by the operator. Email content (including PDF attachments) passes through this provider</li>
    <li>WhatsApp sharing sends a pre-filled message through WhatsApp\'s service (wa.me). When you click the WhatsApp button, settlement details are sent to WhatsApp/Meta servers. We do not control or store WhatsApp messages after they are sent</li>
    <li>Passwords are hashed using bcrypt — they are never stored in plaintext</li>
    <li>Password reset tokens are stored as SHA-256 hashes — even database access cannot reveal usable tokens</li>
    <li>All database queries use PDO prepared statements — protection against SQL injection</li>
    <li>CSRF tokens protect all forms and API endpoints from cross-origin attacks</li>
    <li>Login is rate-limited (5 failed attempts triggers a 30-second cooldown)</li>
    <li>Shareable statement links are protected by a PIN with a maximum of 5 attempts, after which access is temporarily blocked</li>
    <li>Statement links expire automatically after 14 days and cannot be accessed after expiry</li>
    <li>Session IDs are regenerated after PIN verification to prevent session fixation attacks</li>
    <li>Session timeout is enforced (configurable by admin, default 30 minutes of inactivity)</li>
    <li>Database backups are available to admin users only and contain all system data</li>
  </ul>
</div>

<!-- 6. Admin Access & Impersonation -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">6. Admin Access & Impersonation</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>System administrators can view all user accounts, activity logs, and system data</li>
    <li>Admins may impersonate regular users to assist with support issues. Impersonation sessions are limited to 1 hour</li>
    <li>All actions performed during impersonation are logged with the admin's identity in the activity log</li>
    <li>Admins cannot impersonate other admins</li>
    <li>Impersonated users are notified via the "Return to Admin" banner in the top bar</li>
  </ul>
</div>

<!-- 7. Data Retention -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">7. Data Retention</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>Active auction data (vehicles, fees, payment statuses) is retained for the duration of the auction</li>
    <li>Member records are retained until manually deleted by the operator</li>
    <li>Activity logs are retained for a minimum of 30 days. Admins may clear older logs</li>
    <li>Shareable statement links expire automatically after 14 days and their data is cleaned up on next page load</li>
    <li>Resolved error logs older than 30 days can be cleaned up by the system administrator</li>
    <li>Login history records are kept per user (last 50 attempts) and auto-cleaned</li>
    <li>Password reset tokens expire after 1 hour and are deleted after use</li>
    <li>Expired sessions are automatically cleaned up based on the configured timeout</li>
  </ul>
</div>

<!-- 8. Your Rights & Data Deletion -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">8. Your Rights & Data Deletion</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li><b>Account deletion</b> — You can permanently delete your account and all associated data (auctions, members, vehicles, fees, activity logs) from the Profile page. This action is immediate and irreversible</li>
    <li><b>Member deletion</b> — Operators can delete member records at any time from the Members tab</li>
    <li><b>Data export</b> — PDF settlement statements and ZIP downloads are available for record-keeping before deletion</li>
    <li><b>Database backups</b> — Admins can download complete SQL backups at any time</li>
  </ul>
</div>

<!-- 9. Cookies -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">9. Cookies</h2>
  <ul class="text-ak-text2 text-sm leading-relaxed space-y-2 list-disc list-inside">
    <li>A single session cookie (PHPSESSID) is used to maintain login state</li>
    <li>The session cookie is set with HttpOnly and SameSite=Strict flags</li>
    <li>No third-party cookies, tracking cookies, or advertising cookies are used</li>
    <li>Sessions expire after 30 minutes of inactivity (configurable by admin)</li>
  </ul>
</div>

<!-- 10. Contact -->
<div class="bg-ak-card border border-ak-border rounded-xl p-7">
  <h2 class="text-ak-gold font-bold mb-3">10. Contact</h2>
  <p class="text-ak-text2 text-sm leading-relaxed">
    Mirai Global Solutions<br>
    Email: <a href="mailto:admin@miraiglobaltrading.com" class="text-ak-gold hover:underline">admin@miraiglobaltrading.com</a>
  </p>
</div>

</div>

<?php require_once 'includes/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
</body>
</html>
