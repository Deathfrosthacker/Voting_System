<?php
/**
 * Sidebar Navigation Component
 * Displays navigation menu based on user's RBAC role
 * Roles: admin, election_officer, observer, voter
 * 
 * NOTE: This file should be included AFTER session_start() and rbac_helper.php
 * have already been loaded by the parent page.
 */

// Do NOT call session_start() here — parent page already did it
// Do NOT call require_observer() here — parent page handles auth

$current_role = $_SESSION['role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);

// Determine dashboard URL based on role - EACH ROLE HAS THEIR OWN DASHBOARD
$dashboard_url = match($current_role) {
    'admin' => 'admin_dashboard.php',
    'election_officer' => 'election_officer_dashboard.php',
    'observer' => 'observer_dashboard.php',
    default => 'voter_dashboard.php'
};

// Navigation items with RBAC permissions
$nav_items = [
    // Dashboard - available to all authenticated users (each goes to their own)
    [
        'label' => 'Dashboard',
        'icon' => 'fa-chart-line',
        'url' => $dashboard_url,
        'permission' => null, // All authenticated users
        'roles' => ['admin', 'election_officer', 'observer', 'voter']
    ],
    // Elections / Positions
    [
        'label' => 'Manage Elections',
        'icon' => 'fa-briefcase',
        'url' => 'positions.php',
        'permission' => 'manage_elections',
        'roles' => ['admin', 'election_officer']
    ],
    // Candidates
    [
        'label' => 'Manage Candidates',
        'icon' => 'fa-users',
        'url' => 'add_candidate.php',
        'permission' => 'manage_candidates',
        'roles' => ['admin', 'election_officer']
    ],
    // Regions
    [
        'label' => 'Manage Regions',
        'icon' => 'fa-globe',
        'url' => 'regions.php',
        'permission' => 'manage_regions',
        'roles' => ['admin', 'election_officer']
    ],
    // Affiliations
    [
        'label' => 'Affiliations',
        'icon' => 'fa-flag',
        'url' => 'affiliations.php',
        'permission' => 'manage_affiliations',
        'roles' => ['admin', 'election_officer']
    ],
    // Officials Management (Admin only)
    [
        'label' => 'Manage Officials',
        'icon' => 'fa-user-shield',
        'url' => 'manage_officials.php',
        'permission' => 'manage_officials',
        'roles' => ['admin']
    ],
    // Admins (Admin only)
    [
        'label' => 'Manage Admins',
        'icon' => 'fa-user-cog',
        'url' => 'adminadd.php',
        'permission' => 'manage_officials',
        'roles' => ['admin']
    ],
    // Votes Overview
    [
        'label' => 'Votes Overview',
        'icon' => 'fa-vote-yea',
        'url' => 'votes.php',
        'permission' => 'view_votes',
        'roles' => ['admin', 'election_officer', 'observer']
    ],
    // Election Results
    [
        'label' => 'Election Results',
        'icon' => 'fa-trophy',
        'url' => 'winners.php',
        'permission' => 'view_results',
        'roles' => ['admin', 'election_officer', 'observer']
    ],
    // Activity Logs
    [
        'label' => 'Activity Logs',
        'icon' => 'fa-history',
        'url' => 'activity_logs.php',
        'permission' => 'view_logs',
        'roles' => ['admin', 'election_officer', 'observer']
    ],
    // Diagnostic (Admin only)
    [
        'label' => 'Diagnostics',
        'icon' => 'fa-stethoscope',
        'url' => 'diagnostic.php',
        'permission' => 'system_settings',
        'roles' => ['admin']
    ],
];

// Filter nav items based on current user's role/permissions
$visible_nav = [];
foreach ($nav_items as $item) {
    // Check if role is allowed
    if (!in_array($current_role, $item['roles'])) {
        continue;
    }
    // Check permission if required
    if ($item['permission'] !== null && !has_permission($item['permission'])) {
        continue;
    }
    $visible_nav[] = $item;
}
?>

<!-- Sidebar Navigation -->
<aside class="sidebar-nav-container" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="fas fa-vote-yea"></i>
        </div>
        <div class="brand-text">
            <h3>VoteSystem</h3>
            <span class="role-badge-sidebar" style="background: <?php echo get_role_bg_color($current_role); ?>; color: <?php echo get_role_color($current_role); ?>">
                <?php echo get_role_display_name($current_role); ?>
            </span>
        </div>
    </div>

    <nav class="sidebar-menu">
        <?php foreach ($visible_nav as $item): 
            $is_active = ($current_page === $item['url']);
        ?>
        <a href="<?php echo $item['url']; ?>" class="sidebar-link <?php echo $is_active ? 'active' : ''; ?>">
            <i class="fas <?php echo $item['icon']; ?>"></i>
            <span><?php echo $item['label']; ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer-nav">
        <a href="logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Mobile Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<style>
/* Sidebar Container */
.sidebar-nav-container {
    width: 260px;
    height: 100vh;
    background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 50%, #2563eb 100%);
    color: white;
    position: fixed;
    left: 0;
    top: 0;
    display: flex;
    flex-direction: column;
    z-index: 1000;
    transition: transform 0.3s ease;
    box-shadow: 4px 0 20px rgba(0,0,0,0.15);
}

.sidebar-brand {
    padding: 24px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-icon {
    width: 44px;
    height: 44px;
    background: rgba(255,255,255,0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.brand-text h3 {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
}

.role-badge-sidebar {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    margin-top: 4px;
}

/* Menu */
.sidebar-menu {
    flex: 1;
    padding: 16px 12px;
    overflow-y: auto;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: rgba(255,255,255,0.75);
    text-decoration: none;
    border-radius: 10px;
    margin-bottom: 4px;
    transition: all 0.25s ease;
    font-size: 14px;
    font-weight: 500;
}

.sidebar-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    transform: translateX(4px);
}

.sidebar-link.active {
    background: rgba(255,255,255,0.2);
    color: white;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.sidebar-link i {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.sidebar-link.logout-link {
    color: #fca5a5;
}

.sidebar-link.logout-link:hover {
    background: rgba(239,68,68,0.15);
    color: #fecaca;
}

.sidebar-footer-nav {
    padding: 16px 12px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

/* Mobile Toggle */
.sidebar-toggle {
    display: none;
    position: fixed;
    top: 16px;
    left: 16px;
    z-index: 1001;
    background: #1e40af;
    color: white;
    border: none;
    width: 44px;
    height: 44px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 18px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 999;
}

/* Scrollbar */
.sidebar-menu::-webkit-scrollbar {
    width: 5px;
}
.sidebar-menu::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar-menu::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 3px;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar-nav-container {
        transform: translateX(-100%);
    }
    .sidebar-nav-container.open {
        transform: translateX(0);
    }
    .sidebar-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .sidebar-overlay.active {
        display: block;
    }
    .main-content, .main, .content {
        margin-left: 0 !important;
    }
}
</style>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}
</script>