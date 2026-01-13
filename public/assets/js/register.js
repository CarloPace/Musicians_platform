function validateEmail(email) {
  const regex =
    /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/g;
  return regex.test(email);
}

function validateUsername(username) {
  if (username.length < 3 || username.length > 50) {
    return false;
  }
  const regex = /^[A-Za-z][A-Za-z0-9_-]{2,49}$/;
  return regex.test(username);
}

function showError(msg) {
  const error_msg = document.querySelector("#error_message");
  if (!error_msg) return;
  error_msg.textContent = msg;
  error_msg.classList.remove("hidden");
}

function hideError() {
  const el = document.querySelector("#error_message");
  if (el) el.classList.add("hidden");
}

function formCheck(event) {
  const form = event.target;
  const email = form.email?.value.trim();
  const username = form.username?.value.trim();
  const password = form.password?.value;
  const confirmPassword = form.confirm_password?.value;

  if (!email || !username || !password || !confirmPassword) {
    event.preventDefault();
    showError("Please fill in all the fields.");
    return;
  }

  if (!validateEmail(email)) {
    event.preventDefault();
    showError("Invalid email format.");
    return;
  }

  if (!validateUsername(username)) {
    event.preventDefault();
    showError("Username must be 3–50 chars and only letters, numbers, _ or - are allowed");
    return;
  }

  if (password.length < 8) {
    event.preventDefault();
    showError("Password must be at least 8 characters long.");
    return;
  }

  if (password !== confirmPassword) {
    event.preventDefault();
    showError("Passwords do not match.");
    return;
  }

  hideError();
}

document.addEventListener("DOMContentLoaded", () => {
  // === FORM VALIDATION ===
  const form = document.forms["signup_data"];
  if (form) form.addEventListener("submit", formCheck);

  // === PASSWORD STRENGTH ===
  const passwordInput = document.getElementById("password");
  const confirmInput = document.getElementById("confirm_password");
  const meterBar = document.getElementById("password-strength-bar");
  const feedbackEl = document.getElementById("password-feedback");

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
      const percent = (score + 1) * 20;

      meterBar.style.width = percent + "%";
      meterBar.className = "";
      if (score <= 1) meterBar.classList.add("weak");
      else if (score === 2) meterBar.classList.add("fair");
      else if (score === 3) meterBar.classList.add("good");
      else meterBar.classList.add("strong");

      let feedback = result.feedback.warning
        ? result.feedback.warning + " "
        : "";
      if (result.feedback.suggestions.length)
        feedback += result.feedback.suggestions.join(" ");
      feedbackEl.textContent = feedback;

      rules.length?.classList.toggle("valid", val.length >= 8);
      rules.uppercase?.classList.toggle("valid", /[A-Z]/.test(val));
      rules.number?.classList.toggle("valid", /\d/.test(val));
      rules.special?.classList.toggle("valid", /[@#$%&*!]/.test(val));
    });
  }

  // === PASSWORD MATCH ===
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

  // === RESEND OTP BUTTON COOLDOWN ===
  // Inside DOMContentLoaded

  const COOLDOWN = 60; // seconds
  const resendForm = document.querySelector(".resend-wrapper-form");
  const resendBtn = resendForm?.querySelector(".resend-btn");
  const timerSpan = document.getElementById("resend-timer");

  if (resendForm && resendBtn && timerSpan && !passwordInput) {
    const startCooldown = (button, timer, seconds) => {
      button.disabled = true;
      button.classList.add("disabled-btn");
      timer.classList.remove("hidden");
      let countdown = seconds;

      const update = () => {
        timer.textContent = `(Resend in ${countdown}s)`;
        countdown--;
        if (countdown < 0) {
          clearInterval(interval);
          button.disabled = false;
          button.classList.remove("disabled-btn");
          timer.classList.add("hidden");
          timer.textContent = "";
          localStorage.removeItem("otpCooldownStart");
        }
      };

      update();
      const interval = setInterval(update, 1000);
    };

    // Check if there's an active cooldown from previous send
    const lastSent = localStorage.getItem("otpCooldownStart");
    if (lastSent) {
      const elapsed = Math.floor((Date.now() - parseInt(lastSent, 10)) / 1000);
      const remaining = COOLDOWN - elapsed;
      if (remaining > 0) {
        startCooldown(resendBtn, timerSpan, remaining);
      }
    } else {
      // First load: start full cooldown
      localStorage.setItem("otpCooldownStart", Date.now().toString());
      startCooldown(resendBtn, timerSpan, COOLDOWN);
    }

    // When user clicks resend, start full cooldown
    resendForm.addEventListener("submit", () => {
      localStorage.setItem("otpCooldownStart", Date.now().toString());
      startCooldown(resendBtn, timerSpan, COOLDOWN);
    });
  }
});
