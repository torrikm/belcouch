<?php
class ListingsApiService
{
	private Database $db;

	public function __construct()
	{
		$this->db = new Database();
	}

	public function getSearchListings(string $query): array
	{
		$sql = 'SELECT l.id, l.title, l.city, u.id as user_id, pt.name as property_type FROM listings l JOIN users u ON l.user_id = u.id JOIN property_types pt ON l.property_type_id = pt.id WHERE l.title LIKE ? OR l.city LIKE ? LIMIT 3';
		$stmt = $this->db->prepareAndExecute($sql, 'ss', ["%$query%", "%$query%"]);
		$result = $stmt->get_result();
		$listings = [];

		while ($row = $result->fetch_assoc()) {
			$row['image'] = $this->resolveListingImage((int) $row['id']);
			$listings[] = $row;
		}

		return $listings;
	}

	public function getSliderImage(int $id): ?string
	{
		$stmt = $this->db->prepareAndExecute('SELECT image FROM slider_images WHERE id = ?', 'i', [$id]);
		$result = $stmt->get_result();
		$row = $result->fetch_assoc();

		if (!$row) {
			return null;
		}

		return $row['image'];
	}

	public function getFilteredListingsData(array $input, ?int $viewerId): array
	{
		$filters = $this->resolveFilters($input);
		$page = isset($input['page']) ? max(1, (int) $input['page']) : 1;
		$perPage = 12;
		$offset = ($page - 1) * $perPage;
		$whereData = $this->buildWhereClause($filters);

		$userFavorites = $viewerId ? $this->getUserFavoriteIds($viewerId) : [];
		$totalListings = $this->getTotalListings($whereData);
		$totalPages = (int) ceil($totalListings / $perPage);
		$listings = $this->getListings($whereData, $offset, $perPage);

		return [
			'filters' => $filters,
			'user_favorites' => $userFavorites,
			'listings' => $listings,
			'total' => $totalListings,
			'page' => $page,
			'total_pages' => $totalPages,
		];
	}

	private function resolveFilters(array $input): array
	{
		return [
			'property_type_id' => isset($input['property_type']) ? (int) $input['property_type'] : 0,
			'min_guests' => isset($input['min_guests']) ? (int) $input['min_guests'] : 0,
			'max_guests' => isset($input['max_guests']) ? (int) $input['max_guests'] : 0,
			'stay_duration_id' => isset($input['stay_duration']) ? (int) $input['stay_duration'] : 0,
			'region_id' => isset($input['region']) ? (int) $input['region'] : 0,
			'city' => isset($input['city']) ? trim((string) $input['city']) : '',
			'has_amenities' => isset($input['amenities']) && is_array($input['amenities']) ? $input['amenities'] : [],
			'has_rules' => isset($input['rules']) && is_array($input['rules']) ? $input['rules'] : [],
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

		if (!empty($filters['has_amenities'])) {
			$amenityConditions = [];
			foreach ($filters['has_amenities'] as $amenityId) {
				$amenityId = (int) $amenityId;
				if ($amenityId > 0) {
					$amenityConditions[] = 'EXISTS (SELECT 1 FROM listing_amenities la WHERE la.listing_id = l.id AND la.amenity_id = ?)';
					$params[] = $amenityId;
					$types .= 'i';
				}
			}
			if (!empty($amenityConditions)) {
				$conditions[] = '(' . implode(' AND ', $amenityConditions) . ')';
			}
		}

		if (!empty($filters['has_rules'])) {
			$ruleConditions = [];
			foreach ($filters['has_rules'] as $ruleId) {
				$ruleId = (int) $ruleId;
				if ($ruleId > 0) {
					$ruleConditions[] = 'EXISTS (SELECT 1 FROM listing_rules lr WHERE lr.listing_id = l.id AND lr.rule_id = ?)';
					$params[] = $ruleId;
					$types .= 'i';
				}
			}
			if (!empty($ruleConditions)) {
				$conditions[] = '(' . implode(' AND ', $ruleConditions) . ')';
			}
		}

		return [
			'where_clause' => empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions),
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
		$result = $stmt->get_result();
		return (int) $result->fetch_assoc()['total'];
	}

	private function getListings(array $whereData, int $offset, int $perPage): array
	{
		$sql = "SELECT l.*, pt.name as property_type_name, r.name as region_name, sd.name as stay_duration_name, u.first_name, u.last_name, u.avg_rating as user_rating, u.avatar_image, u.is_verify FROM listings l JOIN property_types pt ON l.property_type_id = pt.id JOIN regions r ON l.region_id = r.id LEFT JOIN stay_durations sd ON l.stay_duration_id = sd.id JOIN users u ON l.user_id = u.id {$whereData['where_clause']} ORDER BY l.created_at DESC LIMIT {$offset}, {$perPage}";

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

	private function resolveListingImage(int $listingId): string
	{
		$projectRoot = dirname(__DIR__, 4);
		$listingImagesDir = $projectRoot . '/assets/img/listings/' . $listingId . '/';

		if (file_exists($listingImagesDir . 'main.jpg')) {
			return 'assets/img/listings/' . $listingId . '/main.jpg';
		}

		$imageFiles = glob($listingImagesDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
		if (!empty($imageFiles)) {
			return 'assets/img/listings/' . $listingId . '/' . basename($imageFiles[0]);
		}

		return 'assets/img/listing-placeholder.svg';
	}
}
