/**
 * Скрипт для работы с фильтрами на странице предложений
 * Обрабатывает фильтрацию без перезагрузки страницы с использованием AJAX
 */

App.register("proposalsFilter", function () {
	const filtersForm = document.getElementById("filters-form");
	const listingsContent = document.querySelector(".listings-content");
	const resetFiltersBtn = document.querySelector(".reset-filters");
	let currentPage = 1;

	if (!filtersForm || !listingsContent) {
		return;
	}

	function initGuestRanges(form, onRangeChange) {
		const ranges = form.querySelectorAll(".guest-range");
		ranges.forEach((rangeBox) => {
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
			minSlider.addEventListener("change", onRangeChange);
			maxSlider.addEventListener("change", onRangeChange);
		});
	}

	function applyFilters() {
		listingsContent.innerHTML =
			'<div class="loading-indicator">Загрузка результатов...</div>';

		const formData = new FormData(filtersForm);
		formData.append("page", currentPage);

		$.ajax({
			xhrFields: { withCredentials: true },
			url: API_BASE_URL + "/get_filtered_listings.php",
			type: "POST",
			data: formData,
			processData: false,
			contentType: false,
			dataType: "json",
			success: function (data) {
				if (data.success) {
					listingsContent.innerHTML = data.html;
					updateUrlWithFilters(formData);
					setupPagination();
					updateResetButton(formData);
					document.dispatchEvent(new CustomEvent("contentUpdated"));
					return;
				}

				console.error("Ошибка при получении данных");
			},
			error: function (xhr, status, error) {
				console.error("Ошибка при выполнении запроса:", error);
				listingsContent.innerHTML =
					'<div class="error-message">Произошла ошибка при загрузке данных. Пожалуйста, попробуйте позже.</div>';
			},
		});
	}

	function updateUrlWithFilters(formData) {
		const params = new URLSearchParams();
		const defaultMin = String(
			filtersForm.querySelector(".guest-range")?.dataset.min || "1",
		);
		const defaultMax = String(
			filtersForm.querySelector(".guest-range")?.dataset.max || "20",
		);

		for (const [key, value] of formData.entries()) {
			if (key === "min_guests" && String(value) === defaultMin) {
				continue;
			}
			if (key === "max_guests" && String(value) === defaultMax) {
				continue;
			}

			if (value && key !== "page") {
				if (key.endsWith("[]")) {
					params.append(key.replace("[]", ""), value);
				} else {
					params.set(key, value);
				}
			}
		}

		if (currentPage > 1) {
			params.set("page", currentPage);
		}

		const newUrl =
			window.location.pathname +
			(params.toString() ? "?" + params.toString() : "");
		window.history.pushState({}, "", newUrl);
	}

	function setupPagination() {
		const paginationButtons = document.querySelectorAll(".pagination-btn");

		paginationButtons.forEach((button) => {
			button.addEventListener("click", function () {
				const page = parseInt(this.getAttribute("data-page"), 10);
				if (page && page !== currentPage) {
					currentPage = page;
					window.scrollTo({
						top: 0,
						behavior: "smooth",
					});
					applyFilters();
				}
			});
		});
	}

	function updateResetButton(formData) {
		const resetBtn = document.querySelector(".reset-filters");
		let hasActiveFilters = false;
		const defaultMin = String(
			filtersForm.querySelector(".guest-range")?.dataset.min || "1",
		);
		const defaultMax = String(
			filtersForm.querySelector(".guest-range")?.dataset.max || "20",
		);

		for (const [key, value] of formData.entries()) {
			if (key === "min_guests" && String(value) === defaultMin) {
				continue;
			}
			if (key === "max_guests" && String(value) === defaultMax) {
				continue;
			}

			if (value && key !== "page") {
				hasActiveFilters = true;
				break;
			}
		}

		if (resetBtn) {
			resetBtn.style.display = hasActiveFilters ? "block" : "none";
		}
	}

	const filterInputs = filtersForm.querySelectorAll(
		'select, input[type="number"], input[type="text"]',
	);
	filterInputs.forEach((input) => {
		input.addEventListener("change", function () {
			currentPage = 1;
			applyFilters();
		});
	});

	const checkboxes = filtersForm.querySelectorAll('input[type="checkbox"]');
	checkboxes.forEach((checkbox) => {
		checkbox.addEventListener("change", function () {
			currentPage = 1;
			applyFilters();
		});
	});

	if (resetFiltersBtn) {
		resetFiltersBtn.addEventListener("click", function (e) {
			e.preventDefault();

			filtersForm
				.querySelectorAll("select")
				.forEach((select) => (select.value = "0"));
			filtersForm
				.querySelectorAll('input[type="number"], input[type="text"]')
				.forEach((input) => (input.value = ""));
			filtersForm
				.querySelectorAll('input[type="checkbox"]')
				.forEach((checkbox) => (checkbox.checked = false));
			filtersForm.querySelectorAll(".guest-range").forEach((rangeBox) => {
				const minLimit = Number(rangeBox.dataset.min || 1);
				const maxLimit = Number(rangeBox.dataset.max || 20);
				const minSlider = rangeBox.querySelector(".guest-range-min");
				const maxSlider = rangeBox.querySelector(".guest-range-max");
				const minInput = rangeBox.querySelector(
					'input[name="min_guests"]',
				);
				const maxInput = rangeBox.querySelector(
					'input[name="max_guests"]',
				);
				const minValue = rangeBox.querySelector(
					".guest-range-min-value",
				);
				const maxValue = rangeBox.querySelector(
					".guest-range-max-value",
				);

				if (minSlider) minSlider.value = String(minLimit);
				if (maxSlider) maxSlider.value = String(maxLimit);
				if (minInput) minInput.value = String(minLimit);
				if (maxInput) maxInput.value = String(maxLimit);
				if (minValue) minValue.textContent = String(minLimit);
				if (maxValue) maxValue.textContent = String(maxLimit);
			});

			currentPage = 1;
			applyFilters();
		});
	}

	initGuestRanges(filtersForm, function () {
		currentPage = 1;
		applyFilters();
	});

	setupPagination();

	if (window.location.search) {
		const urlParams = new URLSearchParams(window.location.search);
		const hasOnlyRegion =
			urlParams.has("region") &&
			Array.from(urlParams.keys()).length === 1;

		if (!hasOnlyRegion) {
			if (urlParams.has("page")) {
				currentPage = parseInt(urlParams.get("page"), 10) || 1;
			}
			setTimeout(applyFilters, 100);
		}
	}
});
