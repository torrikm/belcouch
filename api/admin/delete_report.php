<?php
require_once __DIR__ . '/../../bootstrap.php';

AdminAccess::requireAdmin();

if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
	JsonResponse::send(['success' => false, 'message' => 'Недействительный CSRF токен'], 419);
}

if (!isset($_POST['report_id'])) {
	JsonResponse::send(['success' => false, 'message' => 'ID жалобы не указан'], 422);
}

$reportId = (int) $_POST['report_id'];

$db = new Database();
$stmt = $db->prepareAndExecute('DELETE FROM listing_reports WHERE id = ?', 'i', [$reportId]);

if ($stmt->affected_rows > 0) {
	JsonResponse::send(['success' => true, 'message' => 'Жалоба удалена']);
} else {
	JsonResponse::send(['success' => false, 'message' => 'Жалоба не найдена'], 404);
}
