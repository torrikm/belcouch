/**
 * Редактирование профиля пользователя
 */

(function () {
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
		return forms[n % 100 > 4 && n % 100 < 20 ? 2 : cases[Math.min(n % 10, 5)]];
	}

	function updateProfileInfo(userData) {
		const profileName = document.querySelector(".profile-name");
		if (profileName) {
			profileName.textContent = userData.first_name + " " + userData.last_name;
		}

		const profileLocation = document.querySelector(".profile-location");
		if (profileLocation) {
			const profileCity = profileLocation.querySelector(".profile-city");
			const cityValue = String(userData.city || "").trim();
			if (cityValue !== "") {
				profileLocation.style.display = "block";
				if (profileCity) {
					profileCity.textContent = cityValue;
				}
			} else {
				profileLocation.style.display = "none";
			}
		}

		if (userData.avatar_updated) {
			const timestamp = new Date().getTime();
			const avatarUrl = `${API_BASE_URL}/users/get_avatar.php?id=${userData.id}&t=${timestamp}`;
			const avatarContainer = document.querySelector(".profile-avatar-container");

			const profileAvatar = document.querySelector(".profile-avatar-container img");
			if (profileAvatar) {
				profileAvatar.src = avatarUrl;
			}

			const avatarPreview = document.querySelector(".avatar-preview");
			if (avatarPreview) {
				avatarPreview.src = avatarUrl;
			}

			const avatarPlaceholder = document.querySelector(".profile-avatar-placeholder");
			if (avatarContainer && avatarPlaceholder && !profileAvatar) {
				const newAvatar = document.createElement("img");
				newAvatar.src = avatarUrl;
				newAvatar.alt = "Аватар";
				newAvatar.className = "profile-avatar";
				avatarContainer.replaceChild(newAvatar, avatarPlaceholder);
			} else if (avatarPlaceholder) {
				avatarPlaceholder.style.display = "none";
			}
		}

		const genderIcon = document.querySelector(".gender-icon");
		if (genderIcon) {
			const genderValue = String(userData.gender || "not_specified");
			if (genderValue !== "not_specified") {
				genderIcon.src = `../assets/img/icons/${genderValue}.svg`;
				genderIcon.alt = genderValue === "male" ? "Мужской" : "Женский";
				genderIcon.style.display = "inline";
			} else {
				genderIcon.style.display = "none";
			}
		}

		const profileAgeContainer = document.querySelector(".profile-age");
		if (profileAgeContainer) {
			const profileAge = profileAgeContainer.querySelector("span");
			const age = calcAge(userData.birth_date || userData.birthdate);
			if (profileAge) {
				if (age !== null) {
					profileAge.textContent = `${age} ${pluralYears(age)}`;
					profileAgeContainer.style.display = "flex";
				} else {
					profileAge.textContent = "";
					profileAgeContainer.style.display = "none";
				}
			}
		}

		const profileMeta = document.querySelector(".profile-meta");
		const divider = document.querySelector(".divider");
		if (profileMeta && divider) {
			const genderVisible = genderIcon && genderIcon.style.display !== "none";
			const ageVisible = profileAgeContainer && profileAgeContainer.style.display !== "none";
			profileMeta.style.display = genderVisible || ageVisible ? "flex" : "none";
			divider.style.display = genderVisible || ageVisible ? "block" : "none";
		}

		window.App.modal.close("edit-profile-modal");
		window.App.notify("Профиль успешно обновлен");
	}

	const changePasswordBtn = document.getElementById("change-password-btn");
	if (changePasswordBtn) {
		changePasswordBtn.addEventListener("click", function (e) {
			e.preventDefault();
			window.App.modal.close("edit-profile-modal");
			window.App.modal.open("change-password-modal");
		});
	}

	const editProfileForm = document.getElementById("edit-profile-form");
	if (editProfileForm) {
		editProfileForm.addEventListener("submit", function (e) {
			e.preventDefault();
			const cityInput = document.getElementById("city");
			if (
				window.App.cityAutocomplete &&
				typeof window.App.cityAutocomplete.validateInput === "function" &&
				!window.App.cityAutocomplete.validateInput(cityInput)
			) {
				return;
			}

			const formData = new FormData(this);

			const regionSelect = document.getElementById("region_id");
			if (regionSelect && regionSelect.value === "") {
				formData.set("region_id", "");
			}

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
						updateProfileInfo(data.user);
					} else {
						window.App.notify("Ошибка: " + data.message, "error");
					}
				},
				error: function (xhr, status, error) {
					window.App.notify("Произошла ошибка при обновлении профиля", "error");
				},
			});
		});
	}

	const avatarUpload = document.getElementById("avatar-upload");
	if (avatarUpload) {
		avatarUpload.addEventListener("change", function () {
			const file = this.files[0];
			if (file) {
				const reader = new FileReader();
				reader.onload = function (e) {
					const currentAvatar = document.querySelector(".current-avatar");
					if (currentAvatar.tagName === "IMG") {
						currentAvatar.src = e.target.result;
					} else {
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
})();
