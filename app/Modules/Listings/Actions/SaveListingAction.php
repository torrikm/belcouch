<?php
class SaveListingAction
{
	public function handle(): void
	{
		if (!isset($_SESSION['user_id'])) {
			JsonResponse::send(['success' => false, 'message' => 'Вы должны быть авторизованы для добавления объявлений'], 401);
		}

		$db = new Database();
		$userId = (int) $_SESSION['user_id'];
		$listingId = isset($_POST['listing_id']) && $_POST['listing_id'] !== '' ? (int) $_POST['listing_id'] : null;
		$isUpdate = $listingId !== null;
		$propertyTypeId = isset($_POST['property_type_id']) ? (int) $_POST['property_type_id'] : 0;
		$maxGuests = isset($_POST['beds_count']) ? (int) $_POST['beds_count'] : 1;
		$title = isset($_POST['title']) ? trim($_POST['title']) : '';
		$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
		$listingRegionId = isset($_POST['listing_region_id']) ? (int) $_POST['listing_region_id'] : 1;
		$listingCity = isset($_POST['listing_city']) ? trim($_POST['listing_city']) : 'Минск';
		$stayDurationId = isset($_POST['stay_duration_id']) ? (int) $_POST['stay_duration_id'] : 0;
		$rules = isset($_POST['rules']) && is_array($_POST['rules']) ? $_POST['rules'] : [];
		$amenities = isset($_POST['amenities']) && is_array($_POST['amenities']) ? $_POST['amenities'] : [];

		if ($propertyTypeId <= 0 || $title === '') {
			JsonResponse::send(['success' => false, 'message' => 'Пожалуйста, заполните все обязательные поля'], 422);
		}

		try {
			if ($listingId) {
				$db->prepareAndExecute(
					'UPDATE listings SET property_type_id = ?, max_guests = ?, title = ?, notes = ?, stay_duration_id = ?, region_id = ?, city = ?, updated_at = NOW() WHERE id = ? AND user_id = ?',
					'iissiisii',
					[$propertyTypeId, $maxGuests, $title, $notes, $stayDurationId, $listingRegionId, $listingCity, $listingId, $userId]
				);
			} else {
				$db->prepareAndExecute(
					'INSERT INTO listings (user_id, property_type_id, max_guests, title, notes, stay_duration_id, region_id, city, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
					'iiissiis',
					[$userId, $propertyTypeId, $maxGuests, $title, $notes, $stayDurationId, $listingRegionId, $listingCity]
				);
				$listingId = $db->getLastInsertId();
			}

			$db->prepareAndExecute('DELETE FROM listing_rules WHERE listing_id = ?', 'i', [$listingId]);
			$db->prepareAndExecute('DELETE FROM listing_amenities WHERE listing_id = ?', 'i', [$listingId]);

			foreach ($rules as $ruleId) {
				$ruleId = (int) $ruleId;
				if ($ruleId > 0) {
					$db->prepareAndExecute('INSERT INTO listing_rules (listing_id, rule_id) VALUES (?, ?)', 'ii', [$listingId, $ruleId]);
				}
			}

			foreach ($amenities as $amenityId) {
				$amenityId = (int) $amenityId;
				if ($amenityId > 0) {
					$db->prepareAndExecute('INSERT INTO listing_amenities (listing_id, amenity_id) VALUES (?, ?)', 'ii', [$listingId, $amenityId]);
				}
			}

			$listingsRoot = dirname(__DIR__, 4) . '/assets/img/listings';
			$targetDir = $listingsRoot . '/' . $listingId . '/';

			if (!is_dir($listingsRoot)) {
				mkdir($listingsRoot, 0777, true);
			}
			if (!is_dir($targetDir)) {
				mkdir($targetDir, 0777, true);
			}

			if (!empty($_POST['deleted_images']) && is_array($_POST['deleted_images'])) {
				foreach ($_POST['deleted_images'] as $relativePath) {
					$normalized = str_replace(['..\\', '../', '\\'], ['', '', '/'], ltrim($relativePath, '/'));
					$filePath = dirname(__DIR__, 4) . '/' . $normalized;
					$realFile = realpath($filePath);
					$realTargetDir = realpath(rtrim($targetDir, '/\\'));
					if ($realFile && $realTargetDir && strpos($realFile, $realTargetDir) === 0 && is_file($realFile)) {
						unlink($realFile);
					}
				}
			}

			if (isset($_FILES['housing_photos']) && !empty($_FILES['housing_photos']['name'][0])) {
				// Считаем уже существующие файлы, чтобы не перезаписывать их при добавлении новых фото
				$existingGalleryFiles = glob($targetDir . 'image_*.*');
				$nextIndex = $existingGalleryFiles ? count($existingGalleryFiles) + 1 : 1;
				$mainExists = (bool) glob($targetDir . 'main.*');

				$count = count($_FILES['housing_photos']['name']);
				for ($i = 0; $i < $count; $i++) {
					if ($_FILES['housing_photos']['error'][$i] === UPLOAD_ERR_OK) {
						$tmpName = $_FILES['housing_photos']['tmp_name'][$i];
						$name = $_FILES['housing_photos']['name'][$i];
						$extension = pathinfo($name, PATHINFO_EXTENSION);
						$extension = $extension ? strtolower($extension) : 'jpg';
						if (!$mainExists) {
							$newName = 'main.' . $extension;
							$mainExists = true;
						} else {
							$newName = 'image_' . $nextIndex . '.' . $extension;
							$nextIndex++;
						}
						move_uploaded_file($tmpName, $targetDir . $newName);
					}
				}
			}

			JsonResponse::send([
				'success' => true,
				'message' => $isUpdate ? 'Объявление успешно обновлено' : 'Объявление успешно добавлено',
				'listing_id' => $listingId
			]);
		} catch (Exception $exception) {
			JsonResponse::send(['success' => false, 'message' => 'Ошибка при сохранении объявления: ' . $exception->getMessage()], 500);
		}
	}
}
