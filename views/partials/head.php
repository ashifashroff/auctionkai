<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚡</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($brand['brand_name'] ?? 'AuctionKai') ?> — <?= h($brand['brand_tagline'] ?? 'Settlement System') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=3.8.1">
<?php include 'css/tailwind-config.php'; ?>
<style>
:root {
  --ak-gold: <?= sanitizeColor($brand['brand_accent_color']) ?>;
  --ak-gold-rgb: <?php $hex = ltrim(sanitizeColor($brand['brand_accent_color']), '#'); echo hexdec(substr($hex,0,2)) . ', ' . hexdec(substr($hex,2,2)) . ', ' . hexdec(substr($hex,4,2)); ?>;
}
</style>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen flex flex-col"><a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[9999] focus:bg-ak-gold focus:text-ak-bg focus:px-4 focus:py-2 focus:rounded-lg focus:text-sm focus:font-bold">Skip to content</a><div class="page-loading-bar" id="pageLoadingBar"></div><div class="flex-1 flex flex-col">
