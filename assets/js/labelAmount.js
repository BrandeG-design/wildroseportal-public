// =============================================================================
// FILE: labelAmount.js
// PURPOSE: Controls the +/- quantity counter for the Dymo label printer on
//          ticket.php, ticketreport.php, and ticketsummary.php. Enforces a
//          minimum of 0 and a maximum of 10 labels per ticket.
//
// USED BY: ticket.php, ticketreport.php, ticketsummary.php
// =============================================================================

// Cache DOM references for the counter input and its two buttons
const labelAmount = document.getElementById("labelAmount");
const increaseBtn = document.getElementById("increaseAmount");
const decreaseBtn = document.getElementById("decreaseAmount");

// Set the starting value to 1 — staff almost always need at least one label
labelAmount.value = 1;

// ── increaseAmount ────────────────────────────────────────────────────────────
// Increments the label count by 1, up to a maximum of 10.
function increaseAmount() {
  let currentAmount = parseInt(labelAmount.value);
  if (currentAmount < 10) {
    currentAmount++;
    labelAmount.value = currentAmount;
  }
}

// ── decreaseAmount ────────────────────────────────────────────────────────────
// Decrements the label count by 1, down to a minimum of 0.
// 0 is allowed so staff can skip printing if needed.
function decreaseAmount() {
  let currentAmount = parseInt(labelAmount.value);
  if (currentAmount > 0) {
    currentAmount--;
    labelAmount.value = currentAmount;
  }
}

decreaseBtn.addEventListener("click", decreaseAmount);
increaseBtn.addEventListener("click", increaseAmount);
