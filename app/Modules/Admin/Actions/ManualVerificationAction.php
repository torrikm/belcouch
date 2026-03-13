<?php
class ManualVerificationAction
{
	public function handle(): void
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			JsonResponse::send(['success' => false, 'message' => 'Метод не поддерживается'], 405);
		}

		AdminAccess::requireAdmin();

		if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
			JsonResponse::send(['success' => false, 'message' => 'Недействительный CSRF токен'], 419);
		}

		$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
		$action = trim((string) ($_POST['action'] ?? ''));
		$adminNote = isset($_POST['admin_note']) ? trim((string) $_POST['admin_note']) : null;

		if ($userId <= 0) {
			JsonResponse::send(['success' => false, 'message' => 'Пользователь не найден'], 422);
		}

		if (!in_array($action, ['verify', 'unverify'], true)) {
			JsonResponse::send(['success' => false, 'message' => 'Недопустимое действие'], 422);
		}

		try {
			$service = new AdminVerificationService();
			$service->setManualVerification($userId, $action === 'verify', $adminNote, (int) $_SESSION['user_id']);
			JsonResponse::send([
				'success' => true,
				'message' => $action === 'verify' ? 'Верификация выдана вручную' : 'Верификация снята'
			]);
		} catch (Exception $exception) {
			JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
		}
	}
}
