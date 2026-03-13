<?php

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

class SupportService
{
	private Database $db;

	public function __construct()
	{
		$this->db = new Database();
	}

	public function getSupportRecipientEmail(): string
	{
		return defined('SUPPORT_EMAIL') ? (string) SUPPORT_EMAIL : '';
	}

	public function getSupportChatAdmin(): ?array
	{
		$configEmail = trim((string) (defined('SUPPORT_CHAT_ADMIN_EMAIL') ? SUPPORT_CHAT_ADMIN_EMAIL : ''));
		if ($configEmail !== '') {
			$stmt = $this->db->prepareAndExecute(
				"SELECT id, email, first_name, last_name
                 FROM users
                 WHERE email = ? AND role = 'admin'
                 LIMIT 1",
				's',
				[$configEmail]
			);
			$row = $stmt->get_result()->fetch_assoc();
			if ($row) {
				return $row;
			}
		}

		$row = $this->db->getRow("SELECT id, email, first_name, last_name FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
		return $row ?: null;
	}

	public function sendSupportEmail(string $senderEmail, string $senderName, string $subject, string $message, ?int $userId = null): void
	{
		if (!class_exists(PHPMailer::class)) {
			throw new Exception('SMTP-библиотека не установлена. Выполните установку зависимостей Composer.');
		}

		$recipient = $this->getSupportRecipientEmail();
		if ($recipient === '') {
			throw new Exception('Не настроен адрес поддержки');
		}

		$senderEmail = trim($senderEmail);
		$senderName = trim($senderName);
		$subject = trim($subject);
		$message = trim($message);

		if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
			throw new Exception('Укажите корректный email');
		}

		if ($senderName === '') {
			throw new Exception('Укажите ваше имя');
		}

		if ($subject === '') {
			throw new Exception('Укажите тему обращения');
		}

		if ($message === '') {
			throw new Exception('Введите сообщение');
		}

		$body = "Новое обращение в поддержку\n\n"
			. "Имя: {$senderName}\n"
			. "Email: {$senderEmail}\n"
			. "ID пользователя: " . ($userId ? (string) $userId : 'гость') . "\n"
			. "Тема: {$subject}\n\n"
			. "Сообщение:\n{$message}\n";

		$fromEmail = defined('SUPPORT_FROM_EMAIL') ? (string) SUPPORT_FROM_EMAIL : $recipient;

		try {
			$mailer = new PHPMailer(true);
			$mailer->isSMTP();
			$mailer->CharSet = 'UTF-8';
			$mailer->Host = (string) SMTP_HOST;
			$mailer->Port = (int) SMTP_PORT;
			$mailer->SMTPAuth = (bool) SMTP_AUTH;
			$mailer->Username = (string) SMTP_USERNAME;
			$mailer->Password = (string) SMTP_PASSWORD;

			$encryption = strtolower(trim((string) SMTP_ENCRYPTION));
			if ($encryption === 'ssl') {
				$mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
			} elseif ($encryption === 'tls') {
				$mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
			}

			$mailer->setFrom($fromEmail, 'BelCouch');
			$mailer->addAddress($recipient);
			$mailer->addReplyTo($senderEmail, $senderName);
			$mailer->Subject = 'BelCouch Support: ' . $subject;
			$mailer->Body = $body;
			$mailer->send();
		} catch (PHPMailerException $exception) {
			throw new Exception('Не удалось отправить письмо через SMTP: ' . $exception->getMessage());
		}
	}
}
