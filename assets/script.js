// Ensure price is positive on checkout (similar to original)
document.addEventListener("DOMContentLoaded", function () {
  const form = document.querySelector('form[action="order.php"]');
  if (form) {
    form.addEventListener("submit", function (e) {
      const name = document.querySelector('[name="customer_name"]');
      const email = document.querySelector('[name="customer_email"]');
      const phone = document.querySelector('[name="customer_phone"]');
      if (!name.value.trim() || !email.value.trim() || !phone.value.trim()) {
        alert("Please fill all fields");
        e.preventDefault();
      }
    });
  }

  // Add to cart confirmation
  const addLinks = document.querySelectorAll(".btn.buy");
  addLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      // optional confirmation
    });
  });
});
