<?php
// =============================================================================
// FILE: admin.php
// PURPOSE: The Admin Portal landing page. Provides staff with navigation to all
//          administrative functions such as viewing tickets, generating reports,
//          changing their password, ending all sessions, logging out, and
//          viewing the About page.
//
// ACCESS:   Requires staff to be logged in (enforced by checkLogin())
// LINKED CSS: assets/css/admin.css
// NAVIGATION: Accessible from index.php via the "Admin" button
// =============================================================================

require_once "../includes/session.php";
require_once "../SQL_Connection.php";

// Redirect to login page if the user is not authenticated
checkLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- See head.txt for more information -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal</title>
    <link rel="icon" type="image/png" href="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png">
    <link rel="stylesheet" href="http://customer.altismsp.com/assets/css/admin.css">
</head>
<body>

    <!-- ── Nav Bar ──────────────────────────────────────────────────────────── -->
    <nav class="nav-bar">
        <img src="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png"
             width="100" height="39" alt="Altis MSP logo">
    </nav>

    <div class="page-wrapper">

        <h1 class="page-title">Admin Portal</h1>

        <!-- ── Navigation Buttons ───────────────────────────────────────────── -->
        <!-- Each button links to a different admin function.                   -->
        <!-- "danger" class highlights destructive actions in red.              -->
        <div class="nav-grid">

            <!-- View and search all submitted tickets -->
            <a class="nav-btn" href="http://customer.altismsp.com/public/viewtickets.php">
                View Tickets
            </a>

            <!-- Generate filtered reports and export to CSV/Excel -->
            <a class="nav-btn" href="http://customer.altismsp.com/public/report.php">
                Create Report
            </a>

            <!-- Change the currently logged-in staff member's password -->
            <a class="nav-btn" href="http://customer.altismsp.com/public/password.php">
                Change Password
            </a>

            <!-- View project and team information -->
            <a class="nav-btn" href="http://customer.altismsp.com/public/about.php">
                About WildRose Portal
            </a>

            <!-- Destructive — ends all active staff sessions across all devices -->
            <a class="nav-btn danger" href="http://customer.altismsp.com/public/end.php">
                End All Sessions
            </a>

            <!-- Logs out the current staff member and redirects to login -->
            <a class="nav-btn danger" href="http://customer.altismsp.com/public/logout.php">
                Logout
            </a>

        </div>

        <!-- ── Back Button ───────────────────────────────────────────────────── -->
        <div class="back-btn-wrapper">
            <a class="back-btn" href="http://customer.altismsp.com/index.php">Back</a>
        </div>

    </div><!-- /page-wrapper -->

    <!-- ── Footer ───────────────────────────────────────────────────────────── -->
    <footer>
        <p class="copyright">© 2026 FireNode & AltisMSP</p>
    </footer>

</body>
</html>
