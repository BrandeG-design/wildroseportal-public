<?php
// =============================================================================
// FILE: ticketsummary.php
// PURPOSE: Final step of the check-in workflow. Displays a full summary of the
//          customer and device information collected in previous steps, presents
//          the Terms & Conditions for the customer to read, collects a signature,
//          handles Dymo label printing, and submits the completed ticket to the
//          database.
//
//          On successful submission:
//            - Customer record is created (new) or updated (existing)
//            - Device record is always inserted as a new row
//            - Device photos are saved as JPEG files under assets/images/device_photos/
//              and only the relative web path is stored in device_photos.DP_Photo
//            - Ticket record links the customer and device
//            - The browser print dialog opens (via ticketSummary.js) for a receipt
//            - Session data is cleared and the user is redirected to index.php
//
// ACCESS:     Requires staff to be logged in (enforced by checkLogin())
//             Also requires $_SESSION["customer"] and $_SESSION["ticket"] to exist
// PREV PAGE:  ticket.php
// LINKED CSS: assets/css/ticketsummary.css
// LINKED JS:  assets/js/labelAmount.js   — +/- counter for label quantity
//             assets/js/printLabel.js    — sends label job to Dymo printer
//             assets/js/ticketSummary.js — signature canvas, photo rendering,
//                                          submitFinal(), saveSignatureAndGoBack()
// =============================================================================

require_once "../includes/session.php";
require_once "../SQL_Connection.php";
require_once __DIR__ . "/sendTicketEmail.php";
checkLogin();

//Set time zone for print so it doesn't default to UTC
$now = new DateTime('now', new DateTimeZone('America/Edmonton'));

// ── Guard: both session keys must exist ───────────────────────────────────────
// Redirect to home if the workflow was not followed from the beginning
$customer = $_SESSION["customer"] ?? null;
$ticket   = $_SESSION["ticket"]   ?? null;

if (!$customer || !$ticket) {
    header("Location: http://customer.altismsp.com/index.php");
    exit();
}

$submitError = "";

// ── Final submission ──────────────────────────────────────────────────────────
// Triggered when submitFinal() in ticketSummary.js populates the hidden inputs
// and submits the form with action=final_submit
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "final_submit"
) {
    $db = getDB();

    try {
        // Wrap everything in a transaction so a failure at any step rolls back
        // all changes — avoids orphaned customer/device records without a ticket
        $db->begin_transaction();

        // ── Customer ─────────────────────────────────────────────────────────
        // Existing customers are updated (phone, address, email may have changed).
        // New customers are inserted and their auto-increment ID is captured.
        if ($customer["is_existing"] && !empty($customer["CUST_ID"])) {
            $stmt = $db->prepare("
                UPDATE customer
                SET CUST_Fname    = ?,
                    CUST_Lname    = ?,
                    CUST_Phone    = ?,
                    CUST_Address  = ?,
                    CUST_Email    = ?,
                    CUST_Noemail  = ?,
                    CUST_isbusiness = ?
                WHERE CUST_ID = ?
            ");
            $stmt->bind_param(
                "sssssiii",
                $customer["CUST_Fname"],
                $customer["CUST_Lname"],
                $customer["CUST_Phone"],
                $customer["CUST_Address"],
                $customer["CUST_Email"],
                $customer["CUST_Noemail"],
                $customer["CUST_isbusiness"],
                $customer["CUST_ID"]
            );
            $stmt->execute();
            $stmt->close();
            $cust_id = (int) $customer["CUST_ID"];
        } else {
            $stmt = $db->prepare("
                INSERT INTO customer (CUST_Fname, CUST_Lname, CUST_Phone, CUST_Address, CUST_Email, CUST_Noemail, CUST_isbusiness)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sssssii",
                $customer["CUST_Fname"],
                $customer["CUST_Lname"],
                $customer["CUST_Phone"],
                $customer["CUST_Address"],
                $customer["CUST_Email"], 
                $customer["CUST_Noemail"],
                $customer["CUST_isbusiness"]
            );
            $stmt->execute();
            $stmt->close();
            // insert_id gives the auto-increment ID of the just-inserted row
            $cust_id = $db->insert_id;
        }

        // ── Device ───────────────────────────────────────────────────────────
        // A new device row is always inserted — each check-in creates its own
        // device record even if the same physical device has been seen before
        $sn      = $ticket["DEV_S/N"]      ?? "";
        $model   = $ticket["DEV_Model"]    ?? "";
        $brand   = $ticket["DEV_Brand"]    ?? "";
        $devType = $ticket["DEV_Type"]     ?? "";
        $pass    = $ticket["DEV_Pass/PIN"] ?? "";

        // ── Decode peripheral JSON into a clean comma-separated string for DB ─
        // Session stores {"items":["Mouse","Other"],"other":"custom text"}.
        // The DB column receives a plain string e.g. "Mouse, custom text".
        $periphRaw     = $ticket["DEV_Peripheral"] ?? "";
        $periphDecoded = json_decode($periphRaw, true);

        if (is_array($periphDecoded)) {
            $periphItems = array_filter($periphDecoded["items"] ?? [], fn($i) => $i !== "Other");
            $periphOther = trim($periphDecoded["other"] ?? "");
            if ($periphOther !== "") {
                $periphItems[] = $periphOther;
            }
            $periph = implode(", ", $periphItems);
        } else {
            // Legacy plain string fallback
            $periph = $periphRaw;
        }

        $stmt = $db->prepare("
            INSERT INTO device (`DEV_S/N`, DEV_Model, DEV_Brand, DEV_Type, DEV_Peripheral, `DEV_Pass/PIN`)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssss", $sn, $model, $brand, $devType, $periph, $pass);
        $stmt->execute();
        $stmt->close();
        $dev_id = $db->insert_id;

        // ── Device photos ────────────────────────────────────────────────────
        // photosData is a JSON array of {dataUrl, label} objects sent by ticketSummary.js.
        // Each photo is decoded from its base64 data URI and saved as a JPEG file under
        // assets/images/device_photos/. Only the relative web path is stored in the DB.
        //
        // Directory layout:
        //   <webroot>/assets/images/device_photos/dev_{DEV_ID}/photo_{order}.jpg
        // Web path stored in DB:
        //   assets/images/device_photos/dev_{DEV_ID}/photo_{order}.jpg
        $photosJson = $_POST["photosData"] ?? "[]";
        $photoItems = json_decode($photosJson, true);

        if (is_array($photoItems) && count($photoItems) > 0) {

            // Resolve the absolute filesystem path to the photos directory.
            // __DIR__ is the directory of this file (e.g. /var/www/html/public).
            // The webroot is one level up, so assets/ sits at ../assets/.
            $photoDir = realpath(__DIR__ . "/../assets/images/device_photos") . "/dev_{$dev_id}";

            if (!is_dir($photoDir)) {
                mkdir($photoDir, 0755, true);
            }

            $stmtPhoto = $db->prepare("
                INSERT INTO device_photos (DEV_ID, DP_Photo, DP_Order, DP_Label)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($photoItems as $order => $item) {
                // Support both plain string data URLs (legacy) and {dataUrl, label} objects
                $dataUrl = is_string($item) ? $item : $item["dataUrl"] ?? "";
                $label   = is_string($item) ? "" : substr(trim($item["label"] ?? ""), 0, 40);

                // Strip the data URI prefix to get raw base64, then decode to binary
                $b64       = preg_replace("#^data:image/\w+;base64,#i", "", $dataUrl);
                $imgBinary = base64_decode($b64);

                // Write the JPEG file to disk
                $filename  = "photo_{$order}.jpg";
                $absPath   = $photoDir . "/" . $filename;
                file_put_contents($absPath, $imgBinary);

                // Build the web-relative path that will be stored in the DB.
                // ticketreport.php prepends the base URL when rendering.
                $webPath = "assets/images/device_photos/dev_{$dev_id}/{$filename}";

                $stmtPhoto->bind_param("issi", $dev_id, $webPath, $order, $label);
                $stmtPhoto->execute();
            }
            $stmtPhoto->close();
        }

        // ── Signature ────────────────────────────────────────────────────────
        // The signature canvas is exported as a PNG data URI by ticketSummary.js
        // and posted in the "signature" hidden input. Decoded to a BLOB for storage.
        // $signBlob stays null if no signature was provided — the DB column allows NULL.
        $staffNotes = $ticket["T_Staff_Notes"] ?? "";
        $signBlob   = null;

        if (!empty($_POST["signature"])) {
            $b64      = preg_replace("#^data:image/\w+;base64,#i", "", $_POST["signature"]);
            $signBlob = base64_decode($b64);
        }

        // ── Ticket ───────────────────────────────────────────────────────────
        // The ticket row links the customer and device, and stores notes + signature.
        // send_long_data() is used for the signature BLOB (param index 3).
        $stmt = $db->prepare("
            INSERT INTO ticket (CUST_ID, DEV_ID, T_Staff_Notes, T_Cust_Sign)
            VALUES (?, ?, ?, ?)
        ");
        $null = null;
        $stmt->bind_param("iisb", $cust_id, $dev_id, $staffNotes, $null);
        if ($signBlob !== null) {
            $stmt->send_long_data(3, $signBlob);
        }
        $stmt->execute();
        $stmt->close();

        $db->commit();
        $db->close();

        // ── Send ticket confirmation email ────────────────────────────────────
        // Build the photo data-URL array from the posted JSON for the email attachment
        $emailPhotoUrls = [];
        if (is_array($photoItems) && count($photoItems) > 0) {
            foreach ($photoItems as $item) {
                $emailPhotoUrls[] = is_string($item) ? $item : ($item["dataUrl"] ?? "");
            }
        }
        try {
            sendTicketEmail($customer, $ticket, $signBlob, $emailPhotoUrls);
        } catch (Exception $e) {
            // Email failure is non-fatal — ticket is already saved
            error_log("sendTicketEmail failed: " . $e->getMessage());
        }

        // Clear session data so the next check-in starts clean
        unset($_SESSION["ticket"], $_SESSION["customer"]);
        header("Location: http://customer.altismsp.com/index.php?status=success");
        exit();

    } catch (Exception $e) {
        // Roll back all DB changes if anything failed
        $db->rollback();
        $db->close();
        $submitError = "Submission failed. Please try again. (" . htmlspecialchars($e->getMessage()) . ")";
    }
}

// ── Render the summary page ────────────────────────────────────────────────────
// All values below are read from the session for display purposes only.
// They are re-read from $_POST on submission above to ensure consistency.

$custFname   = htmlspecialchars($customer["CUST_Fname"]   ?? "");
$custLname   = htmlspecialchars($customer["CUST_Lname"]   ?? "");
$custPhone   = htmlspecialchars($customer["CUST_Phone"]   ?? "");
$custAddress = htmlspecialchars($customer["CUST_Address"] ?? "");
$custEmail   = htmlspecialchars($customer["CUST_Email"]   ?? "");

$devType     = htmlspecialchars($ticket["DEV_Type"]     ?? "");
$devBrand    = htmlspecialchars($ticket["DEV_Brand"]    ?? "");
$devModel    = htmlspecialchars($ticket["DEV_Model"]    ?? "");
$devSN       = htmlspecialchars($ticket["DEV_S/N"]      ?? "");
$devPass     = htmlspecialchars($ticket["DEV_Pass/PIN"] ?? "");
$staffNotes  = htmlspecialchars($ticket["T_Staff_Notes"] ?? "");

// ── Re-decode peripheral display string ──────────────────────────────────────
$periphRaw     = $ticket["DEV_Peripheral"] ?? "";
$periphDecoded = json_decode($periphRaw, true);
if (is_array($periphDecoded)) {
    $dispItems = array_filter($periphDecoded["items"] ?? [], fn($i) => $i !== "Other");
    $dispOther = trim($periphDecoded["other"] ?? "");
    if ($dispOther !== "") $dispItems[] = $dispOther;
    $periph = htmlspecialchars(implode(", ", $dispItems));
} else {
    $periph = htmlspecialchars($periphRaw);
}

$isBusiness = !empty($customer["CUST_isbusiness"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Summary</title>
    <link rel="icon" type="image/png" href="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png">
    <link rel="stylesheet" href="http://customer.altismsp.com/assets/css/ticketsummary.css">
</head>
<body>

    <!-- ── Nav Bar ──────────────────────────────────────────────────────────── -->
    <nav class="nav-bar">
        <img src="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png"
             width="100" height="39" alt="Altis MSP logo">
    </nav>

    <div class="page-wrapper">

        <h1 class="page-title">Ticket Summary</h1>
        <p class="page-subtitle">Please review all details before submitting.</p>

        <?php if ($submitError): ?>
            <div class="error-banner"><?= $submitError ?></div>
        <?php endif; ?>

        <!-- ── Print Header (print-only) ────────────────────────────────────── -->
        <div class="print-header">
            <img src="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png" width="100" height="39" alt="Altis MSP">
            <span class="print-date">Printed: <?= $now->format("Y-m-d H:i") ?></span>
        </div>

        <!-- ── Customer Info ─────────────────────────────────────────────────── -->
        <div class="section-card">
            <h2 class="section-heading">Customer Information</h2>
            <div class="info-grid">
            <?php if ($isBusiness): ?>
                <div class="info-field">
                    <span class="info-label">Business</span>
                    <span class="info-value"><?= htmlspecialchars($custFname) ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Droppoff Contact</span>
                    <span class="info-value"><?= htmlspecialchars($custLname) ?></span>
                </div>
            <?php else: ?>
                <div class="info-field">
                    <span class="info-label">Name</span>
                    <span class="info-value"><?= "$custFname $custLname" ?></span>
                </div>
            <?php endif; ?> 
                <div class="info-field">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?= $custPhone ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Address</span>
                    <span class="info-value"><?= $custAddress ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Email</span>
                    <!-- ?: "—" displays a dash if email was not provided -->
                    <span class="info-value"><?= $custEmail ?: "—" ?></span>
                </div>
            </div>
        </div>

        <!-- ── Device Info ───────────────────────────────────────────────────── -->
        <div class="section-card">
            <h2 class="section-heading">Device Information</h2>
            <div class="info-grid">
                <div class="info-field">
                    <span class="info-label">Type</span>
                    <span class="info-value"><?= $devType ?: "—" ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Brand</span>
                    <span class="info-value"><?= $devBrand ?: "—" ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Model</span>
                    <span class="info-value"><?= $devModel ?: "—" ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Serial Number</span>
                    <span class="info-value"><?= $devSN ?: "—" ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Password / PIN</span>
                    <span class="info-value"><?= $devPass ?: "—" ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Peripherals</span>
                    <span class="info-value"><?= $periph ?: "—" ?></span>
                </div>
                <div class="info-field full-width">
                    <span class="info-label">Staff Notes</span>
                    <span class="info-value notes"><?= $staffNotes ?: "—" ?></span>
                </div>
            </div>
        </div>

        <!-- ── Device Photos ─────────────────────────────────────────────────── -->
        <!-- Populated from sessionStorage by ticketSummary.js on page load.
             The heading text is also updated by JS to show the photo count. -->
        <div class="section-card">
            <h2 class="section-heading" id="photos-heading">Device Photos</h2>
            <div id="photo-display" class="photo-grid">
                <em class="no-data">No photos captured.</em>
            </div>
        </div>

        <!-- ── Terms & Conditions ────────────────────────────────────────────── -->
        <!-- Read-only — customer reads this before signing below -->
        <div class="section-card">
            <h2 class="section-heading">Terms &amp; Conditions</h2>
            <textarea class="terms-box" id="terms" name="terms" rows="8" readonly>At Altis it is the primary goal to provide our customers with the best possible service. All services and repairs are guaranteed for 10 days from the time-of-service completion. If later, it is found that the repair was diagnosed incorrectly then Altis will perform the repair/service free of any charges excluding any additional hardware costs. Hardware warranty will be provided through the Original Equipment Manufacturer. At the time of system book in the customer acknowledges and understands that Altis is not responsible for data loss due to system/hardware failure. It is the responsibility of the customer to either perform personal backups or request that Altis performs the backup for an additional cost. I hereby agree to the above terms and authorize Altis to perform services/repairs as stated in the service order.</textarea>
        </div>

        <!-- ── Customer Signature ────────────────────────────────────────────── -->
        <!-- Canvas drawing handled by ticketSummary.js — supports mouse and touch.
             The signature is exported as a PNG data URI and posted in a hidden input. -->
        <div class="section-card">
            <h2 class="section-heading">Customer Signature</h2>
            <p class="sig-hint">Please sign below to agree to the terms above.</p>
            <div class="sig-canvas-wrapper">
                <canvas id="sigCanvas" width="600" height="200"></canvas>
            </div>
            <div class="sig-actions">
                <button class="util-btn" type="button" id="clearSig">Clear Signature</button>
            </div>
        </div>

        <!-- ── Print Label ───────────────────────────────────────────────────── -->
        <!-- Label quantity defaults to Label_Count from the session (set in ticket.php).
             Customer name and device string are passed as data attributes so
             ticketSummary.js can send them to the Dymo printer via printLabel.js. -->
        <div class="section-card">
            <h2 class="section-heading">Print Label</h2>
            <div class="label-amount-row">
                <button class="qty-btn" type="button" id="decreaseAmount">−</button>
                <input class="qty-input" type="number" id="labelAmount" name="labelCount"
                       min="0" max="10" readonly
                       value="<?= intval($ticket["Label_Count"] ?? 1) ?>">
                <button class="qty-btn" type="button" id="increaseAmount">+</button>
                <?php
                // Build the device description for the label: "Brand Model" or fall back to type
                $deviceStr = trim(($ticket["DEV_Brand"] ?? "") . " " . ($ticket["DEV_Model"] ?? ""));
                if ($deviceStr === "") {
                    $deviceStr = $ticket["DEV_Type"] ?? "";
                }
                ?>
                <button class="print-btn" type="button" id="printLabelBtn"
                        data-fname="<?= $custFname ?>"
                        data-lname="<?= $custLname ?>"
                        data-device="<?= htmlspecialchars($deviceStr) ?>">
                    Print Label
                </button>
            </div>
        </div>

        <!-- ── Final Submit Form ─────────────────────────────────────────────── -->
        <!-- Hidden inputs are populated by submitFinal() in ticketSummary.js
             before the form is submitted. The form itself has no visible fields. -->
        <form id="finalForm" method="POST" action="ticketsummary.php">
            <input type="hidden" name="action"     value="final_submit">
            <!-- Populated with the signature canvas PNG data URI -->
            <input type="hidden" name="signature"  id="signatureInput">
            <!-- Populated with the JSON array of {dataUrl, label} photo objects -->
            <input type="hidden" name="photosData" id="photosDataInput">
            <div class="form-actions">
                <!-- submitFinal() validates signature, populates inputs, triggers print, then submits -->
                <button class="primary-btn" type="button" onclick="submitFinal()">Submit Ticket</button>
                <!-- saveSignatureAndGoBack() saves the signature to sessionStorage before navigating -->
                <button class="back-btn"    type="button" onclick="saveSignatureAndGoBack()">Back</button>
            </div>
        </form>

    </div><!-- /page-wrapper -->

    <!-- labelAmount.js  — +/- counter for the label quantity input -->
    <!-- printLabel.js   — sends label print job to the Dymo printer -->
    <!-- ticketSummary.js — signature canvas, photo rendering, submit and back logic -->
    <script src="/assets/js/labelAmount.js?v=2"></script>
    <script src="/assets/js/printLabel.js?v=2"></script>
    <script src="/assets/js/ticketSummary.js?v=2"></script>

    <!-- ── Footer ───────────────────────────────────────────────────────────── -->
    <footer>
        <p class="copyright">© 2026 FireNode & AltisMSP</p>
    </footer>

</body>
</html>
