<?php

/**
 * Страница "Избранное" - часть профиля пользователя
 * Отображает избранные объявления пользователя
 */

require_once '../bootstrap.php';
$pageTitle = "Избранное";
$root_path = '../';
$additionalJs = ['../assets/js/favorites.js'];

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
	header('Location: ../index.php#login-modal');
	exit;
}

// Получение ID пользователя из URL или использование ID текущего пользователя
$profileService = new ProfilePageService();
$profile_id = $profileService->resolveProfileId();

try {
	$profileData = $profileService->getProfileData($profile_id);
	$user = $profileData['user'];
	$isOwnProfile = $profileData['isOwnProfile'];
	$starRating = $profileData['starRating'];
} catch (Exception $e) {
	header('Location: ../index.php');
	exit;
}
require_once '../includes/header.php';

try {
	$favorites = $profileService->getFavoriteListings($profile_id);
	$has_favorites = !empty($favorites);
} catch (Exception $e) {
	$has_favorites = false;
	$favorites = [];
}
?>

<div class="container">
	<div class="profile-page">
		<!-- Шапка профиля -->
		<div class="profile-header-container">
			<?php require_once '../includes/profile_header.php'; ?>

			<div class="profile-right">
				<div class="profile-nav">
					<a href="./about<?php echo $profile_id != $_SESSION['user_id'] ? '?id=' . $profile_id : ''; ?>"
						class="profile-nav-item">Обо мне</a>
					<a href="./housing<?php echo $profile_id != $_SESSION['user_id'] ? '?id=' . $profile_id : ''; ?>"
						class="profile-nav-item">Жилье</a>
					<?php if ($isOwnProfile): ?>
						<a href="./favorites" class="profile-nav-item active">Избранное</a>
					<?php endif; ?>
				</div>

				<div class="profile-block">
					<?php if (!$has_favorites): ?>
						<!-- Если избранных объявлений нет -->
						<div class="no-favorites">
							<?php if ($isOwnProfile): ?>
								<h3>У вас пока нет избранных объявлений</h3>
								<a href="../proposals" class="btn btn-primary">Найти жилье</a>
							<?php else: ?>
								<h3>У пользователя пока нет избранных объявлений</h3>
							<?php endif; ?>
						</div>
					<?php else: ?>
						<!-- Если есть избранные объявления -->
						<!-- Заголовок избранного с кнопкой очистки -->
						<div class="favorites-header">
							<h2><?php echo $isOwnProfile ? 'Мои избранные объявления' : 'Избранные объявления пользователя'; ?>
							</h2>
							<?php if ($isOwnProfile): ?>
								<button id="clear-favorites" class="btn-clear">Очистить список</button>
							<?php endif; ?>
						</div>

						<div class="listings-list">
							<?php foreach ($favorites as $listing): ?>
								<div class="listing-card">
									<div class="listing-image">
										<a href="housing?id=<?php echo $listing['user_id']; ?>">
											<img src="<?php echo $listing['main_image']; ?>"
												alt="<?php echo htmlspecialchars($listing['title']); ?>">
										</a>
										<button class="favorite-btn active" data-id="<?php echo $listing['listing_id']; ?>"
											title="Удалить из избранного">♥</button>
									</div>
									<div class="listing-details">
										<div class="listing-main-info">
											<div class="listing-location">
												<h3 class="city-name"><?php echo htmlspecialchars($listing['city']); ?></h3>
												<p class="region-name"><?php echo htmlspecialchars($listing['region_name']); ?></p>
											</div>

											<div class="listing-rating">
												<div class="rating-value">
													<?php echo number_format($listing['avg_rating'] ?: 0, 2); ?>
												</div>
												<div class="rating-stars">
													<?php
													$rating = $listing['avg_rating'] ?: 0;
													for ($i = 1; $i <= 5; $i++): ?>
														<img src="../assets/img/icons/<?php echo ($i <= $rating) ? 'star-filled.svg' : 'star-void.svg'; ?>"
															alt="Рейтинг" class="rating-star">
													<?php endfor; ?>
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
												<span class="spec-value"><?php echo !empty($listing['notes']) ? htmlspecialchars(mb_substr($listing['notes'], 0, 30)) . (mb_strlen($listing['notes']) > 30 ? '...' : '') : 'нет'; ?></span>
											</div>
										</div>

										<div class="listing-host">
											<div class="host-photo">
												<?php if (isset($listing['avatar_image']) && $listing['avatar_image']): ?>
													<img src="<?php echo API_URL; ?>/users/get_avatar.php?id=<?php echo $listing['user_id']; ?>"
														alt="Фото пользователя" class="host-avatar">
												<?php else: ?>
													<div class="host-avatar-placeholder">
														<?php
														$initials = '';
														if (!empty($listing['first_name'])) {
															$initials .= mb_substr($listing['first_name'], 0, 1, 'UTF-8');
														}
														if (!empty($listing['last_name'])) {
															$initials .= mb_substr($listing['last_name'], 0, 1, 'UTF-8');
														}
														echo htmlspecialchars($initials ?: 'U');
														?>
													</div>
												<?php endif; ?>
												<?php if (isset($listing['is_verify']) && $listing['is_verify']): ?>
													<div class="host-verification">✓</div>
												<?php endif; ?>
											</div>
											<div class="host-info">
												<a href="housing?id=<?php echo $listing['user_id']; ?>" class="host-name">
													<?php echo htmlspecialchars($listing['first_name'] . ' ' . $listing['last_name']); ?>
												</a>
												<?php if ($listing['user_rating'] > 0): ?>
													<div class="host-rating">
														<?php echo number_format($listing['user_rating'], 2); ?>
														<?php for ($i = 1; $i <= 5; $i++): ?>
															<img src="../assets/img/icons/<?php echo ($i <= $listing['user_rating']) ? 'star-filled.svg' : 'star-void.svg'; ?>"
																alt="Рейтинг" class="rating-star">
														<?php endfor; ?>
													</div>
												<?php endif; ?>
											</div>
											<a href="../chat?user_id=<?php echo (int) $listing['user_id']; ?>&listing_id=<?php echo (int) $listing['listing_id']; ?>"
												class="contact-host-btn">Написать</a>
										</div>
									</div>
									<a href="housing?id=<?php echo $listing['user_id']; ?>" class="listing-link"></a>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include '../includes/footer.php'; ?>
