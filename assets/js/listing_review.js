/**
 * JavaScript для функционала отзывов о жилье
 */
document.addEventListener("DOMContentLoaded", function () {
	const reviewForm = document.getElementById("listing-review-form");
	if (!reviewForm) return;

	const reviewBlock = document.querySelector(".listing-review-block");
	const stars = reviewBlock.querySelectorAll(".star");
	let currentRating = 0;

	// Наведение и выбор звезды
	stars.forEach((star, idx) => {
		star.addEventListener("mouseenter", () => {
			highlightStars(idx + 1);
		});
		star.addEventListener("mouseleave", () => {
			highlightStars(currentRating);
		});
		star.addEventListener("click", () => {
			currentRating = idx + 1;
			reviewForm.querySelector('input[name="rating"]').value = currentRating;
		});
	});

	function highlightStars(rating) {
		stars.forEach((star, idx) => {
			const starIcon = star.querySelector(".star-icon");
			if (idx < rating) {
				starIcon.src = "../assets/img/icons/star-filled.svg";
				star.classList.add("active");
			} else {
				starIcon.src = "../assets/img/icons/star-void.svg";
				star.classList.remove("active");
			}
		});
	}

	// Отправка формы через AJAX
	reviewForm.addEventListener("submit", function (e) {
		e.preventDefault();

		// Проверка выбранного рейтинга
		if (currentRating === 0) {
			alert("Пожалуйста, выберите рейтинг");
			return;
		}

		const formData = new FormData(reviewForm);
		const submitButton = reviewForm.querySelector('button[type="submit"]');

		// Блокируем кнопку на время отправки
		submitButton.disabled = true;
		submitButton.textContent = "Отправка...";

		$.ajax({
			url: "../api/submit_listing_review.php",
			type: "POST",
			data: formData,
			processData: false,
			contentType: false,
			dataType: "json",
			success: function (data) {
				if (data.success) {
					// Сбрасываем форму и рейтинг
					reviewForm.reset();
					currentRating = 0;
					highlightStars(0);

					// Показываем уведомление об успехе
					alert("Спасибо за отзыв!");

					// Добавляем новый отзыв в список без перезагрузки
					addNewReview(data.review);
				} else {
					alert(data.message || "Ошибка отправки");
				}
			},
			error: function () {
				alert("Ошибка отправки");
			},
			complete: function () {
				// Разблокируем кнопку
				submitButton.disabled = false;
				submitButton.textContent = "Отправить";
			},
		});
	});

	// Функция для добавления нового отзыва в список
	function addNewReview(review) {
		// Проверяем, есть ли список отзывов на странице
		let reviewsList = document.querySelector(".listing-reviews-list");

		// Если списка отзывов нет, создаем его
		if (!reviewsList) {
			reviewsList = document.createElement("div");
			reviewsList.className = "listing-reviews-list";

			const reviewsTitle = document.createElement("h3");
			reviewsTitle.className = "reviews-title";
			reviewsTitle.textContent = "Отзывы";

			reviewsList.appendChild(reviewsTitle);

			// Добавляем список перед формой отправки отзыва
			const reviewsSection = document.querySelector(".listing-reviews-section");
			const reviewBlock = document.querySelector(".listing-review-block");
			reviewsSection.insertBefore(reviewsList, reviewBlock);
		}

		// Создаем элемент отзыва
		const reviewItem = document.createElement("div");
		reviewItem.className = "review-item";

		// Создаем заголовок отзыва (информация о пользователе и рейтинг)
		const reviewHeader = document.createElement("div");
		reviewHeader.className = "review-item-header";

		// Информация о пользователе, оставившем отзыв
		const reviewerInfo = document.createElement("div");
		reviewerInfo.className = "reviewer-info";

		// Аватар пользователя
		let avatarElement;
		if (review.avatar_image) {
			avatarElement = document.createElement("img");
			avatarElement.src = `../api/get_avatar.php?id=${review.user_id}`;
			avatarElement.alt = "Аватар";
			avatarElement.className = "reviewer-avatar";
		} else {
			avatarElement = document.createElement("div");
			avatarElement.className = "reviewer-avatar reviewer-avatar-placeholder";
			avatarElement.textContent =
				review.first_name.substr(0, 1) + review.last_name.substr(0, 1);
		}

		// Детали о пользователе (имя и дата)
		const reviewerDetails = document.createElement("div");
		reviewerDetails.className = "reviewer-details";

		const reviewerName = document.createElement("div");
		reviewerName.className = "reviewer-name";
		reviewerName.textContent = review.first_name + " " + review.last_name;

		const reviewDate = document.createElement("div");
		reviewDate.className = "review-date";

		// Форматируем дату
		const date = new Date(review.created_at);
		const day = date.getDate().toString().padStart(2, "0");
		const month = (date.getMonth() + 1).toString().padStart(2, "0");
		const year = date.getFullYear();
		reviewDate.textContent = `${day}.${month}.${year}`;

		reviewerDetails.appendChild(reviewerName);
		reviewerDetails.appendChild(reviewDate);

		reviewerInfo.appendChild(avatarElement);
		reviewerInfo.appendChild(reviewerDetails);

		// Создаем элемент для отображения рейтинга
		const reviewRating = document.createElement("div");
		reviewRating.className = "review-rating";

		// Добавляем звезды рейтинга
		for (let i = 1; i <= 5; i++) {
			const starImg = document.createElement("img");
			starImg.src = `../assets/img/icons/${
				i <= review.rating ? "star-filled.svg" : "star-void.svg"
			}`;
			starImg.alt = "Звезда";
			starImg.className = "review-star-icon";
			reviewRating.appendChild(starImg);
		}

		// Собираем заголовок отзыва
		reviewHeader.appendChild(reviewerInfo);
		reviewHeader.appendChild(reviewRating);

		// Создаем содержимое отзыва
		const reviewContent = document.createElement("div");
		reviewContent.className = "review-content";

		const reviewText = document.createElement("p");
		reviewText.textContent = review.comment;

		reviewContent.appendChild(reviewText);

		// Собираем весь отзыв
		reviewItem.appendChild(reviewHeader);
		reviewItem.appendChild(reviewContent);

		// Добавляем отзыв в начало списка
		reviewsList.insertBefore(reviewItem, reviewsList.firstChild.nextSibling);
	}

	// Загрузка существующих отзывов при загрузке страницы
	function loadReviews() {
		const listingId = reviewForm.querySelector('input[name="listing_id"]').value;

		if (!listingId) return;

		$.ajax({
			url: "../api/get_listing_reviews.php",
			type: "GET",
			data: { listing_id: listingId },
			dataType: "json",
			success: function (data) {
				if (data.success && data.reviews.length > 0) {
					// Если на странице нет блока для отзывов, создаем его
					let reviewsList = document.querySelector(".listing-reviews-list");

					if (!reviewsList) {
						reviewsList = document.createElement("div");
						reviewsList.className = "listing-reviews-list";

						const reviewsTitle = document.createElement("h3");
						reviewsTitle.className = "reviews-title";
						reviewsTitle.textContent = "Отзывы";

						reviewsList.appendChild(reviewsTitle);

						// Добавляем список перед формой отправки отзыва
						const reviewsSection = document.querySelector(".listing-reviews-section");
						const reviewBlock = document.querySelector(".listing-review-block");
						reviewsSection.insertBefore(reviewsList, reviewBlock);
					}

					// Добавляем каждый отзыв в список
					data.reviews.forEach((review) => {
						// Создаем элемент отзыва
						const reviewItem = document.createElement("div");
						reviewItem.className = "review-item";

						// Создаем заголовок отзыва (информация о пользователе и рейтинг)
						const reviewHeader = document.createElement("div");
						reviewHeader.className = "review-item-header";

						// Информация о пользователе, оставившем отзыв
						const reviewerInfo = document.createElement("div");
						reviewerInfo.className = "reviewer-info";

						// Аватар пользователя
						let avatarElement;
						if (review.avatar_image) {
							avatarElement = document.createElement("img");
							avatarElement.src = `../api/get_avatar.php?id=${review.user_id}`;
							avatarElement.alt = "Аватар";
							avatarElement.className = "reviewer-avatar";
						} else {
							avatarElement = document.createElement("div");
							avatarElement.className = "reviewer-avatar reviewer-avatar-placeholder";
							avatarElement.textContent =
								review.first_name.substr(0, 1) + review.last_name.substr(0, 1);
						}

						// Детали о пользователе (имя и дата)
						const reviewerDetails = document.createElement("div");
						reviewerDetails.className = "reviewer-details";

						const reviewerName = document.createElement("div");
						reviewerName.className = "reviewer-name";
						reviewerName.textContent = review.first_name + " " + review.last_name;

						const reviewDate = document.createElement("div");
						reviewDate.className = "review-date";

						// Форматируем дату
						const date = new Date(review.created_at);
						const day = date.getDate().toString().padStart(2, "0");
						const month = (date.getMonth() + 1).toString().padStart(2, "0");
						const year = date.getFullYear();
						reviewDate.textContent = `${day}.${month}.${year}`;

						reviewerDetails.appendChild(reviewerName);
						reviewerDetails.appendChild(reviewDate);

						reviewerInfo.appendChild(avatarElement);
						reviewerInfo.appendChild(reviewerDetails);

						// Создаем элемент для отображения рейтинга
						const reviewRating = document.createElement("div");
						reviewRating.className = "review-rating";

						// Добавляем звезды рейтинга
						for (let i = 1; i <= 5; i++) {
							const starImg = document.createElement("img");
							starImg.src = `../assets/img/icons/${
								i <= review.rating ? "star-filled.svg" : "star-void.svg"
							}`;
							starImg.alt = "Звезда";
							starImg.className = "review-star-icon";
							reviewRating.appendChild(starImg);
						}

						// Собираем заголовок отзыва
						reviewHeader.appendChild(reviewerInfo);
						reviewHeader.appendChild(reviewRating);

						// Создаем содержимое отзыва
						const reviewContent = document.createElement("div");
						reviewContent.className = "review-content";

						const reviewText = document.createElement("p");
						reviewText.textContent = review.comment;

						reviewContent.appendChild(reviewText);

						// Собираем весь отзыв
						reviewItem.appendChild(reviewHeader);
						reviewItem.appendChild(reviewContent);

						// Добавляем отзыв в список
						reviewsList.appendChild(reviewItem);
					});
				}
			},
			error: function (xhr, status, error) {
				console.error("Ошибка при загрузке отзывов:", error);
			},
		});
	}

	// Проверяем, есть ли уже отзывы на странице
	const existingReviews = document.querySelector(".listing-reviews-list");
	// Загружаем отзывы только если их нет на странице
	if (!existingReviews) {
		loadReviews();
	}
});
