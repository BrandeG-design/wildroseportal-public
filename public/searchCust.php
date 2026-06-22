<?php
// =============================================================================
// FILE: searchCust.php
// PURPOSE: Provides the searchCustomers() function used by the live search on
//          index.php. Searches the customer table by phone, name, or email and
//          returns up to 10 matching records.
//
//          Phone number searching is enhanced. If the search term looks like a
//          phone number (4+ digits), it is reformatted into ###-###-#### format
//          before the LIKE query so partial phone numbers match stored records.
//
// DEPENDS ON: SQL_Connection.php (getDB() must be available before calling)
// USED BY:    index.php (AJAX search endpoint)
// =============================================================================

// ── searchCustomers ───────────────────────────────────────────────────────────
// Searches customers by phone, last name, email, or first name.
// Returns up to 10 results ordered by database default.
//
// @param string $searchTerm  The raw search string typed by the staff member
// @return array              Array of matching customer rows (may be empty)
function searchCustomers(string $searchTerm): array
{
    $db   = getDB();
    $like = "%" . $searchTerm . "%";

    // ── Phone number formatting ───────────────────────────────────────────────
    // Strip all non-digit characters from the search term to get raw digits.
    // If there are enough digits to look like a phone number, reformat them
    // into ###-###-#### so the LIKE query matches how numbers are stored in the DB.
    // e.g. searching "4031234" becomes "403-123-4%" which matches "403-123-4567"
    $digits = preg_replace('/\D/', '', $searchTerm);
    if (strlen($digits) >= 4) {
        if (strlen($digits) >= 7) {
            // Enough digits for area code + exchange: ###-###-####
            $formatted = substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6);
        } else {
            // Only area code + partial exchange: ###-###
            $formatted = substr($digits, 0, 3) . '-' . substr($digits, 3);
        }
        $phoneLike = "%" . $formatted . "%";
    } else {
        // Not enough digits to reformat — use the raw search term for phone too
        $phoneLike = $like;
    }

    // ── Query ─────────────────────────────────────────────────────────────────
    // Phone uses $phoneLike (formatted), all others use $like (raw with wildcards).
    // LIMIT 10 keeps the live search results manageable on screen.
    $stmt = $db->prepare("
        SELECT CUST_ID, CUST_Fname, CUST_Lname, CUST_Phone, CUST_Email
        FROM customer
        WHERE CUST_Phone LIKE ?
           OR CUST_Lname  LIKE ?
           OR CUST_Email  LIKE ?
           OR CUST_Fname  LIKE ?
        LIMIT 10
    ");
    $stmt->bind_param("ssss", $phoneLike, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }

    $stmt->close();
    $db->close();

    return $customers;
}
