<?php
class LoginAction
{
    public function handle(): void
    {
        $response = ['success' => false, 'errors' => []];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            JsonResponse::send($response, 405);
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '') {
            $response['errors']['email'] = 'Введите E-mail';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['errors']['email'] = 'Введите корректный E-mail';
        }

        if ($password === '') {
            $response['errors']['password'] = 'Введите пароль';
        }

        if (!empty($response['errors'])) {
            JsonResponse::send($response);
        }

        try {
            $db = new Database();
            $safeEmail = $db->escapeString($email);
            $user = $db->getRow("SELECT id, email, password_hash, password_salt, first_name, last_name FROM users WHERE email = '$safeEmail'");

            if (!$user) {
                $response['errors']['email'] = 'Пользователь с таким E-mail не найден';
                JsonResponse::send($response);
            }

            $checkHash = hash('sha256', $password . $user['password_salt']);
            if ($checkHash !== $user['password_hash']) {
                $response['errors']['password'] = 'Неверный пароль';
                JsonResponse::send($response);
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ($user['last_name'] ? ' ' . $user['last_name'] : '');
            $db->query('UPDATE users SET is_online = 1 WHERE id = ' . (int) $user['id']);

            JsonResponse::send([
                'success' => true,
                'message' => 'Успешный вход'
            ]);
        } catch (Exception $exception) {
            $response['errors']['general'] = 'Произошла ошибка при обращении к базе данных';
            JsonResponse::send($response, 500);
        }
    }
}
