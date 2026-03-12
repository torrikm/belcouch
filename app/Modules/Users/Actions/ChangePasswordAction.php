<?php
class ChangePasswordAction
{
    public function handle(): void
    {
        if (!isset($_SESSION['user_id'])) {
            JsonResponse::send(['success' => false, 'message' => 'Пользователь не авторизован'], 401);
        }

        $userId = (int) $_SESSION['user_id'];

        if (!isset($_POST['current_password'], $_POST['new_password'], $_POST['confirm_password'])) {
            JsonResponse::send(['success' => false, 'message' => 'Не все обязательные поля заполнены'], 422);
        }

        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            JsonResponse::send(['success' => false, 'message' => 'Новый пароль и подтверждение не совпадают'], 422);
        }

        if (strlen($_POST['new_password']) < 8) {
            JsonResponse::send(['success' => false, 'message' => 'Пароль должен содержать минимум 8 символов'], 422);
        }

        try {
            $db = new Database();
            $stmt = $db->prepareAndExecute('SELECT password_hash, password_salt FROM users WHERE id = ?', 'i', [$userId]);
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                JsonResponse::send(['success' => false, 'message' => 'Пользователь не найден'], 404);
            }

            $user = $result->fetch_assoc();
            $currentHashed = hash('sha256', $_POST['current_password'] . $user['password_salt']);
            if ($currentHashed !== $user['password_hash']) {
                JsonResponse::send(['success' => false, 'message' => 'Текущий пароль введен неверно'], 422);
            }

            $newSalt = bin2hex(random_bytes(16));
            $newHashed = hash('sha256', $_POST['new_password'] . $newSalt);
            $db->prepareAndExecute('UPDATE users SET password_hash = ?, password_salt = ? WHERE id = ?', 'ssi', [$newHashed, $newSalt, $userId]);

            JsonResponse::send(['success' => true, 'message' => 'Пароль успешно изменен']);
        } catch (Exception $exception) {
            JsonResponse::send(['success' => false, 'message' => 'Ошибка при изменении пароля: ' . $exception->getMessage()], 500);
        }
    }
}
