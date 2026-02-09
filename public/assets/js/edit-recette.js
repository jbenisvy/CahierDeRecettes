document.addEventListener("DOMContentLoaded", function () {

    /* =========================
       Gestion des TAGS
       ========================= */
    const tagInput = document.getElementById("tag-input");
    const addBtn = document.getElementById("add-tag-btn");
    const tagSelect = document.getElementById("tag-select");
    const tagSelectError = document.getElementById("tag-select-error");
    const tagFilterInput = document.getElementById("tag-filter-input");

    if (tagInput && addBtn) {
        addBtn.addEventListener("click", function (e) {
            if (tagInput.value.trim() === "") {
                e.preventDefault(); // empêche l'appel à add_tag.php
                alert("Veuillez saisir un tag avant de cliquer sur +");
                tagInput.focus();
            }
        });
    }

    if (tagSelect) {
        const selectForm = tagSelect.closest("form");
        if (selectForm) {
            selectForm.addEventListener("submit", function (e) {
                if (tagSelect.value.trim() === "") {
                    e.preventDefault();
                    if (tagSelectError) {
                        tagSelectError.style.display = "block";
                    }
                    tagSelect.focus();
                }
            });
        }
        tagSelect.addEventListener("change", function () {
            if (tagSelectError) tagSelectError.style.display = "none";
        });
    }

    if (tagSelect && tagFilterInput) {
        const options = Array.from(tagSelect.options);
        tagFilterInput.addEventListener("input", function () {
            const q = tagFilterInput.value.trim().toLowerCase();
            options.forEach((opt) => {
                if (opt.value === "") return;
                const match = opt.text.toLowerCase().includes(q);
                opt.style.display = match ? "block" : "none";
            });
        });
    }

    /* =========================
       Gestion TYPE DE CUISSON
       ========================= */
    const selectCuisson = document.getElementById("type_cuisson");
    const inputAutre = document.getElementById("type_cuisson_autre");

    // Sécurité : si les champs n'existent pas (autre page)
    if (!selectCuisson || !inputAutre) return;

    function toggleTypeCuissonAutre() {
        if (selectCuisson.value === "__autre__") {
            inputAutre.style.display = "block";
            inputAutre.focus();
        } else {
            inputAutre.style.display = "none";
            inputAutre.value = "";
        }
    }

    // État initial (édition d’une recette existante)
    toggleTypeCuissonAutre();

    // Réaction au changement utilisateur
    selectCuisson.addEventListener("change", toggleTypeCuissonAutre);
});
