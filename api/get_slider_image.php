<?php
/**
 * Скрипт для загрузки изображений слайдера из базы данных
 */

require_once '../config/config.php';
require_once '../includes/db.php';

// Проверка наличия ID изображения
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.0 404 Not Found');
    exit('Изображение не найдено');
}

$id = intval($_GET['id']);

$db = new Database();

// Получение изображения из базы данных
$stmt = $db->prepareAndExecute(
    "SELECT image FROM slider_images WHERE id = ?",
    "i",
    [$id]
);

$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($image);
    $stmt->fetch();

    // Устанавливаем правильные заголовки для изображения
    header('Content-Type: image/jpeg');
    header('Cache-Control: max-age=86400'); // Кэширование на 24 часа

    // Выводим содержимое изображения
    echo $image;
} else {
    // Если изображение не найдено, выводим заглушку
    header('HTTP/1.0 404 Not Found');
    exit('Изображение не найдено');
}

// Закрываем подготовленный запрос
$stmt->close();
