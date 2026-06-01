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
    count: 0,

    async refresh() {
      const state = await fetchSelectionState();
      this.ids = Array.isArray(state.ids) ? state.ids : [];
      this.count = Number.isFinite(state.count) ? state.count : this.ids.length;
      this.syncUI();
    },

    syncUI() {
      const btnDelete = document.getElementById("btn-delete-selection");
      if (!btnDelete) return;

      btnDelete.disabled = this.count === 0;
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

  async function fetchSelectionState() {
    try {
      const res = await fetch("/ajax/selection_state.php", {
        headers: { "Accept": "application/json" },
      });
      return await res.json();
    } catch (err) {
      console.error("Erreur récupération sélection", err);
      return {
        count: getSelectedRecetteIds().length,
        ids: getSelectedRecetteIds(),
      };
    }
  }

  // … le reste de ton code


  function updateDeleteButtonState() {
    SelectionState.refresh();
  }


  async function openSelectionPdf() {
    await SelectionState.refresh();
    if (!SelectionState.count) {
      alert("Aucune recette sélectionnée");
      return;
    }

    window.open("/pdf/pdf_selection.php", "_blank");
  }

  function submitDeleteSelection() {
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "/delete_multiple.php";

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

      await SelectionState.refresh();

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
      openSelectionPdf();
      return;
    }

    // 3️⃣ PDF sélection
    if (e.target.closest(".btn-pdf-selection")) {
      e.preventDefault();
      openSelectionPdf();
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

  btnDelete.addEventListener("click", async () => {
    await SelectionState.refresh();

    if (!SelectionState.count) return;

    if (!confirm(`Supprimer ${SelectionState.count} recette(s) ?`)) return;

    submitDeleteSelection();
  });

  SelectionState.refresh();
});


}
