<?php
/**
 * Скрипт для выхода пользователя из системы
 */

// Запускаем сессию
session_start();

// Очищаем все данные сессии
$_SESSION = array();

// Уничтожаем куки сессии
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Уничтожаем сессию
session_destroy();

// Перенаправляем на главную страницу
header("Location: index.php");
exit;
