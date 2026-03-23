async function removeFromWishlist(btn) 
{
    const gameId = btn.dataset.gameId;
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        .getAttribute("content");

    const response = await fetch(`/wishlist/game/${gameId}`, {
        method: "DELETE",
        headers: { "X-CSRF-TOKEN": csrfToken },
    });

    if (response.ok) {
        btn.closest(".result-card").remove();
    }
}
