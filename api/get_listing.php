<?php
/**
 * API для получения данных объявления
 */

// Отключаем вывод ошибок, чтобы не нарушить формат JSON
error_reporting(0);

// Устанавливаем заголовок для JSON
header('Content-Type: application/json');

session_start();
require_once '../config/config.php';
require_once '../includes/db.php';

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

// Получение ID объявления
$listing_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Валидация ID
if ($listing_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID объявления']);
    exit;
}

// Создаем экземпляр класса для работы с БД
$db = new Database();

try {
    // Получаем данные объявления
    $sql = "SELECT * FROM listings WHERE id = ?";
    $stmt = $db->prepareAndExecute($sql, "i", [$listing_id]);
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Объявление не найдено']);
        exit;
    }

    $listing = $result->fetch_assoc();

    // Проверка прав на редактирование Заметок (только владелец может редактировать)
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $listing['user_id']) {
        echo json_encode(['success' => false, 'message' => 'У вас нет прав на просмотр данного объявления']);
        exit;
    }

    // Получаем выбранные правила
    $rules_sql = "SELECT r.id, r.name, r.icon 
                 FROM rules r 
                 JOIN listing_rules lr ON r.id = lr.rule_id 
                 WHERE lr.listing_id = ?";
    $rules_stmt = $db->prepareAndExecute($rules_sql, "i", [$listing_id]);
    $rules_result = $rules_stmt->get_result();

    $rules = [];
    while ($rule = $rules_result->fetch_assoc()) {
        $rules[] = $rule;
    }

    // Получаем выбранные удобства
    $amenities_sql = "SELECT a.id, a.name, a.icon 
                     FROM amenities a 
                     JOIN listing_amenities la ON a.id = la.amenity_id 
                     WHERE la.listing_id = ?";
    $amenities_stmt = $db->prepareAndExecute($amenities_sql, "i", [$listing_id]);
    $amenities_result = $amenities_stmt->get_result();

    $amenities = [];
    while ($amenity = $amenities_result->fetch_assoc()) {
        $amenities[] = $amenity;
    }

    // Получаем изображения объявления из папки
    $images_directory = "../assets/img/listings/{$listing_id}";
    $images = [];

    if (is_dir($images_directory)) {
        // Получаем список всех файлов изображений в папке
        $files = glob($images_directory . "/*.{jpg,jpeg,png,gif}", GLOB_BRACE);

        // Сортируем файлы по имени
        sort($files);

        // Добавляем каждый файл в массив изображений
        foreach ($files as $index => $file) {
            $relative_path = str_replace('../', '', $file);
            $images[] = [
                'id' => $index + 1,
                'image_path' => $relative_path
            ];
        }
    }

    // Добавляем диагностическую информацию
    $debug = [
        'rules_count' => count($rules),
        'amenities_count' => count($amenities),
        'images_count' => count($images)
    ];

    // Добавляем связанные данные
    $listing['rules'] = $rules;
    $listing['amenities'] = $amenities;
    $listing['images'] = $images;
    $listing['debug'] = $debug;

    // Возвращаем данные
    echo json_encode(['success' => true, 'listing' => $listing]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка при получении данных объявления: ' . $e->getMessage()]);
}
?>