const formSteps = document.querySelectorAll(".form-step");
const nextBtn = document.querySelector(".next-btn");
const backBtn = document.querySelector(".back-btn");
const form = document.getElementById("registerForm");

let step = 0;

nextBtn.addEventListener("click", () => {
  formSteps[step].classList.remove("active");
  step++;
  formSteps[step].classList.add("active");
});

backBtn.addEventListener("click", () => {
  formSteps[step].classList.remove("active");
  step--;
  formSteps[step].classList.add("active");
});

form.addEventListener("submit", function(e) {
  const pass = document.getElementById("password").value;
  const confirm = document.getElementById("confirm_password").value;
  if (pass !== confirm) {
      e.preventDefault();
      alert("Passwords do not match!");
  }
});


