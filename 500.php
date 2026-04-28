<?php
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>500 — Server Error | AuctionKai</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--ak-bg:#0A1420;--ak-bg2:#111E2D;--ak-card:#152234;--ak-border:#1E3048;--ak-text:#E8E8E8;--ak-text2:#A0AEC0;--ak-muted:#5A6B80;--ak-gold:#D4A84B;--ak-red:#CC7777;--ak-green:#4CAF82}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Noto Sans JP',sans-serif;background:var(--ak-bg);color:var(--ak-text);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem}
.container{max-width:500px;text-align:center;animation:fadeUp .5s ease-out}
.error-code{font-family:'Space Mono',monospace;font-size:6rem;font-weight:700;color:var(--ak-red);line-height:1;margin-bottom:.5rem}
.title{font-size:1.25rem;font-weight:600;color:var(--ak-text);margin-bottom:.75rem}
.message{font-size:.875rem;color:var(--ak-muted);line-height:1.6;margin-bottom:2rem}
.btn{display:inline-block;padding:.625rem 1.5rem;border-radius:.5rem;font-size:.875rem;font-weight:600;text-decoration:none;transition:all .2s}
.btn-gold{background:var(--ak-gold);color:var(--ak-bg)}
.btn-gold:hover{opacity:.9;transform:translateY(-1px)}
.btn-dark{background:var(--ak-bg2);color:var(--ak-text2);border:1px solid var(--ak-border);margin-left:.75rem}
.btn-dark:hover{border-color:var(--ak-gold);color:var(--ak-gold)}
.footer{position:fixed;bottom:1.5rem;font-size:.75rem;color:var(--ak-muted)}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
</style>
</head>
<body>
<div class="container">
  <div class="error-code">500</div>
  <div class="title">Server Error</div>
  <div class="message">Something went wrong on our end.<br>Try refreshing the page or come back in a few minutes.</div>
  <a href="/" class="btn btn-gold">← Back to Dashboard</a>
  <a href="javascript:location.reload()" class="btn btn-dark">↻ Retry</a>
</div>
<div class="footer">⚡ AuctionKai</div>
</body>
</html>
