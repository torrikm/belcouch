<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!isset($_SESSION['user_id'])) {
	JsonResponse::send(['success' => false, 'message' => 'Требуется авторизация'], 401);
}

if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
	JsonResponse::send(['success' => false, 'message' => 'Недействительный CSRF токен'], 419);
}

$notificationId = isset($_POST['notification_id']) ? (int) $_POST['notification_id'] : 0;
if ($notificationId <= 0) {
	JsonResponse::send(['success' => false, 'message' => 'Некорректный ID уведомления'], 422);
}

$notificationService = new NotificationService();
$deleted = $notificationService->deleteUserNotification((int) $_SESSION['user_id'], $notificationId);

if (!$deleted) {
	JsonResponse::send(['success' => false, 'message' => 'Уведомление не найдено'], 404);
}

JsonResponse::send(['success' => true, 'message' => 'Уведомление удалено']);
