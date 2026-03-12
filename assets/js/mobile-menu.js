App.register("mobileMenu", function () {
	const mobileMenuToggle = document.querySelector(".mobile-menu-toggle");
	const mainNavigation = document.querySelector(".main-navigation");

	if (!mobileMenuToggle || !mainNavigation) {
		return;
	}

	mobileMenuToggle.addEventListener("click", function () {
		mainNavigation.classList.toggle("active");
		const bars = this.querySelectorAll(".bar");
		bars.forEach(function (bar) {
			bar.classList.toggle("active");
		});
	});
});
