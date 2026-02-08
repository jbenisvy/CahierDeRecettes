document.addEventListener('click', async function (e) {
  const btn = e.target.closest('.btn-favori');
  if (!btn) return;

  // Intercepte l'action par défaut et la propagation pour éviter toute anomalie
  e.preventDefault();
  e.stopPropagation();

  // Si déjà en cours, on ignore pour éviter un double-clic
  if (btn.dataset.busy === '1') return;
  btn.dataset.busy = '1';

  const recetteId = btn.dataset.recetteId;
  if (!recetteId) {
    console.warn('recetteId manquant');
    btn.dataset.busy = '0';
    return;
  }

  // Détermine l'état actuel du favori et inverse immédiatement l'affichage
  const currentlyFavori = btn.classList.contains('is-favori');
  const newFavoriState = !currentlyFavori;
  // Mise à jour visuelle immédiate (pas d'icône intermédiaire)
  btn.classList.toggle('is-favori', newFavoriState);
  // Utilise des symboles d'étoile cohérents : ★ pour favori, ☆ sinon. Cela évite toute
  // confusion avec d'autres pictogrammes (cases à cocher, coche, etc.) et garantit
  // un rendu stable quel que soit le chargement du CSS.
  btn.textContent = newFavoriState ? '★' : '☆';

  try {
    const baseUrl = (window.APP_BASE_URL || '').replace(/\/$/, '');
    const endpoint = baseUrl + '/actions/toggle_favori.php';
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'recette_id=' + encodeURIComponent(recetteId)
    });

    if (!res.ok) {
      console.warn('Erreur HTTP favori :', res.status);
      btn.classList.toggle('is-favori', currentlyFavori);
      btn.textContent = currentlyFavori ? '★' : '☆';
      return;
    }

    let data;
    try {
      data = await res.json();
    } catch (parseErr) {
      console.warn('Réponse non JSON pour favoris');
      btn.classList.toggle('is-favori', currentlyFavori);
      btn.textContent = currentlyFavori ? '★' : '☆';
      return;
    }

    // Si le serveur signale une erreur, on annule la mise à jour visuelle
    if (data.error) {
      console.warn('Action refusée :', data.error);
      // On remet l'état précédent
      btn.classList.toggle('is-favori', currentlyFavori);
      btn.textContent = currentlyFavori ? '★' : '☆';
      return;
    }

    // Le serveur confirme l'état : on aligne l'UI avec la réponse
    const added = data.status === 'added';
    btn.classList.toggle('is-favori', added);
    btn.textContent = added ? '★' : '☆';

  } catch (err) {
    console.error('Erreur réseau ou JS favori :', err);
    // En cas d'erreur réseau, on rétablit l'état précédent
    btn.classList.toggle('is-favori', currentlyFavori);
    btn.textContent = currentlyFavori ? '★' : '☆';
  } finally {
    // Libère le bouton au bout de quelques millisecondes
    setTimeout(() => {
      btn.dataset.busy = '0';
    }, 200);
  }
});
