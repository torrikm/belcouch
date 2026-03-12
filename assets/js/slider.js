/**
 * JavaScript для управления слайдером на главной странице
 */
App.register("homeSlider", function () {
	const slider = document.querySelector(".slider");
	const slides = document.querySelectorAll(".slide");
	const dots = document.querySelectorAll(".slider-dot");

	if (!slider || slides.length === 0) {
		return;
	}

	let currentSlide = 0;
	const slideCount = slides.length;
	let autoSlideInterval;
	let touchStartX = 0;
	let touchEndX = 0;

	function updateSlider() {
		const offset = -currentSlide * 100;
		slider.style.transform = `translateX(${offset}%)`;

		dots.forEach((dot, index) => {
			dot.classList.toggle("active", index === currentSlide);
		});
	}

	function startAutoSlide() {
		autoSlideInterval = setInterval(nextSlide, 5000);
	}

	function resetAutoSlide() {
		if (autoSlideInterval) {
			clearInterval(autoSlideInterval);
			startAutoSlide();
		}
	}

	function nextSlide() {
		currentSlide = (currentSlide + 1) % slideCount;
		updateSlider();
		resetAutoSlide();
	}

	function prevSlide() {
		currentSlide = (currentSlide - 1 + slideCount) % slideCount;
		updateSlider();
		resetAutoSlide();
	}

	function goToSlide(index) {
		if (index >= 0 && index < slideCount) {
			currentSlide = index;
			updateSlider();
			resetAutoSlide();
		}
	}

	function handleSwipe() {
		const swipeThreshold = 50;

		if (touchEndX < touchStartX - swipeThreshold) {
			nextSlide();
		}

		if (touchEndX > touchStartX + swipeThreshold) {
			prevSlide();
		}
	}

	updateSlider();

	dots.forEach((dot) => {
		dot.addEventListener("click", function () {
			const slideIndex = parseInt(this.getAttribute("data-index"), 10);
			goToSlide(slideIndex);
		});
	});

	startAutoSlide();

	slider.addEventListener(
		"touchstart",
		function (event) {
			touchStartX = event.changedTouches[0].screenX;
		},
		false,
	);

	slider.addEventListener(
		"touchend",
		function (event) {
			touchEndX = event.changedTouches[0].screenX;
			handleSwipe();
		},
		false,
	);
});
