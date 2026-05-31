<?php

class SaveListingUnavailableDatesAction
{
	public function handle(): void
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			JsonResponse::send(['success' => false, 'message' => 'Неверный метод запроса'], 405);
		}

		if (!isset($_SESSION['user_id'])) {
			JsonResponse::send(['success' => false, 'message' => 'Вы должны быть авторизованы'], 401);
		}

		if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
			JsonResponse::send(['success' => false, 'message' => 'Недействительный CSRF токен'], 419);
		}

		$listingId = isset($_POST['listing_id']) ? (int) $_POST['listing_id'] : 0;
		$datesJson = isset($_POST['unavailable_dates_json']) ? (string) $_POST['unavailable_dates_json'] : '[]';
		$dates = json_decode($datesJson, true);
		$dates = is_array($dates) ? $dates : [];

		if ($listingId <= 0) {
			JsonResponse::send(['success' => false, 'message' => 'Сначала сохраните объявление'], 422);
		}

		try {
			$db = new Database();
			$ownerStmt = $db->prepareAndExecute('SELECT user_id FROM listings WHERE id = ?', 'i', [$listingId]);
			$ownerResult = $ownerStmt->get_result();
			$ownerRow = $ownerResult->fetch_assoc();

			if (!$ownerRow) {
				JsonResponse::send(['success' => false, 'message' => 'Объявление не найдено'], 404);
			}

			if ((int) $ownerRow['user_id'] !== (int) $_SESSION['user_id']) {
				JsonResponse::send(['success' => false, 'message' => 'У вас нет прав на редактирование этого объявления'], 403);
			}

			$db->prepareAndExecute('DELETE FROM listing_unavailable_dates WHERE listing_id = ?', 'i', [$listingId]);

			$savedDates = [];
			foreach ($dates as $rawDate) {
				$rawDate = trim((string) $rawDate);
				if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
					continue;
				}
				$db->prepareAndExecute(
					'INSERT INTO listing_unavailable_dates (listing_id, unavailable_date) VALUES (?, ?)',
					'is',
					[$listingId, $rawDate]
				);
				$savedDates[] = $rawDate;
			}

			JsonResponse::send([
				'success' => true,
				'message' => 'Календарь сохранён',
				'unavailable_dates' => $savedDates,
			]);
		} catch (Exception $exception) {
			JsonResponse::send(['success' => false, 'message' => 'Ошибка при сохранении календаря: ' . $exception->getMessage()], 500);
		}
	}
}
