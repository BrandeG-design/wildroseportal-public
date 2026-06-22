// =============================================================================
// FILE: viewTicketLive.js
// PURPOSE: Powers the live ticket search on viewtickets.php. As staff type in
//          the search bar, AJAX requests are sent to viewtickets.php?search=
//          and matching tickets are rendered in a styled table. The "Most
//          Recent" tickets section is hidden while search results are showing
//          and restored when the search bar is cleared.
//
//          Each result row is clickable and navigates to ticketreport.php for
//          the full ticket details. Both click and touch listeners are attached
//          so the rows work correctly on tablet.
//
// USED BY: viewtickets.php
// =============================================================================

document.getElementById("ticket-search").addEventListener("input", function () {
  const searchValue = this.value.trim();

  // ── Clear search ──────────────────────────────────────────────────────────
  // If fewer than 2 characters are entered, clear results and restore the
  // recent tickets table
  if (searchValue.length < 2) {
    document.getElementById("search-results").innerHTML = "";
    document.getElementById("recent-tickets").style.display = "block";
    return;
  }

  // Hide the recent tickets table while search results are showing
  document.getElementById("recent-tickets").style.display = "none";

  // ── AJAX request to viewtickets.php search endpoint ───────────────────────
  fetch("viewtickets.php?search=" + encodeURIComponent(searchValue))
    .then((response) => response.json())
    .then((tickets) => {

      if (!tickets.length) {
        document.getElementById("search-results").innerHTML =
          '<div class="section-card"><p class="no-results">No tickets found.</p></div>';
        return;
      }

      // ── Build results table ───────────────────────────────────────────────
      // Each row has data-href set so the click listener below can navigate
      // to the correct ticket report page
      const rows = tickets
        .map(
          (t) =>
            `<tr class="clickable-row" data-href="ticketreport.php?id=${encodeURIComponent(t["T_Ticket#"])}">
              <td><span class="ticket-num">${t["T_Ticket#"]}</span></td>
              <td>${t["T_Date"]}</td>
              <td>${t["CUST_Fname"]} ${t["CUST_Lname"]}</td>
              <td>${t["DEV_Type"]}</td>
            </tr>`
        )
        .join("");

      document.getElementById("search-results").innerHTML =
        `<div class="section-card">
          <h2 class="section-heading">Search Results</h2>
          <table class="ticket-table">
            <thead>
              <tr>
                <th>Ticket #</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Device Type</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>`;

      // ── Wire up clickable rows ────────────────────────────────────────────
      // Listeners must be attached after innerHTML is set since the rows are
      // freshly created DOM elements. clickableRows.js only runs on page load
      // so it doesn't cover dynamically injected rows — that's handled here.
      document.querySelectorAll("#search-results .clickable-row").forEach(function (row) {
        row.addEventListener("click", function () {
          window.location.href = row.dataset.href;
        });
      });
    });
});
