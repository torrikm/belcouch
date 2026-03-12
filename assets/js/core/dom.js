window.App = window.App || {};

window.App.dom = {
	qs: function (selector, root) {
		return (root || document).querySelector(selector);
	},
	qsa: function (selector, root) {
		return Array.from((root || document).querySelectorAll(selector));
	},
	on: function (element, eventName, handler) {
		if (element) {
			element.addEventListener(eventName, handler);
		}
	},
	create: function (tagName, className) {
		const element = document.createElement(tagName);
		if (className) {
			element.className = className;
		}
		return element;
	}
};
