<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Call function to check if user is logged in otherwise redirect to auth.php
function checkLogin(): void
{
    if (!isset($_SESSION["staff_id"])) {
        header("Location: http://customer.altismsp.com/public/auth.php");
        exit();
    }
}
