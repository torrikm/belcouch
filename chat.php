<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
	header('Location: /index.php#login-modal');
	exit;
}

$pageTitle = 'Чаты';
$additionalCss = ['assets/css/chat.css'];
$additionalJs = ['assets/js/chat-page.js'];

$currentUserId = (int) $_SESSION['user_id'];
$selectedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$selectedListingId = isset($_GET['listing_id']) ? (int) $_GET['listing_id'] : 0;
$selectedIsSupport = !empty($_GET['support']);
$selectedMessages = [];
$selectedMessagesMeta = [
	'mode' => 'latest',
	'loaded_count' => 0,
	'oldest_id' => 0,
	'newest_id' => 0,
	'has_older' => false,
	'has_newer' => false,
	'anchor_message_id' => null,
];
$readIds = [];
$selectedSupportLockDenied = null;

if ($selectedUserId === $currentUserId) {
	$selectedUserId = 0;
	$selectedListingId = 0;
}

$chatService = new ChatService();
$supportService = new SupportService();
$supportChatAdmin = $supportService->getSupportChatAdmin();
$supportAdminId = (int) ($supportChatAdmin['id'] ?? 0);
$currentUserIsAdmin = AdminAccess::isAdmin();
$selectedUser = $selectedUserId > 0 ? $chatService->getUserPreview($selectedUserId) : null;
$wsTs = time();

if ($selectedIsSupport && !$currentUserIsAdmin && $selectedUser !== null) {
	$selectedUser = [
		'id' => 0,
		'first_name' => 'Поддержка',
		'last_name' => 'BelCouch',
		'full_name' => 'Поддержка BelCouch',
		'role' => 'support',
		'is_admin' => false,
		'has_avatar' => false,
		'is_verify' => false,
		'is_online' => false,
		'city' => '',
		'region_name' => '',
	];
}

if ($selectedIsSupport && $currentUserIsAdmin && $selectedUserId > 0 && $selectedUser !== null) {
	$lockResult = $chatService->acquireSupportConversationLock($currentUserId, $selectedUserId);
	if (empty($lockResult['acquired'])) {
		$selectedSupportLockDenied = $lockResult['lock'] ?? null;
		$selectedUserId = 0;
		$selectedUser = null;
		$selectedIsSupport = false;
	}
}

if ($selectedUserId > 0 && $selectedUserId !== $currentUserId && $selectedUser !== null) {
	$readIds = $chatService->markConversationAsRead($currentUserId, $selectedUserId, $selectedIsSupport);
	$window = $chatService->getMessagesWindow($currentUserId, $selectedUserId, ['limit' => 50], $selectedIsSupport);
	$selectedMessages = $window['messages'] ?? [];
	$selectedMessagesMeta = $window['meta'] ?? $selectedMessagesMeta;

	if (!empty($readIds)) {
		$notifyUserIds = [$selectedUserId];
		if ($selectedIsSupport) {
			$db = new Database();
			$rows = $db->getAll("SELECT id FROM users WHERE role = 'admin'");
			$notifyUserIds = array_map(static function (array $row): int {
				return (int) ($row['id'] ?? 0);
			}, $rows);
			$notifyUserIds[] = $selectedUserId;
		}
		ChatRealtimeNotifier::notifyUsers(
			$notifyUserIds,
			[
				'user_id' => $currentUserId,
				'partner_id' => $selectedUserId,
				'is_support' => $selectedIsSupport,
				'message_ids' => $readIds,
			],
			'chat:messages_read'
		);
	}
}

$conversations = $chatService->getConversationList($currentUserId);
$initialOnlineIds = [];

foreach ($conversations as $conversation) {
	if (!empty($conversation['partner']['is_online'])) {
		$initialOnlineIds[] = (int) $conversation['partner']['id'];
	}
}

if ($selectedUser && !empty($selectedUser['is_online'])) {
	$initialOnlineIds[] = (int) $selectedUser['id'];
}

$initialOnlineIds = array_values(array_unique($initialOnlineIds));

$chatBootstrap = [
	'currentUserId' => $currentUserId,
	'currentUserIsAdmin' => $currentUserIsAdmin,
	'supportAdminId' => $supportAdminId > 0 ? $supportAdminId : null,
	'selectedUserId' => $selectedUserId,
	'selectedIsSupport' => $selectedIsSupport,
	'selectedSupportLockDenied' => $selectedSupportLockDenied,
	'selectedListingId' => $selectedListingId > 0 ? $selectedListingId : null,
	'selectedUser' => $selectedUser,
	'conversations' => $conversations,
	'messages' => $selectedMessages,
	'messageWindowMeta' => $selectedMessagesMeta,
	'onlineUserIds' => $initialOnlineIds,
	'apiBase' => API_URL . '/chat',
	'ws' => [
		'url' => defined('CHAT_WS_PUBLIC_URL') ? CHAT_WS_PUBLIC_URL : '',
		'user_id' => $currentUserId,
		'ts' => $wsTs,
		'sig' => hash_hmac(
			'sha256',
			$currentUserId . '|' . $wsTs,
			defined('CHAT_WS_SHARED_SECRET') ? CHAT_WS_SHARED_SECRET : 'chat_secret'
		),
	],
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
	<div class="chat-page <?php echo $selectedUserId === 0 ? 'is-sidebar-open' : 'is-chat-open'; ?>" id="chat-page" data-chat-state='<?php echo htmlspecialchars(json_encode($chatBootstrap, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'>
		<aside class="chat-sidebar" id="chat-sidebar">
			<div class="chat-sidebar-header">
				<div class="chat-sidebar-header-row">
					<input type="search" class="chat-search-input" id="chat-search-input" placeholder="Поиск по чатам">
					<button type="button" class="chat-sidebar-close" data-action="close-sidebar" aria-label="Закрыть список чатов">
						<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">
							<path fill="currentColor" d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z" />
						</svg>
					</button>
				</div>
			</div>
			<div class="chat-sidebar-tabs" id="chat-sidebar-tabs"></div>
			<div class="chat-conversations" id="chat-conversations"></div>
		</aside>

		<section class="chat-main" id="chat-main">
			<div class="chat-thread-header" id="chat-thread-header"></div>
			<div class="chat-thread-body" id="chat-thread-body"></div>
			<button type="button" class="chat-jump-bottom" id="chat-jump-bottom">Вниз</button>
			<div class="chat-context-menu" id="chat-context-menu" hidden></div>
			<form class="chat-compose" id="chat-compose-form">
				<input type="hidden" name="user_id" id="chat-user-id" value="<?php echo $selectedUserId > 0 ? $selectedUserId : ''; ?>">
				<input type="hidden" name="support" id="chat-support-flag" value="<?php echo $selectedIsSupport ? '1' : ''; ?>">
				<input type="hidden" name="listing_id" id="chat-listing-id" value="<?php echo $selectedListingId > 0 ? $selectedListingId : ''; ?>">
				<input type="hidden" name="reply_to_message_id" id="chat-reply-to-message-id" value="">
				<div class="chat-compose-state" id="chat-compose-state"></div>
				<div class="chat-media-preview" id="chat-media-preview" hidden>
					<div class="chat-media-preview-grid" id="chat-media-preview-grid"></div>
				</div>
				<div class="chat-compose-row">
					<button type="button" class="chat-attach-button" id="chat-attach-button" aria-label="Прикрепить файл">
						<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
							<path d="M16.5 6.5 9 14a3 3 0 1 0 4.24 4.24l8.13-8.13a5 5 0 0 0-7.07-7.07L5.46 11.87a7 7 0 0 0 9.9 9.9l6.01-6.01-1.41-1.41-6.01 6.01a5 5 0 0 1-7.07-7.07l8.84-8.84a3 3 0 1 1 4.24 4.24l-8.13 8.13a1 1 0 1 1-1.41-1.41l7.5-7.5z" />
						</svg>
					</button>
					<input type="file" id="chat-media-input" name="media[]" accept="image/*,video/mp4,video/webm,video/quicktime" multiple hidden>
					<textarea id="chat-message" name="message" class="chat-compose-input" rows="1" placeholder="Напишите сообщение" <?php echo $selectedUser ? '' : 'disabled'; ?>></textarea>
					<button type="submit" class="chat-send-button" <?php echo $selectedUser ? '' : 'disabled'; ?>></button>
				</div>
			</form>
		</section>
	</div>
</div>

<div class="chat-delete-confirm" id="chat-delete-confirm" hidden>
	<div class="chat-delete-confirm-panel">
		<h3>Удалить сообщение?</h3>
		<p>Сообщение исчезнет из переписки для обоих пользователей.</p>
		<div class="chat-delete-confirm-actions">
			<button type="button" class="chat-delete-confirm-btn" data-action="cancel-delete">Отмена</button>
			<button type="button" class="chat-delete-confirm-btn is-danger" data-action="confirm-delete">Удалить</button>
		</div>
	</div>
</div>

<div class="chat-lightbox" id="chat-lightbox" hidden>
	<button type="button" class="chat-lightbox-close" id="chat-lightbox-close" aria-label="Закрыть">&times;</button>
	<img src="" id="chat-lightbox-img" alt="Fullscreen Image" hidden>
	<video src="" id="chat-lightbox-video" controls hidden></video>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
