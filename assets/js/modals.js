/**
 * Скрипт для работы с модальными окнами
 */

(function () {
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
	const modalOverlays = document.querySelectorAll(".modal-overlay");

	window.App.modal = {
		open(target) {
			const overlay = typeof target === "string" ? document.getElementById(target) : target;
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
			const overlay = typeof target === "string" ? document.getElementById(target) : target;
			if (!overlay) return;
			overlay.classList.remove("show");
			document.body.style.overflow = "";
		},
	};

	modalOverlays.forEach((overlay) => {
		bindOverlayCloseBehavior(overlay);

		const closeBtn = overlay.querySelector(".modal-close");
		if (closeBtn) {
			closeBtn.addEventListener("click", function () {
				window.App.modal.close(overlay);
			});
		}

		const cancelBtn = overlay.querySelector(".btn-cancel");
		if (cancelBtn) {
			cancelBtn.addEventListener("click", function () {
				window.App.modal.close(overlay);
			});
		}
	});

	const editProfileBtn = document.getElementById("edit-profile-btn");
	if (editProfileBtn) {
		editProfileBtn.addEventListener("click", function (e) {
			e.preventDefault();
			window.App.modal.open("edit-profile-modal");
		});
	}

	const editBioLink = document.querySelector(".profile-section-header .edit-link");
	if (editBioLink) {
		editBioLink.addEventListener("click", function (e) {
			e.preventDefault();
			window.App.modal.open("edit-bio-modal");
		});
	}
})();
