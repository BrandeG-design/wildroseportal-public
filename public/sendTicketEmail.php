<?php
/**
 * sendTicketEmail.php
 *
 * Sends a ticket confirmation email via PHPMailer (DreamHost SMTP).
 * Call sendTicketEmail() immediately after a successful ticket commit.
 *
 * Parameters:
 *   $customer   – the $_SESSION["customer"] array
 *   $ticket     – the $_SESSION["ticket"] array
 *   $signBlob   – raw PNG binary of the customer signature (may be null)
 *   $photoUrls  – array of base64 data-URL strings for device photos (may be empty)
 */

/**
 * These config addresses could be placed in a single file and not exposed this way.
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../includes/emailconfig.php";

/**
 * Builds a plain-text email body using DB column names as field labels,
 * then sends it with the signature and device photos as attachments.
 *
 * @param  array        $customer   Customer session data
 * @param  array        $ticket     Ticket session data
 * @param  string|null  $signBlob   Raw PNG binary of the signature (or null)
 * @param  array        $photoUrls  Array of base64 data-URL strings
 * @return void
 * @throws Exception    Re-thrown so the caller can log failures without crashing
 */
function sendTicketEmail(
    array $customer,
    array $ticket,
    ?string $signBlob,
    array $photoUrls,
): void {
    // ── Build plain-text body ─────────────────────────────────────────────────
    $isExisting = !empty($customer["is_existing"]) ? "Yes" : "No";
    $custId = !empty($customer["CUST_ID"])
        ? (int) $customer["CUST_ID"]
        : "N/A (new customer)";

    $body = "=== TICKET SUBMISSION ===\n\n";

    // -- customer table fields -------------------------------------------------
    $body .= "--- customer ---\n";
    $body .= "Customer ID      : {$custId}\n";
    $body .= "is_existing  : {$isExisting}\n";
    $body .=
        "Customer First Name   : " . ($customer["CUST_Fname"] ?? "") . "\n";
    $body .= "Customer Last Name   : " . ($customer["CUST_Lname"] ?? "") . "\n";
    $body .= "Customer Phone   : " . ($customer["CUST_Phone"] ?? "") . "\n";
    $body .= "Customer Address : " . ($customer["CUST_Address"] ?? "") . "\n";
    $body .= "Customer Email   : " . ($customer["CUST_Email"] ?? "") . "\n\n";

    // -- device table fields ---------------------------------------------------
    $body .= "--- device ---\n";
    $body .= "Device S/N        : " . ($ticket["DEV_S/N"] ?? "") . "\n";
    $body .= "Device Brand      : " . ($ticket["DEV_Brand"] ?? "") . "\n";
    $body .= "Device Model      : " . ($ticket["DEV_Model"] ?? "") . "\n";
    $body .= "Device Type       : " . ($ticket["DEV_Type"] ?? "") . "\n";
    $body .= "Device Peripheral : " . ($ticket["DEV_Peripheral"] ?? "") . "\n";
    $body .= "Device Pass/PIN   : " . ($ticket["DEV_Pass/PIN"] ?? "") . "\n\n";

    // -- ticket table fields ---------------------------------------------------
    $body .= "--- ticket ---\n";
    $body .=
        "Ticket Staff Notes  : " . ($ticket["T_Staff_Notes"] ?? "") . "\n\n";

    // -- attachments summary ---------------------------------------------------
    $photoCount = count($photoUrls);
    $body .= "--- attachments ---\n";
    $body .=
        "Ticket Customer Signature    : " .
        ($signBlob !== null ? "Yes (see attachment: signature.png)" : "None") .
        "\n";
    $body .=
        "Device Photos  : " .
        ($photoCount > 0 ? "{$photoCount} photo(s) attached" : "None") .
        "\n";

    // ── Compose and send ──────────────────────────────────────────────────────
    $mail = new PHPMailer(true);

    // Server settings
    $mail->SMTPDebug = 0; // Set to 2 for verbose debug output during development
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = "tls";
    $mail->Port = MAIL_PORT;

    // Recipients
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress(MAIL_TO, MAIL_TO_NAME);

    // Subject
    $fname = $customer["CUST_Fname"] ?? "";
    $lname = $customer["CUST_Lname"] ?? "";
    $mail->Subject = "New Ticket - {$fname} {$lname}";

    // Plain-text body
    $mail->isHTML(false);
    $mail->Body = $body;

    // ── Signature attachment ──────────────────────────────────────────────────
    if ($signBlob !== null) {
        $mail->addStringAttachment(
            $signBlob,
            "signature.png",
            PHPMailer::ENCODING_BASE64,
            "image/png",
        );
    }

    // ── Device photo attachments ──────────────────────────────────────────────
    foreach ($photoUrls as $index => $dataUrl) {
        $b64 = preg_replace("#^data:image/\w+;base64,#i", "", $dataUrl);
        $blob = base64_decode($b64);

        $mail->addStringAttachment(
            $blob,
            "device_photo_" . ($index + 1) . ".jpg",
            PHPMailer::ENCODING_BASE64,
            "image/jpeg",
        );
    }

    $mail->send();
}
