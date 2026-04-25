<?php
// ─── SHARED HELPER FUNCTIONS ─────────────────────────────────────────────────

function fmt(float $n): string {
    return '¥' . number_format(round($n));
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function calcStatement(int $memberId, array $vehicles, float $commissionFee): array {
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
    $totalReceived = $grossSales + $taxTotal + $recycleTotal;
    $totalVehicleDed = $listingFeeTotal + $soldFeeTotal + $nagareFeeTotal;
    $totalDed = $totalVehicleDed + $commissionTotal;
    $netPayout = $count > 0 ? $totalReceived - $totalDed : 0;

    return compact('mv','uv','count','unsoldCount','grossSales','taxTotal','recycleTotal','listingFeeTotal','soldFeeTotal','nagareFeeTotal','commissionTotal','commissionFee','totalReceived','totalVehicleDed','totalDed','netPayout');
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
