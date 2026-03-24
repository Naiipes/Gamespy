async function initWishlistState() 
{
    const res = await fetch("/api/wishlist/ids", {
        headers: { Accept: "application/json" },
    }).catch(() => null);

    if (!res || !res.ok) {
        return;
    }

    const list = await res.json().catch(() => []);
    if (!list.length) {
        return;
    }

    const byGameId = new Set(list.map((item) => String(item.game_id)));
    const byCheapshark = new Set(
        list.map((item) => String(item.cheapshark_id)).filter(Boolean),
    );
    const csToGameId = Object.fromEntries(
        list
            .filter((item) => item.cheapshark_id)
            .map((item) => [String(item.cheapshark_id), String(item.game_id)]),
    );

    document.querySelectorAll(".wishlist-btn").forEach((btn) => {
        const gameId = btn.dataset.gameId;
        const cheapsharkId = btn.dataset.cheapsharkId;

        if (
            (gameId && byGameId.has(String(gameId))) ||
            (cheapsharkId && byCheapshark.has(String(cheapsharkId)))
        ) {
            btn.classList.add("in-wishlist");

            if (!gameId && cheapsharkId && csToGameId[cheapsharkId]) {
                btn.dataset.resolvedGameId = csToGameId[cheapsharkId];
            }
        }
    });
}

async function addToWishlist(btn) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrf) {
        return;
    }

    const gameId = btn.dataset.gameId;
    const cheapsharkId = btn.dataset.cheapsharkId;
    const steamAppId = btn.dataset.steamAppId;
    const price = parseFloat(btn.dataset.price || "0");
    const currentPrice = Number.isFinite(price) ? price : 0;

    if (btn.classList.contains("in-wishlist")) {
        const id = btn.dataset.resolvedGameId || gameId;
        if (!id) {
            return;
        }

        const res = await fetch(`/wishlist/game/${id}`, {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": csrf,
                Accept: "application/json",
            },
        }).catch(() => null);

        if (res && res.ok) {
            btn.classList.remove("in-wishlist");
            delete btn.dataset.resolvedGameId;
        }

        return;
    }

    let body;
    if (gameId) {
        body = {
            game_id: parseInt(gameId),
            target_price: 0,
        };
    } else if (cheapsharkId) {
        body = {
            cheapshark_id: cheapsharkId,
            steamAppID: steamAppId || null,
            title: btn.dataset.title || "",
            thumb: btn.dataset.thumb || "",
            current_price: currentPrice,
            target_price: 0,
        };
    } else {
        return;
    }

    const res = await fetch("/wishlist", {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": csrf,
            "Content-Type": "application/json",
            Accept: "application/json",
        },
        body: JSON.stringify(body),
    }).catch(() => null);

    if (!res) {
        return;
    }

    if (res.status === 201 || res.status === 409) {
        btn.classList.add("in-wishlist");
        const data = await res.json().catch(() => null);

        if (data?.game_id) {
            btn.dataset.resolvedGameId = data.game_id;
        }
    } else if (res.status === 401) {
        window.location.href = "/login";
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initWishlistState);
} else {
    initWishlistState();
}
