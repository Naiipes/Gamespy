async function removeFromWishlist(btn) 
{
    const gameId = btn.dataset.gameId;
    const wishlistResults = document.getElementById("wishlist-results");
    const emptyMessage = document.getElementById("wishlist-empty-message");
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        .getAttribute("content");

    const response = await fetch(`/wishlist/game/${gameId}`, {
        method: "DELETE",
        headers: { "X-CSRF-TOKEN": csrfToken },
    });

    if (response.ok) {
        const card = btn.closest(".result-card");

        if (card) {
            card.remove();
        }

        const hasRemainingCards = wishlistResults?.querySelector(".result-card");
        if (!hasRemainingCards && emptyMessage) {
            emptyMessage.hidden = false;
        }
    }
}
