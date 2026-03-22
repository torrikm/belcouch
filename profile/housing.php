<?php

/**
 * Страница управления жильем в профиле пользователя
 * Позволяет просматривать, добавлять, редактировать и удалять объявления о жилье
 */

require_once '../bootstrap.php';
$pageTitle = "Управление жильем";
$root_path = '../';
$additionalJs = ['../assets/js/housing_fixed.js', '../assets/js/listing_review.js', '../assets/js/favorites.js'];
$additionalCss = ['../assets/css/housing.css'];

if (!isset($_SESSION['user_id']) && !isset($_GET['id'])) {
	header('Location: ../index.php#login-modal');
	exit;
}

$profileService = new ProfilePageService();
$housingService = new ProfileHousingService();
$profile_id = $profileService->resolveProfileId();

try {
	$profileData = $profileService->getProfileData($profile_id);
	$user = $profileData['user'];
	$isOwnProfile = $profileData['isOwnProfile'];
	$starRating = $profileData['starRating'];
	$regions = $profileService->getRegions();
} catch (Exception $e) {
	header('Location: ../index.php');
	exit;
}

require_once '../includes/header.php';

try {
	$housingData = $housingService->getUserListingSummary($profile_id);
	$has_housing = $housingData['has_housing'];
	$listing = $housingData['listing'];
	$listing_id = $housingData['listing_id'];
	$images = $housingData['images'];
	$main_image = $housingData['main_image'];
	$rules = $housingData['rules'];
	$amenities = $housingData['amenities'];
	$duration_name = $housingData['duration_name'];
	$listing_count = $housingData['listing_count'];
	$property_types = $housingService->getPropertyTypes();
	$stayDurations = $housingService->getStayDurations();
	$rulesCatalog = $housingService->getRulesCatalog();
	$amenitiesCatalog = $housingService->getAmenitiesCatalog();
	$user_favorites = isset($_SESSION['user_id'])
		? $housingService->getViewerFavoriteListingIds((int) $_SESSION['user_id'])
		: [];
	$reviews = $listing ? $housingService->getListingReviews($listing_id) : [];
	$has_reviews = !empty($reviews);
	$can_review = $listing ? $housingService->canReviewListing($listing_id, (int) $listing['user_id']) : false;
} catch (Exception $e) {
	$has_housing = false;
	$listing = null;
	$listing_id = 0;
	$images = [];
	$main_image = '';
	$rules = [];
	$amenities = [];
	$duration_name = 'Не указано';
	$listing_count = 0;
	$property_types = [];
	$stayDurations = [];
	$rulesCatalog = [];
	$amenitiesCatalog = [];
	$user_favorites = [];
	$reviews = [];
	$has_reviews = false;
	$can_review = false;
}
?>

<div class="container">
	<div class="profile-page">
		<div class="profile-header-container">
			<?php require_once '../includes/profile_header.php'; ?>

			<div class="profile-right">
				<div class="profile-nav">
					<a href="./about<?php echo isset($_GET['id']) ? '?id=' . $profile_id : ''; ?>"
						class="profile-nav-item">Обо мне</a>
					<a href="./housing<?php echo isset($_GET['id']) ? '?id=' . $profile_id : ''; ?>"
						class="profile-nav-item active">Жилье</a>
					<?php if ($isOwnProfile): ?>
						<a href="./favorites" class="profile-nav-item">Избранное</a>
					<?php endif; ?>
				</div>

				<div class="profile-block profile-housing-block">
					<?php if (!$has_housing && $isOwnProfile): ?>
						<div class="no-listings">
							<h3>У Вас пока нет предложений жилья!</h3>
							<button id="add-housing-btn" class="btn btn-primary">Добавить</button>
						</div>
					<?php elseif (!$has_housing && !$isOwnProfile): ?>
						<div class="no-listings">
							<h3>У пользователя пока нет объявлений о жилье</h3>
						</div>
					<?php else: ?>
						<?php if ($isOwnProfile): ?>
							<?php if ($listing_count < 1): ?>
								<button id="add-housing-btn" class="btn btn-primary">Добавить объявление</button>
							<?php endif; ?>
						<?php else: ?>
							<div class="housing-section-header">
								<h2 class="housing-section-title">Объявление пользователя</h2>
								<div class="housing-rating-badge">
									<span
										class="housing-rating-value"><?php echo number_format((float) ($listing['avg_rating'] ?? 0), 2); ?></span>
									<img src="../assets/img/icons/star-filled.svg" alt="Рейтинг жилья"
										class="housing-rating-icon">
								</div>
							</div>
						<?php endif; ?>

						<?php if ($has_housing && $listing): ?>
							<div class="listing-container">
								<div class="listing-content-wrapper">
									<div class="gallery-container">
										<div class="main-image-container">
											<?php if (!empty($main_image)): ?>
												<img id="main-gallery-image" src="../<?php echo $main_image; ?>"
													alt="<?php echo htmlspecialchars($listing['title'] ?? 'Жилье'); ?>">
												<button class="gallery-nav prev">❮</button>
												<button class="gallery-nav next">❯</button>
											<?php else: ?>
												<div class="no-image">Нет фото</div>
											<?php endif; ?>
										</div>

										<?php if (!empty($images)): ?>
											<?php $hasMoreThumbs = count($images) > 4; ?>
											<div class="thumbnails-container<?php echo $hasMoreThumbs ? ' is-collapsed' : ''; ?>">
												<?php foreach ($images as $index => $image): ?>
													<div class="thumbnail<?php echo ($index === 0) ? ' active' : ''; ?>"
														data-index="<?php echo $index; ?>" data-src="../<?php echo $image; ?>">
														<img src="../<?php echo $image; ?>" alt="Миниатюра <?php echo $index + 1; ?>">
													</div>
												<?php endforeach; ?>
											</div>
											<?php if ($hasMoreThumbs): ?>
												<button type="button" class="thumbnails-toggle" data-state="collapsed">Показать все
													фото</button>
											<?php endif; ?>
										<?php endif; ?>
									</div>

									<div class="listing-info">
										<div class="listing-info-header">
											<h3 class="listing-title">
												<?php echo htmlspecialchars($listing['title'] ?? 'Жилье для ' . $listing['max_guests'] . ' человек'); ?>
											</h3>
											<?php if (!$isOwnProfile): ?>
												<?php $is_favorite = in_array((int) $listing['id'], $user_favorites, true); ?>
												<button class="favorite-btn <?php echo $is_favorite ? 'active' : ''; ?>"
													data-id="<?php echo $listing['id']; ?>"
													title="<?php echo $is_favorite ? 'Удалить из избранного' : 'Добавить в избранное'; ?>">
													<?php echo $is_favorite ? '♥' : '♡'; ?>
												</button>
											<?php endif; ?>
										</div>

										<div class="listing-details listing-details--compact">
											<div class="detail-item">
												<strong>Тип:</strong>
												<span
													class="detail-value"><?php echo htmlspecialchars($listing['property_type_name']); ?></span>
											</div>

											<div class="detail-item">
												<strong>Количество спальных мест:</strong>
												<span class="detail-value"><?php echo $listing['max_guests']; ?></span>
											</div>

											<div class="detail-item detail-item--stacked">
												<strong>Время пребывания:</strong>
												<span
													class="detail-value"><?php echo htmlspecialchars($duration_name); ?></span>
											</div>

											<div class="detail-item">
												<strong>Примечание:</strong>
												<?php if (!empty($listing['notes'])): ?>
													<span
														class="detail-value detail-value--notes"><?php echo htmlspecialchars($listing['notes']); ?></span>
												<?php else: ?>
													<span class="detail-value detail-value--muted">нет</span>
												<?php endif; ?>
											</div>
										</div>

										<?php if ($isOwnProfile): ?>
											<div class="listing-actions">
												<button class="btn-edit-listing"
													data-id="<?php echo $listing['id']; ?>">Редактировать</button>
												<button class="btn-delete-listing"
													data-id="<?php echo $listing['id']; ?>">Удалить</button>
											</div>
										<?php endif; ?>
									</div>
								</div>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<div class="details">
		<div class="rules-section">
			<h3>Правила</h3>
			<div class="rules-list">
				<?php if (!empty($rules)): ?>
					<?php foreach ($rules as $rule): ?>
						<div class="rule-item">
							<img src="../assets/img/icons/rules/<?php echo $rule['icon']; ?>"
								alt="<?php echo $rule['name']; ?>">
							<span><?php echo htmlspecialchars($rule['name']); ?></span>
						</div>
					<?php endforeach; ?>
				<?php else: ?>
					<p class="no-items">Правила не указаны</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="amenities-section">
			<h3>Основные удобства</h3>
			<div class="amenities-list">
				<?php if (!empty($amenities)): ?>
					<?php foreach ($amenities as $amenity): ?>
						<div class="amenity-item">
							<img src="../assets/img/icons/amenities/<?php echo $amenity['icon']; ?>"
								alt="<?php echo $amenity['name']; ?>">
							<span><?php echo htmlspecialchars($amenity['name']); ?></span>
						</div>
					<?php endforeach; ?>
				<?php else: ?>
					<p class="no-items">Удобства не указаны</p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if ($has_housing && $listing): ?>
		<div class="reviews-section listing-reviews-section">
			<?php if ($has_reviews): ?>
				<h3 class="reviews-title">Отзывы</h3>
				<div class="reviews-list listing-reviews-list">
					<?php foreach ($reviews as $review): ?>
						<div class="review-item">
							<div class="review-item-header">
								<div class="reviewer-info">
									<?php if ($review['avatar_image']): ?>
										<img src="<?php echo API_URL; ?>/users/get_avatar.php?id=<?php echo $review['user_id']; ?>"
											alt="Аватар" class="reviewer-avatar">
									<?php else: ?>
										<div class="reviewer-avatar reviewer-avatar-placeholder">
											<?php echo mb_substr($review['first_name'], 0, 1) . mb_substr($review['last_name'], 0, 1); ?>
										</div>
									<?php endif; ?>
									<div class="reviewer-details">
										<div class="reviewer-name">
											<?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
										</div>
										<div class="review-date"><?php echo date('d.m.Y', strtotime($review['created_at'])); ?>
										</div>
									</div>
								</div>
								<div class="review-rating">
									<?php for ($i = 1; $i <= 5; $i++): ?>
										<img src="../assets/img/icons/<?php echo ($i <= $review['rating']) ? 'star-filled.svg' : 'star-void.svg'; ?>"
											alt="Звезда" class="review-star-icon">
									<?php endfor; ?>
								</div>
							</div>
							<div class="review-content">
								<p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<div class="reviews-empty">Пока нет отзывов</div>
			<?php endif; ?>

			<?php if ($can_review): ?>
				<div class="review-block listing-review-block">
					<div class="review-header">
						<h3>Написать отзыв</h3>
						<div class="review-stars">
							<?php for ($i = 1; $i <= 5; $i++): ?>
								<span class="star" data-value="<?php echo $i; ?>">
									<img src="../assets/img/icons/star-void.svg" alt="Звезда" class="star-icon">
								</span>
							<?php endfor; ?>
						</div>
					</div>
					<form id="listing-review-form" method="post">
						<input type="hidden" name="listing_id" value="<?php echo (int) $listing_id; ?>">
						<input type="hidden" name="rating" value="0">
						<textarea name="comment" rows="5" class="review-textarea"
							placeholder="Напишите свои впечатления об этом жилье" required></textarea>
						<button type="submit" class="btn btn-primary">Отправить</button>
					</form>
				</div>
			<?php elseif (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $listing['user_id']): ?>
				<div class="profile-review-info listing-review-info">
					<p>Вы уже оставили отзыв на это жилье.</p>
				</div>
			<?php elseif (!isset($_SESSION['user_id'])): ?>
				<div class="profile-review-info listing-review-info">
					<p>Чтобы оставить отзыв, <a href="../index#login-modal">войдите</a> в систему.</p>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

<?php if ($isOwnProfile): ?>
	<div id="housing-modal" class="modal-overlay">
		<div class="modal">
			<div class="modal-header">
				<h2 id="housing-modal-title" class="modal-title">Добавить объявление</h2>
				<button type="button" class="modal-close">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
						stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			<div class="modal-body">
				<form id="housing-form" method="post" enctype="multipart/form-data"
					action="<?php echo API_URL; ?>/listings/add_listing.php">
					<input type="hidden" id="listing_id" name="listing_id" value="">

					<div class="form-group">
						<label for="property_type_id">Тип жилья</label>
						<select id="property_type_id" name="property_type_id" class="form-control" required>
							<option value="">Выберите тип жилья</option>
							<?php foreach ($property_types as $type): ?>
								<option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="form-group">
						<label for="beds_count">Количество спальных мест</label>
						<input type="number" id="beds_count" name="beds_count" class="form-control" min="1" value="1"
							required>
					</div>

					<div class="form-group">
						<label for="listing_city">Город</label>
						<input type="text" id="listing_city" name="listing_city" class="form-control" required>
					</div>

					<div class="form-group">
						<label for="listing_region_id">Область</label>
						<select id="listing_region_id" name="listing_region_id" class="form-control" required>
							<option value="">Выберите область</option>
							<?php foreach ($regions as $region): ?>
								<option value="<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="form-group form-group-full">
						<label for="title">Название объявления</label>
						<input type="text" id="title" name="title" class="form-control" required>
					</div>

					<div class="form-group">
						<label for="stay_duration_id">Время пребывания</label>
						<select id="stay_duration_id" name="stay_duration_id" class="form-control" required>
							<option value="">Выберите время пребывания</option>
							<?php foreach ($stayDurations as $duration): ?>
								<option value="<?php echo $duration['id']; ?>">
									<?php echo htmlspecialchars($duration['name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="form-group">
						<label for="notes">Примечания</label>
						<textarea id="notes" name="notes" class="form-control" rows="4"></textarea>
					</div>

					<div class="form-group form-group-full">
						<label for="housing_photos">Фотографии жилья</label>
						<div class="photo-upload-container">
							<label for="housing_photos" class="photo-upload-btn">
								<span>Загрузить фото</span>
							</label>
							<input type="file" id="housing_photos" name="housing_photos[]" accept="image/*" multiple
								style="display:none;">
						</div>
						<div id="photo-previews" class="photo-previews"></div>
					</div>

					<div class="form-group form-group-full">
						<label for="rules" class="rules-title">Правила проживания</label>
						<div class="select-with-button">
							<select id="rules" name="rules_select" class="form-control">
								<option value="">Выберите правило</option>
								<?php foreach ($rulesCatalog as $rule): ?>
									<option value="<?php echo $rule['id']; ?>"><?php echo htmlspecialchars($rule['name']); ?>
									</option>
								<?php endforeach; ?>
							</select>

							<button type="button" class="btn-add-item" data-target="rules"
								aria-label="Добавить правило">+</button>
						</div>
						<div class="selected-items-container" id="selected-rules"></div>
					</div>

					<div class="form-group form-group-full">
						<label for="amenities" class="amenities-title">Основные удобства</label>
						<div class="select-with-button">
							<select id="amenities" class="form-control">
								<option value="">Выберите удобство</option>
								<?php foreach ($amenitiesCatalog as $amenity): ?>
									<option value="<?php echo $amenity['id']; ?>">
										<?php echo htmlspecialchars($amenity['name']); ?>
									</option>
								<?php endforeach; ?>
							</select>

							<button type="button" class="btn-add-item" data-target="amenities"
								aria-label="Добавить удобство">+</button>
						</div>
						<div class="selected-items-container" id="selected-amenities"></div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="submit" form="housing-form" class="btn-save">Сохранить</button>
				<button type="button" class="btn-cancel">Отмена</button>
			</div>
		</div>
	</div>
<?php endif; ?>

<script>
	document.addEventListener('DOMContentLoaded', function () {
		const mainImage = document.getElementById('main-gallery-image');
		const thumbnails = document.querySelectorAll('.thumbnail');
		const prevButton = document.querySelector('.gallery-nav.prev');
		const nextButton = document.querySelector('.gallery-nav.next');

		if (!mainImage) return;

		let currentIndex = 0;
		const maxIndex = thumbnails.length - 1;

		function updateMainImage(index) {
			if (index < 0) index = maxIndex;
			if (index > maxIndex) index = 0;

			currentIndex = index;

			const src = thumbnails[index].getAttribute('data-src');
			mainImage.src = src;

			thumbnails.forEach(thumb => thumb.classList.remove('active'));
			thumbnails[index].classList.add('active');
		}

		if (prevButton) {
			prevButton.addEventListener('click', function () {
				updateMainImage(currentIndex - 1);
			});
		}

		if (nextButton) {
			nextButton.addEventListener('click', function () {
				updateMainImage(currentIndex + 1);
			});
		}

		thumbnails.forEach(thumbnail => {
			thumbnail.addEventListener('click', function () {
				const index = parseInt(this.getAttribute('data-index'));
				updateMainImage(index);
			});
		});
	});
</script>

<?php include '../includes/footer.php'; ?>