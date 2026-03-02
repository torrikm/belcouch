/**
 * Скрипт для работы с фильтрами на странице предложений
 * Обрабатывает фильтрацию без перезагрузки страницы с использованием AJAX
 */

document.addEventListener("DOMContentLoaded", function () {
	// Получаем форму фильтров
	const filtersForm = document.getElementById("filters-form");
	// Контейнер для отображения результатов
	const listingsContent = document.querySelector(".listings-content");
	// Кнопка сброса фильтров
	const resetFiltersBtn = document.querySelector(".reset-filters");

	// Текущая страница пагинации
	let currentPage = 1;

	// Если форма существует, добавляем обработчик события
	if (filtersForm) {
		// Обработка изменений в форме (для мгновенного применения фильтров)
		const filterInputs = filtersForm.querySelectorAll(
			'select, input[type="number"], input[type="text"]'
		);
		filterInputs.forEach((input) => {
			input.addEventListener("change", function () {
				currentPage = 1; // Сбрасываем на первую страницу при изменении фильтров
				applyFilters();
			});
		});

		// Обработка чекбоксов (с небольшой задержкой для удобства)
		const checkboxes = filtersForm.querySelectorAll('input[type="checkbox"]');
		checkboxes.forEach((checkbox) => {
			checkbox.addEventListener("change", function () {
				currentPage = 1; // Сбрасываем на первую страницу при изменении фильтров
				applyFilters();
			});
		});
	}

	// Обработка сброса фильтров
	if (resetFiltersBtn) {
		resetFiltersBtn.addEventListener("click", function (e) {
			e.preventDefault();
			filtersForm.reset();
			currentPage = 1;
			applyFilters();
		});
	}

	// Функция для применения фильтров
	function applyFilters() {
		// Показываем индикатор загрузки
		if (listingsContent) {
			listingsContent.innerHTML =
				'<div class="loading-indicator">Загрузка результатов...</div>';
		}

		// Собираем данные формы
		const formData = new FormData(filtersForm);
		formData.append("page", currentPage);

		// Отправляем AJAX запрос с использованием jQuery
		$.ajax({
			url: "api/get_filtered_listings.php",
			type: "POST",
			data: formData,
			processData: false, // Важно для FormData
			contentType: false, // Важно для FormData
			dataType: "json",
			success: function(data) {
				if (data.success && listingsContent) {
					// Обновляем содержимое
					listingsContent.innerHTML = data.html;

					// Обновляем URL с параметрами фильтров (для возможности поделиться ссылкой)
					updateUrlWithFilters(formData);

					// Добавляем обработчики для пагинации
					setupPagination();

					// Обновляем кнопку сброса фильтров
					updateResetButton(formData);

					// Вызываем событие обновления контента для переинициализации обработчиков
					const event = new CustomEvent('contentUpdated');
					document.dispatchEvent(event);
				} else {
					console.error("Ошибка при получении данных");
				}
			},
			error: function(xhr, status, error) {
				console.error("Ошибка при выполнении запроса:", error);
				if (listingsContent) {
					listingsContent.innerHTML =
						'<div class="error-message">Произошла ошибка при загрузке данных. Пожалуйста, попробуйте позже.</div>';
				}
			}
		});
	}

	// Функция для обновления URL с параметрами фильтров
	function updateUrlWithFilters(formData) {
		const params = new URLSearchParams();

		// Добавляем все параметры из формы
		for (const [key, value] of formData.entries()) {
			// Пропускаем пустые значения и страницу
			if (value && key !== "page") {
				// Для массивов (чекбоксы) добавляем несколько параметров с одинаковым именем
				if (key.endsWith("[]")) {
					const cleanKey = key.replace("[]", "");
					params.append(cleanKey, value);
				} else {
					params.set(key, value);
				}
			}
		}

		// Если текущая страница не 1, добавляем её в URL
		if (currentPage > 1) {
			params.set("page", currentPage);
		}

		// Обновляем URL без перезагрузки страницы
		const newUrl =
			window.location.pathname + (params.toString() ? "?" + params.toString() : "");
		window.history.pushState({}, "", newUrl);
	}

	// Функция для настройки обработчиков пагинации
	function setupPagination() {
		const paginationButtons = document.querySelectorAll(".pagination-btn");

		paginationButtons.forEach((button) => {
			button.addEventListener("click", function () {
				// Получаем номер страницы из атрибута data-page
				const page = parseInt(this.getAttribute("data-page"));
				if (page && page !== currentPage) {
					currentPage = page;
					// Прокручиваем страницу вверх для удобства
					window.scrollTo({
						top: 0,
						behavior: "smooth",
					});
					// Применяем фильтры с новой страницей
					applyFilters();
				}
			});
		});
	}

	// Функция для обновления кнопки сброса фильтров
	function updateResetButton(formData) {
		const resetBtn = document.querySelector(".reset-filters");
		let hasActiveFilters = false;

		// Проверяем, есть ли активные фильтры
		for (const [key, value] of formData.entries()) {
			if (value && key !== "page") {
				hasActiveFilters = true;
				break;
			}
		}

		// Показываем или скрываем кнопку сброса
		if (resetBtn) {
			if (hasActiveFilters) {
				resetBtn.style.display = "block";
			} else {
				resetBtn.style.display = "none";
			}
		}
	}

	// Настраиваем пагинацию при загрузке страницы
	setupPagination();

	// Если в URL есть параметры, применяем фильтры при загрузке страницы
	if (window.location.search) {
		// Заполняем форму значениями из URL
		const urlParams = new URLSearchParams(window.location.search);

		// Устанавливаем текущую страницу из URL
		if (urlParams.has("page")) {
			currentPage = parseInt(urlParams.get("page")) || 1;
		}

		// Применяем фильтры
		setTimeout(applyFilters, 100);
	}
});
