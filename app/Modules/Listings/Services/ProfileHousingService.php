<?php
class ProfileHousingService
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function getPropertyTypes(): array
    {
        return $this->fetchAll('SELECT * FROM property_types ORDER BY name');
    }

    public function getStayDurations(): array
    {
        return $this->fetchAll('SELECT * FROM stay_durations ORDER BY days ASC');
    }

    public function getRulesCatalog(): array
    {
        return $this->fetchAll('SELECT * FROM rules ORDER BY name');
    }

    public function getAmenitiesCatalog(): array
    {
        return $this->fetchAll('SELECT * FROM amenities ORDER BY name');
    }

    public function getUserListingSummary(int $profileId): array
    {
        $stmt = $this->db->prepareAndExecute(
            'SELECT l.*, pt.name as property_type_name FROM listings l JOIN property_types pt ON l.property_type_id = pt.id WHERE l.user_id = ?',
            'i',
            [$profileId]
        );

        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return [
                'has_housing' => false,
                'listing' => null,
                'listing_id' => 0,
                'images' => [],
                'main_image' => '',
                'rules' => [],
                'amenities' => [],
                'duration_name' => 'Не указано',
                'listing_count' => 0,
            ];
        }

        $listing = $result->fetch_assoc();
        $listingId = (int) $listing['id'];

        $images = [];
        $mainImage = '';
        $imagesDir = dirname(__DIR__, 4) . '/assets/img/listings/' . $listingId . '/';
        if (is_dir($imagesDir)) {
            $files = glob($imagesDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
            if (!empty($files)) {
                foreach ($files as $file) {
                    $images[] = 'assets/img/listings/' . $listingId . '/' . basename($file);
                }
                $mainImage = $images[0];
            }
        }

        $durationName = 'Не указано';
        if (!empty($listing['stay_duration_id'])) {
            $durationStmt = $this->db->prepareAndExecute('SELECT name FROM stay_durations WHERE id = ?', 'i', [(int) $listing['stay_duration_id']]);
            $durationResult = $durationStmt->get_result();
            if ($durationResult->num_rows > 0) {
                $durationName = $durationResult->fetch_assoc()['name'];
            }
        }

        $rules = [];
        $rulesStmt = $this->db->prepareAndExecute(
            'SELECT r.name, r.icon FROM listing_rules lr JOIN rules r ON lr.rule_id = r.id WHERE lr.listing_id = ?',
            'i',
            [$listingId]
        );
        $rulesResult = $rulesStmt->get_result();
        while ($row = $rulesResult->fetch_assoc()) {
            $rules[] = $row;
        }

        $amenities = [];
        $amenitiesStmt = $this->db->prepareAndExecute(
            'SELECT a.name, a.icon FROM listing_amenities la JOIN amenities a ON la.amenity_id = a.id WHERE la.listing_id = ?',
            'i',
            [$listingId]
        );
        $amenitiesResult = $amenitiesStmt->get_result();
        while ($row = $amenitiesResult->fetch_assoc()) {
            $amenities[] = $row;
        }

        $countStmt = $this->db->prepareAndExecute('SELECT COUNT(*) as count FROM listings WHERE user_id = ?', 'i', [$profileId]);
        $countResult = $countStmt->get_result();
        $listingCount = (int) $countResult->fetch_assoc()['count'];

        return [
            'has_housing' => true,
            'listing' => $listing,
            'listing_id' => $listingId,
            'images' => $images,
            'main_image' => $mainImage,
            'rules' => $rules,
            'amenities' => $amenities,
            'duration_name' => $durationName,
            'listing_count' => $listingCount,
        ];
    }

    public function getViewerFavoriteListingIds(int $viewerId): array
    {
        $stmt = $this->db->prepareAndExecute('SELECT listing_id FROM favorites WHERE user_id = ?', 'i', [$viewerId]);
        $result = $stmt->get_result();
        $favoriteIds = [];
        while ($row = $result->fetch_assoc()) {
            $favoriteIds[] = (int) $row['listing_id'];
        }

        return $favoriteIds;
    }

    public function getListingReviews(int $listingId): array
    {
        $stmt = $this->db->prepareAndExecute(
            'SELECT lr.*, u.first_name, u.last_name, u.avatar_image FROM listing_ratings lr LEFT JOIN users u ON lr.user_id = u.id WHERE lr.listing_id = ? ORDER BY lr.created_at DESC LIMIT 5',
            'i',
            [$listingId]
        );

        $result = $stmt->get_result();
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }

        return $reviews;
    }

    public function canReviewListing(int $listingId, int $ownerUserId): bool
    {
        if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] === $ownerUserId) {
            return false;
        }

        $stmt = $this->db->prepareAndExecute(
            'SELECT id FROM listing_ratings WHERE listing_id = ? AND user_id = ?',
            'ii',
            [$listingId, (int) $_SESSION['user_id']]
        );

        return $stmt->get_result()->num_rows === 0;
    }

    private function fetchAll(string $sql): array
    {
        $result = $this->db->query($sql);
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        return $items;
    }
}
