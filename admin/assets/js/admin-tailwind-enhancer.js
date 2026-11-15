(() => {
  "use strict";

  const addClasses = (selector, classes) => {
    document.querySelectorAll(selector).forEach((el) => {
      el.classList.add(...classes);
    });
  };

  const upgradeButtons = () => {
    addClasses(".btn", [
      "inline-flex",
      "items-center",
      "justify-center",
      "gap-1",
      "rounded-xl",
      "font-semibold",
      "transition-all",
      "duration-150",
      "focus:outline-none",
      "focus:ring-2",
      "focus:ring-offset-2",
      "focus:ring-sky-500",
    ]);

    addClasses(".btn-sm", ["px-3", "py-1.5", "text-xs"]);
    addClasses(".btn:not(.btn-sm)", ["px-4", "py-2", "text-sm"]);

    addClasses(".btn-primary", [
      "!bg-sky-700",
      "!border-sky-700",
      "hover:!bg-sky-800",
      "hover:!border-sky-800",
      "!text-white",
    ]);

    addClasses(".btn-success", [
      "!bg-emerald-600",
      "!border-emerald-600",
      "hover:!bg-emerald-700",
      "hover:!border-emerald-700",
      "!text-white",
    ]);

    addClasses(".btn-danger", [
      "!bg-rose-600",
      "!border-rose-600",
      "hover:!bg-rose-700",
      "hover:!border-rose-700",
      "!text-white",
    ]);

    addClasses(".btn-warning", [
      "!bg-amber-500",
      "!border-amber-500",
      "hover:!bg-amber-600",
      "hover:!border-amber-600",
      "!text-white",
    ]);

    addClasses(".btn-info", [
      "!bg-cyan-600",
      "!border-cyan-600",
      "hover:!bg-cyan-700",
      "hover:!border-cyan-700",
      "!text-white",
    ]);

    addClasses(".btn-outline-secondary", [
      "!bg-white",
      "!border-slate-300",
      "hover:!bg-slate-50",
      "!text-slate-700",
    ]);

    addClasses(".btn-outline-primary", [
      "!bg-white",
      "!border-sky-300",
      "hover:!bg-sky-50",
      "!text-sky-700",
    ]);

    addClasses(".btn-outline-danger", [
      "!bg-white",
      "!border-rose-300",
      "hover:!bg-rose-50",
      "!text-rose-700",
    ]);
  };

  const upgradeCards = () => {
    addClasses(".card", [
      "!border",
      "!border-slate-200",
      "!rounded-2xl",
      "!shadow-sm",
      "!overflow-hidden",
      "!bg-white",
    ]);

    addClasses(".card-header", [
      "!bg-white",
      "!border-b",
      "!border-slate-200",
      "px-5",
      "py-4",
    ]);

    addClasses(".card-title", ["!text-slate-900", "font-semibold"]);
    addClasses(".card-body", ["px-5", "py-4"]);
  };

  const upgradeTables = () => {
    addClasses(".table-responsive", ["overflow-x-auto", "rounded-xl"]);
    addClasses(".table", ["min-w-full", "text-sm"]);
    addClasses(".table thead th", [
      "bg-slate-50",
      "!text-slate-600",
      "uppercase",
      "tracking-wide",
      "text-xs",
      "font-semibold",
      "border-slate-200",
      "whitespace-nowrap",
    ]);
    addClasses(".table tbody td", [
      "!text-slate-700",
      "align-middle",
      "border-slate-100",
      "whitespace-nowrap",
    ]);
    addClasses(".table tbody tr", ["hover:bg-slate-50"]);
  };

  const upgradeForms = () => {
    addClasses(
      "input:not([type='checkbox']):not([type='radio']):not([type='file']), textarea, select",
      [
        "!w-full",
        "!rounded-xl",
        "!border-slate-300",
        "focus:!border-sky-500",
        "focus:!ring-sky-500",
      ]
    );

    addClasses(".form-label, .form-group label, label", [
      "text-sm",
      "font-semibold",
      "text-slate-700",
      "mb-1",
    ]);

    addClasses(".form-group", ["mb-3"]);
  };

  const upgradeLayout = () => {
    document.documentElement.classList.add("scroll-smooth");
    document.body.classList.add("bg-slate-100", "text-slate-800", "antialiased");

    addClasses(".page-wrapper", ["pt-3", "pb-6"]);
    addClasses(".content, .page-content", ["mx-auto", "max-w-[1500px]", "space-y-4"]);
    addClasses(".container-fluid", ["!px-0"]);

    addClasses(".page-header", ["mb-5"]);
    addClasses(".page-title", ["!text-2xl", "font-bold", "!text-slate-900"]);
    addClasses(".breadcrumb", ["!text-xs", "!text-slate-500", "mt-1"]);
  };

  const upgradeAlertsAndBadges = () => {
    addClasses(".alert", ["rounded-xl", "border", "shadow-sm"]);
    addClasses(".alert-success", ["!bg-emerald-50", "!border-emerald-200", "!text-emerald-800"]);
    addClasses(".alert-danger", ["!bg-rose-50", "!border-rose-200", "!text-rose-800"]);
    addClasses(".alert-warning", ["!bg-amber-50", "!border-amber-200", "!text-amber-800"]);
    addClasses(".alert-info", ["!bg-sky-50", "!border-sky-200", "!text-sky-800"]);

    addClasses(".badge", ["rounded-full", "px-2.5", "py-1", "text-xs", "font-semibold"]);
  };

  const upgradeModals = () => {
    addClasses(".modal-content", ["rounded-2xl", "border-slate-200", "shadow-xl"]);
    addClasses(".modal-header", ["border-slate-200"]);
    addClasses(".modal-footer", ["border-slate-200"]);
  };

  const patchDuplicates = () => {
    // Evita wrappers duplos antigos que quebravam espaçamento.
    document.querySelectorAll(".page-wrapper .page-wrapper").forEach((nested) => {
      const parent = nested.parentElement;
      while (nested.firstChild) {
        parent.insertBefore(nested.firstChild, nested);
      }
      nested.remove();
    });
  };

  const runEnhancements = () => {
    patchDuplicates();
    upgradeLayout();
    upgradeCards();
    upgradeTables();
    upgradeForms();
    upgradeButtons();
    upgradeAlertsAndBadges();
    upgradeModals();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", runEnhancements);
  } else {
    runEnhancements();
  }
})();
