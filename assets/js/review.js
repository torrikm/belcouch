// Скрипт для отправки отзыва с рейтингом (звёздами)
document.addEventListener("DOMContentLoaded", function () {
	const reviewForm = document.getElementById("review-form");
	if (!reviewForm) return;

	const reviewBlock = document.querySelector(".review-block");
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
			star.classList.toggle("active", idx < rating);
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
			url: "../api/submit_review.php",
			type: "POST",
			data: formData,
			processData: false,
			contentType: false,
			dataType: "json",
			success: function (data) {
				if (data.success) {
					// Скрываем форму и показываем сообщение о том, что отзыв уже оставлен
					const reviewBlock = document.querySelector(".review-block");
					if (reviewBlock) {
						// Создаем элемент с сообщением
						const infoBlock = document.createElement("div");
						infoBlock.className = "profile-review-info";
						infoBlock.innerHTML = "<p>Вы уже оставили отзыв на этого владельца.</p>";
						
						// Заменяем форму на сообщение
						reviewBlock.parentNode.replaceChild(infoBlock, reviewBlock);
					}

					// Показываем уведомление об успехе
					showNotification("Спасибо за отзыв!", "success");

					// Добавляем новый отзыв в список без перезагрузки
					addNewReview(data.review);
				} else {
					showNotification(data.message || "Ошибка отправки", "error");
				}
			},
			error: function () {
				showNotification("Ошибка отправки", "error");
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
		let reviewsList = document.querySelector(".reviews-list");

		// Если списка отзывов нет, создаем его
		if (!reviewsList) {
			reviewsList = document.createElement("div");
			reviewsList.className = "reviews-list";

			const reviewsTitle = document.createElement("h3");
			reviewsTitle.className = "reviews-title";
			reviewsTitle.textContent = "Отзывы";

			reviewsList.appendChild(reviewsTitle);

			// Добавляем список перед формой отправки отзыва
			const reviewsSection = document.querySelector(".reviews-section");
			const reviewBlock = document.querySelector(".review-block");
			reviewsSection.insertBefore(reviewsList, reviewBlock);
		}

		// Создаем элемент отзыва
		const reviewItem = document.createElement("div");
		reviewItem.className = "review-item";

		// Форматируем дату
		const date = new Date(review.created_at);
		const formattedDate = `${date.getDate().toString().padStart(2, "0")}.${(date.getMonth() + 1)
			.toString()
			.padStart(2, "0")}.${date.getFullYear()}`;

		// Создаем HTML для отзыва
		reviewItem.innerHTML = `
            <div class="review-item-header">
                <div class="reviewer-info">
                    ${
						review.avatar_image
							? `<img src="../api/get_avatar.php?id=${review.rater_id}" alt="Аватар" class="reviewer-avatar">`
							: `<div class="reviewer-avatar reviewer-avatar-placeholder">${review.first_name.charAt(
									0
							  )}${review.last_name.charAt(0)}</div>`
					}
                    <div class="reviewer-details">
                        <div class="reviewer-name">${review.first_name} ${review.last_name}</div>
                        <div class="review-date">${formattedDate}</div>
                    </div>
                </div>
                <div class="review-rating">
                    ${Array(5)
						.fill(0)
						.map(
							(_, i) =>
								`<img src="../assets/img/icons/${
									i < review.rating ? "star-filled.svg" : "star-void.svg"
								}" alt="Звезда" class="review-star-icon">`
						)
						.join("")}
                </div>
            </div>
            <div class="review-content">
                <p>${review.comment.replace(/\n/g, "<br>")}</p>
            </div>
        `;

		// Добавляем новый отзыв в начало списка
		const firstReview = reviewsList.querySelector(".review-item");
		if (firstReview) {
			reviewsList.insertBefore(reviewItem, firstReview);
		} else {
			reviewsList.appendChild(reviewItem);
		}

		// Плавно показываем новый отзыв
		reviewItem.style.opacity = "0";
		reviewItem.style.transform = "translateY(20px)";
		reviewItem.style.transition = "opacity 0.5s, transform 0.5s";

		setTimeout(() => {
			reviewItem.style.opacity = "1";
			reviewItem.style.transform = "translateY(0)";
		}, 10);
	}

	// Функция для показа уведомлений
	function showNotification(message, type = "success") {
		const notification = document.createElement("div");
		notification.className = `notification ${type}`;
		notification.textContent = message;

		// Стили уведомления
		notification.style.position = "fixed";
		notification.style.bottom = "20px";
		notification.style.right = "20px";
		notification.style.padding = "12px 20px";
		notification.style.borderRadius = "8px";
		notification.style.color = "#fff";
		notification.style.zIndex = "9999";
		notification.style.boxShadow = "0 4px 12px rgba(0,0,0,0.15)";
		notification.style.opacity = "0";
		notification.style.transition = "opacity 0.3s";

		if (type === "success") {
			notification.style.backgroundColor = "#4CAF50";
		} else {
			notification.style.backgroundColor = "#F44336";
		}

		document.body.appendChild(notification);

		// Плавно показываем и скрываем
		setTimeout(() => {
			notification.style.opacity = "1";
		}, 10);
		setTimeout(() => {
			notification.style.opacity = "0";
			setTimeout(() => notification.remove(), 300);
		}, 3000);
	}
});
