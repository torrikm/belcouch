<?php
class AdminAccess
{
	public static function ensureSchema(?Database $db = null): void
	{
		$database = $db ?: new Database();
		$columnCheck = $database->prepareAndExecute(
			"SELECT COUNT(*) AS total
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'role'",
			's',
			[DB_NAME]
		);
		$columnExists = (int) ($columnCheck->get_result()->fetch_assoc()['total'] ?? 0) > 0;
		if (!$columnExists) {
			$database->query("ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') NOT NULL DEFAULT 'user'");
		}
		$database->query("CREATE TABLE IF NOT EXISTS verification_requests (
            id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id int(11) NOT NULL,
            document_image longblob NOT NULL,
            document_mime varchar(100) NOT NULL,
            status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            admin_note text DEFAULT NULL,
            reviewed_by int(11) DEFAULT NULL,
            reviewed_at timestamp NULL DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        )");
	}

	public static function refreshSessionRole(): void
	{
		if (empty($_SESSION['user_id'])) {
			$_SESSION['user_role'] = 'guest';
			$_SESSION['is_admin'] = false;
			return;
		}

		$db = new Database();
		self::ensureSchema($db);
		$stmt = $db->prepareAndExecute('SELECT role FROM users WHERE id = ?', 'i', [(int) $_SESSION['user_id']]);
		$row = $stmt->get_result()->fetch_assoc();
		$_SESSION['user_role'] = $row['role'] ?? 'user';
		$_SESSION['is_admin'] = ($_SESSION['user_role'] === 'admin');
	}

	public static function isAdmin(): bool
	{
		if (isset($_SESSION['is_admin'])) {
			return (bool) $_SESSION['is_admin'];
		}

		self::refreshSessionRole();
		return !empty($_SESSION['is_admin']);
	}

	public static function requireAdmin(): void
	{
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		if (!isset($_SESSION['user_id'])) {
			http_response_code(401);
			exit('Требуется авторизация');
		}

		if (!self::isAdmin()) {
			http_response_code(403);
			exit('Недостаточно прав');
		}
	}
}
