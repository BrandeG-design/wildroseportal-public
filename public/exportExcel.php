<?php
// =============================================================================
// FILE: exportExcel.php
// PURPOSE: Receives the same form data as report.php, runs the identical query,
//          and streams the results as a UTF-8 CSV file. CSV opens natively in
//          Excel, LibreOffice Calc, and Google Sheets with no format issues.
//          The filename uses a .csv extension which Excel always accepts.
//
//          Called by the "Export to Excel" button on report.php.
// =============================================================================

require_once "../includes/session.php";
require_once "../SQL_Connection.php";
checkLogin();

// ── Available columns — must match report.php exactly ────────────────────────
$availableColumns = [
    "ticket_num"  => ["Ticket #",      "t.`T_Ticket#`",                        false],
    "ticket_date" => ["Date",          "DATE_FORMAT(t.T_Date,'%Y-%m-%d')",      true],
    "cust_name"   => ["Customer Name", "CONCAT(c.CUST_Fname,' ',c.CUST_Lname)", true],
    "cust_phone"  => ["Phone Number",  "c.CUST_Phone",                          false],
    "cust_email"  => ["Email",         "c.CUST_Email",                          false],
    "dev_type"    => ["Device Type",   "d.DEV_Type",                            true],
    "dev_brand"   => ["Brand",         "d.DEV_Brand",                           true],
    "dev_model"   => ["Model",         "d.DEV_Model",                           true],
    "dev_serial"  => ["Serial Number", "d.`DEV_S/N`",                           false],
    "dev_periph"  => ["Peripherals",   "d.DEV_Peripheral",                      false],
    "staff_notes" => ["Staff Notes",   "t.T_Staff_Notes",                       false],
];

$knownDeviceTypes = ["Desktop", "Laptop", "Printer"];

$sortMap = [
    "ticket_date_desc" => "t.T_Date DESC",
    "ticket_date_asc"  => "t.T_Date ASC",
    "cust_name_asc"    => "c.CUST_Fname ASC, c.CUST_Lname ASC",
    "cust_name_desc"   => "c.CUST_Fname DESC, c.CUST_Lname DESC",
    "ticket_num_asc"   => "t.`T_Ticket#` ASC",
    "ticket_num_desc"  => "t.`T_Ticket#` DESC",
];

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: http://customer.altismsp.com/public/report.php");
    exit();
}

// ── Read form inputs ──────────────────────────────────────────────────────────
$startDate     = $_POST["start_date"]  ?? "";
$endDate       = $_POST["end_date"]    ?? "";
$sortBy        = $_POST["sort_by"]     ?? "ticket_date_desc";
$selectedCols  = $_POST["columns"]     ?? [];
$selectedTypes = $_POST["dev_types"]   ?? [];

if (empty($selectedCols)) {
    header("Location: http://customer.altismsp.com/public/report.php");
    exit();
}

// ── Build SELECT clause ───────────────────────────────────────────────────────
$selectParts = [];
$headers     = [];

foreach ($selectedCols as $colKey) {
    if (isset($availableColumns[$colKey])) {
        $col           = $availableColumns[$colKey];
        $selectParts[] = $col[1] . " AS `" . $col[0] . "`";
        $headers[]     = $col[0];
    }
}

$orderClause = $sortMap[$sortBy] ?? "t.T_Date DESC";

// ── Build WHERE clause ────────────────────────────────────────────────────────
$sql = "SELECT " . implode(", ", $selectParts) . "
        FROM ticket t
        JOIN customer c ON t.CUST_ID = c.CUST_ID
        JOIN device d   ON t.DEV_ID  = d.DEV_ID
        WHERE 1=1";

$params     = [];
$paramTypes = "";

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

// Device type filter — mirrors report.php including "Other" handling
if (!empty($selectedTypes)) {
    $includeOther  = in_array("Other", $selectedTypes);
    $knownSelected = array_values(
        array_filter($selectedTypes, fn($t) => $t !== "Other")
    );

    if ($includeOther && !empty($knownSelected)) {
        $placeholders = implode(", ", array_fill(0, count($knownSelected), "?"));
        $knownHolders = implode(", ", array_fill(0, count($knownDeviceTypes), "?"));
        $sql .= " AND (d.DEV_Type IN ($placeholders) OR d.DEV_Type NOT IN ($knownHolders))";
        foreach ($knownSelected    as $t) { $params[] = $t; $paramTypes .= "s"; }
        foreach ($knownDeviceTypes as $t) { $params[] = $t; $paramTypes .= "s"; }
    } elseif ($includeOther) {
        $knownHolders = implode(", ", array_fill(0, count($knownDeviceTypes), "?"));
        $sql .= " AND d.DEV_Type NOT IN ($knownHolders)";
        foreach ($knownDeviceTypes as $t) { $params[] = $t; $paramTypes .= "s"; }
    } else {
        $placeholders = implode(", ", array_fill(0, count($knownSelected), "?"));
        $sql .= " AND d.DEV_Type IN ($placeholders)";
        foreach ($knownSelected as $t) { $params[] = $t; $paramTypes .= "s"; }
    }
}

$sql .= " ORDER BY " . $orderClause;

// ── Run query ─────────────────────────────────────────────────────────────────
$db   = getDB();
$stmt = $db->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// ── Stream CSV headers ────────────────────────────────────────────────────────
// BOM (\xEF\xBB\xBF) tells Excel this is UTF-8, ensuring special characters
// like accented names display correctly instead of showing garbled text.
$filename = "WildRose_Report_" . date("Y-m-d") . ".csv";
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Cache-Control: max-age=0");

$out = fopen("php://output", "w");

// Write UTF-8 BOM
fwrite($out, "\xEF\xBB\xBF");

// Write header row
fputcsv($out, $headers);

// Write data rows
while ($row = $result->fetch_assoc()) {
    // Replace nulls with empty string so CSV doesn't output "NULL"
    $row = array_map(fn($v) => $v ?? "", $row);
    fputcsv($out, $row);
}

fclose($out);
$stmt->close();
$db->close();
exit();
