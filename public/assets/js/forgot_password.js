document.addEventListener("DOMContentLoaded", () => {
  /* === SEND OTP BUTTON COOLDOWN === */
  const sendOtpBtn = document.querySelector('form button[type="submit"]');
  const emailInput = document.querySelector('input[name="email"]');

  // Only apply cooldown when on the email request step
  if (sendOtpBtn && emailInput && !document.getElementById("password")) {
    const lastSent = localStorage.getItem("otpCooldownStart");
    if (lastSent) {
      const elapsed = Math.floor((Date.now() - parseInt(lastSent, 10)) / 1000);
      const remaining = 60 - elapsed;
      if (remaining > 0) startCooldown(sendOtpBtn, remaining);
    }

    document.querySelector("form").addEventListener("submit", () => {
      localStorage.setItem("otpCooldownStart", Date.now().toString());
    });
  }

  function startCooldown(button, seconds) {
    button.disabled = true;
    button.classList.add("disabled-btn");
    let countdown = seconds;

    const update = () => {
      button.textContent = `Resend OTP in ${countdown}s`;
      countdown--;
      if (countdown < 0) {
        clearInterval(timer);
        button.disabled = false;
        button.classList.remove("disabled-btn");
        button.textContent = "Send OTP";
        localStorage.removeItem("otpCooldownStart");
      }
    };

    update();
    const timer = setInterval(update, 1000);
  }

  /* === PASSWORD STRENGTH & RULE CHECK === */
const passwordInput = document.getElementById("password");
const confirmInput = document.getElementById("confirm_password");
const strengthBar = document.getElementById("password-strength-bar");
const feedback = document.getElementById("password-feedback");

// Password rule checklist
const rules = {
  length: document.getElementById("length-rule"),
  uppercase: document.getElementById("uppercase-rule"),
  number: document.getElementById("number-rule"),
  special: document.getElementById("special-rule"),
};

if (passwordInput && strengthBar) {
  passwordInput.addEventListener("input", () => {
    const val = passwordInput.value.trim();
    const result = zxcvbn(val);
    const score = result.score;

    // Update strength bar
    const widths = ["20%", "40%", "60%", "80%", "100%"];
    const classes = ["weak", "weak", "fair", "good", "strong"];
    const labels = ["Very Weak", "Weak", "Fair", "Good", "Strong"];
    strengthBar.style.width = widths[score];
    strengthBar.className = classes[score];
    feedback.textContent =
      `${labels[score]}. ${result.feedback.suggestions.join(" ")}`;

    // Update rule list visually
    rules.length?.classList.toggle("valid", val.length >= 8);
    rules.uppercase?.classList.toggle("valid", /[A-Z]/.test(val));
    rules.number?.classList.toggle("valid", /\d/.test(val));
    rules.special?.classList.toggle("valid", /[@#$%&*!]/.test(val));
  });
}


  /* === PASSWORD MATCH WARNING === */
  if (confirmInput) {
    confirmInput.addEventListener("input", () => {
      if (passwordInput.value && confirmInput.value) {
        confirmInput.style.border =
          passwordInput.value !== confirmInput.value
            ? "2px solid #e74c3c"
            : "2px solid #2ecc71";
      } else {
        confirmInput.style.border = "1px solid #ccc";
      }
    });
  }

  /* === PREVENT MULTIPLE SUBMISSIONS === */
  const forms = document.querySelectorAll("form");
  forms.forEach((form) => {
    form.addEventListener("submit", () => {
      const btn = form.querySelector("button[type=submit]");
      if (btn) {
        btn.disabled = true;
        btn.textContent = "Processing...";
      }
    });
  });
});
