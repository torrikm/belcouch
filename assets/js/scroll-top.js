(function () {
	const button = document.getElementById("global-scroll-top");
	if (!button) {
		return;
	}

	const TOGGLE_OFFSET = 240;
	let rafId = 0;

	function updateVisibility() {
		rafId = 0;
		const shouldShow = window.scrollY > TOGGLE_OFFSET;
		button.classList.toggle("is-visible", shouldShow);
	}

	function onScroll() {
		if (rafId) {
			return;
		}
		rafId = window.requestAnimationFrame(updateVisibility);
	}

	button.addEventListener("click", function () {
		window.scrollTo({
			top: 0,
			behavior: "smooth",
		});
	});

	window.addEventListener("scroll", onScroll, { passive: true });
	updateVisibility();
})();
