<?php

/**
 * Страница "Избранное" - часть профиля пользователя
 * Отображает избранные объявления пользователя
 */

require_once '../bootstrap.php';
require_once '../includes/render_listing_card.php';
$pageTitle = "Избранное";
$root_path = '../';
$additionalCss = [
	'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
	'../assets/css/profile.css',
	'../assets/css/favorites.css',
	'../assets/css/proposals.css',
	'../assets/css/housing.css',
	'../assets/css/housing-modal.css'
];
$additionalJs = [
	'https://cdn.jsdelivr.net/npm/flatpickr',
	'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ru.js',
	'../assets/js/housing_fixed.js',
	'../assets/js/favorites.js'
];

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
	$housingService = new ProfileHousingService();
	$housingData = $housingService->getUserListingSummary($profile_id);
	$has_housing = $housingData['has_housing'];
	$listing = $housingData['listing'];
	$listing_id = $housingData['listing_id'];
	$unavailable_dates = $housingData['unavailable_dates'] ?? [];
} catch (Exception $e) {
	header('Location: ../index.php');
	exit;
}

// Генерируем CSRF токен для календаря
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

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
		<!-- Скрытые элементы для работы календаря -->
		<form id="housing-form" style="display: none;">
			<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
			<input type="hidden" id="listing_id" name="listing_id" value="<?php echo (int) $listing_id; ?>">
			<input type="hidden" id="unavailable_dates_json" name="unavailable_dates_json" value="<?php echo htmlspecialchars(json_encode($unavailable_dates)); ?>">
		</form>

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
										<?php
										echo renderListingCard($listing, [
											'is_favorite' => true,
											'show_chat' => true,
											'root_path' => '../',
										]);
										?>
								<?php endforeach; ?>
							</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include '../includes/footer.php'; ?>