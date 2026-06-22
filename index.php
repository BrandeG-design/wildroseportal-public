<?php
require_once "includes/session.php";
require_once "SQL_Connection.php";
require_once "public/searchCust.php";
require_once "public/getCustID.php";
checkLogin();

// AJAX search endpoint
if (isset($_GET["search"])) {
    header("Content-Type: application/json");
    echo json_encode(searchCustomers($_GET["search"]));
    exit();
}

// Existing customer selected — store in session and redirect to customer form
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["CUST_ID"])) {
    $customer = getCustomerById((int) $_POST["CUST_ID"]);

    if ($customer) {
        $_SESSION["customer"]                = $customer;
        $_SESSION["customer"]["is_existing"] = true;
    }

    header("Location: http://customer.altismsp.com/public/customer.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AltisMSP Portal</title>
    <link rel="icon" type="image/png" href="assets/images/altisMSPLogoSmall.png">
    <!-- Index Stylesheet -->
    <link rel="stylesheet" href="/assets/css/index.css?v=1.2">
</head>

<body>
    <!-- AltisMSP Logo -->
    <nav class="nav-bar">
        <img src="assets/images/altisMSPLogoSmall.png" width="100" height="39" alt="Altis MSP logo">
    </nav>

    <div class="page-wrapper">

        <!-- Header -->
        <div class="header-container">
            <img src="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png"
                alt="WildRose Portal Logo">
        </div>

        <!-- New Customer -->
        <div class="action-grid">
            <a class="nav-btn" href="http://customer.altismsp.com/public/customer.php">
                New Customer
            </a>
        </div>

        <!-- Existing Customer Search -->
        <div class="section-card">
            <h2 class="section-heading">Existing Customer</h2>
            <form onsubmit="return false;">
                <div class="search-wrapper">
                    <input class="search-input" type="text" id="customer-search"
                        placeholder="Search by name, phone, or email…"
                        maxlength="50" autocomplete="off">
                </div>
            </form>
            <div id="search-results"></div>
        </div>

        <!-- Hidden form — posts selected customer ID back to this page -->
        <form id="select-form" action="index.php" method="POST">
            <input type="hidden" name="CUST_ID" id="fcust-id">
        </form>

        <!-- Admin Button -->
        <div class="admin-btn-wrapper">
            <a class="admin-btn" href="http://customer.altismsp.com/public/admin.php">Admin</a>
        </div>

    </div><!-- /page-wrapper -->

    <script src="/assets/js/liveSearch.js"></script>

    <footer>
        <p class="copyright">© 2026 FireNode & AltisMSP</p>
    </footer>
</body>

</html>