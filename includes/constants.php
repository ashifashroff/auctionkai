<?php
// Security
const MIN_PASSWORD_LENGTH = 8;
const CSRF_TOKEN_LENGTH = 32;
const MAX_LOGIN_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 30;

// Trusted proxy IPs — only these may set X-Forwarded-For
// Cloudflare IP ranges (https://www.cloudflare.com/ips/)
// Plus localhost for direct nginx access
const TRUSTED_PROXY_IPS = [
    '127.0.0.1', '::1',
    // Cloudflare IPv4 ranges
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22',
    '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
    '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22',
    '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
];

// Session
const SESSION_TIMEOUT_MINUTES = 30;
const SESSION_WARN_BEFORE_MINUTES = 2;

// Pagination
const DEFAULT_PER_PAGE = 25;
const MAX_PER_PAGE = 100;

// Auction
const AUCTION_EXPIRY_DAYS = 14;

// Statement links
const STATEMENT_LINK_EXPIRY_DAYS = 14; // kept for backwards compat
const STATEMENT_LINK_EXPIRY_HOURS = 72; // 72 hours — used by generate_link.php
const STATEMENT_LINK_PIN_MAX_ATTEMPTS = 5; // per-IP limit (file-based)
const STATEMENT_LINK_PIN_MAX_TOKEN_ATTEMPTS = 10; // per-token limit (DB-based)

// Error log retention
const ERROR_LOG_RETENTION_DAYS = 30;
const ERROR_LOG_RESOLVED_CLEANUP_DAYS = 30;

// App version & updates
const APP_VERSION = 'v4.0.0';
const APP_GITHUB_REPO = 'ashifashroff/auctionkai';
const UPDATE_CHECK_INTERVAL = 3600; // 1 hour cache
