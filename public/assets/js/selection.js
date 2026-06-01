document.addEventListener("click", (e) => {
  if (e.target.closest("#btn-delete-selection")) {
    console.log("👉 CLIC SUPPRIMER CAPTÉ");
  }
});


/* ===============================
   GARDE-FOU : éviter double chargement
=============================== */
if (window.__selectionJSLoaded) {
  console.warn("⚠️ selection.js déjà chargé — stop");
} else {
  window.__selectionJSLoaded = true;

  console.log("✅ selection.js chargé (unique)");

  /* ===============================
     ÉTAT APPLICATIF — SÉLECTION
  =============================== */
  const SelectionState = {
    ids: [],

    refresh() {
      this.ids = getSelectedRecetteIds();
      this.syncUI();
    },

    syncUI() {
      const btnDelete = document.getElementById("btn-delete-selection");
      if (!btnDelete) return;

      btnDelete.disabled = this.ids.length === 0;
    }
  };

  /* ===============================
     UTILITAIRES
  =============================== */
  function getSelectedRecetteIds() {
    return Array.from(
      document.querySelectorAll(
        ".btn-select-recette.is-selected, .btn-selection.is-selected"
      )
    )
      .map(btn => btn.dataset.recetteId)
      .filter(Boolean);
  }

  // … le reste de ton code


  function updateDeleteButtonState() {
  SelectionState.refresh();
}


  function openSelectionDocument(mode = "print") {
    const ids = getSelectedRecetteIds();
    if (!ids.length) {
      alert("Aucune recette sélectionnée");
      return;
    }

    const endpoint = mode === "pdf"
      ? "/pdf/pdf_selection.php?ids="
      : "/print/print_selection.php?ids=";

    window.open(endpoint + ids.join(","), "_blank");
  }

  function submitDeleteSelection(ids) {
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "/delete_multiple.php";

    ids.forEach(id => {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "ids[]";
      input.value = id;
      form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
  }

  async function toggleSelection(btn) {
    const recetteId = btn.dataset.recetteId;
    if (!recetteId) return;

    try {
      const res = await fetch("/ajax/toggle_selection.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "recette_id=" + encodeURIComponent(recetteId),
      });

      const text = await res.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        console.error("Réponse non JSON :", text);
        alert("Erreur serveur (voir console)");
        return;
      }

      if (data.error) {
        alert("Erreur : " + data.error);
        return;
      }

      const isSelected = data.status === "added";
     btn.classList.toggle("is-selected", isSelected);
btn.textContent = isSelected ? "✔️" : "⬜";

SelectionState.refresh();

    } catch (err) {
      console.error("Erreur réseau sélection", err);
      alert("Erreur réseau");
    }
  }

  /* ===============================
     DÉLÉGATION GLOBALE (LISTE + GALLERY)
  =============================== */

  document.addEventListener("click", function (e) {

    // 1️⃣ Toggle sélection
    // On limite explicitement la recherche aux boutons de sélection
    // (classe .btn-select-recette ou .btn-selection) afin d'éviter
    // d'intercepter les clics sur d'autres éléments possédant
    // un attribut data-recette-id, comme les boutons de favoris.
    const btnSelect = e.target.closest(".btn-select-recette, .btn-selection");

    if (btnSelect) {
      e.preventDefault();
      toggleSelection(btnSelect);
      return;
    }

    // 2️⃣ Imprimer sélection
    if (e.target.closest(".btn-print-selection")) {
      e.preventDefault();
      openSelectionDocument("pdf");
      return;
    }

    // 3️⃣ PDF sélection
    if (e.target.closest(".btn-pdf-selection")) {
      e.preventDefault();
      openSelectionDocument("pdf");
      return;
    }

   
  });

  /* ===============================
     INIT
  =============================== */
  document.addEventListener("DOMContentLoaded", updateDeleteButtonState);
  /* ===============================
   ACTION : SUPPRESSION SÉLECTION
=============================== */

  document.addEventListener("DOMContentLoaded", () => {
  const btnDelete = document.getElementById("btn-delete-selection");
  if (!btnDelete) return;

  btnDelete.addEventListener("click", () => {
    const ids = SelectionState.ids;

    if (!ids.length) return;

    if (!confirm(`Supprimer ${ids.length} recette(s) ?`)) return;

    submitDeleteSelection(ids);
  });

  SelectionState.refresh();
});


}
