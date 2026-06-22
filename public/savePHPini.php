<?php
// =============================================================================
// FILE: savePHPini.php
// PURPOSE: Provides the endAllSessions() function, which deletes every active
//          PHP session file from the server filesystem and destroys the current
//          admin session. This is the core of the "End All Sessions" feature
//          accessed from end.php.
//
//          Returns a detailed result array so the caller can report how many
//          sessions were deleted and whether any failed.
//
// DEPENDS ON: readPHPini.php (for getSessionSavePath())
// USED BY:    end.php
// =============================================================================

require_once __DIR__ . '/readPHPini.php';

// ── endAllSessions ────────────────────────────────────────────────────────────
// Deletes all sess_* session files from the PHP session save directory,
// then destroys the current admin session so the caller is also logged out.
//
// @return array  Result summary with keys:
//                  success      — true if all files were deleted without errors
//                  deleted      — count of successfully deleted session files
//                  failed       — count of files that could not be deleted
//                  failed_files — array of file paths that failed deletion
//                  message      — human-readable summary of the outcome
function endAllSessions(): array
{
    $savePath = getSessionSavePath();

    // Build a result array to return a detailed outcome to the caller
    $result = [
        'success'      => false,
        'deleted'      => 0,
        'failed'       => 0,
        'failed_files' => [],
        'message'      => '',
    ];

    // ── Validate the session save path ────────────────────────────────────────
    if (!is_dir($savePath)) {
        $result['message'] = "Session save path does not exist: {$savePath}";
        return $result;
    }

    // Check write permission before attempting any deletions
    if (!is_writable($savePath)) {
        $result['message'] = "Session save path is not writable: {$savePath}";
        return $result;
    }

    // ── Find all session files ────────────────────────────────────────────────
    // PHP names session files sess_{sessionId} in the save directory
    $files = glob($savePath . DIRECTORY_SEPARATOR . 'sess_*');

    if ($files === false) {
        $result['message'] = 'Failed to read session directory.';
        return $result;
    }

    // Nothing to delete — return early with a success state
    if (empty($files)) {
        $result['success'] = true;
        $result['message'] = 'No active sessions found. Nothing to delete.';
        return $result;
    }

    // ── Delete each session file ──────────────────────────────────────────────
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }

        if (unlink($file)) {
            $result['deleted']++;
        } else {
            // Track failures so the caller can report them
            $result['failed']++;
            $result['failed_files'][] = $file;
        }
    }

    // ── Destroy the current admin session ─────────────────────────────────────
    // The admin who triggered this action also gets logged out.
    // Mirrors the three-step logout process in logout.php:
    // 1. Clear session data, 2. Expire cookie, 3. Destroy server session
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000, // expiry in the past forces browser to delete cookie
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    // ── Build result message ──────────────────────────────────────────────────
    $result['success'] = ($result['failed'] === 0);
    $result['message'] = $result['success']
        ? "All {$result['deleted']} session(s) successfully terminated."
        : "{$result['deleted']} session(s) deleted. {$result['failed']} could not be deleted.";

    return $result;
}
