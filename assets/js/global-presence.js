App.register("globalPresence", function () {
	if (document.getElementById("chat-page")) {
		return;
	}

	const wsUrlMeta = document.querySelector('meta[name="chat-ws-url"]');
	const userIdMeta = document.querySelector('meta[name="chat-ws-user-id"]');
	const tsMeta = document.querySelector('meta[name="chat-ws-ts"]');
	const sigMeta = document.querySelector('meta[name="chat-ws-sig"]');

	const wsUrl = wsUrlMeta ? wsUrlMeta.content : "";
	const userId = userIdMeta ? Number(userIdMeta.content || 0) : 0;
	const ts = tsMeta ? tsMeta.content : "";
	const sig = sigMeta ? sigMeta.content : "";

	if (!wsUrl || !userId || !ts || !sig) {
		return;
	}

	let socket = null;
	let reconnectTimer = null;
	let notifyInFlight = false;
	const notifiedMessageIds = new Set();

	function getConversationPreviewText(conversation) {
		const latest = (conversation || {}).latest_message || {};
		if (latest.is_deleted) return "Сообщение удалено";
		if ((latest.message || "").trim()) return latest.message.trim();
		return latest.image_path ? "Вложение" : "Новое сообщение";
	}

	function notifyAboutLatestMessage(partnerId) {
		const normalizedPartnerId = Number(partnerId || 0);
		if (!normalizedPartnerId || notifyInFlight) {
			return;
		}

		notifyInFlight = true;
		window.App.api
			.fetchJson(API_BASE_URL + "/chat/conversations.php")
			.then(function (response) {
				if (
					!response ||
					!response.success ||
					!Array.isArray(response.conversations)
				) {
					return;
				}

				const conversation = response.conversations.find(
					function (item) {
						return (
							Number(((item || {}).partner || {}).id || 0) ===
							normalizedPartnerId
						);
					},
				);

				if (!conversation) {
					return;
				}

				const latestMessage = conversation.latest_message || {};
				const latestMessageId = Number(latestMessage.id || 0);

				if (
					!latestMessageId ||
					notifiedMessageIds.has(latestMessageId)
				) {
					return;
				}

				notifiedMessageIds.add(latestMessageId);

				const partner = conversation.partner || {};
				const partnerName =
					partner.full_name || partner.first_name || "Пользователь";

				window.App.notify(
					"Новое сообщение от " +
						partnerName +
						": " +
						getConversationPreviewText(conversation),
					"success",
					{
						clickable: true,
						duration: 5000,
						onClick: function () {
							window.location.href =
								"/chat?user_id=" + normalizedPartnerId;
						},
					},
				);
			})
			.finally(function () {
				notifyInFlight = false;
			});
	}

	function appendAccountNotification(payload) {
		const title = ((payload || {}).title || "").trim();
		const message = ((payload || {}).message || "").trim();
		const notificationId = Number((payload || {}).notification_id || 0);
		if (!title && !message) {
			return;
		}

		window.App.notify(title ? title + (message ? ": " + message : "") : message, "error", {
			clickable: false,
			duration: 7000,
		});

		const notificationBlocks = document.querySelectorAll(".account-notifications");
		if (!notificationBlocks.length) {
			return;
		}

		const date = new Date();
		const formattedDate = date.toLocaleString("ru-RU", {
			day: "2-digit",
			month: "2-digit",
			year: "numeric",
			hour: "2-digit",
			minute: "2-digit",
		});

		notificationBlocks.forEach(function (block) {
			const existingItem = block.querySelector(".account-notification-item");
			const item = existingItem || document.createElement("div");
			item.className = "account-notification-item";
			if (notificationId) {
				item.dataset.notificationId = String(notificationId);
			}
			item.innerHTML =
				'<button type="button" class="account-notification-close" aria-label="Закрыть уведомление">' +
				'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
				'<line x1="6" y1="6" x2="18" y2="18"></line>' +
				'<line x1="18" y1="6" x2="6" y2="18"></line>' +
				'</svg>' +
				"</button>" +
				'<div class="account-notification-title"></div>' +
				'<div class="account-notification-message"></div>' +
				'<div class="account-notification-date"></div>';
			item.querySelector(".account-notification-title").textContent =
				title || "Уведомление";
			item.querySelector(".account-notification-message").textContent = message;
			item.querySelector(".account-notification-date").textContent = formattedDate;
			if (!existingItem) {
				block.appendChild(item);
			}
		});
	}

	function deleteAccountNotification(notificationId, item, closeButton) {
		if (!notificationId || !window.accountNotificationsCsrfToken) {
			return Promise.reject(new Error("missing-notification-data"));
		}

		if (closeButton) {
			closeButton.disabled = true;
		}

		const formData = new FormData();
		formData.append("notification_id", String(notificationId));
		formData.append("csrf_token", String(window.accountNotificationsCsrfToken));

		return window.App.api
			.postForm(API_BASE_URL + "/notifications/delete_notification.php", formData)
			.then(function (response) {
				if (!response || !response.success) {
					throw new Error((response || {}).message || "Не удалось удалить уведомление");
				}

				if (item) {
					const block = item.closest(".account-notifications");
					item.remove();
					if (block && !block.querySelector(".account-notification-item")) {
						block.remove();
					}
				}
			})
			.catch(function (error) {
				if (closeButton) {
					closeButton.disabled = false;
				}
				window.App.notify(
					"Не удалось удалить уведомление",
					"error",
					{ clickable: false, duration: 4000 },
				);
				throw error;
			});
	}

	document.addEventListener("click", function (event) {
		const closeButton = event.target.closest(".account-notification-close");
		if (!closeButton) {
			return;
		}

		const item = closeButton.closest(".account-notification-item");
		const notificationId = Number(
			(item && item.dataset.notificationId) || 0,
		);
		if (item) {
			deleteAccountNotification(notificationId, item, closeButton).catch(function () {
				// Ошибка уже показана в notify
			});
		}
	});

	function connect() {
		if (
			socket &&
			(socket.readyState === WebSocket.OPEN ||
				socket.readyState === WebSocket.CONNECTING)
		) {
			return;
		}

		const params = new URLSearchParams({
			user_id: String(userId),
			ts: String(ts),
			sig: String(sig),
		});

		socket = new WebSocket(wsUrl + "?" + params.toString());

		socket.onmessage = function (event) {
			const payload = JSON.parse(event.data || "{}");
			if (payload.event !== "chat:message_created") {
				if (payload.event === "account:notification") {
					const accountPayload = (payload || {}).payload || {};
					appendAccountNotification(accountPayload);
					if (
						accountPayload.type === "listing_deleted_by_report" &&
						typeof window.dispatchEvent === "function"
					) {
						window.dispatchEvent(
							new CustomEvent("account:listing_deleted_by_report", {
								detail: accountPayload,
							}),
						);
					}
				}
				return;
			}

			const senderId = Number(
				((payload || {}).payload || {}).user_id || 0,
			);
			const partnerId = Number(
				((payload || {}).payload || {}).partner_id || 0,
			);

			if (senderId && senderId !== userId) {
				notifyAboutLatestMessage(senderId);
				return;
			}

			if (partnerId && partnerId !== userId) {
				notifyAboutLatestMessage(partnerId);
			}
		};

		socket.onclose = function () {
			socket = null;
			if (reconnectTimer) {
				window.clearTimeout(reconnectTimer);
			}
			reconnectTimer = window.setTimeout(connect, 2000);
		};

		socket.onerror = function () {
			if (socket) {
				socket.close();
			}
		};
	}

	connect();
});
