<?php
class ToggleFavoriteAction
{
    public function handle(): void
    {
        if (!isset($_SESSION['user_id'])) {
            JsonResponse::send([
                'success' => false,
                'message' => 'Необходимо авторизоваться'
            ], 401);
        }

        $listingId = isset($_POST['listing_id']) ? (int) $_POST['listing_id'] : 0;
        if ($listingId <= 0) {
            JsonResponse::send([
                'success' => false,
                'message' => 'Некорректный ID объявления'
            ], 422);
        }

        try {
            $db = new Database();
            $userId = (int) $_SESSION['user_id'];

            $checkStmt = $db->prepareAndExecute('SELECT id FROM favorites WHERE user_id = ? AND listing_id = ?', 'ii', [$userId, $listingId]);
            $favorite = $checkStmt->get_result()->fetch_assoc();

            if ($favorite) {
                $db->prepareAndExecute('DELETE FROM favorites WHERE id = ?', 'i', [(int) $favorite['id']]);
                JsonResponse::send([
                    'success' => true,
                    'action' => 'removed',
                    'message' => 'Объявление удалено из избранного'
                ]);
            }

            $createdAt = date('Y-m-d H:i:s');
            $db->prepareAndExecute('INSERT INTO favorites (user_id, listing_id, created_at) VALUES (?, ?, ?)', 'iis', [$userId, $listingId, $createdAt]);

            JsonResponse::send([
                'success' => true,
                'action' => 'added',
                'message' => 'Объявление добавлено в избранное'
            ]);
        } catch (Exception $exception) {
            JsonResponse::send([
                'success' => false,
                'message' => 'Ошибка при обработке запроса',
                'error' => $exception->getMessage()
            ], 500);
        }
    }
}
