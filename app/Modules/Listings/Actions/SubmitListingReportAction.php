<?php
class SubmitListingReportAction
{
	public function handle(): void
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			JsonResponse::send(['success' => false, 'message' => 'Метод не поддерживается'], 405);
		}

		if (!isset($_SESSION['user_id'])) {
			JsonResponse::send(['success' => false, 'message' => 'Чтобы отправить жалобу, войдите в систему'], 401);
		}

		if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
			JsonResponse::send(['success' => false, 'message' => 'Недействительный CSRF токен'], 419);
		}

		$db = new Database();
		$userId = (int) $_SESSION['user_id'];
		$listingId = isset($_POST['listing_id']) ? (int) $_POST['listing_id'] : 0;
		$reason = trim((string) ($_POST['reason'] ?? ''));
		$details = trim((string) ($_POST['details'] ?? ''));

		if ($listingId <= 0) {
			JsonResponse::send(['success' => false, 'message' => 'Объявление не найдено'], 422);
		}

		$allowedReasons = $this->getReportReasons($db);

		if (!isset($allowedReasons[$reason])) {
			JsonResponse::send(['success' => false, 'message' => 'Выберите причину жалобы'], 422);
		}

		if (mb_strlen($details) > 1000) {
			JsonResponse::send(['success' => false, 'message' => 'Описание жалобы не должно превышать 1000 символов'], 422);
		}

		$listingStmt = $db->prepareAndExecute('SELECT id, user_id FROM listings WHERE id = ?', 'i', [$listingId]);
		$listing = $listingStmt->get_result()->fetch_assoc();
		if (!$listing) {
			JsonResponse::send(['success' => false, 'message' => 'Объявление не найдено'], 404);
		}

		if ((int) $listing['user_id'] === $userId) {
			JsonResponse::send(['success' => false, 'message' => 'Нельзя пожаловаться на собственное объявление'], 422);
		}

		$duplicateStmt = $db->prepareAndExecute(
			'SELECT id FROM listing_reports WHERE listing_id = ? AND reporter_id = ? LIMIT 1',
			'ii',
			[$listingId, $userId]
		);
		if ($duplicateStmt->get_result()->fetch_assoc()) {
			JsonResponse::send(['success' => false, 'message' => 'Вы уже отправляли жалобу на это объявление'], 409);
		}

		$db->prepareAndExecute(
			'INSERT INTO listing_reports (listing_id, reporter_id, reason_code, reason_label, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
			'iisss',
			[$listingId, $userId, $reason, $allowedReasons[$reason], $details]
		);

		JsonResponse::send(['success' => true, 'message' => 'Жалоба отправлена. Мы проверим это объявление']);
	}

	private function getReportReasons(Database $db): array
	{
		$stmt = $db->prepareAndExecute(
			'SELECT code, label FROM listing_report_reasons WHERE is_active = 1 ORDER BY sort_order ASC',
			'',
			[]
		);
		$result = $stmt->get_result();
		$reasons = [];

		while ($row = $result->fetch_assoc()) {
			$reasons[$row['code']] = $row['label'];
		}

		return $reasons;
	}
}
