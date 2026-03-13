<?php
require_once __DIR__ . '/bootstrap.php';

$pageTitle = 'Поддержка';
$additionalCss = ['assets/css/support.css'];
$additionalJs = ['assets/js/support.js'];

$supportService = new SupportService();
$supportChatAdmin = $supportService->getSupportChatAdmin();
$csrfToken = Csrf::token();
$currentUser = null;

if (isset($_SESSION['user_id'])) {
	$db = new Database();
	$stmt = $db->prepareAndExecute('SELECT first_name, last_name, email FROM users WHERE id = ?', 'i', [(int) $_SESSION['user_id']]);
	$currentUser = $stmt->get_result()->fetch_assoc();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
	<div class="support-page">
		<div class="support-hero">
			<div>
				<h1>Поддержка BelCouch</h1>
				<p>Если у вас возник вопрос по верификации, профилю, бронированиям или сообщениям — напишите нам.</p>
			</div>
			<?php if ($supportChatAdmin && isset($_SESSION['user_id']) && (int) $supportChatAdmin['id'] !== (int) $_SESSION['user_id']): ?>
				<a href="chat?user_id=<?php echo (int) $supportChatAdmin['id']; ?>&support=1" class="btn btn-secondary">Открыть чат с поддержкой</a>
			<?php endif; ?>
		</div>

		<div class="support-layout">
			<div class="support-card">
				<h2>Написать в поддержку</h2>
				<form id="support-form" class="support-form">
					<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
					<div class="form-group">
						<label for="support-name">Ваше имя</label>
						<input type="text" id="support-name" name="name" class="form-control" value="<?php echo htmlspecialchars(trim((string) (($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')))); ?>" required>
					</div>
					<div class="form-group">
						<label for="support-email">Email для ответа</label>
						<input type="email" id="support-email" name="email" class="form-control" value="<?php echo htmlspecialchars((string) ($currentUser['email'] ?? '')); ?>" required>
					</div>
					<div class="form-group">
						<label for="support-subject">Тема</label>
						<input type="text" id="support-subject" name="subject" class="form-control" placeholder="Например: Проблема с верификацией" required>
					</div>
					<div class="form-group">
						<label for="support-message">Сообщение</label>
						<textarea id="support-message" name="message" class="form-control" rows="7" placeholder="Опишите проблему как можно подробнее" required></textarea>
					</div>
					<button type="submit" class="btn btn-primary">Отправить обращение</button>
				</form>
			</div>

			<div class="support-card support-card--aside">
				<h2>Другие способы связи</h2>
				<p class="support-card-intro">Мы рассматриваем обращения по почте и в чате поддержки.</p>
				<ul class="support-list">
					<li class="support-list-item">
						<span class="support-list-item__label">Email поддержки</span>
						<strong class="support-list-item__value"><a href="mailto:<?php echo htmlspecialchars((string) SUPPORT_EMAIL); ?>"><?php echo htmlspecialchars((string) SUPPORT_EMAIL); ?></a></strong>
					</li>
					<?php if ($supportChatAdmin): ?>
						<li class="support-list-item">
							<span class="support-list-item__label">Чат поддержки</span>
							<span class="support-list-item__text">Доступен в личных сообщениях после входа в аккаунт.</span>
						</li>
					<?php else: ?>
						<li class="support-list-item support-list-item--muted">
							<span class="support-list-item__label">Чат поддержки</span>
							<span class="support-list-item__text">Пока недоступен, потому что не найден администратор для переписки.</span>
						</li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>