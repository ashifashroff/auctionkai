<?php
// Security
const MIN_PASSWORD_LENGTH = 8;
const CSRF_TOKEN_LENGTH = 32;
const MAX_LOGIN_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 30;

// Session
const SESSION_TIMEOUT_MINUTES = 30;
const SESSION_WARN_BEFORE_MINUTES = 2;

// Pagination
const DEFAULT_PER_PAGE = 25;
const MAX_PER_PAGE = 100;

// Auction
const AUCTION_EXPIRY_DAYS = 14;

// Statement links
const STATEMENT_LINK_EXPIRY_DAYS = 14;
const STATEMENT_LINK_PIN_MAX_ATTEMPTS = 5;

// Error log retention
const ERROR_LOG_RETENTION_DAYS = 30;
const ERROR_LOG_RESOLVED_CLEANUP_DAYS = 30;

// App version & updates
const APP_VERSION = 'v3.9.0';
const APP_GITHUB_REPO = 'ashifashroff/auctionkai';
const UPDATE_CHECK_INTERVAL = 3600; // 1 hour cache
