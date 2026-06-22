<?php
// =============================================================================
// FILE: auth.php
// PURPOSE: Staff login page. Validates username and password against the
//          database, and creates a session for authenticated staff members.
//          Redirects to index.php on successful login.
//
// LINKED CSS:  assets/css/auth.css
// LINKED JS:   assets/js/auth.js  (handles show/hide password toggle)
// DEPENDS ON:  includes/session.php, SQL_Connection.php, validateCred.php
// =============================================================================

// Display all PHP errors — remove or disable before production deployment
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "../includes/session.php";
require_once "../SQL_Connection.php";

// validateCred.php is in the same directory as auth.php (modules/m1-auth/)
require_once __DIR__ . "/validateCred.php";

// ── Already logged in ─────────────────────────────────────────────────────────
// If a valid session already exists, skip the login page entirely
if (isset($_SESSION["staff_id"])) {
    header("Location: http://customer.altismsp.com/index.php");
    exit();
}

$errorMessage = "";
$username     = "";

// ── Handle login form submission ──────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Lowercase the username so login is case-insensitive
    // (validateCred.php uses LOWER(USERNAME) in the SQL query to match)
    $username = strtolower(trim($_POST["username"]));
    $password = $_POST["password"];

    // ── Basic empty field check ───────────────────────────────────────────────
    if (empty($username) || empty($password)) {
        $errorMessage = "Username and password are required.";
    } else {

        // ── Validate credentials against the database ─────────────────────────
        // Returns the staff record array on success, or false on failure
        $staff = validateCredentials($username, $password);

        if ($staff) {
            // Store staff ID and username in session for use across the app
            $_SESSION["staff_id"]   = $staff["SL_ID"];
            $_SESSION["staff_name"] = $staff["USERNAME"];
            header("Location: http://customer.altismsp.com/index.php");
            exit();
        } else {
            // Generic error message — intentionally vague for security
            $errorMessage = "Invalid username or password.";
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
    <title>Authorization</title>
    <link rel="icon" type="image/png" href="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png">
    <link rel="stylesheet" href="http://customer.altismsp.com/assets/css/auth.css?v=1.1" >
</head>
<body>

    <!-- ── Nav Bar ──────────────────────────────────────────────────────────── -->
    <nav class="nav-bar">
        <img src="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png"
             width="100" height="39" alt="Altis MSP logo">
    </nav>

    <!-- ── Page Header ──────────────────────────────────────────────────────── -->
    <div class="header-container">
        <img src="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png"
            alt="WildRose Portal Logo" id="jumpscare">
    </div>
    <h1>Log In</h1>

    <!-- ── Error Message ────────────────────────────────────────────────────── -->
    <!-- Only rendered if a failed login attempt was made -->
    <?php if (!empty($errorMessage)): ?>
        <div class="error-alert" role="alert">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- ── Login Form ───────────────────────────────────────────────────────── -->
    <form action="auth.php" method="POST">

        <!-- Username field — value re-populated on failed login so staff
             don't have to retype it -->
        <label class="label" for="Username">Username</label>
        <input class="input" type="text" id="Username" name="username"
               minlength="4" maxlength="20" required autocomplete="username"
               value="<?= htmlspecialchars($username) ?>">

        <!-- Password field — show/hide toggle handled by auth.js -->
        <label class="label" for="Password">Password</label>
        <div class="password-input">
            <input class="input" type="password" id="Password" name="password"
                   minlength="8" maxlength="20" required autocomplete="current-password"
                   placeholder="••••••••">

            <!-- Toggles the password input between type="password" and type="text" -->
            <button class="showpsw" type="button" id="Showpsw">Show</button>
        </div>

        <input class="login" type="submit" value="Log In">
    </form>

    <!-- auth.js handles the show/hide password toggle on the Showpsw button -->
    <script src="/assets/js/auth.js"></script>
    <script src="/assets/js/jumpscare.js?v=1.7"></script>

    <!-- ── Footer ───────────────────────────────────────────────────────────── -->
    <footer>
        <p class="copyright">© 2026 FireNode & AltisMSP</p>
    </footer>

</body>
</html>
