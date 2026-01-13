document.addEventListener("DOMContentLoaded", () => {
  const passwordInput = document.getElementById("password");
  const confirmInput = document.getElementById("confirm_password");
  const strengthBar = document.getElementById("password-strength-bar");
  const feedback = document.getElementById("password-feedback");

  const rules = {
    length: document.getElementById("length-rule"),
    uppercase: document.getElementById("uppercase-rule"),
    number: document.getElementById("number-rule"),
    special: document.getElementById("special-rule"),
  };

  if (passwordInput) {
    passwordInput.addEventListener("input", () => {
      const val = passwordInput.value.trim();
      const result = zxcvbn(val);
      const score = result.score;
      const widths = ["20%", "40%", "60%", "80%", "100%"];
      const classes = ["weak", "weak", "fair", "good", "strong"];
      const labels = ["Very Weak", "Weak", "Fair", "Good", "Strong"];

      strengthBar.style.width = widths[score];
      strengthBar.className = classes[score];
      feedback.textContent = `${labels[score]}. ${result.feedback.suggestions.join(" ")}`;

      rules.length.classList.toggle("valid", val.length >= 8);
      rules.uppercase.classList.toggle("valid", /[A-Z]/.test(val));
      rules.number.classList.toggle("valid", /\d/.test(val));
      rules.special.classList.toggle("valid", /[@#$%&*!]/.test(val));
    });
  }

  if (confirmInput) {
    confirmInput.addEventListener("input", () => {
      confirmInput.style.border =
        passwordInput.value && confirmInput.value && passwordInput.value !== confirmInput.value
          ? "2px solid #e74c3c"
          : "2px solid #2ecc71";
    });
  }
});
