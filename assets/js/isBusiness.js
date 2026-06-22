// =============================================================================
// FILE: isBusiness.js
// PURPOSE: Handles the "Business" checkbox on customer.php. When checked, the
//          first/last name is changed to business and drop off respectively.
//          
//          Just like the no email flag, there is a hidden input that is kept 
//          in sync with the checkbox state so the php form handler can read it as
//          a "1" or "0" on submit that way we can track if it's a business.
// USED BY: customer.php
// =============================================================================
(function () {
  "use strict";

  const isBusinessBox  = document.getElementById("is_business");
  const isBusinessFlag = document.getElementById("is_business_flag"); // hidden input submitted with the form
  const fnameLabel = document.getElementById("fnameLabel");
  const lnameLabel = document.getElementById("lnameLabel");

  // Guard — exit silently if any element is missing
  if (!isBusinessBox || !isBusinessFlag || !fnameLabel || !lnameLabel) return;

    //Helper function to reinsert the text and append the star
    function updateLabel(labelElement, text){
        labelElement.textContent = text + " "; //space for padding

        const span = document.createElement("span");
        span.className="required-star";
        span.setAttribute("aria-hidden", "true");
        span.textContent="*";

        labelElement.appendChild(span);
    }

  function applyBusinessState(isChecked) {
    if (isChecked) {
      updateLabel(fnameLabel, "Business");
      updateLabel(lnameLabel, "Dropoff Contact");
      isBusinessFlag.value = "1";
    } else {
      updateLabel(fnameLabel, "First Name");
      updateLabel(lnameLabel, "Last Name");
      isBusinessFlag.value = "0";
    }
  }

  isBusinessBox.addEventListener("change", function () {
    applyBusinessState(this.checked);
  });

  // Run once on page load so pre-checked state (e.g. existing business customer)
  // is reflected immediately without needing to toggle the checkbox
  applyBusinessState(isBusinessBox.checked);
})();