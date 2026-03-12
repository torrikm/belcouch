App.register("adminVerification", function () {
	document.addEventListener("submit", function (event) {
		const form = event.target.closest(".js-admin-moderation-form");
		if (!form) {
			return;
		}

		event.preventDefault();
		const submitter = event.submitter;
		const formData = new FormData(form);
		if (submitter && submitter.name && submitter.value) {
			formData.set(submitter.name, submitter.value);
		}

		window.App.api
			.postForm(API_BASE_URL + "/admin/moderate_verification.php", formData)
			.then(function (data) {
				if (!data.success) {
					window.App.notify(data.message || "Не удалось обработать заявку", "error");
					return;
				}

				window.App.notify(data.message || "Статус обновлён");
				window.location.reload();
			})
			.catch(function () {
				window.App.notify("Ошибка при обработке заявки", "error");
			});
	});
});
