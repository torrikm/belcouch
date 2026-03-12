<?php
class GetListingReviewsAction
{
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            JsonResponse::send(['success' => false, 'message' => 'Неверный метод запроса'], 405);
        }

        $listingId = isset($_GET['listing_id']) ? (int) $_GET['listing_id'] : 0;
        if ($listingId <= 0) {
            JsonResponse::send(['success' => false, 'message' => 'ID объявления указано неверно'], 422);
        }

        try {
            $db = new Database();
            $stmt = $db->prepareAndExecute(
                'SELECT lr.*, u.first_name, u.last_name, u.avatar_image FROM listing_ratings lr JOIN users u ON lr.user_id = u.id WHERE lr.listing_id = ? ORDER BY lr.created_at DESC',
                'i',
                [$listingId]
            );
            $result = $stmt->get_result();
            $reviews = [];

            while ($row = $result->fetch_assoc()) {
                $row['avatar_image'] = !empty($row['avatar_image']);
                $reviews[] = $row;
            }

            JsonResponse::send(['success' => true, 'reviews' => $reviews]);
        } catch (Exception $exception) {
            JsonResponse::send(['success' => false, 'message' => 'Ошибка при получении отзывов: ' . $exception->getMessage()], 500);
        }
    }
}
