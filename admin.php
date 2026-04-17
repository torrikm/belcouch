<?php
require_once __DIR__ . '/bootstrap.php';
AdminAccess::requireAdmin();

$statusLabels = [
	'pending' => 'На проверке',
	'approved' => 'Одобрена',
	'rejected' => 'Отклонена',
	'' => 'Нет',
];

$roleLabels = [
	'user' => 'Пользователь',
	'admin' => 'Администратор',
];

$pageTitle = 'Админ-панель';
$additionalCss = ['assets/css/admin.css'];
$additionalJs = ['assets/js/admin-verification.js'];

$service = new AdminVerificationService();
$status = isset($_GET['status']) ? (string) $_GET['status'] : 'pending';
$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'requests';
$userEmail = trim((string) ($_GET['user_email'] ?? ''));
$stats = $service->getStats();
$requests = $service->getRequests($status);
$verifiedUsers = $service->getVerifiedUsers();
$manualUser = $userEmail !== '' ? $service->findUserByEmail($userEmail) : null;
$csrfToken = Csrf::token();

include __DIR__ . '/includes/header.php';
?>
<div class="container admin-page">
	<div class="admin-nav">
		<a href="admin" class="admin-nav-item active">Верификация</a>
		<a href="admin-reports" class="admin-nav-item">Жалобы</a>
	</div>

	<div class="admin-filters">
		<a href="admin?tab=verified_users" class="admin-filter<?php echo $tab === 'verified_users' ? ' active' : ''; ?>">Верифицированные пользователи<span class="admin-filter-badge"><?php echo (int) $stats['verified_users']; ?></span></a>
		<a href="admin?tab=requests" class="admin-filter<?php echo $tab === 'requests' ? ' active' : ''; ?>">Ручная верификация</a>
	</div>

	<?php if ($tab === 'verified_users'): ?>
		<div class="admin-list">
			<?php if (empty($verifiedUsers)): ?>
				<div class="admin-empty">Верифицированных пользователей нет.</div>
			<?php else: ?>
				<?php foreach ($verifiedUsers as $user): ?>
					<div class="admin-request-card">
						<div class="admin-request-body">
							<div class="admin-request-header">
								<div>
									<h2><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?></h2>
									<p><?php echo htmlspecialchars($user['email']); ?></p>
								</div>
								<span class="admin-status admin-status--approved">Верифицирован</span>
							</div>
							<div class="admin-request-grid">
								<div><strong>ID пользователя:</strong> <?php echo (int) $user['id']; ?></div>
								<div><strong>Роль:</strong> <?php echo htmlspecialchars($roleLabels[(string) $user['role']] ?? (string) $user['role']); ?></div>
								<div><strong>Последняя верификация:</strong> <?php echo htmlspecialchars((string) ($user['latest_reviewed_at'] ?: 'ещё нет')); ?></div>
							</div>
							<?php if (!empty($user['latest_admin_note'])): ?>
								<div class="admin-note-box"><?php echo nl2br(htmlspecialchars($user['latest_admin_note'])); ?></div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	<?php else: ?>
		<div class="admin-toolbar">
			<div class="admin-toolbar-label">Показывать заявки:</div>
			<form method="get" action="admin" class="admin-status-form">
				<input type="hidden" name="tab" value="requests">
				<?php if ($userEmail !== ''): ?>
					<input type="hidden" name="user_email" value="<?php echo htmlspecialchars($userEmail); ?>">
				<?php endif; ?>
				<select name="status" class="form-control admin-status-select" onchange="this.form.submit()">
					<option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>На проверке (<?php echo (int) $stats['pending']; ?>)</option>
					<option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Одобренные (<?php echo (int) $stats['approved']; ?>)</option>
					<option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Отклонённые (<?php echo (int) $stats['rejected']; ?>)</option>
				</select>
			</form>
		</div>

		<div class="admin-manual-card">
			<div class="admin-manual-header">
				<div>
					<h2>Ручная верификация</h2>
					<p>Найдите пользователя по email и вручную выдайте или снимите верификацию.</p>
				</div>
			</div>
			<form method="get" action="admin" class="admin-user-search-form">
				<input type="hidden" name="tab" value="requests">
				<input type="email" name="user_email" class="admin-input" placeholder="Введите email пользователя" value="<?php echo htmlspecialchars($userEmail); ?>" required>
				<button type="submit" class="btn btn-primary">Найти</button>
			</form>

			<?php if ($userEmail !== ''): ?>
				<?php if ($manualUser): ?>
					<div class="admin-manual-user">
						<div class="admin-manual-user-grid">
							<div><strong>Пользователь:</strong> <?php echo htmlspecialchars(trim($manualUser['first_name'] . ' ' . $manualUser['last_name'])); ?></div>
							<div><strong>Email:</strong> <?php echo htmlspecialchars($manualUser['email']); ?></div>
							<div><strong>Роль:</strong> <?php echo htmlspecialchars($roleLabels[(string) $manualUser['role']] ?? (string) $manualUser['role']); ?></div>
							<div><strong>Текущая верификация:</strong> <?php echo (int) $manualUser['is_verify'] === 1 ? 'Да' : 'Нет'; ?></div>
							<div><strong>Последняя заявка:</strong> <?php echo htmlspecialchars($statusLabels[(string) ($manualUser['latest_verification_status'] ?? '')] ?? (string) ($manualUser['latest_verification_status'] ?? 'Нет')); ?></div>
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
								<span class="admin-status admin-status--<?php echo htmlspecialchars($request['status']); ?>"><?php echo htmlspecialchars($statusLabels[(string) $request['status']] ?? (string) $request['status']); ?></span>
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
	<?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>