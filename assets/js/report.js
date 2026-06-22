// =============================================================================
// FILE: report.js
// PURPOSE: Client-side logic for report.php. Provides selectAll() and
//          clearAll() helpers for the column checkboxes, and validates the
//          report form before submission to catch errors that the server-side
//          validation in report.php also checks — this gives faster feedback
//          without a page reload.
//
//          Also auto-scrolls to the results section after a report is generated
//          so staff don't have to scroll down manually on longer screens.
//
// USED BY: report.php
// =============================================================================

// ── selectAll ─────────────────────────────────────────────────────────────────
// Checks all column checkboxes. Called by the "Select All" button in the
// Columns to Include section.
function selectAll() {
  document
    .querySelectorAll('input[name="columns[]"]')
    .forEach((cb) => (cb.checked = true));
}

// ── clearAll ──────────────────────────────────────────────────────────────────
// Unchecks all column checkboxes. Called by the "Clear All" button in the
// Columns to Include section.
function clearAll() {
  document
    .querySelectorAll('input[name="columns[]"]')
    .forEach((cb) => (cb.checked = false));
}

document.addEventListener("DOMContentLoaded", function () {

  // ── Client-side form validation ───────────────────────────────────────────
  // These checks mirror the server-side validation in report.php. Running them
  // here gives immediate feedback without a full page reload on failure.
  const form = document.getElementById("report-form");

  if (form) {
    form.addEventListener("submit", function (e) {
      const start   = document.getElementById("start_date").value;
      const end     = document.getElementById("end_date").value;
      const checked = document.querySelectorAll('input[name="columns[]"]:checked');

      // Prevent end date from being earlier than start date
      if (start && end && start > end) {
        e.preventDefault();
        alert("The start date cannot be later than the end date.");
        document.getElementById("start_date").focus();
        return;
      }

      // At least one column must be selected or the report table has no data
      if (checked.length === 0) {
        e.preventDefault();
        alert("Please select at least one column to include in the report.");
        return;
      }

      // At least one device type must be checked — an empty selection would
      // return no results and is likely a mistake rather than intentional
      const checkedTypes = document.querySelectorAll('input[name="dev_types[]"]:checked');
      if (checkedTypes.length === 0) {
        e.preventDefault();
        alert(
          "Please select at least one device type, or leave all checked to include everything.",
        );
      }
    });
  }

  // ── Auto-scroll to results ────────────────────────────────────────────────
  // If the report results section exists (i.e. a report was just generated),
  // scroll it into view so staff don't have to scroll past the form manually.
  // This runs on every page load so it fires after the POST redirect renders results.
  const results = document.getElementById("report-results");
  if (results) {
    results.scrollIntoView({ behavior: "smooth", block: "start" });
  }

});
