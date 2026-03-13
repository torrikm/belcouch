<?php

class GetMessagesAction
{
	public function handle(): void
	{
		if (!isset($_SESSION['user_id'])) {
			JsonResponse::send(['success' => false, 'message' => 'Требуется авторизация'], 401);
		}

		$partnerId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
		$afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : 0;
		$isSupport = !empty($_GET['support']);

		if ($partnerId <= 0) {
			JsonResponse::send(['success' => false, 'message' => 'Некорректный диалог'], 422);
		}

		try {
			$service = new ChatService();
			$currentUserId = (int) $_SESSION['user_id'];
			$readIds = $service->markConversationAsRead($currentUserId, $partnerId, $isSupport);
			$messages = $service->getMessages((int) $_SESSION['user_id'], $partnerId, $afterId, $isSupport);

			if (!empty($readIds)) {
				$notifyUserIds = [$partnerId];
				if ($isSupport) {
					$db = new Database();
					$rows = $db->getAll("SELECT id FROM users WHERE role = 'admin'");
					$notifyUserIds = array_map(static function (array $row): int {
						return (int) ($row['id'] ?? 0);
					}, $rows);
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

			JsonResponse::send([
				'success' => true,
				'messages' => $messages,
				'last_id' => empty($messages) ? $afterId : (int) end($messages)['id'],
			]);
		} catch (InvalidArgumentException $exception) {
			JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
		} catch (Throwable $exception) {
			JsonResponse::send(['success' => false, 'message' => 'Не удалось загрузить сообщения'], 500);
		}
	}
}
