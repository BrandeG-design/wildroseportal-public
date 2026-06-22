<?php
// =============================================================================
// FILE: end.php
// PURPOSE: Allows an authenticated staff member to forcibly end ALL active
//          sessions across every device logged into WildRose Portal. This logs
//          out all staff simultaneously, including the person triggering it.
//
//          Uses a two-step confirmation flow to prevent accidental execution,
//          and a CSRF token to prevent the action from being triggered by a
//          malicious third-party page.
//
// ACCESS:     Requires staff_id in session (own check — not via checkLogin())
// LINKED CSS: assets/css/admintools.css
// DEPENDS ON: readPHPini.php (getSessionSavePath), savePHPini.php (endAllSessions)
// USED BY:    admin.php (End All Sessions button)
// =============================================================================

require_once "../includes/session.php";
require_once __DIR__ . "/readPHPini.php";
require_once __DIR__ . "/savePHPini.php";

// ── Auth check ────────────────────────────────────────────────────────────────
// Uses a direct session check rather than checkLogin() because endAllSessions()
// destroys the session, and checkLogin() may behave unexpectedly after that.
if (!isset($_SESSION['staff_id'])) {
    header("Location: http://customer.altismsp.com/public/auth.php");
    exit();
}

// ── CSRF token ────────────────────────────────────────────────────────────────
// Generated once per session and embedded in the confirmation form.
// Verified before executing the destructive action to prevent cross-site
// request forgery attacks (a third-party page tricking staff into triggering this).
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$confirmStep = false;

// ── Step 2: Confirmed — validate CSRF token and end all sessions ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_end_all'])) {

    // Reject the request if the CSRF token is missing or doesn't match
    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('403 Forbidden: Invalid CSRF token.');
    }

    // Delete all session files and destroy the current session
    // The admin is also logged out as part of this process
    endAllSessions();
    header("Location: http://customer.altismsp.com/public/auth.php");
    exit();
}

// ── Step 1: Initial button click — show the confirmation screen ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_end_all'])) {
    $confirmStep = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- See head.txt for more information -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>End All Sessions</title>
    <link rel="icon" type="image/png" href="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png">
    <link rel="stylesheet" href="http://customer.altismsp.com/assets/css/admintools.css">
</head>
<body>

    <!-- ── Nav Bar ──────────────────────────────────────────────────────────── -->
    <nav class="nav-bar">
        <img src="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png"
             width="100" height="39" alt="Altis MSP logo">
    </nav>

    <div class="page-wrapper">

        <h1 class="page-title">End All Sessions</h1>
        <p class="page-description">This will end all current active sessions and log out all devices including this one.</p>

        <div class="section-card">
            <!-- Section heading changes based on which step of the flow we're on -->
            <h2 class="section-heading"><?= $confirmStep ? "Confirm Action" : "End Sessions" ?></h2>

            <?php if ($confirmStep): ?>
                <!-- ── Step 2: Confirmation screen ───────────────────────────── -->
                <!-- CSRF token is embedded as a hidden field and verified on POST -->
                <div class="warning-notice">
                    <strong>Are you sure?</strong> This will immediately log out all staff on all devices and cannot be undone.
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="confirm_end_all" value="1">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="form-actions">
                        <button class="danger-btn" type="submit">Yes, End All Sessions</button>
                        <a class="secondary-btn" href="http://customer.altismsp.com/public/admin.php">Cancel</a>
                    </div>
                </form>

            <?php else: ?>
                <!-- ── Step 1: Initial action screen ─────────────────────────── -->
                <!-- Submitting this form sets $confirmStep = true and shows the warning -->
                <form action="" method="POST">
                    <input type="hidden" name="request_end_all" value="1">
                    <div class="form-actions">
                        <button class="danger-btn" type="submit">End All Sessions</button>
                        <a class="secondary-btn" href="http://customer.altismsp.com/public/admin.php">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- ── Back Button ───────────────────────────────────────────────────── -->
        <div class="back-btn-wrapper">
            <a class="back-btn" href="http://customer.altismsp.com/public/admin.php">Back</a>
        </div>

    </div><!-- /page-wrapper -->

    <!-- ── Footer ───────────────────────────────────────────────────────────── -->
    <footer>
        <p class="copyright">© 2026 FireNode & AltisMSP</p>
    </footer>

</body>
</html>
