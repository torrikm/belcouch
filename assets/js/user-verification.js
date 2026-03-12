App.register("userVerification", function () {
	const form = document.getElementById("verification-request-form");
	const fileInput = document.getElementById("document_photo");
	const fileName = document.getElementById("verification-file-name");
	const previewContainer = document.getElementById(
		"verification-preview-container",
	);
	const previewImage = document.getElementById("verification-preview-image");
	if (!form) {
		return;
	}

	if (fileInput && fileName) {
		fileInput.addEventListener("change", function () {
			const file = this.files && this.files[0] ? this.files[0] : null;
			fileName.textContent = file ? file.name : "Файл не выбран";

			if (!previewContainer || !previewImage) {
				return;
			}

			if (!file) {
				previewImage.src = "";
				previewContainer.classList.add("verification-preview--hidden");
				return;
			}

			const reader = new FileReader();
			reader.onload = function (event) {
				previewImage.src =
					event.target && event.target.result
						? event.target.result
						: "";
				previewContainer.classList.remove(
					"verification-preview--hidden",
				);
			};
			reader.readAsDataURL(file);
		});
	}

	form.addEventListener("submit", function (event) {
		event.preventDefault();
		const formData = new FormData(form);
		window.App.api
			.postForm(
				API_BASE_URL + "/admin/submit_verification_request.php",
				formData,
			)
			.then(function (data) {
				if (!data.success) {
					window.App.notify(
						data.message || "Не удалось отправить заявку",
						"error",
					);
					return;
				}

				window.App.notify(
					data.message ||
						"Фото отправлены на проверку. Мы уведомим вас после модерации.",
				);
				window.setTimeout(function () {
					window.location.reload();
				}, 1200);
			})
			.catch(function () {
				window.App.notify("Ошибка при отправке заявки", "error");
			});
	});
});
