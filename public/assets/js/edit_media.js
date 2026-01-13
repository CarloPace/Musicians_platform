document.addEventListener("DOMContentLoaded", function () {
  // === TARGET THE MAIN EDIT FORM ONLY ===
  const editForm    = document.getElementById('editMediaForm'); // main form with file inputs
  const lyricsInput = editForm ? editForm.querySelector('input[name="lyrics_file"]') : null;
  const audioInput  = editForm ? editForm.querySelector('input[name="audio_file"]') : null;
  const errorBox    = document.getElementById('uploadError');
  const submitBtn   = editForm ? editForm.querySelector('button[name="update_media"]') : null;

  // Accepted extensions and max sizes (matching server-side validation)
  const validLyrics = ['txt'];
  const validAudio  = ['mp3'];
  const MAX_LYRICS_SIZE = 100 * 1024;       // 100 KB
  const MAX_AUDIO_SIZE = 10 * 1024 * 1024;  // 10 MB

  function validateFile(input, validTypes, maxSize, label) {
      if (!input || !input.files || input.files.length === 0) {
          return { valid: true };
      }

      const file = input.files[0];
      const ext  = file.name.split('.').pop().toLowerCase();

      if (!validTypes.includes(ext)) {
          return { 
              valid: false, 
              message: `❌ Invalid ${label} file. Allowed: .${validTypes.join(', .')}`
          };
      }

      if (file.size > maxSize) {
          const maxSizeMB = (maxSize / (1024 * 1024)).toFixed(1);
          const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
          return { 
              valid: false, 
              message: `❌ ${label} file is too large (${fileSizeMB} MB). Maximum allowed: ${maxSizeMB} MB`
          };
      }

      return { valid: true };
  }

  function showError(msg) {
      if (errorBox) {
          errorBox.style.display = 'block';
          errorBox.textContent = msg;
          errorBox.classList.add('warning');
      }
      if (submitBtn) submitBtn.disabled = true;
  }

  function clearError() {
      if (errorBox) {
          errorBox.style.display = 'none';
          errorBox.textContent = '';
          errorBox.classList.remove('warning');
      }
      if (submitBtn) submitBtn.disabled = false;
  }

  // === REAL-TIME FILE VALIDATION ===
  if (lyricsInput) {
      lyricsInput.addEventListener('change', () => {
          const result = validateFile(lyricsInput, validLyrics, MAX_LYRICS_SIZE, 'Lyrics');
          if (!result.valid) {
              showError(result.message);
              lyricsInput.value = '';
          } else {
              clearError();
          }
      });
  }

  if (audioInput) {
      audioInput.addEventListener('change', () => {
          const result = validateFile(audioInput, validAudio, MAX_AUDIO_SIZE, 'Audio');
          if (!result.valid) {
              showError(result.message);
              audioInput.value = '';
          } else {
              clearError();
          }
      });
  }

  // === FORM SUBMIT VALIDATION ===
  if (editForm) {
      editForm.addEventListener('submit', (e) => {
          clearError();

          const lyricsResult = validateFile(lyricsInput, validLyrics, MAX_LYRICS_SIZE, 'Lyrics');
          const audioResult = validateFile(audioInput, validAudio, MAX_AUDIO_SIZE, 'Audio');

          if (!lyricsResult.valid) {
              e.preventDefault();
              showError(lyricsResult.message);
              lyricsInput.value = '';
              return false;
          }

          if (!audioResult.valid) {
              e.preventDefault();
              showError(audioResult.message);
              audioInput.value = '';
              return false;
          }

          return true;
      });
  }

  // === DELETE BUTTON CONFIRM PROMPT ===
  document.querySelectorAll('button.btn-delete').forEach(btn => {
      btn.addEventListener('click', function(e) {
          if (!confirm("Are you sure you want to delete this file?")) {
              e.preventDefault(); // stop form submission
          }
          // else form submits naturally
      });
  });
});
