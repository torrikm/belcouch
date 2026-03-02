<?php
// Подключение необходимых файлов
require_once 'config/config.php';
require_once 'includes/db.php';

// Инициализация переменных
$errors = [];
$email = '';

// Проверка, была ли выполнена успешная регистрация
$justRegistered = isset($_GET['registered']) && $_GET['registered'] == '1';

// Проверка авторизации (если пользователь уже авторизован, перенаправляем на главную)
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получение данных из формы
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Валидация данных
    if (empty($email)) {
        $errors['email'] = 'Введите E-mail';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный E-mail';
    }

    if (empty($password)) {
        $errors['password'] = 'Введите пароль';
    }

    // Если ошибок нет, проверяем учетные данные
    if (empty($errors)) {
        // Подключение к БД
        $db = new Database();

        // Получение данных пользователя
        $email = $db->escapeString($email);
        $user = $db->getRow("SELECT id, email, password_hash, password_salt, first_name, last_name FROM users WHERE email = '$email'");

        if ($user) {
            // Проверка пароля
            $checkHash = hash('sha256', $password . $user['password_salt']);

            if ($checkHash === $user['password_hash']) {
                // Создание сессии
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];

                // Перенаправление на главную страницу
                header('Location: index.php');
                exit;
            } else {
                $errors['password'] = 'Неверный пароль';
            }
        } else {
            $errors['email'] = 'Пользователь с таким E-mail не найден';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - BelCouch</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <div class="auth-container">
        <form class="auth-form" method="POST" action="login.php">
            <h1 class="auth-title">Вход</h1>

            <?php if ($justRegistered): ?>
                <div class="success-message" style="color: #2ecc71; text-align: center; margin-bottom: 15px;">
                    Регистрация успешно завершена! Теперь вы можете войти.
                </div>
            <?php endif; ?>

            <div class="input-group">
                <label for="email">E-mail</label>
                <input type="email" name="email"
                    class="form-input <?php echo (!empty($errors['email'])) ? 'input-error' : ''; ?>"
                    placeholder="E-mail" value="<?php echo htmlspecialchars($email); ?>">
                <?php if (!empty($errors['email'])): ?>
                    <div class="error-message" style="display: block;"><?php echo $errors['email']; ?></div>
                <?php endif; ?>
            </div>

            <div class="input-group">
                <label for="password">Пароль</label>
                <input type="password" name="password"
                    class="form-input <?php echo (!empty($errors['password'])) ? 'input-error' : ''; ?>"
                    placeholder="Пароль">
                <?php if (!empty($errors['password'])): ?>
                    <div class="error-message" style="display: block;"><?php echo $errors['password']; ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="auth-button">Войти</button>

            <div class="auth-link-container">
                <span class="auth-text">Ещё нет аккаунта?</span>
                <a href="register.php" class="auth-link">Зарегистрироваться</a>
            </div>
        </form>
    </div>
</body>

</html>