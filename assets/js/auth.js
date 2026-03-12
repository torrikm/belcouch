function openAuthModal(modalId) {
	const modal = document.getElementById(modalId);
	if (!modal) {
		return;
	}

	if (window.App.modal && typeof window.App.modal.open === "function") {
		window.App.modal.open(modal);
		return;
	}
	modal.classList.add("show");
	document.body.style.overflow = "hidden";
}

function closeAuthModal(modalId) {
	const modal = document.getElementById(modalId);
	if (!modal) {
		return;
	}

	if (window.App.modal && typeof window.App.modal.close === "function") {
		window.App.modal.close(modal);
		return;
	}
	modal.classList.remove("show");
	document.body.style.overflow = "";
}

function switchAuthModal(closeId, openId) {
	closeAuthModal(closeId);
	openAuthModal(openId);
}

App.register("auth", function () {
	const dom = window.App.dom;
	const loginForm = dom.qs("#login-form");
	const registerForm = dom.qs("#register-form");
	const AUTH_NOTICE_KEY = "auth_notice";

	function showPendingAuthNotice() {
		const notice = window.sessionStorage.getItem(AUTH_NOTICE_KEY);
		if (!notice) return;
		window.sessionStorage.removeItem(AUTH_NOTICE_KEY);
		if (notice === "login") {
			window.App.notify("Вы вошли в аккаунт", "success");
		}
		if (notice === "logout") {
			window.App.notify("Вы вышли из аккаунта", "success");
		}
	}

	function resetFormState(
		formSelector,
		generalErrorSelector,
		successSelector,
	) {
		dom.qsa(formSelector + " .error-message").forEach(function (element) {
			element.style.display = "none";
		});

		dom.qsa(formSelector + " .form-control").forEach(function (element) {
			element.classList.remove("input-error");
		});

		const generalError = dom.qs(generalErrorSelector);
		if (generalError) {
			generalError.style.display = "none";
			generalError.textContent = "";
		}

		if (successSelector) {
			const successElement = dom.qs(successSelector);
			if (successElement) {
				successElement.style.display = "none";
				successElement.textContent = "";
			}
		}
	}

	function applyErrors(prefix, errors, generalSelector) {
		Object.entries(errors || {}).forEach(function (entry) {
			const field = entry[0];
			const message = entry[1];

			if (field === "general") {
				const errorElement = dom.qs(generalSelector);
				if (errorElement) {
					errorElement.textContent = message;
					errorElement.style.display = "block";
				}
				return;
			}

			const normalizedField = field.replace("_", "-");
			const input = dom.qs("#" + prefix + "-" + normalizedField);
			const errorElement = dom.qs(
				"#" + prefix + "-" + normalizedField + "-error",
			);

			if (input) {
				input.classList.add("input-error");
			}

			if (errorElement) {
				errorElement.textContent = message;
				errorElement.style.display = "block";
			}
		});
	}

	document.addEventListener("click", function (event) {
		const openTrigger = event.target.closest("[data-auth-open]");

		if (openTrigger) {
			event.preventDefault();
			openAuthModal(openTrigger.getAttribute("data-auth-open"));
			return;
		}

		const closeTrigger = event.target.closest("[data-auth-close]");
		if (closeTrigger) {
			event.preventDefault();
			closeAuthModal(closeTrigger.getAttribute("data-auth-close"));
			return;
		}

		const switchTrigger = event.target.closest("[data-auth-switch-open]");
		if (switchTrigger) {
			event.preventDefault();
			switchAuthModal(
				switchTrigger.getAttribute("data-auth-switch-close"),
				switchTrigger.getAttribute("data-auth-switch-open"),
			);
			return;
		}

		const logoutTrigger = event.target.closest('a[href*="logout"]');
		if (logoutTrigger) {
			window.sessionStorage.setItem(AUTH_NOTICE_KEY, "logout");
		}
	});

	showPendingAuthNotice();

	if (loginForm) {
		loginForm.addEventListener("submit", function (event) {
			event.preventDefault();
			resetFormState("#login-form", "#login-error");

			window.App.api
				.postForm(
					API_BASE_URL + "/auth/login.php",
					new FormData(loginForm),
				)
				.then(function (data) {
					if (data.success) {
						window.sessionStorage.setItem(AUTH_NOTICE_KEY, "login");
						window.location.reload();
						return;
					}

					applyErrors("login", data.errors, "#login-error");
				})
				.catch(function (error) {
					console.error("Error:", error);
					const errorElement = dom.qs("#login-error");
					if (errorElement) {
						errorElement.textContent =
							"Произошла ошибка при отправке запроса.";
						errorElement.style.display = "block";
					}
				});
		});
	}

	if (registerForm) {
		registerForm.addEventListener("submit", function (event) {
			event.preventDefault();
			resetFormState(
				"#register-form",
				"#register-error",
				"#register-success",
			);

			window.App.api
				.postForm(
					API_BASE_URL + "/auth/register.php",
					new FormData(registerForm),
				)
				.then(function (data) {
					if (data.success) {
						const successElement = dom.qs("#register-success");
						if (successElement) {
							successElement.textContent = data.message;
							successElement.style.display = "block";
						}

						setTimeout(function () {
							window.location.reload();
						}, 1500);
						return;
					}

					applyErrors("register", data.errors, "#register-error");
				})
				.catch(function (error) {
					console.error("Error:", error);
					const errorElement = dom.qs("#register-error");
					if (errorElement) {
						errorElement.textContent =
							"Произошла ошибка при отправке запроса.";
						errorElement.style.display = "block";
					}
				});
		});
	}
});
