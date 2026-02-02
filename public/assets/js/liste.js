// liste.js — page liste (tri colonnes + (ancien) checkbox + toggle boutons)
document.addEventListener("DOMContentLoaded", () => {

  // ===============================
  // (ANCIEN) CHECKBOX MULTI-DELETE
  // ===============================
  const form = document.getElementById("form-multi-delete");
  const btnDelete = document.getElementById("btn-delete-selected");

  if (form && btnDelete) {
    const refresh = () => {
      const boxes = form.querySelectorAll('input[type="checkbox"][name="ids[]"]');
      const anyChecked = Array.from(boxes).some((b) => b.checked);
      btnDelete.disabled = !anyChecked;
    };

    form.addEventListener("change", refresh);
    refresh();
  }

  // ===============================
  // TRI CÔTÉ CLIENT (TABLE LISTE)
  // ===============================
  const table = document.querySelector(".recettes-table");

  if (table) {
    const tbody = table.querySelector("tbody");
    const headers = table.querySelectorAll("thead th[data-sort]");

    if (tbody && headers.length > 0) {
      headers.forEach((th) => th.classList.add("is-sortable"));

      let state = { key: null, asc: true };

      const getCellValue = (row, idx) => {
        const cell = row.children[idx];
        if (!cell) return "";

        // priorité au tri numérique si présent
        if (cell.dataset.sortValue !== undefined && cell.dataset.sortValue !== "") {
          const n = Number(cell.dataset.sortValue);
          return Number.isNaN(n) ? 0 : n;
        }

        return (cell.innerText || "").trim().toLowerCase();
      };

      headers.forEach((th) => {
        th.addEventListener("click", () => {
          const key = th.dataset.sort || "";
          const idx = Array.from(th.parentElement.children).indexOf(th);

          state.asc = state.key === key ? !state.asc : true;
          state.key = key;

          const rows = Array.from(tbody.querySelectorAll("tr"));
          rows.sort((a, b) => {
            const av = getCellValue(a, idx);
            const bv = getCellValue(b, idx);

            if (av < bv) return state.asc ? -1 : 1;
            if (av > bv) return state.asc ? 1 : -1;
            return 0;
          });

          rows.forEach((r) => tbody.appendChild(r));
        });
      });
    }
  }



});
