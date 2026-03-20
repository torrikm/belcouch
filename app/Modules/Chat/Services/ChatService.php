<?php

class ChatService
{
	private Database $db;

	public function __construct()
	{
		$this->db = new Database();
		$this->ensureSupportChatSchemaExists();
		$this->ensureSupportChatLocksTableExists();
		$this->ensureMessageAttachmentsTableExists();
	}

	public function getConversationList(int $currentUserId): array
	{
		$conversations = array_merge(
			$this->getRegularConversationList($currentUserId),
			$this->getSupportConversationList($currentUserId)
		);

		usort($conversations, static function (array $left, array $right): int {
			return (int) (($right['latest_message']['id'] ?? 0)) <=> (int) (($left['latest_message']['id'] ?? 0));
		});

		return $conversations;
	}

	public function getUserPreview(int $userId): ?array
	{
		$stmt = $this->db->prepareAndExecute(
			'SELECT u.id, u.first_name, u.last_name, u.role, (u.avatar_image IS NOT NULL) AS has_avatar, u.is_verify, u.is_online,
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
			'role' => (string) ($user['role'] ?? 'user'),
			'is_admin' => (string) ($user['role'] ?? 'user') === 'admin',
			'has_avatar' => (int) ($user['has_avatar'] ?? 0) === 1,
			'is_verify' => (int) $user['is_verify'] === 1,
			'is_online' => (int) ($user['is_online'] ?? 0) === 1,
			'city' => $user['city'] ?? '',
			'region_name' => $user['region_name'] ?? '',
		];
	}

	public function getMessages(int $currentUserId, int $partnerId, int $afterId = 0, bool $isSupport = false): array
	{
		if ($isSupport) {
			$this->assertSupportConversationAccess($currentUserId, $partnerId);
			$supportUserId = $this->resolveSupportConversationUserId($currentUserId, $partnerId);
			$stmt = $this->db->prepareAndExecute(
				$this->getMessageSelectSql() . '
            WHERE m.is_support = 1
            AND m.support_user_id = ?
            AND m.id > ?
            ORDER BY m.id ASC',
				'ii',
				[$supportUserId, $afterId]
			);
		} else {
			$stmt = $this->db->prepareAndExecute(
				$this->getMessageSelectSql() . '
            WHERE m.is_support = 0
            AND (
                (m.sender_id = ? AND m.receiver_id = ?)
                OR
                (m.sender_id = ? AND m.receiver_id = ?)
            )
            AND m.id > ?
            ORDER BY m.id ASC',
				'iiiii',
				[$currentUserId, $partnerId, $partnerId, $currentUserId, $afterId]
			);
		}

		$result = $stmt->get_result();
		$messages = [];
		while ($row = $result->fetch_assoc()) {
			$messages[] = $this->formatMessageRow($row, $currentUserId);
		}

		return $this->hydrateMessagesWithAttachments($messages);
	}

	public function getMessagesWindow(
		int $currentUserId,
		int $partnerId,
		array $options = [],
		bool $isSupport = false
	): array {
		$afterId = max(0, (int) ($options['after_id'] ?? 0));
		$beforeId = max(0, (int) ($options['before_id'] ?? 0));
		$aroundId = max(0, (int) ($options['around_id'] ?? 0));
		$limit = max(1, min(100, (int) ($options['limit'] ?? 50)));
		$context = max(1, min(50, (int) ($options['context'] ?? 25)));

		if ($aroundId > 0) {
			$messages = $this->getMessagesAroundAnchor($currentUserId, $partnerId, $aroundId, $context, $isSupport);
			return [
				'messages' => $messages,
				'meta' => $this->buildWindowMeta($currentUserId, $partnerId, $messages, $isSupport, $aroundId, 'around'),
			];
		}

		if ($beforeId > 0) {
			$messages = $this->getMessagesBeforeId($currentUserId, $partnerId, $beforeId, $limit, $isSupport);
			return [
				'messages' => $messages,
				'meta' => $this->buildWindowMeta($currentUserId, $partnerId, $messages, $isSupport, null, 'before'),
			];
		}

		if ($afterId > 0) {
			$messages = $this->getMessagesAfterId($currentUserId, $partnerId, $afterId, $limit, $isSupport);
			return [
				'messages' => $messages,
				'meta' => $this->buildWindowMeta($currentUserId, $partnerId, $messages, $isSupport, null, 'after'),
			];
		}

		$messages = $this->getLatestMessages($currentUserId, $partnerId, $limit, $isSupport);
		return [
			'messages' => $messages,
			'meta' => $this->buildWindowMeta($currentUserId, $partnerId, $messages, $isSupport, null, 'latest'),
		];
	}

	public function markConversationAsRead(int $currentUserId, int $partnerId, bool $isSupport = false): array
	{
		if ($isSupport) {
			$this->assertSupportConversationAccess($currentUserId, $partnerId);
			return $this->markSupportConversationAsRead($currentUserId, $partnerId);
		}

		$idsStmt = $this->db->prepareAndExecute(
			'SELECT id
            FROM messages
            WHERE is_support = 0 AND sender_id = ? AND receiver_id = ? AND is_read = 0
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
                WHERE is_support = 0 AND sender_id = ? AND receiver_id = ? AND is_read = 0',
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
		array $attachments = [],
		bool $isSupport = false
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

		$replyTargetId = $this->resolveReplyTargetId($currentUserId, $partnerId, $replyToMessageId, $isSupport);
		$primaryImagePath = !empty($attachments) ? (string) ($attachments[0]['file_path'] ?? '') : null;
		$supportUserId = null;

		if ($isSupport) {
			$this->assertSupportConversationAccess($currentUserId, $partnerId);
			$supportUserId = $this->resolveSupportConversationUserId($currentUserId, $partnerId);
			$listingId = null;
		}

		$sql = 'INSERT INTO messages (sender_id, receiver_id, listing_id, message, image_path, is_read, created_at, reply_to_message_id, is_support, support_user_id) VALUES (?, ?, ?, ?, ?, 0, NOW(), ?, ?, ?)';
		$types = 'iiissiii';
		$params = [
			$currentUserId,
			$partnerId,
			$listingId,
			$message,
			$primaryImagePath,
			$replyTargetId,
			$isSupport ? 1 : 0,
			$supportUserId
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

	public function acquireSupportConversationLock(int $currentUserId, int $partnerId): array
	{
		if (!$this->isAdminUser($currentUserId)) {
			throw new InvalidArgumentException('Только администратор может занять чат поддержки');
		}

		$this->cleanupExpiredSupportConversationLocks();
		$supportUserId = $this->resolveSupportConversationUserId($currentUserId, $partnerId);
		$lock = $this->getSupportConversationLockRow($supportUserId);

		if ($lock !== null && (int) ($lock['admin_user_id'] ?? 0) !== $currentUserId) {
			return [
				'acquired' => false,
				'lock' => $this->formatSupportConversationLock($lock, $currentUserId),
			];
		}

		$this->db->prepareAndExecute(
			'INSERT INTO support_chat_locks (support_user_id, admin_user_id, expires_at, created_at, updated_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 SECOND), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                admin_user_id = VALUES(admin_user_id),
                expires_at = VALUES(expires_at),
                updated_at = NOW()',
			'ii',
			[$supportUserId, $currentUserId]
		);

		return [
			'acquired' => true,
			'lock' => $this->getSupportConversationLock($currentUserId, $partnerId),
		];
	}

	public function refreshSupportConversationLock(int $currentUserId, int $partnerId): array
	{
		return $this->acquireSupportConversationLock($currentUserId, $partnerId);
	}

	public function releaseSupportConversationLock(int $currentUserId, int $partnerId): void
	{
		if (!$this->isAdminUser($currentUserId)) {
			return;
		}

		$this->cleanupExpiredSupportConversationLocks();
		$supportUserId = $this->resolveSupportConversationUserId($currentUserId, $partnerId);
		$this->db->prepareAndExecute(
			'DELETE FROM support_chat_locks WHERE support_user_id = ? AND admin_user_id = ?',
			'ii',
			[$supportUserId, $currentUserId]
		);
	}

	public function getSupportConversationLock(int $currentUserId, int $partnerId): ?array
	{
		$this->cleanupExpiredSupportConversationLocks();
		$supportUserId = $this->resolveSupportConversationUserId($currentUserId, $partnerId);
		return $this->formatSupportConversationLock(
			$this->getSupportConversationLockRow($supportUserId),
			$currentUserId
		);
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

	private function getLatestMessages(int $currentUserId, int $partnerId, int $limit, bool $isSupport = false): array
	{
		return $this->getMessagesByWindowMode($currentUserId, $partnerId, 'latest', 0, $limit, $isSupport);
	}

	private function getMessagesBeforeId(int $currentUserId, int $partnerId, int $beforeId, int $limit, bool $isSupport = false): array
	{
		return $this->getMessagesByWindowMode($currentUserId, $partnerId, 'before', $beforeId, $limit, $isSupport);
	}

	private function getMessagesAfterId(int $currentUserId, int $partnerId, int $afterId, int $limit, bool $isSupport = false): array
	{
		return $this->getMessagesByWindowMode($currentUserId, $partnerId, 'after', $afterId, $limit, $isSupport);
	}

	private function getMessagesAroundAnchor(
		int $currentUserId,
		int $partnerId,
		int $anchorId,
		int $context,
		bool $isSupport = false
	): array {
		$this->assertMessageBelongsToConversation($currentUserId, $partnerId, $anchorId, $isSupport);
		$before = $this->getMessagesBeforeId($currentUserId, $partnerId, $anchorId, $context, $isSupport);
		$missingBefore = max(0, $context - count($before));
		$after = $this->getMessagesAfterId($currentUserId, $partnerId, $anchorId, $context + $missingBefore, $isSupport);
		$anchor = [$this->getMessageByIdForUser($anchorId, $currentUserId)];

		if (count($after) < $context + $missingBefore) {
			$extraBefore = $this->getMessagesBeforeId(
				$currentUserId,
				$partnerId,
				$before ? (int) $before[0]['id'] : $anchorId,
				($context + $missingBefore) - count($after),
				$isSupport
			);
			$before = array_merge($extraBefore, $before);
		}

		return array_merge($before, $anchor, $after);
	}

	private function getMessagesByWindowMode(
		int $currentUserId,
		int $partnerId,
		string $mode,
		int $cursorId,
		int $limit,
		bool $isSupport = false
	): array {
		$limit = max(1, min(100, $limit));
		$params = [];
		$types = '';

		if ($isSupport) {
			$this->assertSupportConversationAccess($currentUserId, $partnerId);
			$supportUserId = $this->resolveSupportConversationUserId($currentUserId, $partnerId);
			$where = 'm.is_support = 1 AND m.support_user_id = ?';
			$params[] = $supportUserId;
			$types .= 'i';
		} else {
			$where = 'm.is_support = 0 AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))';
			array_push($params, $currentUserId, $partnerId, $partnerId, $currentUserId);
			$types .= 'iiii';
		}

		$order = 'm.id DESC';
		if ($mode === 'before') {
			$where .= ' AND m.id < ?';
			$params[] = $cursorId;
			$types .= 'i';
		} elseif ($mode === 'after') {
			$where .= ' AND m.id > ?';
			$params[] = $cursorId;
			$types .= 'i';
			$order = 'm.id ASC';
		} elseif ($mode === 'latest') {
			// no-op
		} else {
			throw new InvalidArgumentException('Unsupported window mode');
		}

		$stmt = $this->db->prepareAndExecute(
			$this->getMessageSelectSql() . '
            WHERE ' . $where . '
            ORDER BY ' . $order . '
            LIMIT ?',
			$types . 'i',
			array_merge($params, [$limit])
		);

		$result = $stmt->get_result();
		$messages = [];
		while ($row = $result->fetch_assoc()) {
			$messages[] = $this->formatMessageRow($row, $currentUserId);
		}

		if ($mode !== 'after') {
			$messages = array_reverse($messages);
		}
		return $this->hydrateMessagesWithAttachments($messages);
	}

	private function buildWindowMeta(
		int $currentUserId,
		int $partnerId,
		array $messages,
		bool $isSupport = false,
		?int $anchorId = null,
		string $mode = 'latest'
	): array {
		$oldestId = !empty($messages) ? (int) $messages[0]['id'] : 0;
		$newestId = !empty($messages) ? (int) $messages[count($messages) - 1]['id'] : 0;

		return [
			'mode' => $mode,
			'anchor_message_id' => $anchorId,
			'oldest_id' => $oldestId,
			'newest_id' => $newestId,
			'loaded_count' => count($messages),
			'has_older' => $oldestId > 0 ? $this->conversationHasOlderMessages($currentUserId, $partnerId, $oldestId, $isSupport) : false,
			'has_newer' => $newestId > 0 ? $this->conversationHasNewerMessages($currentUserId, $partnerId, $newestId, $isSupport) : false,
		];
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

	private function assertMessageBelongsToConversation(
		int $currentUserId,
		int $partnerId,
		int $messageId,
		bool $isSupport = false
	): void {
		if ($messageId <= 0) {
			throw new InvalidArgumentException('Сообщение не найдено');
		}

		if ($isSupport) {
			$supportUserId = $this->resolveSupportConversationUserId($currentUserId, $partnerId);
			$stmt = $this->db->prepareAndExecute(
				'SELECT id
                FROM messages
                WHERE id = ? AND is_support = 1 AND support_user_id = ?
                LIMIT 1',
				'ii',
				[$messageId, $supportUserId]
			);
		} else {
			$stmt = $this->db->prepareAndExecute(
				'SELECT id
                FROM messages
                WHERE id = ?
                  AND is_support = 0
                  AND (
                    (sender_id = ? AND receiver_id = ?)
                    OR
                    (sender_id = ? AND receiver_id = ?)
                  )
                LIMIT 1',
				'iiiii',
				[$messageId, $currentUserId, $partnerId, $partnerId, $currentUserId]
			);
		}

		if (!$stmt->get_result()->fetch_assoc()) {
			throw new InvalidArgumentException('Сообщение для перехода не найдено');
		}
	}

	private function conversationHasOlderMessages(
		int $currentUserId,
		int $partnerId,
		int $messageId,
		bool $isSupport = false
	): bool {
		return $this->conversationHasMessagesByDirection($currentUserId, $partnerId, $messageId, '<', $isSupport);
	}

	private function conversationHasNewerMessages(
		int $currentUserId,
		int $partnerId,
		int $messageId,
		bool $isSupport = false
	): bool {
		return $this->conversationHasMessagesByDirection($currentUserId, $partnerId, $messageId, '>', $isSupport);
	}

	private function conversationHasMessagesByDirection(
		int $currentUserId,
		int $partnerId,
		int $messageId,
		string $operator,
		bool $isSupport = false
	): bool {
		$operator = $operator === '>' ? '>' : '<';

		if ($isSupport) {
			$supportUserId = $this->resolveSupportConversationUserId($currentUserId, $partnerId);
			$stmt = $this->db->prepareAndExecute(
				'SELECT id
                FROM messages
                WHERE is_support = 1
                  AND support_user_id = ?
                  AND id ' . $operator . ' ?
                LIMIT 1',
				'ii',
				[$supportUserId, $messageId]
			);
		} else {
			$stmt = $this->db->prepareAndExecute(
				'SELECT id
                FROM messages
                WHERE is_support = 0
                  AND (
                    (sender_id = ? AND receiver_id = ?)
                    OR
                    (sender_id = ? AND receiver_id = ?)
                  )
                  AND id ' . $operator . ' ?
                LIMIT 1',
				'iiiii',
				[$currentUserId, $partnerId, $partnerId, $currentUserId, $messageId]
			);
		}

		return (bool) $stmt->get_result()->fetch_assoc();
	}

	private function resolveReplyTargetId(
		int $currentUserId,
		int $partnerId,
		?int $replyToMessageId,
		bool $isSupport = false
	): ?int {
		if ($replyToMessageId === null || $replyToMessageId <= 0) {
			return null;
		}

		if ($isSupport) {
			$supportUserId = $this->resolveSupportConversationUserId($currentUserId, $partnerId);
			$stmt = $this->db->prepareAndExecute(
				'SELECT id
                FROM messages
                WHERE id = ?
                  AND is_support = 1
                  AND support_user_id = ?
                LIMIT 1',
				'ii',
				[$replyToMessageId, $supportUserId]
			);
		} else {
			$stmt = $this->db->prepareAndExecute(
				'SELECT id
                FROM messages
                WHERE id = ?
                  AND is_support = 0
                  AND (
                    (sender_id = ? AND receiver_id = ?)
                    OR
                    (sender_id = ? AND receiver_id = ?)
                  )
                LIMIT 1',
				'iiiii',
				[$replyToMessageId, $currentUserId, $partnerId, $partnerId, $currentUserId]
			);
		}

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

	private function getRegularConversationList(int $currentUserId): array
	{
		$stmt = $this->db->prepareAndExecute(
			'SELECT
                convo.partner_id,
                u.first_name,
                u.last_name,
                u.role,
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
                    WHERE m.is_support = 0
                      AND (m.sender_id = ? OR m.receiver_id = ?)
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
                WHERE is_support = 0 AND receiver_id = ? AND is_read = 0 AND deleted_at IS NULL
                GROUP BY sender_id
            ) AS unread ON unread.partner_id = convo.partner_id
            ORDER BY lm.id DESC',
			'iiii',
			[$currentUserId, $currentUserId, $currentUserId, $currentUserId]
		);

		$result = $stmt->get_result();
		$conversations = [];
		while ($row = $result->fetch_assoc()) {
			$conversations[] = $this->formatConversationRow($row, $currentUserId, false);
		}

		return $conversations;
	}

	private function getSupportConversationList(int $currentUserId): array
	{
		if ($this->isAdminUser($currentUserId)) {
			$stmt = $this->db->prepareAndExecute(
				'SELECT
                    convo.support_user_id AS partner_id,
                    u.first_name,
                    u.last_name,
                    u.role,
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
                    SELECT support_user_id, MAX(id) AS last_message_id
                    FROM messages
                    WHERE is_support = 1 AND support_user_id IS NOT NULL
                    GROUP BY support_user_id
                ) AS convo
                JOIN messages lm ON lm.id = convo.last_message_id
                JOIN users u ON u.id = convo.support_user_id
                LEFT JOIN user_details ud ON ud.user_id = u.id
                LEFT JOIN regions r ON r.id = ud.region_id
                LEFT JOIN (
                    SELECT support_user_id, COUNT(*) AS unread_count
                    FROM messages
                    WHERE is_support = 1
                      AND support_user_id IS NOT NULL
                      AND sender_id = support_user_id
                      AND is_read = 0
                      AND deleted_at IS NULL
                    GROUP BY support_user_id
                ) AS unread ON unread.support_user_id = convo.support_user_id
                ORDER BY lm.id DESC',
				'',
				[]
			);

			$result = $stmt->get_result();
			$conversations = [];
			while ($row = $result->fetch_assoc()) {
				$conversations[] = $this->formatConversationRow($row, $currentUserId, true);
			}

			return $conversations;
		}

		$supportPreview = $this->buildSupportTeamPreview();
		$supportAdmin = $this->getDefaultSupportAdminPreview();
		$targetUserId = (int) (($supportAdmin['id'] ?? 0));

		$stmt = $this->db->prepareAndExecute(
			'SELECT
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
                SELECT MAX(id) AS last_message_id
                FROM messages
                WHERE is_support = 1 AND support_user_id = ?
            ) AS convo
            LEFT JOIN messages lm ON lm.id = convo.last_message_id
            LEFT JOIN (
                SELECT COUNT(*) AS unread_count
                FROM messages
                WHERE is_support = 1
                  AND support_user_id = ?
                  AND receiver_id = ?
                  AND sender_id <> ?
                  AND is_read = 0
                  AND deleted_at IS NULL
            ) AS unread ON 1 = 1',
			'iiii',
			[$currentUserId, $currentUserId, $currentUserId, $currentUserId]
		);

		$row = $stmt->get_result()->fetch_assoc();
		if ((int) ($row['message_id'] ?? 0) <= 0) {
			return [[
				'partner' => $supportPreview,
				'latest_message' => [
					'id' => 0,
					'sender_id' => 0,
					'receiver_id' => 0,
					'listing_id' => null,
					'message' => '',
					'image_path' => null,
					'is_read' => true,
					'created_at' => null,
					'edited_at' => null,
					'deleted_at' => null,
					'is_deleted' => false,
					'is_outgoing' => false,
				],
				'unread_count' => 0,
				'is_support' => true,
				'target_user_id' => $targetUserId,
			]];
		}

		return [[
			'partner' => $supportPreview,
			'latest_message' => [
				'id' => (int) $row['message_id'],
				'sender_id' => (int) $row['sender_id'],
				'receiver_id' => (int) $row['receiver_id'],
				'listing_id' => $row['listing_id'] !== null ? (int) $row['listing_id'] : null,
				'message' => $row['deleted_at'] !== null ? 'Сообщение удалено' : (string) ($row['message'] ?? ''),
				'image_path' => $row['deleted_at'] !== null ? null : ($row['image_path'] ?? null),
				'is_read' => (int) ($row['is_read'] ?? 0) === 1,
				'created_at' => $row['created_at'] ?? null,
				'edited_at' => $row['edited_at'] ?? null,
				'deleted_at' => $row['deleted_at'] ?? null,
				'is_deleted' => $row['deleted_at'] !== null,
				'is_outgoing' => (int) ($row['sender_id'] ?? 0) === $currentUserId,
			],
			'unread_count' => (int) ($row['unread_count'] ?? 0),
			'is_support' => true,
			'target_user_id' => $targetUserId,
		]];
	}

	private function formatConversationRow(array $row, int $currentUserId, bool $isSupport): array
	{
		$conversation = [
			'partner' => [
				'id' => (int) $row['partner_id'],
				'first_name' => (string) ($row['first_name'] ?? ''),
				'last_name' => (string) ($row['last_name'] ?? ''),
				'full_name' => trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? ''))),
				'role' => (string) ($row['role'] ?? 'user'),
				'is_admin' => (string) ($row['role'] ?? 'user') === 'admin',
				'has_avatar' => (int) ($row['has_avatar'] ?? 0) === 1,
				'is_verify' => (int) ($row['is_verify'] ?? 0) === 1,
				'is_online' => (int) ($row['is_online'] ?? 0) === 1,
				'city' => (string) ($row['city'] ?? ''),
				'region_name' => (string) ($row['region_name'] ?? ''),
			],
			'latest_message' => [
				'id' => (int) ($row['message_id'] ?? 0),
				'sender_id' => (int) ($row['sender_id'] ?? 0),
				'receiver_id' => (int) ($row['receiver_id'] ?? 0),
				'listing_id' => $row['listing_id'] !== null ? (int) $row['listing_id'] : null,
				'message' => $row['deleted_at'] !== null ? 'Сообщение удалено' : (string) ($row['message'] ?? ''),
				'image_path' => $row['deleted_at'] !== null ? null : ($row['image_path'] ?? null),
				'is_read' => (int) ($row['is_read'] ?? 0) === 1,
				'created_at' => $row['created_at'] ?? null,
				'edited_at' => $row['edited_at'] ?? null,
				'deleted_at' => $row['deleted_at'] ?? null,
				'is_deleted' => $row['deleted_at'] !== null,
				'is_outgoing' => (int) ($row['sender_id'] ?? 0) === $currentUserId,
			],
			'unread_count' => (int) ($row['unread_count'] ?? 0),
			'is_support' => $isSupport,
		];

		if ($isSupport && $this->isAdminUser($currentUserId)) {
			$conversation['support_lock'] = $this->getSupportConversationLockSnapshotBySupportUserId(
				(int) $row['partner_id'],
				$currentUserId
			);
		}

		return $conversation;
	}

	private function markSupportConversationAsRead(int $currentUserId, int $partnerId): array
	{
		$supportUserId = $this->resolveSupportConversationUserId($currentUserId, $partnerId);
		if ($this->isAdminUser($currentUserId)) {
			$idsStmt = $this->db->prepareAndExecute(
				'SELECT id
                FROM messages
                WHERE is_support = 1 AND support_user_id = ? AND sender_id = ? AND is_read = 0
                ORDER BY id ASC',
				'ii',
				[$supportUserId, $supportUserId]
			);
		} else {
			$idsStmt = $this->db->prepareAndExecute(
				'SELECT id
                FROM messages
                WHERE is_support = 1 AND support_user_id = ? AND receiver_id = ? AND sender_id <> ? AND is_read = 0
                ORDER BY id ASC',
				'iii',
				[$supportUserId, $currentUserId, $currentUserId]
			);
		}

		$result = $idsStmt->get_result();
		$messageIds = [];
		while ($row = $result->fetch_assoc()) {
			$messageIds[] = (int) $row['id'];
		}

		if (!empty($messageIds)) {
			if ($this->isAdminUser($currentUserId)) {
				$this->db->prepareAndExecute(
					'UPDATE messages
                    SET is_read = 1
                    WHERE is_support = 1 AND support_user_id = ? AND sender_id = ? AND is_read = 0',
					'ii',
					[$supportUserId, $supportUserId]
				);
			} else {
				$this->db->prepareAndExecute(
					'UPDATE messages
                    SET is_read = 1
                    WHERE is_support = 1 AND support_user_id = ? AND receiver_id = ? AND sender_id <> ? AND is_read = 0',
					'iii',
					[$supportUserId, $currentUserId, $currentUserId]
				);
			}
		}

		return $messageIds;
	}

	private function resolveSupportConversationUserId(int $currentUserId, int $partnerId): int
	{
		$currentIsAdmin = $this->isAdminUser($currentUserId);
		$partner = $this->getUserPreview($partnerId);
		if ($partner === null) {
			throw new InvalidArgumentException('Получатель не найден');
		}

		$partnerIsAdmin = !empty($partner['is_admin']);
		if ($currentIsAdmin && !$partnerIsAdmin) {
			return $partnerId;
		}

		if (!$currentIsAdmin && $partnerIsAdmin) {
			return $currentUserId;
		}

		throw new InvalidArgumentException('Чат поддержки доступен только между пользователем и администратором');
	}

	private function assertSupportConversationAccess(int $currentUserId, int $partnerId): void
	{
		if (!$this->isAdminUser($currentUserId)) {
			return;
		}

		$supportUserId = $this->resolveSupportConversationUserId($currentUserId, $partnerId);
		$lock = $this->getSupportConversationLockSnapshotBySupportUserId($supportUserId, $currentUserId);
		if ($lock !== null && !empty($lock['is_locked']) && empty($lock['is_mine'])) {
			throw new InvalidArgumentException('Чат поддержки уже занят другим администратором');
		}
	}

	private function getSupportConversationLockSnapshotBySupportUserId(int $supportUserId, int $currentUserId): ?array
	{
		$this->cleanupExpiredSupportConversationLocks();
		return $this->formatSupportConversationLock(
			$this->getSupportConversationLockRow($supportUserId),
			$currentUserId
		);
	}

	private function getSupportConversationLockRow(int $supportUserId): ?array
	{
		$stmt = $this->db->prepareAndExecute(
			'SELECT l.support_user_id, l.admin_user_id, l.expires_at, l.updated_at,
                u.first_name, u.last_name
            FROM support_chat_locks l
            JOIN users u ON u.id = l.admin_user_id
            WHERE l.support_user_id = ?
            LIMIT 1',
			'i',
			[$supportUserId]
		);

		$row = $stmt->get_result()->fetch_assoc();
		return $row ?: null;
	}

	private function formatSupportConversationLock(?array $row, int $currentUserId): ?array
	{
		if ($row === null) {
			return null;
		}

		$adminUserId = (int) ($row['admin_user_id'] ?? 0);

		return [
			'support_user_id' => (int) ($row['support_user_id'] ?? 0),
			'admin_user_id' => $adminUserId,
			'admin_name' => trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? ''))),
			'expires_at' => $row['expires_at'] ?? null,
			'updated_at' => $row['updated_at'] ?? null,
			'is_locked' => $adminUserId > 0,
			'is_mine' => $adminUserId === $currentUserId,
		];
	}

	private function cleanupExpiredSupportConversationLocks(): void
	{
		$this->db->query('DELETE FROM support_chat_locks WHERE expires_at <= NOW()');
	}

	private function buildSupportTeamPreview(): array
	{
		return [
			'id' => 0,
			'first_name' => 'Поддержка',
			'last_name' => 'BelCouch',
			'full_name' => 'Поддержка BelCouch',
			'role' => 'support',
			'is_admin' => false,
			'has_avatar' => false,
			'is_verify' => false,
			'is_online' => false,
			'city' => '',
			'region_name' => '',
		];
	}

	private function getDefaultSupportAdminPreview(): ?array
	{
		$supportService = new SupportService();
		$supportAdmin = $supportService->getSupportChatAdmin();
		if (!$supportAdmin) {
			return null;
		}

		return $this->getUserPreview((int) $supportAdmin['id']);
	}

	private function isAdminUser(int $userId): bool
	{
		$user = $this->getUserPreview($userId);
		return !empty($user['is_admin']);
	}

	private function ensureSupportChatSchemaExists(): void
	{
		$columns = $this->db->getAll(
			"SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = '" . $this->db->escapeString(DB_NAME) . "'
               AND TABLE_NAME = 'messages'
               AND COLUMN_NAME IN ('is_support', 'support_user_id')"
		);

		$existing = array_map(static function (array $row): string {
			return (string) ($row['COLUMN_NAME'] ?? '');
		}, $columns);

		if (!in_array('is_support', $existing, true)) {
			$this->db->query("ALTER TABLE messages ADD COLUMN is_support TINYINT(1) NOT NULL DEFAULT 0 AFTER reply_to_message_id");
		}

		if (!in_array('support_user_id', $existing, true)) {
			$this->db->query("ALTER TABLE messages ADD COLUMN support_user_id INT(11) NULL AFTER is_support");
		}
	}

	private function ensureSupportChatLocksTableExists(): void
	{
		$this->db->query(
			'CREATE TABLE IF NOT EXISTS support_chat_locks (
                support_user_id int(11) NOT NULL PRIMARY KEY,
                admin_user_id int(11) NOT NULL,
                expires_at datetime NOT NULL,
                created_at timestamp NOT NULL DEFAULT current_timestamp(),
                updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                INDEX idx_support_chat_locks_admin_user_id (admin_user_id),
                INDEX idx_support_chat_locks_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
		);
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
