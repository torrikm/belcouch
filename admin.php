<?php
require_once __DIR__ . '/bootstrap.php';
AdminAccess::requireAdmin();

$pageTitle = 'Админ-панель';
$additionalCss = ['assets/css/admin.css'];
$additionalJs = ['assets/js/admin-verification.js'];

$service = new AdminVerificationService();
$status = isset($_GET['status']) ? (string) $_GET['status'] : 'pending';
$userEmail = trim((string) ($_GET['user_email'] ?? ''));
$stats = $service->getStats();
$requests = $service->getRequests($status);
$manualUser = $userEmail !== '' ? $service->findUserByEmail($userEmail) : null;
$csrfToken = Csrf::token();

include __DIR__ . '/includes/header.php';
?>
<div class="container admin-page">
	<div class="admin-hero">
		<div>
			<h1 class="admin-title">Панель верификации пользователей</h1>
			<p class="admin-subtitle">Проверка паспортных фото, ручное подтверждение и отклонение заявок.</p>
		</div>
		<div class="admin-meta">Вы вошли как администратор</div>
	</div>

	<div class="admin-stats">
		<div class="admin-stat-card"><span class="admin-stat-value"><?php echo (int) $stats['pending']; ?></span><span class="admin-stat-label">На проверке</span></div>
		<div class="admin-stat-card"><span class="admin-stat-value"><?php echo (int) $stats['approved']; ?></span><span class="admin-stat-label">Одобрено</span></div>
		<div class="admin-stat-card"><span class="admin-stat-value"><?php echo (int) $stats['rejected']; ?></span><span class="admin-stat-label">Отклонено</span></div>
		<div class="admin-stat-card"><span class="admin-stat-value"><?php echo (int) $stats['verified_users']; ?></span><span class="admin-stat-label">Верифицированных пользователей</span></div>
	</div>

	<div class="admin-filters">
		<a href="admin?status=pending" class="admin-filter<?php echo $status === 'pending' ? ' active' : ''; ?>">На проверке</a>
		<a href="admin?status=approved" class="admin-filter<?php echo $status === 'approved' ? ' active' : ''; ?>">Одобренные</a>
		<a href="admin?status=rejected" class="admin-filter<?php echo $status === 'rejected' ? ' active' : ''; ?>">Отклонённые</a>
	</div>

	<div class="admin-manual-card">
		<div class="admin-manual-header">
			<div>
				<h2>Ручная верификация</h2>
				<p>Найдите пользователя по email и вручную выдайте или снимите верификацию.</p>
			</div>
		</div>
		<form method="get" action="admin" class="admin-user-search-form">
			<input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
			<input type="email" name="user_email" class="admin-input" placeholder="Введите email пользователя" value="<?php echo htmlspecialchars($userEmail); ?>" required>
			<button type="submit" class="btn btn-primary">Найти</button>
		</form>

		<?php if ($userEmail !== ''): ?>
			<?php if ($manualUser): ?>
				<div class="admin-manual-user">
					<div class="admin-manual-user-grid">
						<div><strong>Пользователь:</strong> <?php echo htmlspecialchars(trim($manualUser['first_name'] . ' ' . $manualUser['last_name'])); ?></div>
						<div><strong>Email:</strong> <?php echo htmlspecialchars($manualUser['email']); ?></div>
						<div><strong>Роль:</strong> <?php echo htmlspecialchars((string) $manualUser['role']); ?></div>
						<div><strong>Текущая верификация:</strong> <?php echo (int) $manualUser['is_verify'] === 1 ? 'Да' : 'Нет'; ?></div>
						<div><strong>Последняя заявка:</strong> <?php echo htmlspecialchars((string) ($manualUser['latest_verification_status'] ?? 'нет')); ?></div>
						<div><strong>Последняя модерация:</strong> <?php echo htmlspecialchars((string) ($manualUser['latest_reviewed_at'] ?: 'ещё нет')); ?></div>
					</div>
					<form class="admin-manual-verification-form js-admin-manual-verification-form">
						<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
						<input type="hidden" name="user_id" value="<?php echo (int) $manualUser['id']; ?>">
						<textarea name="admin_note" class="admin-textarea" placeholder="Комментарий администратора"></textarea>
						<div class="admin-actions-row">
							<?php if ((int) $manualUser['is_verify'] === 1): ?>
								<button type="submit" name="action" value="unverify" class="btn btn-outline-primary">Снять верификацию</button>
							<?php else: ?>
								<button type="submit" name="action" value="verify" class="btn btn-primary">Выдать верификацию</button>
							<?php endif; ?>
						</div>
					</form>
				</div>
			<?php else: ?>
				<div class="admin-empty">Пользователь с таким email не найден.</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<div class="admin-list">
		<?php if (empty($requests)): ?>
			<div class="admin-empty">В этом разделе пока нет заявок.</div>
		<?php else: ?>
			<?php foreach ($requests as $request): ?>
				<div class="admin-request-card <?php echo $request['status'] === 'pending' ? '' : 'not-pending'; ?>">
					<?php if ($request['status'] === 'pending'): ?>
						<div class="admin-request-preview">
							<img src="<?php echo API_URL; ?>/admin/get_verification_document.php?id=<?php echo (int) $request['id']; ?>" alt="Документ пользователя">
						</div>
					<?php endif; ?>
					<div class="admin-request-body">
						<div class="admin-request-header">
							<div>
								<h2><?php echo htmlspecialchars(trim($request['first_name'] . ' ' . $request['last_name'])); ?></h2>
								<p><?php echo htmlspecialchars($request['email']); ?></p>
							</div>
							<span class="admin-status admin-status--<?php echo htmlspecialchars($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span>
						</div>
						<div class="admin-request-grid">
							<div><strong>ID заявки:</strong> <?php echo (int) $request['id']; ?></div>
							<div><strong>ID пользователя:</strong> <?php echo (int) $request['user_id']; ?></div>
							<div><strong>Отправлена:</strong> <?php echo htmlspecialchars((string) $request['created_at']); ?></div>
							<div><strong>Проверена:</strong> <?php echo htmlspecialchars((string) ($request['reviewed_at'] ?: 'ещё нет')); ?></div>
						</div>
						<?php if (!empty($request['admin_note'])): ?>
							<div class="admin-note-box"><?php echo nl2br(htmlspecialchars($request['admin_note'])); ?></div>
						<?php endif; ?>
						<?php if ($request['status'] === 'pending'): ?>
							<form class="admin-moderation-form js-admin-moderation-form">
								<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
								<input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
								<textarea name="admin_note" class="admin-textarea" placeholder="Комментарий администратора"></textarea>
								<div class="admin-actions-row">
									<button type="submit" name="status" value="approved" class="btn btn-primary">Подтвердить</button>
									<button type="submit" name="status" value="rejected" class="btn btn-outline-primary">Отклонить</button>
								</div>
							</form>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>