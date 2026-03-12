App.register("profileReview", function () {
	const reviewForm = document.getElementById("review-form");
	if (!reviewForm) {
		return;
	}

	const reviewBlock = document.querySelector(".review-block");
	if (!reviewBlock) {
		return;
	}

	const stars = reviewBlock.querySelectorAll(".star");
	const ratingInput = reviewForm.querySelector('input[name="rating"]');
	const submitButton = reviewForm.querySelector('button[type="submit"]');
	let currentRating = 0;
	let submitted = false;

	function getReviewsSection() {
		return document.querySelector(".reviews-section");
	}

	function getReviewsList() {
		return document.querySelector(".reviews-list");
	}

	function ensureReviewsList() {
		let list = getReviewsList();
		if (list) {
			return list;
		}

		const section = getReviewsSection();
		if (!section) {
			return null;
		}

		list = document.createElement("div");
		list.className = "reviews-list";
		list.innerHTML = '<h3 class="reviews-title">Отзывы</h3>';

		const formBlock = document.querySelector(".review-block");
		if (formBlock) {
			section.insertBefore(list, formBlock);
		} else {
			section.appendChild(list);
		}

		return list;
	}

	function removeEmptyState() {
		const empty = document.querySelector(".reviews-empty");
		if (empty) {
			empty.remove();
		}
	}

	function setInfoState() {
		const currentBlock = document.querySelector(".review-block");
		if (!currentBlock || !currentBlock.parentNode) {
			return;
		}

		const infoBlock = document.createElement("div");
		infoBlock.className = "profile-review-info";
		infoBlock.innerHTML = "<p>Вы уже оставили отзыв на этого владельца.</p>";
		currentBlock.parentNode.replaceChild(infoBlock, currentBlock);
	}

	function normalizeReview(review) {
		const firstName = String(review?.first_name || "").trim();
		const lastName = String(review?.last_name || "").trim();

		return {
			rater_id: review?.rater_id || 0,
			rating: Number(review?.rating || 0),
			comment: String(review?.comment || ""),
			created_at: review?.created_at || new Date().toISOString(),
			avatar_image: Boolean(review?.avatar_image),
			full_name: `${firstName} ${lastName}`.trim() || "Пользователь",
			initials: `${firstName.charAt(0)}${lastName.charAt(0)}` || "U",
		};
	}

	function buildReviewItem(rawReview) {
		const review = normalizeReview(rawReview);
		const date = new Date(review.created_at);
		const formattedDate = `${String(date.getDate()).padStart(2, "0")}.${String(
			date.getMonth() + 1,
		).padStart(2, "0")}.${date.getFullYear()}`;

		const avatarHtml = review.avatar_image
			? `<img src="${API_BASE_URL}/users/get_avatar.php?id=${review.rater_id}" alt="Аватар" class="reviewer-avatar">`
			: `<div class="reviewer-avatar reviewer-avatar-placeholder">${review.initials}</div>`;

		const starsHtml = Array.from({ length: 5 }, (_, index) => {
			const icon = index < review.rating ? "star-filled.svg" : "star-void.svg";
			return `<img src="../assets/img/icons/${icon}" alt="Звезда" class="review-star-icon">`;
		}).join("");

		const item = document.createElement("div");
		item.className = "review-item";
		item.innerHTML = `
			<div class="review-item-header">
				<div class="reviewer-info">
					${avatarHtml}
					<div class="reviewer-details">
						<div class="reviewer-name">${review.full_name}</div>
						<div class="review-date">${formattedDate}</div>
					</div>
				</div>
				<div class="review-rating">${starsHtml}</div>
			</div>
			<div class="review-content">
				<p>${review.comment.replace(/\n/g, "<br>")}</p>
			</div>
		`;

		return item;
	}

	function highlightStars(rating) {
		stars.forEach((star, index) => {
			star.classList.toggle("active", index < rating);
		});
	}

	function refreshSectionFromServer() {
		const currentSection = getReviewsSection();
		if (!currentSection) {
			return;
		}

		fetch(window.location.href, {
			credentials: "same-origin",
			headers: { "X-Requested-With": "XMLHttpRequest" },
		})
			.then((response) => response.text())
			.then((html) => {
				const parser = new DOMParser();
				const doc = parser.parseFromString(html, "text/html");
				const updatedSection = doc.querySelector(".reviews-section");
				if (updatedSection) {
					currentSection.innerHTML = updatedSection.innerHTML;
				}
			})
			.catch(() => {});
	}

	stars.forEach((star, index) => {
		star.addEventListener("mouseenter", function () {
			highlightStars(index + 1);
		});
		star.addEventListener("mouseleave", function () {
			highlightStars(currentRating);
		});
		star.addEventListener("click", function () {
			currentRating = index + 1;
			if (ratingInput) {
				ratingInput.value = String(currentRating);
			}
		});
	});

	reviewForm.addEventListener("submit", function (event) {
		event.preventDefault();

		if (currentRating === 0) {
			window.App.notify("Пожалуйста, выберите рейтинг", "error");
			return;
		}

		const formData = new FormData(reviewForm);
		submitButton.disabled = true;
		submitButton.textContent = "Отправка...";

		$.ajax({
			xhrFields: { withCredentials: true },
			url: API_BASE_URL + "/reviews/submit_review.php",
			type: "POST",
			data: formData,
			processData: false,
			contentType: false,
			dataType: "json",
			success: function (data) {
				if (!data.success) {
					window.App.notify(data.message || "Ошибка отправки", "error");
					return;
				}

				submitted = true;
				window.App.notify("Спасибо за отзыв!", "success");
				removeEmptyState();

				const list = ensureReviewsList();
				if (list) {
					const item = buildReviewItem(data.review || {});
					const title = list.querySelector(".reviews-title");
					if (title) {
						list.insertBefore(item, title.nextSibling);
					} else {
						list.insertBefore(item, list.firstChild);
					}
				}

				setInfoState();
				setTimeout(refreshSectionFromServer, 120);
			},
			error: function () {
				window.App.notify("Ошибка отправки", "error");
			},
			complete: function () {
				if (submitted) {
					return;
				}
				submitButton.disabled = false;
				submitButton.textContent = "Отправить";
			},
		});
	});
});
