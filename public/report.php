<?php
// =============================================================================
// FILE: report.php
// PURPOSE: Allows staff to generate filtered ticket reports. Staff can filter
//          by date range and device type, choose which columns to display, and
//          sort the results. Reports can be printed as PDF or exported to CSV
//          for use in Excel.
//
// ACCESS:     Requires staff to be logged in (enforced by checkLogin())
// LINKED CSS: assets/css/report.css
// LINKED JS:  assets/js/report.js      — client-side form validation
//             exportExcel.php           — handles CSV/Excel download
// =============================================================================

require_once "../includes/session.php";
require_once "../SQL_Connection.php";
checkLogin();

// ── Initialise result variables ───────────────────────────────────────────────
$reportData  = [];
$reportRan   = false;
$reportError = "";
$totalCount  = 0;

// ── Device type options ───────────────────────────────────────────────────────
// "Other" is a catch-all for any device type not in the known three.
// $knownDeviceTypes is used in the SQL query to identify "Other" records
// (anything NOT IN this list).
$allDeviceTypes   = ["Desktop", "Laptop", "Printer", "Other"];
$knownDeviceTypes = ["Desktop", "Laptop", "Printer"];

// ── Available columns ─────────────────────────────────────────────────────────
// Each entry: [display label, SQL expression, default selected (true/false)]
// The SQL expression is injected directly into the SELECT clause — only keys
// from this array are ever used, so there is no SQL injection risk.
$availableColumns = [
    "ticket_num"  => ["Ticket #",       "t.`T_Ticket#`",                        false],
    "ticket_date" => ["Date",           "DATE_FORMAT(t.T_Date,'%Y-%m-%d')",      true],
    "cust_name"   => ["Customer Name",  "CONCAT(c.CUST_Fname,' ',c.CUST_Lname)", true],
    "cust_phone"  => ["Phone Number",   "c.CUST_Phone",                          false],
    "cust_email"  => ["Email",          "c.CUST_Email",                          false],
    "dev_type"    => ["Device Type",    "d.DEV_Type",                            true],
    "dev_brand"   => ["Brand",          "d.DEV_Brand",                           true],
    "dev_model"   => ["Model",          "d.DEV_Model",                           true],
    "dev_serial"  => ["Serial Number",  "d.`DEV_S/N`",                           false],
    "dev_periph"  => ["Peripherals",    "d.DEV_Peripheral",                      false],
    "staff_notes" => ["Staff Notes",    "t.T_Staff_Notes",                       false],
];

// ── Sort options (display labels shown to the user) ───────────────────────────
$sortOptions = [
    "ticket_date_desc" => "Date (Newest First)",
    "ticket_date_asc"  => "Date (Oldest First)",
    "cust_name_asc"    => "First Name (A-Z)",
    "cust_name_desc"   => "First Name (Z-A)",
    "ticket_num_asc"   => "Ticket # (Ascending)",
    "ticket_num_desc"  => "Ticket # (Descending)",
];

// ── Sort map (translates user selection to SQL ORDER BY clause) ───────────────
$sortMap = [
    "ticket_date_desc" => "t.T_Date DESC",
    "ticket_date_asc"  => "t.T_Date ASC",
    "cust_name_asc"    => "c.CUST_Fname ASC, c.CUST_Lname ASC",
    "cust_name_desc"   => "c.CUST_Fname DESC, c.CUST_Lname DESC",
    "ticket_num_asc"   => "t.`T_Ticket#` ASC",
    "ticket_num_desc"  => "t.`T_Ticket#` DESC",
];

// ── Report generation ─────────────────────────────────────────────────────────
// Only runs when the Generate Report button is clicked (POST + generate key)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["generate"])) {
    $startDate     = $_POST["start_date"]  ?? "";
    $endDate       = $_POST["end_date"]    ?? "";
    $sortBy        = $_POST["sort_by"]     ?? "ticket_date_desc";
    $selectedCols  = $_POST["columns"]     ?? [];
    $selectedTypes = $_POST["dev_types"]   ?? [];

    // ── Input validation ──────────────────────────────────────────────────────
    if (empty($selectedCols)) {
        $reportError = "Please select at least one column to include in the report.";
    } elseif (!empty($startDate) && !empty($endDate) && $startDate > $endDate) {
        $reportError = "The start date cannot be later than the end date.";
    } else {

        // ── Build SELECT clause ───────────────────────────────────────────────
        // Map each selected column key to its SQL expression aliased with the
        // display label so column headers come back from the DB already named
        $selectParts = [];
        foreach ($selectedCols as $colKey) {
            if (isset($availableColumns[$colKey])) {
                $col           = $availableColumns[$colKey];
                $selectParts[] = $col[1] . " AS `" . $col[0] . "`";
            }
        }

        $orderClause = $sortMap[$sortBy] ?? "t.T_Date DESC";

        // WHERE 1=1 is a placeholder that makes appending AND clauses simpler
        $sql = "SELECT " . implode(", ", $selectParts) . "
                FROM ticket t
                JOIN customer c ON t.CUST_ID = c.CUST_ID
                JOIN device d   ON t.DEV_ID  = d.DEV_ID
                WHERE 1=1";

        $params     = [];
        $paramTypes = "";

        // ── Date range filters ────────────────────────────────────────────────
        if (!empty($startDate)) {
            $sql        .= " AND DATE(t.T_Date) >= ?";
            $params[]    = $startDate;
            $paramTypes .= "s";
        }

        if (!empty($endDate)) {
            $sql        .= " AND DATE(t.T_Date) <= ?";
            $params[]    = $endDate;
            $paramTypes .= "s";
        }

        // ── Device type filter ────────────────────────────────────────────────
        // Three cases depending on whether "Other" is included:
        // 1. Known types + Other: IN (selected known) OR NOT IN (all three known)
        // 2. Only Other:          NOT IN (all three known)
        // 3. No Other:            plain IN (selected known)
        if (!empty($selectedTypes)) {
            $includeOther  = in_array("Other", $selectedTypes);
            $knownSelected = array_values(
                array_filter($selectedTypes, fn($t) => $t !== "Other")
            );

            if ($includeOther && !empty($knownSelected)) {
                // Known types + Other: match selected known OR anything outside all three known
                $placeholders = implode(", ", array_fill(0, count($knownSelected), "?"));
                $knownHolders = implode(", ", array_fill(0, count($knownDeviceTypes), "?"));
                $sql .= " AND (d.DEV_Type IN ($placeholders) OR d.DEV_Type NOT IN ($knownHolders))";
                foreach ($knownSelected    as $t) { $params[] = $t; $paramTypes .= "s"; }
                foreach ($knownDeviceTypes as $t) { $params[] = $t; $paramTypes .= "s"; }

            } elseif ($includeOther) {
                // Only Other: exclude all known types
                $knownHolders = implode(", ", array_fill(0, count($knownDeviceTypes), "?"));
                $sql .= " AND d.DEV_Type NOT IN ($knownHolders)";
                foreach ($knownDeviceTypes as $t) { $params[] = $t; $paramTypes .= "s"; }

            } else {
                // No Other: plain IN clause for the selected known types
                $placeholders = implode(", ", array_fill(0, count($knownSelected), "?"));
                $sql .= " AND d.DEV_Type IN ($placeholders)";
                foreach ($knownSelected as $t) { $params[] = $t; $paramTypes .= "s"; }
            }
        }

        $sql .= " ORDER BY " . $orderClause;

        // ── Execute query ─────────────────────────────────────────────────────
        $db   = getDB();
        $stmt = $db->prepare($sql);

        // Only bind params if there are any — bind_param fails on empty arrays
        if (!empty($params)) {
            $stmt->bind_param($paramTypes, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }

        $totalCount = count($reportData);
        $stmt->close();
        $db->close();

        $reportRan = true;
    }
}

// ── Re-populate form fields after submission ──────────────────────────────────
// These variables are used to restore the form to the same state after a POST
// so the user doesn't lose their filter selections when the results appear.
$formStartDate    = $_POST["start_date"] ?? "";
$formEndDate      = $_POST["end_date"]   ?? "";
$formSortBy       = $_POST["sort_by"]    ?? "ticket_date_desc";
// Default selected columns are those with true in the third array element
$formSelectedCols = $_POST["columns"]    ?? array_keys(array_filter($availableColumns, fn($c) => $c[2]));
// Default to all device types selected when the form hasn't been submitted yet
$formDevTypes     = isset($_POST["generate"])
    ? $_POST["dev_types"] ?? []
    : $allDeviceTypes;

// Tab index counter — starts at 5 to leave room for nav/back button tab stops
$cbTabIdx = 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Generator</title>
    <link rel="icon" type="image/png" href="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png">
    <link rel="stylesheet" href="http://customer.altismsp.com/assets/css/report.css">
</head>
<body>

    <!-- ── Nav Bar — hidden on print ───────────────────────────────────────── -->
    <nav class="nav-bar no-print">
        <img src="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png"
             width="100" height="39" alt="Altis MSP logo">
    </nav>

    <div class="page-wrapper">

        <!-- Header — hidden on print so only the report table shows -->
        <div class="no-print">
            <h1 class="page-title">Report Generator</h1>
            <p class="page-description">Filter ticket records by date range and choose which columns to display.</p>
        </div>

        <!-- ── Error Message ─────────────────────────────────────────────────── -->
        <?php if ($reportError): ?>
            <div class="error-alert no-print" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($reportError) ?>
            </div>
        <?php endif; ?>

        <!-- ── Filter Form — hidden on print ────────────────────────────────── -->
        <form method="POST" action="report.php" id="report-form" class="no-print">

            <!-- Date Range -->
            <div class="section-card">
                <span class="section-heading">Date Range</span>
                <div class="date-row">
                    <div class="field-group">
                        <label class="field-label" for="start_date">Start Date</label>
                        <!-- max set to today so future dates can't be selected -->
                        <input class="field-input" type="date" id="start_date" name="start_date"
                               tabindex="2" value="<?= htmlspecialchars($formStartDate) ?>"
                               max="<?= date("Y-m-d") ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="end_date">End Date</label>
                        <input class="field-input" type="date" id="end_date" name="end_date"
                               tabindex="3" value="<?= htmlspecialchars($formEndDate) ?>"
                               max="<?= date("Y-m-d") ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="sort_by">Sort By</label>
                        <select class="field-select" id="sort_by" name="sort_by" tabindex="4">
                            <?php foreach ($sortOptions as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= $formSortBy === $val ? "selected" : "" ?>>
                                    <?= htmlspecialchars($lbl) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <p class="field-hint">Leave both dates blank to include all tickets.</p>
            </div>

            <!-- Device Types — checkboxes to include/exclude device categories -->
            <div class="section-card">
                <span class="section-heading">Device Types</span>
                <p class="checkbox-hint">Uncheck any types you want to exclude. Leave all checked to include everything.</p>
                <div class="checkbox-list">
                    <?php foreach ($allDeviceTypes as $devT):
                        $checked = in_array($devT, $formDevTypes) ? "checked" : ""; ?>
                        <label>
                            <input type="checkbox" name="dev_types[]"
                                   value="<?= htmlspecialchars($devT) ?>"
                                   tabindex="<?= $cbTabIdx++ ?>" <?= $checked ?>>
                            <?= htmlspecialchars($devT) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button class="util-btn" type="button" tabindex="<?= $cbTabIdx++ ?>"
                        onclick="document.querySelectorAll('input[name=\'dev_types[]\']').forEach(cb => cb.checked = true)">
                    Select All
                </button>
                <button class="util-btn" type="button" tabindex="<?= $cbTabIdx++ ?>"
                        onclick="document.querySelectorAll('input[name=\'dev_types[]\']').forEach(cb => cb.checked = false)">
                    Clear All
                </button>
            </div>

            <!-- Columns to Include — checkboxes map to $availableColumns keys -->
            <div class="section-card">
                <span class="section-heading">Columns to Include</span>
                <div class="checkbox-list">
                    <?php foreach ($availableColumns as $key => $col):
                        $checked = in_array($key, $formSelectedCols) ? "checked" : ""; ?>
                        <label>
                            <input type="checkbox" name="columns[]" value="<?= $key ?>"
                                   tabindex="<?= $cbTabIdx++ ?>" <?= $checked ?>>
                            <?= htmlspecialchars($col[0]) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button class="util-btn" type="button" tabindex="<?= $cbTabIdx++ ?>" onclick="selectAll()">Select All</button>
                <button class="util-btn" type="button" tabindex="<?= $cbTabIdx++ ?>" onclick="clearAll()">Clear All</button>
            </div>

            <!-- Form Actions -->
            <div class="action-row">
                <!-- name="generate" value="1" is the flag checked in PHP to trigger report generation -->
                <button class="primary-btn" type="submit" name="generate" value="1" tabindex="<?= $cbTabIdx++ ?>">
                    Generate Report
                </button>
                <button class="secondary-btn" type="button" tabindex="<?= $cbTabIdx++ ?>"
                        onclick="if(confirm('Reset the form? All selections will return to defaults.')) { document.getElementById('report-form').reset(); }">
                    Reset Form
                </button>
            </div>
        </form>

        <!-- ── Report Results ────────────────────────────────────────────────── -->
        <?php if ($reportRan): ?>
            <hr class="section-divider no-print">
            <div id="report-results">

                <!-- Record count and date range summary — hidden on print -->
                <div class="results-meta no-print">
                    <span class="results-count"><?= $totalCount ?> record<?= $totalCount !== 1 ? "s" : "" ?></span>
                    <?php if ($formStartDate || $formEndDate): ?>
                        <span>
                            <?= $formStartDate ? htmlspecialchars($formStartDate) : "(all)" ?>
                            &mdash;
                            <?= $formEndDate ? htmlspecialchars($formEndDate) : "(all)" ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($totalCount === 0): ?>
                    <p class="no-results-msg" role="status">No tickets matched the selected filters. Try adjusting the date range or removing filters.</p>
                <?php else: ?>

                    <!-- Print and export buttons — hidden on print -->
                    <div class="no-print" style="margin-bottom: 16px; display:flex; gap:0.75em; flex-wrap:wrap;">
                        <button class="secondary-btn" type="button" onclick="window.print()">Print / Save as PDF</button>
                        <!-- exportToExcel() defined below — POSTs form data to exportExcel.php -->
                        <button class="secondary-btn" type="button" onclick="exportToExcel()">Export to CSV / Excel</button>
                    </div>

                    <!-- Print header — only visible when printing -->
                    <div class="print-only">
                        <strong>WildRose Portal — Report Generator</strong><br>
                        Generated: <?= date("Y-m-d") ?>
                        <?php if ($formStartDate || $formEndDate): ?>
                            &nbsp;|&nbsp; Date range:
                            <?= $formStartDate ? htmlspecialchars($formStartDate) : "(all)" ?>
                            to
                            <?= $formEndDate ? htmlspecialchars($formEndDate) : "(all)" ?>
                        <?php else: ?>
                            &nbsp;|&nbsp; All dates included
                        <?php endif; ?>
                        <br><br>
                    </div>

                    <!-- Results table — column headers are the display labels from $availableColumns -->
                    <div class="section-card" style="overflow-x: auto;">
                        <table class="report-table" aria-label="Ticket report results">
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($reportData[0]) as $col): ?>
                                        <th scope="col"><?= htmlspecialchars($col) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                        <td><?= htmlspecialchars($cell ?? "—") ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ── Back Button — hidden on print ────────────────────────────────── -->
        <br><a class="back-btn no-print" href="http://customer.altismsp.com/public/admin.php" tabindex="1">Back</a>

    </div><!-- /page-wrapper -->

    <!-- report.js handles selectAll(), clearAll(), and client-side form validation -->
    <script src="/assets/js/report.js"></script>

    <!-- ── Footer — hidden on print ─────────────────────────────────────────── -->
    <footer class="no-print">
        <p class="copyright">&copy; 2026 FireNode & AltisMSP</p>
    </footer>

    <script>
        // ── Export to CSV / Excel ─────────────────────────────────────────────
        // Collects the current form selections and POSTs them to exportExcel.php,
        // which runs the same query and streams a .csv download that opens in Excel.
        // A hidden form is built dynamically so the main report form isn't affected.
        function exportToExcel() {
            const reportForm = document.getElementById("report-form");
            if (!reportForm) return;

            // Build a hidden form pointing to the export endpoint
            const exportForm = document.createElement("form");
            exportForm.method = "POST";
            exportForm.action = "exportExcel.php";
            exportForm.style.display = "none";

            // Copy all current form field values into the export form
            const formData = new FormData(reportForm);
            formData.append("generate", "1"); // signal that this is a real submission

            for (const [key, value] of formData.entries()) {
                const input = document.createElement("input");
                input.type  = "hidden";
                input.name  = key;
                input.value = value;
                exportForm.appendChild(input);
            }

            document.body.appendChild(exportForm);
            exportForm.submit();
            document.body.removeChild(exportForm);
        }
    </script>

</body>
</html>
