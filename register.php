<?php
// Подключение необходимых файлов
require_once 'config/config.php';
require_once 'includes/db.php';

// Инициализация переменных
$errors = [];
$success = false;
$formData = [
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'password' => '',
    'confirm_password' => '',
    'birthdate' => ''
];

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получение данных из формы
    $formData = [
        'email' => trim($_POST['email'] ?? ''),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'birthdate' => $_POST['birthdate'] ?? ''
    ];

    // Валидация данных
    if (empty($formData['email'])) {
        $errors['email'] = 'Введите E-mail';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный E-mail';
    }

    if (empty($formData['first_name'])) {
        $errors['first_name'] = 'Введите имя';
    }

    if (empty($formData['last_name'])) {
        $errors['last_name'] = 'Введите фамилию';
    }

    if (empty($formData['password'])) {
        $errors['password'] = 'Введите пароль';
    } elseif (strlen($formData['password']) < 8) {
        $errors['password'] = 'Пароль должен содержать не менее 8 символов';
    } elseif (!preg_match('/[0-9]/', $formData['password']) || !preg_match('/[a-zA-Z]/', $formData['password'])) {
        $errors['password'] = 'Пароль должен содержать буквы и цифры';
    }

    if (empty($formData['confirm_password'])) {
        $errors['confirm_password'] = 'Повторите пароль';
    } elseif ($formData['password'] !== $formData['confirm_password']) {
        $errors['confirm_password'] = 'Пароли не совпадают';
    }

    if (empty($formData['birthdate'])) {
        $errors['birthdate'] = 'Выберите дату рождения';
    }

    // Если ошибок нет, сохраняем пользователя
    if (empty($errors)) {
        // Подключение к БД
        $db = new Database();

        // Проверка, существует ли пользователь с таким email
        $email = $db->escapeString($formData['email']);
        $existingUser = $db->getRow("SELECT id FROM users WHERE email = '$email'");

        if ($existingUser) {
            $errors['email'] = 'Пользователь с таким E-mail уже существует';
        } else {
            // Генерация соли и хеширование пароля
            $salt = bin2hex(random_bytes(16));
            $passwordHash = hash('sha256', $formData['password'] . $salt); // Используем SHA-256 для совместимости с другими частями сайта

            // Подготовка и выполнение запроса
            $first_name = $db->escapeString($formData['first_name']);
            $last_name = $db->escapeString($formData['last_name']);
            $birthdate = $db->escapeString($formData['birthdate']);

            // Добавляем пользователя в таблицу users
            $sql = "INSERT INTO users (email, first_name, last_name, password_hash, password_salt) 
                    VALUES ('$email', '$first_name', '$last_name', '$passwordHash', '$salt')";

            $result = $db->query($sql);

            if ($result) {
                $success = true;

                // Получаем ID нового пользователя
                $userId = $db->getLastInsertId();

                // Создаем запись в таблице user_details
                $details_sql = "INSERT INTO user_details (user_id, birthdate) VALUES ('$userId', '$birthdate')";
                $db->query($details_sql);

                // Запускаем сессию
                session_start();

                // Создаем сессию для пользователя, чтобы он сразу был авторизован
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $formData['email'];
                $_SESSION['user_name'] = $formData['first_name'] . ' ' . $formData['last_name'];

                // Перенаправление на главную страницу
                header('Location: index.php');
                exit;
            } else {
                $errors['general'] = 'Ошибка при регистрации. Пожалуйста, попробуйте снова позже.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - BelCouch</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <div class="auth-container">
        <form class="auth-form" method="POST" action="register.php">
            <h1 class="auth-title">Регистрация</h1>

            <?php if (!empty($errors['general'])): ?>
                <div class="error-message" style="display: block; margin-bottom: 15px; text-align: center;">
                    <?php echo $errors['general']; ?>
                </div>
            <?php endif; ?>

            <div class="input-group">
                <label for="email">Почта</label>
                <input type="email" name="email"
                    class="form-input <?php echo (!empty($errors['email'])) ? 'input-error' : ''; ?>"
                    placeholder="E-mail" value="<?php echo htmlspecialchars($formData['email']); ?>">
                <?php if (!empty($errors['email'])): ?>
                    <div class="error-message" style="display: block;"><?php echo $errors['email']; ?></div>
                <?php endif; ?>
            </div>

            <div class="input-group">
                <label for="first_name">Имя</label>
                <input type="text" name="first_name"
                    class="form-input <?php echo (!empty($errors['first_name'])) ? 'input-error' : ''; ?>"
                    placeholder="Имя" value="<?php echo htmlspecialchars($formData['first_name']); ?>">
                <?php if (!empty($errors['first_name'])): ?>
                    <div class="error-message" style="display: block;"><?php echo $errors['first_name']; ?></div>
                <?php endif; ?>
            </div>

            <div class="input-group">
                <label for="last_name">Фамилия</label>
                <input type="text" name="last_name"
                    class="form-input <?php echo (!empty($errors['last_name'])) ? 'input-error' : ''; ?>"
                    placeholder="Фамилия" value="<?php echo htmlspecialchars($formData['last_name']); ?>">
                <?php if (!empty($errors['last_name'])): ?>
                    <div class="error-message" style="display: block;"><?php echo $errors['last_name']; ?></div>
                <?php endif; ?>
            </div>

            <div class="input-group">
                <label for="password">Пароль</label>
                <input type="password" name="password"
                    class="form-input <?php echo (!empty($errors['password'])) ? 'input-error' : ''; ?>"
                    placeholder="Пароль">
                <?php if (!empty($errors['password'])): ?>
                    <div class="error-message" style="display: block;"><?php echo $errors['password']; ?></div>
                <?php else: ?>
                    <div class="password-requirements" style="font-size: 12px;">Пароль должен содержать минимум 8 символов,
                        включая буквы и цифры
                    </div>
                <?php endif; ?>
            </div>

            <div class="input-group">
                <label for="confirm_password">Повторите пароль</label>
                <input type="password" name="confirm_password"
                    class="form-input <?php echo (!empty($errors['confirm_password'])) ? 'input-error' : ''; ?>"
                    placeholder="Повторите пароль">
                <?php if (!empty($errors['confirm_password'])): ?>
                    <div class="error-message" style="display: block;"><?php echo $errors['confirm_password']; ?></div>
                <?php endif; ?>
            </div>

            <div class="input-group date-select">
                <label for="birthdate">Дата рождения</label>
                <input type="date" name="birthdate"
                    class="form-input <?php echo (!empty($errors['birthdate'])) ? 'input-error' : ''; ?>"
                    placeholder="Дата рождения" value="<?php echo htmlspecialchars($formData['birthdate']); ?>">
                <?php if (!empty($errors['birthdate'])): ?>
                    <div class="error-message" style="display: block;"><?php echo $errors['birthdate']; ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="auth-button">Зарегистрироваться</button>

            <div class="auth-link-container">
                <span class="auth-text">Уже есть аккаунт?</span>
                <a href="login.php" class="auth-link">Войти</a>
            </div>
        </form>
    </div>

    <script>
        // JavaScript для улучшения UX при заполнении формы
        document.addEventListener('DOMContentLoaded', function () {
            // Преобразуем поле даты для лучшего UX на мобильных
            const birthdateInput = document.querySelector('input[name="birthdate"]');
            if (birthdateInput) {
                // Устанавливаем атрибут для мобильных устройств
                birthdateInput.setAttribute('max', new Date().toISOString().split('T')[0]);

                // Если поле пустое, скрываем placeholder при фокусе
                birthdateInput.addEventListener('focus', function () {
                    if (!this.value) {
                        this.type = 'date';
                    }
                });

                // Возвращаем placeholder при потере фокуса, если поле пустое
                birthdateInput.addEventListener('blur', function () {
                    if (!this.value) {
                        this.type = 'text';
                    }
                });
            }
        });
    </script>
</body>

</html>