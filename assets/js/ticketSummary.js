// =============================================================================
// FILE: ticketSummary.js
// PURPOSE: Manages all interactive behaviour on ticketsummary.php:
//            - Signature canvas drawing (mouse and touch)
//            - Restoring a saved signature when the user navigates back
//            - Clearing the signature canvas
//            - Dymo label printing
//            - Final ticket submission (print receipt then submit form)
//            - Navigating back while preserving the signature in sessionStorage
//            - Rendering device photos from sessionStorage into the page
//
// DEPENDS ON: printLabel.js (defines printDymoLabel — must be loaded first)
//             labelAmount.js (manages the label quantity counter)
// USED BY:    ticketsummary.php
// =============================================================================

// ── Module-level canvas references ───────────────────────────────────────────
// Stored at module scope so all functions below can access the canvas and
// context without re-querying the DOM on every call
const _sigCanvas  = document.getElementById("sigCanvas");
const _sigCtx     = _sigCanvas.getContext("2d");
let   _sigDrawing = false; // tracks whether the user is actively drawing

// ── Print Label ───────────────────────────────────────────────────────────────
// Reads customer name and device info from data attributes set by PHP on the
// button element and sends them to the Dymo printer via printDymoLabel()
document.getElementById("printLabelBtn").addEventListener("click", function () {
  printDymoLabel({
    first_name:  this.dataset.fname,
    last_name:   this.dataset.lname,
    device:      this.dataset.device || "",
    label_count: parseInt(document.getElementById("labelAmount").value) || 1,
  });
});

// ── Restore signature on Back navigation ─────────────────────────────────────
// If the user tapped Back from this page, saveSignatureAndGoBack() saved their
// signature to sessionStorage. Restore it here so they don't have to re-sign.
// The saved data is removed immediately after restoring so it doesn't persist
// beyond this navigation.
(function restoreSignature() {
  const saved = sessionStorage.getItem("ticketSignature");
  if (!saved) return;
  const img    = new Image();
  img.onload   = () => _sigCtx.drawImage(img, 0, 0);
  img.src      = saved;
  sessionStorage.removeItem("ticketSignature");
})();

// ── Canvas drawing helpers ────────────────────────────────────────────────────
function _sigBeginAt(x, y) {
  _sigCtx.beginPath();
  _sigCtx.moveTo(x, y);
}

function _sigDrawTo(x, y) {
  _sigCtx.lineWidth   = 2;
  _sigCtx.lineCap     = "round";
  _sigCtx.strokeStyle = "#000";
  _sigCtx.lineTo(x, y);
  _sigCtx.stroke();
  // Begin a new path after each segment so lineCap applies to every stroke end
  _sigCtx.beginPath();
  _sigCtx.moveTo(x, y);
}

// Converts a touch event coordinate to canvas-relative x/y position
function _sigTouchPos(e) {
  const rect  = _sigCanvas.getBoundingClientRect();
  const touch = e.touches[0];
  return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
}

// ── Mouse drawing listeners ───────────────────────────────────────────────────
_sigCanvas.addEventListener("mousedown",  (e) => { _sigDrawing = true;  _sigBeginAt(e.offsetX, e.offsetY); });
_sigCanvas.addEventListener("mouseup",    ()  => { _sigDrawing = false; _sigCtx.beginPath(); });
_sigCanvas.addEventListener("mouseleave", ()  => { _sigDrawing = false; _sigCtx.beginPath(); });
_sigCanvas.addEventListener("mousemove",  (e) => { if (_sigDrawing) _sigDrawTo(e.offsetX, e.offsetY); });

// ── Touch drawing listeners ───────────────────────────────────────────────────
// { passive: false } is required so e.preventDefault() can block the default
// touch scroll behaviour while the customer is signing
_sigCanvas.addEventListener("touchstart", (e) => {
  e.preventDefault();
  _sigDrawing = true;
  const pt = _sigTouchPos(e);
  _sigBeginAt(pt.x, pt.y);
}, { passive: false });

_sigCanvas.addEventListener("touchend", (e) => {
  e.preventDefault();
  _sigDrawing = false;
  _sigCtx.beginPath();
}, { passive: false });

_sigCanvas.addEventListener("touchmove", (e) => {
  e.preventDefault();
  if (!_sigDrawing) return;
  const pt = _sigTouchPos(e);
  _sigDrawTo(pt.x, pt.y);
}, { passive: false });

// ── Clear signature button ────────────────────────────────────────────────────
document.getElementById("clearSig").onclick = () =>
  _sigCtx.clearRect(0, 0, _sigCanvas.width, _sigCanvas.height);

// ── saveSignatureAndGoBack ────────────────────────────────────────────────────
// Called by the Back button. Saves the signature to sessionStorage (if ink is
// present) so it can be restored when the page is returned to, then navigates
// back to ticket.php.
window.saveSignatureAndGoBack = function () {
  const px     = _sigCtx.getImageData(0, 0, _sigCanvas.width, _sigCanvas.height).data;
  const hasInk = px.some((v, i) => i % 4 === 3 && v > 0); // check alpha channel for any drawn pixels

  if (hasInk) {
    sessionStorage.setItem("ticketSignature", _sigCanvas.toDataURL("image/png"));
  }
  window.location.href = "https://customer.altismsp.com/public/ticket.php";
};

// ── showToast ─────────────────────────────────────────────────────────────────
// Injects a non-blocking toast notification into the page for a given duration.
// Non-blocking so it does not interfere with window.print() unlike alert().
//
// @param {string} message   Text to display in the toast
// @param {number} duration  How long (ms) before the toast fades out (default 4000)
function showToast(message, duration) {
  duration = duration || 4000;

  const toast = document.createElement("div");
  toast.textContent = message;
  toast.style.cssText = [
    "position:fixed",
    "bottom:2rem",
    "left:50%",
    "transform:translateX(-50%)",
    "background:#1a7a4a",
    "color:#fff",
    "padding:0.85rem 1.6rem",
    "border-radius:8px",
    "font-family:Verdana,sans-serif",
    "font-size:1rem",
    "font-weight:600",
    "box-shadow:0 4px 16px rgba(0,0,0,0.18)",
    "z-index:99999",
    "opacity:1",
    "transition:opacity 0.5s ease",
    "text-align:center",
    "max-width:90vw",
  ].join(";");

  document.body.appendChild(toast);

  // Begin fade-out before removing from DOM
  setTimeout(function () {
    toast.style.opacity = "0";
    setTimeout(function () {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 500);
  }, duration);
}

// ── submitFinal ───────────────────────────────────────────────────────────────
// Called by the Submit Ticket button. Validates the signature, populates the
// hidden form inputs, clears sessionStorage, triggers the print dialog, and
// submits the form after the dialog is closed.
//
// window.print() is called last with nothing blocking it so Chrome does not
// suppress the dialog. The toast and form submission are handled after print
// via onafterprint, with a fallback setTimeout in case onafterprint does not
// fire (which can happen in Chrome when the user cancels the dialog).
window.submitFinal = function () {
  const px     = _sigCtx.getImageData(0, 0, _sigCanvas.width, _sigCanvas.height).data;
  const hasInk = px.some((v, i) => i % 4 === 3 && v > 0);

  if (!hasInk && !confirm("No signature detected. Submit anyway?")) return;

  // Populate the signature hidden input with the canvas PNG data URI
  document.getElementById("signatureInput").value = _sigCanvas.toDataURL("image/png");

  // Send full {dataUrl, label} objects so photo labels are saved to the DB
  const raw    = sessionStorage.getItem("devicePhotos") || "[]";
  const stored = JSON.parse(raw);
  document.getElementById("photosDataInput").value = JSON.stringify(stored);

  // Clear all ticket state from sessionStorage — the workflow is complete
  sessionStorage.removeItem("devicePhotos");
  sessionStorage.removeItem("ticketSignature");

  // ── Post-print: show toast then submit ────────────────────────────────────
  // Wrapped in a named function so both onafterprint and the fallback timeout
  // share the same logic without risk of the form submitting twice.
  let submitted = false;
  function afterPrint() {
    if (submitted) return;
    submitted = true;
    window.onafterprint = null;
    showToast("Ticket submitted — please return the tablet to staff.");
    // Short delay so the toast is visible before the page navigates away
    setTimeout(function () {
      document.getElementById("finalForm").submit();
    }, 1500);
  }

  // onafterprint fires when the print dialog is closed (print or cancel)
  window.onafterprint = afterPrint;

  // Fallback: if onafterprint never fires (Chrome edge case on cancel),
  // submit after a generous timeout that covers time spent in the dialog
  setTimeout(afterPrint, 30000);

  // Call print last — nothing above is blocking so Chrome opens the dialog
  window.print();
};

// ── Render photos from sessionStorage ────────────────────────────────────────
// Photos were saved to sessionStorage by cameraFeed.js on the previous page.
// Render them into the photo display section so the customer can review them
// before signing. Supports both plain data URL strings (legacy) and
// {dataUrl, label} objects (current format).
(function renderPhotos() {
  const raw    = sessionStorage.getItem("devicePhotos");
  const stored = raw ? JSON.parse(raw) : [];
  if (!stored.length) return;

  const div     = document.getElementById("photo-display");
  div.innerHTML = "";

  stored.forEach((item, i) => {
    const dataUrl = typeof item === "string" ? item : item.dataUrl;
    const label   = typeof item === "object" ? item.label || "" : "";

    const wrapper       = document.createElement("div");
    wrapper.className   = "photo-thumb-wrapper";

    const img   = document.createElement("img");
    img.src     = dataUrl;
    img.alt     = "Device photo " + (i + 1);
    wrapper.appendChild(img);

    // Only render the caption element if a label was provided
    if (label) {
      const cap           = document.createElement("span");
      cap.className       = "photo-label-cap";
      cap.textContent     = label;
      wrapper.appendChild(cap);
    }

    div.appendChild(wrapper);
  });

  // Update the section heading to show the photo count
  const heading = document.getElementById("photos-heading");
  if (heading) heading.textContent = "Device Photos (" + stored.length + ")";
})();
