App.register("proposalsFiltersModal", function () {
	const modal = document.getElementById("filters-modal");
	const button = document.getElementById("open-filters-modal");
	const closeButton = document.querySelector(".close-modal");
	const mobileForm = document.getElementById("mobile-filters-form");

	if (!modal || !button || !closeButton) {
		return;
	}

	function closeModal() {
		if (window.App.modal && typeof window.App.modal.close === "function") {
			window.App.modal.close(modal);
			return;
		}
		modal.classList.remove("show");
		document.body.style.overflow = "";
	}

	if (mobileForm) {
		mobileForm.querySelectorAll(".guest-range").forEach((rangeBox) => {
			const minLimit = Number(rangeBox.dataset.min || 1);
			const maxLimit = Number(rangeBox.dataset.max || 20);
			const minSlider = rangeBox.querySelector(".guest-range-min");
			const maxSlider = rangeBox.querySelector(".guest-range-max");
			const minInput = rangeBox.querySelector('input[name="min_guests"]');
			const maxInput = rangeBox.querySelector('input[name="max_guests"]');
			const minValue = rangeBox.querySelector(".guest-range-min-value");
			const maxValue = rangeBox.querySelector(".guest-range-max-value");

			if (!minSlider || !maxSlider || !minInput || !maxInput) {
				return;
			}

			const syncRange = function (source) {
				let min = Number(minSlider.value);
				let max = Number(maxSlider.value);

				if (min > max) {
					if (source === "min") {
						max = min;
						maxSlider.value = String(max);
					} else {
						min = max;
						minSlider.value = String(min);
					}
				}

				min = Math.max(minLimit, Math.min(min, maxLimit));
				max = Math.max(minLimit, Math.min(max, maxLimit));

				minInput.value = String(min);
				maxInput.value = String(max);
				if (minValue) minValue.textContent = String(min);
				if (maxValue) maxValue.textContent = String(max);
			};

			syncRange("");
			minSlider.addEventListener("input", function () {
				syncRange("min");
			});
			maxSlider.addEventListener("input", function () {
				syncRange("max");
			});
		});
	}

	button.addEventListener("click", function () {
		if (window.App.modal && typeof window.App.modal.open === "function") {
			window.App.modal.open(modal);
			return;
		}
		modal.classList.add("show");
		document.body.style.overflow = "hidden";
	});

	closeButton.addEventListener("click", function () {
		closeModal();
	});
});
