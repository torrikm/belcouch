<?php
/**
 * API для обновления основной информации профиля пользователя
 */
session_start();
require_once '../config/config.php';
require_once '../includes/db.php';

// Добавляем отладочную информацию
error_log("POST: " . print_r($_POST, true));
error_log("FILES: " . print_r($_FILES, true));

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован']);
    exit;
}

// Получаем ID пользователя из сессии
$user_id = $_SESSION['user_id'];

// Проверяем, что все необходимые поля отправлены
if (!isset($_POST['first_name']) || !isset($_POST['last_name']) || !isset($_POST['email'])) {
    echo json_encode(['success' => false, 'message' => 'Не все обязательные поля заполнены']);
    exit;
}

// Валидация email
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Неверный формат email']);
    exit;
}

try {
    // Инициализируем подключение к БД
    $db = new Database();
    
    // Проверяем, не занят ли уже новый email другим пользователем
    if ($_POST['email'] !== $_SESSION['email']) {
        $stmt = $db->prepareAndExecute(
            "SELECT id FROM users WHERE email = ? AND id != ?",
            "si",
            [$_POST['email'], $user_id]
        );
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Этот email уже используется другим пользователем']);
            exit;
        }
    }
    
    // Получаем данные из формы
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    
    // Обрабатываем поля, которые могут быть NULL
    $region_id = !empty($_POST['region_id']) ? intval($_POST['region_id']) : null;
    $city = !empty($_POST['city']) ? $_POST['city'] : null;
    
    // Получаем выбранный пол
    $gender = isset($_POST['gender']) ? $_POST['gender'] : 'not_specified';
    
    // Обработка загруженного аватара
    $avatar_updated = false;
    $avatar_sql = "";
    $avatar_types = [];
    $avatar_values = [];
    
    // Подробная отладка загрузки файла
    if (isset($_FILES['avatar'])) {
        error_log("Avatar upload info: " . print_r($_FILES['avatar'], true));
        error_log("Avatar upload error code: " . $_FILES['avatar']['error']);
        
        // Коды ошибок загрузки
        $upload_errors = [
            UPLOAD_ERR_OK => 'Файл загружен успешно',
            UPLOAD_ERR_INI_SIZE => 'Файл превышает размер upload_max_filesize в php.ini',
            UPLOAD_ERR_FORM_SIZE => 'Файл превышает размер MAX_FILE_SIZE в HTML форме',
            UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично',
            UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
            UPLOAD_ERR_EXTENSION => 'Загрузка файла остановлена расширением'
        ];
        
        error_log("Upload error message: " . $upload_errors[$_FILES['avatar']['error']]);
    } else {
        error_log("No avatar file in request");
    }
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        // Проверка типа файла
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $file_type = $_FILES['avatar']['type'];
        
        error_log("File type: " . $file_type);
        error_log("File size: " . $_FILES['avatar']['size'] . " bytes");
        
        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Недопустимый тип файла аватара. Разрешены только JPG и PNG.']);
            exit;
        }
        
        // Проверка размера файла (не более 2 МБ)
        if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Размер файла не должен превышать 2 МБ']);
            exit;
        }
        
        try {
            // Обрабатываем и сохраняем аватар
            $avatar_data = file_get_contents($_FILES['avatar']['tmp_name']);
            if ($avatar_data === false) {
                error_log("Failed to read avatar file");
                throw new Exception('Не удалось прочитать файл аватара');
            }
            
            error_log("Avatar data read successfully, size: " . strlen($avatar_data) . " bytes");
            
            // Сохраняем аватар в отдельном запросе, используя прямой доступ к mysqli
            $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $mysqli->set_charset("utf8");
            
            if ($mysqli->connect_error) {
                error_log("Connection failed: " . $mysqli->connect_error);
                throw new Exception('Ошибка подключения к базе данных');
            }
            
            // Подготавливаем запрос для обновления аватара
            $stmt = $mysqli->prepare("UPDATE users SET avatar_image = ? WHERE id = ?");
            
            if (!$stmt) {
                error_log("Prepare failed: " . $mysqli->error);
                $mysqli->close();
                throw new Exception('Ошибка при подготовке запроса для обновления аватара');
            }
            
            // Привязываем параметры
            $stmt->bind_param("si", $avatar_data, $user_id);
            
            // Выполняем запрос
            if (!$stmt->execute()) {
                error_log("Execute failed: " . $stmt->error);
                $stmt->close();
                $mysqli->close();
                throw new Exception('Ошибка при выполнении запроса для обновления аватара');
            }
            
            // Закрываем соединение
            $stmt->close();
            $mysqli->close();
            
            error_log("Avatar updated successfully");
            $avatar_updated = true;
        } catch (Exception $e) {
            error_log("Error processing avatar: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Ошибка при обработке аватара: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Формируем запрос для обновления данных в таблице users
    $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?";
    $types = "sss";
    $values = [$first_name, $last_name, $email];
    
    // Проверяем, существует ли запись в user_details
    $check_sql = "SELECT id FROM user_details WHERE user_id = ?";
    $check_stmt = $db->prepareAndExecute($check_sql, "i", [$user_id]);
    $check_result = $check_stmt->get_result();
    
    // Добавляем отладочную информацию
    error_log("region_id: " . var_export($region_id, true));
    error_log("city: " . var_export($city, true));
    error_log("gender: " . var_export($gender, true));
    
    if ($check_result->num_rows > 0) {
        // Обновляем существующую запись в user_details
        if ($region_id === null) {
            // Если область не выбрана, устанавливаем NULL
            $details_sql = "UPDATE user_details SET region_id = NULL, city = ?, gender = ? WHERE user_id = ?";
            $details_types = "ssi";
            $details_values = [$city, $gender, $user_id];
        } else {
            $details_sql = "UPDATE user_details SET region_id = ?, city = ?, gender = ? WHERE user_id = ?";
            $details_types = "issi";
            $details_values = [$region_id, $city, $gender, $user_id];
        }
    } else {
        // Создаем новую запись в user_details
        if ($region_id === null) {
            $details_sql = "INSERT INTO user_details (user_id, city, gender) VALUES (?, ?, ?)";
            $details_types = "iss";
            $details_values = [$user_id, $city, $gender];
        } else {
            $details_sql = "INSERT INTO user_details (user_id, region_id, city, gender) VALUES (?, ?, ?, ?)";
            $details_types = "iiss";
            $details_values = [$user_id, $region_id, $city, $gender];
        }
    }
    
    // Если аватар был обновлен, добавляем его в запрос
    if ($avatar_updated) {
        $sql .= $avatar_sql;
        $types .= implode('', $avatar_types);
        $values = array_merge($values, $avatar_values);
    }
    
    // Добавляем ID пользователя в запрос для таблицы users
    $sql .= " WHERE id = ?";
    $types .= "i";
    $values[] = $user_id;
    
    // Выполняем запрос на обновление таблицы users
    $stmt = $db->prepareAndExecute($sql, $types, $values);
    
    // Выполняем запрос на обновление/добавление в таблицу user_details
    $details_stmt = $db->prepareAndExecute($details_sql, $details_types, $details_values);
    
    // Обновляем данные в сессии
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    $_SESSION['email'] = $email;
    
    // Получаем обновленные данные пользователя для возврата в ответе
    $userData = [
        'id' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'region' => $region_id, // Возвращаем id региона вместо неопределённой переменной
        'city' => $city,
        'gender' => $gender,
        'avatar_updated' => $avatar_updated
    ];
    
    // Возвращаем успешный ответ с данными пользователя
    echo json_encode([
        'success' => true, 
        'message' => 'Профиль успешно обновлен',
        'user' => $userData
    ]);
    
} catch (Exception $e) {
    // В случае ошибки возвращаем сообщение об ошибке
    echo json_encode(['success' => false, 'message' => 'Ошибка при обновлении профиля: ' . $e->getMessage()]);
    exit;
}
?>
