/**
 * Скрипт для работы с избранными объявлениями
 */

App.register("favorites", function () {
	setupFavoriteButtons();
	setupClearFavoritesButton();

	// Функция для настройки обработчиков кнопок избранного
	function setupFavoriteButtons() {
		const favoriteButtons = document.querySelectorAll(".favorite-btn");

		favoriteButtons.forEach((button) => {
			button.addEventListener("click", function (e) {
				e.preventDefault();

				// Получаем ID объявления из атрибута data-id
				const listingId = this.getAttribute("data-id");

				// Проверяем, авторизован ли пользователь
				if (!window.App.auth.isLoggedIn()) {
					showLoginPrompt();
					return;
				}

				// Отправляем запрос на сервер для добавления/удаления из избранного
				toggleFavorite(listingId, this);
			});
		});
	}

	function showLoginPrompt() {
		window.App.notify(
			"Чтобы добавить объявление в избранное, необходимо авторизоваться",
			"error",
		);
		if (typeof openAuthModal === "function") {
			openAuthModal("login-modal");
		}
	}

	function getFavoriteApiUrl() {
		return API_BASE_URL + "/favorites/add_favorite.php";
	}

	function getClearFavoritesApiUrl() {
		return API_BASE_URL + "/favorites/clear_favorites.php";
	}

	// Функция для добавления/удаления объявления из избранного
	function toggleFavorite(listingId, buttonElement) {
		// Показываем индикатор загрузки
		buttonElement.classList.add("loading");

		$.ajax({
			xhrFields: { withCredentials: true },
			url: getFavoriteApiUrl(),
			type: "POST",
			data: "listing_id=" + listingId,
			dataType: "json",
			success: function (data) {
				// Убираем индикатор загрузки
				buttonElement.classList.remove("loading");

				if (data.success) {
					// Проверяем, находимся ли мы на странице избранного
					const isOnFavoritesPage = window.location.pathname.includes(
						"/profile/favorites.php",
					);

					if (data.action === "added") {
						// Добавление в избранное
						buttonElement.classList.add("active");
						buttonElement.innerHTML = "♥"; // Заполненное сердце
						buttonElement.title = "Удалить из избранного";
					} else {
						// Удаление из избранного
						buttonElement.classList.remove("active");
						buttonElement.innerHTML = "♡"; // Пустое сердце
						buttonElement.title = "Добавить в избранное";

						// Если мы на странице избранного, удаляем карточку
						if (isOnFavoritesPage) {
							const listingCard =
								buttonElement.closest(".listing-card");
							if (listingCard) {
								// Плавно скрываем карточку
								listingCard.style.transition = "all 0.3s ease";
								listingCard.style.opacity = "0";
								listingCard.style.transform = "scale(0.9)";

								// Удаляем после анимации
								setTimeout(() => {
									listingCard.remove();

									// Проверяем, остались ли еще карточки
									const remainingListings =
										document.querySelectorAll(
											".listing-card",
										);
									if (remainingListings.length === 0) {
										// Если карточек больше нет, перезагружаем страницу
										setTimeout(() => {
											window.location.reload();
										}, 500);
									}
								}, 300);
							}
						}
					}

					window.App.notify(data.message);
				} else {
					window.App.notify(data.message, "error");
				}
			},
			error: function (xhr, status, error) {
				buttonElement.classList.remove("loading");
				window.App.notify(
					"Произошла ошибка при обработке запроса",
					"error",
				);
				console.error("Ошибка при выполнении запроса:", error);
			},
		});
	}

	// Функция для обработки кнопки "Очистить список"
	function setupClearFavoritesButton() {
		const clearButton = document.getElementById("clear-favorites");

		if (clearButton) {
			clearButton.addEventListener("click", function (e) {
				e.preventDefault();

				if (
					confirm(
						"Вы уверены, что хотите очистить весь список избранного?",
					)
				) {
					fetch(getClearFavoritesApiUrl(), {
						credentials: "include",
						method: "POST",
					})
						.then((response) => response.json())
						.then((data) => {
							if (data.success) {
								window.App.notify(data.message);
								setTimeout(() => {
									window.location.reload();
								}, 1000);
							} else {
								window.App.notify(data.message, "error");
							}
						})
						.catch((error) => {
							console.error(
								"Ошибка при очистке избранного:",
								error,
							);
							window.App.notify(
								"Произошла ошибка при очистке избранного",
								"error",
							);
						});
				}
			});
		}
	}

	// Обновляем обработчики после AJAX-обновления контента
	document.addEventListener("contentUpdated", function () {
		setupFavoriteButtons();
	});
});
