App.register("listingCardLink", function () {
	const INTERACTIVE_SELECTOR =
		'a, button, input, select, textarea, label, [role="button"]';

	document.addEventListener("click", function (event) {
		const card = event.target.closest(".listing-card[data-href]");
		if (!card) {
			return;
		}

		if (event.target.closest(INTERACTIVE_SELECTOR)) {
			return;
		}

		const href = card.getAttribute("data-href");
		if (!href) {
			return;
		}

		window.location.href = href;
	});
});
