<?php

/**
 * Скрипт перенаправления на страницу профиля пользователя
 * Перенаправляет на profile/about.php с соответствующим ID пользователя
 */

require_once __DIR__ . '/bootstrap.php';

// Получаем ID пользователя из GET-параметра или используем ID текущего пользователя
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id']) && $profile_id === 0) {
	header('Location: ' . SITE_URL . 'index#login-modal');
	exit;
}

// Перенаправляем на страницу about в директории profile (без .php)
header('Location: ' . SITE_URL . 'profile/about');
exit;
