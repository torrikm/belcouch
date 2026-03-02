<?php
/**
 * API для отправки отзывов о жилье
 */

session_start();
require_once '../config/config.php';
require_once '../includes/db.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Необходимо авторизоваться']);
    exit;
}

// Создаем экземпляр класса для работы с БД
$db = new Database();

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

// Получение данных из запроса
$listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$user_id = $_SESSION['user_id'];

// Валидация данных
if ($listing_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID объявления указано неверно']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Рейтинг должен быть от 1 до 5']);
    exit;
}

if (empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Комментарий не может быть пустым']);
    exit;
}

// Проверка, не оставлял ли пользователь уже отзыв на это объявление
try {
    $check_sql = "SELECT id FROM listing_ratings WHERE listing_id = ? AND user_id = ?";
    $check_stmt = $db->prepareAndExecute($check_sql, "ii", [$listing_id, $user_id]);
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Вы уже оставляли отзыв на это объявление']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка при проверке отзыва: ' . $e->getMessage()]);
    exit;
}

// Проверка, существует ли объявление
try {
    $listing_sql = "SELECT id, user_id FROM listings WHERE id = ?";
    $listing_stmt = $db->prepareAndExecute($listing_sql, "i", [$listing_id]);
    $listing_result = $listing_stmt->get_result();
    
    if ($listing_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Объявление не найдено']);
        exit;
    }
    
    $listing = $listing_result->fetch_assoc();
    
    // Пользователь не может оставлять отзывы на свои объявления
    if ($listing['user_id'] == $user_id) {
        echo json_encode(['success' => false, 'message' => 'Вы не можете оставлять отзывы на свои объявления']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка при проверке объявления: ' . $e->getMessage()]);
    exit;
}

// Добавление отзыва в базу данных
try {
    $sql = "INSERT INTO listing_ratings (listing_id, user_id, comment, rating) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepareAndExecute($sql, "iisi", [$listing_id, $user_id, $comment, $rating]);
    
    if ($stmt->affected_rows > 0) {
        // Получаем данные о пользователе, оставившем отзыв
        $user_sql = "SELECT first_name, last_name, avatar_image FROM users WHERE id = ?";
        $user_stmt = $db->prepareAndExecute($user_sql, "i", [$user_id]);
        $user_result = $user_stmt->get_result();
        $user = $user_result->fetch_assoc();
        
        // Обновляем средний рейтинг объявления
        $avg_sql = "UPDATE listings SET avg_rating = (SELECT AVG(rating) FROM listing_ratings WHERE listing_id = ?) WHERE id = ?";
        $db->prepareAndExecute($avg_sql, "ii", [$listing_id, $listing_id]);
        
        // Формируем данные для ответа
        $review = [
            'id' => $stmt->insert_id,
            'listing_id' => $listing_id,
            'user_id' => $user_id,
            'comment' => $comment,
            'rating' => $rating,
            'created_at' => date('Y-m-d H:i:s'),
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'avatar_image' => $user['avatar_image'] ? true : false
        ];
        
        echo json_encode(['success' => true, 'message' => 'Отзыв успешно добавлен', 'review' => $review]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Не удалось добавить отзыв']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка при добавлении отзыва: ' . $e->getMessage()]);
}
?>
