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

// Удаляем объявление
$db->prepareAndExecute('DELETE FROM listings WHERE id = ?', 'i', [$listingId]);

// Удаляем жалобу
$db->prepareAndExecute('DELETE FROM listing_reports WHERE id = ?', 'i', [$reportId]);

JsonResponse::send(['success' => true, 'message' => 'Объявление и жалоба удалены']);
