<?php
class UpdateBioAction
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

		try {
			$db = new Database();
			$description = !empty($_POST['description']) ? trim((string) $_POST['description']) : null;
			$education = !empty($_POST['education']) ? $_POST['education'] : null;
			$occupation = !empty($_POST['occupation']) ? $_POST['occupation'] : null;
			$interests = !empty($_POST['interests']) ? $_POST['interests'] : null;

			if ($description !== null && mb_strlen($description) > 800) {
				JsonResponse::send(['success' => false, 'message' => 'Описание не должно превышать 800 символов'], 422);
			}

			$checkStmt = $db->prepareAndExecute('SELECT id FROM user_details WHERE user_id = ?', 'i', [$userId]);
			$exists = $checkStmt->get_result()->num_rows > 0;

			if ($exists) {
				$db->prepareAndExecute('UPDATE user_details SET description = ?, education = ?, occupation = ?, interests = ? WHERE user_id = ?', 'ssssi', [$description, $education, $occupation, $interests, $userId]);
			} else {
				$db->prepareAndExecute('INSERT INTO user_details (user_id, description, education, occupation, interests) VALUES (?, ?, ?, ?, ?)', 'issss', [$userId, $description, $education, $occupation, $interests]);
			}

			JsonResponse::send([
				'success' => true,
				'message' => 'Информация о профиле успешно обновлена',
				'user' => [
					'id' => $userId,
					'description' => $description,
					'education' => $education,
					'occupation' => $occupation,
					'interests' => $interests
				]
			]);
		} catch (Exception $exception) {
			JsonResponse::send(['success' => false, 'message' => 'Ошибка при обновлении информации: ' . $exception->getMessage()], 500);
		}
	}
}
