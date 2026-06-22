<?php
// =============================================================================
// FILE: logout.php
// PURPOSE: Logs out the current staff member by clearing all session data,
//          expiring the session cookie in the browser, and destroying the
//          server-side session. Redirects to the login page when complete.
//
// ACCESS:   Can be linked from anywhere in the appwith no login check needed
//           since the goal is to end the session regardless of its state.
// USED BY:  admin.php (Logout button)
// =============================================================================

require_once "../includes/session.php";

// ── Clear session data ────────────────────────────────────────────────────────
// Empties the $_SESSION superglobal — removes all stored values (staff_id,
// staff_name, customer, ticket, etc.) without destroying the session itself yet
$_SESSION = [];

// ── Expire the session cookie ─────────────────────────────────────────────────
// Sets the cookie expiry to a time in the past so the browser deletes it.
// This prevents the browser from sending the old session ID on the next request.
// Only runs if PHP is configured to use cookies for session management.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),   // name of the session cookie (usually "PHPSESSID")
        '',               // empty value
        time() - 42000,  // expiry set in the past to force browser deletion
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// ── Destroy the server-side session ──────────────────────────────────────────
// Removes the session file/record from the server so the session ID
// can no longer be used to resume the session
session_destroy();

// ── Redirect to login ─────────────────────────────────────────────────────────
header("Location: /public/auth.php");
exit();
