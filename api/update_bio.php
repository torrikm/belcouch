<?php
/**
 * API для обновления описания и дополнительной информации пользователя
 */
session_start();
require_once '../config/config.php';
require_once '../includes/db.php';

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован']);
    exit;
}

// Получаем ID пользователя из сессии
$user_id = $_SESSION['user_id'];

try {
    // Инициализируем подключение к БД
    $db = new Database();

    // Получаем данные из формы
    $description = !empty($_POST['description']) ? $_POST['description'] : null;

    // Получаем дополнительные поля, имена полей в форме теперь совпадают с именами в БД
    $education = !empty($_POST['education']) ? $_POST['education'] : null;
    $occupation = !empty($_POST['occupation']) ? $_POST['occupation'] : null;
    $interests = !empty($_POST['interests']) ? $_POST['interests'] : null;

    // Проверяем, существует ли запись в user_details
    $check_sql = "SELECT id FROM user_details WHERE user_id = ?";
    $check_stmt = $db->prepareAndExecute($check_sql, "i", [$user_id]);
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Обновляем существующую запись в user_details
        $sql = "UPDATE user_details SET description = ?, education = ?, occupation = ?, interests = ? WHERE user_id = ?";
        $stmt = $db->prepareAndExecute($sql, "ssssi", [$description, $education, $occupation, $interests, $user_id]);
    } else {
        // Создаем новую запись в user_details
        $sql = "INSERT INTO user_details (user_id, description, education, occupation, interests) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepareAndExecute($sql, "issss", [$user_id, $description, $education, $occupation, $interests]);
    }

    // Получаем обновленные данные пользователя для возврата в ответе
    $userData = [
        'id' => $user_id,
        'description' => $description,
        'education' => $education,
        'occupation' => $occupation,
        'interests' => $interests
    ];
    
    // Возвращаем успешный ответ с данными пользователя
    echo json_encode([
        'success' => true, 
        'message' => 'Информация о профиле успешно обновлена',
        'user' => $userData
    ]);

} catch (Exception $e) {
    // В случае ошибки возвращаем сообщение об ошибке
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при обновлении информации: ' . $e->getMessage()
    ]);
    exit;
}
?>