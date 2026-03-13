const carouselTrack = document.getElementById("carouselTrack");
const slides = Array.from(carouselTrack.children);
const nextBtn = document.getElementById("nextBtn");
const prevBtn = document.getElementById("prevBtn");
const navDots = Array.from(document.querySelectorAll(".nav-dot"));

// Start at index 1 so the middle slide is active on load
let currentSlideIndex = 1;

function updateCarousel() {
// 1. Update active slide opacity
slides.forEach((slide, index) => {
slide.classList.toggle("current-slide", index === currentSlideIndex);
});

// 2. Update active nav dot
if (navDots.length > 0) {
navDots.forEach((dot, index) => {
dot.classList.toggle("active", index === currentSlideIndex);
});
}

// 3. Move the track
carouselTrack.style.transform = `translateX(calc(-${
currentSlideIndex * 60
}% - ${currentSlideIndex * 20}px + 20%))`;
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
