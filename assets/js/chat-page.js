App.register("chatPage", function () {
	const page = document.getElementById("chat-page");
	if (!page) return;

	const state = JSON.parse(page.dataset.chatState || "{}");
	const conversationsRoot = document.getElementById("chat-conversations");
	const sidebarTabsRoot = document.getElementById("chat-sidebar-tabs");
	const threadHeader = document.getElementById("chat-thread-header");
	const threadBody = document.getElementById("chat-thread-body");
	const contextMenu = document.getElementById("chat-context-menu");
	const contextMenuHost = contextMenu ? contextMenu.parentElement : null;
	const composeForm = document.getElementById("chat-compose-form");
	const composeStateRoot = document.getElementById("chat-compose-state");
	const userIdInput = document.getElementById("chat-user-id");
	const supportInput = document.getElementById("chat-support-flag");
	const listingIdInput = document.getElementById("chat-listing-id");
	const replyToInput = document.getElementById("chat-reply-to-message-id");
	const messageInput = document.getElementById("chat-message");
	const imageInput = document.getElementById("chat-media-input");
	const imagePreviewBlock = document.getElementById("chat-media-preview");
	const imagePreviewGrid = document.getElementById("chat-media-preview-grid");
	const attachButton = document.getElementById("chat-attach-button");
	const searchInput = document.getElementById("chat-search-input");
	const lightbox = document.getElementById("chat-lightbox");
	const lightboxImg = document.getElementById("chat-lightbox-img");
	const lightboxVideo = document.getElementById("chat-lightbox-video");
	const lightboxClose = document.getElementById("chat-lightbox-close");
	const submitButton = composeForm.querySelector('button[type="submit"]');
	const deleteConfirm = document.getElementById("chat-delete-confirm");
	const MAX_MEDIA_SIZE = 100 * 1024 * 1024;
	const MAX_MEDIA_COUNT = 5;

	let conversations = (state.conversations || []).map(normalizeConversation);
	let selectedUserId = Number(state.selectedUserId || 0);
	let selectedUser = state.selectedUser
		? normalizeUser(state.selectedUser)
		: null;
	let messages = (state.messages || []).map(normalizeMessage);
	let selectedListingId = state.selectedListingId || null;
	let stream = null;
	let ws = null;
	let lastMessageId = messages.length
		? Number(messages[messages.length - 1].id)
		: 0;
	let syncInFlight = false;
	let onlineUserIds = new Set((state.onlineUserIds || []).map(Number));
	let typingUsers = new Map();
	let replyTarget = null;
	let editTargetId = 0;
	let editTargetPreview = "";
	let contextMenuMessageId = 0;
	let deleteResolver = null;
	let conversationSearchQuery = "";
	let selectedMediaFiles = [];
	let typingNotifyTimer = null;
	let activeConversationTab = "primary";
	let selectedIsSupport = !!state.selectedIsSupport;
	let supportLockHeartbeatTimer = null;
	let isConversationSwitching = false;
	let pendingSyncAfterCurrent = false;

	function esc(v) {
		return String(v)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;")
			.replace(/'/g, "&#39;");
	}
	function isMobile() {
		return window.matchMedia("(max-width: 992px)").matches;
	}
	function pad(v) {
		return String(v).padStart(2, "0");
	}
	function toDate(v) {
		const d = new Date(String(v || "").replace(" ", "T"));
		return Number.isNaN(d.getTime()) ? null : d;
	}
	function fmtTime(v) {
		const d = toDate(v);
		return d ? pad(d.getHours()) + ":" + pad(d.getMinutes()) : "";
	}
	function dayKey(v) {
		const d = toDate(v);
		return d
			? [d.getFullYear(), pad(d.getMonth() + 1), pad(d.getDate())].join(
					"-",
				)
			: "";
	}
	function sameDay(a, b) {
		return (
			a.getFullYear() === b.getFullYear() &&
			a.getMonth() === b.getMonth() &&
			a.getDate() === b.getDate()
		);
	}
	function isOnline(id) {
		return onlineUserIds.has(Number(id));
	}
	function isTyping(id) {
		const expiresAt = typingUsers.get(Number(id) || 0) || 0;
		return expiresAt > Date.now();
	}
	function setTyping(userId) {
		const id = Number(userId || 0);
		if (!id || id === state.currentUserId) return;
		const ttl = Date.now() + 4000;
		typingUsers.set(id, ttl);
		window.setTimeout(function () {
			if ((typingUsers.get(id) || 0) <= Date.now())
				typingUsers.delete(id);
			renderHeader();
		}, 4200);
		renderHeader();
	}

	function normalizeUser(user) {
		return {
			id: Number(user.id || 0),
			first_name: user.first_name || "",
			last_name: user.last_name || "",
			full_name: user.full_name || "",
			role: user.role || "user",
			is_admin: !!user.is_admin,
			has_avatar: !!user.has_avatar,
			is_online: !!user.is_online,
			city: user.city || "",
			region_name: user.region_name || "",
		};
	}
	function normalizeConversation(c) {
		c.partner = normalizeUser(c.partner || {});
		c.latest_message = c.latest_message || {};
		c.unread_count = Number(c.unread_count || 0);
		c.is_support = !!c.is_support;
		c.target_user_id = Number(c.target_user_id || 0);
		c.support_lock = c.support_lock || null;
		return c;
	}
	function isCurrentUserAdmin() {
		return !!state.currentUserIsAdmin;
	}
	function isSupportConversation(conversation) {
		return !!(conversation || {}).is_support;
	}
	function isSelectedSupportChat() {
		return !!selectedIsSupport;
	}
	function shouldShowPresenceForUser(user, isSupportChat) {
		if (isSupportChat) return false;
		return !!user && Number(user.id || 0) > 0;
	}
	function getConversationTab(conversation) {
		if (isCurrentUserAdmin()) {
			return isSupportConversation(conversation) ? "support" : "primary";
		}
		return "primary";
	}
	function getSupportDisplayName() {
		return "Поддержка BelCouch";
	}
	function getConversationUserId(conversation) {
		if (!conversation) return 0;
		if (
			isSupportConversation(conversation) &&
			Number(conversation.target_user_id || 0)
		)
			return Number(conversation.target_user_id || 0);
		return Number(((conversation || {}).partner || {}).id || 0);
	}
	function getConversationKey(conversation) {
		return (
			(isSupportConversation(conversation) ? "support:" : "primary:") +
			String(getConversationUserId(conversation))
		);
	}
	function getSelectedConversationKey() {
		if (!selectedUserId) return "";
		return (
			(selectedIsSupport ? "support:" : "primary:") +
			String(selectedUserId)
		);
	}
	function isSupportConversationLockedForCurrentAdmin(conversation) {
		if (!isCurrentUserAdmin() || !isSupportConversation(conversation))
			return false;
		const lock = (conversation || {}).support_lock || null;
		return !!(lock && lock.is_locked && !lock.is_mine);
	}
	function getSupportLockLabel(conversation) {
		const lock = (conversation || {}).support_lock || null;
		if (!lock || !lock.is_locked) return "";
		return lock.is_mine ? "У вас" : "Занят";
	}
	function getConversationDisplayName(conversation) {
		if (!conversation || !conversation.partner) return "Пользователь";
		if (!isCurrentUserAdmin() && isSupportConversation(conversation)) {
			return getSupportDisplayName();
		}
		return (
			conversation.partner.full_name ||
			conversation.partner.first_name ||
			"Пользователь"
		);
	}
	function getSelectedUserDisplayName() {
		if (!selectedUser) return "Пользователь";
		if (!isCurrentUserAdmin() && isSelectedSupportChat()) {
			return getSupportDisplayName();
		}
		return (
			selectedUser.full_name || selectedUser.first_name || "Пользователь"
		);
	}
	function normalizeMessage(m) {
		m.id = Number(m.id || 0);
		m.sender_id = Number(m.sender_id || 0);
		m.receiver_id = Number(m.receiver_id || 0);
		m.is_outgoing = !!m.is_outgoing;
		m.is_read = !!m.is_read;
		m.is_deleted = !!m.is_deleted || !!m.deleted_at;
		m.image_path = m.image_path || null;
		m.attachments = Array.isArray(m.attachments)
			? m.attachments
					.map(function (attachment) {
						if (!attachment || !attachment.file_path) return null;
						return {
							file_path: attachment.file_path,
							file_type:
								attachment.file_type ||
								getAttachmentType(attachment.file_path),
						};
					})
					.filter(Boolean)
			: m.image_path
				? [
						{
							file_path: m.image_path,
							file_type: getAttachmentType(m.image_path),
						},
					]
				: [];
		return m;
	}
	function getAttachmentType(filePath) {
		return /\.(mp4|webm|mov|qt)$/i.test(String(filePath || ""))
			? "video"
			: "image";
	}
	function getMessageAttachments(message) {
		if (!message || message.is_deleted) return [];
		if (Array.isArray(message.attachments) && message.attachments.length) {
			return message.attachments;
		}
		return message.image_path
			? [
					{
						file_path: message.image_path,
						file_type: getAttachmentType(message.image_path),
					},
				]
			: [];
	}
	function getUserDisplayNameById(userId) {
		const normalizedUserId = Number(userId || 0);
		if (!normalizedUserId) return "Пользователь";
		if (selectedUser && Number(selectedUser.id) === normalizedUserId) {
			if (selectedIsSupport && !isCurrentUserAdmin()) {
				return getSupportDisplayName();
			}
			return (
				selectedUser.full_name ||
				selectedUser.first_name ||
				"Пользователь"
			);
		}
		const conversation = conversations.find(function (item) {
			return (
				Number(((item || {}).partner || {}).id || 0) ===
				normalizedUserId
			);
		});
		if (conversation && conversation.partner) {
			return (
				conversation.partner.full_name ||
				conversation.partner.first_name ||
				"Пользователь"
			);
		}
		return "Пользователь";
	}
	function notifyIncomingMessage(payload) {
		const senderId = Number((payload || {}).user_id || 0);
		const isSupport = !!(payload || {}).is_support;
		if (!senderId || senderId === Number(state.currentUserId || 0)) return;
		window.App.notify(
			"Новое сообщение от " + getUserDisplayNameById(senderId),
			"success",
			{
				clickable: true,
				duration: 5000,
				onClick: function () {
					openConversation(senderId, isSupport);
				},
			},
		);
	}
	function getConversationPreviewText(conversation) {
		const latest = (conversation || {}).latest_message || {};
		if (latest.is_deleted) return "Сообщение удалено";
		if ((latest.message || "").trim()) return latest.message.trim();
		return getMessageAttachments(latest).length
			? "Вложение"
			: "Новое сообщение";
	}
	function applyConversationsSnapshot(nextConversations, options) {
		options = options || {};
		const shouldNotify = !!options.notify;
		const previousMap = new Map();

		conversations.forEach(function (conversation) {
			const conversationKey = getConversationKey(conversation);
			if (!conversationKey) return;
			previousMap.set(conversationKey, {
				unread_count: Number(conversation.unread_count || 0),
				latest_message_id: Number(
					((conversation || {}).latest_message || {}).id || 0,
				),
			});
		});

		const normalizedConversations = (nextConversations || []).map(
			normalizeConversation,
		);

		if (shouldNotify) {
			normalizedConversations.forEach(function (conversation) {
				const partnerId = getConversationUserId(conversation);
				const conversationKey = getConversationKey(conversation);
				if (!partnerId || !conversationKey) return;
				if (
					partnerId === selectedUserId &&
					isSupportConversation(conversation) === selectedIsSupport
				)
					return;

				const previous = previousMap.get(conversationKey) || {
					unread_count: 0,
					latest_message_id: 0,
				};
				const nextUnreadCount = Number(conversation.unread_count || 0);
				const nextLatestMessageId = Number(
					((conversation || {}).latest_message || {}).id || 0,
				);

				if (
					nextUnreadCount > previous.unread_count &&
					nextLatestMessageId > previous.latest_message_id
				) {
					window.App.notify(
						"Новое сообщение от " +
							getUserDisplayNameById(partnerId) +
							": " +
							getConversationPreviewText(conversation),
						"success",
						{
							clickable: true,
							duration: 5000,
							onClick: function () {
								openConversation(
									partnerId,
									isSupportConversation(conversation),
								);
							},
						},
					);
				}
			});
		}

		conversations = normalizedConversations;
	}
	function syncSelectedFilesToInput() {
		if (!imageInput) return;
		const dataTransfer = new DataTransfer();
		selectedMediaFiles.forEach(function (fileItem) {
			dataTransfer.items.add(fileItem.file);
		});
		imageInput.files = dataTransfer.files;
	}
	function getSelectedMediaSize() {
		return selectedMediaFiles.reduce(function (sum, fileItem) {
			return sum + Number((fileItem.file || {}).size || 0);
		}, 0);
	}
	function getMediaPreviewLayoutClass(count) {
		return (
			"is-count-" +
			Math.min(Math.max(Number(count || 0), 1), MAX_MEDIA_COUNT)
		);
	}
	function renderMediaPreview() {
		if (!imagePreviewBlock || !imagePreviewGrid) return;
		if (!selectedMediaFiles.length) {
			imagePreviewBlock.hidden = true;
			imagePreviewGrid.innerHTML = "";
			return;
		}

		imagePreviewBlock.hidden = false;
		imagePreviewGrid.className =
			"chat-media-preview-grid " +
			getMediaPreviewLayoutClass(selectedMediaFiles.length);
		imagePreviewGrid.innerHTML = selectedMediaFiles
			.map(function (item, index) {
				const mediaMarkup =
					item.type === "video"
						? '<video src="' +
							esc(item.url) +
							'" muted playsinline preload="metadata"></video><div class="chat-message-play-btn">▶</div>'
						: '<img src="' + esc(item.url) + '" alt="Вложение">';
				return (
					'<div class="chat-media-preview-item">' +
					mediaMarkup +
					'<button type="button" class="chat-media-preview-remove" data-action="remove-preview-media" data-index="' +
					index +
					'">×</button></div>'
				);
			})
			.join("");
	}
	function resetMediaPreview() {
		selectedMediaFiles.forEach(function (item) {
			if (item && item.url) URL.revokeObjectURL(item.url);
		});
		selectedMediaFiles = [];
		if (imageInput) imageInput.value = "";
		if (imagePreviewBlock) imagePreviewBlock.hidden = true;
		if (imagePreviewGrid) imagePreviewGrid.innerHTML = "";
	}
	function removeSelectedMedia(index) {
		const normalizedIndex = Number(index);
		if (!Number.isInteger(normalizedIndex) || normalizedIndex < 0) return;
		const removed = selectedMediaFiles.splice(normalizedIndex, 1)[0];
		if (removed && removed.url) URL.revokeObjectURL(removed.url);
		syncSelectedFilesToInput();
		renderMediaPreview();
	}
	function appendSelectedMedia(fileList) {
		const nextFiles = Array.from(fileList || []);
		if (!nextFiles.length) return;
		if (selectedMediaFiles.length + nextFiles.length > MAX_MEDIA_COUNT) {
			window.App.notify("Можно прикрепить не более 5 файлов", "error");
			imageInput.value = "";
			return;
		}

		const nextTotalSize =
			getSelectedMediaSize() +
			nextFiles.reduce(function (sum, file) {
				return sum + Number(file.size || 0);
			}, 0);
		if (nextTotalSize > MAX_MEDIA_SIZE) {
			window.App.notify(
				"Максимальный общий размер вложений — 100 МБ",
				"error",
			);
			imageInput.value = "";
			return;
		}

		nextFiles.forEach(function (file) {
			selectedMediaFiles.push({
				file: file,
				type: file.type.startsWith("video/") ? "video" : "image",
				url: URL.createObjectURL(file),
			});
		});
		syncSelectedFilesToInput();
		renderMediaPreview();
	}
	function getMessageMediaLayoutClass(count) {
		return (
			"is-count-" +
			Math.min(Math.max(Number(count || 0), 1), MAX_MEDIA_COUNT)
		);
	}
	function renderMessageAttachments(message) {
		const attachments = getMessageAttachments(message);
		if (!attachments.length) return "";
		return (
			'<div class="chat-message-media-grid ' +
			getMessageMediaLayoutClass(attachments.length) +
			'">' +
			attachments
				.map(function (attachment, index) {
					const filePath = attachment.file_path;
					const fileType =
						attachment.file_type || getAttachmentType(filePath);
					const mediaMarkup =
						fileType === "video"
							? '<video src="' +
								esc(filePath) +
								'" preload="metadata"></video><div class="chat-message-play-btn">▶</div>'
							: '<img src="' +
								esc(filePath) +
								'" alt="Вложение">';
					return (
						'<button type="button" class="chat-message-media-item' +
						(fileType === "video" ? " is-video" : "") +
						'" data-action="open-lightbox" data-src="' +
						esc(filePath) +
						'" data-type="' +
						esc(fileType) +
						'" data-index="' +
						index +
						'">' +
						mediaMarkup +
						"</button>"
					);
				})
				.join("") +
			"</div>"
		);
	}

	function getFilteredConversations() {
		const query = conversationSearchQuery.trim().toLowerCase();
		let filtered = conversations.filter(function (conversation) {
			return getConversationTab(conversation) === activeConversationTab;
		});
		if (!query) {
			return sortConversationsForDisplay(filtered);
		}
		filtered = filtered.filter(function (conversation) {
			const partner = conversation.partner || {};
			const latest = conversation.latest_message || {};
			const haystack = [
				getConversationDisplayName(conversation),
				partner.full_name,
				partner.first_name,
				partner.last_name,
				partner.city,
				partner.region_name,
				latest.message,
			]
				.filter(Boolean)
				.join(" ")
				.toLowerCase();
			return haystack.includes(query);
		});
		return sortConversationsForDisplay(filtered);
	}
	function sortConversationsForDisplay(list) {
		const nextList = Array.isArray(list) ? list.slice() : [];
		if (!isCurrentUserAdmin()) {
			nextList.sort(function (a, b) {
				const aSupport = isSupportConversation(a) ? 1 : 0;
				const bSupport = isSupportConversation(b) ? 1 : 0;
				if (aSupport !== bSupport) {
					return bSupport - aSupport;
				}
				return (
					Number(((b || {}).latest_message || {}).id || 0) -
					Number(((a || {}).latest_message || {}).id || 0)
				);
			});
			return nextList;
		}
		return nextList;
	}
	function renderSidebarTabs() {
		if (!sidebarTabsRoot) return;
		if (!isCurrentUserAdmin()) {
			sidebarTabsRoot.innerHTML = "";
			sidebarTabsRoot.hidden = true;
			return;
		}
		sidebarTabsRoot.hidden = false;
		const primaryCount = conversations.filter(function (conversation) {
			return getConversationTab(conversation) === "primary";
		}).length;
		const supportCount = conversations.filter(function (conversation) {
			return getConversationTab(conversation) === "support";
		}).length;
		sidebarTabsRoot.innerHTML =
			'<button type="button" class="chat-sidebar-tab' +
			(activeConversationTab === "primary" ? " is-active" : "") +
			'" data-tab="primary">Основные' +
			(primaryCount
				? '<span class="chat-sidebar-tab-badge">' +
					primaryCount +
					"</span>"
				: "") +
			"</button>" +
			'<button type="button" class="chat-sidebar-tab' +
			(activeConversationTab === "support" ? " is-active" : "") +
			'" data-tab="support">Поддержка' +
			(supportCount
				? '<span class="chat-sidebar-tab-badge">' +
					supportCount +
					"</span>"
				: "") +
			"</button>";
	}

	function fmtDay(v) {
		const d = toDate(v);
		if (!d) return "";
		const now = new Date();
		const t = new Date(now.getFullYear(), now.getMonth(), now.getDate());
		const y = new Date(t);
		y.setDate(t.getDate() - 1);
		const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
		if (sameDay(x, t)) return "Сегодня";
		if (sameDay(x, y)) return "Вчера";
		const o = { day: "numeric", month: "long" };
		if (d.getFullYear() !== now.getFullYear()) o.year = "numeric";
		return d.toLocaleDateString("ru-RU", o);
	}

	function avatar(user) {
		const initials =
			(
				(user.first_name || "").slice(0, 1) +
				(user.last_name || "").slice(0, 1)
			).toUpperCase() || "?";
		if (user.has_avatar)
			return (
				'<img src="' +
				API_URL +
				"/users/get_avatar.php?id=" +
				user.id +
				'" class="chat-avatar-image" alt="' +
				esc(user.full_name) +
				'">'
			);
		return (
			'<div class="chat-avatar-placeholder">' + esc(initials) + "</div>"
		);
	}

	function renderConversations() {
		const filteredConversations = getFilteredConversations();
		if (!filteredConversations.length) {
			conversationsRoot.innerHTML = conversationSearchQuery.trim()
				? '<div class="chat-empty-list">Ничего не найдено.</div>'
				: activeConversationTab === "support" && isCurrentUserAdmin()
					? '<div class="chat-empty-list">Пока нет чатов поддержки.</div>'
					: '<div class="chat-empty-list">Пока нет диалогов.</div>';
			return;
		}
		conversationsRoot.innerHTML = filteredConversations
			.map(function (c) {
				const u = c.partner;
				const conversationUserId = getConversationUserId(c);
				const showPresence = shouldShowPresenceForUser(
					u,
					isSupportConversation(c),
				);
				const isLocked = isSupportConversationLockedForCurrentAdmin(c);
				const active =
					conversationUserId === selectedUserId &&
					isSupportConversation(c) === selectedIsSupport;
				const latest = c.latest_message || {};
				const preview = latest.is_deleted
					? "Сообщение удалено"
					: latest.message ||
						(getMessageAttachments(latest).length
							? "Вложение"
							: "Новый диалог");
				return (
					'<a class="chat-conversation' +
					(active ? " is-active" : "") +
					(isSupportConversation(c) ? " is-support" : "") +
					(isLocked ? " is-locked" : "") +
					(!isCurrentUserAdmin() && isSupportConversation(c)
						? " is-pinned"
						: "") +
					'" href="/chat?user_id=' +
					conversationUserId +
					(isSupportConversation(c) ? "&support=1" : "") +
					'" data-user-id="' +
					conversationUserId +
					'" data-support="' +
					(isSupportConversation(c) ? "1" : "0") +
					'" data-locked="' +
					(isLocked ? "1" : "0") +
					'" data-tab="' +
					getConversationTab(c) +
					'">' +
					'<div class="chat-conversation-avatar">' +
					avatar(u) +
					(showPresence
						? '<span class="chat-online-dot' +
							(isOnline(u.id) ? " is-online" : "") +
							'"></span>'
						: "") +
					"</div>" +
					'<div class="chat-conversation-content"><div class="chat-conversation-top"><span class="chat-conversation-name">' +
					esc(getConversationDisplayName(c)) +
					'</span><span class="chat-conversation-time">' +
					esc(fmtTime(latest.created_at)) +
					"</span></div>" +
					'<div class="chat-conversation-bottom"><span class="chat-conversation-preview' +
					(latest.is_deleted ? " is-muted" : "") +
					'">' +
					esc(preview) +
					"</span>" +
					(getSupportLockLabel(c)
						? '<span class="chat-conversation-lock">' +
							esc(getSupportLockLabel(c)) +
							"</span>"
						: "") +
					(c.unread_count
						? '<span class="chat-conversation-badge">' +
							c.unread_count +
							"</span>"
						: "") +
					"</div></div></a>"
				);
			})
			.join("");
	}

	function renderHeader() {
		if (!selectedUser) {
			threadHeader.innerHTML =
				'<div class="chat-thread-empty-state"><h2>Выберите диалог</h2></div>';
			return;
		}
		const meta = [selectedUser.city, selectedUser.region_name]
			.filter(Boolean)
			.join(", ");
		const showPresence = shouldShowPresenceForUser(
			selectedUser,
			isSelectedSupportChat(),
		);
		const statusText = isSelectedSupportChat()
			? ""
			: isTyping(selectedUser.id)
				? "Печатает…"
				: isOnline(selectedUser.id)
					? "В сети"
					: "Не в сети";
		threadHeader.innerHTML =
			'<button type="button" data-action="open-sidebar" class="chat-mobile-list-button" aria-label="Назад к списку чатов">' +
			'<svg class="chat-mobile-list-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>' +
			"</button>" +
			'<div class="chat-thread-person"><div class="chat-thread-avatar">' +
			avatar(selectedUser) +
			(showPresence
				? '<span class="chat-online-dot' +
					(isOnline(selectedUser.id) ? " is-online" : "") +
					'"></span>'
				: "") +
			"</div>" +
			'<div class="chat-thread-person-info"><h2 class="chat-thread-name">' +
			esc(getSelectedUserDisplayName()) +
			'</h2><div class="chat-thread-status-row">' +
			(statusText
				? '<span class="chat-thread-status' +
					(isTyping(selectedUser.id)
						? " is-typing"
						: isOnline(selectedUser.id)
							? " is-online"
							: "") +
					'">' +
					statusText +
					"</span>"
				: "") +
			(meta
				? '<span class="chat-thread-meta">' + esc(meta) + "</span>"
				: "") +
			"</div></div></div>";
	}

	function renderMessages(scrollBottom) {
		const visible = messages.filter(function (m) {
			return !m.is_deleted;
		});
		if (!selectedUser) {
			threadBody.innerHTML =
				'<div class="chat-thread-placeholder"><p>Выберите диалог.</p></div>';
			return;
		}
		if (!visible.length) {
			threadBody.innerHTML =
				'<div class="chat-thread-placeholder"><p>Диалог еще не создан.</p></div>';
			return;
		}
		let curDay = "";
		let html = "";
		for (let i = 0; i < visible.length; i++) {
			const m = visible[i];
			const p = i > 0 ? visible[i - 1] : null;
			const n = i < visible.length - 1 ? visible[i + 1] : null;
			const dk = dayKey(m.created_at);
			if (dk !== curDay) {
				curDay = dk;
				html +=
					'<div class="chat-date-divider"><span>' +
					esc(fmtDay(m.created_at)) +
					"</span></div>";
			}
			if (
				!p ||
				p.sender_id !== m.sender_id ||
				dayKey(p.created_at) !== dk
			)
				html +=
					'<div class="chat-message-group' +
					(m.is_outgoing ? " is-outgoing" : "") +
					'">';
			const reply = m.reply_preview
				? '<button type="button" class="chat-message-reply" data-action="jump" data-message-id="' +
					Number(m.reply_preview.id || 0) +
					'"><span class="chat-message-reply-author">' +
					esc(
						m.reply_preview.is_outgoing
							? "Вы"
							: m.reply_preview.sender_name || "Сообщение",
					) +
					'</span><span class="chat-message-reply-text">' +
					esc(
						m.reply_preview.is_deleted
							? "Сообщение удалено"
							: m.reply_preview.message ||
									((m.reply_preview.attachments || [])
										.length || m.reply_preview.image_path
										? "Вложение"
										: ""),
					) +
					"</span></button>"
				: "";
			const mediaHtml = renderMessageAttachments(m);
			html +=
				'<div class="chat-message' +
				(m.is_outgoing ? " is-outgoing" : "") +
				'" data-message-id="' +
				m.id +
				'"><div class="chat-message-bubble">' +
				reply +
				mediaHtml +
				'<div class="chat-message-text">' +
				esc(m.message || "").replace(/\n/g, "<br>") +
				'</div><div class="chat-message-meta"><span class="chat-message-time">' +
				esc(fmtTime(m.created_at)) +
				"</span>" +
				(m.edited_at
					? '<span class="chat-message-edited">изменено</span>'
					: "") +
				(m.is_outgoing
					? '<span class="chat-message-read-status' +
						(m.is_read ? " is-read" : "") +
						'">' +
						(m.is_read
							? '<svg viewBox="0 0 16 11" width="16" height="11" fill="currentColor"><path d="M11.07 0.66L4.43 7.3 2.93 5.8 1.51 7.22 4.43 10.14 12.49 2.08 11.07 0.66zM14.49 2.08L6.43 10.14 5.93 9.64 4.51 11.06 6.43 12.98 15.91 3.5 14.49 2.08z"/></svg>'
							: '<svg viewBox="0 0 16 11" width="16" height="11" fill="currentColor"><path d="M11.07 0.66L4.43 7.3 2.93 5.8 1.51 7.22 4.43 10.14 12.49 2.08 11.07 0.66z"/></svg>') +
						"</span>"
					: "") +
				"</div></div></div>";
			if (
				!n ||
				n.sender_id !== m.sender_id ||
				dayKey(n.created_at) !== dk
			)
				html += "</div>";
		}
		threadBody.innerHTML = html;
		if (scrollBottom) threadBody.scrollTop = threadBody.scrollHeight;
	}

	function scrollToMessage(messageId) {
		const target = threadBody.querySelector(
			'.chat-message[data-message-id="' + Number(messageId || 0) + '"]',
		);
		if (!target) {
			return;
		}
		target.scrollIntoView({ behavior: "smooth", block: "center" });
		target.classList.add("is-highlighted");
		window.setTimeout(function () {
			target.classList.remove("is-highlighted");
		}, 1600);
	}

	function setReply(messageId) {
		const message = messages.find(function (item) {
			return Number(item.id) === Number(messageId || 0);
		});
		if (!message) {
			return;
		}
		replyTarget = message;
		editTargetId = 0;
		renderComposeState();
		messageInput.focus();
	}

	function setEdit(messageId) {
		const message = messages.find(function (item) {
			return Number(item.id) === Number(messageId || 0);
		});
		if (!message || !message.can_edit) {
			return;
		}
		editTargetId = Number(message.id);
		replyTarget = null;
		messageInput.value = message.message || "";
		messageInput.style.height = "auto";
		messageInput.style.height =
			Math.min(messageInput.scrollHeight, 160) + "px";
		const text = (message.message || "").trim();
		if (text) {
			editTargetPreview = text;
		} else {
			const attachments = getMessageAttachments(message);
			if (attachments.length > 0) {
				editTargetPreview =
					attachments.length === 1
						? "Вложение"
						: "Вложение: " + attachments.length;
			} else {
				editTargetPreview = "Без содержимого";
			}
		}
		renderComposeState();
		messageInput.focus();
	}

	function renderComposeState() {
		const chips = [];
		if (replyTarget)
			chips.push(
				'<div class="chat-compose-chip"><div class="chat-compose-chip-content"><span class="chat-compose-chip-label">Ответ</span><span class="chat-compose-chip-text">' +
					esc(
						replyTarget.is_deleted
							? "Сообщение удалено"
							: replyTarget.message ||
									(getMessageAttachments(replyTarget).length
										? "Вложение"
										: ""),
					) +
					'</span></div><button type="button" class="chat-compose-chip-close" data-action="cancel-reply">×</button></div>',
			);
		if (editTargetId > 0)
			chips.push(
				'<div class="chat-compose-chip is-editing"><div class="chat-compose-chip-content"><span class="chat-compose-chip-label">Редактирование</span><span class="chat-compose-chip-text">' +
					esc(editTargetPreview || "") +
					'</span></div><button type="button" class="chat-compose-chip-close" data-action="cancel-edit"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button></div>',
			);
		composeStateRoot.innerHTML = chips.join("");
		replyToInput.value = replyTarget ? String(replyTarget.id) : "";
		submitButton.textContent = editTargetId > 0 ? "Сохранить" : "Отправить";
	}

	function syncCompose() {
		const en = !!(selectedUser && selectedUserId > 0);
		messageInput.disabled = !en;
		submitButton.disabled = !en;
		if (attachButton) attachButton.disabled = !en;
		userIdInput.value = en ? String(selectedUserId) : "";
		if (supportInput)
			supportInput.value = en && selectedIsSupport ? "1" : "";
		listingIdInput.value = selectedListingId
			? String(selectedListingId)
			: "";
		renderComposeState();
	}
	function rerender(scrollBottom) {
		renderSidebarTabs();
		renderConversations();
		renderHeader();
		renderMessages(scrollBottom);
		syncCompose();
	}
	function rerenderWithoutMessages() {
		renderSidebarTabs();
		renderConversations();
		renderHeader();
		syncCompose();
	}
	function upsert(msg, scrollBottom) {
		const m = normalizeMessage(msg);
		const i = messages.findIndex(function (x) {
			return Number(x.id) === Number(m.id);
		});
		if (i < 0) messages.push(m);
		else messages[i] = Object.assign({}, messages[i], m);
		messages.sort(function (a, b) {
			return Number(a.id) - Number(b.id);
		});
		lastMessageId = messages.length
			? Number(messages[messages.length - 1].id)
			: 0;
		renderMessages(scrollBottom);
	}

	function closeContextMenu() {
		if (!contextMenu) return;
		contextMenu.hidden = true;
		contextMenu.innerHTML = "";
		contextMenu.classList.remove("is-outgoing");
		if (contextMenuHost && contextMenu.parentElement !== contextMenuHost)
			contextMenuHost.appendChild(contextMenu);
		contextMenuMessageId = 0;
	}
	function openLightbox(src, type) {
		if (!lightbox) return;
		if (type === "video") {
			lightboxImg.hidden = true;
			lightboxImg.src = "";
			lightboxVideo.src = src;
			lightboxVideo.hidden = false;
		} else {
			lightboxVideo.pause();
			lightboxVideo.src = "";
			lightboxVideo.hidden = true;
			lightboxImg.src = src;
			lightboxImg.hidden = false;
		}
		lightbox.hidden = false;
	}
	function closeLightbox() {
		if (!lightbox) return;
		lightbox.hidden = true;
		if (lightboxImg) {
			lightboxImg.src = "";
			lightboxImg.hidden = true;
		}
		if (lightboxVideo) {
			lightboxVideo.pause();
			lightboxVideo.src = "";
			lightboxVideo.hidden = true;
		}
	}
	function openContextMenu(messageId, node) {
		const m = messages.find(function (x) {
			return Number(x.id) === Number(messageId);
		});
		if (!m || m.is_deleted) return;
		const items = [
			'<button type="button" class="chat-context-menu-item" data-action="reply" data-message-id="' +
				m.id +
				'"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M10 9V5l-7 7 7 7v-4.1c5 0 8.5 1.6 11 5.1-1-5-4-10-11-11z"/></svg>Ответить</button>',
		];
		if (m.reply_preview)
			items.push(
				'<button type="button" class="chat-context-menu-item" data-action="jump" data-message-id="' +
					Number(m.reply_preview.id || 0) +
					'"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg>Перейти к ответу</button>',
			);
		if (m.can_edit)
			items.push(
				'<button type="button" class="chat-context-menu-item" data-action="edit" data-message-id="' +
					m.id +
					'"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>Редактировать</button>',
			);
		if (m.can_delete)
			items.push(
				'<button type="button" class="chat-context-menu-item is-danger" data-action="delete" data-message-id="' +
					m.id +
					'"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>Удалить</button>',
			);
		contextMenu.innerHTML = items.join("");
		contextMenu.hidden = false;
		contextMenuMessageId = m.id;
		contextMenu.classList.toggle(
			"is-outgoing",
			node.classList.contains("is-outgoing"),
		);
		contextMenu.classList.remove("is-up");
		node.appendChild(contextMenu);

		const menuRect = contextMenu.getBoundingClientRect();
		const containerRect = threadBody.getBoundingClientRect();
		if (menuRect.bottom > containerRect.bottom) {
			contextMenu.classList.add("is-up");
		}
	}

	function openDeleteConfirm(messageId) {
		pendingDeleteMessageId = Number(messageId || 0);
		deleteConfirm.hidden = false;
		return new Promise(function (r) {
			deleteResolver = r;
		});
	}
	function closeDeleteConfirm(ok) {
		deleteConfirm.hidden = true;
		if (typeof deleteResolver === "function") deleteResolver(ok);
		deleteResolver = null;
		if (!ok) pendingDeleteMessageId = 0;
	}

	function applyRead(ids) {
		const s = new Set((ids || []).map(Number));
		if (!s.size) return;
		messages = messages.map(function (m) {
			return s.has(Number(m.id))
				? Object.assign({}, m, { is_read: true })
				: m;
		});
		renderMessages(false);
	}
	function refreshConversations() {
		return window.App.api
			.fetchJson(state.apiBase + "/conversations.php")
			.then(function (r) {
				if (r && r.success) {
					applyConversationsSnapshot(r.conversations || [], {
						notify: false,
					});
					syncSelectedConversationFromList();
					rerender(false);
				}
			});
	}
	function syncSelectedConversationFromList() {
		if (!selectedUserId) return;
		const currentConversation = conversations.find(function (conversation) {
			return (
				getConversationUserId(conversation) === selectedUserId &&
				isSupportConversation(conversation) === selectedIsSupport
			);
		});
		if (!currentConversation) return;
		if (isSupportConversationLockedForCurrentAdmin(currentConversation)) {
			window.App.notify("Чат уже занят другим администратором", "error");
			selectedUserId = 0;
			selectedUser = null;
			selectedIsSupport = false;
			messages = [];
			page.classList.remove("is-chat-open");
			if (isMobile()) page.classList.add("is-sidebar-open");
			lastMessageId = 0;
			stopSupportLockHeartbeat();
			const p = new URLSearchParams(window.location.search);
			p.delete("user_id");
			p.delete("support");
			window.history.replaceState(
				{},
				"",
				"/chat" + (p.toString() ? "?" + p.toString() : ""),
			);
		}
	}
	function acquireSupportLock(userId) {
		const fd = new FormData();
		fd.append("user_id", String(userId));
		fd.append("support", "1");
		return window.App.api.postForm(state.apiBase + "/lock.php", fd);
	}
	function releaseSupportLock(userId) {
		const fd = new FormData();
		fd.append("user_id", String(userId));
		fd.append("support", "1");
		return window.App.api.postForm(state.apiBase + "/unlock.php", fd);
	}
	function stopSupportLockHeartbeat() {
		if (supportLockHeartbeatTimer) {
			window.clearInterval(supportLockHeartbeatTimer);
			supportLockHeartbeatTimer = null;
		}
	}
	function startSupportLockHeartbeat() {
		stopSupportLockHeartbeat();
		if (!isCurrentUserAdmin() || !selectedIsSupport || !selectedUserId)
			return;
		supportLockHeartbeatTimer = window.setInterval(function () {
			acquireSupportLock(selectedUserId).then(function (r) {
				if (!(r && r.success && r.acquired)) {
					stopSupportLockHeartbeat();
					syncFromServer();
				}
			});
		}, 10000);
	}
	function releaseCurrentSupportLock() {
		stopSupportLockHeartbeat();
		if (!isCurrentUserAdmin() || !selectedIsSupport || !selectedUserId) {
			return Promise.resolve();
		}
		return releaseSupportLock(selectedUserId).finally(function () {
			refreshConversations();
		});
	}
	function syncFromServer() {
		if (syncInFlight) {
			pendingSyncAfterCurrent = true;
			return Promise.resolve();
		}
		syncInFlight = true;
		const shouldReplaceMessages = isConversationSwitching;
		const requestConversationKey = getSelectedConversationKey();
		const req = [
			window.App.api.fetchJson(state.apiBase + "/conversations.php"),
		];
		if (selectedUserId > 0)
			req.push(
				window.App.api.fetchJson(
					state.apiBase +
						"/messages.php?user_id=" +
						selectedUserId +
						"&after_id=" +
						lastMessageId +
						(selectedIsSupport ? "&support=1" : ""),
				),
			);
		return Promise.all(req)
			.then(function (r) {
				if (r[0] && r[0].success)
					applyConversationsSnapshot(r[0].conversations || [], {
						notify: true,
					});
				syncSelectedConversationFromList();
				if (
					r[1] &&
					r[1].success &&
					requestConversationKey &&
					requestConversationKey === getSelectedConversationKey()
				) {
					if (shouldReplaceMessages) {
						messages = (r[1].messages || []).map(normalizeMessage);
						lastMessageId = r[1].last_id
							? Number(r[1].last_id)
							: messages.length
								? Number(messages[messages.length - 1].id)
								: 0;
					} else {
						(r[1].messages || []).forEach(function (m) {
							upsert(m, true);
						});
					}
				}
				rerender(false);
			})
			.finally(function () {
				isConversationSwitching = false;
				syncInFlight = false;
				if (pendingSyncAfterCurrent) {
					pendingSyncAfterCurrent = false;
					syncFromServer();
				}
			});
	}

	function connectStream() {
		if (stream) stream.close();
		const p = new URLSearchParams();
		if (selectedUserId > 0) {
			p.set("user_id", String(selectedUserId));
			p.set("last_message_id", String(lastMessageId));
			if (selectedIsSupport) p.set("support", "1");
		}
		stream = new EventSource(state.apiBase + "/stream.php?" + p.toString());
		stream.addEventListener("messages", function (e) {
			const x = JSON.parse(e.data || "{}");
			(x.messages || []).forEach(function (m) {
				upsert(m, true);
			});
		});
		stream.addEventListener("conversations", function (e) {
			const x = JSON.parse(e.data || "{}");
			if (Array.isArray(x.conversations)) {
				applyConversationsSnapshot(x.conversations, { notify: true });
				renderConversations();
			}
		});
		stream.onerror = function () {
			if (stream) stream.close();
			window.setTimeout(connectStream, 1500);
		};
	}

	function connectWebSocket() {
		const c = state.ws || {};
		if (!c.url || !c.user_id || !c.ts || !c.sig) return connectStream();
		const q = new URLSearchParams({
			user_id: String(c.user_id),
			ts: String(c.ts),
			sig: String(c.sig),
		});
		ws = new WebSocket(c.url + "?" + q.toString());
		ws.onmessage = function (e) {
			const p = JSON.parse(e.data || "{}");
			if (p.event === "chat:update") return void syncFromServer();
			if (p.event === "chat:message_created") {
				return void syncFromServer();
			}
			if (p.event === "chat:support_lock_updated") {
				return void syncFromServer();
			}
			if (p.event === "chat:typing") {
				setTyping((p.payload || {}).user_id);
				return;
			}
			if (
				(p.event === "chat:message_updated" ||
					p.event === "chat:message_deleted") &&
				p.payload &&
				p.payload.message
			) {
				upsert(p.payload.message, false);
				refreshConversations();
				return;
			}
			if (p.event === "chat:messages_read") {
				applyRead((p.payload || {}).message_ids || []);
				refreshConversations();
				return;
			}
			if (p.event === "presence:snapshot") {
				onlineUserIds = new Set(
					((p.payload || {}).user_ids || []).map(Number),
				);
				renderConversations();
				renderHeader();
				return;
			}
			if (p.event === "presence:update") {
				const id = Number((p.payload || {}).user_id || 0);
				if (!id) return;
				if ((p.payload || {}).is_online) onlineUserIds.add(id);
				else onlineUserIds.delete(id);
				renderConversations();
				renderHeader();
			}
		};
		ws.onerror = connectStream;
	}

	function sendTyping() {
		if (!selectedUserId) return;
		if (typingNotifyTimer) window.clearTimeout(typingNotifyTimer);
		typingNotifyTimer = window.setTimeout(function () {
			typingNotifyTimer = null;
		}, 2000);
		const fd = new FormData();
		fd.append("partner_id", String(selectedUserId));
		window.App.api.postForm(API_BASE_URL + "/chat/typing.php", fd);
	}

	function openConversation(userId, isSupportConversationFlag) {
		const id = Number(userId || 0);
		const nextIsSupport = !!isSupportConversationFlag;
		if (!id) return;
		if (id === selectedUserId && nextIsSupport === selectedIsSupport) {
			if (isMobile()) page.classList.remove("is-sidebar-open");
			return;
		}
		const c = conversations.find(function (x) {
			return (
				getConversationUserId(x) === id &&
				isSupportConversation(x) === nextIsSupport
			);
		});
		if (c && isSupportConversationLockedForCurrentAdmin(c)) {
			window.App.notify(
				"Этот чат уже занят другим администратором",
				"error",
			);
			return;
		}
		const proceed = function () {
			if (c) {
				activeConversationTab = getConversationTab(c);
				selectedIsSupport = isSupportConversation(c);
			} else {
				selectedIsSupport = nextIsSupport;
			}
			selectedUserId = id;
			selectedUser = c
				? normalizeUser(c.partner)
				: normalizeUser({ id: id, full_name: "Пользователь" });
			selectedListingId = null;
			lastMessageId = 0;
			isConversationSwitching = true;
			replyTarget = null;
			editTargetId = 0;
			resetMediaPreview();
			startSupportLockHeartbeat();
			page.classList.add("is-chat-open");
			if (isMobile()) page.classList.remove("is-sidebar-open");
			const p = new URLSearchParams(window.location.search);
			p.set("user_id", String(id));
			if (selectedIsSupport) p.set("support", "1");
			else p.delete("support");
			window.history.replaceState({}, "", "/chat?" + p.toString());
			rerenderWithoutMessages();
			syncFromServer();
		};
		if (isCurrentUserAdmin() && nextIsSupport) {
			releaseCurrentSupportLock().finally(function () {
				acquireSupportLock(id).then(function (r) {
					if (!(r && r.success && r.acquired)) {
						window.App.notify(
							"Этот чат уже занят другим администратором",
							"error",
						);
						refreshConversations();
						return;
					}
					proceed();
				});
			});
			return;
		}
		releaseCurrentSupportLock().finally(proceed);
	}

	if (selectedUserId > 0) {
		const initialConversation = conversations.find(function (conversation) {
			return (
				getConversationUserId(conversation) === selectedUserId &&
				isSupportConversation(conversation) === selectedIsSupport
			);
		});
		if (initialConversation) {
			activeConversationTab = getConversationTab(initialConversation);
			selectedIsSupport = isSupportConversation(initialConversation);
		}
	}

	function deleteMessage(id) {
		const fd = new FormData();
		fd.append("message_id", String(id));
		window.App.api
			.postForm(state.apiBase + "/delete.php", fd)
			.then(function (r) {
				if (r && r.success && r.message_item)
					upsert(r.message_item, false);
				refreshConversations();
			});
	}

	conversationsRoot.addEventListener("click", function (e) {
		const a = e.target.closest(".chat-conversation");
		if (!a) return;
		e.preventDefault();
		if (a.dataset.locked === "1") {
			window.App.notify(
				"Этот чат уже занят другим администратором",
				"error",
			);
			return;
		}
		openConversation(
			Number(a.dataset.userId || 0),
			a.dataset.support === "1",
		);
	});
	if (sidebarTabsRoot) {
		sidebarTabsRoot.addEventListener("click", function (e) {
			const button = e.target.closest("[data-tab]");
			if (!button) return;
			activeConversationTab =
				button.dataset.tab === "support" ? "support" : "primary";
			if (isMobile()) {
				page.classList.add("is-sidebar-open");
				page.classList.remove("is-chat-open");
			}
			renderSidebarTabs();
			renderConversations();
		});
	}

	if (attachButton && imageInput) {
		attachButton.addEventListener("click", function () {
			imageInput.click();
		});

		imageInput.addEventListener("change", function () {
			appendSelectedMedia(this.files);
			imageInput.value = "";
		});
	}
	if (imagePreviewGrid) {
		imagePreviewGrid.addEventListener("click", function (e) {
			const removeButton = e.target.closest(
				'[data-action="remove-preview-media"]',
			);
			if (!removeButton) return;
			removeSelectedMedia(removeButton.dataset.index);
		});
	}
	if (lightboxClose) {
		lightboxClose.addEventListener("click", function (e) {
			e.preventDefault();
			e.stopPropagation();
			closeLightbox();
		});
	}
	if (lightbox) {
		lightbox.addEventListener("click", function (e) {
			if (e.target === lightbox) closeLightbox();
		});
	}
	document.addEventListener("keydown", function (e) {
		if (e.key === "Escape" && lightbox && !lightbox.hidden) {
			closeLightbox();
		}
	});

	threadHeader.addEventListener("click", function (e) {
		if (e.target.closest('[data-action="open-sidebar"]'))
			page.classList.add("is-sidebar-open");
	});
	threadBody.addEventListener("contextmenu", function (e) {
		const m = e.target.closest(".chat-message");
		if (!m) return;
		e.preventDefault();
		openContextMenu(Number(m.dataset.messageId || 0), m);
	});
	threadBody.addEventListener("click", function (e) {
		if (!e.target.closest(".chat-context-menu")) closeContextMenu();
		const b = e.target.closest("[data-action]");
		if (!b && isMobile()) {
			const m = e.target.closest(".chat-message");
			if (m) {
				openContextMenu(Number(m.dataset.messageId || 0), m);
				return;
			}
		}
		if (!b) return;
		const action = b.dataset.action;
		const id = Number(b.dataset.messageId || 0);

		if (action === "open-lightbox") {
			openLightbox(b.dataset.src, b.dataset.type);
			return;
		}

		if (action === "jump") return scrollToMessage(id);
		if (action === "reply") return setReply(id);
		if (action === "edit") return setEdit(id);
		if (action === "delete")
			return openDeleteConfirm(id).then(function (ok) {
				if (ok) deleteMessage(id);
			});
	});
	contextMenu.addEventListener("click", function (e) {
		const b = e.target.closest("[data-action]");
		if (!b) return;
		const a = b.dataset.action;
		const id = Number(b.dataset.messageId || contextMenuMessageId || 0);
		closeContextMenu();
		if (a === "jump") scrollToMessage(id);
		if (a === "reply") setReply(contextMenuMessageId);
		if (a === "edit") setEdit(contextMenuMessageId);
		if (a === "delete")
			openDeleteConfirm(contextMenuMessageId).then(function (ok) {
				if (ok) deleteMessage(contextMenuMessageId);
			});
	});
	deleteConfirm.addEventListener("click", function (e) {
		if (e.target === deleteConfirm) return closeDeleteConfirm(false);
		const b = e.target.closest("[data-action]");
		if (!b) return;
		if (b.dataset.action === "confirm-delete")
			return closeDeleteConfirm(true);
		closeDeleteConfirm(false);
	});
	composeStateRoot.addEventListener("click", function (e) {
		const b = e.target.closest("[data-action]");
		if (!b) return;
		if (b.dataset.action === "cancel-reply") {
			replyTarget = null;
			renderComposeState();
		}
		if (b.dataset.action === "cancel-edit") {
			editTargetId = 0;
			messageInput.value = "";
			editTargetPreview = "";
			renderComposeState();
		}
	});

	composeForm.addEventListener("submit", function (e) {
		e.preventDefault();
		if (!selectedUserId) return;
		const text = messageInput.value.trim();
		const hasMedia = selectedMediaFiles.length > 0;
		if (!text && !hasMedia) return;
		const fd = new FormData();
		if (editTargetId > 0) {
			fd.append("message_id", String(editTargetId));
			fd.append("message", text);
			return window.App.api
				.postForm(state.apiBase + "/edit.php", fd)
				.then(function (r) {
					if (r && r.success && r.message_item) {
						upsert(r.message_item, false);
						editTargetId = 0;
						messageInput.value = "";
						messageInput.style.height = "auto";
						editTargetPreview = "";
						renderComposeState();
						refreshConversations();
					}
				});
		}
		fd.append("user_id", String(selectedUserId));
		if (selectedIsSupport) fd.append("support", "1");
		fd.append("message", text);
		if (selectedListingId && !selectedIsSupport)
			fd.append("listing_id", String(selectedListingId));
		if (replyTarget)
			fd.append("reply_to_message_id", String(replyTarget.id));
		selectedMediaFiles.forEach(function (fileItem) {
			fd.append("media[]", fileItem.file);
		});
		window.App.api
			.postForm(state.apiBase + "/send.php", fd)
			.then(function (r) {
				if (r && r.success && r.message_item) {
					upsert(r.message_item, true);
					messageInput.value = "";
					messageInput.style.height = "auto";
					resetMediaPreview();
					selectedListingId = null;
					replyTarget = null;
					renderComposeState();
					refreshConversations();
				}
			});
	});

	if (messageInput) {
		messageInput.addEventListener("input", function () {
			messageInput.style.height = "auto";
			messageInput.style.height =
				Math.min(messageInput.scrollHeight, 160) + "px";
			sendTyping();
		});
		messageInput.addEventListener("keydown", function (e) {
			if (e.key === "Enter" && !e.shiftKey) {
				e.preventDefault();
				composeForm.requestSubmit();
			}
		});
	}
	if (searchInput) {
		searchInput.addEventListener("input", function () {
			conversationSearchQuery = searchInput.value || "";
			renderConversations();
		});
	}
	document.addEventListener("click", function (e) {
		const insideMenu = !!e.target.closest(".chat-context-menu");
		const insideMessage = !!e.target.closest(".chat-message");
		if (!insideMenu && !insideMessage) closeContextMenu();

		if (e.target.closest('[data-action="close-sidebar"]')) {
			page.classList.remove("is-sidebar-open");
			return;
		}

		if (
			isMobile() &&
			!e.target.closest(".chat-sidebar") &&
			!e.target.closest('[data-action="open-sidebar"]')
		)
			page.classList.remove("is-sidebar-open");
	});
	window.addEventListener("resize", function () {
		closeContextMenu();
		if (!isMobile()) page.classList.remove("is-sidebar-open");
	});
	threadBody.addEventListener("scroll", closeContextMenu);
	document.addEventListener("visibilitychange", function () {
		if (document.visibilityState === "visible") syncFromServer();
	});
	window.addEventListener("beforeunload", function () {
		if (!isCurrentUserAdmin() || !selectedIsSupport || !selectedUserId)
			return;
		const fd = new FormData();
		fd.append("user_id", String(selectedUserId));
		fd.append("support", "1");
		if (navigator.sendBeacon) {
			navigator.sendBeacon(state.apiBase + "/unlock.php", fd);
		}
	});

	if (state.selectedSupportLockDenied) {
		window.App.notify("Этот чат уже занят другим администратором", "error");
	}

	rerender(true);
	startSupportLockHeartbeat();
	connectWebSocket();
});
