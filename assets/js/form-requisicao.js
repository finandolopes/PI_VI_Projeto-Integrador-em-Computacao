document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("form-requisicao");
  if (!form) return;

  const outrosCheckbox = document.getElementById("outros_check");
  const outrosInfoDiv = document.getElementById("outros_info_div");
  const outrosInfoInput = document.getElementById("outros_info");
  const alertBox = document.getElementById("form-requisicao-alert");
  const categoriaFeedback = document.getElementById("categoria-feedback");

  let firstInvalid = null;

  const markInvalid = (input, message) => {
    setFieldError(input, message);
    if (!firstInvalid && input) {
      firstInvalid = input;
    }
  };

  const setFieldError = (input, message) => {
    if (!input) return;
    input.classList.add("is-invalid");
    input.setAttribute("aria-invalid", "true");
    const feedback = input.parentElement.querySelector(".invalid-feedback");
    if (feedback) feedback.textContent = message;
  };

  const clearFieldError = (input) => {
    if (!input) return;
    input.classList.remove("is-invalid");
    input.setAttribute("aria-invalid", "false");
    const feedback = input.parentElement.querySelector(".invalid-feedback");
    if (feedback) feedback.textContent = "";
  };

  const showAlert = (message) => {
    if (!alertBox) return;
    alertBox.textContent = message;
    alertBox.classList.remove("d-none");
  };

  const hideAlert = () => {
    if (!alertBox) return;
    alertBox.textContent = "";
    alertBox.classList.add("d-none");
  };

  const setCategoriaError = (message) => {
    if (categoriaFeedback) categoriaFeedback.textContent = message;
  };

  const clearCategoriaError = () => {
    if (categoriaFeedback) categoriaFeedback.textContent = "";
  };

  const toggleOutros = () => {
    if (!outrosCheckbox || !outrosInfoDiv || !outrosInfoInput) return;
    if (outrosCheckbox.checked) {
      outrosInfoDiv.style.display = "block";
      outrosInfoInput.setAttribute("required", "required");
    } else {
      outrosInfoDiv.style.display = "none";
      outrosInfoInput.removeAttribute("required");
      clearFieldError(outrosInfoInput);
    }
  };

  if (outrosCheckbox) {
    outrosCheckbox.addEventListener("change", toggleOutros);
  }
  toggleOutros();

  // Máscaras (se jQuery + mask estiverem disponíveis)
  if (window.$ && typeof window.$.fn.mask === "function") {
    window.$("#telefone").mask("(00) 00000-0000");
    window.$("#data_nascimento").mask("00/00/0000");
  }

  form.addEventListener("submit", (event) => {
    hideAlert();
    clearCategoriaError();
    firstInvalid = null;

    const nome = document.getElementById("nome");
    const dataNascimento = document.getElementById("data_nascimento");
    const telefone = document.getElementById("telefone");
    const email = document.getElementById("email");
    const horarioContato = document.getElementById("horario_contato");

    [nome, dataNascimento, telefone, email, horarioContato, outrosInfoInput].forEach(clearFieldError);

    let hasError = false;

    if (!nome.value.trim()) {
      markInvalid(nome, "Informe seu nome completo.");
      hasError = true;
    }

    if (!dataNascimento.value.trim()) {
      markInvalid(dataNascimento, "Informe sua data de nascimento.");
      hasError = true;
    }

    if (!telefone.value.trim()) {
      setFieldError(telefone, "Informe um telefone válido.");
      hasError = true;
    }

  if (!email.value.trim()) {
    markInvalid(email, "Informe seu e-mail.");
    hasError = true;
  } else {
    const emailPattern = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
    if (!emailPattern.test(email.value.trim())) {
      setFieldError(email, "Informe um e-mail válido.");
      hasError = true;
    }
  }

    if (!horarioContato.value.trim()) {
      setFieldError(horarioContato, "Informe o horário para contato.");
      hasError = true;
    }

    const categorias = document.querySelectorAll('input[name="categoria[]"]:checked');
    if (!categorias.length) {
      showAlert("Selecione ao menos uma categoria.");
      setCategoriaError("Selecione ao menos uma categoria.");
      const categoriaInput = document.querySelector('input[name="categoria[]"]');
      if (!firstInvalid && categoriaInput) {
        firstInvalid = categoriaInput;
      }
      hasError = true;
    }

    if (outrosCheckbox && outrosCheckbox.checked && outrosInfoInput && !outrosInfoInput.value.trim()) {
      markInvalid(outrosInfoInput, "Descreva a categoria em Outros.");
      hasError = true;
    }

    if (hasError) {
      event.preventDefault();
      if (firstInvalid && typeof firstInvalid.focus === "function") {
        firstInvalid.focus();
      }
      return;
    }

    if (window.trackEvent) {
      window.trackEvent("form_requisicao_submit", {
        label: "Enviar Requisio",
      });
    }

    // Normalizar data para YYYY-MM-DD antes do envio
    const dataValue = dataNascimento.value.trim();
    const match = dataValue.match(/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/);
    if (match) {
      dataNascimento.value = `${match[3]}-${match[2]}-${match[1]}`;
    }
  });
});
