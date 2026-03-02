/**
 * JavaScript для функционала добавления, редактирования и удаления жилья
 */
document.addEventListener("DOMContentLoaded", function () {
	// Обработчик для удаления правил и удобств (делегирование событий)
	document.addEventListener('click', function(e) {
		// Проверяем, что клик был по кнопке удаления
		if (e.target.classList.contains('remove-item') || (e.target.closest && e.target.closest('.remove-item'))) {
			const removeBtn = e.target.classList.contains('remove-item') ? e.target : e.target.closest('.remove-item');
			const selectedItem = removeBtn.closest('.selected-item');
			if (selectedItem) {
				selectedItem.remove();
			}
		}
	});

	// Найти элементы на странице
	const addButton = document.getElementById("add-housing-btn");
	const modal = document.getElementById("housing-modal");
	const closeButton = document.querySelector(".modal-close");
	const cancelButton = document.querySelector(".btn-cancel");
	const housingForm = document.getElementById("housing-form");
	const photoInput = document.getElementById("housing_photos");
	const previewContainer = document.getElementById("photo-previews");
	const addButtons = document.querySelectorAll(".btn-add-item");
	const editButtons = document.querySelectorAll(".btn-edit-listing");
	const deleteButtons = document.querySelectorAll(".btn-delete-listing");
	const modalTitle = document.getElementById("housing-modal-title");
	const listingIdInput = document.getElementById("listing_id");

	console.log("DOM загружен, ищем элементы:", {
		addButton: addButton,
		modal: modal,
		closeButton: closeButton,
		housingForm: housingForm,
		editButtons: editButtons,
		deleteButtons: deleteButtons,
	});

	// Обработчик для кнопки "Добавить"
	if (addButton) {
		addButton.addEventListener("click", function () {
			console.log("Кнопка Добавить нажата");
			if (modal) {
				// Сбрасываем форму перед открытием
				if (housingForm) housingForm.reset();

				// Сбрасываем ID объявления
				if (listingIdInput) listingIdInput.value = "";

				// Меняем заголовок модального окна
				if (modalTitle) modalTitle.textContent = "Добавить объявление";

				// Очищаем предпросмотр фото
				if (previewContainer) previewContainer.innerHTML = "";

				// Очищаем выбранные правила и удобства
				const selectedRules = document.getElementById("selected-rules");
				const selectedAmenities = document.getElementById("selected-amenities");

				if (selectedRules) selectedRules.innerHTML = "";
				if (selectedAmenities) selectedAmenities.innerHTML = "";

				// Открываем модальное окно
				modal.classList.add("show");
				document.body.style.overflow = "hidden"; // Блокируем прокрутку боди
			} else {
				console.error("Модальное окно не найдено");
			}
		});
	}

	// Обработчики для кнопок редактирования
	if (editButtons && editButtons.length > 0) {
		editButtons.forEach(function (button) {
			button.addEventListener("click", function () {
				const listingId = this.getAttribute("data-id");
				console.log("Редактирование объявления ID:", listingId);

				if (!listingId) {
					console.error("Не удалось получить ID объявления");
					return;
				}

				// Загружаем данные объявления через AJAX
				$.ajax({
					url: `../api/get_listing.php?id=${listingId}`,
					method: "GET",
					dataType: "json",
					success: function (data) {
						if (data.success && data.listing) {
							const listing = data.listing;
							if (listingIdInput) listingIdInput.value = listing.id;

							$("#property_type_id").val(listing.property_type_id || "");
							$("#beds_count").val(listing.max_guests || 1);
							$("#title").val(listing.title || "");
							$("#listing_city").val(listing.city || "");
							$("#listing_region_id").val(listing.region_id || "");
							$("#notes").val(listing.notes || "");
							$("#stay_duration_id").val(listing.stay_duration_id || "");

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
									const preview = $("<div>").addClass("photo-preview");
									if (image.id) preview.attr("data-image-id", image.id);
									if (image.image_path) preview.attr("data-path", image.image_path);
									const img = $("<img>")
										.attr("src", src)
										.addClass("photo-preview-img");
									const removeBtn = $(
										"<button type='button' class='remove-photo' title='Удалить'>&times;</button>"
									);
									removeBtn.on("click", function () {
										// Если это сохранённое фото (есть data-path) — добавляем скрытый input с путём
										const imagePath = preview.attr("data-path");
										if (imagePath) {
											const deletedInput = $("<input type='hidden' name='deleted_images[]'>").val(imagePath);
											$("#housing-form").append(deletedInput);
										}
										preview.remove();
									});
									preview.append(img).append(removeBtn);
									$("#photo-previews").append(preview);
								});
							}

							if (modalTitle) modalTitle.textContent = "Редактировать объявление";
							modal.classList.add("show");
							document.body.style.overflow = "hidden";
						} else {
							alert(
								"Не удалось загрузить данные объявления. Пожалуйста, попробуйте обновить страницу."
							);
						}
					},
					error: function (xhr, status, error) {
						console.error("Ошибка при загрузке данных:", error);
						alert(
							"Произошла ошибка при загрузке данных. Пожалуйста, попробуйте позже."
						);
					},
				});
			});
		});
	}

	// Обработчики для кнопок удаления
	if (deleteButtons && deleteButtons.length > 0) {
		deleteButtons.forEach(function (button) {
			button.addEventListener("click", function () {
				const listingId = this.getAttribute("data-id");
				console.log("Удаление объявления ID:", listingId);

				if (!listingId) {
					console.error("Не удалось получить ID объявления");
					return;
				}

				// Запрашиваем подтверждение на удаление
				if (confirm("Вы действительно хотите удалить это объявление?")) {
					// Отправляем запрос на удаление
					$.ajax({
						url: "../api/delete_listing.php",
						method: "POST",
						data: { listing_id: listingId },
						dataType: "json",
						success: function (data) {
							if (data.success) {
								alert("Объявление успешно удалено");
								// Перезагружаем страницу для обновления списка объявлений
								window.location.reload();
							} else {
								alert(
									"Ошибка при удалении объявления: " +
										(data.message || "Неизвестная ошибка")
								);
							}
						},
						error: function (xhr, status, error) {
							console.error("Ошибка при удалении:", error);
							alert(
								"Произошла ошибка при удалении объявления. Пожалуйста, попробуйте позже."
							);
						},
					});
				}
			});
		});
	}

	// Функция для закрытия модального окна
	function closeModal() {
		if (modal) {
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
						if (previewContainer.querySelectorAll('.photo-preview').length === 0) {
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
				const selectedItemsContainer = document.getElementById("selected-" + targetType);

				if (!selectElement || !selectedItemsContainer) {
					console.error("Не найдены необходимые элементы", targetType);
					return;
				}

				const selectedId = selectElement.value;
				const selectedText = selectElement.options[selectElement.selectedIndex].text;

				if (!selectedId || selectedId === "") {
					alert(
						"Пожалуйста, выберите " + (targetType === "rules" ? "правило" : "удобство")
					);
					return;
				}

				// Проверяем, не было ли уже добавлено это правило/удобство
				const existingItems = selectedItemsContainer.querySelectorAll(".selected-item");
				for (let i = 0; i < existingItems.length; i++) {
					if (existingItems[i].querySelector("input").value === selectedId) {
						alert("Этот элемент уже добавлен");
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
			console.log("Отправка формы...");

			// Создаем объект FormData для отправки формы
			const formData = new FormData(housingForm);
			console.log("Данные формы:", [...formData.entries()]);

			// Отправляем данные на сервер с использованием Ajax
			$.ajax({
				url: "../api/add_listing.php",
				type: "POST",
				data: formData,
				processData: false, // Важно для FormData
				contentType: false, // Важно для FormData
				dataType: "json",
				success: function (data) {
					console.log("Ответ сервера:", data);
					if (data.success) {
						alert(data.message);
						// Закрываем модальное окно и перезагружаем страницу
						closeModal();
						window.location.reload();
					} else {
						alert("Ошибка: " + data.message);
					}
				},
				error: function (xhr, status, error) {
					console.error("Ошибка при отправке формы:", error);
					alert("Произошла ошибка при отправке формы. Пожалуйста, попробуйте позже.");
				},
			});
		});
	}

	// Обработка события закрытия по клику вне модального окна
	window.addEventListener("click", function (e) {
		if (modal && e.target === modal) {
			modal.classList.remove("show");
			document.body.style.overflow = "";
		}
	});
});
