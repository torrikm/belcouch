/**
 * Смена пароля
 */

(function () {
	const changePasswordForm = document.getElementById("change-password-form");
	if (changePasswordForm) {
		const newPassword = document.getElementById("new_password");
		const confirmPassword = document.getElementById("confirm_password");
		const passwordMatchError = document.getElementById("password-match-error");

		confirmPassword.addEventListener("input", function () {
			if (newPassword.value !== confirmPassword.value) {
				passwordMatchError.style.display = "block";
			} else {
				passwordMatchError.style.display = "none";
			}
		});

		newPassword.addEventListener("input", function () {
			if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
				passwordMatchError.style.display = "block";
			} else if (confirmPassword.value) {
				passwordMatchError.style.display = "none";
			}
		});

		changePasswordForm.addEventListener("submit", function (e) {
			e.preventDefault();

			if (newPassword.value !== confirmPassword.value) {
				passwordMatchError.style.display = "block";
				return;
			}

			if (newPassword.value.length < 8) {
				window.App.notify("Пароль должен содержать минимум 8 символов", "error");
				return;
			}

			const formData = new FormData(this);

			$.ajax({
				xhrFields: { withCredentials: true },
				url: API_BASE_URL + "/users/change_password.php",
				type: "POST",
				data: formData,
				processData: false,
				contentType: false,
				dataType: "json",
				success: function (data) {
					if (data.success) {
						window.App.modal.close("change-password-modal");
						window.App.notify("Пароль успешно изменен");
					} else {
						window.App.notify("Ошибка: " + data.message, "error");
					}
				},
				error: function () {
					window.App.notify("Произошла ошибка при изменении пароля", "error");
				},
			});
		});
	}
})();
