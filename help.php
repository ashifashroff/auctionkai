<?php
header("Content-Security-Policy: default-src 'self'; connect-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AuctionKai — Help & Guide</title>
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
    <div class="text-ak-muted text-[11px]">Help & Guide</div>
  </div>
  <div class="flex items-center gap-3 shrink-0 ml-auto">
    <a href="index.php" class="text-ak-muted text-xs hover:text-ak-gold transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">← Back to App</a>
  </div>
</div>

<!-- ─── CONTENT ─────────────────────────────────────── -->
<div class="p-7 max-w-[900px] mx-auto">

<h1 class="text-2xl font-bold text-ak-gold mb-6">📖 Help & Guide</h1>

<!-- Accordion -->
<?php
$sections = [
  [
    'title' => '🚀 Getting Started',
    'items' => [
      '<b>Creating your first auction</b> — Click the "+ New Auction" button in the auction selector bar. Enter a name (e.g. "Nagoya Auto Auction") and the auction date. The system automatically sets a 14-day expiry.',
      '<b>Setting the auction date and commission fee</b> — The date field is set when you create the auction and cannot be changed after. The commission fee (default ¥3,300 per member) can be adjusted from the top bar at any time.',
      '<b>What the 14-day expiry means</b> — After the expiry date, all vehicles (sold and unsold) and the auction itself are automatically deleted. Member records are preserved. A red badge warns you when expiry is near.',
    ],
  ],
  [
    'title' => '👥 Managing Members',
    'items' => [
      '<b>Adding a seller/member</b> — Go to the Members tab. Fill in the name (required), phone, and email. Click "+ Add". Duplicate names are blocked.',
      '<b>Editing member details</b> — Click the "Edit" button on any member card. A popup lets you update name, phone, and email without reloading the page.',
      '<b>Removing a member</b> — Click "Remove" on the member card. This also deletes all their vehicles in the current auction.',
    ],
  ],
  [
    'title' => '🚗 Managing Vehicles',
    'items' => [
      '<b>Adding a vehicle</b> — In the Vehicles tab, select a member from the search dropdown. Fill in the make (required), model, lot number, and fees. Click "Add".',
      '<b>Fee field explanations:</b>
        <ul class="list-disc list-inside ml-2 mt-1 space-y-1">
          <li><b>Sold Price</b> — the hammer price at auction</li>
          <li><b>Recycle Fee (リサイクル料)</b> — recycling fee collected from buyer</li>
          <li><b>Listing Fee (出品料)</b> — fee charged for listing the vehicle</li>
          <li><b>Sold Fee (落札手数料)</b> — commission charged on sold vehicle</li>
          <li><b>Nagare Fee (流れ費用)</b> — fee charged for unsold vehicles</li>
          <li><b>Other Fee</b> — any additional deduction</li>
        </ul>',
      '<b>Toggling Sold / Unsold</b> — Click the "✓ SOLD" or "✗ UNSOLD" button on any vehicle row. When sold, the nagare field disables and sold-price fields enable. When unsold, it flips.',
    ],
  ],
  [
    'title' => '💴 Special Fees',
    'items' => [
      '<b>What are special fees?</b> — Custom per-member fees for each auction. Use them for car wash fees, bank charges, storage fees, repair costs, inspection fees, key duplicates, bonus payments, or any other custom charge.',
      '<b>Adding a special fee</b> — Go to the Fees tab. Select a member from the search dropdown, enter the fee name and amount, choose Deduction (−) or Addition (+), and click Add.',
      '<b>Quick preset chips</b> — Click any preset button below the form to auto-fill the fee name, amount, and type. Then just select a member and click Add.',
      '<b>Deductions vs Additions</b> — Deductions (shown in red) subtract from the member\'s payout. Additions (shown in green) add to the payout. Both appear in the settlement statement and PDF.',
      '<b>Deleting a fee</b> — Click the × button on any fee row. The row animates out. This cannot be undone.',
      '<b>Fee summary</b> — The bottom of the fees table shows a summary row with total count, total deductions, and total additions.',
    ],
  ],
  [
    'title' => '📄 Statements & PDF',
    'items' => [
      '<b>Net payout calculation</b> — Total Received (Sold Price + 10% Tax + Recycle Fee) minus Total Deductions (Listing Fee + Sold Fee + Nagare Fee + Commission + Special Fee Deductions − Special Fee Additions).',
      '<b>Generating a PDF for one member</b> — Click the "↓ PDF" button on any member\'s statement card in the Statements tab.',
      '<b>Printing all PDFs at once</b> — Click "↓ Print All PDFs" at the top of the Statements tab.',
      '<b>Downloading all PDFs as ZIP</b> — Click "📦 Download ZIP" to get all member statements in one ZIP file.',
      '<b>Sending email to a member</b> — Click the "✉ Send Email" button. This opens your default email client with a pre-filled subject and settlement summary.',
      '<b>Payment status</b> — Click the status button (Unpaid/Partial/Paid) on any member\'s card to update it. Paid members get a PAID stamp on their PDF.',
    ],
  ],
  [
    'title' => '⚙️ Fee Settings',
    'items' => [
      '<b>How commission fee works</b> — Commission is a flat fee per member (not per vehicle, not a percentage). Default is ¥3,300. It is deducted once from each member who has at least one sold vehicle. Change it per auction from the top bar.',
      '<b>Per-vehicle fees vs per-member commission</b> — Listing Fee, Sold Fee, and Nagare Fee are charged per vehicle. Commission is charged once per member regardless of how many vehicles they sold. Special fees are charged once per member per auction.',
    ],
  ],
];
?>

<?php foreach ($sections as $i => $section): ?>
<div class="bg-ak-card border border-ak-border rounded-xl mb-4 overflow-hidden">
  <button class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-ak-bg/50 transition-colors" onclick="toggleAccordion(<?= $i ?>)">
    <span class="text-ak-text font-semibold"><?= $section['title'] ?></span>
    <span class="text-ak-muted text-xl transition-transform duration-200" id="acc-icon-<?= $i ?>">+</span>
  </button>
  <div class="px-6 pb-0 overflow-hidden transition-all duration-300" id="acc-content-<?= $i ?>" style="max-height:0">
    <div class="pb-5 space-y-3">
      <?php foreach ($section['items'] as $item): ?>
        <div class="text-ak-text2 text-sm leading-relaxed"><?= $item ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

</div>

<script>
function toggleAccordion(i) {
  const content = document.getElementById('acc-content-' + i);
  const icon = document.getElementById('acc-icon-' + i);
  if (content.style.maxHeight && content.style.maxHeight !== '0px') {
    content.style.maxHeight = '0px';
    content.style.paddingBottom = '0';
    icon.textContent = '+';
    icon.style.transform = 'rotate(0deg)';
  } else {
    content.style.maxHeight = content.scrollHeight + 'px';
    content.style.paddingBottom = '0';
    icon.textContent = '−';
    icon.style.transform = 'rotate(180deg)';
  }
}
</script>

<?php require_once 'includes/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
</body>
</html>