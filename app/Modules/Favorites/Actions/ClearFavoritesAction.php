<?php
class ClearFavoritesAction
{
    public function handle(): void
    {
        if (!isset($_SESSION['user_id'])) {
            JsonResponse::send([
                'success' => false,
                'message' => 'Вы должны быть авторизованы для управления избранным'
            ], 401);
        }

        try {
            $db = new Database();
            $userId = (int) $_SESSION['user_id'];
            $db->prepareAndExecute('DELETE FROM favorites WHERE user_id = ?', 'i', [$userId]);

            JsonResponse::send([
                'success' => true,
                'message' => 'Список избранного очищен'
            ]);
        } catch (Exception $exception) {
            JsonResponse::send([
                'success' => false,
                'message' => 'Ошибка при очистке списка избранного: ' . $exception->getMessage()
            ], 500);
        }
    }
}
