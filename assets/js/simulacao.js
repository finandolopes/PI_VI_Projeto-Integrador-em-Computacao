document.addEventListener("DOMContentLoaded", () => {
  const openBtn = document.getElementById("btnSimular");
  const modal = document.getElementById("modalSimulacao");
  const overlay = document.getElementById("modalOverlay");
  const closeBtn = document.getElementById("closeSimulacao");
  const form = document.getElementById("formSimulacao");
  const resultado = document.getElementById("resultado");
  const extraTriggers = document.querySelectorAll('[data-open-simulacao="true"]');

  if (!modal || !overlay || !closeBtn || !form) return;

  const firstInput = form.querySelector("input, select, button");

  const openModal = () => {
    modal.style.display = "block";
    overlay.style.display = "block";
    document.body.classList.add("modal-open");
    modal.setAttribute("aria-hidden", "false");
    if (firstInput) firstInput.focus();
  };

  const closeModal = () => {
    modal.style.display = "none";
    overlay.style.display = "none";
    document.body.classList.remove("modal-open");
    modal.setAttribute("aria-hidden", "true");
    openBtn.focus();
  };

  if (openBtn) {
    openBtn.addEventListener("click", openModal);
  }
  extraTriggers.forEach((trigger) => trigger.addEventListener("click", openModal));
  closeBtn.addEventListener("click", closeModal);
  overlay.addEventListener("click", closeModal);

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && modal.style.display === "block") {
      closeModal();
    }
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    resultado.textContent = "";

    const valor = document.getElementById("valor").value.trim();
    const parcelas = document.getElementById("parcelas").value.trim();
    const cpf = document.getElementById("cpf").value.trim();

    if (!valor || !parcelas || !cpf) {
      resultado.textContent = "Preencha todos os campos para simular.";
      return;
    }

    try {
      const response = await fetch("api/simulacao.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ valor, parcelas, cpf }),
      });
      const data = await response.json();
      if (!response.ok || data.error) {
        resultado.textContent = data.error || "Erro ao simular. Tente novamente.";
        return;
      }
      resultado.innerHTML = `<p>Parcela mensal: R$ ${data.parcela}</p>`;
    } catch (error) {
      resultado.textContent = "Erro ao simular. Tente novamente.";
    }
  });
});
