<?php
class UpdateProfileAction
{
	public function handle(): void
	{
		if (!isset($_SESSION['user_id'])) {
			JsonResponse::send(['success' => false, 'message' => 'Пользователь не авторизован'], 401);
		}

		$userId = (int) $_SESSION['user_id'];
		$targetUserId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : $userId;

		if ($targetUserId !== $userId) {
			JsonResponse::send(['success' => false, 'message' => 'Недостаточно прав для редактирования этого профиля'], 403);
		}

		if (!isset($_POST['first_name'], $_POST['last_name'], $_POST['email'])) {
			JsonResponse::send(['success' => false, 'message' => 'Не все обязательные поля заполнены'], 422);
		}

		if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
			JsonResponse::send(['success' => false, 'message' => 'Неверный формат email'], 422);
		}

		try {
			$db = new Database();
			$sessionEmail = $_SESSION['email'] ?? ($_SESSION['user_email'] ?? '');

			if ($_POST['email'] !== $sessionEmail) {
				$stmt = $db->prepareAndExecute('SELECT id FROM users WHERE email = ? AND id != ?', 'si', [$_POST['email'], $userId]);
				$stmt->store_result();
				if ($stmt->num_rows > 0) {
					JsonResponse::send(['success' => false, 'message' => 'Этот email уже используется другим пользователем'], 422);
				}
			}

			$firstName = $_POST['first_name'];
			$lastName = $_POST['last_name'];
			$email = $_POST['email'];
			$regionId = !empty($_POST['region_id']) ? (int) $_POST['region_id'] : null;
			$city = !empty($_POST['city']) ? $_POST['city'] : null;
			$gender = isset($_POST['gender']) ? $_POST['gender'] : 'not_specified';
			$birthDate = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
			$avatarUpdated = false;

			if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
				$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
				if (!in_array($_FILES['avatar']['type'], $allowedTypes, true)) {
					JsonResponse::send(['success' => false, 'message' => 'Недопустимый тип файла аватара. Разрешены только JPG и PNG.'], 422);
				}

				if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
					JsonResponse::send(['success' => false, 'message' => 'Размер файла не должен превышать 2 МБ'], 422);
				}

				$avatarData = file_get_contents($_FILES['avatar']['tmp_name']);
				if ($avatarData === false) {
					JsonResponse::send(['success' => false, 'message' => 'Ошибка при обработке аватара: Не удалось прочитать файл аватара'], 500);
				}

				$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
				$mysqli->set_charset('utf8');
				if ($mysqli->connect_error) {
					JsonResponse::send(['success' => false, 'message' => 'Ошибка при обработке аватара: Ошибка подключения к базе данных'], 500);
				}

				$stmt = $mysqli->prepare('UPDATE users SET avatar_image = ? WHERE id = ?');
				if (!$stmt) {
					$mysqli->close();
					JsonResponse::send(['success' => false, 'message' => 'Ошибка при обработке аватара: Ошибка при подготовке запроса для обновления аватара'], 500);
				}

				$stmt->bind_param('si', $avatarData, $userId);
				if (!$stmt->execute()) {
					$stmt->close();
					$mysqli->close();
					JsonResponse::send(['success' => false, 'message' => 'Ошибка при обработке аватара: Ошибка при выполнении запроса для обновления аватара'], 500);
				}

				$stmt->close();
				$mysqli->close();
				$avatarUpdated = true;
			}

			$db->prepareAndExecute('UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?', 'sssi', [$firstName, $lastName, $email, $userId]);

			$checkStmt = $db->prepareAndExecute('SELECT id FROM user_details WHERE user_id = ?', 'i', [$userId]);
			$exists = $checkStmt->get_result()->num_rows > 0;

			if ($exists) {
				if ($regionId === null) {
					$db->prepareAndExecute('UPDATE user_details SET region_id = NULL, city = ?, gender = ?, birthdate = ? WHERE user_id = ?', 'sssi', [$city, $gender, $birthDate, $userId]);
				} else {
					$db->prepareAndExecute('UPDATE user_details SET region_id = ?, city = ?, gender = ?, birthdate = ? WHERE user_id = ?', 'isssi', [$regionId, $city, $gender, $birthDate, $userId]);
				}
			} else {
				if ($regionId === null) {
					$db->prepareAndExecute('INSERT INTO user_details (user_id, city, gender, birthdate) VALUES (?, ?, ?, ?)', 'isss', [$userId, $city, $gender, $birthDate]);
				} else {
					$db->prepareAndExecute('INSERT INTO user_details (user_id, region_id, city, gender, birthdate) VALUES (?, ?, ?, ?, ?)', 'iisss', [$userId, $regionId, $city, $gender, $birthDate]);
				}
			}

			$_SESSION['first_name'] = $firstName;
			$_SESSION['last_name'] = $lastName;
			$_SESSION['email'] = $email;
			$_SESSION['user_email'] = $email;
			$_SESSION['user_name'] = $firstName . ($lastName ? ' ' . $lastName : '');

			JsonResponse::send([
				'success' => true,
				'message' => 'Профиль успешно обновлен',
				'user' => [
					'id' => $userId,
					'first_name' => $firstName,
					'last_name' => $lastName,
					'email' => $email,
					'region' => $regionId,
					'city' => $city,
					'gender' => $gender,
					'birth_date' => $birthDate,
					'avatar_updated' => $avatarUpdated
				]
			]);
		} catch (Exception $exception) {
			JsonResponse::send(['success' => false, 'message' => 'Ошибка при обновлении профиля: ' . $exception->getMessage()], 500);
		}
	}
}
