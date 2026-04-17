(function () {
	const form = document.getElementById("listing-report-form");
	const reasonSelect = document.getElementById("listing-report-reason");
	if (!form || !reasonSelect) {
		return;
	}

	function loadReportReasons() {
		$.ajax({
			url: API_BASE_URL + "/listings/get_report_reasons.php",
			type: "GET",
			dataType: "json",
			success: function (data) {
				if (data.success && data.reasons) {
					reasonSelect.innerHTML = '<option value="">Выберите причину</option>';
					data.reasons.forEach(function (reason) {
						const option = document.createElement("option");
						option.value = reason.code;
						option.textContent = reason.label;
						reasonSelect.appendChild(option);
					});
				}
			},
			error: function () {
				reasonSelect.innerHTML = '<option value="">Ошибка загрузки причин</option>';
			},
		});
	}

	loadReportReasons();

	form.addEventListener("submit", function (event) {
		event.preventDefault();
		const formData = new FormData(form);

		window.App.api
			.postForm(API_BASE_URL + "/listings/report_listing.php", formData)
			.then(function (data) {
				if (!data.success) {
					window.App.notify(data.message || "Не удалось отправить жалобу", "error");
					return;
				}

				window.App.notify(data.message || "Жалоба отправлена");
				form.reset();
				if (window.App.modal && typeof window.App.modal.close === "function") {
					window.App.modal.close("listing-report-modal");
				}
			})
			.catch(function () {
				window.App.notify("Ошибка при отправке жалобы", "error");
			});
	});
})();
