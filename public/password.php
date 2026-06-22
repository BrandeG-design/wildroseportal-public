<?php
// =============================================================================
// FILE: password.php
// PURPOSE: Allows a logged-in staff member to change their own password.
//          Validates the current password before accepting the new one, then
//          hashes and stores the new password in the database.
//
// ACCESS:     Requires staff to be logged in (enforced by checkLogin())
// LINKED CSS: assets/css/admintools.css
// LINKED JS:  assets/js/auth.js  (show/hide password toggle on the new password field)
// DEPENDS ON: includes/session.php, SQL_Connection.php, validateCred.php
// =============================================================================

require_once "../includes/session.php";
require_once "../SQL_Connection.php";

// validateCred.php is in the same directory as password.php (modules/m1-auth/)
require_once __DIR__ . "/validateCred.php";
checkLogin();

$errorMessage = "";

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $currentPassword = $_POST["currentpassword"]   ?? "";
    $newPassword     = $_POST["newpassword"]        ?? "";
    $confirmPassword = $_POST["confirmnewpassword"] ?? "";

    // ── Client-side validation mirrors ───────────────────────────────────────
    // These checks duplicate the HTML minlength/required attributes as a
    // server-side safety net in case the browser validation is bypassed
    if ($newPassword !== $confirmPassword) {
        $errorMessage = "New passwords do not match.";
    } elseif (strlen($newPassword) < 8) {
        $errorMessage = "New password must be at least 8 characters.";
    } else {

        // ── Verify current password ───────────────────────────────────────────
        // Uses the same validateCredentials() function as the login page to
        // confirm the staff member knows their existing password before allowing
        // a change — prevents unauthorized password changes if a session is left open
        $staff = validateCredentials($_SESSION["staff_name"], $currentPassword);

        if (!$staff) {
            $errorMessage = "Current password is incorrect.";
        } else {
            // ── Hash and store the new password ───────────────────────────────
            // PASSWORD_DEFAULT uses bcrypt — the hash includes the salt so no
            // separate salt column is needed in the database
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db      = getDB();
            $stmt    = $db->prepare("UPDATE staff_login SET PASSWORD_HASH = ? WHERE SL_ID = ?");
            $stmt->bind_param("si", $newHash, $_SESSION["staff_id"]);
            $stmt->execute();
            $stmt->close();
            $db->close();

            // Redirect back to admin on success
            header("Location: admin.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- See head.txt for more information -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
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

        <h1 class="page-title">Change Password</h1>
        <p class="page-description">Enter your current password, then choose a new one.</p>

        <!-- ── Error Message ─────────────────────────────────────────────────── -->
        <!-- Displayed if validation fails (passwords don't match, too short,
             or current password is incorrect) -->
        <?php if (!empty($errorMessage)): ?>
            <div class="error-alert" role="alert"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <div class="section-card">
            <h2 class="section-heading">Update Password</h2>

            <!-- ── Password Change Form ──────────────────────────────────────── -->
            <form method="POST" action="password.php">

                <!-- Current password — verified against DB before allowing change -->
                <div class="form-field">
                    <label class="field-label" for="currentpassword">Current Password</label>
                    <input class="field-input" type="password" id="currentpassword"
                           name="currentpassword" placeholder="••••••••"
                           minlength="8" maxlength="20" required>
                </div>

                <!-- New password — show/hide toggle handled by auth.js -->
                <div class="form-field">
                    <label class="field-label" for="newpassword">New Password</label>
                    <div style="position: relative;">
                        <input class="field-input" type="password" id="newpassword"
                               name="newpassword" placeholder="••••••••"
                               minlength="8" maxlength="20" required>
                        <button class="showpsw" type="button" id="Showpsw">Show</button>
                    </div>
                </div>

                <!-- Confirm new password — must match newpassword or server returns error -->
                <div class="form-field">
                    <label class="field-label" for="confirmnewpassword">Confirm New Password</label>
                    <input class="field-input" type="password" id="confirmnewpassword"
                           name="confirmnewpassword" placeholder="••••••••"
                           minlength="8" maxlength="20" required>
                </div>

                <div class="form-actions">
                    <button class="primary-btn" type="submit">Update Password</button>
                    <!-- Clear resets all three fields to empty -->
                    <button class="secondary-btn" type="reset">Clear</button>
                </div>

            </form>
        </div>

        <!-- ── Back Button ───────────────────────────────────────────────────── -->
        <div class="back-btn-wrapper">
            <a class="back-btn" href="http://customer.altismsp.com/public/admin.php">Back</a>
        </div>

    </div><!-- /page-wrapper -->

    <!-- auth.js handles the Show/Hide toggle on the new password field -->
    <script src="/assets/js/auth.js"></script>

    <!-- ── Footer ───────────────────────────────────────────────────────────── -->
    <footer>
        <p class="copyright">© 2026 FireNode & AltisMSP</p>
    </footer>

</body>
</html>
