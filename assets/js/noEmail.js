// =============================================================================
// FILE: noEmail.js
// PURPOSE: Handles the "No email" checkbox on customer.php. When checked,
//          the email field is disabled, cleared, and marked as not required.
//          When unchecked, the field is re-enabled and required again.
//
//          A hidden input (no-email-flag) is kept in sync with the checkbox
//          state so the PHP form handler can read it as "1" or "0" on submit,
//          since disabled fields are not included in form POST data.
//
// USED BY: customer.php
// =============================================================================

(function () {
  "use strict";

  const noEmailBox  = document.getElementById("no-email");
  const emailInput  = document.getElementById("email");
  const noEmailFlag = document.getElementById("no-email-flag"); // hidden input submitted with the form

  // Guard — exit silently if any element is missing
  if (!noEmailBox || !emailInput || !noEmailFlag) return;

  noEmailBox.addEventListener("change", function () {
    if (noEmailBox.checked) {
      // Disable and clear the email field — disabled inputs are excluded from
      // POST data, so the hidden flag is the only way PHP knows no email was given
      emailInput.required = false;
      emailInput.disabled = true;
      emailInput.value    = "";
      noEmailFlag.value   = "1";
    } else {
      // Re-enable and require the email field
      emailInput.required = true;
      emailInput.disabled = false;
      noEmailFlag.value   = "0";
    }
  });
})();
