/**
 * Редактирование описания "О себе"
 */

(function () {
	function updateBioInfo(userData) {
		const profileBio = document.querySelector(".profile-bio");
		if (profileBio && userData.description) {
			profileBio.innerHTML = "";
			const paragraphs = userData.description.split("\n");
			paragraphs.forEach((paragraph) => {
				if (paragraph.trim()) {
					const p = document.createElement("p");
					p.textContent = paragraph;
					profileBio.appendChild(p);
				}
			});
		} else if (profileBio && !userData.description) {
			profileBio.innerHTML =
				'<p class="no-bio-text">Добавьте информацию о себе, чтобы другие пользователи могли узнать вас лучше.</p>';
		}

		const educationText = document.querySelector(".detail-icon.education-icon + .detail-text");
		if (educationText) {
			educationText.textContent = userData.education || "Укажите ваше образование";
		}

		const occupationText = document.querySelector(".detail-icon.work-icon + .detail-text");
		if (occupationText) {
			occupationText.textContent = userData.occupation || "Укажите вашу работу";
		}

		const interestsText = document.querySelector(".detail-icon.hobby-icon + .detail-text");
		if (interestsText) {
			interestsText.textContent = userData.interests || "Укажите ваши интересы";
		}

		window.App.modal.close("edit-bio-modal");
		window.App.notify("Информация обновлена");
	}

	const editBioForm = document.getElementById("edit-bio-form");
	const descriptionField = document.getElementById("description");
	const descriptionLengthValue = document.getElementById("description-length-value");
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

			if (descriptionField && descriptionField.value.length > BIO_MAX_LENGTH) {
				window.App.notify("Описание не должно превышать 800 символов", "error");
				return;
			}

			const formData = new FormData(this);

			$.ajax({
				xhrFields: { withCredentials: true },
				url: API_BASE_URL + "/users/update_bio.php",
				type: "POST",
				data: formData,
				processData: false,
				contentType: false,
				dataType: "json",
				success: function (data) {
					if (data.success) {
						updateBioInfo(data.user);
					} else {
						window.App.notify("Ошибка: " + data.message, "error");
					}
				},
				error: function () {
					window.App.notify("Произошла ошибка при обновлении описания", "error");
				},
			});
		});
	}
})();
