<?php
class GetListingAction
{
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            JsonResponse::send(['success' => false, 'message' => 'Неверный метод запроса'], 405);
        }

        $listingId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($listingId <= 0) {
            JsonResponse::send(['success' => false, 'message' => 'Неверный ID объявления'], 422);
        }

        try {
            $db = new Database();
            $stmt = $db->prepareAndExecute('SELECT * FROM listings WHERE id = ?', 'i', [$listingId]);
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                JsonResponse::send(['success' => false, 'message' => 'Объявление не найдено'], 404);
            }

            $listing = $result->fetch_assoc();

            if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] !== (int) $listing['user_id']) {
                JsonResponse::send(['success' => false, 'message' => 'У вас нет прав на просмотр данного объявления'], 403);
            }

            $rulesStmt = $db->prepareAndExecute('SELECT r.id, r.name, r.icon FROM rules r JOIN listing_rules lr ON r.id = lr.rule_id WHERE lr.listing_id = ?', 'i', [$listingId]);
            $rules = [];
            $rulesResult = $rulesStmt->get_result();
            while ($rule = $rulesResult->fetch_assoc()) {
                $rules[] = $rule;
            }

            $amenitiesStmt = $db->prepareAndExecute('SELECT a.id, a.name, a.icon FROM amenities a JOIN listing_amenities la ON a.id = la.amenity_id WHERE la.listing_id = ?', 'i', [$listingId]);
            $amenities = [];
            $amenitiesResult = $amenitiesStmt->get_result();
            while ($amenity = $amenitiesResult->fetch_assoc()) {
                $amenities[] = $amenity;
            }

            $imagesDirectory = dirname(__DIR__, 4) . '/assets/img/listings/' . $listingId;
            $images = [];
            if (is_dir($imagesDirectory)) {
                $files = glob($imagesDirectory . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
                sort($files);
                foreach ($files as $index => $file) {
                    $images[] = [
                        'id' => $index + 1,
                        'image_path' => 'assets/img/listings/' . $listingId . '/' . basename($file)
                    ];
                }
            }

            $listing['rules'] = $rules;
            $listing['amenities'] = $amenities;
            $listing['images'] = $images;
            $listing['debug'] = [
                'rules_count' => count($rules),
                'amenities_count' => count($amenities),
                'images_count' => count($images)
            ];

            JsonResponse::send(['success' => true, 'listing' => $listing]);
        } catch (Exception $exception) {
            JsonResponse::send(['success' => false, 'message' => 'Ошибка при получении данных объявления: ' . $exception->getMessage()], 500);
        }
    }
}
