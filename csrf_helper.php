<?php
/*    CSRF PROTECTION HELPERS
      Security enhancements:
   - Tokens are stored per-session with time-based expiration
   - validate_csrf_token() returns false if token is missing/expired
   - Token rotation after successful validation (one-time use pattern)
   - Time-based token expiry (2 hours)
*/

/*
  Generate a new CSRF token for the current session.
  Creates a token with embedded timestamp for expiry checking.
 */
function generate_csrf_token(): string {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/*
  Validate a CSRF token.
  Checks: existence, non-emptiness, hash equality, and time expiry.
  
 @param string $token The token submitted with the form
 * @return bool True if valid, false otherwise
 */
function validate_csrf_token(string $token): bool {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check token exists and is not empty
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    // Check token hasn't expired (2 hours)
    $maxAge = 7200; // 2 hours in seconds
    if (!empty($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time'] > $maxAge)) {
        // Token expired - clear it
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    // Constant-time comparison to prevent timing attacks
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    
    // Rotate token after successful validation (one-time use pattern)
    // This prevents replay attacks
    if ($valid) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    return $valid;
}

/**
 * Generate a hidden input field with the CSRF token.
 * 
 * @return string HTML input element
 */
function csrf_input_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
}