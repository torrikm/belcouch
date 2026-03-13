<?php

class SubmitSupportRequestAction
{
	public function handle(): void
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			JsonResponse::send(['success' => false, 'message' => 'Метод не поддерживается'], 405);
		}

		if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
			JsonResponse::send(['success' => false, 'message' => 'Недействительный CSRF токен'], 419);
		}

		$senderEmail = trim((string) ($_POST['email'] ?? ''));
		$senderName = trim((string) ($_POST['name'] ?? ''));
		$subject = trim((string) ($_POST['subject'] ?? ''));
		$message = trim((string) ($_POST['message'] ?? ''));

		try {
			$service = new SupportService();
			$service->sendSupportEmail(
				$senderEmail,
				$senderName,
				$subject,
				$message,
				isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null
			);

			JsonResponse::send(['success' => true, 'message' => 'Обращение отправлено в поддержку']);
		} catch (Exception $exception) {
			JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
		}
	}
}
