App.register("cityAutocomplete", function () {
	const configs = [
		{
			inputSelector: "#city",
			regionSelector: "#region_id",
			formSelector: "#edit-profile-form",
			required: false,
			emptyMessage: "Выберите населенный пункт из подсказок",
		},
		{
			inputSelector: "#listing_city",
			regionSelector: "#listing_region_id",
			formSelector: "#housing-form",
			required: true,
			emptyMessage: "Выберите населенный пункт из подсказок",
		},
	];
	const initializedInputs = new WeakSet();

	let belarusCities = [];
	let isCitiesLoading = false;
	let citiesLoadPromise = null;

	function loadCities() {
		if (belarusCities.length > 0) return Promise.resolve(belarusCities);
		if (citiesLoadPromise) return citiesLoadPromise;

		isCitiesLoading = true;
		citiesLoadPromise = fetch("/assets/js/cities.json")
			.then((response) => response.json())
			.then((data) => {
				belarusCities = data;
				isCitiesLoading = false;
				return data;
			})
			.catch((error) => {
				console.error("Ошибка загрузки городов:", error);
				isCitiesLoading = false;
				return [];
			});

		return citiesLoadPromise;
	}

	function normalizeText(value) {
		return String(value || "")
			.trim()
			.toLowerCase()
			.replace(/ё/g, "е")
			.replace("брестая", "брестская"); // На всякий случай, так как в SQL опечатка 'Брестая область'
	}

	function getRegionHint(regionSelect) {
		if (!regionSelect) return "";
		const option = regionSelect.options[regionSelect.selectedIndex];
		if (!option || !option.value) return ""; // Если value пустое (например "Выберите область"), возвращаем пустую строку
		return option.textContent.trim();
	}

	function matchesRegion(item, regionHint) {
		// Если регион не выбран - разрешаем искать по всей базе
		if (!regionHint) return true;

		const normalizedRegionHint = normalizeText(regionHint);
		const normalizedRegionName = normalizeText(item.r || "");
		return (
			normalizedRegionName.indexOf(normalizedRegionHint) !== -1 ||
			normalizedRegionHint.indexOf(normalizedRegionName) !== -1
		);
	}

	function autoSelectRegion(regionName, regionSelect) {
		if (!regionSelect || !regionName) return;

		const normalizedRegionName = normalizeText(regionName);

		for (let i = 0; i < regionSelect.options.length; i++) {
			const option = regionSelect.options[i];
			if (!option.value) continue;

			const optionLabel = normalizeText(option.textContent.trim());

			if (
				optionLabel === normalizedRegionName ||
				normalizedRegionName.indexOf(optionLabel) !== -1 ||
				optionLabel.indexOf(normalizedRegionName) !== -1
			) {
				if (regionSelect.selectedIndex !== i) {
					regionSelect.selectedIndex = i;
					// Триггерим событие change для других скриптов
					regionSelect.dispatchEvent(
						new Event("change", { bubbles: true }),
					);
				}
				break;
			}
		}
	}

	function ensureWrapper(input) {
		let wrapper = input.parentElement;
		if (wrapper && wrapper.classList.contains("city-autocomplete")) {
			return wrapper;
		}
		wrapper = document.createElement("div");
		wrapper.className = "city-autocomplete";
		input.parentNode.insertBefore(wrapper, input);
		wrapper.appendChild(input);
		return wrapper;
	}

	function initAutocomplete(config) {
		const input = document.querySelector(config.inputSelector);
		const regionSelect = document.querySelector(config.regionSelector);
		const form = document.querySelector(config.formSelector);
		if (!input || !form || initializedInputs.has(input)) return;
		initializedInputs.add(input);

		// Запускаем предзагрузку городов, как только инпут инициализирован
		loadCities();

		const wrapper = ensureWrapper(input);
		let list = wrapper.querySelector(".city-autocomplete-list");
		if (!list) {
			list = document.createElement("div");
			list.className = "city-autocomplete-list";
			list.hidden = true; // Скрываем по умолчанию
			wrapper.appendChild(list);
		}

		let debounceTimer = null;
		let suggestions = [];
		let isSelecting = false;
		let isRegionAutoSelecting = false;

		if ((input.value || "").trim()) {
			input.dataset.cityConfirmed = "true";
		}

		function closeList() {
			list.innerHTML = "";
			list.hidden = true;
			wrapper.classList.remove("is-open");
		}

		function openList() {
			if (!list.innerHTML.trim()) return;
			list.hidden = false;
			wrapper.classList.add("is-open");
		}

		function selectSuggestion(item) {
			const cityName = item.n;
			if (!cityName) return;
			input.value = cityName;
			input.dataset.cityConfirmed = "true";
			input.dataset.cityRegion = item.r || "";
			closeList();

			// Автоматически подставляем область, если она есть
			if (item.r && regionSelect) {
				// Временно отключаем обработчик изменения региона, чтобы не стереть город
				isRegionAutoSelecting = true;
				autoSelectRegion(item.r, regionSelect);
				setTimeout(() => {
					isRegionAutoSelecting = false;
				}, 100);
			}

			input.dispatchEvent(new Event("change", { bubbles: true }));
		}

		function renderSuggestions(items) {
			suggestions = items;
			if (!items.length) {
				closeList();
				return;
			}
			list.innerHTML = items
				.map(function (item, index) {
					const cityName = item.n;
					const regionName = item.r;
					const districtName = item.d;
					const type = item.t;

					let metaText = [];
					if (type) metaText.push(type);
					if (districtName) metaText.push(districtName);
					if (regionName) metaText.push(regionName);

					return (
						'<button type="button" class="city-autocomplete-option" data-index="' +
						index +
						'">' +
						'<span class="city-autocomplete-option-title">' +
						esc(cityName) +
						"</span>" +
						(metaText.length > 0
							? '<span class="city-autocomplete-option-meta">' +
								esc(metaText.join(", ")) +
								"</span>"
							: "") +
						"</button>"
					);
				})
				.join("");
			openList();
		}

		function fetchSuggestions(query) {
			const regionHint = getRegionHint(regionSelect);
			const normalizedQuery = normalizeText(query);

			// Если города еще грузятся, ждем
			if (belarusCities.length === 0) {
				if (isCitiesLoading) {
					citiesLoadPromise.then(() => fetchSuggestions(query));
				}
				return;
			}

			// Оптимизированный поиск по массиву из 25к элементов
			const filtered = [];
			const contains = [];

			for (let i = 0; i < belarusCities.length; i++) {
				const item = belarusCities[i];
				if (!matchesRegion(item, regionHint)) continue;

				const normalizedCity = normalizeText(item.n);
				const index = normalizedCity.indexOf(normalizedQuery);

				if (index === 0) {
					// Начинается с запроса (самый приоритетный)
					filtered.push(item);
					if (filtered.length >= 10) break; // Хватит 10 идеальных совпадений
				} else if (index > 0 && filtered.length < 10) {
					// Содержит запрос в середине
					if (contains.length < 5) contains.push(item);
				}
			}

			// Добиваем результаты совпадениями из середины, если префиксных мало
			if (filtered.length < 10 && contains.length > 0) {
				for (let i = 0; i < contains.length; i++) {
					if (filtered.length >= 10) break;
					filtered.push(contains[i]);
				}
			}

			renderSuggestions(filtered);
		}

		function autoConfirmIfValid() {
			const value = (input.value || "").trim();
			if (!value) return !config.required;

			if (input.dataset.cityConfirmed === "true") return true;

			// Пытаемся найти точное совпадение, если пользователь ввел вручную
			const normalizedValue = normalizeText(value);
			const regionHint = getRegionHint(regionSelect);

			for (let i = 0; i < belarusCities.length; i++) {
				const item = belarusCities[i];
				if (normalizeText(item.n) === normalizedValue) {
					// Проверяем регион, если он выбран
					if (!regionHint || matchesRegion(item, regionHint)) {
						input.value = item.n;
						input.dataset.cityConfirmed = "true";
						input.dataset.cityRegion = item.r || "";

						// Если регион не был выбран вручную, но мы его нашли, подставляем
						if (item.r && regionSelect && !regionSelect.value) {
							isRegionAutoSelecting = true;
							autoSelectRegion(item.r, regionSelect);
							setTimeout(() => {
								isRegionAutoSelecting = false;
							}, 100);
						}

						return true;
					}
				}
			}
			return false;
		}

		function validateSelection() {
			const isValid = autoConfirmIfValid();
			if (isValid) return true;

			window.App.notify(config.emptyMessage, "error");
			input.focus();
			openList();
			return false;
		}

		list.addEventListener("mousedown", function () {
			isSelecting = true;
		});

		list.addEventListener("click", function (event) {
			const option = event.target.closest(".city-autocomplete-option");
			if (!option) return;
			const index = Number(option.dataset.index || -1);
			if (index < 0 || !suggestions[index]) return;
			selectSuggestion(suggestions[index]);
			isSelecting = false;
		});

		input.addEventListener("input", function () {
			input.dataset.cityConfirmed = "false";
			input.dataset.cityRegion = "";
			window.clearTimeout(debounceTimer);
			const query = (input.value || "").trim();
			if (query.length < 1) {
				closeList();
				return;
			}
			debounceTimer = window.setTimeout(function () {
				fetchSuggestions(query);
			}, 150); // Уменьшил задержку, так как поиск локальный
		});

		input.addEventListener("focus", function () {
			const query = (input.value || "").trim();
			if (query.length >= 1 && list.innerHTML.trim()) {
				openList();
			}
		});

		input.addEventListener("blur", function () {
			window.setTimeout(function () {
				if (!isSelecting) closeList();
				isSelecting = false;
			}, 120);
		});

		if (regionSelect) {
			regionSelect.addEventListener("change", function () {
				if (isRegionAutoSelecting) return; // Не сбрасываем, если выбираем программно
				input.value = "";
				input.dataset.cityConfirmed = "false";
				input.dataset.cityRegion = "";
				closeList();
			});
		}

		form.addEventListener("submit", function (event) {
			if (!validateSelection()) {
				event.preventDefault();
				event.stopPropagation();
			}
		});

		input.addEventListener("city-autocomplete:sync", function () {
			input.dataset.cityConfirmed = (input.value || "").trim()
				? "true"
				: "false";
			closeList();
		});
	}

	function validateInput(input) {
		if (!input) return true;
		const value = (input.value || "").trim();
		const isRequired = input.hasAttribute("required");

		if (!value) {
			if (isRequired) {
				window.App.notify(
					"Выберите населенный пункт из подсказок",
					"error",
				);
				input.focus();
				return false;
			}
			return true;
		}

		if (input.dataset.cityConfirmed === "true") {
			return true;
		}

		// Пытаемся подтвердить ручной ввод перед ошибкой
		const normalizedValue = normalizeText(value);
		const regionSelect = input.form
			? input.form.querySelector("#region_id, #listing_region_id")
			: null;
		const regionHint = getRegionHint(regionSelect);

		for (let i = 0; i < belarusCities.length; i++) {
			const item = belarusCities[i];
			if (normalizeText(item.n) === normalizedValue) {
				if (!regionHint || matchesRegion(item, regionHint)) {
					input.value = item.n;
					input.dataset.cityConfirmed = "true";
					input.dataset.cityRegion = item.r || "";
					return true;
				}
			}
		}

		window.App.notify("Выберите населенный пункт из подсказок", "error");
		input.focus();
		return false;
	}

	function esc(value) {
		return String(value || "")
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;")
			.replace(/'/g, "&#39;");
	}

	window.App.cityAutocomplete = {
		validateInput: validateInput,
		init: function () {
			configs.forEach(initAutocomplete);
		},
	};

	window.App.cityAutocomplete.init();
	document.addEventListener("contentUpdated", function () {
		window.App.cityAutocomplete.init();
	});
});
