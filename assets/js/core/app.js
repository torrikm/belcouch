window.App = window.App || {};

window.App.modules = window.App.modules || {};

window.App.register = function (name, init) {
	window.App.modules[name] = init;
};

window.App.boot = function () {
	Object.keys(window.App.modules).forEach(function (name) {
		if (typeof window.App.modules[name] === "function") {
			window.App.modules[name]();
		}
	});
};

if (!window.__appBootBound) {
	window.__appBootBound = true;
	document.addEventListener("DOMContentLoaded", function () {
		window.App.boot();
	});
}
