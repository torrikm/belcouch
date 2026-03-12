<?php
class DeleteListingAction
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
        if ($listingId <= 0) {
            JsonResponse::send(['success' => false, 'message' => 'Неверный ID объявления'], 422);
        }

        $db = new Database();

        try {
            $checkStmt = $db->prepareAndExecute('SELECT id FROM listings WHERE id = ? AND user_id = ?', 'ii', [$listingId, (int) $_SESSION['user_id']]);
            if ($checkStmt->get_result()->num_rows === 0) {
                JsonResponse::send(['success' => false, 'message' => 'Объявление не найдено или у вас нет прав на его удаление'], 404);
            }

            $db->query('START TRANSACTION');

            $imagesDirectory = dirname(__DIR__, 4) . '/assets/img/listings/' . $listingId;
            if (is_dir($imagesDirectory)) {
                $files = glob($imagesDirectory . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($imagesDirectory);
            }

            $deleteStmt = $db->prepareAndExecute('DELETE FROM listings WHERE id = ?', 'i', [$listingId]);
            if ($deleteStmt->affected_rows > 0) {
                $db->query('COMMIT');
                JsonResponse::send(['success' => true, 'message' => 'Объявление успешно удалено']);
            }

            $db->query('ROLLBACK');
            JsonResponse::send(['success' => false, 'message' => 'Не удалось удалить объявление'], 500);
        } catch (Exception $exception) {
            try {
                $db->query('ROLLBACK');
            } catch (Exception $rollbackException) {
            }
            JsonResponse::send(['success' => false, 'message' => 'Ошибка при удалении объявления: ' . $exception->getMessage()], 500);
        }
    }
}
