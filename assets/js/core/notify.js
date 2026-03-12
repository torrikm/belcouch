window.App = window.App || {};

window.App.notify = function (message, type, options) {
	if (type && typeof type === "object") {
		options = type;
		type = options.type;
	}

	options = options || {};
	const notification = document.createElement("div");
	notification.className = "notification " + (type || "success");
	notification.textContent = message;
	if (typeof options.className === "string" && options.className.trim()) {
		notification.className += " " + options.className.trim();
	}
	if (options.clickable) {
		notification.style.cursor = "pointer";
	}
	document.body.appendChild(notification);

	if (typeof options.onClick === "function") {
		notification.addEventListener("click", function () {
			options.onClick();
			notification.classList.remove("show");
			setTimeout(function () {
				notification.remove();
			}, 300);
		});
	}

	setTimeout(function () {
		notification.classList.add("show");
	}, 10);

	const duration =
		typeof options.duration === "number" && options.duration > 0
			? options.duration
			: 3000;

	setTimeout(function () {
		notification.classList.remove("show");
		setTimeout(function () {
			notification.remove();
		}, 300);
	}, duration);
};
