// main.js — interactions globales (menus, partage)
document.addEventListener("DOMContentLoaded", () => {
  // Dropdowns
  document.querySelectorAll("[data-dropdown]").forEach((dd) => {
    const toggle = dd.querySelector("[data-dropdown-toggle]");
    const panel = dd.querySelector("[data-dropdown-panel]");
    if (!toggle || !panel) return;

    const close = () => dd.classList.remove("is-open");
    const open = () => dd.classList.add("is-open");

    toggle.addEventListener("click", (e) => {
      e.stopPropagation();
      dd.classList.contains("is-open") ? close() : open();
    });

    document.addEventListener("click", (e) => {
      if (!dd.contains(e.target)) close();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") close();
    });
  });

  // Partage (recette)
  document.querySelectorAll("[data-share]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const type = btn.getAttribute("data-share");
      const url = window.location.href;
      const title = document.title;

      if (navigator.share) {
        try {
          await navigator.share({ title, url });
          return;
        } catch (_) {}
      }

      if (type === "mail") {
        window.location.href = `mailto:?subject=${encodeURIComponent(title)}&body=${encodeURIComponent(url)}`;
        return;
      }

      if (type === "whatsapp") {
        window.open(`https://wa.me/?text=${encodeURIComponent(title + " " + url)}`, "_blank");
      }
    });
  });
});
