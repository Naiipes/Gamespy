async function removeFromWishlist(btn) {
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

        const hasRemainingCards =
            wishlistResults?.querySelector(".result-card");
        if (!hasRemainingCards && emptyMessage) {
            emptyMessage.hidden = false;
        }
    }
}

function closeNotificationDropdowns(exceptCard = null) {
    document.querySelectorAll(".notify-card").forEach((notifyCard) => {
        const dropdown = notifyCard.querySelector(".notification-dropdown");
        const toggle = notifyCard.querySelector("[data-notify-toggle]");
        const shouldStayOpen = exceptCard && notifyCard === exceptCard;

        notifyCard.classList.toggle("is-open", shouldStayOpen);

        if (dropdown) {
            dropdown.hidden = !shouldStayOpen;
        }

        if (toggle) {
            toggle.setAttribute(
                "aria-expanded",
                shouldStayOpen ? "true" : "false",
            );
        }
    });
}

function openWishlistCard(card) {
    const url = card?.dataset.dealUrl;

    if (url) {
        window.open(url, "_blank", "noopener");
    }
}

function updateNotificationDropdownState(dropdown) {
    const enabledInput = dropdown?.querySelector(".notify-enabled-input");
    const emailInput = dropdown?.querySelector(".notify-email-input");
    const targetToggle = dropdown?.querySelector(".notify-target-toggle");
    const priceInput = dropdown?.querySelector(".notify-price-input");

    if (!enabledInput || !emailInput || !targetToggle || !priceInput) {
        return;
    }

    emailInput.disabled = !enabledInput.checked;
    targetToggle.disabled = !enabledInput.checked;

    if (!enabledInput.checked) {
        emailInput.checked = false;
        targetToggle.checked = false;
    }

    priceInput.disabled = !enabledInput.checked || !targetToggle.checked;
}

async function saveNotificationSettings(btn) {
    const dropdown = btn.closest(".notification-dropdown");
    const notifyCard = btn.closest(".notify-card");
    const enabledInput = dropdown?.querySelector(".notify-enabled-input");
    const emailInput = dropdown?.querySelector(".notify-email-input");
    const targetToggle = dropdown?.querySelector(".notify-target-toggle");
    const priceInput = dropdown?.querySelector(".notify-price-input");
    const feedback = dropdown?.querySelector(".notify-feedback");

    if (
        !dropdown ||
        !enabledInput ||
        !emailInput ||
        !targetToggle ||
        !priceInput
    ) {
        return;
    }

    const targetPrice = targetToggle.checked
        ? parseFloat(priceInput.value || "0")
        : 0;
    const gameId = btn.dataset.gameId;
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

    if (!gameId || !csrfToken) {
        if (feedback) {
            feedback.hidden = false;
            feedback.textContent = "Unable to save settings.";
        }
        return;
    }

    if (targetToggle.checked && (targetPrice > 1000 || targetPrice < 0)) {
        if (feedback) {
            feedback.hidden = false;
            feedback.textContent = "Enter a valid target price.";
        }
        return;
    }

    if (feedback) {
        feedback.hidden = false;
        feedback.textContent = "Saving...";
    }

    try {
        const response = await fetch(`/wishlist/game/${gameId}/target-price`, {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken,
                Accept: "application/json",
            },
            body: JSON.stringify({
                notifications_enabled: enabledInput.checked,
                notify_by_email: emailInput.checked,
                use_target_price: targetToggle.checked,
                target_price: targetPrice,
            }),
        });

        if (!response.ok) {
            throw new Error("Request failed");
        }

        if (feedback) {
            feedback.textContent = "Settings saved.";
        }
    } catch (_error) {
        if (feedback) {
            feedback.textContent = "Could not save settings.";
        }
        return;
    }

    const toggle = notifyCard?.querySelector(".notify-btn");
    if (toggle) {
        toggle.classList.toggle("notifications-enabled", enabledInput.checked);
    }

    setTimeout(() => {
        if (feedback) {
            feedback.hidden = true;
        }
    }, 2000);
}

function initWishlistNotificationControls() {
    document.querySelectorAll("[data-notify-toggle]").forEach((toggle) => {
        toggle.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();

            const notifyCard = toggle.closest(".notify-card");
            const dropdown = notifyCard?.querySelector(
                ".notification-dropdown",
            );
            const isOpening = dropdown?.hidden ?? false;

            closeNotificationDropdowns(isOpening ? notifyCard : null);
        });
    });

    document
        .querySelectorAll(".notify-enabled-input, .notify-target-toggle")
        .forEach((input) => {
            input.addEventListener("change", () => {
                const dropdown = input.closest(".notification-dropdown");
                updateNotificationDropdownState(dropdown);
            });
        });

    document.querySelectorAll(".notify-save-btn").forEach((btn) => {
        btn.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            saveNotificationSettings(btn);
        });
    });

    document.addEventListener("click", (event) => {
        if (!event.target.closest(".notify-card")) {
            closeNotificationDropdowns();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeNotificationDropdowns();
        }
    });

    document.querySelectorAll(".notification-dropdown").forEach((dropdown) => {
        updateNotificationDropdownState(dropdown);
    });
}

function initWishlistCardLinks() {
    document.querySelectorAll(".result-card[data-deal-url]").forEach((card) => {
        card.addEventListener("click", (event) => {
            if (
                event.target.closest(".notify-card") ||
                event.target.closest(".result-wishlist-btn")
            ) {
                return;
            }

            openWishlistCard(card);
        });

        card.addEventListener("keydown", (event) => {
            if (event.key !== "Enter" && event.key !== " ") {
                return;
            }

            if (
                event.target.closest(".notify-card") ||
                event.target.closest(".result-wishlist-btn")
            ) {
                return;
            }

            event.preventDefault();
            openWishlistCard(card);
        });
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        initWishlistNotificationControls();
        initWishlistCardLinks();
    });
} else {
    initWishlistNotificationControls();
    initWishlistCardLinks();
}
