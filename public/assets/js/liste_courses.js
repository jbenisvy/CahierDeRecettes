document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('btn-copy-chatgpt');
  const textarea = document.getElementById('prompt-chatgpt');

  if (!btn || !textarea) return;

  btn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(textarea.value);

      btn.textContent = '✅ Prompt copié';
      setTimeout(() => {
        btn.textContent = '🧠 Copier le prompt ChatGPT';
      }, 1500);

    } catch (e) {
      alert('Impossible de copier automatiquement.');
    }
  });
});
