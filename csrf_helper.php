<?php
function generate_csrf_token(): string {
    // FIX: Only start session if not already active - don't interfere with role-specific sessions
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token(string $token): bool {
    // Only start session if not already active
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

    // Rotate token after successful validation 
    // This prevents replay attacks
    if ($valid) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }

    return $valid;
}

function csrf_input_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
}