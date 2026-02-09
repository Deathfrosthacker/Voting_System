<style>
    /* Sidebar Styles */
    .sidebar {
        width: 250px;
        background: linear-gradient(180deg, #1e40af 0%, #1e3a8a 100%);
        color: white;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 1000;
        transition: transform 0.3s ease;
    }

    .sidebar-header {
        padding: 30px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .logo h2 {
        font-size: 20px;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.5px;
    }

    .logo p {
        font-size: 12px;
        opacity: 0.7;
        margin: 2px 0 0 0;
    }

    .sidebar-nav {
        flex: 1;
        padding: 20px 0;
        overflow-y: auto;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all 0.3s ease;
        margin: 0 12px 6px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
    }

    .nav-item:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        transform: translateX(4px);
    }

    .nav-item.active {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .nav-item svg {
        flex-shrink: 0;
    }

    .sidebar-footer {
        padding: 20px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .nav-item.logout {
        color: #fca5a5;
    }

    .nav-item.logout:hover {
        background: rgba(239, 68, 68, 0.1);
        color: #fee2e2;
    }

    /* Scrollbar Styling */
    .sidebar-nav::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar-nav::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
    }

    .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 3px;
    }

    .sidebar-nav::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    /* Hamburger Menu Button */
    .hamburger-btn {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1001;
        background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
        border: none;
        padding: 12px;
        border-radius: 8px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transition: all 0.3s ease;
    }

    .hamburger-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }

    .hamburger-btn span {
        display: block;
        width: 25px;
        height: 3px;
        background: white;
        margin: 5px 0;
        border-radius: 2px;
        transition: all 0.3s ease;
    }

    .hamburger-btn.active span:nth-child(1) {
        transform: rotate(45deg) translate(8px, 8px);
    }

    .hamburger-btn.active span:nth-child(2) {
        opacity: 0;
    }

    .hamburger-btn.active span:nth-child(3) {
        transform: rotate(-45deg) translate(8px, -8px);
    }

    /* Overlay for mobile */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .sidebar-overlay.active {
        opacity: 1;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .hamburger-btn {
            display: block;
        }

        .sidebar-overlay {
            display: block;
        }

        /* Adjust main content margin on mobile */
        .main-content {
            margin-left: 0 !important;
        }
    }
</style>

<!-- Hamburger Menu Button (Mobile Only) -->
<button class="hamburger-btn" id="hamburgerBtn">
    <span></span>
    <span></span>
    <span></span>
</button>

<!-- Sidebar Overlay (Mobile Only) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Component -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                <rect width="40" height="40" rx="8" fill="white" opacity="0.1"/>
                <path d="M20 10L28 15V25L20 30L12 25V15L20 10Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
            </svg>
            <div>
                <h2>Vote System</h2>
                <p>Admin Panel</p>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="./admin_dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M3 9L10 3L17 9V17C17 17.5523 16.5523 18 16 18H4C3.44772 18 3 17.5523 3 17V9Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Dashboard</span>
        </a>

        <a href="./positions.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == './Position/positions.php' ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <rect x="3" y="3" width="14" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                <path d="M10 7V13M7 10H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span>Positions</span>
        </a>

        <a href="./add_candidate.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == './Candidate/add_candidate.php' ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M16 17V15C16 13.3431 14.6569 12 13 12H7C5.34315 12 4 13.3431 4 15V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <circle cx="10" cy="6" r="3" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span>Candidates</span>
        </a>

        <a href="./votes.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'votes.php' ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <rect x="3" y="3" width="14" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span>Votes</span>
        </a>

        <a href="./adminadd.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M16 17V15C16 13.3431 14.6569 12 13 12H7C5.34315 12 4 13.3431 4 15V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <circle cx="10" cy="6" r="3" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span>Add Admin</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="./logout.php" class="nav-item logout">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M13 3H16C16.5523 3 17 3.44772 17 4V16C17 16.5523 16.5523 17 16 17H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M8 13L3 10L8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M3 10H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span>Logout</span>
        </a>
    </div>
</div>

<script>
    // Sidebar toggle functionality
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    // Toggle sidebar
    function toggleSidebar() {
        hamburgerBtn.classList.toggle('active');
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        
        // Prevent body scroll when sidebar is open on mobile
        if (sidebar.classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    // Event listeners
    hamburgerBtn.addEventListener('click', toggleSidebar);
    sidebarOverlay.addEventListener('click', toggleSidebar);

    // Close sidebar when clicking on a nav item (mobile)
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
        });
    });

    // Close sidebar on window resize if it's open
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            hamburgerBtn.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
</script>