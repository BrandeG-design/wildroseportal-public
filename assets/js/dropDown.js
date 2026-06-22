// =============================================================================
// FILE: dropDown.js
// PURPOSE: Controls the "Other" device type text input on ticket.php.
//          When "Other" is selected from the device type dropdown, a free-text
//          input is shown and marked as required so staff must specify the type.
//          When any other option is selected, the input is hidden and its
//          required attribute is removed so it doesn't block form submission.
//
// USED BY: ticket.php
// =============================================================================

// ── toggleOtherType ───────────────────────────────────────────────────────────
// Called by the onchange attribute on the device type <select> element, and
// also on DOMContentLoaded to restore the correct state when session data
// pre-selects "Other" after the user navigates back from ticketsummary.php.
function toggleOtherType() {
  const select     = document.getElementById("type");
  const otherInput = document.getElementById("other_type");

  if (select.value === "Other") {
    // Show the free-text input and make it required
    otherInput.style.display = "inline-block";
    otherInput.required      = true;
  } else {
    // Hide the free-text input and remove required so it doesn't block submit
    otherInput.style.display = "none";
    otherInput.required      = false;
  }
}

// ── Restore state on page load ────────────────────────────────────────────────
// If the user navigated back and the session had "Other" as the device type,
// PHP will have pre-selected "Other" in the dropdown and pre-filled the
// other_type input. Running toggleOtherType() here ensures the input is
// visible and required to match that restored state.
window.addEventListener("DOMContentLoaded", function () {
  toggleOtherType();
});
