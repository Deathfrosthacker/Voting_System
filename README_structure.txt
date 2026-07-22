Voting System - Code Structure Guide
===================================

This project is a PHP-based online voting system with role-based access.

Main entry points:
- index.php -> redirects visitors to the public landing page.
- Landing.php -> public homepage with Login and Register links.
- Login.php -> login form UI.
- Register.php -> registration form UI.

Authentication flow:
- logindb.php -> processes login requests and starts the correct role-based session.
- registerdb.php -> processes new voter registration submissions.
- csrf_helper.php -> provides CSRF protection helpers.
- rbac_helper.php -> handles authentication, authorization, and session timeout checks.

Role-based dashboards:
- admin_dashboard.php -> main admin overview.
- election_officer_dashboard.php -> officer-specific dashboard.
- observer_dashboard.php -> observer-specific dashboard.
- voter_dashboard.php -> voter-facing dashboard with active elections.

Core modules:
- positions.php -> create and manage election positions.
- add_candidate.php -> create and manage candidates.
- vote.php -> voting interface for a selected position.
- winners.php -> displays election results.
- votes.php -> overview of vote records.
- activity_logs.php -> shows system activity history.

User management:
- register_voter.php -> admin/officer registration of voters.
- manage_officials.php -> create/manage election officers and observers.
- adminadd.php -> admin account management.

Support and configuration:
- sidebar.php -> role-based navigation menu.
- config/connection.php -> database connection settings.
- auto_declare.php -> automatic election declaration logic.
- regions.php -> region management.
- affiliations.php -> affiliation management.
- change_password.php -> password change flow.
- logout.php -> destroys sessions and logs the user out.

Navigation tip:
- Start from Landing.php or Login.php.
- After login, the system redirects users to their dashboard based on role.
- Admin and officer pages generally include sidebar.php for navigation.
