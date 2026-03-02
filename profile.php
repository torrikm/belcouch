<?php
/**
 * Скрипт перенаправления на страницу профиля пользователя
 * Перенаправляет на profile/about.php с соответствующим ID пользователя
 */

// Запускаем сессию
session_start();

// Получаем ID пользователя из GET-параметра или используем ID текущего пользователя
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id']) && $profile_id === 0) {
    // Если пользователь не авторизован и не указан ID профиля, перенаправляем на страницу входа
    header('Location: login.php');
    exit;
}

// Перенаправляем на страницу about.php в директории profile
header("Location: profile/about.php" . ($profile_id ? "?id=$profile_id" : ""));
exit;
