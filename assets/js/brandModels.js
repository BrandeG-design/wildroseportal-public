// =============================================================================
// FILE: brandModels.js
// PURPOSE: Populates the model field suggestion list when staff select a device
//          brand on ticket.php. Uses a <datalist> element so the model input
//          shows autocomplete suggestions without locking the field to only
//          those options — staff can still type any model not in the list.
//
// DEPENDS ON: A <select> or <input> with id="brand" and a <datalist> with
//             id="model-suggestions" and an <input> with id="model" on the page.
// USED BY:    ticket.php
// =============================================================================

const brandInput = document.getElementById("brand");
const modelList  = document.getElementById("model-suggestions");

// ── Brand → Model suggestion map ─────────────────────────────────────────────
// Add or remove entries here to update the suggestions shown to staff.
// Keys must match the brand values in the brand input exactly (case-sensitive).
const models = {
  Acer: [
    "Aspire 3",
    "Aspire 5",
    "Swift 3",
    "Swift Go",
    "Nitro 5",
    "Predator Helios 300",
  ],
  Apple: [
    "MacBook Air M1",
    "MacBook Air M2",
    "MacBook Air M3",
    "MacBook Pro 13",
    "MacBook Pro 14",
    "MacBook Pro 16",
    'iMac 24"',
    "Mac Mini",
  ],
  ASUS: [
    "VivoBook 15",
    "ZenBook 14",
    "ROG Strix G15",
    "ROG Zephyrus G14",
    "TUF Gaming F15",
  ],
  Dell: [
    "Inspiron 15",
    "Inspiron 14",
    "XPS 13",
    "XPS 15",
    "Latitude 5400",
    "Latitude 7400",
    "OptiPlex 7010",
    "Alienware m15",
  ],
  HP: [
    "Pavilion 15",
    "Envy 13",
    "Envy x360",
    "EliteBook 840",
    "ProBook 450",
    "Omen 16",
  ],
  Lenovo: [
    "ThinkPad T14",
    "ThinkPad X1 Carbon",
    "ThinkPad E15",
    "IdeaPad 3",
    "IdeaPad 5",
    "Legion 5",
    "Yoga 7i",
  ],
  LG: ["Gram 14", "Gram 15", "Gram 17"],
  Microsoft: [
    "Surface Laptop 4",
    "Surface Laptop 5",
    "Surface Laptop Go",
    "Surface Pro 7",
    "Surface Pro 9",
    "Surface Book 3",
  ],
  MSI:       ["GF63 Thin", "Katana 15", "Stealth 15M", "Raider GE76"],
  Razer:     ["Blade 14", "Blade 15", "Blade 16", "Blade 18"],
  Samsung: [
    "Galaxy Book 2",
    "Galaxy Book 3",
    "Galaxy Book Pro",
    "Galaxy Book Odyssey",
  ],
  Sony:      ["VAIO E Series", "VAIO S Series", "VAIO Pro 13"],
  Toshiba:   ["Satellite C55", "Satellite L50", "Tecra A50", "Portégé X30"],
  Google:    ["Pixelbook", "Pixelbook Go", "Chromebook Plus"],
  Panasonic: ["Toughbook CF-31", "Toughbook CF-33", "Toughbook 55"],
  Alienware: ["m15", "m16", "m18", "Aurora R15", "Aurora R16"],
};

// ── Brand change handler ──────────────────────────────────────────────────────
// Fires when the brand field loses focus or a value is selected.
// Clears the current model value and repopulates the datalist with suggestions
// matching the selected brand. If the brand has no entry in the map, the
// datalist is emptied so no stale suggestions from a previous brand remain.
brandInput.addEventListener("change", function () {
  const selectedBrand = brandInput.value;

  // Clear the model field so staff don't accidentally keep the previous entry
  document.getElementById("model").value = "";
  modelList.innerHTML = "";

  // Populate datalist with matching model suggestions
  if (models[selectedBrand]) {
    models[selectedBrand].forEach((model) => {
      const option   = document.createElement("option");
      option.value   = model;
      modelList.appendChild(option);
    });
  }
});
