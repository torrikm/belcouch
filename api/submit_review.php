<?php
// API для отправки отзыва о пользователе
require_once '../config/config.php';
require_once '../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit;
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$rater_id = $_SESSION['user_id'];
$comment = trim($_POST['comment'] ?? '');
$rating = intval($_POST['rating'] ?? 0);

if ($user_id <= 0 || $rater_id <= 0 || $rating < 1 || $rating > 5 || $comment === '') {
    echo json_encode(['success' => false, 'message' => 'Некорректные данные']);
    exit;
}

try {
    $db = new Database();
    
    // Добавляем отзыв в базу данных
    $stmt = $db->prepareAndExecute(
        "INSERT INTO user_ratings (user_id, rater_id, comment, rating, created_at) VALUES (?, ?, ?, ?, NOW())",
        "iisi",
        [$user_id, $rater_id, $comment, $rating]
    );
    
    // Получаем ID только что добавленного отзыва
    $review_id = $stmt->insert_id;
    
    // Получаем данные о текущем пользователе
    $user_stmt = $db->prepareAndExecute(
        "SELECT first_name, last_name, avatar_image FROM users WHERE id = ?",
        "i",
        [$rater_id]
    );
    $user_data = $user_stmt->get_result()->fetch_assoc();
    
    // Формируем данные для ответа
    $response = [
        'success' => true,
        'review' => [
            'id' => $review_id,
            'user_id' => $user_id,
            'rater_id' => $rater_id,
            'first_name' => $user_data['first_name'],
            'last_name' => $user_data['last_name'],
            'avatar_image' => $user_data['avatar_image'],
            'comment' => $comment,
            'rating' => $rating,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка при сохранении отзыва']);
}
