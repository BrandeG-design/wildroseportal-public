// =============================================================================
// FILE: liveSearch.js
// PURPOSE: Powers the existing customer live search on index.php. As staff type
//          in the search field, AJAX requests are sent to index.php?search=
//          and matching customers are displayed in a styled table. Clicking or
//          tapping a result row selects that customer and submits their ID to
//          start the check-in workflow with their details pre-filled.
//
//          Phone number input is automatically reformatted to ###-###-#### format
//          before searching so partial phone numbers match stored records.
//
// USED BY: index.php
// =============================================================================

document
  .getElementById("customer-search")
  .addEventListener("input", function () {
    const raw    = this.value.trim();
    const digits = raw.replace(/\D/g, ""); // strip non-digits to check if input looks like a phone number

    // ── Phone number reformatting ─────────────────────────────────────────────
    // If the input contains enough digits to be a phone number, reformat it to
    // ###-###-#### so the LIKE query matches how numbers are stored in the DB.
    // e.g. typing "4031234" becomes "403-123-4" which matches "403-123-4567"
    let searchValue;
    if (digits.length >= 7) {
      searchValue = digits.slice(0, 3) + "-" + digits.slice(3, 6) + "-" + digits.slice(6, 10);
    } else if (digits.length >= 4) {
      searchValue = digits.slice(0, 3) + "-" + digits.slice(3);
    } else {
      searchValue = raw; // not enough digits — use the raw input as-is
    }

    // Don't search until at least 2 characters have been entered
    if (searchValue.length < 2) {
      document.getElementById("search-results").innerHTML = "";
      return;
    }

    // ── AJAX request to index.php search endpoint ─────────────────────────────
    fetch("index.php?search=" + encodeURIComponent(searchValue))
      .then((response) => response.json())
      .then((customers) => {
        const resultsDiv = document.getElementById("search-results");

        if (!customers.length) {
          resultsDiv.innerHTML = '<p class="no-results">No customers found.</p>';
          return;
        }

        // ── Build results table ───────────────────────────────────────────────
        const rows = customers
          .map(
            (customer) =>
              `<tr class="clickable-row">
                <td>${customer.CUST_Fname} ${customer.CUST_Lname}</td>
                <td>${customer.CUST_Phone ?? "—"}</td>
                <td>${customer.CUST_Email ?? "—"}</td>
              </tr>`
          )
          .join("");

        resultsDiv.innerHTML =
          `<table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>`;

        // ── Wire up row selection ─────────────────────────────────────────────
        // Both click and touchend listeners are needed:
        // - click handles mouse interaction on desktop
        // - touchend with preventDefault() fixes tap issues on tablet where
        //   a touch can fire both touchend and a delayed synthetic click,
        //   which can cause double-submission or navigation issues
        resultsDiv.querySelectorAll(".clickable-row").forEach((row, index) => {
          const custId = customers[index].CUST_ID;

          row.addEventListener("click", () => selectCustomer(custId));

          row.addEventListener("touchend", (e) => {
            e.preventDefault(); // prevent the delayed synthetic click on touch devices
            selectCustomer(custId);
          });
        });
      });
  });

// ── selectCustomer ────────────────────────────────────────────────────────────
// Stores the selected customer's ID in the hidden form input and submits it
// to index.php, which loads the customer's data into the session and redirects
// to customer.php with their fields pre-filled.
function selectCustomer(customerId) {
  document.getElementById("fcust-id").value = customerId;
  document.getElementById("select-form").submit();
}
