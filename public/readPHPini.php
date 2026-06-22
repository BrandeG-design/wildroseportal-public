<?php
// =============================================================================
// FILE: readPHPini.php
// PURPOSE: Utility functions for reading PHP session configuration and
//          inspecting active session files on the server filesystem.
//          Used by the "End All Sessions" feature in end.php.
//
// PROVIDES: getSessionSavePath() — resolves where PHP stores session files
//           readAllSessions()    — returns a list of all active session files
//           getSessionConfig()   — returns key PHP session config values
//
// USED BY:  savePHPini.php (requires this file), end.php
// =============================================================================

// ── getSessionSavePath ────────────────────────────────────────────────────────
// Resolves the directory where PHP stores session files, handling edge cases
// like an empty save path (falls back to the system temp dir) and the
// "N;/path" depth prefix format that some PHP configs use.
//
// @return string  Absolute path to the session save directory, no trailing slash
function getSessionSavePath(): string
{
    $path = ini_get('session.save_path');

    // Fall back to the system temp directory if no path is configured
    if (empty($path)) {
        $path = sys_get_temp_dir();
    }

    // Some PHP configs prefix the path with a depth value e.g. "2;/var/lib/php/sessions"
    // Strip everything before the last semicolon to get just the path
    if (str_contains($path, ';')) {
        $parts = explode(';', $path);
        $path  = end($parts);
    }

    // Remove any trailing slashes for consistent path joining later
    return rtrim($path, '/\\');
}

// ── readAllSessions ───────────────────────────────────────────────────────────
// Scans the session save directory for all active session files (named sess_*)
// and returns metadata about each one. Used to display active sessions
// and support the bulk-delete feature in end.php.
//
// @return array  Array of session info arrays, sorted by most recently active.
//                Each entry contains: id, file path, size, last_active, raw_data
function readAllSessions(): array
{
    $savePath = getSessionSavePath();
    $sessions = [];

    // Return empty array if the session directory doesn't exist
    if (!is_dir($savePath)) {
        return $sessions;
    }

    // PHP session files are named sess_{sessionId}
    $files = glob($savePath . DIRECTORY_SEPARATOR . 'sess_*');

    if ($files === false || empty($files)) {
        return $sessions;
    }

    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }

        $filename  = basename($file);
        $sessionId = substr($filename, 5); // strip the 'sess_' prefix to get the ID

        $sessions[] = [
            'id'          => $sessionId,
            'file'        => $file,
            'size'        => filesize($file),
            'last_active' => date('Y-m-d H:i:s', filemtime($file)), // last modified = last active
            'raw_data'    => file_get_contents($file),
        ];
    }

    // Sort by most recently active first
    usort($sessions, fn($a, $b) => strcmp($b['last_active'], $a['last_active']));

    return $sessions;
}

// ── getSessionConfig ──────────────────────────────────────────────────────────
// Returns a summary of the current PHP session configuration values.
// Useful for debugging session behaviour or auditing security settings.
//
// @return array  Associative array of session config key => human-readable value
function getSessionConfig(): array
{
    return [
        'save_handler'    => ini_get('session.save_handler'),
        'save_path'       => getSessionSavePath(),
        'gc_maxlifetime'  => ini_get('session.gc_maxlifetime') . ' seconds',
        'session_name'    => ini_get('session.name'),
        'cookie_lifetime' => ini_get('session.cookie_lifetime') . ' seconds',
        'cookie_secure'   => ini_get('session.cookie_secure')   ? 'Yes' : 'No',
        'cookie_httponly' => ini_get('session.cookie_httponly')  ? 'Yes' : 'No',
    ];
}
