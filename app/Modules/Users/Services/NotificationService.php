<?php

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

class NotificationService
{
	private Database $db;
	private ?bool $hasNotificationTypeColumn = null;

	public function __construct()
	{
		$this->db = new Database();
	}

	public function createUserNotification(
		int $userId,
		string $title,
		string $message,
		?string $notificationType = null,
		bool $replacePrevious = false
	): int
	{
		$notificationType = trim((string) $notificationType);
		$hasTypeColumn = $this->hasNotificationTypeColumn();

		if ($replacePrevious && $notificationType !== '' && $hasTypeColumn) {
			$this->db->prepareAndExecute(
				'DELETE FROM user_notifications WHERE user_id = ? AND notification_type = ?',
				'is',
				[$userId, $notificationType]
			);
		}

		if ($hasTypeColumn) {
			$this->db->prepareAndExecute(
				'INSERT INTO user_notifications (user_id, notification_type, title, message, created_at) VALUES (?, ?, ?, ?, NOW())',
				'isss',
				[$userId, $notificationType, trim($title), trim($message)]
			);
			return $this->db->getLastInsertId();
		}

		$this->db->prepareAndExecute(
			'INSERT INTO user_notifications (user_id, title, message, created_at) VALUES (?, ?, ?, NOW())',
			'iss',
			[$userId, trim($title), trim($message)]
		);

		return $this->db->getLastInsertId();
	}

	public function getUserNotifications(int $userId, int $limit = 10): array
	{
		$limit = max(1, min(50, $limit));
		if ($this->hasNotificationTypeColumn()) {
			$stmt = $this->db->prepareAndExecute(
				"SELECT id, notification_type, title, message, created_at FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT {$limit}",
				'i',
				[$userId]
			);
		} else {
			$stmt = $this->db->prepareAndExecute(
				"SELECT id, NULL AS notification_type, title, message, created_at FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT {$limit}",
				'i',
				[$userId]
			);
		}
		$result = $stmt->get_result();
		$items = [];
		while ($row = $result->fetch_assoc()) {
			$items[] = $row;
		}

		return $items;
	}

	public function sendEmailToUser(string $toEmail, string $subject, string $body): void
	{
		if (!class_exists(PHPMailer::class)) {
			throw new Exception('PHPMailer не установлен');
		}

		$mailer = new PHPMailer(true);
		try {
			$mailer->isSMTP();
			$mailer->CharSet = 'UTF-8';
			$mailer->Host = (string) SMTP_HOST;
			$mailer->Port = (int) SMTP_PORT;
			$mailer->SMTPAuth = (bool) SMTP_AUTH;
			$mailer->Username = (string) SMTP_USERNAME;
			$mailer->Password = (string) SMTP_PASSWORD;

			$encryption = strtolower((string) SMTP_ENCRYPTION);
			if ($encryption === 'ssl') {
				$mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
			} elseif ($encryption === 'tls') {
				$mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
			}

			$mailer->setFrom((string) SUPPORT_FROM_EMAIL, 'BelCouch');
			$mailer->addAddress($toEmail);
			$mailer->Subject = $subject;
			$mailer->Body = $body;
			$mailer->send();
		} catch (PHPMailerException $exception) {
			throw new Exception('Не удалось отправить email: ' . $exception->getMessage());
		}
	}

	public function deleteUserNotification(int $userId, int $notificationId): bool
	{
		$stmt = $this->db->prepareAndExecute(
			'DELETE FROM user_notifications WHERE id = ? AND user_id = ?',
			'ii',
			[$notificationId, $userId]
		);

		return $stmt->affected_rows > 0;
	}

	private function hasNotificationTypeColumn(): bool
	{
		if ($this->hasNotificationTypeColumn !== null) {
			return $this->hasNotificationTypeColumn;
		}

		$result = $this->db->query("SHOW COLUMNS FROM user_notifications LIKE 'notification_type'");
		$this->hasNotificationTypeColumn = $result && $result->num_rows > 0;

		return $this->hasNotificationTypeColumn;
	}
}
