<?php
// =============================================================================
// FILE: ticket.php
// PURPOSE: Step 2 of the check-in workflow. Collects device information
//          (type, brand, model, serial number, password, peripherals, staff
//          notes) and allows staff to take photos of the device using the
//          tablet camera. Stores all data in the session and proceeds to
//          ticketsummary.php.
//
// ACCESS:     Requires staff to be logged in (enforced by checkLogin())
//             Also requires $_SESSION["customer"] to exist — redirects to
//             index.php if the customer step was skipped.
// PREV PAGE:  customer.php
// NEXT PAGE:  ticketsummary.php
// LINKED CSS: assets/css/ticket.css
// LINKED JS:  assets/js/cameraInit.js    — initialises the device camera stream
//             assets/js/dropDown.js       — toggles the "Other" device type input
//             assets/js/passwordToggle.js — show/hide password field toggle
//             assets/js/photoArray.js     — manages the array of captured photos
//             assets/js/cameraFeed.js     — handles capture, flip, and stop actions
// =============================================================================

require_once "../includes/session.php";
require_once "../SQL_Connection.php";
checkLogin();

// ── Guard: customer step must be completed first ──────────────────────────────
if (empty($_SESSION["customer"])) {
    header("Location: http://customer.altismsp.com/index.php");
    exit();
}

// ── Cancel action ─────────────────────────────────────────────────────────────
if (isset($_GET["action"]) && $_GET["action"] === "cancel") {
    unset($_SESSION["customer"], $_SESSION["ticket"]);
    header("Location: http://customer.altismsp.com/index.php");
    exit();
}

// ── Load existing ticket session data ────────────────────────────────────────
$ticket = $_SESSION["ticket"] ?? null;

// ── Peripheral options ────────────────────────────────────────────────────────
$peripheralOptions = ["Mouse", "Mouse Dongle", "Keyboard", "Bag", "Power Adapter"];

// ── Decode stored peripheral data ────────────────────────────────────────────
// DEV_Peripheral is stored as JSON: {"items":["Mouse"],"other":"custom text"}
// Falls back gracefully if it's still a legacy plain string.
$storedPeriph     = $ticket["DEV_Peripheral"] ?? "";
$periphData       = [];
$checkedPeriph    = [];
$otherPeriphVal   = "";

if (!empty($storedPeriph)) {
    $decoded = json_decode($storedPeriph, true);
    if (is_array($decoded)) {
        $checkedPeriph  = $decoded["items"] ?? [];
        $otherPeriphVal = $decoded["other"] ?? "";
    } else {
        // Legacy plain string — treat as "other"
        $otherPeriphVal = $storedPeriph;
    }
}

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ── Device type handling ──────────────────────────────────────────────────
    $type = trim($_POST["type"] ?? "");
    if ($type === "Other" && !empty(trim($_POST["other_type"] ?? ""))) {
        $type = trim($_POST["other_type"]);
    }

    // ── No password / no peripherals flags ───────────────────────────────────
    $noPass   = !empty($_POST["no_pass"])   && $_POST["no_pass"]   === "1";
    $noPeriph = !empty($_POST["no_periph"]) && $_POST["no_periph"] === "1";

    // ── Build peripheral JSON ─────────────────────────────────────────────────
    // Collect checked boxes; if "Other" is among them, grab the text value too.
    $periphItems     = $_POST["peripherals_check"] ?? [];
    $periphOtherText = substr(trim($_POST["peripherals_other"] ?? ""), 0, 50); // enforce 50-char hard limit server-side

    $peripheralJson = "";
    if (!$noPeriph) {
        $peripheralJson = json_encode([
            "items" => array_values($periphItems),
            "other" => $periphOtherText,
        ]);
    }

    $_SESSION["ticket"] = [
        "DEV_Type"       => $type,
        "DEV_S/N"        => trim($_POST["serial-number"] ?? ""),
        "DEV_Brand"      => trim($_POST["brand"]         ?? ""),
        "DEV_Model"      => trim($_POST["model"]         ?? ""),
        "DEV_Pass/PIN"   => $noPass   ? "" : trim($_POST["password"] ?? ""),
        "no_pass"        => $noPass,
        // Stored as JSON string: {"items":["Mouse","Keyboard"],"other":""}
        "DEV_Peripheral" => $peripheralJson,
        "no_periph"      => $noPeriph,
        "T_Staff_Notes"  => trim($_POST["staff-notes"] ?? ""),
        "Label_Count"    => max(0, intval($_POST["labelCount"] ?? 0)),
    ];

    header("Location: ticketsummary.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Information</title>
    <link rel="icon" type="image/png" href="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png">
    <link rel="stylesheet" href="http://customer.altismsp.com/assets/css/ticket.css">
</head>
<body>

    <!-- ── Nav Bar ──────────────────────────────────────────────────────────── -->
    <nav class="nav-bar">
        <img src="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png"
             width="100" height="39" alt="Altis MSP logo">
    </nav>

    <div class="page-wrapper">

        <form id="ticketForm" action="ticket.php" method="POST">

            <h1 class="page-title">Ticket Information</h1>

            <!-- ── Device Details ───────────────────────────────────────────── -->
            <div class="section-card">
                <h2 class="section-heading">Device Details</h2>
                <div class="form-grid">

                    <!-- Device Type -->
                    <?php
                    $devType    = $ticket["DEV_Type"] ?? "";
                    $knownTypes = ["Laptop", "Desktop", "Printer"];
                    $otherVal   = !in_array($devType, array_merge($knownTypes, [""]))
                        ? htmlspecialchars($devType) : "";
                    ?>
                    <div class="form-field">
                        <label class="field-label" for="type">Device Type <span class="required-star" aria-hidden="true">*</span></label>
                        <select class="field-select" name="type" id="type" onchange="toggleOtherType()" required>
                            <option value="">-- Select Device Type --</option>
                            <?php foreach ($knownTypes as $t):
                                $sel = $devType === $t ? "selected" : ""; ?>
                                <option value="<?= $t ?>" <?= $sel ?>><?= $t ?></option>
                            <?php endforeach; ?>
                            <option value="Other" <?= $otherVal ? "selected" : "" ?>>Other</option>
                        </select>
                        <input class="field-input" type="text" name="other_type" id="other_type"
                               placeholder="Enter device type"
                               style="display:<?= $otherVal ? "block" : "none" ?>; margin-top: 0.5em;"
                               value="<?= $otherVal ?>">
                    </div>

                    <!-- Brand -->
                    <div class="form-field">
                        <label class="field-label" for="brand">Brand</label>
                        <input class="field-input" type="text" id="brand" name="brand" placeholder="e.g. Acer"
                               value="<?= htmlspecialchars($ticket["DEV_Brand"] ?? "") ?>">
                    </div>

                    <!-- Model -->
                    <div class="form-field">
                        <label class="field-label" for="model">Model</label>
                        <input class="field-input" type="text" id="model" name="model" placeholder="e.g. Aspire 5"
                               value="<?= htmlspecialchars($ticket["DEV_Model"] ?? "") ?>">
                    </div>

                    <!-- Serial Number -->
                    <div class="form-field">
                        <label class="field-label" for="serial-number">Serial Number</label>
                        <input class="field-input" type="text" id="serial-number" name="serial-number"
                               placeholder="e.g. 123456789"
                               value="<?= htmlspecialchars($ticket["DEV_S/N"] ?? "") ?>">
                    </div>

                    <!-- Password / PIN -->
                    <?php $noPassChecked = !empty($ticket["no_pass"]); ?>
                    <div class="form-field">
                        <label class="field-label" for="psw">Password / PIN <span class="required-star" aria-hidden="true">*</span></label>
                        <div style="position: relative;">
                            <input class="field-input" type="password" id="psw" name="password"
                                   placeholder="••••••••"
                                   <?= $noPassChecked ? "" : "required" ?>
                                   <?= $noPassChecked ? "disabled" : "" ?>
                                   value="<?= htmlspecialchars($ticket["DEV_Pass/PIN"] ?? "") ?>">
                            <button class="showpsw" type="button" id="Showpsw">Show</button>
                        </div>
                        <input type="hidden" id="no-pass-flag" name="no_pass" value="<?= $noPassChecked ? "1" : "0" ?>">
                        <div class="checkbox-row">
                            <input type="checkbox" id="no-password" name="no-password" value="no-password"
                                   <?= $noPassChecked ? "checked" : "" ?>>
                            <label for="no-password">No password on this device</label>
                        </div>
                    </div>

                    <!-- ── Peripherals — checkbox grid ───────────────────────────────────────
                         Each option posts as peripherals_check[].
                         "Other" reveals a free-text input (max 50 chars).
                         The no_periph flag disables the entire group when checked. -->
                    <?php $noPeriphChecked = !empty($ticket["no_periph"]); ?>
                    <div class="form-field full-width" id="periphField">
                        <label class="field-label">Peripherals <span class="required-star" aria-hidden="true">*</span></label>

                        <div class="periph-grid" id="periphGrid" <?= $noPeriphChecked ? 'style="opacity:0.4; pointer-events:none;"' : '' ?>>

                            <?php foreach ($peripheralOptions as $opt):
                                $checked = in_array($opt, $checkedPeriph) ? "checked" : "";
                                $id      = "periph_" . strtolower(str_replace(" ", "_", $opt));
                            ?>
                            <label class="periph-option <?= $checked ? 'periph-option--checked' : '' ?>" for="<?= $id ?>">
                                <input type="checkbox"
                                       id="<?= $id ?>"
                                       name="peripherals_check[]"
                                       value="<?= htmlspecialchars($opt) ?>"
                                       <?= $checked ?>
                                       onchange="syncPeriphOption(this)">
                                <?= htmlspecialchars($opt) ?>
                            </label>
                            <?php endforeach; ?>

                            <!-- Other -->
                            <?php
                            $otherChecked = !empty($otherPeriphVal) ? "checked" : "";
                            // Also check if "Other" was explicitly in the items array
                            if (in_array("Other", $checkedPeriph)) $otherChecked = "checked";
                            ?>
                            <label class="periph-option <?= $otherChecked ? 'periph-option--checked' : '' ?>" for="periph_other">
                                <input type="checkbox"
                                       id="periph_other"
                                       name="peripherals_check[]"
                                       value="Other"
                                       <?= $otherChecked ?>
                                       onchange="togglePeriphOther(this)">
                                Other
                            </label>

                        </div>

                        <!-- "Other" free-text input — shown when Other checkbox is ticked -->
                        <div id="periphOtherWrap" style="display:<?= $otherChecked ? 'block' : 'none' ?>; margin-top: 0.6em;">
                            <input class="field-input"
                                   type="text"
                                   id="peripherals_other"
                                   name="peripherals_other"
                                   placeholder="Describe other peripheral (max 50 chars)"
                                   maxlength="50"
                                   value="<?= htmlspecialchars($otherPeriphVal) ?>">
                            <div class="char-counter">
                                <span id="periphOtherCount"><?= strlen($otherPeriphVal) ?></span>/50
                            </div>
                        </div>

                        <input type="hidden" id="no-periph-flag" name="no_periph" value="<?= $noPeriphChecked ? "1" : "0" ?>">
                        <div class="checkbox-row" style="margin-top: 0.75em;">
                            <input type="checkbox" id="no-peripherals" <?= $noPeriphChecked ? "checked" : "" ?>
                                   onchange="toggleNoPeripherals(this)">
                            <label for="no-peripherals">No peripherals with this device</label>
                        </div>
                    </div>

                    <!-- Problem / Staff Notes -->
                    <div class="form-field full-width">
                        <label class="field-label" for="staff-notes">Problem / Staff Notes <span class="required-star" aria-hidden="true">*</span></label>
                        <textarea class="field-textarea" id="staff-notes" name="staff-notes"
                                  rows="3" required><?= htmlspecialchars($ticket["T_Staff_Notes"] ?? "") ?></textarea>
                    </div>

                </div>
            </div>

            <!-- ── Camera Section ───────────────────────────────────────────── -->
            <div class="section-card">
                <h2 class="section-heading">Device Photos</h2>

                <p class="camera-status">Status: <strong id="statusText">Offline</strong></p>

                <fieldset id="cameraSelectSection" style="border:none; padding:0;">
                    <div class="camera-select-row">
                        <label>
                            <input type="radio" name="facing" value="environment" checked>
                            Rear Camera
                        </label>
                        <label>
                            <input type="radio" name="facing" value="user">
                            Front Camera
                        </label>
                    </div>
                </fieldset>

                <p class="idle-msg" id="idleMsg">Camera not started yet.</p>

                <div class="camera-feed-wrapper" id="cameraFeedWrapper" style="display:none;">
                    <video id="videoFeed" autoplay playsinline width="640" height="480"></video>
                </div>

                <img id="photoPreview" width="640" height="480"
                     style="display:none; max-width:100%; border-radius:8px; margin-top:0.75em;">

                <div id="liveBadgeWrapper" style="display:none; margin: 0.5em 0;">
                    <span class="live-badge">● LIVE</span>
                </div>

                <div class="camera-btn-row" id="cameraBtns">
                    <button class="secondary-btn" type="button" id="startBtn"    onclick="initCamera()">Start Camera</button>
                    <button class="util-btn"       id="captureBtn" type="button" onclick="capturePhoto()" disabled>Capture Photo</button>
                    <button class="util-btn"       id="flipBtn"    type="button" onclick="flipCamera()"   disabled>Flip Camera</button>
                    <button class="danger-btn"     id="stopBtn"    type="button" onclick="stopCamera()"   disabled>Stop Camera</button>
                </div>

                <div style="margin-top: 1.25em;">
                    <span class="photo-count-label">Captured Photos (<span id="photoCount">0</span>)</span>
                    <div id="thumbStrip">
                        <em class="no-photos-msg" id="noPhotosMsg">No photos yet.</em>
                    </div>
                </div>

                <p id="submitMsg"></p>
                <canvas id="canvas" style="display:none;"></canvas>
            </div>

            <!-- ── Form Actions ──────────────────────────────────────────────── -->
            <div class="form-actions">
                <button class="secondary-btn" type="button"
                        onclick="window.location.href='customer.php'">Previous</button>
                <button class="danger-btn" type="button" onclick="cancelTicket()">Cancel</button>
                <button class="primary-btn" type="button" id="submitBtn" onclick="submitWithPhotos()">Next</button>
            </div>

        </form>

    </div><!-- /page-wrapper -->

    <script src="/assets/js/cameraInit.js"></script>
    <script src="/assets/js/dropDown.js"></script>
    <script src="/assets/js/passwordToggle.js"></script>
    <script src="/assets/js/photoArray.js"></script>
    <script src="/assets/js/cameraFeed.js"></script>
    <script src="/assets/js/auth.js?v=1.1"></script>

    <script>
        // ── Cancel ticket ─────────────────────────────────────────────────────
        function cancelTicket() {
            sessionStorage.removeItem("devicePhotos");
            sessionStorage.removeItem("ticketSignature");
            window.location.href = "ticket.php?action=cancel";
        }

        // ── Peripheral helpers ────────────────────────────────────────────────

        /**
         * Syncs the visual checked state of a periph-option label
         * when any checkbox (except Other) changes.
         */
        function syncPeriphOption(checkbox) {
            const label = checkbox.closest(".periph-option");
            if (label) label.classList.toggle("periph-option--checked", checkbox.checked);
        }

        /**
         * Shows/hides the "Other" free-text input and syncs the label style.
         */
        function togglePeriphOther(checkbox) {
            const wrap  = document.getElementById("periphOtherWrap");
            const input = document.getElementById("peripherals_other");
            const label = checkbox.closest(".periph-option");

            wrap.style.display = checkbox.checked ? "block" : "none";
            if (label) label.classList.toggle("periph-option--checked", checkbox.checked);

            if (!checkbox.checked) {
                input.value = "";
                document.getElementById("periphOtherCount").textContent = "0";
            }
        }

        /**
         * Enables/disables the entire peripheral grid when
         * "No peripherals with this device" is toggled.
         */
        function toggleNoPeripherals(checkbox) {
            const grid  = document.getElementById("periphGrid");
            const wrap  = document.getElementById("periphOtherWrap");
            const flag  = document.getElementById("no-periph-flag");

            if (checkbox.checked) {
                // Disable grid — uncheck all options visually & in DOM
                grid.style.opacity      = "0.4";
                grid.style.pointerEvents = "none";
                wrap.style.display       = "none";
                flag.value = "1";

                grid.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                    cb.checked = false;
                    const lbl = cb.closest(".periph-option");
                    if (lbl) lbl.classList.remove("periph-option--checked");
                });
                document.getElementById("peripherals_other").value = "";
                document.getElementById("periphOtherCount").textContent = "0";
            } else {
                grid.style.opacity       = "1";
                grid.style.pointerEvents = "auto";
                flag.value = "0";
            }
        }

        // ── Character counter for Other periph text ───────────────────────────
        document.getElementById("peripherals_other").addEventListener("input", function () {
            document.getElementById("periphOtherCount").textContent = this.value.length;
        });
    </script>

    <!-- ── Peripheral styles ─────────────────────────────────────────────────── -->
    <style>
        /* Grid of checkbox pill options */
        .periph-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5em;
            transition: opacity 0.2s;
        }

        /* Each pill label */
        .periph-option {
            display: inline-flex;
            align-items: center;
            gap: 0.45em;
            padding: 0.45em 0.9em;
            border: 1.5px solid #d1d5db;
            border-radius: 999px;
            cursor: pointer;
            font-size: 0.875em;
            font-family: inherit;
            color: #374151;
            background: #fff;
            transition: border-color 0.15s, background 0.15s, color 0.15s;
            user-select: none;
        }

        /* Hide the native checkbox but keep it accessible */
        .periph-option input[type="checkbox"] {
            width: 0;
            height: 0;
            opacity: 0;
            position: absolute;
            pointer-events: none;
        }

        /* Checked state */
        .periph-option--checked {
            border-color: #2563eb;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 500;
        }

        .periph-option--checked::before {
            content: "✓ ";
            font-weight: 700;
            color: #2563eb;
        }

        .periph-option:hover:not(.periph-option--checked) {
            border-color: #9ca3af;
            background: #f9fafb;
        }

        /* Character counter */
        .char-counter {
            font-size: 0.75em;
            color: #9ca3af;
            text-align: right;
            margin-top: 0.25em;
        }

        /* Show/hide password button */
        .showpsw {
            position: absolute;
            right: 0.875em;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-family: 'Roboto', sans-serif;
            font-size: 0.8125em;
            font-weight: 500;
            color: #767676;
            padding: 0;
            transition: color 0.2s;
        }
    </style>

    <!-- ── Footer ───────────────────────────────────────────────────────────── -->
    <footer>
        <p class="copyright">© 2026 FireNode & AltisMSP</p>
    </footer>

</body>
</html>