<?php
// Файл для получения аватара пользователя из базы данных
require_once '../config/config.php';
require_once '../includes/db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$id = intval($_GET['id']);

try {
    // Инициализируем соединение с базой данных
    $db = new Database();
    
    // Получаем аватар из базы данных
    $stmt = $db->prepareAndExecute(
        "SELECT avatar_image FROM users WHERE id = ?",
        "i",
        [$id]
    );
    
    $stmt->store_result();
    
    if ($stmt->num_rows === 0) {
        header('HTTP/1.1 404 Not Found');
        exit;
    }
    
    $stmt->bind_result($avatar);
    $stmt->fetch();
    
    // Проверяем, что аватар существует
    if ($avatar === null) {
        header('HTTP/1.1 404 Not Found');
        exit;
    }
    
    // Устанавливаем заголовки для изображения
    header('Content-Type: image/jpeg');
    header('Cache-Control: max-age=86400'); // Кеширование на 24 часа
    
    // Выводим содержимое изображения
    echo $avatar;
    
} catch (Exception $e) {
    // Обработка ошибок
    header('HTTP/1.1 500 Internal Server Error');
    exit;
}
?>
