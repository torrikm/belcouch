<?php
require_once __DIR__ . '/../../bootstrap.php';

AdminAccess::requireAdmin();

if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
	JsonResponse::send(['success' => false, 'message' => 'Недействительный CSRF токен'], 419);
}

if (!isset($_POST['listing_id']) || !isset($_POST['report_id'])) {
	JsonResponse::send(['success' => false, 'message' => 'ID объявления или жалобы не указан'], 422);
}

$listingId = (int) $_POST['listing_id'];
$reportId = (int) $_POST['report_id'];

$db = new Database();
$stmt = $db->prepareAndExecute(
	'SELECT l.id, l.title, u.id as owner_id, u.email as owner_email
	 FROM listings l
	 JOIN users u ON u.id = l.user_id
	 WHERE l.id = ?',
	'i',
	[$listingId]
);
$listingResult = $stmt->get_result();
$listingRow = $listingResult->fetch_assoc();

if (!$listingRow) {
	JsonResponse::send(['success' => false, 'message' => 'Объявление не найдено'], 404);
}

// Удаляем объявление
$db->prepareAndExecute('DELETE FROM listings WHERE id = ?', 'i', [$listingId]);

// Удаляем жалобу
$db->prepareAndExecute('DELETE FROM listing_reports WHERE id = ?', 'i', [$reportId]);

$notificationService = new NotificationService();
$notificationTitle = 'Объявление удалено модерацией';
$notificationMessage = 'Ваше объявление "' . (string) $listingRow['title'] . '" было удалено после проверки жалобы. Письмо с деталями отправлено на вашу почту.';

$notificationId = $notificationService->createUserNotification(
	(int) $listingRow['owner_id'],
	$notificationTitle,
	$notificationMessage,
	'listing_deleted_by_report',
	true
);

if (class_exists('ChatRealtimeNotifier')) {
	ChatRealtimeNotifier::notifyUsers(
		[(int) $listingRow['owner_id']],
		[
			'type' => 'listing_deleted_by_report',
			'notification_id' => $notificationId,
			'title' => $notificationTitle,
			'message' => $notificationMessage,
			'listing_id' => $listingId,
			'listing_title' => (string) $listingRow['title'],
		],
		'account:notification'
	);
}

try {
	$notificationService->sendEmailToUser(
		(string) $listingRow['owner_email'],
		'BelCouch: объявление удалено после жалобы',
		"Здравствуйте!\n\nВаше объявление \"" . (string) $listingRow['title'] . "\" было удалено модератором после рассмотрения жалобы.\nЕсли вы считаете это ошибкой, свяжитесь с поддержкой BelCouch."
	);
} catch (Exception $exception) {
	// Не блокируем удаление, если почта недоступна.
}

JsonResponse::send(['success' => true, 'message' => 'Объявление и жалоба удалены. Владельцу отправлены уведомление в аккаунт и письмо на почту.']);
