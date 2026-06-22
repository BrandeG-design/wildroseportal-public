<?php
// =============================================================================
// FILE: validateCred.php
// PURPOSE: Provides the validateCredentials() function used by auth.php and
//          password.php to authenticate staff members against the database.
//
//          Uses LOWER(USERNAME) in the SQL query so the comparison is
//          case-insensitive. auth.php also lowercases the input before calling
//          this function, so "Admin", "ADMIN", and "admin" all match.
//
//          Password verification uses PHP's password_verify() which handles
//          bcrypt comparison securely, including timing-safe comparison to
//          prevent timing attacks.
//
// DEPENDS ON: SQL_Connection.php (getDB() must be available)
// USED BY:    auth.php (login), password.php (verify current password before change)
// =============================================================================

// ── validateCredentials ───────────────────────────────────────────────────────
// Looks up a staff record by username and verifies the provided password
// against the stored bcrypt hash.
//
// @param string $username  The lowercased username from the login form
// @param string $password  The raw password from the login form (not hashed)
// @return array|false      The staff record array on success, false on failure.
//                          Returns false for both wrong username AND wrong password
//                          so callers cannot distinguish between the two —
//                          this is intentional to prevent username enumeration.
function validateCredentials(string $username, string $password): array|false
{
    $db   = getDB();

    // LOWER(USERNAME) makes the match case-insensitive regardless of how the
    // username was originally stored in the database
    $stmt = $db->prepare(
        "SELECT SL_ID, USERNAME, PASSWORD_HASH
         FROM staff_login
         WHERE LOWER(USERNAME) = ?"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $cred = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    // If no record found, $cred is null — the && short-circuits before password_verify()
    // password_verify() does a constant-time comparison to prevent timing attacks
    return $cred && password_verify($password, $cred["PASSWORD_HASH"])
        ? $cred
        : false;
}
