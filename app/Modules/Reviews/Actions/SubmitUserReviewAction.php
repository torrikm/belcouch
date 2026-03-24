<?php
class SubmitUserReviewAction
{
	public function handle(): void
	{
		if (!isset($_SESSION['user_id'])) {
			JsonResponse::send(['success' => false, 'message' => 'Требуется авторизация'], 401);
		}

		$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
		$raterId = (int) $_SESSION['user_id'];
		$comment = trim($_POST['comment'] ?? '');
		$rating = (int) ($_POST['rating'] ?? 0);

		if ($userId <= 0 || $raterId <= 0 || $rating < 1 || $rating > 5 || $comment === '') {
			JsonResponse::send(['success' => false, 'message' => 'Некорректные данные'], 422);
		}

		try {
			$db = new Database();
			$stmt = $db->prepareAndExecute(
				'INSERT INTO user_ratings (user_id, rater_id, comment, rating, created_at) VALUES (?, ?, ?, ?, NOW())',
				'iisi',
				[$userId, $raterId, $comment, $rating]
			);

			// Keep denormalized value in users table in sync.
			$db->prepareAndExecute(
				'UPDATE users SET avg_rating = (SELECT AVG(rating) FROM user_ratings WHERE user_id = ?) WHERE id = ?',
				'ii',
				[$userId, $userId]
			);

			$ratingStmt = $db->prepareAndExecute(
				'SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS rating_count FROM user_ratings WHERE user_id = ?',
				'i',
				[$userId]
			);
			$ratingData = $ratingStmt->get_result()->fetch_assoc();
			$avgRating = isset($ratingData['avg_rating']) ? (float) $ratingData['avg_rating'] : 0.0;
			$ratingCount = isset($ratingData['rating_count']) ? (int) $ratingData['rating_count'] : 0;

			$reviewId = $stmt->insert_id;
			$userStmt = $db->prepareAndExecute(
				'SELECT first_name, last_name, avatar_image FROM users WHERE id = ?',
				'i',
				[$raterId]
			);
			$userData = $userStmt->get_result()->fetch_assoc();

			JsonResponse::send([
				'success' => true,
				'avg_rating' => $avgRating,
				'rating_count' => $ratingCount,
				'review' => [
					'id' => $reviewId,
					'user_id' => $userId,
					'rater_id' => $raterId,
					'first_name' => $userData['first_name'],
					'last_name' => $userData['last_name'],
					'avatar_image' => !empty($userData['avatar_image']),
					'comment' => $comment,
					'rating' => $rating,
					'created_at' => date('Y-m-d H:i:s')
				]
			]);
		} catch (Exception $exception) {
			JsonResponse::send(['success' => false, 'message' => 'Ошибка при сохранении отзыва'], 500);
		}
	}
}
