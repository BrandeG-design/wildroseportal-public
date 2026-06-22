const checkbox = document.getElementById("no-password");
const passwordInput = document.getElementById("psw");
const noPassFlag = document.getElementById("no-pass-flag");

checkbox.addEventListener("change", () => {
  if (checkbox.checked) {
    passwordInput.required = false;
    passwordInput.disabled = true;
    passwordInput.value = "";
    noPassFlag.value = "1";
  } else {
    passwordInput.required = true;
    passwordInput.disabled = false;
    noPassFlag.value = "0";
  }
});

// Also wire up the peripherals toggle
(function () {
  const noPeriphBox = document.getElementById("no-peripherals");
  const periphArea = document.getElementById("peripherals");
  const noPeriphFlag = document.getElementById("no-periph-flag");
  if (!noPeriphBox) return;

  noPeriphBox.addEventListener("change", () => {
    if (noPeriphBox.checked) {
      periphArea.required = false;
      periphArea.disabled = true;
      periphArea.value = "";
      noPeriphFlag.value = "1";
    } else {
      periphArea.required = true;
      periphArea.disabled = false;
      noPeriphFlag.value = "0";
    }
  });
})();
