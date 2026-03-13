<?php

class StreamChatAction
{
	public function handle(): void
	{
		if (!isset($_SESSION['user_id'])) {
			http_response_code(401);
			exit;
		}

		$currentUserId = (int) $_SESSION['user_id'];
		$partnerId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
		$lastMessageId = isset($_GET['last_message_id']) ? (int) $_GET['last_message_id'] : 0;
		$isSupport = !empty($_GET['support']);
		$lastConversationsStamp = $_GET['last_conversations_stamp'] ?? '';

		// Important: release PHP session lock for long-lived SSE request.
		// Otherwise other requests from the same user are blocked until stream ends.
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}

		@set_time_limit(0);
		@ini_set('output_buffering', 'off');
		@ini_set('zlib.output_compression', '0');

		header('Content-Type: text/event-stream; charset=utf-8');
		header('Cache-Control: no-cache, no-transform');
		header('Connection: keep-alive');
		header('X-Accel-Buffering: no');

		$service = new ChatService();
		$iterations = 0;

		while ($iterations < 20) {
			$hasPayload = false;

			if ($partnerId > 0) {
				try {
					$readIds = $service->markConversationAsRead($currentUserId, $partnerId, $isSupport);
					if (!empty($readIds)) {
						$notifyUserIds = [$partnerId];
						if ($isSupport) {
							$db = new Database();
							$rows = $db->getAll("SELECT id FROM users WHERE role = 'admin'");
							$notifyUserIds = array_map(static function (array $row): int {
								return (int) ($row['id'] ?? 0);
							}, $rows);
							$notifyUserIds[] = $partnerId;
						}
						ChatRealtimeNotifier::notifyUsers(
							$notifyUserIds,
							[
								'user_id' => $currentUserId,
								'partner_id' => $partnerId,
								'is_support' => $isSupport,
								'message_ids' => $readIds,
							],
							'chat:messages_read'
						);
					}
					$messages = $service->getMessages($currentUserId, $partnerId, $lastMessageId, $isSupport);
					if (!empty($messages)) {
						$lastMessageId = (int) end($messages)['id'];
						$this->emit('messages', [
							'messages' => $messages,
							'last_id' => $lastMessageId,
						]);
						$hasPayload = true;
					}
				} catch (InvalidArgumentException $exception) {
				}
			}

			$conversations = $service->getConversationList($currentUserId);
			$currentStamp = sha1(json_encode($conversations, JSON_UNESCAPED_UNICODE));

			if ($currentStamp !== $lastConversationsStamp) {
				$lastConversationsStamp = $currentStamp;
				$this->emit('conversations', [
					'conversations' => $conversations,
					'stamp' => $currentStamp,
				]);
				$hasPayload = true;
			}

			if ($hasPayload) {
				@ob_flush();
				@flush();
			}

			if (connection_aborted()) {
				break;
			}

			$iterations++;
			sleep(1);
		}

		$this->emit('heartbeat', ['ok' => true]);
		@ob_flush();
		@flush();
		exit;
	}

	private function emit(string $event, array $payload): void
	{
		echo "event: {$event}\n";
		echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
	}
}
