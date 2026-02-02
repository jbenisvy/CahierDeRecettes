document.addEventListener('click', async function (e) {
  const btn = e.target.closest('.btn-favori');
  if (!btn) return;

  // 🔒 STOP propagation + comportement par défaut
  e.preventDefault();
  e.stopPropagation();

  // 🔒 anti double trigger
  if (btn.dataset.busy === '1') return;
  btn.dataset.busy = '1';

  const recetteId = btn.dataset.recetteId;
  if (!recetteId) {
    console.warn('recetteId manquant');
    btn.dataset.busy = '0';
    return;
  }

  try {
    const res = await fetch('/actions/toggle_favori.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'recette_id=' + encodeURIComponent(recetteId)
    });

    const data = await res.json();

    if (data.error) {
      console.warn('Action refusée :', data.error);
      return;
    }

    const added = data.status === 'added';
    btn.textContent = added ? '⭐' : '☆';
    btn.classList.toggle('is-favori', added);

  } catch (err) {
    console.error('Erreur JS favori :', err);
  } finally {
    setTimeout(() => {
      btn.dataset.busy = '0';
    }, 200);
  }
});
