<?php
// =============================================================================
// FILE: getCustID.php
// PURPOSE: Provides the getCustomerById() function, which fetches a single
//          customer record from the database by their primary key (CUST_ID).
//
//          Called by index.php after a staff member selects an existing customer
//          from the live search results. The returned array is stored in the
//          session so customer.php can pre-populate the contact form fields.
//
// DEPENDS ON: SQL_Connection.php (getDB() must be available before calling)
// USED BY:    index.php
// =============================================================================

// ── getCustomerById ───────────────────────────────────────────────────────────
// Fetches a single customer record by CUST_ID.
//
// @param int $customerId  The primary key of the customer to retrieve
// @return array|false     The customer row as an associative array on success,
//                         or false if no matching record was found
function getCustomerById(int $customerId): array|false
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT CUST_ID, CUST_Fname, CUST_Lname, CUST_Phone, CUST_Address, CUST_Email, CUST_NoEmail, CUST_isbusiness
        FROM customer
        WHERE CUST_ID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result   = $stmt->get_result();
    $customer = $result->fetch_assoc();

    $stmt->close();
    $db->close();

    // Return false if no customer was found (ternary shorthand)
    return $customer ?: false;
}
