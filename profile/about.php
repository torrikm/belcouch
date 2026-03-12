<?php

/**
 * Страница "О себе" - часть профиля пользователя
 * Отображает личную информацию, образование, интересы и отзывы пользователя
 */

require_once '../bootstrap.php';
$pageTitle = "О пользователе";
$root_path = '../';
$additionalJs = ['../assets/js/review.js', '../assets/js/user-verification.js'];

if (!isset($_GET['id']) && !isset($_SESSION['user_id'])) {
	header("Location: ../index.php#login-modal");
	exit;
}

// Получение ID пользователя из URL или использование ID текущего пользователя
$profileService = new ProfilePageService();
$profile_id = $profileService->resolveProfileId();

// Функция для расчета возраста по дате рождения
function calculateAge($birthdate)
{
	if (empty($birthdate))
		return null;

	$today = new DateTime();
	$birth = new DateTime($birthdate);
	$interval = $today->diff($birth);
	return $interval->y;
}

// Функция для склонения слов в зависимости от числа
function plural_form($number, $forms)
{
	$cases = [2, 0, 1, 1, 1, 2];
	$index = ($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)];
	return $forms[$index];
}

try {
	$regions = $profileService->getRegions();
} catch (Exception $e) {
	$regions = [];
}

try {
	$profileData = $profileService->getProfileData($profile_id);
	$user = $profileData['user'];
	$isOwnProfile = $profileData['isOwnProfile'];
	$starRating = $profileData['starRating'];
	$registrationDateFormatted = $profileData['registrationDateFormatted'];
} catch (Exception $e) {
	header('Location: ../index.php');
	exit;
}

// Определяем текущую вкладку (по умолчанию "О себе")
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'about';
if (!in_array($active_tab, ['about', 'housing', 'favorites'])) {
	$active_tab = 'about';
}

require_once '../includes/header.php';

try {
	$ratings = $profileService->getUserRatings($profile_id);
	$can_review = $profileService->canReviewUser($profile_id);
} catch (Exception $e) {
	$ratings = [];
	$can_review = false;
}
?>

<div class="container">
	<div class="profile-page">
		<!-- Шапка профиля -->
		<div class="profile-header-container">
			<?php require_once '../includes/profile_header.php'; ?>

			<!-- Навигация профиля -->
			<div class="profile-right">
				<div class="profile-nav">
					<a href="./about<?php echo isset($_GET['id']) ? '?id=' . $profile_id : ''; ?>"
						class="profile-nav-item active">Обо мне</a>
					<a href="./housing<?php echo isset($_GET['id']) ? '?id=' . $profile_id : ''; ?>"
						class="profile-nav-item">Жилье</a>
					<?php if ($isOwnProfile): ?>
						<a href="./favorites" class="profile-nav-item">Избранное</a>
					<?php endif; ?>
				</div>

				<!-- Блок "Обо мне" -->
				<div class="profile-block">
					<div class="profile-section-header">
						<h2>Обо мне</h2>
						<?php if ($isOwnProfile): ?>
							<a href="#" class="edit-link">
								<img src="../assets/img/icons/edit.svg" alt="Редактировать">
							</a>
						<?php endif; ?>
					</div>

					<div class="profile-bio">
						<?php if (!empty($user['description'])): ?>
							<?php
							// Преобразуем описание в параграфы
							$paragraphs = explode("\n", $user['description']);
							foreach ($paragraphs as $paragraph):
								if (trim($paragraph)): // Проверяем, что параграф не пустой
							?>
									<p><?php echo htmlspecialchars($paragraph); ?></p>
							<?php
								endif;
							endforeach;
							?>
						<?php else: ?>
							<p class="no-bio-text">
								<?php echo $isOwnProfile ? 'Добавьте информацию о себе, чтобы другие пользователи могли узнать вас лучше.' : 'Пользователь еще не добавил информацию о себе.'; ?>
							</p>
						<?php endif; ?>
					</div>

					<div class="profile-details">
						<div class="profile-detail-item">
							<div class="detail-icon education-icon"></div>
							<div class="detail-text">
								<?php if (!empty($user['education'])): ?>
									<?php echo htmlspecialchars($user['education']); ?>
								<?php else: ?>
									<?php echo $isOwnProfile ? 'Укажите ваше образование' : 'Не указано'; ?>
								<?php endif; ?>
							</div>
						</div>
						<div class="profile-detail-item">
							<div class="detail-icon work-icon"></div>
							<div class="detail-text">
								<?php if (!empty($user['occupation'])): ?>
									<?php echo htmlspecialchars($user['occupation']); ?>
								<?php else: ?>
									<?php echo $isOwnProfile ? 'Укажите вашу работу' : 'Не указано'; ?>
								<?php endif; ?>
							</div>
						</div>
						<div class="profile-detail-item">
							<div class="detail-icon hobby-icon"></div>
							<div class="detail-text">
								<?php if (!empty($user['interests'])): ?>
									<?php echo htmlspecialchars($user['interests']); ?>
								<?php else: ?>
									<?php echo $isOwnProfile ? 'Укажите ваши интересы' : 'Не указано'; ?>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<?php if ($isOwnProfile || !empty($ratings) || ($can_review && isset($_SESSION['user_id']) && !$isOwnProfile)): ?>
			<!-- Блок с отзывами -->
			<div class="reviews-section">
				<!-- Список отзывов -->
				<?php if (!empty($ratings)): ?>
					<h3 class="reviews-title">Отзывы</h3>
					<div class="reviews-list">
						<?php foreach ($ratings as $review): ?>
							<div class="review-item">
								<div class="review-item-header">
									<div class="reviewer-info">
										<?php if ($review['avatar_image']): ?>
											<img src="<?php echo API_URL; ?>/users/get_avatar.php?id=<?php echo $review['rater_id']; ?>" alt="Аватар"
												class="reviewer-avatar">
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

				<!-- Форма для отправки отзыва -->
				<?php if ($can_review && !$isOwnProfile && isset($_SESSION['user_id'])): ?>
					<div class="review-block">
						<div class="review-header">
							<h3>Написать отзыв</h3>
							<div class="review-stars">
								<!-- Звёзды -->
								<?php for ($i = 1; $i <= 5; $i++): ?>
									<span class="star" data-value="<?php echo $i; ?>">
										<img src="../assets/img/icons/star-void.svg" alt="Звезда" class="star-icon">
									</span>
								<?php endfor; ?>
							</div>
						</div>
						<form id="review-form" method="post">
							<input type="hidden" name="user_id" value="<?php echo (int) $profile_id; ?>">
							<input type="hidden" name="rating" value="0">
							<textarea name="comment" rows="5" class="review-textarea"
								placeholder="Напишите свои впечатления об этом владельце" required></textarea>
							<button type="submit" class="btn btn-primary">Отправить</button>
						</form>
					</div>
				<?php elseif (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $profile_id && !$can_review): ?>
					<div class="profile-review-info">
						<p>Вы уже оставили отзыв на этого владельца.</p>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php
require_once '../includes/footer.php';
?>