window.App = window.App || {};

window.App.api = {
	fetchJson: function (url, options) {
		return fetch(url, Object.assign({ credentials: "include" }, options || {})).then(function (response) {
			return response.json();
		});
	},
	postForm: function (url, formData) {
		return window.App.api.fetchJson(url, {
			method: "POST",
			body: formData
		});
	}
};
