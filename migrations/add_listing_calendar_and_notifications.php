<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
	$db = new Database();

	$checkUnavailable = $db->query("SHOW TABLES LIKE 'listing_unavailable_dates'");
	if ($checkUnavailable->num_rows === 0) {
		$db->query(
			"CREATE TABLE listing_unavailable_dates (
				id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
				listing_id int(11) NOT NULL,
				unavailable_date date NOT NULL,
				created_at timestamp NOT NULL DEFAULT current_timestamp(),
				UNIQUE KEY listing_unavailable_unique (listing_id, unavailable_date),
				FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
			)"
		);
		echo "Создана таблица listing_unavailable_dates.\n";
	} else {
		echo "Таблица listing_unavailable_dates уже существует.\n";
	}

	$checkNotifications = $db->query("SHOW TABLES LIKE 'user_notifications'");
	if ($checkNotifications->num_rows === 0) {
		$db->query(
			"CREATE TABLE user_notifications (
				id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
				user_id int(11) NOT NULL,
				notification_type varchar(100) DEFAULT NULL,
				title varchar(255) NOT NULL,
				message text NOT NULL,
				created_at timestamp NOT NULL DEFAULT current_timestamp(),
				FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
			)"
		);
		echo "Создана таблица user_notifications.\n";
	} else {
		$checkType = $db->query("SHOW COLUMNS FROM user_notifications LIKE 'notification_type'");
		if ($checkType->num_rows === 0) {
			$db->query("ALTER TABLE user_notifications ADD COLUMN notification_type varchar(100) DEFAULT NULL AFTER user_id");
			echo "Добавлена колонка notification_type в user_notifications.\n";
		} else {
			echo "Колонка notification_type в user_notifications уже существует.\n";
		}
		echo "Таблица user_notifications уже существует.\n";
	}

	echo "Миграция успешно завершена.\n";
} catch (Exception $exception) {
	echo "Ошибка миграции: " . $exception->getMessage() . "\n";
}
