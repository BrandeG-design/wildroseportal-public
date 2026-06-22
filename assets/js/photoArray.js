// =============================================================================
// FILE: photoArray.js
// PURPOSE: Legacy submit handler for ticket.php. Saves captured photo data URLs
//          to sessionStorage and submits the form using requestSubmit() so
//          native HTML5 validation still runs.
//
// NOTE:    cameraFeed.js defines its own submitWithPhotos() which supersedes
//          this one and also saves photo labels alongside data URLs. If both
//          files are loaded, cameraFeed.js should be loaded after this one so
//          its version of submitWithPhotos() takes precedence.
//
// USED BY: ticket.php
// =============================================================================

// ── submitWithPhotos ──────────────────────────────────────────────────────────
// Reads the `photos` array from cameraInit.js, extracts the data URLs, saves
// them to sessionStorage so ticketsummary.php can display them, then submits
// the form. Falls back to an empty array if no photos were captured.
//
// requestSubmit() is used instead of submit() so that required field validation
// and pattern checks still fire before the form is sent to the server.
function submitWithPhotos() {
  const dataUrls =
    typeof photos !== "undefined" ? photos.map((p) => p.dataUrl) : [];
  sessionStorage.setItem("devicePhotos", JSON.stringify(dataUrls));

  document.getElementById("ticketForm").requestSubmit();
}
