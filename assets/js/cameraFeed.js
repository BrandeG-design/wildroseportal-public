// =============================================================================
// FILE: cameraFeed.js
// PURPOSE: Extends the camera functionality on ticket.php. Wraps the existing
//          initCamera() function from cameraInit.js to show the camera UI
//          elements after the stream starts, and provides submitWithPhotos()
//          which validates the form and saves captured photos to sessionStorage
//          before submitting.
//
// DEPENDS ON: cameraInit.js (must be loaded first — defines window.initCamera)
//             photoArray.js (defines the `photos` array of captured frames)
// USED BY:    ticket.php
// =============================================================================

// ── Extend initCamera() ───────────────────────────────────────────────────────
// cameraInit.js defines window.initCamera() to start the camera stream.
// This wraps it to also show the video feed wrapper and camera button row
// once the stream is running, keeping all UI reveal logic in one place.
// The guard prevents a crash if cameraInit.js hasn't loaded yet.
const origInitCamera = window.initCamera;
if (typeof origInitCamera === "function") {
  window.initCamera = async function () {
    await origInitCamera(); // start the camera stream first
    document.getElementById("cameraFeedWrapper").style.display = "inline-block";
    document.getElementById("cameraBtns").style.display        = "flex";
  };
}

// ── submitWithPhotos ──────────────────────────────────────────────────────────
// Called by the Next button on ticket.php. Validates the peripherals field,
// saves captured photos (with their labels) to sessionStorage so they survive
// the navigation to ticketsummary.php, then submits the form.
//
// requestSubmit() is used instead of submit() so that native HTML5 validation
// (required fields, patterns) still runs before the form is sent.
function submitWithPhotos() {

  // ── Peripherals validation ────────────────────────────────────────────────
  // The peripherals textarea uses a "no peripherals" checkbox to opt out.
  // If the checkbox is unchecked but the field is empty, show a custom
  // validation message and stop submission.
  const noPeriphBox = document.getElementById("no-peripherals");
  const periphArea  = document.getElementById("peripherals");

  if (
    noPeriphBox &&
    periphArea &&
    !noPeriphBox.checked &&
    periphArea.value.trim() === ""
  ) {
    periphArea.setCustomValidity(
      "Please list any peripherals, or check 'No peripherals with this device'.",
    );
    periphArea.reportValidity(); // shows the browser's native validation popup
    return;
  }

  // Clear any previously set custom validity so the field doesn't stay invalid
  if (periphArea) periphArea.setCustomValidity("");

  // ── Save photos to sessionStorage ─────────────────────────────────────────
  // Converts the `photos` array from photoArray.js into {dataUrl, label}
  // objects and stores them as JSON. ticketsummary.php reads this to display
  // the photos on the summary page, and the hidden photosData input sends them
  // to the server on final submission.
  const data =
    typeof photos !== "undefined"
      ? photos.map((p) => ({ dataUrl: p.dataUrl, label: p.label || "" }))
      : [];
  sessionStorage.setItem("devicePhotos", JSON.stringify(data));

  // Submit the form — requestSubmit() triggers HTML5 validation unlike submit()
  document.getElementById("ticketForm").requestSubmit();
}
