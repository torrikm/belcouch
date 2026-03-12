App.register("customSelect", function () {
	const selector =
		"select.form-control:not([multiple]):not([data-custom-select='off'])";
	const wrappers = new WeakMap();

	function getSelectedOption(select) {
		return (
			select.options[select.selectedIndex] || select.options[0] || null
		);
	}

	function getOptionLabel(option) {
		return option ? option.textContent.trim() : "";
	}

	function closeAll(exceptWrapper) {
		document
			.querySelectorAll(".custom-select-wrapper.is-open")
			.forEach((wrapper) => {
				if (wrapper !== exceptWrapper) {
					wrapper.classList.remove("is-open");
				}
			});
	}

	function syncFromSelect(select) {
		const wrapper = wrappers.get(select);
		if (!wrapper) {
			return;
		}

		const trigger = wrapper.querySelector(".custom-select-trigger-label");
		const selected = getSelectedOption(select);
		trigger.textContent = getOptionLabel(selected);

		wrapper
			.querySelectorAll(".custom-select-option")
			.forEach((optionNode) => {
				const isSelected = optionNode.dataset.value === select.value;
				optionNode.classList.toggle("is-selected", isSelected);
				optionNode.setAttribute(
					"aria-selected",
					isSelected ? "true" : "false",
				);
			});
	}

	function buildOptions(select, wrapper, list) {
		list.innerHTML = "";

		Array.from(select.options).forEach((option) => {
			const item = document.createElement("button");
			item.type = "button";
			item.className = "custom-select-option";
			item.dataset.value = option.value;
			item.textContent = getOptionLabel(option);
			item.disabled = option.disabled;
			item.setAttribute("role", "option");
			item.setAttribute(
				"aria-selected",
				option.selected ? "true" : "false",
			);

			if (option.selected) {
				item.classList.add("is-selected");
			}

			item.addEventListener("click", function () {
				if (option.disabled) {
					return;
				}

				select.value = option.value;
				syncFromSelect(select);
				wrapper.classList.remove("is-open");
				select.dispatchEvent(new Event("change", { bubbles: true }));
			});

			list.appendChild(item);
		});
	}

	function createCustomSelect(select) {
		if (
			wrappers.has(select) ||
			select.dataset.customSelectReady === "true"
		) {
			return;
		}

		select.dataset.customSelectReady = "true";
		select.classList.add("custom-select-native");

		const wrapper = document.createElement("div");
		wrapper.className = "custom-select-wrapper";
		wrapper.tabIndex = -1;

		const trigger = document.createElement("button");
		trigger.type = "button";
		trigger.className = "custom-select-trigger";
		trigger.setAttribute("aria-haspopup", "listbox");
		trigger.setAttribute("aria-expanded", "false");
		trigger.innerHTML =
			'<span class="custom-select-trigger-label"></span><span class="custom-select-trigger-icon"></span>';

		const list = document.createElement("div");
		list.className = "custom-select-dropdown";
		list.setAttribute("role", "listbox");

		select.parentNode.insertBefore(wrapper, select);
		wrapper.appendChild(select);
		wrapper.appendChild(trigger);
		wrapper.appendChild(list);

		wrappers.set(select, wrapper);
		buildOptions(select, wrapper, list);
		syncFromSelect(select);

		trigger.addEventListener("click", function () {
			const willOpen = !wrapper.classList.contains("is-open");
			closeAll(wrapper);
			wrapper.classList.toggle("is-open", willOpen);
			trigger.setAttribute("aria-expanded", willOpen ? "true" : "false");
		});

		select.addEventListener("change", function () {
			syncFromSelect(select);
		});

		select.addEventListener("custom-select:refresh", function () {
			buildOptions(select, wrapper, list);
			syncFromSelect(select);
		});

		const observer = new MutationObserver(function () {
			buildOptions(select, wrapper, list);
			syncFromSelect(select);
		});
		observer.observe(select, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: ["disabled"],
		});
	}

	function init(root) {
		(root || document)
			.querySelectorAll(selector)
			.forEach(createCustomSelect);
	}

	document.addEventListener("click", function (event) {
		if (!event.target.closest(".custom-select-wrapper")) {
			closeAll(null);
			document
				.querySelectorAll(
					".custom-select-trigger[aria-expanded='true']",
				)
				.forEach((trigger) => {
					trigger.setAttribute("aria-expanded", "false");
				});
		}
	});

	document.addEventListener("keydown", function (event) {
		if (event.key === "Escape") {
			closeAll(null);
			document
				.querySelectorAll(
					".custom-select-trigger[aria-expanded='true']",
				)
				.forEach((trigger) => {
					trigger.setAttribute("aria-expanded", "false");
				});
		}
	});

	document.addEventListener(
		"reset",
		function (event) {
			setTimeout(function () {
				init(event.target);
				event.target.querySelectorAll(selector).forEach((select) => {
					select.dispatchEvent(new Event("custom-select:refresh"));
				});
			}, 0);
		},
		true,
	);

	document.addEventListener("contentUpdated", function () {
		init(document);
	});

	window.App.initCustomSelects = init;
	init(document);
});
