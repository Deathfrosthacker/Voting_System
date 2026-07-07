<?php
/**
 * RBAC (Role-Based Access Control) Helper
 * Manages roles, permissions, and access control for the voting system
 * 
 * Roles:
 * - admin: Full system control
 * - election_officer: Manages elections, candidates, voters
 * - observer: Read-only access to logs and results
 * - voter: Can only cast votes
 */

require_once __DIR__ . "/config/connection.php";

/**
 * Check if current user has a specific permission
 * 
 * @param string $permission_code The permission code to check
 * @return bool True if user has permission, false otherwise
 */
function has_permission(string $permission_code): bool {
    global $conn;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Must be logged in
    if (!isset($_SESSION['role'])) {
        return false;
    }

    $role = $_SESSION['role'];

    // Admin always has all permissions
    if ($role === 'admin') {
        return true;
    }

    // Check role_permissions table
    $stmt = mysqli_prepare($conn, 
        "SELECT 1 FROM role_permissions WHERE role = ? AND permission_code = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "ss", $role, $permission_code);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_num_rows($result) > 0;
}

/**
 * Check if user has ANY of the given permissions
 * 
 * @param array $permission_codes Array of permission codes
 * @return bool True if user has at least one permission
 */
function has_any_permission(array $permission_codes): bool {
    foreach ($permission_codes as $code) {
        if (has_permission($code)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user has ALL of the given permissions
 * 
 * @param array $permission_codes Array of permission codes
 * @return bool True if user has all permissions
 */
function has_all_permissions(array $permission_codes): bool {
    foreach ($permission_codes as $code) {
        if (!has_permission($code)) {
            return false;
        }
    }
    return true;
}

/**
 * Require a specific permission or redirect to unauthorized page
 * 
 * @param string $permission_code The required permission
 * @param string $redirect_url Where to redirect if unauthorized (default: login.php)
 */
function require_permission(string $permission_code, string $redirect_url = "login.php"): void {
    if (!has_permission($permission_code)) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Require any of the given permissions
 * 
 * @param array $permission_codes Array of acceptable permissions
 * @param string $redirect_url Where to redirect if unauthorized
 */
function require_any_permission(array $permission_codes, string $redirect_url = "login.php"): void {
    if (!has_any_permission($permission_codes)) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Get all permissions for a role
 * 
 * @param string $role The role to get permissions for
 * @return array Array of permission codes
 */
function get_role_permissions(string $role): array {
    global $conn;

    $stmt = mysqli_prepare($conn, 
        "SELECT permission_code FROM role_permissions WHERE role = ?"
    );
    mysqli_stmt_bind_param($stmt, "s", $role);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $permissions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $permissions[] = $row['permission_code'];
    }

    return $permissions;
}

/**
 * Get human-readable role name
 * 
 * @param string $role The role key
 * @return string Human-readable role name
 */
function get_role_display_name(string $role): string {
    $names = [
        'admin' => 'System Administrator',
        'election_officer' => 'Election Officer',
        'observer' => 'Observer / Auditor',
        'voter' => 'Voter'
    ];
    return $names[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

/**
 * Get role badge color for UI
 * 
 * @param string $role The role
 * @return string CSS color code
 */
function get_role_color(string $role): string {
    $colors = [
        'admin' => '#dc2626',           // Red
        'election_officer' => '#2563eb', // Blue
        'observer' => '#059669',         // Green
        'voter' => '#6b7280'           // Gray
    ];
    return $colors[$role] ?? '#6b7280';
}

/**
 * Get role badge background color for UI
 * 
 * @param string $role The role
 * @return string CSS background color
 */
function get_role_bg_color(string $role): string {
    $colors = [
        'admin' => '#fee2e2',           // Light red
        'election_officer' => '#dbeafe', // Light blue
        'observer' => '#d1fae5',         // Light green
        'voter' => '#f3f4f6'             // Light gray
    ];
    return $colors[$role] ?? '#f3f4f6';
}

/**
 * Check if user is an official (admin, election_officer, or observer)
 * 
 * @return bool True if user is an official
 */
function is_official(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'election_officer', 'observer']);
}

/**
 * Check if user is admin
 * 
 * @return bool True if admin
 */
function is_admin(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is election officer
 * 
 * @return bool True if election officer
 */
function is_election_officer(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'election_officer';
}

/**
 * Check if user is observer
 * 
 * @return bool True if observer
 */
function is_observer(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'observer';
}

/**
 * Check if user is voter
 * 
 * @return bool True if voter
 */
function is_voter(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'voter';
}

/**
 * Require observer role (for pages that both admin and observer can access)
 * Redirects to login if not an official
 */
function require_observer(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'election_officer', 'observer'])) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Universal session timeout check (30 minutes)
 * Call this at the top of every protected page
 * 
 * @param string $redirect_url Where to redirect on timeout
 */
function check_session_timeout(string $redirect_url = "login.php?timeout=1"): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header("Location: $redirect_url");
        exit();
    }

    $_SESSION['last_activity'] = time();
}

/**
 * Universal authentication check
 * Ensures user is logged in with a valid role
 * 
 * @param array $allowed_roles Array of allowed roles (empty = any role)
 * @param string $redirect_url Where to redirect if not authenticated
 */
function require_auth(array $allowed_roles = [], string $redirect_url = "login.php"): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: $redirect_url");
        exit();
    }

    // Check session timeout
    check_session_timeout();

    // Check role restriction
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: unauthorized.php");
        exit();
    }
}

/**
 * Log an activity to the database
 * 
 * @param int $user_id The user performing the action
 * @param string $activity Description of the activity
 */
function log_activity(int $user_id, string $activity): void {
    global $conn;

    $stmt = mysqli_prepare($conn, 
        "INSERT INTO logs (user_id, activity, log_time) VALUES (?, ?, NOW())"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "is", $user_id, $activity);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Get role badge HTML for display in tables/lists
 * 
 * @param string $role The role
 * @return string HTML span element
 */
function get_role_badge(string $role): string {
    $bg = get_role_bg_color($role);
    $color = get_role_color($role);
    $name = get_role_display_name($role);

    return '<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;background:' . $bg . ';color:' . $color . '">' 
        . '<i class="fas fa-user-tag" style="font-size:10px;"></i>' . $name . '</span>';
}