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
    const uploadPhotoDesktop = document.getElementById("photo-upload-desktop");
    const uploadPhotoGallery = document.getElementById("photo-upload-gallery");
    const uploadPhotoCamera = document.getElementById("photo-upload-camera");
    const photoDesktopWrap = document.getElementById("photo-upload-desktop-wrap");
    const photoMobileWrap = document.getElementById("photo-upload-mobile-wrap");
    const photoOpenGalleryBtn = document.getElementById("photo-open-gallery");
    const photoOpenCameraBtn = document.getElementById("photo-open-camera");
    const photoMobileStatus = document.getElementById("photo-mobile-status");

    if (
        uploadPhotoForm &&
        uploadPhotoDesktop &&
        uploadPhotoGallery &&
        uploadPhotoCamera &&
        photoDesktopWrap &&
        photoMobileWrap
    ) {
        function isMobileDevice() {
            const smallScreen = window.matchMedia("(max-width: 767px)").matches;
            const touchDevice = navigator.maxTouchPoints > 1 && window.matchMedia("(pointer:coarse)").matches;
            const uaMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || "");
            return smallScreen || touchDevice || uaMobile;
        }

        function applyUploadMode() {
            const mobile = isMobileDevice();
            photoDesktopWrap.style.display = mobile ? "none" : "block";
            photoMobileWrap.style.display = mobile ? "block" : "none";
        }

        function updateMobileStatus() {
            if (!photoMobileStatus) return;
            const hasGallery = uploadPhotoGallery.files && uploadPhotoGallery.files.length > 0;
            const hasCamera = uploadPhotoCamera.files && uploadPhotoCamera.files.length > 0;

            if (hasGallery) {
                photoMobileStatus.textContent = "Photo sélectionnée depuis la pellicule/fichiers.";
                return;
            }
            if (hasCamera) {
                photoMobileStatus.textContent = "Photo prise avec l'appareil.";
                return;
            }
            photoMobileStatus.textContent = "Aucune photo sélectionnée.";
        }

        applyUploadMode();
        window.addEventListener("resize", applyUploadMode);

        if (photoOpenGalleryBtn) {
            photoOpenGalleryBtn.addEventListener("click", function () {
                uploadPhotoGallery.click();
            });
        }
        if (photoOpenCameraBtn) {
            photoOpenCameraBtn.addEventListener("click", function () {
                uploadPhotoCamera.click();
            });
        }
        uploadPhotoGallery.addEventListener("change", updateMobileStatus);
        uploadPhotoCamera.addEventListener("change", updateMobileStatus);

        uploadPhotoForm.addEventListener("submit", function (e) {
            const isMobile = isMobileDevice();
            const hasDesktop = uploadPhotoDesktop.files && uploadPhotoDesktop.files.length > 0;
            const hasGallery = uploadPhotoGallery.files && uploadPhotoGallery.files.length > 0;
            const hasCamera = uploadPhotoCamera.files && uploadPhotoCamera.files.length > 0;

            const valid = isMobile ? (hasGallery || hasCamera) : hasDesktop;
            if (!valid) {
                e.preventDefault();
                alert(isMobile
                    ? "Veuillez choisir une photo depuis la pellicule/fichiers ou prendre une photo."
                    : "Veuillez choisir une photo sur votre ordinateur.");
            }
        });
    }
});
