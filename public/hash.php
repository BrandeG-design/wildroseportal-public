<?php
// =============================================================================
// FILE: hash.php
// PURPOSE: One-time utility script for generating a bcrypt password hash.
//          Replace 'insert password here' with the desired password, run the
//          script once in the browser, then copy the output hash into the
//          database manually (e.g. via phpMyAdmin).
//
// WARNING: This file should NOT be left accessible on a production or shared
//          server. Remove or restrict access to it after use.
//
// USAGE:   1. Replace 'insert password here' with the actual password
//          2. Visit this file in the browser
//          3. Copy the printed hash
//          4. Paste it into the PASSWORD_HASH column in the staff_login table
//          5. Delete or move this file when done
// =============================================================================

echo password_hash('insert password here', PASSWORD_DEFAULT);
