// =============================================================================
// FILE: cameraInit.js
// PURPOSE: Manages the device camera on ticket.php. Handles starting, stopping,
//          and flipping the camera stream, capturing photos, displaying a
//          thumbnail strip with per-photo labels, and restoring photos from
//          sessionStorage when the user navigates back from ticketsummary.php.
//
//          Key functions exposed on window so cameraFeed.js can wrap them:
//            window.initCamera()   — starts the stream with the selected facing
//            window.stopCamera()   — stops the stream and shows the last preview
//            window.flipCamera()   — toggles front/rear and restarts the stream
//            window.capturePhoto() — captures a frame and adds it to the strip
//
// DEPENDS ON: ticket.php (expects #videoFeed, #canvas, #photoPreview, #thumbStrip,
//             #captureBtn, #flipBtn, #stopBtn, #idleMsg, #liveBadgeWrapper,
//             #statusText, #photoCount, #noPhotosMsg elements to exist)
// USED BY:    ticket.php (loaded before cameraFeed.js)
// =============================================================================

// ── Module-level state ────────────────────────────────────────────────────────
let stream     = null;       // active MediaStream — null when camera is off
let photos     = [];         // array of {dataUrl, blob, timestamp, label} objects
let facingMode = "environment"; // "environment" = rear camera, "user" = front

// Cache DOM references used across multiple functions
const video   = document.getElementById("videoFeed");
const canvas  = document.getElementById("canvas");
const preview = document.getElementById("photoPreview");

// ── getSelectedFacing ─────────────────────────────────────────────────────────
// Reads the currently checked radio button to determine which camera to use.
// Falls back to rear camera ("environment") if no radio is checked.
function getSelectedFacing() {
  const sel = document.querySelector('input[name="facing"]:checked');
  return sel ? sel.value : "environment";
}

// ── initCamera ────────────────────────────────────────────────────────────────
// Entry point called by the Start Camera button and wrapped by cameraFeed.js.
// Reads the selected facing mode then delegates to startStream().
window.initCamera = async function () {
  facingMode = getSelectedFacing();
  await startStream();
};

// ── startStream ───────────────────────────────────────────────────────────────
// Requests camera access and attaches the stream to the video element.
// If a stream is already running (e.g. flip was called), it is stopped first.
// Uses "ideal" rather than "exact" for facingMode so it degrades gracefully
// on devices with only one camera.
async function startStream() {
  // Stop any existing stream before starting a new one
  if (stream) stream.getTracks().forEach((t) => t.stop());

  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: facingMode } },
      audio: false,
    });

    video.srcObject = stream;
    video.style.display = "block";
    preview.style.display = "none";
    document.getElementById("idleMsg").style.display        = "none";
    document.getElementById("liveBadgeWrapper").style.display = "block";
    document.getElementById("statusText").textContent        = "Live";

    // Enable camera control buttons now that the stream is active
    document.getElementById("captureBtn").disabled = false;
    document.getElementById("flipBtn").disabled    = false;
    document.getElementById("stopBtn").disabled    = false;

  } catch (err) {
    // Camera permission denied or no camera available
    alert("Camera access denied or unavailable: " + err.message);
  }
}

// ── stopCamera ────────────────────────────────────────────────────────────────
// Stops all camera tracks, clears the video element, and resets the UI.
// If photos have been taken, shows the last captured photo as a preview.
// If no photos, shows the idle message instead.
window.stopCamera = function () {
  if (stream) {
    stream.getTracks().forEach((t) => t.stop());
    stream = null;
  }

  video.style.display = "none";
  video.srcObject     = null;
  document.getElementById("liveBadgeWrapper").style.display = "none";
  document.getElementById("statusText").textContent         = "Offline";

  // Disable camera control buttons while stream is off
  document.getElementById("captureBtn").disabled = true;
  document.getElementById("flipBtn").disabled    = true;
  document.getElementById("stopBtn").disabled    = true;

  // Show the most recent photo preview, or the idle message if none taken
  if (photos.length) showPreview(photos.length - 1);
  else document.getElementById("idleMsg").style.display = "block";
};

// ── flipCamera ────────────────────────────────────────────────────────────────
// Toggles between front and rear camera then restarts the stream.
// Also updates the radio button to keep the UI in sync with the actual state.
window.flipCamera = async function () {
  facingMode = facingMode === "environment" ? "user" : "environment";
  document.querySelector(`input[name="facing"][value="${facingMode}"]`).checked = true;
  await startStream();
};

// ── capturePhoto ──────────────────────────────────────────────────────────────
// Draws the current video frame onto the off-screen canvas, converts it to a
// JPEG data URL, and adds it to the photos array. Also creates a Blob for
// potential upload. Enforces a maximum of 8 photos per ticket.
window.capturePhoto = function () {
  if (!stream) return;

  if (photos.length >= 8) {
    alert("Maximum of 8 photos allowed.");
    return;
  }

  // Size the canvas to match the actual video resolution
  canvas.width  = video.videoWidth  || 640;
  canvas.height = video.videoHeight || 480;
  canvas.getContext("2d").drawImage(video, 0, 0);

  // 0.92 quality gives a good balance between file size and image clarity
  const dataUrl   = canvas.toDataURL("image/jpeg", 0.92);
  const timestamp = new Date().toISOString();

  canvas.toBlob(
    (blob) => {
      photos.push({ dataUrl, blob, timestamp, label: "" });
      addThumb(photos.length - 1); // add thumbnail to the strip
      showPreview(photos.length - 1); // show this photo as the preview
      updateCount();
    },
    "image/jpeg",
    0.92,
  );
};

// ── addThumb ──────────────────────────────────────────────────────────────────
// Builds a thumbnail widget for one photo and appends it to the thumb strip.
// Each thumbnail has: a clickable preview image, a label text input, and a
// delete button. The label value is kept in sync with photos[idx].label so it
// is saved to the database when the ticket is submitted.
function addThumb(idx) {
  document.getElementById("noPhotosMsg").style.display = "none";

  const strip   = document.getElementById("thumbStrip");
  const wrapper = document.createElement("span");
  wrapper.dataset.idx = idx;
  wrapper.style.cssText =
    "display:inline-flex; flex-direction:column; align-items:center; gap:4px; margin-right:8px; vertical-align:top;";

  // Thumbnail image — clicking shows this photo in the main preview area
  const img       = document.createElement("img");
  img.src         = photos[idx].dataUrl;
  img.width       = 60;
  img.height      = 60;
  img.style.objectFit   = "cover";
  img.style.cursor      = "pointer";
  img.style.borderRadius = "4px";
  img.style.border      = "1px solid #d0dcea";
  img.onclick = () => showPreview(idx);

  // Label input — stored in photos[idx].label and saved to DP_Label in the DB
  const labelInput       = document.createElement("input");
  labelInput.type        = "text";
  labelInput.placeholder = "Label…";
  labelInput.value       = photos[idx].label || "";
  labelInput.maxLength   = 40;
  labelInput.style.cssText =
    "width:64px; font-size:0.7em; padding:2px 4px; border:1px solid #d0dcea; border-radius:4px; text-align:center; font-family:Verdana,sans-serif; color:#1a1a1a;";
  labelInput.addEventListener("input", () => {
    photos[idx].label = labelInput.value; // keep photos array in sync
  });

  // Delete button — removes this photo from the array and rebuilds the strip
  const del       = document.createElement("button");
  del.type        = "button";
  del.textContent = "✕";
  del.title       = "Remove photo";
  del.style.cssText =
    "font-size:0.65em; cursor:pointer; border:1px solid #d0dcea; background:#fff; border-radius:3px; padding:1px 5px; color:#b00020; line-height:1.4;";
  del.onclick = () => removePhoto(idx);

  wrapper.appendChild(img);
  wrapper.appendChild(labelInput);
  wrapper.appendChild(del);
  strip.appendChild(wrapper);
}

// ── showPreview ───────────────────────────────────────────────────────────────
// Shows a captured photo in the main preview area. Hides the live video feed
// while the preview is shown so they don't overlap.
function showPreview(idx) {
  if (idx < 0 || idx >= photos.length) return;
  preview.src           = photos[idx].dataUrl;
  preview.style.display = "block";
  if (stream) video.style.display = "none"; // hide live feed while previewing
}

// ── removePhoto ───────────────────────────────────────────────────────────────
// Removes a photo from the array by index and rebuilds the entire thumbnail
// strip to ensure all indices and delete buttons remain in sync.
// After deletion, shows the next available photo or restores the camera/idle state.
function removePhoto(idx) {
  photos.splice(idx, 1);
  rebuildThumbs();

  if (!photos.length) {
    preview.style.display = "none";
    // If camera is still running, show the live feed; otherwise show idle message
    if (stream) video.style.display = "block";
    else document.getElementById("idleMsg").style.display = "block";
  } else {
    // Show the photo at the same position, or the last one if idx was the last
    showPreview(Math.min(idx, photos.length - 1));
  }

  updateCount();
}

// ── rebuildThumbs ─────────────────────────────────────────────────────────────
// Clears and recreates the entire thumbnail strip from the photos array.
// Called after a photo is deleted so all indices are correct and consistent.
function rebuildThumbs() {
  const strip   = document.getElementById("thumbStrip");
  strip.innerHTML = '<em id="noPhotosMsg">No photos yet.</em>';
  if (!photos.length) return;

  document.getElementById("noPhotosMsg").style.display = "none";
  photos.forEach((_, i) => addThumb(i));
}

// ── updateCount ───────────────────────────────────────────────────────────────
// Updates the photo count display in the "Captured Photos (N)" label.
function updateCount() {
  document.getElementById("photoCount").textContent = photos.length;
}

// ── Restore photos on Back navigation ────────────────────────────────────────
// If the user navigated back from ticketsummary.php, their photos are still in
// sessionStorage (saved by cameraFeed.js on Next). Restore them into the photos
// array and rebuild the thumbnail strip so nothing is lost.
// blob is set to null for restored photos since the raw binary is not stored in
// sessionStorage — only the dataUrl and label are needed for DB submission.
document.addEventListener("DOMContentLoaded", function () {
  const raw = sessionStorage.getItem("devicePhotos");
  if (!raw) return;

  let stored;
  try {
    stored = JSON.parse(raw);
  } catch (e) {
    return; // invalid JSON — ignore and start with no photos
  }

  if (!Array.isArray(stored) || !stored.length) return;

  stored.forEach((item) => {
    // Support both plain string data URLs (legacy) and {dataUrl, label} objects
    const dataUrl = typeof item === "string" ? item : item.dataUrl;
    const label   = typeof item === "object" ? item.label || "" : "";
    if (!dataUrl) return;

    photos.push({
      dataUrl,
      blob: null, // blob not available after sessionStorage round-trip
      timestamp: new Date().toISOString(),
      label,
    });
    addThumb(photos.length - 1);
  });

  updateCount();
  showPreview(0); // show the first restored photo in the preview area
});
