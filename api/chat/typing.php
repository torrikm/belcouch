<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	JsonResponse::send(['success' => false, 'message' => 'Требуется авторизация'], 401);
}

$currentUserId = (int) $_SESSION['user_id'];
$partnerId = isset($_POST['partner_id']) ? (int) $_POST['partner_id'] : 0;

if ($partnerId <= 0 || $partnerId === $currentUserId) {
	JsonResponse::send(['success' => false, 'message' => 'Некорректный получатель'], 422);
}

$dir = __DIR__ . '/../../uploads/chat_typing/';
if (!is_dir($dir)) {
	mkdir($dir, 0777, true);
}

$filePath = $dir . 'typing_' . $currentUserId . '_' . $partnerId . '.txt';
file_put_contents($filePath, (string) time());

ChatRealtimeNotifier::notifyUsers(
	[$partnerId],
	[
		'user_id' => $currentUserId,
		'partner_id' => $partnerId,
	],
	'chat:typing'
);

JsonResponse::send(['success' => true]);
