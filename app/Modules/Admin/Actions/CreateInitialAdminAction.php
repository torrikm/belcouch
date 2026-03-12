<?php
class CreateInitialAdminAction
{
	public function handle(): void
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			JsonResponse::send(['success' => false, 'message' => 'Метод не поддерживается'], 405);
		}

		$secret = (string) ($_POST['setup_secret'] ?? '');
		if ($secret !== ADMIN_SETUP_SECRET) {
			JsonResponse::send(['success' => false, 'message' => 'Неверный секрет инициализации'], 403);
		}

		$email = trim((string) ($_POST['email'] ?? ''));
		$firstName = trim((string) ($_POST['first_name'] ?? 'Администратор'));
		$lastName = trim((string) ($_POST['last_name'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			JsonResponse::send(['success' => false, 'message' => 'Некорректный email'], 422);
		}

		if (strlen($password) < 10) {
			JsonResponse::send(['success' => false, 'message' => 'Пароль должен содержать минимум 10 символов'], 422);
		}

		$db = new Database();
		AdminAccess::ensureSchema($db);
		$adminsCount = (int) $db->getValue("SELECT COUNT(*) FROM users WHERE role = 'admin'");
		if ($adminsCount > 0) {
			JsonResponse::send(['success' => false, 'message' => 'Первый администратор уже создан'], 409);
		}

		$safeEmail = $db->escapeString($email);
		$exists = $db->getRow("SELECT id FROM users WHERE email = '$safeEmail'");
		if ($exists) {
			$db->prepareAndExecute("UPDATE users SET role = 'admin', is_verify = 1 WHERE id = ?", 'i', [(int) $exists['id']]);
			JsonResponse::send(['success' => true, 'message' => 'Существующий пользователь повышен до администратора']);
		}

		$salt = bin2hex(random_bytes(16));
		$passwordHash = hash('sha256', $password . $salt);
		$safeFirstName = $db->escapeString($firstName);
		$safeLastName = $db->escapeString($lastName);
		$db->query("INSERT INTO users (email, first_name, last_name, password_hash, password_salt, role, is_verify, is_online) VALUES ('$safeEmail', '$safeFirstName', '$safeLastName', '$passwordHash', '$salt', 'admin', 1, 0)");
		$userId = $db->getLastInsertId();
		$db->query("INSERT INTO user_details (user_id) VALUES ('$userId')");

		JsonResponse::send(['success' => true, 'message' => 'Администратор создан', 'admin_email' => $email]);
	}
}
