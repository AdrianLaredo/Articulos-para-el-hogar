document.addEventListener("DOMContentLoaded", () => {
  const errorDiv = document.getElementById("error-message");
  if (errorDiv && errorDiv.textContent.trim() !== "") {
    errorDiv.style.display = "block";
    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
});
