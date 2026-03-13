App.register("supportForm", function () {
	const form = document.getElementById("support-form");
	if (!form) {
		return;
	}

	form.addEventListener("submit", function (event) {
		event.preventDefault();
		const formData = new FormData(form);

		window.App.api
			.postForm(API_BASE_URL + "/support/send.php", formData)
			.then(function (data) {
				if (!data.success) {
					window.App.notify(
						data.message || "Не удалось отправить обращение",
						"error",
					);
					return;
				}

				window.App.notify(data.message || "Обращение отправлено");
				form.reset();
			})
			.catch(function () {
				window.App.notify(
					"Не удалось получить корректный ответ от сервера. Проверьте настройки почты.",
					"error",
				);
			});
	});
});
