/**
 * Скрипт для работы с модальными окнами
 */

// Функция для открытия модального окна по его ID
function openModal(modalId) {
	const modal = document.getElementById(modalId);
	if (modal) {
		const modalWindow = modal.querySelector(".modal");
		if (modalWindow) {
			if (modal.dataset.modalWidth) {
				modalWindow.style.maxWidth = modal.dataset.modalWidth;
			} else {
				modalWindow.style.removeProperty("max-width");
			}
		}
		modal.classList.add("show");
		document.body.style.overflow = "hidden";
	}
}

// Функция для закрытия модального окна
function closeModal(modalId) {
	const modal = document.getElementById(modalId);
	if (modal) {
		modal.classList.remove("show");
		document.body.style.overflow = "";
	}
}

function bindOverlayCloseBehavior(overlay) {
	let backdropPressStarted = false;
	let downX = 0;
	let downY = 0;

	overlay.addEventListener("mousedown", function (e) {
		backdropPressStarted = e.target === overlay;
		if (backdropPressStarted) {
			downX = e.clientX;
			downY = e.clientY;
		}
	});

	overlay.addEventListener("mouseup", function (e) {
		if (backdropPressStarted && e.target === overlay) {
			const movedX = Math.abs(e.clientX - downX);
			const movedY = Math.abs(e.clientY - downY);
			const isTap = movedX < 5 && movedY < 5;

			if (isTap) {
				overlay.classList.remove("show");
				document.body.style.overflow = "";
			}
		}
		backdropPressStarted = false;
	});
}

// Закрытие модального окна при клике на оверлей
App.register("profileModals", function () {
	// Получаем все модальные окна
	const modalOverlays = document.querySelectorAll(".modal-overlay");

	window.App.modal = {
		open(target) {
			const overlay =
				typeof target === "string"
					? document.getElementById(target)
					: target;
			if (!overlay) return;
			const modalWindow = overlay.querySelector(".modal");
			if (modalWindow) {
				if (overlay.dataset.modalWidth) {
					modalWindow.style.maxWidth = overlay.dataset.modalWidth;
				} else {
					modalWindow.style.removeProperty("max-width");
				}
			}
			overlay.classList.add("show");
			document.body.style.overflow = "hidden";
		},
		close(target) {
			const overlay =
				typeof target === "string"
					? document.getElementById(target)
					: target;
			if (!overlay) return;
			overlay.classList.remove("show");
			document.body.style.overflow = "";
		},
	};

	modalOverlays.forEach((overlay) => {
		bindOverlayCloseBehavior(overlay);

		// Находим кнопку закрытия внутри каждого модального окна
		const closeBtn = overlay.querySelector(".modal-close");
		if (closeBtn) {
			closeBtn.addEventListener("click", function () {
				window.App.modal.close(overlay);
			});
		}

		// Находим кнопку отмены внутри каждого модального окна
		const cancelBtn = overlay.querySelector(".btn-cancel");
		if (cancelBtn) {
			cancelBtn.addEventListener("click", function () {
				window.App.modal.close(overlay);
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

	const editBioLink = document.querySelector(
		".profile-section-header .edit-link",
	);
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

		function calcAge(dateStr) {
			if (!dateStr) return null;
			const parts = String(dateStr).split("-");
			if (parts.length !== 3) return null;
			const year = Number(parts[0]);
			const month = Number(parts[1]) - 1;
			const day = Number(parts[2]);
			if (!year || month < 0 || month > 11 || !day) return null;
			const today = new Date();
			let age = today.getFullYear() - year;
			const m = today.getMonth() - month;
			if (m < 0 || (m === 0 && today.getDate() < day)) {
				age--;
			}
			return age >= 0 ? age : null;
		}

		function pluralYears(n) {
			const forms = ["год", "года", "лет"];
			const cases = [2, 0, 1, 1, 1, 2];
			return forms[
				n % 100 > 4 && n % 100 < 20 ? 2 : cases[Math.min(n % 10, 5)]
			];
		}

		function ensureProfileInfoContainer() {
			return document.querySelector(".profile-info");
		}

		function getProfileRatingContainer() {
			return document.querySelector(".profile-rating-container");
		}

		function ensureProfileLocationBlock() {
			let profileLocation = document.querySelector(".profile-location");
			if (profileLocation) {
				return profileLocation;
			}

			const profileInfo = ensureProfileInfoContainer();
			if (!profileInfo) {
				return null;
			}

			profileLocation = document.createElement("div");
			profileLocation.className = "profile-location";
			profileLocation.innerHTML =
				'<i class="location-icon"></i><span class="profile-city"></span>';

			const profileMeta = document.querySelector(".profile-meta");
			const divider = document.querySelector(".divider");
			const ratingContainer = getProfileRatingContainer();
			if (profileMeta) {
				profileInfo.insertBefore(profileLocation, profileMeta);
			} else if (divider) {
				profileInfo.insertBefore(profileLocation, divider);
			} else if (ratingContainer) {
				profileInfo.insertBefore(profileLocation, ratingContainer);
			} else {
				profileInfo.appendChild(profileLocation);
			}

			return profileLocation;
		}

		function ensureProfileMetaBlock() {
			let profileMeta = document.querySelector(".profile-meta");
			if (profileMeta) {
				return profileMeta;
			}

			const profileInfo = ensureProfileInfoContainer();
			if (!profileInfo) {
				return null;
			}

			profileMeta = document.createElement("div");
			profileMeta.className = "profile-meta";

			const divider = document.querySelector(".divider");
			const ratingContainer = getProfileRatingContainer();
			if (divider) {
				profileInfo.insertBefore(profileMeta, divider);
			} else if (ratingContainer) {
				profileInfo.insertBefore(profileMeta, ratingContainer);
			} else {
				profileInfo.appendChild(profileMeta);
			}

			return profileMeta;
		}

		// Обновляем имя пользователя
		const profileName = document.querySelector(".profile-name");
		if (profileName) {
			profileName.textContent =
				userData.first_name + " " + userData.last_name;
		}

		// Обновляем город
		let profileLocation = document.querySelector(".profile-location");
		const cityValue = String(userData.city || "").trim();
		if (!profileLocation && cityValue !== "") {
			profileLocation = ensureProfileLocationBlock();
		}

		if (profileLocation) {
			const profileCity = profileLocation.querySelector(".profile-city");
			if (cityValue !== "") {
				profileLocation.style.display = "block";
				if (profileCity) {
					profileCity.textContent = cityValue;
				}
			} else {
				profileLocation.style.display = "none";
			}
		}

		// Обновляем аватар, если он был обновлен
		if (userData.avatar_updated) {
			console.log("Обновляем аватар на странице");

			// Добавляем случайный параметр к URL, чтобы избежать кеширования
			const timestamp = new Date().getTime();
			const avatarUrl = `${API_BASE_URL}/users/get_avatar.php?id=${userData.id}&t=${timestamp}`;

			// Обновляем аватар в шапке профиля
			const profileAvatar = document.querySelector(
				".profile-avatar-container img",
			);
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
			const avatarPlaceholder = document.querySelector(
				".profile-avatar-placeholder",
			);
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
					const avatarContainer = document.querySelector(
						".profile-avatar-container",
					);
					if (avatarContainer) {
						avatarContainer.insertBefore(
							newAvatar,
							avatarPlaceholder,
						);
					}
				}
			}
		}

		// Обновляем информацию о поле
		let genderIcon = document.querySelector(".gender-icon");
		const genderValue = String(userData.gender || "not_specified");
		if (!genderIcon && genderValue !== "not_specified") {
			const profileMeta = ensureProfileMetaBlock();
			if (profileMeta) {
				const genderBlock = document.createElement("div");
				genderBlock.className = "profile-gender";
				genderIcon = document.createElement("img");
				genderIcon.className = "gender-icon";
				genderIcon.alt = genderValue === "male" ? "Мужской" : "Женский";
				genderBlock.appendChild(genderIcon);
				profileMeta.appendChild(genderBlock);
			}
		}
		if (genderIcon && genderValue !== "not_specified") {
			const timestamp = new Date().getTime();
			genderIcon.src = `../assets/img/icons/${genderValue}.svg?t=${timestamp}`;
			genderIcon.alt = genderValue === "male" ? "Мужской" : "Женский";
			genderIcon.style.display = "inline";
		} else if (genderIcon) {
			genderIcon.style.display = "none";
		}

		// Обновляем возраст (и показываем/скрываем блок meta/divider)
		let profileAgeContainer = document.querySelector(".profile-age");
		let profileAge = profileAgeContainer
			? profileAgeContainer.querySelector("span")
			: null;
		const profileMeta = document.querySelector(".profile-meta");
		const divider = document.querySelector(".divider");
		const age = calcAge(userData.birth_date || userData.birthdate);
		if (!profileAgeContainer && age !== null) {
			const metaBlock = ensureProfileMetaBlock();
			if (metaBlock) {
				profileAgeContainer = document.createElement("div");
				profileAgeContainer.className = "profile-age";
				profileAge = document.createElement("span");
				profileAgeContainer.appendChild(profileAge);
				metaBlock.appendChild(profileAgeContainer);
			}
		}

		if (profileAge && profileAgeContainer) {
			if (age !== null) {
				profileAge.textContent = `${age} ${pluralYears(age)}`;
				profileAgeContainer.style.display = "flex";
			} else {
				profileAge.textContent = "";
				profileAgeContainer.style.display = "none";
			}
		}

		if (profileMeta) {
			const genderVisible =
				genderIcon && genderIcon.style.display !== "none";
			const ageVisible =
				profileAgeContainer &&
				profileAgeContainer.style.display !== "none";
			profileMeta.style.display =
				genderVisible || ageVisible ? "flex" : "none";
			if (profileMeta.style.display !== "none") {
				let profileDivider = document.querySelector(".divider");
				if (!profileDivider) {
					const ratingContainer = getProfileRatingContainer();
					if (ratingContainer && profileMeta.parentNode) {
						profileDivider = document.createElement("div");
						profileDivider.className = "divider";
						profileMeta.parentNode.insertBefore(
							profileDivider,
							ratingContainer,
						);
					}
				}
				if (profileDivider) {
					profileDivider.style.display = "block";
				}
			} else if (divider) {
				divider.style.display = "none";
			}
		}

		// Обновляем аватар, если он был изменен
		if (userData.avatar_updated) {
			const avatarImages =
				document.querySelectorAll("img.profile-avatar");
			const timestamp = new Date().getTime(); // Добавляем таймстемп для обхода кеширования
			avatarImages.forEach((img) => {
				const src = img.src.split("?")[0]; // Удаляем старые параметры
				img.src = `${src}?id=${userData.id}&t=${timestamp}`;
			});
		}

		// Закрываем модальное окно
		closeModal("edit-profile-modal");

		// Показываем уведомление об успешном обновлении
		window.App.notify("Профиль успешно обновлен");
	}

	// Обработка отправки формы редактирования профиля
	const editProfileForm = document.getElementById("edit-profile-form");
	if (editProfileForm) {
		editProfileForm.addEventListener("submit", function (e) {
			e.preventDefault();
			const cityInput = document.getElementById("city");
			if (
				window.App.cityAutocomplete &&
				typeof window.App.cityAutocomplete.validateInput ===
					"function" &&
				!window.App.cityAutocomplete.validateInput(cityInput)
			) {
				return;
			}

			const formData = new FormData(this);
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
				xhrFields: { withCredentials: true },
				url: API_BASE_URL + "/users/update_profile.php",
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
						window.App.notify("Профиль успешно обновлен");
					} else {
						window.App.notify("Ошибка: " + data.message, "error");
					}
				},
				error: function (xhr, status, error) {
					console.error("Ошибка:", error);
					window.App.notify(
						"Произошла ошибка при обновлении профиля",
						"error",
					);
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
		const educationText = document.querySelector(
			".detail-icon.education-icon + .detail-text",
		);
		if (educationText) {
			if (userData.education) {
				educationText.textContent = userData.education;
			} else {
				educationText.textContent = "Укажите ваше образование";
			}
		}

		// Обновляем информацию о работе
		const occupationText = document.querySelector(
			".detail-icon.work-icon + .detail-text",
		);
		if (occupationText) {
			if (userData.occupation) {
				occupationText.textContent = userData.occupation;
			} else {
				occupationText.textContent = "Укажите вашу работу";
			}
		}

		// Обновляем информацию об интересах
		const interestsText = document.querySelector(
			".detail-icon.hobby-icon + .detail-text",
		);
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
		window.App.notify("Информация обновлена");
	}

	// Обработка отправки формы редактирования описания
	const editBioForm = document.getElementById("edit-bio-form");
	const descriptionField = document.getElementById("description");
	const descriptionLengthValue = document.getElementById(
		"description-length-value",
	);
	const BIO_MAX_LENGTH = 800;

	function updateDescriptionLength() {
		if (!descriptionField || !descriptionLengthValue) {
			return;
		}

		descriptionLengthValue.textContent = descriptionField.value.length;
	}

	if (descriptionField) {
		descriptionField.setAttribute("maxlength", BIO_MAX_LENGTH);
		descriptionField.addEventListener("input", updateDescriptionLength);
		updateDescriptionLength();
	}

	if (editBioForm) {
		editBioForm.addEventListener("submit", function (e) {
			e.preventDefault();

			if (
				descriptionField &&
				descriptionField.value.length > BIO_MAX_LENGTH
			) {
				window.App.notify(
					"Описание не должно превышать 800 символов",
					"error",
				);
				return;
			}

			// Создаем объект FormData для отправки данных формы
			const formData = new FormData(this);

			// Отправляем данные на сервер
			$.ajax({
				xhrFields: { withCredentials: true },
				url: API_BASE_URL + "/users/update_bio.php",
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
						window.App.notify("Ошибка: " + data.message, "error");
					}
				},
				error: function (xhr, status, error) {
					console.error("Ошибка:", error);
					window.App.notify(
						"Произошла ошибка при обновлении описания",
						"error",
					);
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
		const passwordMatchError = document.getElementById(
			"password-match-error",
		);

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
			if (
				confirmPassword.value &&
				newPassword.value !== confirmPassword.value
			) {
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
				window.App.notify(
					"Пароль должен содержать минимум 8 символов",
					"error",
				);
				return;
			}

			// Создаем объект FormData для отправки данных формы
			const formData = new FormData(this);

			// Отправляем данные на сервер
			$.ajax({
				xhrFields: { withCredentials: true },
				url: API_BASE_URL + "/users/change_password.php",
				type: "POST",
				data: formData,
				processData: false,
				contentType: false,
				success: function (data) {
					if (data.success) {
						// Если обновление прошло успешно, закрываем модальное окно
						closeModal("change-password-modal");
						window.App.notify("Пароль успешно изменен");
					} else {
						window.App.notify("Ошибка: " + data.message, "error");
					}
				},
				error: function (xhr, status, error) {
					console.error("Ошибка:", error);
					window.App.notify(
						"Произошла ошибка при изменении пароля",
						"error",
					);
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
					const currentAvatar =
						document.querySelector(".current-avatar");
					if (currentAvatar.tagName === "IMG") {
						// Если это уже изображение, обновляем его src
						currentAvatar.src = e.target.result;
					} else {
						// Если это div с инициалами, заменяем его на img
						const imgElement = document.createElement("img");
						imgElement.src = e.target.result;
						imgElement.classList.add("current-avatar");
						currentAvatar.parentNode.replaceChild(
							imgElement,
							currentAvatar,
						);
					}
				};
				reader.readAsDataURL(file);
			}
		});
	}
});
