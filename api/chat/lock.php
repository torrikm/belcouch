<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!isset($_SESSION['user_id'])) {
	JsonResponse::send(['success' => false, 'message' => 'Требуется авторизация'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	JsonResponse::send(['success' => false, 'message' => 'Метод не поддерживается'], 405);
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$isSupport = !empty($_POST['support']);

if ($userId <= 0 || !$isSupport) {
	JsonResponse::send(['success' => false, 'message' => 'Некорректный диалог'], 422);
}

try {
	$service = new ChatService();
	$currentUserId = (int) $_SESSION['user_id'];
	$previousLock = $service->getSupportConversationLock($currentUserId, $userId);
	$result = $service->acquireSupportConversationLock((int) $_SESSION['user_id'], $userId);
	if (!empty($result['acquired']) && (empty($previousLock) || empty($previousLock['is_mine']))) {
		$db = new Database();
		$rows = $db->getAll("SELECT id FROM users WHERE role = 'admin'");
		$notifyUserIds = array_map(static function (array $row): int {
			return (int) ($row['id'] ?? 0);
		}, $rows);
		ChatRealtimeNotifier::notifyUsers(
			$notifyUserIds,
			[
				'user_id' => $currentUserId,
				'partner_id' => $userId,
				'is_support' => true,
				'lock' => $result['lock'] ?? null,
			],
			'chat:support_lock_updated'
		);
	}
	JsonResponse::send([
		'success' => true,
		'acquired' => !empty($result['acquired']),
		'lock' => $result['lock'] ?? null,
	]);
} catch (InvalidArgumentException $exception) {
	JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
	JsonResponse::send(['success' => false, 'message' => 'Не удалось занять чат'], 500);
}
