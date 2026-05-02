<?php
/**
 * Load branding settings from database.
 * Falls back to defaults if not configured.
 */
function loadBranding(PDO $db): array {
    $defaults = [
        'brand_name' => 'AuctionKai',
        'brand_tagline' => 'Settlement Management System',
        'brand_owner' => 'Mirai Global Solutions',
        'brand_email' => '',
        'brand_phone' => '',
        'brand_address' => '',
        'brand_logo_url' => '',
        'brand_accent_color' => '#D4A84B',
        'brand_footer_text' => 'Designed & Developed by Mirai Global Solutions',
    ];

    try {
        $rows = $db->query("SELECT `key`, value FROM settings WHERE `key` LIKE 'brand_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_merge($defaults, $rows);
    } catch (Exception $e) {
        return $defaults;
    }
}

/**
 * Sanitize a hex color value
 */
function sanitizeColor(string $color): string {
    $color = trim($color);
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        return $color;
    }
    return '#D4A84B';
}
