<?php
class ProposalsPageService
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function getPageData(): array
    {
        $filters = $this->resolveFilters();
        $pagination = $this->resolvePagination();
        $whereData = $this->buildWhereClause($filters);

        $userFavorites = isset($_SESSION['user_id'])
            ? $this->getUserFavoriteIds((int) $_SESSION['user_id'])
            : [];

        $totalListings = $this->getTotalListings($whereData);
        $totalPages = (int) ceil($totalListings / $pagination['per_page']);
        $listings = $this->getListings($whereData, $pagination['offset'], $pagination['per_page']);

        return [
            'filters' => $filters,
            'pagination' => [
                'page' => $pagination['page'],
                'per_page' => $pagination['per_page'],
                'total_listings' => $totalListings,
                'total_pages' => $totalPages,
            ],
            'listings' => $listings,
            'user_favorites' => $userFavorites,
            'property_types' => $this->fetchAll('SELECT * FROM property_types ORDER BY name'),
            'regions' => $this->fetchAll('SELECT * FROM regions ORDER BY name'),
            'stay_durations' => $this->fetchAll('SELECT * FROM stay_durations ORDER BY days'),
            'amenities' => $this->fetchAll('SELECT * FROM amenities ORDER BY name'),
            'rules' => $this->fetchAll('SELECT * FROM rules ORDER BY name'),
        ];
    }

    private function resolveFilters(): array
    {
        return [
            'property_type_id' => isset($_GET['property_type']) ? (int) $_GET['property_type'] : 0,
            'min_guests' => isset($_GET['min_guests']) ? (int) $_GET['min_guests'] : 0,
            'max_guests' => isset($_GET['max_guests']) ? (int) $_GET['max_guests'] : 0,
            'stay_duration_id' => isset($_GET['stay_duration']) ? (int) $_GET['stay_duration'] : 0,
            'region_id' => isset($_GET['region']) ? (int) $_GET['region'] : 0,
            'city' => isset($_GET['city']) ? trim($_GET['city']) : '',
            'has_amenities' => isset($_GET['amenities']) && is_array($_GET['amenities']) ? $_GET['amenities'] : [],
            'has_rules' => isset($_GET['rules']) && is_array($_GET['rules']) ? $_GET['rules'] : [],
        ];
    }

    private function resolvePagination(): array
    {
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = 12;

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    private function buildWhereClause(array $filters): array
    {
        $conditions = [];
        $params = [];
        $types = '';

        if ($filters['property_type_id'] > 0) {
            $conditions[] = 'l.property_type_id = ?';
            $params[] = $filters['property_type_id'];
            $types .= 'i';
        }

        if ($filters['min_guests'] > 0) {
            $conditions[] = 'l.max_guests >= ?';
            $params[] = $filters['min_guests'];
            $types .= 'i';
        }

        if ($filters['max_guests'] > 0) {
            $conditions[] = 'l.max_guests <= ?';
            $params[] = $filters['max_guests'];
            $types .= 'i';
        }

        if ($filters['stay_duration_id'] > 0) {
            $conditions[] = 'l.stay_duration_id = ?';
            $params[] = $filters['stay_duration_id'];
            $types .= 'i';
        }

        if ($filters['region_id'] > 0) {
            $conditions[] = 'l.region_id = ?';
            $params[] = $filters['region_id'];
            $types .= 'i';
        }

        if ($filters['city'] !== '') {
            $conditions[] = 'l.city LIKE ?';
            $params[] = '%' . $filters['city'] . '%';
            $types .= 's';
        }

        return [
            'where_clause' => !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '',
            'params' => $params,
            'types' => $types,
        ];
    }

    private function getTotalListings(array $whereData): int
    {
        $sql = 'SELECT COUNT(*) as total FROM listings l ' . $whereData['where_clause'];

        if (empty($whereData['params'])) {
            $result = $this->db->query($sql);
            return (int) $result->fetch_assoc()['total'];
        }

        $stmt = $this->db->prepareAndExecute($sql, $whereData['types'], $whereData['params']);
        $stmt->bind_result($totalListings);
        $stmt->fetch();
        $stmt->close();

        return (int) $totalListings;
    }

    private function getListings(array $whereData, int $offset, int $perPage): array
    {
        $sql = "SELECT l.*, pt.name as property_type_name, r.name as region_name,
                sd.name as stay_duration_name, u.first_name, u.last_name, u.avg_rating as user_rating, u.is_verify,
                u.avatar_image
                FROM listings l
                JOIN property_types pt ON l.property_type_id = pt.id
                JOIN regions r ON l.region_id = r.id
                LEFT JOIN stay_durations sd ON l.stay_duration_id = sd.id
                JOIN users u ON l.user_id = u.id
                {$whereData['where_clause']}
                ORDER BY l.created_at DESC
                LIMIT {$offset}, {$perPage}";

        $result = empty($whereData['params'])
            ? $this->db->query($sql)
            : $this->db->prepareAndExecute($sql, $whereData['types'], $whereData['params'])->get_result();

        $listings = [];
        while ($row = $result->fetch_assoc()) {
            $row['main_image'] = $this->resolveListingImage((int) $row['id']);
            $listings[] = $row;
        }

        return $listings;
    }

    private function resolveListingImage(int $listingId): string
    {
        $listingImagesDir = 'assets/img/listings/' . $listingId . '/';
        $documentRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);

        if (file_exists($documentRoot . '/' . $listingImagesDir . 'main.jpg')) {
            return $listingImagesDir . 'main.jpg';
        }

        $imageFiles = glob($documentRoot . '/' . $listingImagesDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        if (!empty($imageFiles)) {
            return $listingImagesDir . basename($imageFiles[0]);
        }

        return 'assets/img/listing-placeholder.svg';
    }

    private function getUserFavoriteIds(int $userId): array
    {
        $stmt = $this->db->prepareAndExecute('SELECT listing_id FROM favorites WHERE user_id = ?', 'i', [$userId]);
        $result = $stmt->get_result();
        $favoriteIds = [];

        while ($row = $result->fetch_assoc()) {
            $favoriteIds[] = (int) $row['listing_id'];
        }

        return $favoriteIds;
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
