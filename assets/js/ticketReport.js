// =============================================================================
// FILE: ticketReport.js
// PURPOSE: Wires up the Print Label button on ticketreport.php. Reads the
//          customer name and device description from data attributes set by PHP
//          on the button element, and passes them to printDymoLabel() along
//          with the current quantity from the label amount counter.
//
// DEPENDS ON: printLabel.js (defines printDymoLabel — must be loaded first)
//             labelAmount.js (manages the #labelAmount input value)
// USED BY:    ticketreport.php
// =============================================================================

// The button's data-fname, data-lname, and data-device attributes are set by
// PHP in ticketreport.php so the customer and device info doesn't need to be
// passed through JavaScript separately
document.getElementById("printLabelBtn").addEventListener("click", function () {
  printDymoLabel({
    first_name:  this.dataset.fname,
    last_name:   this.dataset.lname,
    device:      this.dataset.device || "", // empty string if no device info available
    label_count: parseInt(document.getElementById("labelAmount").value) || 1,
  });
});
