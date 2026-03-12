window.App = window.App || {};

window.App.auth = {
	isLoggedIn: function () {
		const logoutLink = document.querySelector(".logout-btn");
		if (logoutLink) {
			return true;
		}

		const userLoggedInMeta = document.querySelector('meta[name="user-logged-in"]');
		return !!(userLoggedInMeta && userLoggedInMeta.getAttribute("content") === "true");
	}
};
