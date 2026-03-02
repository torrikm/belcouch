<?php
/**
 * API для живого поиска предложений
 */

// Запуск сессии для доступа к данным пользователя
session_start();

// Подключение необходимых файлов
require_once '../config/config.php';
require_once '../includes/db.php';

// Инициализация базы данных
$db = new Database();

// Получаем поисковый запрос
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

// Если запрос пустой, возвращаем пустой результат
if (empty($query)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

// Формируем запрос к базе данных для поиска предложений
$sql = "SELECT l.id, l.title, l.city, u.id as user_id, pt.name as property_type 
        FROM listings l
        JOIN users u ON l.user_id = u.id
        JOIN property_types pt ON l.property_type_id = pt.id
        WHERE l.title LIKE ? OR l.city LIKE ?
        LIMIT 3";

$params = ["%$query%", "%$query%"];
$types = "ss";

$stmt = $db->prepareAndExecute($sql, $types, $params);
$result = $stmt->get_result();

$listings = [];
while ($row = $result->fetch_assoc()) {
    // Изображения хранятся в папках по ID объявления
    $listing_images_dir = "../assets/img/listings/{$row['id']}/";
    $document_root = str_replace("\\", "/", $_SERVER['DOCUMENT_ROOT']);
    
    // Проверяем наличие основного изображения
    if (file_exists($document_root . "/" . str_replace("../", "", $listing_images_dir) . "main.jpg")) {
        $row['image'] = str_replace("../", "", $listing_images_dir) . "main.jpg";
    } else {
        // Если основного изображения нет, попробуем взять первое изображение из папки
        $image_files = glob($document_root . "/" . str_replace("../", "", $listing_images_dir) . "*.{jpg,jpeg,png,gif}", GLOB_BRACE);
        if (!empty($image_files)) {
            $row['image'] = str_replace("../", "", $listing_images_dir) . basename($image_files[0]);
        } else {
            $row['image'] = "assets/img/placeholder.jpg";
        }
    }
    
    $listings[] = $row;
}

// Возвращаем результат в формате JSON
header('Content-Type: application/json');
echo json_encode(['success' => true, 'results' => $listings]);
