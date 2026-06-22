// =============================================================================
// FILE: nameValid.js
// PURPOSE: Strips digits from the first and last name fields on customer.php
//          in real time as the user types. This prevents numbers from being
//          entered into name fields without blocking the field entirely.
//
//          Cursor position is restored after the value is cleaned so the
//          field doesn't jump to the end when a digit is removed mid-word.
//
// USED BY: customer.php
// =============================================================================

document.addEventListener("DOMContentLoaded", function () {
  ["fname", "lname"].forEach(function (id) {
    const input = document.getElementById(id);

    // Guard in case one of the fields doesn't exist on the current page
    if (!input) return;

    input.addEventListener("input", function () {
      // Record cursor position before modifying the value
      let pos     = this.selectionStart;
      let cleaned = this.value.replace(/[0-9]/g, ""); // remove all digits

      if (cleaned !== this.value) {
        this.value = cleaned;
        // Restore cursor to one position before the removed digit so the
        // user's position in the field feels natural after the strip
        this.setSelectionRange(pos - 1, pos - 1);
      }
    });
  });
});
