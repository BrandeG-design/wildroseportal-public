// =============================================================================
// FILE: custFormat.js
// PURPOSE: Provides real-time input validation for the customer contact form
//          on customer.php. Blocks non-letter keypresses on name fields and
//          validates email format when the field loses focus.
//
// USED BY: customer.php
// =============================================================================

// ── Name field validation ─────────────────────────────────────────────────────
// Blocks any keypress that isn't a letter (A-Z, a-z) on the first and last
// name fields. Note: this is a keypress guard — the pattern attribute on the
// input handles full validation on form submit. This just provides immediate
// feedback while typing.
function lettersOnly(cName) {
  if (!/^[A-Za-z]$/.test(e.key)) {
    cName.preventDefault();
  }
}

document.getElementById("fname").addEventListener("keypress", lettersOnly);
document.getElementById("lname").addEventListener("keypress", lettersOnly);

// ── Email format validation ───────────────────────────────────────────────────
// Validates the email field when the user leaves it (blur event).
// Uses setCustomValidity() so the browser's native validation popup shows
// a helpful message rather than a generic "invalid" error.
// Email is optional on this form — only validated if a value was entered.
document.getElementById("email").addEventListener("blur", function () {
  const emailVal     = this.value.trim();
  const emailPattern = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/;

  if (emailVal !== "" && !emailPattern.test(emailVal)) {
    this.setCustomValidity(
      "Please enter a valid email address (e.g. example@email.com).",
    );
    this.reportValidity(); // show the browser's validation popup immediately
  } else {
    this.setCustomValidity(""); // clear any previous error
  }
});

// ── Clear email error on re-entry ─────────────────────────────────────────────
// Clears the custom validity message as soon as the user starts typing again
// so the error doesn't persist while they are correcting their input.
document.getElementById("email").addEventListener("input", function () {
  this.setCustomValidity("");
});
