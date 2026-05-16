<?php
http_response_code(503);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Offline — AuctionKai</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Noto Sans JP', sans-serif;
  background: #0A1420;
  color: #E8DCC8;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  text-align: center;
}
.card {
  background: #111E2D;
  border: 1px solid #1E3A5F;
  border-radius: 20px;
  padding: 48px 40px;
  max-width: 380px;
  width: 100%;
}
.icon { font-size: 64px; margin-bottom: 20px; display: block; animation: pulse 2s infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
h1 { font-size: 22px; font-weight: 700; color: #D4A84B; margin-bottom: 10px; }
p { color: #6A88A0; font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
.btn {
  background: #D4A84B; color: #0A1420; border: none; border-radius: 10px;
  padding: 12px 32px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit;
}
.status { margin-top: 20px; font-size: 11px; color: #3A5570; }
</style>
</head>
<body>
<div class="card">
  <span class="icon">📡</span>
  <h1>No Connection</h1>
  <p>AuctionKai needs an internet connection to sync your auction data.<br><br>Please check your connection and try again.</p>
  <button class="btn" onclick="window.location.reload()">Try Again</button>
  <div class="status" id="status">Checking connection...</div>
</div>
<script>
window.addEventListener('online', () => {
  document.getElementById('status').textContent = '✓ Connected — reloading...';
  setTimeout(() => window.history.back(), 1000);
});
window.addEventListener('offline', () => {
  document.getElementById('status').textContent = '✗ No internet connection';
});
document.getElementById('status').textContent = navigator.onLine ? '⏳ Connection found — tap Try Again' : '✗ No internet connection';
</script>
</body>
</html>
