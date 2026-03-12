/**
 * Скрипт для живого поиска на главной странице
 */
App.register("liveSearch", function () {
	const searchInput = document.getElementById("search-input");
	const searchResults = document.getElementById("search-results");
	const minSearchLength = 2;
	let searchTimer;

	if (!searchInput || !searchResults) {
		return;
	}

	function displaySearchResults(results) {
		searchResults.innerHTML = "";

		if (results.length === 0) {
			searchResults.innerHTML =
				'<div class="search-no-results">Ничего не найдено</div>';
			searchResults.classList.add("active");
			return;
		}

		results.forEach(function (result) {
			const resultItem = document.createElement("div");
			resultItem.className = "search-result-item";

			resultItem.addEventListener("click", function () {
				window.location.href = `profile/housing.php?id=${result.user_id}`;
			});

			resultItem.innerHTML = `
                <img src="${result.image}" alt="${result.title}" class="search-result-image">
                <div class="search-result-info">
                    <h4 class="search-result-title">${result.title}</h4>
                    <p class="search-result-location">${result.city}</p>
                    <span class="search-result-type">${result.property_type}</span>
                </div>
            `;

			searchResults.appendChild(resultItem);
		});

		searchResults.classList.add("active");
	}

	function fetchSearchResults(query) {
		$.ajax({
			xhrFields: { withCredentials: true },
			url: API_BASE_URL + "/search_listings.php",
			type: "GET",
			data: { query: query },
			dataType: "json",
			success: function (data) {
				if (data.success) {
					displaySearchResults(data.results);
				}
			},
			error: function (xhr, status, error) {
				console.error("Ошибка при выполнении поиска:", error);
			},
		});
	}

	searchInput.addEventListener("input", function () {
		clearTimeout(searchTimer);

		const query = this.value.trim();

		if (query.length < minSearchLength) {
			searchResults.classList.remove("active");
			searchResults.innerHTML = "";
			return;
		}

		searchTimer = setTimeout(function () {
			fetchSearchResults(query);
		}, 300);
	});

	document.addEventListener("click", function (event) {
		if (!event.target.closest(".search-input-container")) {
			searchResults.classList.remove("active");
		}
	});
});
