<?php

/**
 * Общий компонент шапки профиля пользователя
 * Используется на всех страницах профиля для отображения основной информации
 * 
 * Ожидает, что переменные $user, $isOwnProfile и $starRating уже определены
 */

// Проверка наличия необходимых данных
if (!isset($user) || !isset($isOwnProfile) || !isset($starRating)) {
	die('Ошибка: Отсутствуют необходимые данные для отображения профиля');
}

// Проверка наличия функции расчета возраста
if (!function_exists('calculateAge')) {
	/**
	 * Функция для расчета возраста по дате рождения
	 */
	function calculateAge($birthdate)
	{
		if (empty($birthdate)) {
			return null;
		}

		$today = new DateTime();
		$birth = new DateTime($birthdate);
		$interval = $today->diff($birth);
		return $interval->y;
	}
}

// Проверка наличия функции склонения
if (!function_exists('plural_form')) {
	/**
	 * Функция для склонения слов в зависимости от числа
	 */
	function plural_form($number, $forms)
	{
		$cases = [2, 0, 1, 1, 1, 2];
		$index = ($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)];
		return $forms[$index];
	}
}

// Определяем активную страницу для навигации
$current_page = basename($_SERVER['SCRIPT_NAME'], '.php');
$verificationRequest = null;
$verificationCsrfToken = null;

if ($isOwnProfile && class_exists('AdminVerificationService') && class_exists('Csrf')) {
	$verificationService = new AdminVerificationService();
	$verificationRequest = $verificationService->getLatestUserRequest((int) $user['id']);
	$verificationCsrfToken = Csrf::token();
}
?>

<div class="profile-header">
	<div class="profile-avatar-container">
		<?php if ($user['avatar_image']): ?>
			<img src="<?php echo API_URL; ?>/users/get_avatar.php?id=<?php echo $user['id']; ?>"
				alt="Аватар" class="profile-avatar">
		<?php else: ?>
			<div class="profile-avatar profile-avatar-placeholder">
				<?php echo mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1); ?>
			</div>
		<?php endif; ?>

		<?php if ($user['is_verify']): ?>
			<div class="verified-badge">
				<img src="<?php echo isset($root_path) ? $root_path : '../'; ?>assets/img/icons/verified.svg"
					alt="Проверенный пользователь">
			</div>
		<?php endif; ?>
	</div>

	<div class="profile-info">
		<h1 class="profile-name">
			<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
		</h1>

		<?php if (!empty($user['city'])): ?>
			<div class="profile-location">
				<i class="location-icon"></i>
				<span class="profile-city"><?php echo htmlspecialchars($user['city']); ?></span>
			</div>
		<?php endif; ?>

		<?php
		$hasGender = isset($user['gender']) && $user['gender'] !== 'not_specified';
		$age = !empty($user['birthdate']) ? calculateAge($user['birthdate']) : null;
		$hasAge = !empty($age);
		?>
		<?php if ($hasGender || $hasAge): ?>
			<div class="profile-meta">
				<?php if ($hasGender): ?>
					<div class="profile-gender">
						<img src="<?php echo isset($root_path) ? $root_path : '../'; ?>assets/img/icons/<?php echo $user['gender']; ?>.svg"
							alt="<?php echo $user['gender'] === 'male' ? 'Мужской' : 'Женский'; ?>" class="gender-icon">
					</div>
				<?php endif; ?>

				<?php if ($hasAge): ?>
					<div class="profile-age">
						<span><?php echo $age; ?> <?php echo plural_form($age, ['год', 'года', 'лет']); ?></span>
					</div>
				<?php endif; ?>
			</div>
			<div class="divider"></div>
		<?php endif; ?>
		<div class="profile-rating-container">
			<div class="profile-rating">
				<div class="rating-score"><?php echo number_format($user['avg_rating'] ?? 0, 1, ',', ''); ?></div>
				<div class="rating-stars">
					<?php for ($i = 1; $i <= 5; $i++): ?>
						<?php if ($i <= $starRating): ?>
							<img src="<?php echo isset($root_path) ? $root_path : '../'; ?>assets/img/icons/star-filled.svg"
								alt="★" class="star-icon">
						<?php else: ?>
							<img src="<?php echo isset($root_path) ? $root_path : '../'; ?>assets/img/icons/star-empty.svg"
								alt="☆" class="star-icon">
						<?php endif; ?>
					<?php endfor; ?>
				</div>
				<?php if (isset($user['rating_count']) && $user['rating_count'] > 0): ?>
					<span class="rating-count" style="margin-left: 10px;">(<?php echo $user['rating_count']; ?>
						<?php echo plural_form($user['rating_count'], ['отзыв', 'отзыва', 'отзывов']); ?>)
					</span>
				<?php endif; ?>
			</div>
		</div>

		<?php if ($isOwnProfile): ?>
			<div class="profile-actions">
				<button id="edit-profile-btn" class="btn btn-primary">Редактировать</button>
				<a href="<?php echo isset($root_path) ? $root_path : '../'; ?>logout" class="btn btn-outline-primary"
					style="width: 100%; text-align: center;">Выйти</a>
			</div>
			<div class="verification-card">
				<h3>Подтверждение личности</h3>
				<p>
					<?php if (!empty($user['is_verify'])): ?>
						Ваш профиль подтверждён. Значок верификации уже отображается для других пользователей.
					<?php elseif (($verificationRequest['status'] ?? '') === 'pending'): ?>
						Ваша заявка находится на ручной проверке администратором.
					<?php elseif (($verificationRequest['status'] ?? '') === 'rejected'): ?>
						Заявка была отклонена. Вы можете отправить новое фото документов.
					<?php else: ?>
						Загрузите фото паспорта или другого документа, чтобы получить верификацию профиля.
					<?php endif; ?>
				</p>
				<?php if (!empty($user['is_verify'])): ?>
					<span class="verification-status verification-status--approved">Профиль подтверждён</span>
				<?php elseif (!empty($verificationRequest['status'])): ?>
					<span class="verification-status verification-status--<?php echo htmlspecialchars($verificationRequest['status']); ?>">
						Статус: <?php echo htmlspecialchars($verificationRequest['status']); ?>
					</span>
				<?php endif; ?>
				<?php if (empty($user['is_verify'])): ?>
					<button type="button" class="btn btn-primary verification-card-button" onclick="window.App && window.App.modal ? window.App.modal.open('verification-request-modal') : null;">
						<?php echo ($verificationRequest['status'] ?? '') === 'rejected' ? 'Отправить заново' : 'Подать заявку'; ?>
					</button>
				<?php endif; ?>
			</div>
		<?php else: ?>
			<div class="profile-actions">
				<a href="<?php echo isset($root_path) ? $root_path : '../'; ?>chat?user_id=<?php echo (int) $user['id']; ?>" class="btn btn-message">Написать сообщение</a>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- Модальное окно для редактирования профиля -->
<?php if ($isOwnProfile): ?>
	<div id="edit-profile-modal" class="modal-overlay">
		<div class="modal">
			<div class="modal-header">
				<h2 class="modal-title">Редактирование профиля</h2>
				<button type="button" class="modal-close">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			<div class="modal-body">
				<form id="edit-profile-form" enctype="multipart/form-data">
					<div class="avatar-upload-container">
						<?php if ($user['avatar_image']): ?>
							<img src="<?php echo API_URL; ?>/users/get_avatar.php?id=<?php echo $user['id']; ?>" alt="Аватар" class="current-avatar">
						<?php else: ?>
							<div class="current-avatar">
								<?php echo mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1); ?>
							</div>
						<?php endif; ?>
						<label class="avatar-upload-btn" for="avatar-upload">Загрузить фото</label>
						<input type="file" id="avatar-upload" name="avatar" accept="image/*">
					</div>

					<div class="profile-details-form">
						<div class="form-group">
							<label for="first_name">Имя</label>
							<input type="text" id="first_name" name="first_name" class="form-control"
								value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
						</div>

						<div class="form-group">
							<label for="last_name">Фамилия</label>
							<input type="text" id="last_name" name="last_name" class="form-control"
								value="<?php echo htmlspecialchars($user['last_name']); ?>">
						</div>

						<div class="form-group">
							<label for="gender">Пол</label>
							<select id="gender" name="gender" class="form-control">
								<option value="not_specified" <?php echo (!isset($user['gender']) || $user['gender'] === 'not_specified') ? 'selected' : ''; ?>>Не указано</option>
								<option value="male" <?php echo (isset($user['gender']) && $user['gender'] === 'male') ? 'selected' : ''; ?>>Мужской</option>
								<option value="female" <?php echo (isset($user['gender']) && $user['gender'] === 'female') ? 'selected' : ''; ?>>Женский</option>
							</select>
						</div>

						<div class="form-group">
							<label for="birth_date">Дата рождения</label>
							<input type="date" id="birth_date" name="birth_date" class="form-control"
								value="<?php echo htmlspecialchars($user['birth_date'] ?? ($user['birthdate'] ?? '')); ?>">
						</div>

						<div class="form-group">
							<label for="region_id">Область</label>
							<?php $regions = isset($regions) && is_array($regions) ? $regions : []; ?>
							<select id="region_id" name="region_id" class="form-control">
								<option value="">Выберите область</option>
								<?php foreach ($regions as $region): ?>
									<option value="<?php echo $region['id']; ?>" <?php echo (isset($user['region_id']) && $user['region_id'] == $region['id']) ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($region['name']); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="form-group">
							<label for="city">Город</label>
							<input type="text" id="city" name="city" class="form-control"
								value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>"
								placeholder="Укажите ваш город">
						</div>

						<div class="form-group form-group-full">
							<label for="email">Email</label>
							<input type="email" id="email" name="email" class="form-control"
								value="<?php echo htmlspecialchars($user['email']); ?>" required>
						</div>

						<div class="form-group form-group-full">
							<button type="button" id="change-password-btn" class="btn-password-change">Изменить
								пароль</button>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn-cancel">Отмена</button>
				<button type="submit" form="edit-profile-form" class="btn-save">Сохранить</button>
			</div>
		</div>
	</div>

	<!-- Модальное окно для редактирования описания -->
	<div id="edit-bio-modal" class="modal-overlay">
		<div class="modal">
			<div class="modal-header">
				<h2 class="modal-title">Редактирование информации о себе</h2>
				<button type="button" class="modal-close">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			<div class="modal-body">
				<form id="edit-bio-form">
					<div class="form-group">
						<label for="description">О себе</label>
						<textarea id="description" name="description" class="form-control" rows="6"
							maxlength="800"
							placeholder="Расскажите немного о себе..."><?php echo htmlspecialchars($user['description'] ?? ''); ?></textarea>
						<div class="bio-length-hint">
							<span id="description-length-value"><?php echo mb_strlen((string) ($user['description'] ?? '')); ?></span>/800
						</div>
					</div>

					<div class="form-group">
						<label for="education">Образование</label>
						<input type="text" id="education" name="education" class="form-control"
							value="<?php echo htmlspecialchars($user['education'] ?? ''); ?>"
							placeholder="Например: БГТУ, Факультет информационных технологий">
					</div>

					<div class="form-group">
						<label for="occupation">Работа</label>
						<input type="text" id="occupation" name="occupation" class="form-control"
							value="<?php echo htmlspecialchars($user['occupation'] ?? ''); ?>"
							placeholder="Например: Дизайнер, Компания XYZ">
					</div>

					<div class="form-group">
						<label for="interests">Хобби и интересы</label>
						<input type="text" id="interests" name="interests" class="form-control"
							value="<?php echo htmlspecialchars($user['interests'] ?? ''); ?>"
							placeholder="Например: Танцы, рисование, чтение">
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn-cancel">Отмена</button>
				<button type="submit" form="edit-bio-form" class="btn-save">Сохранить</button>
			</div>
		</div>
	</div>

	<!-- Модальное окно для смены пароля -->
	<div id="change-password-modal" class="modal-overlay">
		<div class="modal">
			<div class="modal-header">
				<h2 class="modal-title">Изменение пароля</h2>
				<button type="button" class="modal-close">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			<div class="modal-body">
				<form id="change-password-form">
					<div class="form-group">
						<label for="current_password">Текущий пароль</label>
						<input type="password" id="current_password" name="current_password" class="form-control" required>
					</div>

					<div class="form-group">
						<label for="new_password">Новый пароль</label>
						<input type="password" id="new_password" name="new_password" class="form-control" required>
						<div class="password-requirements">
							Пароль должен содержать минимум 8 символов, включая буквы и цифры.
						</div>
					</div>

					<div class="form-group">
						<label for="confirm_password">Подтвердите новый пароль</label>
						<input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
						<div id="password-match-error" class="password-match-error">Пароли не совпадают</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn-cancel">Отмена</button>
				<button type="submit" form="change-password-form" class="btn-save">Сохранить</button>
			</div>
		</div>
	</div>

	<div id="verification-request-modal" class="modal-overlay">
		<div class="modal verification-modal">
			<div class="modal-header">
				<h2 class="modal-title">Подтверждение личности</h2>
				<button type="button" class="modal-close">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			<div class="modal-body">
				<form id="verification-request-form" enctype="multipart/form-data">
					<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) $verificationCsrfToken); ?>">
					<p class="verification-form-note">
						Загрузите чёткое фото документа. Изображение будет доступно только администраторам для ручной проверки.
					</p>
					<div class="form-group">
						<label for="document_photo">Фото документа</label>
						<div class="verification-upload">
							<label for="document_photo" class="verification-upload-btn">Выбрать файл</label>
							<span class="verification-upload-text" id="verification-file-name">Файл не выбран</span>
							<input type="file" id="document_photo" name="document_photo" class="verification-file-input" accept="image/jpeg,image/png,image/webp" required>
						</div>
						<div id="verification-preview-container" class="verification-preview verification-preview--hidden">
							<img id="verification-preview-image" src="" alt="Предпросмотр документа">
						</div>
						<div class="verification-upload-hint">
							Поддерживаются JPG, PNG и WEBP. Перед отправкой проверьте, что текст на документе читается.
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn-cancel">Отмена</button>
				<button type="submit" form="verification-request-form" class="btn-save">Отправить</button>
			</div>
		</div>
	</div>
<?php endif; ?>