<?php
// Security
const MIN_PASSWORD_LENGTH = 8;
const CSRF_TOKEN_LENGTH = 32;

// Business Logic
const AUCTION_EXPIRY_DAYS = 14;
const DEFAULT_COMMISSION_FEE = 3300;
const TAX_RATE = 0.10;

// Time
const SECONDS_PER_DAY = 86400;

// Login Rate Limiting
const MAX_LOGIN_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 30;
