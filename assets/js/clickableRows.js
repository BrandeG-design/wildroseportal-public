// =============================================================================
// FILE: clickableRows.js
// PURPOSE: Makes table rows with the class "clickable-row" navigate to the URL
//          stored in their data-href attribute when clicked. Used on viewtickets.php
//          to make the entire ticket row clickable rather than just a link in one cell.
//
//          The data-href attribute is set in PHP when rendering each row:
//          <tr class="clickable-row" data-href="ticketreport.php?id=42">
//
// USED BY: viewtickets.php
//          viewTicketLive.js also applies this behaviour to dynamically rendered
//          search result rows directly after injecting them into the DOM.
// =============================================================================

// Attach click listeners to all clickable rows present on page load.
// Rows injected later by viewTicketLive.js get their own listeners applied
// directly in that file since this script only runs once at load time.
document.querySelectorAll(".clickable-row").forEach(function (row) {
  row.addEventListener("click", function () {
    window.location.href = row.dataset.href;
  });
});
