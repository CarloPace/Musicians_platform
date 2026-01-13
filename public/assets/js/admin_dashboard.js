document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".action-form").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      if (!confirm("Are you sure you want to perform this action?")) {
        e.preventDefault();
      }
    });
  });
});
