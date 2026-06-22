<?php
// =============================================================================
// FILE: customer.php
// PURPOSE: Step 1 of the check-in workflow. Collects or confirms customer
//          contact information (name, phone, address, email) and stores it in
//          the session before proceeding to ticket.php.
//
//          For existing customers, fields are pre-filled from the session data
//          set by index.php when a customer was selected from the live search.
//          For new customers, all fields start empty.
//
// ACCESS:     Requires staff to be logged in (enforced by checkLogin())
// NEXT PAGE:  ticket.php
// LINKED CSS: assets/css/customer.css
// LINKED JS:  assets/js/phoneFormat.js   — formats phone number as ###-###-####
//             assets/js/nameValidation.js — strips digits from name fields live
//             assets/js/noEmail.js        — handles the "No email" checkbox toggle
// =============================================================================

require_once "../includes/session.php";
require_once "../SQL_Connection.php";
checkLogin();

// ── Cancel action ─────────────────────────────────────────────────────────────
// Wipes all in-progress check-in session data and returns to the home screen.
// Triggered by the Cancel button via ?action=cancel
if (isset($_GET["action"]) && $_GET["action"] === "cancel") {
    unset($_SESSION["customer"], $_SESSION["ticket"]);
    header("Location: http://customer.altismsp.com/index.php");
    exit();
}

// ── Load existing session data ────────────────────────────────────────────────
// If the customer was selected from the live search on index.php, their data
// will already be in the session and the form fields will be pre-populated.
// If this is a new customer, $customer will be null and fields start empty.
$customer = $_SESSION["customer"] ?? null;

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Preserve CUST_ID and is_existing from the previous session state so that
    // existing customers retain their database identity through the workflow.
    // Without this, re-submitting the form would lose the link to their DB record.
    $prev = $_SESSION["customer"] ?? [];

    $_SESSION["customer"] = [
        "CUST_ID"      => $prev["CUST_ID"]      ?? null,
        "is_existing"  => (bool) ($prev["is_existing"] ?? false),
        "CUST_Fname"   => trim($_POST["CUST_Fname"]   ?? ""),
        "CUST_Lname"   => trim($_POST["CUST_Lname"]   ?? ""),
        "CUST_Phone"   => trim($_POST["CUST_Phone"]   ?? ""),
        "CUST_Address" => trim($_POST["CUST_Address"] ?? ""),
        "CUST_Email"   => trim($_POST["CUST_Email"]   ?? ""),
        // no_email is sent as "1" or "0" by the hidden input managed by noEmail.js
        "CUST_Noemail"     => ($_POST["CUST_Noemail"] ?? "0") === "1",
        "CUST_isbusiness"  => ($_POST["CUST_isbusiness"] ?? "0") === "1",
    ];

    header("Location: ticket.php");
    exit();
}

// ── Determine no-email state ──────────────────────────────────────────────────
// Checks both session keys for compatibility — no_email is set by this form,
// CUST_NoEmail may be present on records loaded from the database.
$noEmail = !empty($customer["no_email"]) || !empty($customer["CUST_NoEmail"]);
$isBusiness = !empty($customer["is_business"]) || !empty($customer["CUST_isbusiness"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- See head.txt for more information -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Contact Form</title>
    <link rel="icon" type="image/png" href="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png">
    <link rel="stylesheet" href="http://customer.altismsp.com/assets/css/customer.css">
</head>
<body>

    <!-- ── Nav Bar ──────────────────────────────────────────────────────────── -->
    <nav class="nav-bar">
        <img src="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png"
             width="100" height="39" alt="Altis MSP logo">
    </nav>

    <div class="page-wrapper">

        <h1 class="page-title">Customer Contact Form</h1>

        <!-- ── Customer Information Form ────────────────────────────────────── -->
        <form action="customer.php" method="POST">

            <div class="section-card">
                <h2 class="section-heading">Customer Information</h2>
                <div class="form-grid">

                        <!--Business - Not required 
                        isBusiness toggles the first/last name field to change into 
                        business and drop off person that way it's much easier to read when signing out -->
                    <div class="form-field full-width">
                        <label class="field-label" for="business">Business</label>
                        <input type="hidden" id="is_business_flag" name="CUST_isbusiness" value="<?= $isBusiness ? "1" : "0" ?>">
                        <div class="checkbox-row">
                            <input type="checkbox" value="1" id="is_business" <?= $isBusiness ? "checked" : "" ?>>
                            <label for="is_business">Is this business related?</label>
                        </div>
                    </div>

                    <!-- First Name — letters, hyphens, apostrophes, spaces only -->
                    <div class="form-field">
                        <label class="field-label" id="fnameLabel" for="fname">First Name <span class="required-star" aria-hidden="true">*</span></label>
                        <input class="field-input" type="text" id="fname" name="CUST_Fname"
                               maxlength="45" required
                               pattern="[A-Za-zÀ-ÖØ-öø-ÿ'\- ]+"
                               title="First name may only contain letters, hyphens, apostrophes, and spaces."
                               placeholder="e.g. Jane"
                               value="<?= htmlspecialchars($customer["CUST_Fname"] ?? "") ?>">
                    </div>

                    <!-- Last Name — same validation pattern as first name -->
                    <div class="form-field">
                        <label class="field-label" id="lnameLabel" for="lname">Last Name <span class="required-star" aria-hidden="true">*</span></label>
                        <input class="field-input" type="text" id="lname" name="CUST_Lname"
                               maxlength="45" required
                               pattern="[A-Za-zÀ-ÖØ-öø-ÿ'\- ]+"
                               title="Last name may only contain letters, hyphens, apostrophes, and spaces."
                               placeholder="e.g. Smith"
                               value="<?= htmlspecialchars($customer["CUST_Lname"] ?? "") ?>">
                    </div>

                    <!-- Phone — enforces ###-###-#### format, formatted live by phoneFormat.js -->
                    <div class="form-field">
                        <label class="field-label" for="phone">Phone Number <span class="required-star" aria-hidden="true">*</span></label>
                        <input class="field-input" type="tel" id="phone" name="CUST_Phone"
                               placeholder="123-456-7890"
                               pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}"
                               maxlength="15" required
                               value="<?= htmlspecialchars($customer["CUST_Phone"] ?? "") ?>">
                    </div>

                    <!-- Address -->
                    <div class="form-field">
                        <label class="field-label" for="address">Address</label>
                        <input class="field-input" type="text" id="address" name="CUST_Address"
                               maxlength="45"
                               placeholder="e.g. 123 Main St"
                               value="<?= htmlspecialchars($customer["CUST_Address"] ?? "") ?>">
                    </div>

                    <!-- Email — required unless "No email" checkbox is checked.
                         noEmail.js toggles the required/disabled state and updates
                         the hidden no_email flag that gets submitted with the form. -->
                    <div class="form-field full-width">
                        <label class="field-label" for="email">Email <span class="required-star" aria-hidden="true">*</span></label>
                        <input class="field-input" type="email" id="email" name="CUST_Email"
                               maxlength="255"
                               <?= $noEmail ? "" : "required" ?>
                               <?= $noEmail ? "disabled" : "" ?>
                               placeholder="e.g. jane@example.com"
                               value="<?= htmlspecialchars($noEmail ? "" : $customer["CUST_Email"] ?? "") ?>"
                               style="max-width: 360px;">

                        <!-- Hidden flag submitted with the form — toggled by noEmail.js -->
                        <input type="hidden" id="no-email-flag" name="CUST_Noemail" value="<?= $noEmail ? "1" : "0" ?>">

                        <!-- Checkbox managed by noEmail.js — disables the email field
                             and clears its value when checked -->
                        <div class="checkbox-row">
                            <input type="checkbox" value="1" id="no-email" <?= $noEmail ? "checked" : "" ?>>
                            <label for="no-email">No email</label>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Reminder for staff to take the tablet back after the customer fills in their info -->
            <p class="return-notice">Please return the device to staff once complete.</p>

            <!-- ── Form Actions ───────────────────────────────────────────────── -->
            <div class="form-actions">
                <button class="primary-btn" type="submit">Next</button>
                <!-- Cancel wipes the session and returns to home -->
                <a class="danger-btn" href="customer.php?action=cancel">Cancel</a>
            </div>

        </form>

    </div><!-- /page-wrapper -->

    <!-- phoneFormat.js  — auto-formats phone input to ###-###-#### as user types -->
    <!-- nameValidation.js — strips any digits typed into the name fields in real time -->
    <!-- noEmail.js        — toggles email field required/disabled based on checkbox -->
    <script src="/assets/js/phoneFormat.js"></script>
    <script src="/assets/js/nameValidation.js"></script>
    <script src="/assets/js/noEmail.js"></script>
    <script src="/assets/js/isBusiness.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/js/isBusiness.js'); ?>"></script>

    <!-- ── Footer ───────────────────────────────────────────────────────────── -->
    <footer>
        <p class="copyright">© 2026 FireNode & AltisMSP</p>
    </footer>

</body>
</html>
