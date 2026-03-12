<?php
class AdminVerificationService
{
	private Database $db;

	public function __construct()
	{
		$this->db = new Database();
		AdminAccess::ensureSchema($this->db);
	}

	public function getStats(): array
	{
		return [
			'pending' => (int) $this->db->getValue("SELECT COUNT(*) FROM verification_requests WHERE status = 'pending'"),
			'approved' => (int) $this->db->getValue("SELECT COUNT(*) FROM verification_requests WHERE status = 'approved'"),
			'rejected' => (int) $this->db->getValue("SELECT COUNT(*) FROM verification_requests WHERE status = 'rejected'"),
			'verified_users' => (int) $this->db->getValue("SELECT COUNT(*) FROM users WHERE is_verify = 1"),
		];
	}

	public function getRequests(string $status = 'pending'): array
	{
		$allowed = ['pending', 'approved', 'rejected'];
		if (!in_array($status, $allowed, true)) {
			$status = 'pending';
		}

		$stmt = $this->db->prepareAndExecute(
			"SELECT vr.id, vr.user_id, vr.document_mime, vr.status, vr.admin_note, vr.reviewed_at, vr.created_at,
                    u.first_name, u.last_name, u.email, u.is_verify,
                    reviewer.first_name AS reviewer_first_name, reviewer.last_name AS reviewer_last_name
             FROM verification_requests vr
             JOIN users u ON u.id = vr.user_id
             LEFT JOIN users reviewer ON reviewer.id = vr.reviewed_by
             WHERE vr.status = ?
             ORDER BY CASE WHEN vr.status = 'pending' THEN vr.created_at END ASC, vr.updated_at DESC",
			's',
			[$status]
		);

		return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	}

	public function getLatestUserRequest(int $userId): ?array
	{
		$stmt = $this->db->prepareAndExecute(
			'SELECT id, status, admin_note, created_at, reviewed_at FROM verification_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 1',
			'i',
			[$userId]
		);
		$row = $stmt->get_result()->fetch_assoc();
		return $row ?: null;
	}

	public function submitRequest(int $userId, array $file): void
	{
		if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			throw new Exception('Загрузите фото документа');
		}

		$allowed = ['image/jpeg', 'image/png', 'image/webp'];
		$mime = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
		if (!in_array($mime, $allowed, true)) {
			throw new Exception('Разрешены только JPG, PNG и WEBP');
		}

		if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
			throw new Exception('Размер файла не должен превышать 5 МБ');
		}

		$imageData = file_get_contents($file['tmp_name']);
		if ($imageData === false) {
			throw new Exception('Не удалось прочитать файл');
		}

		$this->db->prepareAndExecute('UPDATE verification_requests SET status = ?, admin_note = ?, reviewed_by = NULL, reviewed_at = NULL WHERE user_id = ? AND status = ?', 'ssis', ['rejected', 'Заявка заменена новой', $userId, 'pending']);
		$this->db->prepareAndExecute('INSERT INTO verification_requests (user_id, document_image, document_mime, status) VALUES (?, ?, ?, ?)', 'ibss', [$userId, $imageData, $mime, 'pending']);
	}

	public function moderate(int $requestId, string $status, ?string $adminNote, int $adminId): void
	{
		if (!in_array($status, ['approved', 'rejected'], true)) {
			throw new Exception('Недопустимый статус');
		}

		$stmt = $this->db->prepareAndExecute('SELECT id, user_id FROM verification_requests WHERE id = ?', 'i', [$requestId]);
		$request = $stmt->get_result()->fetch_assoc();
		if (!$request) {
			throw new Exception('Заявка не найдена');
		}

		$note = $adminNote !== null ? trim($adminNote) : null;
		$this->db->prepareAndExecute('UPDATE verification_requests SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?', 'ssii', [$status, $note, $adminId, $requestId]);
		$this->db->prepareAndExecute('UPDATE users SET is_verify = ? WHERE id = ?', 'ii', [$status === 'approved' ? 1 : 0, (int) $request['user_id']]);
	}

	public function outputDocument(int $requestId): void
	{
		$stmt = $this->db->prepareAndExecute('SELECT document_image, document_mime FROM verification_requests WHERE id = ?', 'i', [$requestId]);
		$stmt->store_result();
		if ($stmt->num_rows === 0) {
			http_response_code(404);
			exit;
		}

		$image = null;
		$mime = null;
		$stmt->bind_result($image, $mime);
		$stmt->fetch();
		if ($image === null) {
			http_response_code(404);
			exit;
		}

		header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		echo $image;
		exit;
	}
}
