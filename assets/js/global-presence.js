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
