document.addEventListener('keydown', function (e) {
    const cards = Array.from(document.querySelectorAll('.gallery-card a'));
    if (!cards.length) return;

    const currentIndex = cards.indexOf(document.activeElement);
    if (currentIndex === -1) return;

    let nextIndex = null;

    switch (e.key) {
        case 'ArrowRight':
        case 'ArrowDown':
            nextIndex = Math.min(currentIndex + 1, cards.length - 1);
            break;

        case 'ArrowLeft':
        case 'ArrowUp':
            nextIndex = Math.max(currentIndex - 1, 0);
            break;

        case 'Enter':
            document.activeElement.click();
            break;
    }

    if (nextIndex !== null) {
        e.preventDefault();
        cards[nextIndex].focus();
    }
});
