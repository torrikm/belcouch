/**
 * Скрипт для работы с модальными окнами
 */

// Функция для открытия модального окна по его ID
function openModal(modalId) {
	const modal = document.getElementById(modalId);
	if (modal) {
		modal.classList.add("show");
	}
}

// Функция для закрытия модального окна
function closeModal(modalId) {
	const modal = document.getElementById(modalId);
	if (modal) {
		modal.classList.remove("show");
		// Разблокируем прокрутку основного контента
		document.body.style.overflow = "";
	}
}

// Закрытие модального окна при клике на оверлей
document.addEventListener("DOMContentLoaded", function () {
	// Получаем все модальные окна
	const modalOverlays = document.querySelectorAll(".modal-overlay");

	modalOverlays.forEach((overlay) => {
		// Закрытие при клике на оверлей (но не на само модальное окно)
		overlay.addEventListener("click", function (e) {
			if (e.target === overlay) {
				overlay.classList.remove("show");
				document.body.style.overflow = "";
			}
		});

		// Находим кнопку закрытия внутри каждого модального окна
		const closeBtn = overlay.querySelector(".modal-close");
		if (closeBtn) {
			closeBtn.addEventListener("click", function () {
				overlay.classList.remove("show");
				document.body.style.overflow = "";
			});
		}

		// Находим кнопку отмены внутри каждого модального окна
		const cancelBtn = overlay.querySelector(".btn-cancel");
		if (cancelBtn) {
			cancelBtn.addEventListener("click", function () {
				overlay.classList.remove("show");
				document.body.style.overflow = "";
			});
		}
	});

	// Обработчики кнопок открытия модальных окон
	const editProfileBtn = document.getElementById("edit-profile-btn");
	if (editProfileBtn) {
		editProfileBtn.addEventListener("click", function (e) {
			e.preventDefault();
			openModal("edit-profile-modal");
		});
	}

	const editBioLink = document.querySelector(".profile-section-header .edit-link");
	if (editBioLink) {
		editBioLink.addEventListener("click", function (e) {
			e.preventDefault();
			openModal("edit-bio-modal");
		});
	}

	// Обработчик кнопки смены пароля
	const changePasswordBtn = document.getElementById("change-password-btn");
	if (changePasswordBtn) {
		changePasswordBtn.addEventListener("click", function (e) {
			e.preventDefault();
			// Закрываем окно редактирования профиля и открываем окно смены пароля
			closeModal("edit-profile-modal");
			openModal("change-password-modal");
		});
	}

	// Функция для обновления информации о пользователе на странице
	function updateProfileInfo(userData) {
		console.log("Обновление информации профиля:", userData);

		// Обновляем имя пользователя
		const profileName = document.querySelector(".profile-name");
		if (profileName) {
			profileName.textContent = userData.first_name + " " + userData.last_name;
		}

		// Обновляем город
		const profileCity = document.querySelector(".profile-city");
		const profileLocation = document.querySelector(".profile-location");
		if (profileLocation) {
			if (userData.city && userData.city.trim() !== "") {
				// Если город есть, показываем блок и обновляем текст
				profileLocation.style.display = "block";
				if (profileCity) {
					profileCity.textContent = userData.city;
				}
			} else {
				// Если города нет, скрываем блок
				profileLocation.style.display = "none";
			}
		}

		// Обновляем аватар, если он был обновлен
		if (userData.avatar_updated) {
			console.log("Обновляем аватар на странице");

			// Добавляем случайный параметр к URL, чтобы избежать кеширования
			const timestamp = new Date().getTime();
			const avatarUrl = `../api/get_avatar.php?id=${userData.id}&t=${timestamp}`;

			// Обновляем аватар в шапке профиля
			const profileAvatar = document.querySelector(".profile-avatar-container img");
			if (profileAvatar) {
				console.log("Обновляем аватар в шапке профиля");
				profileAvatar.src = avatarUrl;
			}

			// Обновляем предпросмотр аватара в модальном окне
			const avatarPreview = document.querySelector(".avatar-preview");
			if (avatarPreview) {
				console.log("Обновляем предпросмотр аватара");
				avatarPreview.src = avatarUrl;
			}

			// Если есть плейсхолдер аватара, скрываем его и показываем изображение
			const avatarPlaceholder = document.querySelector(".profile-avatar-placeholder");
			if (avatarPlaceholder) {
				console.log("Скрываем плейсхолдер аватара");
				avatarPlaceholder.style.display = "none";

				// Создаем элемент изображения, если его нет
				if (!profileAvatar) {
					const newAvatar = document.createElement("img");
					newAvatar.src = avatarUrl;
					newAvatar.alt = "Аватар";
					newAvatar.className = "profile-avatar";

					// Добавляем изображение в контейнер
					const avatarContainer = document.querySelector(".profile-avatar-container");
					if (avatarContainer) {
						avatarContainer.insertBefore(newAvatar, avatarPlaceholder);
					}
				}
			}
		}

		// Обновляем информацию о поле
		const genderIcon = document.querySelector(".gender-icon");
		if (genderIcon && userData.gender && userData.gender !== "not_specified") {
			const timestamp = new Date().getTime();
			genderIcon.src = `../assets/img/icons/${userData.gender}.svg?t=${timestamp}`;
			genderIcon.alt = userData.gender === "male" ? "Мужской" : "Женский";
			genderIcon.style.display = "inline";
		} else if (genderIcon) {
			genderIcon.style.display = "none";
		}

		// Обновляем аватар, если он был изменен
		if (userData.avatar_updated) {
			const avatarImages = document.querySelectorAll("img.profile-avatar");
			const timestamp = new Date().getTime(); // Добавляем таймстемп для обхода кеширования
			avatarImages.forEach((img) => {
				const src = img.src.split("?")[0]; // Удаляем старые параметры
				img.src = `${src}?id=${userData.id}&t=${timestamp}`;
			});
		}

		// Закрываем модальное окно
		closeModal("edit-profile-modal");

		// Показываем уведомление об успешном обновлении
		showNotification("Профиль успешно обновлен");
	}

	// Функция для показа уведомления
	function showNotification(message) {
		// Проверяем, есть ли уже уведомление на странице
		let notification = document.querySelector(".notification");

		// Если нет, создаем новый элемент
		if (!notification) {
			notification = document.createElement("div");
			notification.className = "notification";
			document.body.appendChild(notification);

			// Добавляем стили для уведомления
			notification.style.position = "fixed";
			notification.style.bottom = "20px";
			notification.style.right = "20px";
			notification.style.backgroundColor = "var(--hover-button)";
			notification.style.color = "white";
			notification.style.padding = "15px 20px";
			notification.style.borderRadius = "8px";
			notification.style.boxShadow = "0 4px 8px rgba(0,0,0,0.2)";
			notification.style.zIndex = "2000";
			notification.style.opacity = "0";
			notification.style.transition = "opacity 0.3s";
		}

		// Устанавливаем текст уведомления
		notification.textContent = message;

		// Показываем уведомление
		setTimeout(() => {
			notification.style.opacity = "1";
		}, 10);

		// Скрываем через 3 секунды
		setTimeout(() => {
			notification.style.opacity = "0";
			setTimeout(() => {
				notification.remove();
			}, 300);
		}, 3000);
	}

	// Обработка отправки формы редактирования профиля
	const editProfileForm = document.getElementById("edit-profile-form");
	if (editProfileForm) {
		editProfileForm.addEventListener("submit", function (e) {
			e.preventDefault();

			// Создаем объект FormData для отправки данных формы включая файлы
			const formData = new FormData(this);

			// Добавляем отладочную информацию
			console.log("Отправка формы редактирования профиля");

			// Проверяем загружаемый файл аватара
			const avatarInput = document.getElementById("avatar-upload");
			if (avatarInput && avatarInput.files.length > 0) {
				const avatarFile = avatarInput.files[0];
				console.log("Загружаемый аватар:", {
					имя: avatarFile.name,
					тип: avatarFile.type,
					размер: avatarFile.size + " байт",
				});
			} else {
				console.log("Файл аватара не выбран");
			}

			// Выводим все данные формы для отладки
			console.log("Данные формы:");
			for (let pair of formData.entries()) {
				if (pair[0] !== "avatar") {
					// Не выводим бинарные данные аватара
					console.log(pair[0] + ": " + pair[1]);
				} else {
					console.log("avatar: [binary data]");
				}
			}

			// Проверяем значение выбранной области
			const regionSelect = document.getElementById("region_id");
			if (regionSelect) {
				console.log("Выбранная область:", regionSelect.value);

				// Если выбрано пустое значение, устанавливаем NULL
				if (regionSelect.value === "") {
					formData.set("region_id", "");
				}
			}

			// Отправляем данные на сервер
			$.ajax({
				url: "../api/update_profile.php",
				type: "POST",
				data: formData,
				processData: false,
				contentType: false,
				dataType: "json",
				success: function (data) {
					if (data.success) {
						// Если обновление прошло успешно, закрываем модальное окно
						closeModal("edit-profile-modal");
						// Обновляем данные профиля на странице без перезагрузки
						updateProfileInfo(data.user);
						// Показываем уведомление об успехе
						showNotification("Профиль успешно обновлен");
					} else {
						alert("Ошибка: " + data.message);
					}
				},
				error: function (xhr, status, error) {
					console.error("Ошибка:", error);
					alert("Произошла ошибка при обновлении профиля");
				},
			});
		});
	}

	// Функция для обновления информации о пользователе в блоке "О себе"
	function updateBioInfo(userData) {
		const profileBio = document.querySelector(".profile-bio");
		if (profileBio && userData.description) {
			// Очищаем текущее содержимое
			profileBio.innerHTML = "";

			// Разбиваем описание на параграфы
			const paragraphs = userData.description.split("\n");
			paragraphs.forEach((paragraph) => {
				if (paragraph.trim()) {
					const p = document.createElement("p");
					p.textContent = paragraph;
					profileBio.appendChild(p);
				}
			});
		} else if (profileBio && !userData.description) {
			// Если описание пустое, показываем сообщение
			profileBio.innerHTML =
				'<p class="no-bio-text">Добавьте информацию о себе, чтобы другие пользователи могли узнать вас лучше.</p>';
		}

		// Обновляем информацию об образовании
		const educationText = document.querySelector(".detail-icon.education-icon + .detail-text");
		if (educationText) {
			if (userData.education) {
				educationText.textContent = userData.education;
			} else {
				educationText.textContent = "Укажите ваше образование";
			}
		}

		// Обновляем информацию о работе
		const occupationText = document.querySelector(".detail-icon.work-icon + .detail-text");
		if (occupationText) {
			if (userData.occupation) {
				occupationText.textContent = userData.occupation;
			} else {
				occupationText.textContent = "Укажите вашу работу";
			}
		}

		// Обновляем информацию об интересах
		const interestsText = document.querySelector(".detail-icon.hobby-icon + .detail-text");
		if (interestsText) {
			if (userData.interests) {
				interestsText.textContent = userData.interests;
			} else {
				interestsText.textContent = "Укажите ваши интересы";
			}
		}

		// Закрываем модальное окно
		closeModal("edit-bio-modal");

		// Показываем уведомление об успешном обновлении
		showNotification("Информация обновлена");
	}

	// Обработка отправки формы редактирования описания
	const editBioForm = document.getElementById("edit-bio-form");
	if (editBioForm) {
		editBioForm.addEventListener("submit", function (e) {
			e.preventDefault();

			// Создаем объект FormData для отправки данных формы
			const formData = new FormData(this);

			// Отправляем данные на сервер
			$.ajax({
				url: "../api/update_bio.php",
				type: "POST",
				data: formData,
				processData: false,
				contentType: false,
				dataType: "json",
				success: function (data) {
					console.log(data);
					if (data.success) {
						// Если обновление прошло успешно, обновляем информацию на странице
						updateBioInfo(data.user);
					} else {
						// Если возникла ошибка, показываем её пользователю
						alert("Ошибка: " + data.message);
					}
				},
				error: function (xhr, status, error) {
					console.error("Ошибка:", error);
					alert("Произошла ошибка при обновлении описания");
				},
			});
		});
	}

	// Обработка отправки формы смены пароля
	const changePasswordForm = document.getElementById("change-password-form");
	if (changePasswordForm) {
		// Валидация совпадения паролей
		const newPassword = document.getElementById("new_password");
		const confirmPassword = document.getElementById("confirm_password");
		const passwordMatchError = document.getElementById("password-match-error");

		// Проверка совпадения паролей при вводе
		confirmPassword.addEventListener("input", function () {
			if (newPassword.value !== confirmPassword.value) {
				passwordMatchError.style.display = "block";
			} else {
				passwordMatchError.style.display = "none";
			}
		});

		// Проверка совпадения паролей при изменении нового пароля
		newPassword.addEventListener("input", function () {
			if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
				passwordMatchError.style.display = "block";
			} else if (confirmPassword.value) {
				passwordMatchError.style.display = "none";
			}
		});

		// Обработка отправки формы
		changePasswordForm.addEventListener("submit", function (e) {
			e.preventDefault();

			// Проверяем совпадение паролей перед отправкой
			if (newPassword.value !== confirmPassword.value) {
				passwordMatchError.style.display = "block";
				return;
			}

			// Проверка минимальной длины пароля
			if (newPassword.value.length < 8) {
				alert("Пароль должен содержать минимум 8 символов");
				return;
			}

			// Создаем объект FormData для отправки данных формы
			const formData = new FormData(this);

			// Отправляем данные на сервер
			$.ajax({
				url: "../api/change_password.php",
				type: "POST",
				data: formData,
				processData: false,
				contentType: false,
				success: function (data) {
					if (data.success) {
						// Если обновление прошло успешно, закрываем модальное окно
						closeModal("change-password-modal");
						alert("Пароль успешно изменен");
					} else {
						// Если возникла ошибка, показываем её пользователю
						alert("Ошибка: " + data.message);
					}
				},
				error: function (xhr, status, error) {
					console.error("Ошибка:", error);
					alert("Произошла ошибка при изменении пароля");
				},
			});
		});
	}

	// Предпросмотр загружаемого аватара
	const avatarUpload = document.getElementById("avatar-upload");
	if (avatarUpload) {
		avatarUpload.addEventListener("change", function () {
			const file = this.files[0];
			if (file) {
				const reader = new FileReader();
				reader.onload = function (e) {
					const currentAvatar = document.querySelector(".current-avatar");
					if (currentAvatar.tagName === "IMG") {
						// Если это уже изображение, обновляем его src
						currentAvatar.src = e.target.result;
					} else {
						// Если это div с инициалами, заменяем его на img
						const imgElement = document.createElement("img");
						imgElement.src = e.target.result;
						imgElement.classList.add("current-avatar");
						currentAvatar.parentNode.replaceChild(imgElement, currentAvatar);
					}
				};
				reader.readAsDataURL(file);
			}
		});
	}
});
