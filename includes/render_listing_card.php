<?php

/**
 * Рендер карточки жилья
 * Используется на странице предложений, в избранном и в AJAX-ответах
 *
 * @param array $listing Данные объявления
 * @param array $options Опции рендеринга:
 *   - 'show_favorite' (bool): показывать кнопку избранного
 *   - 'show_chat' (bool): показывать кнопку написать
 *   - 'is_favorite' (bool): объявление в избранном
 *   - 'root_path' (string): путь к корню для путей к файлам
 * @return string HTML карточки
 */
function renderListingCard(array $listing, array $options = []): string
{
	$showFavorite = $options['show_favorite'] ?? true;
	$showChat = $options['show_chat'] ?? true;
	$isFavorite = $options['is_favorite'] ?? false;
	$rootPath = $options['root_path'] ?? '';

	// Определяем, показывать ли кнопку избранного и чата
	$userId = $_SESSION['user_id'] ?? null;
	$canFavorite = $showFavorite && (!$userId || $userId != $listing['user_id']);
	$canChat = $showChat && (!$userId || $userId != $listing['user_id']);

	// Инициалы для плейсхолдера аватара
	$initials = '';
	if (!empty($listing['first_name'])) {
		$initials .= mb_substr($listing['first_name'], 0, 1, 'UTF-8');
	}
	if (!empty($listing['last_name'])) {
		$initials .= mb_substr($listing['last_name'], 0, 1, 'UTF-8');
	}

	// Рейтинг жилья
	$rating = $listing['avg_rating'] ?: 0;
	$starIcon = $rating > 0 ? 'star-filled.svg' : 'star-void.svg';

	// Примечание
	$notes = !empty($listing['notes'])
		? htmlspecialchars(mb_substr($listing['notes'], 0, 30)) . (mb_strlen($listing['notes']) > 30 ? '...' : '')
		: 'нет';

	// Рейтинг хоста
	$hostStarType = $listing['user_rating'] > 0 ? 'star-filled.svg' : 'star-void.svg';
	$hostRatingHtml = '<div class="host-rating">' . number_format($listing['user_rating'], 2);
	$hostRatingHtml .= '<img src="' . $rootPath . 'assets/img/icons/' . $hostStarType . '" alt="Рейтинг" class="rating-star">';
	$hostRatingHtml .= '</div>';

	// Кнопка избранного
	$favoriteHtml = '';
	if ($canFavorite) {
		$heartIcon = $isFavorite ? '♥' : '♡';
		$favoriteTitle = $isFavorite ? 'Удалить из избранного' : 'Добавить в избранное';
		$favoriteActiveClass = $isFavorite ? ' active' : '';
		$favoriteHtml = '<button class="favorite-btn' . $favoriteActiveClass . '" data-id="' . $listing['id'] . '" title="' . $favoriteTitle . '">' . $heartIcon . '</button>';
	}

	// Кнопка написать
	$chatHtml = '';
	if ($canChat) {
		$chatHtml = '<a href="' . $rootPath . 'chat?user_id=' . (int) $listing['user_id'] . '&listing_id=' . (int) $listing['id'] . '" class="contact-host-btn">Написать</a>';
	}

	ob_start();
?>
	<div class="listing-card" data-href="<?php echo $rootPath; ?>profile/housing?id=<?php echo $listing['user_id']; ?>">
		<div class="listing-image">
			<a href="<?php echo $rootPath; ?>profile/housing?id=<?php echo $listing['user_id']; ?>">
				<img src="<?php echo $listing['main_image']; ?>"
					alt="<?php echo htmlspecialchars($listing['title']); ?>">
			</a>
			<?php echo $favoriteHtml; ?>
		</div>
		<div class="listing-details">
			<div class="listing-main-info">
				<div class="listing-location">
					<h3 class="city-name"><?php echo htmlspecialchars($listing['city']); ?></h3>
					<p class="region-name"><?php echo htmlspecialchars($listing['region_name']); ?></p>
				</div>

				<div class="listing-rating">
					<div class="rating-value"><?php echo number_format($rating, 2); ?></div>
					<div class="rating-stars">
						<img src="<?php echo $rootPath; ?>assets/img/icons/<?php echo $starIcon; ?>"
							alt="Рейтинг" class="rating-star">
					</div>
				</div>
			</div>

			<div class="listing-specifications">
				<div class="specification-item">
					<span class="spec-label">Тип:</span>
					<span class="spec-value"><?php echo htmlspecialchars($listing['property_type_name']); ?></span>
				</div>
				<div class="specification-item">
					<span class="spec-label">Количество спальных мест:</span>
					<span class="spec-value"><?php echo $listing['max_guests']; ?></span>
				</div>
				<div class="specification-item">
					<span class="spec-label">Время пребывания:</span>
					<span class="spec-value"><?php echo htmlspecialchars($listing['stay_duration_name'] ?: 'Не указано'); ?></span>
				</div>
				<div class="specification-item">
					<span class="spec-label">Примечание:</span>
					<span class="spec-value"><?php echo $notes; ?></span>
				</div>
			</div>

			<div class="listing-host">
				<div class="host-photo">
					<a href="<?php echo $rootPath; ?>profile/about?id=<?php echo $listing['user_id']; ?>" class="host-photo-link">
						<?php if (!empty($listing['avatar_image'])): ?>
							<img src="<?php echo API_URL; ?>/users/get_avatar.php?id=<?php echo $listing['user_id']; ?>"
								alt="Фото пользователя" class="host-avatar">
						<?php else: ?>
							<div class="host-avatar-placeholder">
								<?php echo htmlspecialchars($initials ?: 'U'); ?>
							</div>
						<?php endif; ?>
					</a>
					<?php if (!empty($listing['is_verify'])): ?>
						<div class="proposals-verified-badge">
							<img src="<?php echo $rootPath; ?>assets/img/icons/verified.svg" alt="Проверенный пользователь">
						</div>
					<?php endif; ?>
				</div>
				<div class="host-info">
					<a href="<?php echo $rootPath; ?>profile/about?id=<?php echo $listing['user_id']; ?>" class="host-name">
						<?php echo htmlspecialchars($listing['first_name'] . ' ' . $listing['last_name']); ?>
					</a>
					<?php echo $hostRatingHtml; ?>
				</div>
				<?php echo $chatHtml; ?>
			</div>
		</div>
		<a href="<?php echo $rootPath; ?>profile/housing?id=<?php echo $listing['user_id']; ?>" class="listing-link"></a>
	</div>
<?php
	return ob_get_clean();
}
