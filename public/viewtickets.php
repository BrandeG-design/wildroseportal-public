<?php
// =============================================================================
// FILE: viewtickets.php
// PURPOSE: Displays the ticket management page for staff. Shows the 10 most
//          recently submitted tickets by default, and provides a live search
//          bar that queries tickets by customer name, phone, email, device type,
//          or ticket number. Each row links to ticketreport.php for full details.
//
//          This file serves two roles depending on the request:
//            1. AJAX endpoint: returns JSON results when ?search= is present
//            2. Full page: renders the HTML ticket list when loaded normally
//
// ACCESS:     Requires staff to be logged in (enforced by checkLogin())
// LINKED CSS: assets/css/viewtickets.css
// LINKED JS:  assets/js/viewTicketLive.js — live search AJAX and result rendering
//             assets/js/clickableRows.js  — makes each table row navigate on click
// =============================================================================

require_once "../includes/session.php";
require_once "../SQL_Connection.php";
checkLogin();
$db = getDB();

// ── AJAX search endpoint ──────────────────────────────────────────────────────
// Called by viewTicketLive.js with ?search={term} as the user types.
// Returns up to 25 matching tickets as JSON and exits, no HTML is rendered.
if (isset($_GET["search"])) {
    header("Content-Type: application/json");

    // Wrap the search term in % wildcards for a LIKE match on all searchable fields
    $like = "%" . $_GET["search"] . "%";

    $stmt = $db->prepare("
        SELECT
            t.`T_Ticket#`,
            DATE(t.T_Date) AS T_Date,
            c.CUST_Fname,
            c.CUST_Lname,
            c.CUST_Phone,
            c.CUST_Email,
            d.DEV_Type
        FROM customer c
        JOIN ticket t ON c.CUST_ID = t.CUST_ID
        JOIN device d ON d.DEV_ID  = t.DEV_ID
        WHERE c.CUST_Fname LIKE ?
           OR c.CUST_Lname  LIKE ?
           OR c.CUST_Phone  LIKE ?
           OR c.CUST_Email  LIKE ?
           OR d.DEV_Type    LIKE ?
           OR t.`T_Ticket#` LIKE ?
        ORDER BY t.T_Date DESC
        LIMIT 25
    ");

    // All six params receive the same $like value — one per OR condition
    $stmt->bind_param("ssssss", $like, $like, $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows   = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    $db->close();

    echo json_encode($rows);
    exit();
}

// ── Default page load: fetch most recent 10 tickets ──────────────────────────
// Shown in the "Most Recent" table when no search has been performed.
// Fewer columns selected here than the search endpoint since this view
// doesn't need phone/email (no search results to disambiguate).
$stmt = $db->prepare("
    SELECT
        t.`T_Ticket#`,
        DATE(t.T_Date) AS T_Date,
        c.CUST_Fname,
        c.CUST_Lname,
        d.DEV_Type
    FROM customer c
    JOIN ticket t ON c.CUST_ID = t.CUST_ID
    JOIN device d ON d.DEV_ID  = t.DEV_ID
    ORDER BY t.T_Date DESC
    LIMIT 10
");

$stmt->execute();
$result        = $stmt->get_result();
$recentTickets = [];
while ($row = $result->fetch_assoc()) {
    $recentTickets[] = $row;
}

$stmt->close();
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- See head.txt for more information -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets</title>
    <link rel="icon" type="image/png" href="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png">
    <link rel="stylesheet" href="http://customer.altismsp.com/assets/css/viewtickets.css">
</head>
<body>

    <!-- ── Nav Bar ──────────────────────────────────────────────────────────── -->
    <nav class="nav-bar">
        <img src="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png" width="100" height="39" alt="Altis MSP logo">
    </nav>

    <div class="page-wrapper">

        <h1 class="page-title">Tickets</h1>

        <!-- ── Live Search ───────────────────────────────────────────────────── -->
        <!-- viewTicketLive.js listens to input events and fires AJAX requests
             to this file's ?search= endpoint after 2+ characters are typed -->
        <div class="search-wrapper">
            <input class="search-input" type="text" id="ticket-search"
                   placeholder="Search tickets…" maxlength="50" autocomplete="off">
        </div>
        <p class="search-hint">Search by name, phone, email, device type, or ticket #</p>

        <!-- Search results injected here by viewTicketLive.js.
             The recent tickets table is hidden while results are showing. -->
        <div id="search-results"></div>

        <!-- ── Most Recent Tickets ───────────────────────────────────────────── -->
        <!-- Shown on page load and when the search bar is cleared.
             Each row has data-href set so clickableRows.js can navigate on click. -->
        <div id="recent-tickets" class="section-card">
            <h2 class="section-heading">Most Recent</h2>
            <table class="ticket-table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Device Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTickets as $t): ?>
                    <!-- data-href is read by clickableRows.js to navigate the full row -->
                    <tr class="clickable-row" data-href="ticketreport.php?id=<?= urlencode($t["T_Ticket#"]) ?>">
                        <td><span class="ticket-num"><?= htmlspecialchars($t["T_Ticket#"]) ?></span></td>
                        <td><?= htmlspecialchars($t["T_Date"]) ?></td>
                        <td><?= htmlspecialchars($t["CUST_Fname"] . " " . $t["CUST_Lname"]) ?></td>
                        <td><?= htmlspecialchars($t["DEV_Type"]) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Back Button ───────────────────────────────────────────────────── -->
        <div class="back-btn-wrapper">
            <a class="back-btn" href="http://customer.altismsp.com/public/admin.php">Back</a>
        </div>

    </div><!-- /page-wrapper -->

    <!-- viewTicketLive.js handles live search AJAX and injects result rows -->
    <!-- clickableRows.js attaches click listeners to .clickable-row elements -->
    <script src="/assets/js/viewTicketLive.js"></script>
    <script src="/assets/js/clickableRows.js"></script>

    <!-- ── Footer ───────────────────────────────────────────────────────────── -->
    <footer>
        <p class="copyright">© 2026 FireNode & AltisMSP</p>
    </footer>

</body>
</html>
