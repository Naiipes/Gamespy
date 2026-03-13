const carouselTrack = document.getElementById("carouselTrack");
const slides = Array.from(carouselTrack.children);
const nextBtn = document.getElementById("nextBtn");
const prevBtn = document.getElementById("prevBtn");
const navDots = Array.from(document.querySelectorAll(".nav-dot"));

let currentSlideIndex = 0; // We can start at 0 again since there are no "peeking" edge images

function updateCarousel() {
    // 1. Update active slide (CSS handles the fade/swap)
    slides.forEach((slide, index) => {
        slide.classList.toggle("current-slide", index === currentSlideIndex);
    });

    // 2. Update active nav dot
    if (navDots.length > 0) {
        navDots.forEach((dot, index) => {
            dot.classList.toggle("active", index === currentSlideIndex);
        });
    }
}

// Next Button Click - Loops to beginning
nextBtn.addEventListener("click", () => {
    currentSlideIndex = (currentSlideIndex + 1) % slides.length;
    updateCarousel();
});

// Prev Button Click - Loops to end
prevBtn.addEventListener("click", () => {
    currentSlideIndex = (currentSlideIndex - 1 + slides.length) % slides.length;
    updateCarousel();
});

// Dot Navigation
navDots.forEach((dot, index) => {
    dot.addEventListener("click", () => {
        currentSlideIndex = index;
        updateCarousel();
    });
});

// Initialize
updateCarousel();
