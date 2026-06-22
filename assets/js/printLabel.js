// =============================================================================
// FILE: printLabel.js
// PURPOSE: Prints Dymo address labels using the browser's native print dialog
//          rather than the Dymo SDK or Connect software. The label content is
//          written into a hidden iframe and printed via the iframe's print()
//          method, keeping the main page unaffected.
//
//          The DYMO LabelWriter 550 Turbo must be installed as a regular OS
//          printer. The browser remembers print settings per origin after the
//          first use, so setup only needs to happen once per machine.
//
// FIRST-TIME SETUP (once per browser/machine):
//   1. Click Print Label — the browser print dialog opens.
//   2. Select "DYMO LabelWriter 550 Turbo" as the destination.
//   3. Under "More settings" set:
//        Paper size   → 30252 Address  (or 3.5" × 1.125")
//        Margins      → None
//        Headers/Footers → off
//   4. The browser remembers these settings for this origin going forward.
//
// USED BY: ticketsummary.php, ticketreport.php (via ticketReport.js)
// =============================================================================

// ── printDymoLabel ────────────────────────────────────────────────────────────
// Generates label HTML for the given number of copies and sends it to the
// Dymo printer via a hidden iframe's print dialog.
//
// @param {object} label
//   @param {string} label.first_name   Customer first name
//   @param {string} label.last_name    Customer last name
//   @param {string} label.device       Device description (brand + model or type)
//   @param {number} label.label_count  Number of copies to print (min 1)
function printDymoLabel(label) {
  const firstName = (label.first_name || "").trim();
  const lastName  = (label.last_name  || "").trim();
  const device    = (label.device     || "").trim();
  const count     = Math.max(1, parseInt(label.label_count) || 1);

  const custName = [firstName, lastName].filter(Boolean).join(" ");

  // Format today's date for display on the label e.g. "Apr 14, 2026"
  const today = new Date().toLocaleDateString("en-US", {
    year:  "numeric",
    month: "short",
    day:   "numeric",
  });

  // ── HTML escape helper ────────────────────────────────────────────────────
  // Escapes special characters so customer names and device strings with
  // ampersands or angle brackets don't break the label HTML
  function esc(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  // ── Build label copies ────────────────────────────────────────────────────
  // Each copy is a <div class="label"> with page-break-after:always so the
  // printer advances to the next label after each one
  let copies = "";
  for (let i = 0; i < count; i++) {
    copies += `
    <div class="label">
      <div class="row name">${esc(custName)}</div>
      <div class="row device">${esc(device)}</div>
      <div class="row date">${esc(today)}</div>
    </div>`;
  }

  // ── Label HTML ────────────────────────────────────────────────────────────
  // @page size matches the Dymo 30252 Address label (3.5" × 1.125")
  // Margins are set to 0 — the Dymo driver handles physical margins itself
  const html = `<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page {
    size: 3.5in 1.125in;
    margin: 0;
  }

  html, body { margin: 0; padding: 0; }

  .label {
    width: 3.5in;
    height: 1.125in;
    padding: 0.06in 0.1in;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    font-family: 'Segoe UI', Arial, sans-serif;
    overflow: hidden;
    page-break-after: always;
  }

  .row {
    width: 100%;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* Customer name — largest text on the label */
  .name   { font-size: 16pt; font-weight: 700; line-height: 1.15; }

  /* Device description */
  .device { font-size: 11pt; font-weight: 400; line-height: 1.2; margin-top: 0.02in; }

  /* Date — smallest text, slightly muted */
  .date   { font-size: 9pt;  font-weight: 400; line-height: 1.2; margin-top: 0.02in; color: #333; }
</style>
</head>
<body>${copies}</body>
</html>`;

  // ── Reuse or create the hidden print iframe ───────────────────────────────
  // The iframe is positioned off-screen so it doesn't affect page layout.
  // Reusing the same iframe avoids accumulating DOM nodes on repeated prints.
  let frame = document.getElementById("_labelPrintFrame");
  if (!frame) {
    frame = document.createElement("iframe");
    frame.id = "_labelPrintFrame";
    frame.style.cssText =
      "position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;border:none;visibility:hidden;";
    document.body.appendChild(frame);
  }

  // Write the label HTML into the iframe and trigger print
  const doc = frame.contentDocument || frame.contentWindow.document;
  doc.open();
  doc.write(html);
  doc.close();

  // 350ms delay gives the iframe time to finish rendering before print() is called
  // — calling it immediately can result in a blank label on some browsers
  setTimeout(() => {
    frame.contentWindow.focus();
    frame.contentWindow.print();
  }, 350);
}
