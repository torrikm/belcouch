<?php
class SubmitListingReviewAction
{
    public function handle(): void
    {
        if (!isset($_SESSION['user_id'])) {
            JsonResponse::send(['success' => false, 'message' => 'Необходимо авторизоваться'], 401);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            JsonResponse::send(['success' => false, 'message' => 'Неверный метод запроса'], 405);
        }

        $listingId = isset($_POST['listing_id']) ? (int) $_POST['listing_id'] : 0;
        $rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
        $comment = trim($_POST['comment'] ?? '');
        $userId = (int) $_SESSION['user_id'];

        if ($listingId <= 0) {
            JsonResponse::send(['success' => false, 'message' => 'ID объявления указано неверно'], 422);
        }

        if ($rating < 1 || $rating > 5) {
            JsonResponse::send(['success' => false, 'message' => 'Рейтинг должен быть от 1 до 5'], 422);
        }

        if ($comment === '') {
            JsonResponse::send(['success' => false, 'message' => 'Комментарий не может быть пустым'], 422);
        }

        try {
            $db = new Database();
            $checkStmt = $db->prepareAndExecute('SELECT id FROM listing_ratings WHERE listing_id = ? AND user_id = ?', 'ii', [$listingId, $userId]);
            if ($checkStmt->get_result()->num_rows > 0) {
                JsonResponse::send(['success' => false, 'message' => 'Вы уже оставляли отзыв на это объявление'], 422);
            }

            $listingStmt = $db->prepareAndExecute('SELECT id, user_id FROM listings WHERE id = ?', 'i', [$listingId]);
            $listingResult = $listingStmt->get_result();
            if ($listingResult->num_rows === 0) {
                JsonResponse::send(['success' => false, 'message' => 'Объявление не найдено'], 404);
            }

            $listing = $listingResult->fetch_assoc();
            if ((int) $listing['user_id'] === $userId) {
                JsonResponse::send(['success' => false, 'message' => 'Вы не можете оставлять отзывы на свои объявления'], 422);
            }

            $stmt = $db->prepareAndExecute('INSERT INTO listing_ratings (listing_id, user_id, comment, rating) VALUES (?, ?, ?, ?)', 'iisi', [$listingId, $userId, $comment, $rating]);
            if ($stmt->affected_rows <= 0) {
                JsonResponse::send(['success' => false, 'message' => 'Не удалось добавить отзыв'], 500);
            }

            $userStmt = $db->prepareAndExecute('SELECT first_name, last_name, avatar_image FROM users WHERE id = ?', 'i', [$userId]);
            $user = $userStmt->get_result()->fetch_assoc();
            $db->prepareAndExecute('UPDATE listings SET avg_rating = (SELECT AVG(rating) FROM listing_ratings WHERE listing_id = ?) WHERE id = ?', 'ii', [$listingId, $listingId]);

            JsonResponse::send([
                'success' => true,
                'message' => 'Отзыв успешно добавлен',
                'review' => [
                    'id' => $stmt->insert_id,
                    'listing_id' => $listingId,
                    'user_id' => $userId,
                    'comment' => $comment,
                    'rating' => $rating,
                    'created_at' => date('Y-m-d H:i:s'),
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'avatar_image' => $user['avatar_image'] ? true : false
                ]
            ]);
        } catch (Exception $exception) {
            JsonResponse::send(['success' => false, 'message' => 'Ошибка при добавлении отзыва: ' . $exception->getMessage()], 500);
        }
    }
}
