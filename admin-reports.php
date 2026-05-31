<?php
require_once __DIR__ . '/bootstrap.php';
AdminAccess::requireAdmin();

$pageTitle = 'Жалобы на объявления';
$additionalCss = ['assets/css/admin.css'];
$additionalJs = ['assets/js/admin-reports.js'];

$service = new AdminReportsService();
$reports = $service->getReports();
$csrfToken = Csrf::token();

include __DIR__ . '/includes/header.php';
?>
<div class="container admin-page">
	<div class="admin-nav">
		<a href="admin" class="admin-nav-item">Верификация</a>
		<a href="admin-reports" class="admin-nav-item active">Жалобы</a>
	</div>

	<div class="admin-list">
		<?php if (empty($reports)): ?>
			<div class="admin-empty">Жалоб нет.</div>
		<?php else: ?>
			<?php foreach ($reports as $report): ?>
				<div class="admin-request-card">
					<div class="admin-request-body">
						<div class="admin-request-header">
							<div>
								<h2><?php echo htmlspecialchars($report['reason_label']); ?></h2>
							</div>
						</div>
						<div class="admin-request-grid admin-request-grid--reports">
							<a href="profile/housing?id=<?php echo (int) $report['listing_id']; ?>" class="admin-request-meta-item admin-request-meta-item--wide admin-request-meta-item--link"><span>Объявление</span><strong><?php echo htmlspecialchars($report['listing_title']); ?></strong></a>
							<div class="admin-request-meta-item"><span>Отправлена</span><strong><?php echo date('d.m.Y H:i', strtotime((string) $report['created_at'])); ?></strong></div>
						</div>
						<div class="admin-report-participants">
							<a href="profile/about?id=<?php echo (int) $report['reporter_id']; ?>" class="admin-report-person admin-report-person--link">
								<span class="admin-report-person-label">Жалоба от</span>
								<div class="admin-report-person-value"><?php echo htmlspecialchars($report['reporter_first_name'] . ' ' . $report['reporter_last_name']); ?> <span>(<?php echo htmlspecialchars($report['reporter_email']); ?>)</span></div>
							</a>
							<a href="profile/about?id=<?php echo (int) $report['listing_owner_id']; ?>" class="admin-report-person admin-report-person--link">
								<span class="admin-report-person-label">Владелец объявления</span>
								<div class="admin-report-person-value"><?php echo htmlspecialchars($report['listing_owner_first_name'] . ' ' . $report['listing_owner_last_name']); ?> <span>(<?php echo htmlspecialchars($report['listing_owner_email']); ?>)</span></div>
							</a>
						</div>
						<?php if (!empty($report['details'])): ?>
							<div class="admin-note-box">
								<strong>Подробности</strong><br>
								<?php echo nl2br(htmlspecialchars($report['details'])); ?>
							</div>
						<?php endif; ?>
						<div class="admin-actions-row">
							<form method="post" action="api/admin/delete_report.php" class="admin-inline-form" id="dismiss-form-<?php echo (int) $report['id']; ?>">
								<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
								<input type="hidden" name="report_id" value="<?php echo (int) $report['id']; ?>">
								<button type="button" class="btn admin-action-btn admin-action-btn--primary" onclick="openConfirmModal('Удалить жалобу', 'Вы уверены, что хотите удалить эту жалобу?', 'Удалить', document.getElementById('dismiss-form-<?php echo (int) $report['id']; ?>'))">Удалить жалобу</button>
							</form>
							<form method="post" action="api/admin/delete_listing_by_report.php" class="admin-inline-form" id="delete-form-<?php echo (int) $report['id']; ?>">
								<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
								<input type="hidden" name="report_id" value="<?php echo (int) $report['id']; ?>">
								<input type="hidden" name="listing_id" value="<?php echo (int) $report['listing_id']; ?>">
								<button type="button" class="btn admin-action-btn admin-action-btn--danger" onclick="openConfirmModal('Удалить объявление', 'Вы уверены, что хотите удалить это объявление? Это действие нельзя отменить.', 'Удалить', document.getElementById('delete-form-<?php echo (int) $report['id']; ?>'))">Удалить объявление</button>
							</form>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>

<!-- Модалка подтверждения -->
<div id="admin-confirm-modal" class="modal-overlay" data-modal-width="400px">
	<div class="modal">
		<div class="modal-header">
			<h2 class="modal-title" id="confirm-modal-title">Подтвердите действие</h2>
			<button type="button" class="modal-close">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
		</div>
		<div class="modal-body">
			<p id="confirm-modal-message">Вы уверены?</p>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn-cancel">Отмена</button>
			<button type="button" class="btn-save" id="confirm-modal-action">Подтвердить</button>
		</div>
	</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
