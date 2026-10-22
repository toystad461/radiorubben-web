// An unavailable crest must never leave a broken-image icon in the match card.
(() => {
    const hideBroken = image => { if (image.complete && !image.naturalWidth) image.hidden = true; };
    document.querySelectorAll('.rrfr-week-team img').forEach(image => {
        image.addEventListener('error', () => { image.hidden = true; });
        hideBroken(image);
    });
})();
