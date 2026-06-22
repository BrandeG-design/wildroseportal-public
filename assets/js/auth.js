// =============================================================================
// FILE: auth.js
// PURPOSE: Handles the Show/Hide password toggle button used on auth.php
//          (Login page) and password.php (Change Password page).
//
//          Targets all three password fields by ID so a single toggle button
//          can reveal/hide whichever fields are present on the current page.
//          Fields that don't exist on the page are simply skipped by
//          querySelectorAll returning an empty NodeList for missing IDs.
//
// USED BY: auth.php (toggle on #Password)
//          password.php (toggle on #newpassword, #confirmnewpassword)
// =============================================================================

// ── Show / Hide password toggle ───────────────────────────────────────────────
// Guard with if (toggle) so this script doesn't throw an error if loaded on a
// page that doesn't have the Showpsw button
const toggle = document.getElementById("Showpsw");

if (toggle) {
    toggle.addEventListener("click", function () {
        // Target all password fields that may be on the current page
        const inputs = document.querySelectorAll("#newpassword, #confirmnewpassword, #psw, #Password");

        // Check if ANY of the fields are currently hidden — if so, show all of them
        const isHidden = [...inputs].some(input => input.type === "password");
        inputs.forEach(input => input.type = isHidden ? "text" : "password");

        // Update button label to reflect the new state
        toggle.textContent = isHidden ? "Hide" : "Show";
    });
}
