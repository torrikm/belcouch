<?php
/**
 * Простое API для добавления объявления в избранное
 */

// Подключаем конфигурацию для доступа к константам базы данных
require_once '../config/config.php';

// Запуск сессии для доступа к данным пользователя
session_start();

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Необходимо авторизоваться'
    ]);
    exit;
}

// Получаем данные
$user_id = $_SESSION['user_id'];
$listing_id = isset($_POST['listing_id']) ? (int) $_POST['listing_id'] : 0;

if ($listing_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Некорректный ID объявления'
    ]);
    exit;
}

try {
    // Устанавливаем соединение напрямую
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8");
    
    if ($conn->connect_error) {
        throw new Exception("Ошибка соединения: " . $conn->connect_error);
    }
    
    // Проверяем, есть ли уже это объявление в избранном
    $check_sql = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND listing_id = ?");
    $check_sql->bind_param("ii", $user_id, $listing_id);
    $check_sql->execute();
    $check_result = $check_sql->get_result();
    
    if ($check_result->num_rows > 0) {
        // Если объявление уже в избранном - удаляем его
        $favorite_id = $check_result->fetch_assoc()['id'];
        
        $delete_sql = $conn->prepare("DELETE FROM favorites WHERE id = ?");
        $delete_sql->bind_param("i", $favorite_id);
        $delete_sql->execute();
        
        echo json_encode([
            'success' => true,
            'action' => 'removed',
            'message' => 'Объявление удалено из избранного'
        ]);
    } else {
        // Если объявления нет в избранном - добавляем его
        $now = date("Y-m-d H:i:s");
        $insert_sql = $conn->prepare("INSERT INTO favorites (user_id, listing_id, created_at) VALUES (?, ?, ?)");
        $insert_sql->bind_param("iis", $user_id, $listing_id, $now);
        $insert_sql->execute();
        
        echo json_encode([
            'success' => true,
            'action' => 'added',
            'message' => 'Объявление добавлено в избранное'
        ]);
    }
    
    // Закрываем соединение
    $check_sql->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при обработке запроса',
        'error' => $e->getMessage()
    ]);
}
?>
