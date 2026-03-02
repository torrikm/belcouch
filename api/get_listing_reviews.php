<?php
/**
 * API для получения отзывов о жилье
 */

session_start();
require_once '../config/config.php';
require_once '../includes/db.php';

// Создаем экземпляр класса для работы с БД
$db = new Database();

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

// Получение ID объявления из запроса
$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;

// Валидация данных
if ($listing_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID объявления указано неверно']);
    exit;
}

// Получение отзывов из базы данных
try {
    $sql = "SELECT lr.*, u.first_name, u.last_name, u.avatar_image
            FROM listing_ratings lr
            JOIN users u ON lr.user_id = u.id
            WHERE lr.listing_id = ?
            ORDER BY lr.created_at DESC";
    
    $stmt = $db->prepareAndExecute($sql, "i", [$listing_id]);
    $result = $stmt->get_result();
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        // Преобразуем данные о наличии аватарки в булево значение
        $row['avatar_image'] = !empty($row['avatar_image']);
        $reviews[] = $row;
    }
    
    echo json_encode(['success' => true, 'reviews' => $reviews]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка при получении отзывов: ' . $e->getMessage()]);
}
?>
