<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚡</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- PWA Manifest -->
<link rel="manifest" href="manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="AuctionKai">
<meta name="mobile-web-app-capable" content="yes">
<link rel="apple-touch-icon" href="icons/icon-152.png">
<link rel="apple-touch-icon" sizes="192x192" href="icons/icon-192.png">
<link rel="apple-touch-icon" sizes="512x512" href="icons/icon-512.png">
<meta name="theme-color" content="#D4A84B">
<meta name="msapplication-TileColor" content="#0A1420">
<meta name="msapplication-TileImage" content="icons/icon-144.png">
<title><?= h($brand['brand_name'] ?? 'AuctionKai') ?> — <?= h($brand['brand_tagline'] ?? 'Settlement System') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=3.9.0">
<?php include 'css/tailwind-config.php'; ?>
<style>
:root {
  --ak-gold: <?= sanitizeColor($brand['brand_accent_color']) ?>;
  --ak-gold-rgb: <?php $hex = ltrim(sanitizeColor($brand['brand_accent_color']), '#'); echo hexdec(substr($hex,0,2)) . ', ' . hexdec(substr($hex,2,2)) . ', ' . hexdec(substr($hex,4,2)); ?>;
}
</style>
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('sw.js').then(reg => {
      console.log('[PWA] SW registered:', reg.scope);
      reg.addEventListener('updatefound', () => {
        const nw = reg.installing;
        nw.addEventListener('statechange', () => {
          if (nw.state === 'installed' && navigator.serviceWorker.controller) {
            if (typeof showToast === 'function') showToast('🆕 App updated! Refresh to apply.', 'info', 8000);
          }
        });
      });
    }).catch(err => console.log('[PWA] SW failed:', err));
  });
}
</script>
<script src="js/animations.js?v=3.9.0"></script>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen flex flex-col"><a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[9999] focus:bg-ak-gold focus:text-ak-bg focus:px-4 focus:py-2 focus:rounded-lg focus:text-sm focus:font-bold">Skip to content</a><div class="page-loading-bar" id="pageLoadingBar"></div><div class="flex-1 flex flex-col">
