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
 * Start session respecting any previously set session_name()
 * This allows role-specific sessions to work correctly across tabs.
 * 
 * CRITICAL FIX: When multiple role sessions exist in cookies, we need to
 * check which one is being requested by the current page context.
 * Now supports $_GET['role'] as a hint for multi-session scenarios.
 */
function start_role_session(): void {
    // If session already active, don't restart
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // If a session name was already explicitly set by calling code, respect it
    if (session_name() !== 'PHPSESSID') {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_path', '/');
            session_start();
        }
        return;
    }

    // Role session mapping
    $role_sessions = [
        'ADMIN_SESSION'    => 'admin',
        'OFFICER_SESSION'  => 'election_officer',
        'OBSERVER_SESSION' => 'observer',
        'VOTER_SESSION'    => 'voter'
    ];

    // ============================================================
    // FIX: Check for role hint from URL first (e.g., ?role=election_officer)
    // This prevents the wrong session from being selected when
    // multiple role cookies exist in the browser.
    // ============================================================
    $role_hint = $_GET['role'] ?? null;
    if ($role_hint && in_array($role_hint, $role_sessions)) {
        $target_sess = array_search($role_hint, $role_sessions);
        if ($target_sess !== false && isset($_COOKIE[$target_sess])) {
            ini_set('session.cookie_path', '/');
            session_name($target_sess);
            session_start();
            return;
        }
    }

    // Auto-detect from cookies - but ONLY if there's exactly one role session
    // If multiple exist, we need the calling code to tell us which one to use
    $found_sessions = [];
    foreach ($role_sessions as $sess_name => $sess_role) {
        if (isset($_COOKIE[$sess_name])) {
            $found_sessions[$sess_name] = $sess_role;
        }
    }

    // If no role session found, start generic session (for login page, etc.)
    if (empty($found_sessions)) {
        ini_set('session.cookie_path', '/');
        session_start();
        return;
    }

    // If exactly one found, use it
    if (count($found_sessions) === 1) {
        $sess_name = array_key_first($found_sessions);
        ini_set('session.cookie_path', '/');
        session_name($sess_name);
        session_start();
        return;
    }

    // Multiple sessions found - this is the multi-tab scenario
    // We need to determine which one this page wants based on allowed roles
    // The calling code (require_auth) should have set this, but as fallback:
    // Check if current script name hints at the role
    $script = basename($_SERVER['PHP_SELF']);

    $role_hints = [
        'admin' => [
            'admin_dashboard', 'adminadd', 'manage_officials',
            'winners', 'activity_logs', 'votes', 'positions',
            'add_candidate', 'regions', 'affiliations', 'register_voter',
            'diagnostic', 'edit_position', 'delete_position', 'change_password'
        ],
        'election_officer' => [
            'election_officer_dashboard',
            'winners', 'activity_logs', 'votes', 'positions',
            'add_candidate', 'regions', 'affiliations', 'register_voter',
            'edit_position', 'delete_position', 'change_password'
        ],
        'observer' => [
            'observer_dashboard',
            'winners', 'activity_logs', 'votes', 'diagnostic', 'change_password'
        ],
        'voter' => [
            'voter_dashboard', 'vote.php', 'vote', 'change_password'
        ]
    ];

    foreach ($role_hints as $hint_role => $hint_scripts) {
        foreach ($hint_scripts as $hint) {
            if (strpos($script, $hint) !== false) {
                // Found a hint, use the corresponding session
                $target_sess = array_search($hint_role, $role_sessions);
                if ($target_sess !== false && isset($found_sessions[$target_sess])) {
                    ini_set('session.cookie_path', '/');
                    session_name($target_sess);
                    session_start();
                    return;
                }
            }
        }
    }

    // Fallback: use the first one found (usually the most recently created)
    // This maintains backward compatibility
    $first_sess = array_key_first($found_sessions);
    ini_set('session.cookie_path', '/');
    session_name($first_sess);
    session_start();
}

/**
 * Check if current user has a specific permission
 */
function has_permission(string $permission_code): bool {
    global $conn;

    start_role_session();

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

function has_any_permission(array $permission_codes): bool {
    foreach ($permission_codes as $code) {
        if (has_permission($code)) {
            return true;
        }
    }
    return false;
}

function has_all_permissions(array $permission_codes): bool {
    foreach ($permission_codes as $code) {
        if (!has_permission($code)) {
            return false;
        }
    }
    return true;
}

function require_permission(string $permission_code, string $redirect_url = "login.php"): void {
    if (!has_permission($permission_code)) {
        header("Location: $redirect_url");
        exit();
    }
}

function require_any_permission(array $permission_codes, string $redirect_url = "login.php"): void {
    if (!has_any_permission($permission_codes)) {
        header("Location: $redirect_url");
        exit();
    }
}

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

function get_role_display_name(string $role): string {
    $names = [
        'admin' => 'System Administrator',
        'election_officer' => 'Election Officer',
        'observer' => 'Observer / Auditor',
        'voter' => 'Voter'
    ];
    return $names[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

function get_role_color(string $role): string {
    $colors = [
        'admin' => '#dc2626',
        'election_officer' => '#2563eb',
        'observer' => '#059669',
        'voter' => '#6b7280'
    ];
    return $colors[$role] ?? '#6b7280';
}

function get_role_bg_color(string $role): string {
    $colors = [
        'admin' => '#fee2e2',
        'election_officer' => '#dbeafe',
        'observer' => '#d1fae5',
        'voter' => '#f3f4f6'
    ];
    return $colors[$role] ?? '#f3f4f6';
}

function is_official(): bool {
    start_role_session();
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'election_officer', 'observer']);
}

function is_admin(): bool {
    start_role_session();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function is_election_officer(): bool {
    start_role_session();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'election_officer';
}

function is_observer(): bool {
    start_role_session();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'observer';
}

function is_voter(): bool {
    start_role_session();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'voter';
}

function require_observer(): void {
    start_role_session();
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'election_officer', 'observer'])) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Universal session timeout check (30 minutes)
 */
function check_session_timeout(string $redirect_url = "login.php?timeout=1"): void {
    start_role_session();

    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        // Destroy session
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
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
 */
function require_auth(array $allowed_roles = [], string $redirect_url = "login.php"): void {
    start_role_session();

    // Check if logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: $redirect_url");
        exit();
    }

    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }

    $_SESSION['last_activity'] = time();

    // Check role restriction
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        if ($redirect_url === 'unauthorized.php' && isset($_SESSION['role'])) {
            $redirect_url .= '?role=' . urlencode($_SESSION['role']);
        }
        header("Location: $redirect_url");
        exit();
    }
}

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

function get_role_badge(string $role): string {
    $bg = get_role_bg_color($role);
    $color = get_role_color($role);
    $name = get_role_display_name($role);

    return '<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;background:' . $bg . ';color:' . $color . '">' 
        . '<i class="fas fa-user-tag" style="font-size:10px;"></i>' . $name . '</span>';
}