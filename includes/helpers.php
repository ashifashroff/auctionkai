<?php
// ─── SHARED HELPER FUNCTIONS ─────────────────────────────────────────────────

function fmt(float $n): string {
    return '¥' . number_format(round($n));
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function calcStatement(int $memberId, array $vehicles, float $commissionFee, array $specialFees = []): array {
    $all = array_values(array_filter($vehicles, fn($v) => (int)$v['member_id'] === $memberId));
    $mv = array_values(array_filter($all, fn($v) => $v['sold']));
    $uv = array_values(array_filter($all, fn($v) => !$v['sold']));
    $count       = count($mv);
    $unsoldCount = count($uv);
    $grossSales  = array_sum(array_column($mv, 'sold_price'));
    $taxTotal    = array_sum(array_map(fn($v) => round((float)$v['sold_price'] * 0.10), $mv));
    $recycleTotal= array_sum(array_map(fn($v) => (float)($v['recycle_fee'] ?? 0), $mv));
    $listingFeeTotal = array_sum(array_map(fn($v) => (float)($v['listing_fee'] ?? 0), $mv));
    $soldFeeTotal    = array_sum(array_map(fn($v) => (float)($v['sold_fee'] ?? 0), $mv));
    $nagareFeeTotal  = array_sum(array_map(fn($v) => (float)($v['nagare_fee'] ?? 0), $uv)); // nagare for unsold only

    $commissionTotal = $commissionFee;

    // Special fees per member
    $specialDeductions = 0;
    $specialAdditions = 0;
    foreach ($specialFees as $sf) {
        if (($sf['fee_type'] ?? 'deduction') === 'deduction') {
            $specialDeductions += (float)$sf['amount'];
        } else {
            $specialAdditions += (float)$sf['amount'];
        }
    }

    $totalReceived = $grossSales + $taxTotal + $recycleTotal + $specialAdditions;
    $totalVehicleDed = $listingFeeTotal + $soldFeeTotal + $nagareFeeTotal;
    $totalDed = $totalVehicleDed + $commissionTotal + $specialDeductions;
    $netPayout = $count > 0 ? $totalReceived - $totalDed : 0;

    return compact('mv','uv','count','unsoldCount','grossSales','taxTotal','recycleTotal','listingFeeTotal','soldFeeTotal','nagareFeeTotal','commissionTotal','commissionFee','specialDeductions','specialAdditions','specialFees','totalReceived','totalVehicleDed','totalDed','netPayout');
}

function sanitizeInput(string $input, string $type = 'string'): mixed {
    $input = trim($input);
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) !== false ? (int)$input : 0;
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (float)$input : 0.0;
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) ?: '';
        case 'string':
        default:
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

function buildWhatsAppMessage(array $member, array $auction, array $s, array $specialFees = [], string $brandName = 'AuctionKai', string $shareUrl = ''): string {
    $fmt = fn(float $n) => '¥' . number_format(round($n));

    $msg = "⚡ *{$brandName} 精算書*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "🏷 *{$auction['name']}*\n";
    $msg .= "📅 " . $auction['date'] . "\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━\n\n";

    $msg .= "👤 *" . $member['name'] . "*\n\n";

    if (!empty($s['mv'])) {
        $msg .= "🚗 *Sold Vehicles*\n";
        foreach ($s['mv'] as $v) {
            $msg .= " • ";
            $msg .= "[" . ($v['lot'] ?: '—') . "] ";
            $msg .= $v['make'] . " " . $v['model'];
            if (!empty($v['year'])) { $msg .= " (" . $v['year'] . ")"; }
            $msg .= "\n";
            $msg .= "   " . $fmt((float)$v['sold_price']) . "\n";
        }
        $msg .= "\n";
    }

    $msg .= "💰 *Settlement Breakdown*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "Gross Sales: " . $fmt($s['grossSales']) . "\n";
    $msg .= "＋ Tax (10%): " . $fmt($s['taxTotal'] ?? 0) . "\n";
    if (($s['recycleTotal'] ?? 0) > 0) { $msg .= "＋ Recycle Fee: " . $fmt($s['recycleTotal']) . "\n"; }
    $msg .= "─────────────────\n";
    $msg .= "Total Received: " . $fmt($s['totalReceived'] ?? $s['grossSales']) . "\n\n";

    if (($s['listingFeeTotal'] ?? 0) > 0) { $msg .= "－ Listing Fee: " . $fmt($s['listingFeeTotal']) . "\n"; }
    if (($s['soldFeeTotal'] ?? 0) > 0) { $msg .= "－ Sold Fee: " . $fmt($s['soldFeeTotal']) . "\n"; }
    if (($s['nagareFeeTotal'] ?? 0) > 0) { $msg .= "－ Nagare Fee: " . $fmt($s['nagareFeeTotal']) . "\n"; }
    if (($s['otherFeeTotal'] ?? 0) > 0) { $msg .= "－ Other Fee: " . $fmt($s['otherFeeTotal']) . "\n"; }
    if (($s['commissionTotal'] ?? 0) > 0) { $msg .= "－ Commission: " . $fmt($s['commissionTotal']) . "\n"; }

    foreach ($specialFees as $sf) {
        $isAdd = $sf['fee_type'] === 'addition';
        $msg .= ($isAdd ? "＋" : "－") . " " . $sf['fee_name'] . ": ";
        $msg .= ($isAdd ? "+" : "-") . $fmt((float)$sf['amount']) . "\n";
    }

    $msg .= "─────────────────\n";
    $msg .= "Total Deductions: " . $fmt($s['totalDed']) . "\n\n";

    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "💴 *NET PAYOUT*\n";
    $msg .= "💴 *お支払い額*\n";
    $msg .= "💴 *" . $fmt($s['netPayout']) . "*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━\n\n";

 if (!empty($shareUrl)) {
 $msg .= "\n\n🔗 *View Statement Online:*\n";
 $msg .= $shareUrl . "\n";
 $msg .= "_PIN: last 4 digits of your phone number_\n";
 }

    $msg .= "_Generated by {$brandName}_\n";
    $msg .= "_Mirai Global Solutions_";

    return $msg;
}

function buildWhatsAppUrl(string $phone, string $message): string {
    $phone = preg_replace('/[^\d+]/', '', $phone);
    if (str_starts_with($phone, '0')) { $phone = '+81' . substr($phone, 1); }
    if (!str_starts_with($phone, '+')) { $phone = '+' . $phone; }
    return "https://wa.me/{$phone}?text=" . urlencode($message);
}

function buildPdfHtml(array $member, array $auction, array $s, array $fees, array $brand = []): string {
    $brandName = $brand['brand_name'] ?? 'AuctionKai';
    $accent = sanitizeColor($brand['brand_accent_color'] ?? '#D4A84B');
    $fmt = fn(float $n) => '¥' . number_format(round($n));

    $vehicleRows = '';
    foreach ($s['mv'] as $v) {
        $vehicleRows .= '<tr>
            <td style="padding:6px 8px;border-bottom:1px solid #e8e8e8;font-size:11px">' . h($v['lot'] ?? '—') . '</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e8e8e8;font-size:11px">' . h($v['make'] . ' ' . $v['model']) . '</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e8e8e8;text-align:right;font-family:monospace;font-size:11px">' . $fmt((float)$v['sold_price']) . '</td>
        </tr>';
    }

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body{font-family:sans-serif;color:#111;font-size:12px;margin:0;padding:20px}
        table{width:100%;border-collapse:collapse}
        .header{background:#0A1420;color:#E8DCC8;padding:20px;margin:-20px -20px 20px}
        .header h1{color:' . $accent . ';font-size:20px;margin:0}
        .header .sub{color:#6A88A0;font-size:11px;margin-top:4px}
        .section{margin:16px 0 8px;font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#555}
        .fee-row{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f0f0f0;font-size:12px}
        .fee-row.deduction span:last-child{color:#CC3333}
        .fee-row.addition span:last-child{color:#2E7D52}
        .net-box{background:' . $accent . ';color:#0A1420;padding:14px 20px;border-radius:8px;display:flex;justify-content:space-between;align-items:center;margin-top:16px;font-weight:700}
        .net-box .amount{font-size:24px;font-family:monospace}
        .footer{text-align:center;color:#999;font-size:10px;margin-top:24px;padding-top:12px;border-top:1px solid #e8e8e8}
    </style></head><body>
        <div class="header">
            <h1>⚡ ' . h($brandName) . ' 精算書</h1>
            <div class="sub">' . h($auction['name']) . ' · ' . h($auction['date']) . '</div>
        </div>
        <div style="font-size:14px;font-weight:600;margin-bottom:12px">' . h($member['name']) . '</div>
        <div class="section">Sold Vehicles (' . count($s['mv']) . ')</div>
        <table><thead><tr style="background:#f5f5f5">
            <th style="padding:6px 8px;text-align:left;font-size:10px;color:#555">Lot</th>
            <th style="padding:6px 8px;text-align:left;font-size:10px;color:#555">Vehicle</th>
            <th style="padding:6px 8px;text-align:right;font-size:10px;color:#555">Price</th>
        </tr></thead><tbody>' . $vehicleRows . '</tbody></table>
        <div class="section">Fee Breakdown</div>
        <div class="fee-row"><span>Gross Sales</span><span>' . $fmt($s['grossSales']) . '</span></div>
        <div class="fee-row"><span>＋ Tax (10%)</span><span>' . $fmt($s['taxTotal'] ?? 0) . '</span></div>' .
        (($s['recycleTotal'] ?? 0) > 0 ? '<div class="fee-row"><span>＋ Recycle Fee</span><span>' . $fmt($s['recycleTotal']) . '</span></div>' : '') .
        '<div style="border-top:1px dashed #ccc;margin:8px 0"></div>
        <div class="fee-row" style="font-weight:600"><span>Total Received</span><span>' . $fmt($s['totalReceived'] ?? $s['grossSales']) . '</span></div>
        <div style="border-top:1px dashed #ccc;margin:8px 0"></div>' .
        (($s['listingFeeTotal'] ?? 0) > 0 ? '<div class="fee-row deduction"><span>－ Listing Fee</span><span>−' . $fmt($s['listingFeeTotal']) . '</span></div>' : '') .
        (($s['soldFeeTotal'] ?? 0) > 0 ? '<div class="fee-row deduction"><span>－ Sold Fee</span><span>−' . $fmt($s['soldFeeTotal']) . '</span></div>' : '') .
        (($s['nagareFeeTotal'] ?? 0) > 0 ? '<div class="fee-row deduction"><span>－ Nagare Fee</span><span>−' . $fmt($s['nagareFeeTotal']) . '</span></div>' : '') .
        (($s['commissionTotal'] ?? 0) > 0 ? '<div class="fee-row deduction"><span>－ Commission</span><span>−' . $fmt($s['commissionTotal']) . '</span></div>' : '') .
        '<div style="border-top:1px dashed #ccc;margin:8px 0"></div>
        <div class="fee-row deduction" style="font-weight:700"><span>Total Deductions</span><span>−' . $fmt($s['totalDed']) . '</span></div>
        <div class="net-box">
            <div><div>NET PAYOUT</div><div style="font-size:11px">お支払い額</div></div>
            <div class="amount">' . $fmt($s['netPayout']) . '</div>
        </div>
        <div class="footer">' . h($auction['name']) . ' · ' . h($auction['date']) . ' · ' . h($brand['brand_footer_text'] ?? 'Mirai Global Solutions') . '</div>
    </body></html>';
}
HELPER_EOF
function appUrl(): string {
    if (defined('APP_URL') && !empty(APP_URL)) {
        return rtrim(APP_URL, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    $host = $_SERVER['SERVER_NAME'] ?? '';
    if (empty($host) || !preg_match('/^[a-zA-Z0-9.\-]+$/', $host)) {
        $host = 'localhost';
    }
    $path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    return ($https ? 'https' : 'http') . '://' . $host . ($path !== '/' ? $path : '');
}
HELPER_EOF