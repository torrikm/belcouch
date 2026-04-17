(function () {
	let currentForm = null;

	window.openConfirmModal = function (title, message, actionText, form) {
		document.getElementById("confirm-modal-title").textContent = title;
		document.getElementById("confirm-modal-message").textContent = message;
		document.getElementById("confirm-modal-action").textContent = actionText;
		currentForm = form;

		if (window.App && window.App.modal) {
			window.App.modal.open("admin-confirm-modal");
		}
	};

	async function submitCurrentForm() {
		if (!currentForm) return;

		const formData = new FormData(currentForm);
		const confirmBtn = document.getElementById("confirm-modal-action");
		confirmBtn.disabled = true;
		confirmBtn.textContent = "Отправка...";

		try {
			const response = await fetch(currentForm.action, {
				method: "POST",
				body: formData,
			});

			const data = await response.json();

			if (data.success) {
				window.location.reload();
			} else {
				alert("Ошибка: " + (data.message || "Неизвестная ошибка"));
				confirmBtn.disabled = false;
				confirmBtn.textContent = "Подтвердить";
			}
		} catch (error) {
			alert("Ошибка при отправке: " + error.message);
			confirmBtn.disabled = false;
			confirmBtn.textContent = "Подтвердить";
		}

		if (window.App && window.App.modal) {
			window.App.modal.close("admin-confirm-modal");
		}
	}

	const confirmBtn = document.getElementById("confirm-modal-action");
	if (confirmBtn) {
		confirmBtn.addEventListener("click", submitCurrentForm);
	}
})();
