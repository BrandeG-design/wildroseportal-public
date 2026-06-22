<?php
// =============================================================================
// FILE: ticketreport.php
// PURPOSE: Displays a full read-only view of a single submitted ticket,
//          including customer info, device info, photos, customer signature,
//          and a Dymo label printing tool. Also provides Print and CSV export
//          options for staff records.
//
// ACCESS:     Requires staff to be logged in (enforced by checkLogin())
// ROUTE:      Accessed via viewtickets.php with ?id={ticket number}
// LINKED CSS: assets/css/ticketreport.css
// LINKED JS:  assets/js/ticketReport.js — wires up the Print Label button
//             assets/js/labelAmount.js  — handles the +/- quantity counter
// =============================================================================

require_once "../includes/session.php";
require_once "../SQL_Connection.php";
checkLogin();

// ── Validate the id parameter ─────────────────────────────────────────────────
// Reject the request if ?id is missing, empty, or not a positive integer.
// ctype_digit() is used rather than is_numeric() because is_numeric() accepts
// floats and negative numbers which are not valid ticket IDs.
if (empty($_GET["id"]) || !ctype_digit((string) $_GET["id"])) {
    header("Location: http://customer.altismsp.com/public/viewtickets.php");
    exit();
}

$db = getDB();
$id = (int) $_GET["id"];

// ── Fetch ticket, customer, and device data in one query ──────────────────────
// Joins all three tables so a single query returns everything needed for the page.
// DATE() strips the time component from T_Date for cleaner display.
$stmt = $db->prepare("
    SELECT
        t.`T_Ticket#`,
        DATE(t.T_Date)   AS T_Date,
        t.T_Staff_Notes,
        t.T_Cust_Sign,
        c.CUST_Fname,
        c.CUST_Lname,
        c.CUST_Phone,
        c.CUST_Address,
        c.CUST_Email,
        d.DEV_ID,
        d.DEV_Type,
        d.DEV_Brand,
        d.DEV_Model,
        d.`DEV_S/N`,
        d.DEV_Peripheral,
        d.`DEV_Pass/PIN`
    FROM customer c
    JOIN ticket t ON c.CUST_ID = t.CUST_ID
    JOIN device d ON d.DEV_ID  = t.DEV_ID
    WHERE t.`T_Ticket#` = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Redirect if ticket not found ──────────────────────────────────────────────
if (!$ticket) {
    $db->close();
    header("Location: http://customer.altismsp.com/public/viewtickets.php");
    exit();
}

// ── Fetch device photo paths and labels ──────────────────────────────────────
// DP_Photo now holds a relative web path (e.g. assets/images/device_photos/dev_42/photo_0.jpg)
// rather than a BLOB. The base URL is prepended when building <img> src attributes.
// Ordered by DP_Order to display them in the sequence they were captured.
$photos     = [];
$stmtPhotos = $db->prepare("
    SELECT DP_Photo, DP_Label FROM device_photos
    WHERE DEV_ID = ?
    ORDER BY DP_Order ASC
");
$stmtPhotos->bind_param("i", $ticket["DEV_ID"]);
$stmtPhotos->execute();
$photoResult = $stmtPhotos->get_result();
while ($row = $photoResult->fetch_assoc()) {
    $photos[] = [
        "src"   => "http://customer.altismsp.com/" . ltrim($row["DP_Photo"], "/"),
        "label" => $row["DP_Label"] ?? "",
    ];
}
$stmtPhotos->close();
$db->close();

// ── Decode customer signature ─────────────────────────────────────────────────
// T_Cust_Sign is stored as a PNG BLOB. Convert to a base64 data URI for display.
// $signSrc remains null if no signature was captured, which the HTML checks for.
$signSrc = null;
if (!empty($ticket["T_Cust_Sign"])) {
    $signSrc = "data:image/png;base64," . base64_encode($ticket["T_Cust_Sign"]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- See head.txt for more information -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= htmlspecialchars($ticket["T_Ticket#"]) ?></title>
    <link rel="icon" type="image/png" href="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png">
    <link rel="stylesheet" href="http://customer.altismsp.com/assets/css/ticketreport.css">
</head>
<body>

    <!-- ── Nav Bar ──────────────────────────────────────────────────────────── -->
    <nav class="nav-bar">
        <img src="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png" width="100" height="39" alt="Altis MSP logo">
    </nav>

    <div class="page-wrapper">

        <!-- Ticket number and date -->
        <h1 class="page-title">Ticket #<?= htmlspecialchars($ticket["T_Ticket#"]) ?></h1>
        <p class="page-date">Date: <?= htmlspecialchars($ticket["T_Date"]) ?></p>

        <!-- ── Action Buttons — hidden on print ──────────────────────────────── -->
        <div class="action-btn-row no-print">
            <button class="secondary-btn" type="button" onclick="window.print()">Print / Save as PDF</button>
            <!-- exportTicketCSV() defined below — builds CSV from rendered page data -->
            <button class="secondary-btn" type="button" onclick="exportTicketCSV()">Export to CSV / Excel</button>
        </div>

        <!-- ── Customer Info ─────────────────────────────────────────────────── -->
        <div class="section-card">
            <h2 class="section-heading">Customer Info</h2>
            <div class="info-grid">
                <div class="info-field">
                    <span class="info-label">Name</span>
                    <span class="info-value"><?= htmlspecialchars($ticket["CUST_Fname"] . " " . $ticket["CUST_Lname"]) ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?= htmlspecialchars($ticket["CUST_Phone"]) ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Address</span>
                    <span class="info-value"><?= htmlspecialchars($ticket["CUST_Address"]) ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Email</span>
                    <!-- ?? "—" displays a dash if email was not provided -->
                    <span class="info-value"><?= htmlspecialchars($ticket["CUST_Email"] ?? "—") ?></span>
                </div>
            </div>
        </div>

        <!-- ── Device Info ───────────────────────────────────────────────────── -->
        <div class="section-card">
            <h2 class="section-heading">Device Info</h2>
            <div class="info-grid">
                <div class="info-field">
                    <span class="info-label">Type</span>
                    <span class="info-value"><?= htmlspecialchars($ticket["DEV_Type"]) ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Brand</span>
                    <span class="info-value"><?= htmlspecialchars($ticket["DEV_Brand"]) ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Model</span>
                    <span class="info-value"><?= htmlspecialchars($ticket["DEV_Model"]) ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Serial #</span>
                    <span class="info-value"><?= htmlspecialchars($ticket["DEV_S/N"] ?? "—") ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Peripherals</span>
                    <span class="info-value"><?= htmlspecialchars($ticket["DEV_Peripheral"] ?? "—") ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Password / PIN</span>
                    <span class="info-value"><?= htmlspecialchars($ticket["DEV_Pass/PIN"] ?? "—") ?></span>
                </div>
                <div class="info-field full-width">
                    <span class="info-label">Staff Notes / Problem</span>
                    <span class="info-value notes"><?= htmlspecialchars($ticket["T_Staff_Notes"] ?? "—") ?></span>
                </div>
            </div>
        </div>

        <!-- ── Device Photos ─────────────────────────────────────────────────── -->
        <!-- src values are absolute URLs built from the stored relative paths -->
        <div class="section-card">
            <h2 class="section-heading">Device Photos</h2>
            <p class="photo-count"><?= count($photos) ?> photo(s) on record</p>
            <?php if (empty($photos)): ?>
                <p class="no-data">No photos on record.</p>
            <?php else: ?>
                <div class="photo-grid">
                    <?php foreach ($photos as $i => $photo): ?>
                        <figure class="photo-figure">
                            <img src="<?= htmlspecialchars($photo["src"]) ?>"
                                 alt="Device photo <?= $i + 1 ?><?= $photo["label"] !== "" ? " — " . htmlspecialchars($photo["label"]) : "" ?>">
                            <?php if ($photo["label"] !== ""): ?>
                                <figcaption><?= htmlspecialchars($photo["label"]) ?></figcaption>
                            <?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Customer Signature ────────────────────────────────────────────── -->
        <!-- Signature is a base64 PNG data URI built from the BLOB in T_Cust_Sign -->
        <div class="section-card">
            <h2 class="section-heading">Customer Signature</h2>
            <?php if ($signSrc): ?>
                <img class="signature-img" src="<?= $signSrc ?>" alt="Customer signature">
            <?php else: ?>
                <p class="no-data">No signature on record.</p>
            <?php endif; ?>
        </div>

        <!-- ── Print Label ───────────────────────────────────────────────────── -->
        <!-- labelAmount.js manages the +/- counter.
             ticketReport.js wires up the Print Label button to the Dymo printer. -->
        <div class="section-card">
            <h2 class="section-heading">Print Label</h2>
            <div class="print-label-row">
                <label class="info-label" for="labelAmount">Quantity</label>
                <button class="qty-btn" type="button" id="decreaseAmount">−</button>
                <input class="qty-input" type="number" id="labelAmount" min="0" max="10" value="0" readonly>
                <button class="qty-btn" type="button" id="increaseAmount">+</button>
                <button class="print-btn" type="button" id="printLabelBtn"
                        data-fname="<?= htmlspecialchars($ticket['CUST_Fname']) ?>"
                        data-lname="<?= htmlspecialchars($ticket['CUST_Lname']) ?>"
                        data-device="<?= htmlspecialchars(trim(($ticket['DEV_Brand'] ?? '') . ' ' . ($ticket['DEV_Model'] ?? '')) ?: ($ticket['DEV_Type'] ?? '')) ?>">Print Label</button>
            </div>
        </div>

        <!-- ── Back Button ───────────────────────────────────────────────────── -->
        <div class="back-btn-wrapper">
            <a class="back-btn" href="http://customer.altismsp.com/public/viewtickets.php">Back</a>
        </div>

    </div><!-- /page-wrapper -->

    <!-- ticketReport.js — wires up printLabelBtn to the Dymo label printer -->
    <!-- labelAmount.js  — handles the +/- quantity counter for label printing -->
    <script src="/assets/js/printLabel.js"></script>
    <script src="/assets/js/ticketReport.js"></script>
    <script src="/assets/js/labelAmount.js"></script>

    <!-- ── Footer ───────────────────────────────────────────────────────────── -->
    <footer>
        <p class="copyright">© 2026 FireNode & AltisMSP</p>
    </footer>

    <script>
        // ── Export single ticket to CSV ────────────────────────────────────────
        // Reads all .info-field label/value pairs already rendered on the page
        // and builds a CSV file client-side. No server round-trip is needed
        // since the PHP already rendered all ticket data into the HTML.
        function exportTicketCSV() {
            const rows = [["Field", "Value"]]; // CSV header row

            // Walk every info-field on the page and collect its label and value
            document.querySelectorAll(".info-field").forEach(function(field) {
                const label = field.querySelector(".info-label");
                const value = field.querySelector(".info-value");
                if (label && value) {
                    rows.push([
                        label.textContent.trim(),
                        value.textContent.trim()
                    ]);
                }
            });

            // Build CSV — wrap each cell in quotes and escape any internal quotes
            const csvContent = rows.map(function(row) {
                return row.map(function(cell) {
                    return '"' + String(cell).replace(/"/g, '""') + '"';
                }).join(",");
            }).join("\r\n");

            // UTF-8 BOM ensures Excel displays accented characters correctly
            const bom  = "\uFEFF";
            const blob = new Blob([bom + csvContent], { type: "text/csv;charset=utf-8;" });
            const url  = URL.createObjectURL(blob);

            // Build filename from the ticket number heading e.g. "Ticket_#42_2026-04-12.csv"
            const ticketNum = document.querySelector(".page-title")
                ? document.querySelector(".page-title").textContent.trim().replace(/\s+/g, "_")
                : "Ticket";
            const filename = ticketNum + "_" + new Date().toISOString().slice(0, 10) + ".csv";

            // Create a temporary link, click it to trigger the download, then clean up
            const a    = document.createElement("a");
            a.href     = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url); // release the object URL from memory
        }
    </script>

</body>
</html>
