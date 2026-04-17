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
$resolution = $_POST['resolution'] ?? 'dismissed';

$service = new AdminReportsService();
if ($service->resolveReport($reportId, $resolution)) {
	JsonResponse::send(['success' => true, 'message' => 'Жалоба закрыта']);
} else {
	JsonResponse::send(['success' => false, 'message' => 'Жалоба не найдена'], 404);
}
