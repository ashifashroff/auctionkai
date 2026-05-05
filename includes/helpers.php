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

function buildWhatsAppMessage(array $member, array $auction, array $s, array $specialFees = [], string $brandName = 'AuctionKai'): string {
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
