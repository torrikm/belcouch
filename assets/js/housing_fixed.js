/**
 * JavaScript для функционала добавления, редактирования и удаления жилья
 */
App.register("housingManager", function () {
	// Обработчик для удаления правил и удобств (делегирование событий)
	document.addEventListener("click", function (e) {
		// Проверяем, что клик был по кнопке удаления
		if (
			e.target.classList.contains("remove-item") ||
			(e.target.closest && e.target.closest(".remove-item"))
		) {
			const removeBtn = e.target.classList.contains("remove-item")
				? e.target
				: e.target.closest(".remove-item");
			const selectedItem = removeBtn.closest(".selected-item");
			if (selectedItem) {
				selectedItem.remove();
			}
		}
	});

	// Найти элементы на странице
	const modal = document.getElementById("housing-modal");
	const closeButton = modal ? modal.querySelector(".modal-close") : null;
	const cancelButton = modal ? modal.querySelector(".btn-cancel") : null;
	const housingForm = document.getElementById("housing-form");
	const photoInput = document.getElementById("housing_photos");
	const previewContainer = document.getElementById("photo-previews");
	const addButtons = document.querySelectorAll(".btn-add-item");
	const modalTitle = document.getElementById("housing-modal-title");
	const listingIdInput = document.getElementById("listing_id");
	const profileRight = document.querySelector(".profile-right");

	console.log("DOM загружен, ищем элементы:", {
		modal: modal,
		closeButton: closeButton,
		housingForm: housingForm,
		profileRight: profileRight,
	});

	function resetHousingForm() {
		if (housingForm) {
			housingForm.reset();
		}
		if (listingIdInput) {
			listingIdInput.value = "";
		}
		if (modalTitle) {
			modalTitle.textContent = "Добавить объявление";
		}
		if (previewContainer) {
			previewContainer.innerHTML = "";
		}
		const selectedRules = document.getElementById("selected-rules");
		const selectedAmenities = document.getElementById("selected-amenities");
		if (selectedRules) {
			selectedRules.innerHTML = "";
		}
		if (selectedAmenities) {
			selectedAmenities.innerHTML = "";
		}
		document
			.querySelectorAll(
				"#property_type_id, #listing_region_id, #stay_duration_id, #rules, #amenities",
			)
			.forEach((select) => {
				select.selectedIndex = 0;
				select.dispatchEvent(
					new Event("custom-select:refresh", {
						bubbles: true,
					}),
				);
			});
	}

	// Инициализируем галерею сразу и после обновлений контента
	document.addEventListener("DOMContentLoaded", initGalleryNavigation);
	document.addEventListener("contentUpdated", initGalleryNavigation);

	// Делегированный обработчик кнопки раскрытия миниатюр (чтобы работало после подмены DOM)
	document.addEventListener("click", function (e) {
		const toggleBtn = e.target.closest(".thumbnails-toggle");
		if (!toggleBtn) return;
		const galleryContainer = toggleBtn.closest(".gallery-container");
		if (!galleryContainer) return;
		const container = galleryContainer.querySelector(
			".thumbnails-container",
		);
		if (!container) return;
		const isCollapsed = container.classList.contains("is-collapsed");
		if (isCollapsed) {
			container.classList.remove("is-collapsed");
			toggleBtn.textContent = "Скрыть";
		} else {
			container.classList.add("is-collapsed");
			toggleBtn.textContent = "Показать все фото";
		}
	});

	function openModal() {
		if (!modal) {
			console.error("Модальное окно не найдено");
			return;
		}
		if (window.App.modal && typeof window.App.modal.open === "function") {
			window.App.modal.open(modal);
			return;
		}
		modal.classList.add("show");
		document.body.style.overflow = "hidden";
	}

	function refreshHousingContent() {
		if (!profileRight) {
			return Promise.resolve();
		}

		return fetch(window.location.href, {
			credentials: "same-origin",
			headers: {
				"X-Requested-With": "XMLHttpRequest",
			},
		})
			.then((response) => response.text())
			.then((html) => {
				const parser = new DOMParser();
				const doc = parser.parseFromString(html, "text/html");
				const updatedProfileRight = doc.querySelector(".profile-right");
				const currentDetails = document.querySelector(".details");
				const updatedDetails = doc.querySelector(".details");
				const currentReviews = document.querySelector(
					".listing-reviews-section",
				);
				const updatedReviews = doc.querySelector(
					".listing-reviews-section",
				);
				if (!updatedProfileRight) {
					throw new Error("Не удалось обновить блок жилья");
				}
				profileRight.innerHTML = updatedProfileRight.innerHTML;
				if (currentDetails) {
					if (updatedDetails) {
						currentDetails.innerHTML = updatedDetails.innerHTML;
					} else {
						currentDetails.remove();
					}
				}
				if (currentReviews) {
					if (updatedReviews) {
						currentReviews.innerHTML = updatedReviews.innerHTML;
						const currentDetails =
							document.querySelector(".details");
						if (
							currentDetails &&
							currentReviews !== currentDetails.nextElementSibling
						) {
							currentDetails.insertAdjacentElement(
								"afterend",
								currentReviews,
							);
						}
					} else {
						currentReviews.remove();
					}
				} else if (updatedReviews) {
					const currentDetails = document.querySelector(".details");
					if (currentDetails && currentDetails.parentNode) {
						currentDetails.insertAdjacentHTML(
							"afterend",
							updatedReviews.outerHTML,
						);
					} else if (profileRight.parentElement) {
						profileRight.parentElement.appendChild(
							updatedReviews.cloneNode(true),
						);
					}
				}
				document.dispatchEvent(new CustomEvent("contentUpdated"));
			});
	}

	function initGalleryNavigation() {
		const galleryContainer = document.querySelector(
			".profile-right .gallery-container",
		);
		if (!galleryContainer || galleryContainer.dataset.galleryInitialized) {
			return;
		}
		const mainImage = galleryContainer.querySelector("#main-gallery-image");
		const thumbnails = galleryContainer.querySelectorAll(".thumbnail");
		const prevButton = galleryContainer.querySelector(".gallery-nav.prev");
		const nextButton = galleryContainer.querySelector(".gallery-nav.next");

		if (!mainImage || thumbnails.length === 0) {
			return;
		}

		galleryContainer.dataset.galleryInitialized = "true";

		if (thumbnails.length === 1) {
			if (prevButton) prevButton.style.display = "none";
			if (nextButton) nextButton.style.display = "none";
			return;
		}

		let currentIndex = 0;
		const maxIndex = thumbnails.length - 1;

		function updateMainImage(index) {
			if (index < 0) index = maxIndex;
			if (index > maxIndex) index = 0;

			currentIndex = index;

			const src = thumbnails[index].getAttribute("data-src");
			if (src) {
				mainImage.src = src;
			}

			thumbnails.forEach((thumb) => thumb.classList.remove("active"));
			thumbnails[index].classList.add("active");
		}

		if (prevButton) {
			prevButton.addEventListener("click", function () {
				updateMainImage(currentIndex - 1);
			});
		}

		if (nextButton) {
			nextButton.addEventListener("click", function () {
				updateMainImage(currentIndex + 1);
			});
		}

		thumbnails.forEach((thumbnail) => {
			thumbnail.addEventListener("click", function () {
				const index = parseInt(this.getAttribute("data-index"), 10);
				updateMainImage(index);
			});
		});

		const toggleBtn = galleryContainer.querySelector(".thumbnails-toggle");
		if (toggleBtn) {
			toggleBtn.addEventListener("click", function () {
				const isCollapsed = galleryContainer
					.querySelector(".thumbnails-container")
					?.classList.contains("is-collapsed");
				const container = galleryContainer.querySelector(
					".thumbnails-container",
				);
				if (!container) return;
				if (isCollapsed) {
					container.classList.remove("is-collapsed");
					this.textContent = "Скрыть";
				} else {
					container.classList.add("is-collapsed");
					this.textContent = "Показать все фото";
				}
			});
		}
	}

	function handleEditListing(listingId) {
		console.log("Редактирование объявления ID:", listingId);

		if (!listingId) {
			console.error("Не удалось получить ID объявления");
			return;
		}

		$.ajax({
			xhrFields: { withCredentials: true },
			url: `${API_BASE_URL}/listings/get_listing.php?id=${listingId}`,
			method: "GET",
			dataType: "json",
			success: function (data) {
				if (data.success && data.listing) {
					const listing = data.listing;
					if (listingIdInput) {
						listingIdInput.value = listing.id;
					}

					$("#property_type_id").val(listing.property_type_id || "");
					$("#beds_count").val(listing.max_guests || 1);
					$("#title").val(listing.title || "");
					$("#listing_city").val(listing.city || "");
					$("#listing_region_id").val(listing.region_id || "");
					$("#notes").val(listing.notes || "");
					$("#stay_duration_id").val(listing.stay_duration_id || "");
					$(
						"#property_type_id, #listing_region_id, #stay_duration_id",
					).trigger("change");
					document
						.querySelectorAll(
							"#property_type_id, #listing_region_id, #stay_duration_id",
						)
						.forEach((select) => {
							select.dispatchEvent(
								new Event("custom-select:refresh", {
									bubbles: true,
								}),
							);
						});
					const listingCityInput =
						document.getElementById("listing_city");
					if (listingCityInput) {
						listingCityInput.dispatchEvent(
							new Event("city-autocomplete:sync", {
								bubbles: true,
							}),
						);
					}

					console.log("Заполнение формы", {
						listingIdInput: listing.property_type_id,
						property_type_id: listing.property_type_id,
						beds_count: listing.max_guests,
						title: listing.title,
						city: listing.city,
						region_id: listing.region_id,
						notes: listing.notes,
						stay_duration_id: listing.stay_duration_id,
					});

					$("#selected-rules, #selected-amenities").empty();

					if (listing.rules && Array.isArray(listing.rules)) {
						listing.rules.forEach((rule) => {
							$("#selected-rules").append(`
										<div class="selected-item">
											<input type="hidden" name="rules[]" value="${rule.id}">
											<span>${rule.name || "Правило " + rule.id}</span>
											<button type="button" class="remove-item">&times;</button>
										</div>
									`);
						});
					}

					if (listing.amenities && Array.isArray(listing.amenities)) {
						listing.amenities.forEach((amenity) => {
							$("#selected-amenities").append(`
										<div class="selected-item">
											<input type="hidden" name="amenities[]" value="${amenity.id}">
											<span>${amenity.name || "Удобство " + amenity.id}</span>
											<button type="button" class="remove-item">&times;</button>
										</div>
									`);
						});
					}

					// Очищаем контейнер предпросмотра фото
					$("#photo-previews").empty();

					// Проверяем, есть ли изображения
					if (
						listing.images &&
						Array.isArray(listing.images) &&
						listing.images.length > 0
					) {
						listing.images.forEach(function (image) {
							let src = "../" + image.image_path;
							const preview =
								$("<div>").addClass("photo-preview");
							if (image.id)
								preview.attr("data-image-id", image.id);
							if (image.image_path)
								preview.attr("data-path", image.image_path);
							const img = $("<img>")
								.attr("src", src)
								.addClass("photo-preview-img");
							const removeBtn = $(
								"<button type='button' class='remove-photo' title='Удалить'>&times;</button>",
							);
							removeBtn.on("click", function () {
								// Если это сохранённое фото (есть data-path) — добавляем скрытый input с путём
								const imagePath = preview.attr("data-path");
								if (imagePath) {
									const deletedInput = $(
										"<input type='hidden' name='deleted_images[]'>",
									).val(imagePath);
									$("#housing-form").append(deletedInput);
								}
								preview.remove();
							});
							preview.append(img).append(removeBtn);
							$("#photo-previews").append(preview);
						});
					}

					if (modalTitle) {
						modalTitle.textContent = "Редактировать объявление";
					}
					openModal();
				} else {
					window.App.notify(
						"Не удалось загрузить данные объявления. Пожалуйста, попробуйте обновить страницу.",
						"error",
					);
				}
			},
			error: function (xhr, status, error) {
				console.error("Ошибка при загрузке данных:", error);
				window.App.notify(
					"Произошла ошибка при загрузке данных. Пожалуйста, попробуйте позже.",
					"error",
				);
			},
		});
	}

	function handleDeleteListing(listingId) {
		console.log("Удаление объявления ID:", listingId);

		if (!listingId) {
			console.error("Не удалось получить ID объявления");
			return;
		}

		if (!confirm("Вы действительно хотите удалить это объявление?")) {
			return;
		}

		$.ajax({
			xhrFields: { withCredentials: true },
			url: API_BASE_URL + "/listings/delete_listing.php",
			method: "POST",
			data: { listing_id: listingId },
			dataType: "json",
			success: function (data) {
				if (data.success) {
					window.App.notify("Объявление успешно удалено");
					refreshHousingContent().catch(function (error) {
						console.error(
							"Ошибка при обновлении блока жилья:",
							error,
						);
						window.App.notify(
							"Объявление удалено, но обновить блок без перезагрузки не удалось.",
							"error",
						);
					});
				} else {
					window.App.notify(
						"Ошибка при удалении объявления: " +
							(data.message || "Неизвестная ошибка"),
						"error",
					);
				}
			},
			error: function (xhr, status, error) {
				console.error("Ошибка при удалении:", error);
				window.App.notify(
					"Произошла ошибка при удалении объявления. Пожалуйста, попробуйте позже.",
					"error",
				);
			},
		});
	}

	document.addEventListener("click", function (event) {
		const addButton = event.target.closest("#add-housing-btn");
		if (addButton) {
			resetHousingForm();
			openModal();
			return;
		}

		const editButton = event.target.closest(".btn-edit-listing");
		if (editButton) {
			handleEditListing(editButton.getAttribute("data-id"));
			return;
		}

		const deleteButton = event.target.closest(".btn-delete-listing");
		if (deleteButton) {
			handleDeleteListing(deleteButton.getAttribute("data-id"));
		}
	});

	// Функция для закрытия модального окна
	function closeModal() {
		if (modal) {
			if (
				window.App.modal &&
				typeof window.App.modal.close === "function"
			) {
				window.App.modal.close(modal);
				return;
			}
			modal.classList.remove("show");
			document.body.style.overflow = "";
		}
	}

	// Обработчик для кнопки закрытия модального окна
	if (closeButton) {
		closeButton.addEventListener("click", function () {
			closeModal();
		});
	}

	// Обработчик для кнопки отмены в модальном окне
	if (cancelButton) {
		cancelButton.addEventListener("click", function () {
			closeModal();
		});
	}

	// Обработчик для загрузки фото
	if (photoInput) {
		photoInput.addEventListener("change", function (e) {
			// Не очищаем previewContainer полностью!
			const files = e.target.files;
			for (let i = 0; i < files.length; i++) {
				const file = files[i];
				if (file.type.startsWith("image/")) {
					const preview = document.createElement("div");
					preview.className = "photo-preview";

					const img = document.createElement("img");
					img.file = file;
					img.className = "photo-preview-img";

					// Кнопка удаления для новых фото
					const removeBtn = document.createElement("button");
					removeBtn.type = "button";
					removeBtn.className = "remove-photo";
					removeBtn.title = "Удалить";
					removeBtn.innerHTML = "&times;";
					removeBtn.addEventListener("click", function () {
						preview.remove();
						// Если не осталось превью, очищаем input file
						if (
							previewContainer.querySelectorAll(".photo-preview")
								.length === 0
						) {
							photoInput.value = "";
						}
					});

					preview.appendChild(img);
					preview.appendChild(removeBtn);
					previewContainer.appendChild(preview);

					const reader = new FileReader();
					reader.onload = (function (aImg) {
						return function (e) {
							aImg.src = e.target.result;
						};
					})(img);

					reader.readAsDataURL(file);
				}
			}
		});
	}

	// Обработчики для кнопок добавления правил и удобств
	if (addButtons && addButtons.length > 0) {
		addButtons.forEach(function (button) {
			button.addEventListener("click", function () {
				const targetType = this.getAttribute("data-target"); // 'rules' или 'amenities'
				const selectElement = document.getElementById(targetType);
				const selectedItemsContainer = document.getElementById(
					"selected-" + targetType,
				);

				if (!selectElement || !selectedItemsContainer) {
					console.error(
						"Не найдены необходимые элементы",
						targetType,
					);
					return;
				}

				const selectedId = selectElement.value;
				const selectedText =
					selectElement.options[selectElement.selectedIndex].text;

				if (!selectedId || selectedId === "") {
					window.App.notify(
						"Пожалуйста, выберите " +
							(targetType === "rules" ? "правило" : "удобство"),
						"error",
					);
					return;
				}

				// Проверяем, не было ли уже добавлено это правило/удобство
				const existingItems =
					selectedItemsContainer.querySelectorAll(".selected-item");
				for (let i = 0; i < existingItems.length; i++) {
					if (
						existingItems[i].querySelector("input").value ===
						selectedId
					) {
						window.App.notify("Этот элемент уже добавлен", "error");
						return;
					}
				}

				// Создаем элемент для выбранного правила/удобства
				const selectedItem = document.createElement("div");
				selectedItem.className = "selected-item";

				// Создаем скрытый инпут с ID выбранного элемента
				const input = document.createElement("input");
				input.type = "hidden";
				input.name = targetType + "[]";
				input.value = selectedId;

				// Создаем текстовое представление правила/удобства
				const text = document.createElement("span");
				text.textContent = selectedText;

				// Создаем кнопку удаления
				const removeButton = document.createElement("button");
				removeButton.type = "button";
				removeButton.className = "remove-item";
				removeButton.innerHTML = "&times;";
				removeButton.addEventListener("click", function () {
					selectedItem.remove();
				});

				// Собираем всё вместе
				selectedItem.appendChild(input);
				selectedItem.appendChild(text);
				selectedItem.appendChild(removeButton);

				// Добавляем в контейнер
				selectedItemsContainer.appendChild(selectedItem);

				// Сбрасываем выбор в селекте
				selectElement.selectedIndex = 0;
			});
		});
	}

	// Добавляем обработчик отправки формы
	if (housingForm) {
		console.log("Добавлен обработчик отправки формы");
		housingForm.addEventListener("submit", function (e) {
			e.preventDefault();
			const listingCityInput = document.getElementById("listing_city");
			if (
				window.App.cityAutocomplete &&
				typeof window.App.cityAutocomplete.validateInput ===
					"function" &&
				!window.App.cityAutocomplete.validateInput(listingCityInput)
			) {
				return;
			}
			console.log("Отправка формы...");

			// Создаем объект FormData для отправки формы
			const formData = new FormData(housingForm);
			console.log("Данные формы:", [...formData.entries()]);

			// Отправляем данные на сервер с использованием Ajax
			$.ajax({
				xhrFields: { withCredentials: true },
				url: API_BASE_URL + "/listings/add_listing.php",
				type: "POST",
				data: formData,
				processData: false, // Важно для FormData
				contentType: false, // Важно для FormData
				dataType: "json",
				success: function (data) {
					console.log("Ответ сервера:", data);
					if (data.success) {
						window.App.notify(data.message);
						closeModal();
						refreshHousingContent().catch(function (error) {
							console.error(
								"Ошибка при обновлении блока жилья:",
								error,
							);
							window.App.notify(
								"Данные сохранены, но обновить блок без перезагрузки не удалось.",
								"error",
							);
						});
					} else {
						window.App.notify("Ошибка: " + data.message, "error");
					}
				},
				error: function (xhr, status, error) {
					console.error("Ошибка при отправке формы:", error);
					window.App.notify(
						"Произошла ошибка при отправке формы. Пожалуйста, попробуйте позже.",
						"error",
					);
				},
			});
		});
	}
});
