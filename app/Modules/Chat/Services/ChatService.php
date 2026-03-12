<?php

class ChatService
{
	private Database $db;

	public function __construct()
	{
		$this->db = new Database();
		$this->ensureMessageAttachmentsTableExists();
	}

	public function getConversationList(int $currentUserId): array
	{
		$stmt = $this->db->prepareAndExecute(
			'SELECT
                convo.partner_id,
                u.first_name,
                u.last_name,
                (u.avatar_image IS NOT NULL) AS has_avatar,
                u.is_verify,
                u.is_online,
                ud.city,
                r.name AS region_name,
                lm.id AS message_id,
                lm.sender_id,
                lm.receiver_id,
                lm.listing_id,
                lm.message,
                lm.is_read,
                lm.created_at,
                lm.edited_at,
                lm.image_path,
                lm.deleted_at,
                COALESCE(unread.unread_count, 0) AS unread_count
            FROM (
                SELECT
                    grouped.partner_id,
                    MAX(grouped.id) AS last_message_id
                FROM (
                    SELECT
                        m.id,
                        CASE
                            WHEN m.sender_id = ? THEN m.receiver_id
                            ELSE m.sender_id
                        END AS partner_id
                    FROM messages m
                    WHERE m.sender_id = ? OR m.receiver_id = ?
                ) AS grouped
                GROUP BY grouped.partner_id
            ) AS convo
            JOIN messages lm ON lm.id = convo.last_message_id
            JOIN users u ON u.id = convo.partner_id
            LEFT JOIN user_details ud ON ud.user_id = u.id
            LEFT JOIN regions r ON r.id = ud.region_id
            LEFT JOIN (
                SELECT sender_id AS partner_id, COUNT(*) AS unread_count
                FROM messages
                WHERE receiver_id = ? AND is_read = 0 AND deleted_at IS NULL
                GROUP BY sender_id
            ) AS unread ON unread.partner_id = convo.partner_id
            ORDER BY lm.id DESC',
			'iiii',
			[$currentUserId, $currentUserId, $currentUserId, $currentUserId]
		);

		$result = $stmt->get_result();
		$conversations = [];

		while ($row = $result->fetch_assoc()) {
			$conversations[] = [
				'partner' => [
					'id' => (int) $row['partner_id'],
					'first_name' => $row['first_name'],
					'last_name' => $row['last_name'] ?? '',
					'full_name' => trim($row['first_name'] . ' ' . ($row['last_name'] ?? '')),
					'has_avatar' => (int) $row['has_avatar'] === 1,
					'is_verify' => (int) $row['is_verify'] === 1,
					'is_online' => (int) ($row['is_online'] ?? 0) === 1,
					'city' => $row['city'] ?? '',
					'region_name' => $row['region_name'] ?? '',
				],
				'latest_message' => [
					'id' => (int) $row['message_id'],
					'sender_id' => (int) $row['sender_id'],
					'receiver_id' => (int) $row['receiver_id'],
					'listing_id' => $row['listing_id'] !== null ? (int) $row['listing_id'] : null,
					'message' => $row['deleted_at'] !== null ? 'Сообщение удалено' : $row['message'],
					'image_path' => $row['deleted_at'] !== null ? null : ($row['image_path'] ?? null),
					'is_read' => (int) $row['is_read'] === 1,
					'created_at' => $row['created_at'],
					'edited_at' => $row['edited_at'],
					'deleted_at' => $row['deleted_at'],
					'is_deleted' => $row['deleted_at'] !== null,
					'is_outgoing' => (int) $row['sender_id'] === $currentUserId,
				],
				'unread_count' => (int) $row['unread_count'],
			];
		}

		return $conversations;
	}

	public function getUserPreview(int $userId): ?array
	{
		$stmt = $this->db->prepareAndExecute(
			'SELECT u.id, u.first_name, u.last_name, (u.avatar_image IS NOT NULL) AS has_avatar, u.is_verify, u.is_online,
                ud.city, r.name AS region_name
            FROM users u
            LEFT JOIN user_details ud ON ud.user_id = u.id
            LEFT JOIN regions r ON r.id = ud.region_id
            WHERE u.id = ?',
			'i',
			[$userId]
		);

		$user = $stmt->get_result()->fetch_assoc();
		if (!$user) {
			return null;
		}

		return [
			'id' => (int) $user['id'],
			'first_name' => $user['first_name'],
			'last_name' => $user['last_name'] ?? '',
			'full_name' => trim($user['first_name'] . ' ' . ($user['last_name'] ?? '')),
			'has_avatar' => (int) ($user['has_avatar'] ?? 0) === 1,
			'is_verify' => (int) $user['is_verify'] === 1,
			'is_online' => (int) ($user['is_online'] ?? 0) === 1,
			'city' => $user['city'] ?? '',
			'region_name' => $user['region_name'] ?? '',
		];
	}

	public function getMessages(int $currentUserId, int $partnerId, int $afterId = 0): array
	{
		$stmt = $this->db->prepareAndExecute(
			$this->getMessageSelectSql() . '
            WHERE (
                (m.sender_id = ? AND m.receiver_id = ?)
                OR
                (m.sender_id = ? AND m.receiver_id = ?)
            )
            AND m.id > ?
            ORDER BY m.id ASC',
			'iiiii',
			[$currentUserId, $partnerId, $partnerId, $currentUserId, $afterId]
		);

		$result = $stmt->get_result();
		$messages = [];
		while ($row = $result->fetch_assoc()) {
			$messages[] = $this->formatMessageRow($row, $currentUserId);
		}

		return $this->hydrateMessagesWithAttachments($messages);
	}

	public function markConversationAsRead(int $currentUserId, int $partnerId): array
	{
		$idsStmt = $this->db->prepareAndExecute(
			'SELECT id
            FROM messages
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
            ORDER BY id ASC',
			'ii',
			[$partnerId, $currentUserId]
		);

		$result = $idsStmt->get_result();
		$messageIds = [];
		while ($row = $result->fetch_assoc()) {
			$messageIds[] = (int) $row['id'];
		}

		if (!empty($messageIds)) {
			$this->db->prepareAndExecute(
				'UPDATE messages
                SET is_read = 1
                WHERE sender_id = ? AND receiver_id = ? AND is_read = 0',
				'ii',
				[$partnerId, $currentUserId]
			);
		}

		return $messageIds;
	}

	public function sendMessage(
		int $currentUserId,
		int $partnerId,
		string $message,
		?int $listingId = null,
		?int $replyToMessageId = null,
		array $attachments = []
	): array {
		$message = trim($message ?? '');
		if ($partnerId <= 0 || $partnerId === $currentUserId) {
			throw new InvalidArgumentException('Некорректный получатель сообщения');
		}

		if ($message === '' && empty($attachments)) {
			throw new InvalidArgumentException('Сообщение или вложения не могут быть пустыми');
		}

		if (mb_strlen($message) > 2000) {
			throw new InvalidArgumentException('Сообщение слишком длинное');
		}

		if ($this->getUserPreview($partnerId) === null) {
			throw new InvalidArgumentException('Получатель не найден');
		}

		$replyTargetId = $this->resolveReplyTargetId($currentUserId, $partnerId, $replyToMessageId);
		$primaryImagePath = !empty($attachments) ? (string) ($attachments[0]['file_path'] ?? '') : null;

		$sql = 'INSERT INTO messages (sender_id, receiver_id, listing_id, message, image_path, is_read, created_at, reply_to_message_id) VALUES (?, ?, ?, ?, ?, 0, NOW(), ?)';
		$types = 'iiissi';
		$params = [
			$currentUserId,
			$partnerId,
			$listingId,
			$message,
			$primaryImagePath,
			$replyTargetId
		];

		$this->db->query('START TRANSACTION');

		try {
			$stmt = $this->db->prepareAndExecute($sql, $types, $params);
			$messageId = (int) $stmt->insert_id;

			$this->saveMessageAttachments($messageId, $attachments);

			$this->db->query('COMMIT');
			return $this->getMessageByIdForUser($messageId, $currentUserId);
		} catch (Throwable $exception) {
			$this->db->query('ROLLBACK');
			throw $exception;
		}
	}

	public function updateMessage(int $currentUserId, int $messageId, string $message): array
	{
		$message = trim($message);
		if ($message === '') {
			throw new InvalidArgumentException('Сообщение не может быть пустым');
		}

		if (mb_strlen($message) > 2000) {
			throw new InvalidArgumentException('Сообщение слишком длинное');
		}

		$messageRow = $this->getOwnedMessageRow($currentUserId, $messageId);
		if (!$messageRow) {
			throw new InvalidArgumentException('Сообщение не найдено');
		}

		if ($messageRow['deleted_at'] !== null) {
			throw new InvalidArgumentException('Удаленное сообщение нельзя редактировать');
		}

		$this->db->prepareAndExecute(
			'UPDATE messages
            SET message = ?, edited_at = NOW()
            WHERE id = ? AND sender_id = ?',
			'sii',
			[$message, $messageId, $currentUserId]
		);

		return $this->getMessageByIdForUser($messageId, $currentUserId);
	}

	public function deleteMessage(int $currentUserId, int $messageId): array
	{
		$messageRow = $this->getOwnedMessageRow($currentUserId, $messageId);
		if (!$messageRow) {
			throw new InvalidArgumentException('Сообщение не найдено');
		}

		if ($messageRow['deleted_at'] !== null) {
			return $this->getMessageByIdForUser($messageId, $currentUserId);
		}

		$this->db->prepareAndExecute(
			'UPDATE messages
            SET deleted_at = NOW(), edited_at = NULL, message = "", image_path = NULL
            WHERE id = ? AND sender_id = ?',
			'ii',
			[$messageId, $currentUserId]
		);

		$this->db->prepareAndExecute(
			'DELETE FROM message_attachments WHERE message_id = ?',
			'i',
			[$messageId]
		);

		return $this->getMessageByIdForUser($messageId, $currentUserId);
	}

	public function getMessageParticipants(int $messageId): ?array
	{
		$stmt = $this->db->prepareAndExecute(
			'SELECT sender_id, receiver_id
            FROM messages
            WHERE id = ?',
			'i',
			[$messageId]
		);

		$row = $stmt->get_result()->fetch_assoc();
		if (!$row) {
			return null;
		}

		return [
			'sender_id' => (int) $row['sender_id'],
			'receiver_id' => (int) $row['receiver_id'],
		];
	}

	public function getMessageByIdForUser(int $messageId, int $currentUserId): array
	{
		$stmt = $this->db->prepareAndExecute(
			$this->getMessageSelectSql() . '
            WHERE m.id = ?
            AND (m.sender_id = ? OR m.receiver_id = ?)
            LIMIT 1',
			'iii',
			[$messageId, $currentUserId, $currentUserId]
		);

		$row = $stmt->get_result()->fetch_assoc();
		if (!$row) {
			throw new RuntimeException('Не удалось получить сообщение');
		}

		$messages = $this->hydrateMessagesWithAttachments([
			$this->formatMessageRow($row, $currentUserId)
		]);

		return $messages[0];
	}

	private function getOwnedMessageRow(int $currentUserId, int $messageId): ?array
	{
		$stmt = $this->db->prepareAndExecute(
			'SELECT id, sender_id, receiver_id, deleted_at
            FROM messages
            WHERE id = ? AND sender_id = ?
            LIMIT 1',
			'ii',
			[$messageId, $currentUserId]
		);

		$row = $stmt->get_result()->fetch_assoc();
		return $row ?: null;
	}

	private function resolveReplyTargetId(
		int $currentUserId,
		int $partnerId,
		?int $replyToMessageId
	): ?int {
		if ($replyToMessageId === null || $replyToMessageId <= 0) {
			return null;
		}

		$stmt = $this->db->prepareAndExecute(
			'SELECT id
            FROM messages
            WHERE id = ?
            AND (
                (sender_id = ? AND receiver_id = ?)
                OR
                (sender_id = ? AND receiver_id = ?)
            )
            LIMIT 1',
			'iiiii',
			[$replyToMessageId, $currentUserId, $partnerId, $partnerId, $currentUserId]
		);

		$row = $stmt->get_result()->fetch_assoc();
		if (!$row) {
			throw new InvalidArgumentException('Сообщение для ответа не найдено');
		}

		return (int) $row['id'];
	}

	private function getMessageSelectSql(): string
	{
		return 'SELECT
                m.id,
                m.sender_id,
                m.receiver_id,
                m.listing_id,
                m.message,
                m.is_read,
                m.created_at,
                m.edited_at,
                m.deleted_at,
                m.reply_to_message_id,
                m.image_path,
                sender.first_name AS sender_first_name,
                sender.last_name AS sender_last_name,
                (sender.avatar_image IS NOT NULL) AS sender_has_avatar,
                reply.id AS reply_id,
                reply.message AS reply_message,
                reply.deleted_at AS reply_deleted_at,
                reply.sender_id AS reply_sender_id,
                reply.image_path AS reply_image_path,
                reply_sender.first_name AS reply_sender_first_name,
                reply_sender.last_name AS reply_sender_last_name
            FROM messages m
            JOIN users sender ON sender.id = m.sender_id
            LEFT JOIN messages reply ON reply.id = m.reply_to_message_id
            LEFT JOIN users reply_sender ON reply_sender.id = reply.sender_id';
	}

	private function formatMessageRow(array $row, int $currentUserId): array
	{
		$replyPreview = null;
		if (!empty($row['reply_id'])) {
			$replyPreview = [
				'id' => (int) $row['reply_id'],
				'sender_id' => (int) $row['reply_sender_id'],
				'sender_name' => trim(($row['reply_sender_first_name'] ?? '') . ' ' . ($row['reply_sender_last_name'] ?? '')),
				'message' => $row['reply_deleted_at'] !== null ? 'Сообщение удалено' : (string) ($row['reply_message'] ?? ''),
				'is_deleted' => $row['reply_deleted_at'] !== null,
				'is_outgoing' => (int) $row['reply_sender_id'] === $currentUserId,
				'image_path' => $row['reply_deleted_at'] !== null ? null : ($row['reply_image_path'] ?? null),
			];
		}

		$canEdit = (int) $row['sender_id'] === $currentUserId && $row['deleted_at'] === null;
		$canDelete = (int) $row['sender_id'] === $currentUserId;

		return [
			'id' => (int) $row['id'],
			'sender_id' => (int) $row['sender_id'],
			'receiver_id' => (int) $row['receiver_id'],
			'listing_id' => $row['listing_id'] !== null ? (int) $row['listing_id'] : null,
			'message' => $row['deleted_at'] !== null ? '' : $row['message'],
			'is_read' => (bool) $row['is_read'],
			'created_at' => $row['created_at'],
			'edited_at' => $row['edited_at'],
			'deleted_at' => $row['deleted_at'],
			'can_edit' => $canEdit,
			'can_delete' => $canDelete,
			'reply_preview' => $replyPreview,
			'reply_to_message_id' => $row['reply_to_message_id'] !== null ? (int) $row['reply_to_message_id'] : null,
			'is_outgoing' => (int) $row['sender_id'] === $currentUserId,
			'sender_name' => trim(($row['sender_first_name'] ?? '') . ' ' . ($row['sender_last_name'] ?? '')),
			'sender_has_avatar' => (int) ($row['sender_has_avatar'] ?? 0) === 1,
			'image_path' => $row['deleted_at'] !== null ? null : ($row['image_path'] ?? null),
			'attachments' => [],
		];
	}

	private function saveMessageAttachments(int $messageId, array $attachments): void
	{
		if (empty($attachments)) {
			return;
		}

		foreach (array_values($attachments) as $index => $attachment) {
			$filePath = trim((string) ($attachment['file_path'] ?? ''));
			$fileType = trim((string) ($attachment['file_type'] ?? ''));

			if ($filePath === '' || !in_array($fileType, ['image', 'video'], true)) {
				continue;
			}

			$this->db->prepareAndExecute(
				'INSERT INTO message_attachments (message_id, file_path, file_type, sort_order, created_at)
                VALUES (?, ?, ?, ?, NOW())',
				'issi',
				[$messageId, $filePath, $fileType, $index]
			);
		}
	}

	private function hydrateMessagesWithAttachments(array $messages): array
	{
		if (empty($messages)) {
			return [];
		}

		$messageIds = array_map(static function (array $message): int {
			return (int) $message['id'];
		}, $messages);

		$attachmentsMap = $this->getAttachmentsMap($messageIds);

		foreach ($messages as &$message) {
			$messageId = (int) $message['id'];
			$attachments = $attachmentsMap[$messageId] ?? [];

			if (empty($attachments) && !empty($message['image_path'])) {
				$attachments = [[
					'file_path' => $message['image_path'],
					'file_type' => $this->detectAttachmentType((string) $message['image_path']),
				]];
			}

			$message['attachments'] = $message['deleted_at'] !== null ? [] : $attachments;
		}
		unset($message);

		return $messages;
	}

	private function getAttachmentsMap(array $messageIds): array
	{
		$messageIds = array_values(array_filter(array_map('intval', $messageIds)));
		if (empty($messageIds)) {
			return [];
		}

		$placeholders = implode(', ', array_fill(0, count($messageIds), '?'));
		$types = str_repeat('i', count($messageIds));
		$stmt = $this->db->prepareAndExecute(
			'SELECT message_id, file_path, file_type
            FROM message_attachments
            WHERE message_id IN (' . $placeholders . ')
            ORDER BY message_id ASC, sort_order ASC, id ASC',
			$types,
			$messageIds
		);

		$result = $stmt->get_result();
		$attachmentsMap = [];

		while ($row = $result->fetch_assoc()) {
			$messageId = (int) $row['message_id'];
			if (!isset($attachmentsMap[$messageId])) {
				$attachmentsMap[$messageId] = [];
			}

			$attachmentsMap[$messageId][] = [
				'file_path' => (string) $row['file_path'],
				'file_type' => in_array($row['file_type'], ['image', 'video'], true)
					? $row['file_type']
					: $this->detectAttachmentType((string) $row['file_path']),
			];
		}

		return $attachmentsMap;
	}

	private function detectAttachmentType(string $filePath): string
	{
		return preg_match('/\.(mp4|webm|mov|qt)$/i', $filePath) ? 'video' : 'image';
	}

	private function ensureMessageAttachmentsTableExists(): void
	{
		$this->db->query(
			'CREATE TABLE IF NOT EXISTS message_attachments (
                id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                message_id int(11) NOT NULL,
                file_path varchar(255) NOT NULL,
                file_type enum("image","video") NOT NULL,
                sort_order int(11) NOT NULL DEFAULT 0,
                created_at timestamp NOT NULL DEFAULT current_timestamp(),
                CONSTRAINT fk_message_attachments_message_id
                    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
                INDEX idx_message_attachments_message_id_sort_order (message_id, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
		);
	}
}
