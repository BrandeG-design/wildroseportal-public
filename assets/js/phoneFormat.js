// =============================================================================
// FILE: phoneFormat.js
// PURPOSE: Auto-formats the phone number field on customer.php into the
//          ###-###-#### pattern as the user types. Strips non-numeric characters
//          and limits input to 10 digits so the result always matches the
//          pattern attribute required for form validation.
//
// USED BY: customer.php
// =============================================================================

document.getElementById("phone").addEventListener("input", function (phoneEvt) {
  // Strip everything that isn't a digit, then cap at 10 digits
  let digits = phoneEvt.target.value.replace(/\D/g, "").slice(0, 10);

  // Insert dashes progressively as more digits are entered:
  // 7+ digits → ###-###-####
  // 4-6 digits → ###-###
  // 0-3 digits → ### (no dash yet)
  if (digits.length >= 7) {
    phoneEvt.target.value =
      digits.slice(0, 3) + "-" + digits.slice(3, 6) + "-" + digits.slice(6, 10);
  } else if (digits.length >= 4) {
    phoneEvt.target.value = digits.slice(0, 3) + "-" + digits.slice(3);
  } else {
    phoneEvt.target.value = digits;
  }
});
