<?php
class RegisterAction
{
    public function handle(): void
    {
        $response = ['success' => false, 'errors' => []];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            JsonResponse::send($response, 405);
        }

        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($email === '') {
            $response['errors']['email'] = 'Введите E-mail';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['errors']['email'] = 'Введите корректный E-mail';
        }

        if ($firstName === '') {
            $response['errors']['first_name'] = 'Введите имя';
        }

        if ($password === '') {
            $response['errors']['password'] = 'Введите пароль';
        } elseif (strlen($password) < 8) {
            $response['errors']['password'] = 'Пароль должен содержать не менее 8 символов';
        } elseif (!preg_match('/[0-9]/', $password) || !preg_match('/[a-zA-Z]/', $password)) {
            $response['errors']['password'] = 'Пароль должен содержать буквы и цифры';
        }

        if ($confirmPassword === '') {
            $response['errors']['confirm_password'] = 'Повторите пароль';
        } elseif ($password !== $confirmPassword) {
            $response['errors']['confirm_password'] = 'Пароли не совпадают';
        }

        if (!empty($response['errors'])) {
            JsonResponse::send($response);
        }

        try {
            $db = new Database();
            $safeEmail = $db->escapeString($email);
            $existingUser = $db->getRow("SELECT id FROM users WHERE email = '$safeEmail'");

            if ($existingUser) {
                $response['errors']['email'] = 'Пользователь с таким E-mail уже существует';
                JsonResponse::send($response);
            }

            $salt = bin2hex(random_bytes(16));
            $passwordHash = hash('sha256', $password . $salt);
            $safeFirstName = $db->escapeString($firstName);

            $db->query("INSERT INTO users (email, first_name, password_hash, password_salt, is_online) VALUES ('$safeEmail', '$safeFirstName', '$passwordHash', '$salt', 1)");
            $userId = $db->getLastInsertId();
            $db->query("INSERT INTO user_details (user_id) VALUES ('$userId')");

            $_SESSION['user_id'] = $userId;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $firstName;

            JsonResponse::send([
                'success' => true,
                'message' => 'Регистрация успешна'
            ]);
        } catch (Exception $exception) {
            $response['errors']['general'] = 'Произошла системная ошибка: ' . $exception->getMessage();
            JsonResponse::send($response, 500);
        }
    }
}
