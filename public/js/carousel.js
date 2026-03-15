function initCarousel() {
    const carouselTrack = document.getElementById("carouselTrack");
    const nextBtn = document.getElementById("nextBtn");
    const prevBtn = document.getElementById("prevBtn");
    const currentSlideTitle = document.getElementById("currentSlideTitle");
    const navDots = Array.from(document.querySelectorAll(".nav-dot"));

    if (!carouselTrack) {
        return;
    }

    const slides = Array.from(carouselTrack.children);
    if (slides.length === 0) {
        return;
    }

    let currentSlideIndex = 0;

    function updateCarousel() {
        slides.forEach((slide, index) => {
            slide.classList.toggle(
                "current-slide",
                index === currentSlideIndex,
            );
        });

        if (navDots.length > 0) {
            navDots.forEach((dot, index) => {
                dot.classList.toggle("active", index === currentSlideIndex);
            });
        }

        if (currentSlideTitle && slides[currentSlideIndex]) {
            currentSlideTitle.textContent =
                slides[currentSlideIndex].dataset.title || "Unknown title";
        }
    }

    if (nextBtn) {
        nextBtn.addEventListener("click", () => {
            currentSlideIndex = (currentSlideIndex + 1) % slides.length;
            updateCarousel();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener("click", () => {
            currentSlideIndex =
                (currentSlideIndex - 1 + slides.length) % slides.length;
            updateCarousel();
        });
    }

    navDots.forEach((dot, index) => {
        dot.addEventListener("click", () => {
            currentSlideIndex = index;
            updateCarousel();
        });
    });

    updateCarousel();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initCarousel);
} else {
    initCarousel();
}
