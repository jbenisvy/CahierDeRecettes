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

    if (selectCuisson && inputAutre) {
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
    }

    /* =========================
       Génération photo IA
       ========================= */
    const aiImageForm = document.getElementById("ai-image-form");
    const aiImageBtn = document.getElementById("ai-image-btn");

    if (aiImageForm && aiImageBtn) {
        aiImageForm.addEventListener("submit", function (e) {
            const ok = window.confirm(
                "Générer une image IA pour cette recette ? Cette action peut engendrer un coût API."
            );
            if (!ok) {
                e.preventDefault();
                return;
            }

            aiImageBtn.disabled = true;
            aiImageBtn.textContent = "⏳ Génération en cours...";
        });
    }

    /* =========================
       Upload photo (fichier/camera)
       ========================= */
    const uploadPhotoForm = document.getElementById("upload-photo-form");
    const uploadPhotoFile = document.getElementById("photo-upload-file");
    const uploadPhotoCamera = document.getElementById("photo-upload-camera");

    if (uploadPhotoForm && uploadPhotoFile && uploadPhotoCamera) {
        uploadPhotoForm.addEventListener("submit", function (e) {
            const hasFile = uploadPhotoFile.files && uploadPhotoFile.files.length > 0;
            const hasCamera = uploadPhotoCamera.files && uploadPhotoCamera.files.length > 0;

            if (!hasFile && !hasCamera) {
                e.preventDefault();
                alert("Veuillez choisir une photo ou prendre une photo.");
            }
        });
    }
});
