document.addEventListener("DOMContentLoaded", function () {
  // === FILE VALIDATION LOGIC ===
  const uploadForm = document.querySelector('form[action="upload_media.php"]');
  const lyricsInput = document.querySelector('input[name="lyrics_file"]');
  const audioInput = document.querySelector('input[name="audio_file"]');
  const errorBox = document.getElementById("uploadError");
  const submitBtn = document.querySelector('#tab-upload button[type="submit"]');

  // Accepted extensions and max sizes (matching server-side validation)
  const validLyrics = ["txt"];
  const validAudio = ["mp3"];
  const MAX_LYRICS_SIZE = 100 * 1024; // 100 KB (matching server)
  const MAX_AUDIO_SIZE = 10 * 1024 * 1024; // 10 MB (matching server)

  function validateFile(input, validTypes, maxSize, label) {
    if (!input || !input.files || input.files.length === 0) {
      // no file selected → allow submission
      return { valid: true };
    }

    const file = input.files[0];
    const ext = file.name.split(".").pop().toLowerCase();

    // Check extension
    if (!validTypes.includes(ext)) {
      return {
        valid: false,
        message: `Invalid ${label} file. Allowed: .${validTypes.join(", .")}`,
      };
    }

    // Check file size
    if (file.size > maxSize) {
      const maxSizeMB = (maxSize / (1024 * 1024)).toFixed(1);
      const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
      return {
        valid: false,
        message: `${label} file is too large (${fileSizeMB} MB). Maximum allowed: ${maxSizeMB} MB`,
      };
    }

    return { valid: true };
  }

  function showError(msg) {
    if (errorBox) {
      errorBox.style.display = "block";
      errorBox.textContent = msg;
    }
    if (submitBtn) submitBtn.disabled = true;
  }

  function clearError() {
    if (errorBox) {
      errorBox.style.display = "none";
      errorBox.textContent = "";
    }
    if (submitBtn) submitBtn.disabled = false;
  }

  // === EVENT LISTENERS FOR REAL-TIME VALIDATION ===
  if (lyricsInput) {
    lyricsInput.addEventListener("change", () => {
      const result = validateFile(
        lyricsInput,
        validLyrics,
        MAX_LYRICS_SIZE,
        "Lyrics"
      );
      if (!result.valid) {
        showError(result.message);
        lyricsInput.value = ""; // Reset input field
      } else {
        clearError();
      }
    });
  }

  if (audioInput) {
    audioInput.addEventListener("change", () => {
      const result = validateFile(
        audioInput,
        validAudio,
        MAX_AUDIO_SIZE,
        "Audio"
      );
      if (!result.valid) {
        showError(result.message);
        audioInput.value = ""; // Reset input field
      } else {
        clearError();
      }
    });
  }

  // === FORM SUBMIT VALIDATION ===
  if (uploadForm) {
    uploadForm.addEventListener("submit", (e) => {
      clearError();

      // Validate both files before submission
      const lyricsResult = validateFile(
        lyricsInput,
        validLyrics,
        MAX_LYRICS_SIZE,
        "Lyrics"
      );
      const audioResult = validateFile(
        audioInput,
        validAudio,
        MAX_AUDIO_SIZE,
        "Audio"
      );

      if (!lyricsResult.valid) {
        e.preventDefault();
        showError(lyricsResult.message);
        lyricsInput.value = "";
        return false;
      }

      if (!audioResult.valid) {
        e.preventDefault();
        showError(audioResult.message);
        audioInput.value = "";
        return false;
      }

      // All validations passed
      return true;
    });
  }

  // === TAB SWITCHING ===
  const mainTabs = document.querySelectorAll(".tab-btn");
  const mainPanels = document.querySelectorAll(".tab-panel");

  mainTabs.forEach((btn) => {
    btn.addEventListener("click", () => {
      mainTabs.forEach((b) => b.classList.remove("active"));
      mainPanels.forEach((p) => p.classList.remove("active"));
      btn.classList.add("active");
      document.getElementById(btn.dataset.tab).classList.add("active");

      // Clear any error messages when switching tabs
      clearError();
    });
  });

  // === TOAST NOTIFICATION ===
  const toast = document.getElementById("toast");
  if (toast && toast.dataset.show === "1") {
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 3000);
  }

  // === DELETE BUTTON CONFIRMATION ===
const deleteForms = document.querySelectorAll('form.delete-form');

deleteForms.forEach((form) => {
  form.addEventListener("submit", function (e) {
    const confirmed = confirm("Are you sure you want to delete this media? This action cannot be undone.");
    
    if (!confirmed) {
      e.preventDefault();
    }
  });
});
});
