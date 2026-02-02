document.addEventListener("DOMContentLoaded", function () {

    /* =========================
       Gestion des TAGS
       ========================= */
    const tagInput = document.getElementById("tag-input");
    const addBtn = document.getElementById("add-tag-btn");

    if (tagInput && addBtn) {
        addBtn.addEventListener("click", function (e) {
            if (tagInput.value.trim() === "") {
                e.preventDefault(); // empêche l'appel à add_tag.php
                alert("Veuillez saisir un tag avant de cliquer sur +");
                tagInput.focus();
            }
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
