<?php
class ProfilePageService
{
	private Database $db;

	public function __construct()
	{
		$this->db = new Database();
	}

	public function resolveProfileId(): int
	{
		if (isset($_GET['id'])) {
			return (int) $_GET['id'];
		}

		return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
	}

	public function getProfileData(int $profileId): array
	{
		$sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_image, u.is_verify,
                u.registration_date, COALESCE(avg_rating_data.avg_rating, 0) as avg_rating, ud.description, ud.education, ud.occupation,
                ud.interests, ud.gender, ud.birthdate, ud.region_id, r.name as region_name, ud.city,
                COALESCE(ratings.rating_count, 0) AS rating_count
                FROM users u
                LEFT JOIN user_details ud ON u.id = ud.user_id
                LEFT JOIN regions r ON ud.region_id = r.id
                LEFT JOIN (
                    SELECT user_id, AVG(rating) AS avg_rating
                    FROM user_ratings
                    WHERE user_id = ?
                    GROUP BY user_id
                ) avg_rating_data ON avg_rating_data.user_id = u.id
                LEFT JOIN (
                    SELECT user_id, COUNT(*) AS rating_count
                    FROM user_ratings
                    WHERE user_id = ?
                    GROUP BY user_id
                ) ratings ON ratings.user_id = u.id
                WHERE u.id = ?";

		$stmt = $this->db->prepareAndExecute($sql, 'iii', [$profileId, $profileId, $profileId]);
		$result = $stmt->get_result();

		if ($result->num_rows === 0) {
			throw new Exception('Профиль не найден');
		}

		$user = $result->fetch_assoc();

		return [
			'user' => $user,
			'isOwnProfile' => isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $profileId,
			'starRating' => isset($user['avg_rating']) ? round((float) $user['avg_rating']) : 0,
			'registrationDateFormatted' => (new DateTime($user['registration_date']))->format('d.m.Y')
		];
	}

	public function getRegions(): array
	{
		$result = $this->db->query('SELECT id, name FROM regions ORDER BY name ASC');
		$regions = [];
		while ($row = $result->fetch_assoc()) {
			$regions[] = $row;
		}

		return $regions;
	}

	public function getUserRatings(int $profileId): array
	{
		$sql = "SELECT ur.*, u.first_name, u.last_name, u.avatar_image
                FROM user_ratings ur
                JOIN users u ON ur.rater_id = u.id
                WHERE ur.user_id = ?
                ORDER BY ur.created_at DESC
                LIMIT 3";

		$stmt = $this->db->prepareAndExecute($sql, 'i', [$profileId]);
		$result = $stmt->get_result();
		$ratings = [];
		while ($row = $result->fetch_assoc()) {
			$ratings[] = $row;
		}

		return $ratings;
	}

	public function canReviewUser(int $profileId): bool
	{
		if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] === $profileId) {
			return false;
		}

		$checkSql = 'SELECT id FROM user_ratings WHERE user_id = ? AND rater_id = ?';
		$stmt = $this->db->prepareAndExecute($checkSql, 'ii', [$profileId, (int) $_SESSION['user_id']]);
		$result = $stmt->get_result();

		return $result->num_rows === 0;
	}

	public function getFavoriteListings(int $profileId): array
	{
		$sql = "SELECT l.*, l.id as listing_id, pt.name as property_type_name, r.name as region_name,
                sd.name as stay_duration_name, u.first_name, u.last_name, u.avg_rating as user_rating, u.is_verify,
                u.avatar_image
                FROM favorites f
                JOIN listings l ON f.listing_id = l.id
                JOIN property_types pt ON l.property_type_id = pt.id
                JOIN regions r ON l.region_id = r.id
                LEFT JOIN stay_durations sd ON l.stay_duration_id = sd.id
                JOIN users u ON l.user_id = u.id
                WHERE f.user_id = ?";

		$stmt = $this->db->prepareAndExecute($sql, 'i', [$profileId]);
		$result = $stmt->get_result();
		$favorites = [];

		while ($row = $result->fetch_assoc()) {
			$listingId = (int) $row['listing_id'];
			$mainImage = '../assets/img/listings/' . $listingId . '/main.jpg';
			$documentRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']) . '/';

			if (!file_exists($documentRoot . $mainImage)) {
				$imageFiles = glob($documentRoot . 'assets/img/listings/' . $listingId . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
				if (!empty($imageFiles)) {
					$mainImage = '../assets/img/listings/' . $listingId . '/' . basename($imageFiles[0]);
				} else {
					$mainImage = '../assets/img/listing-placeholder.svg';
				}
			}

			$row['main_image'] = $mainImage;
			$favorites[] = $row;
		}

		return $favorites;
	}

	public function getDb(): Database
	{
		return $this->db;
	}
}
