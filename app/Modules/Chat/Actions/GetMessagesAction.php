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
		$beforeId = isset($_GET['before_id']) ? (int) $_GET['before_id'] : 0;
		$aroundId = isset($_GET['around_id']) ? (int) $_GET['around_id'] : 0;
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
		$contextSize = isset($_GET['context']) ? (int) $_GET['context'] : 25;
		$isSupport = !empty($_GET['support']);

		if ($partnerId <= 0) {
			JsonResponse::send(['success' => false, 'message' => 'Некорректный диалог'], 422);
		}

		try {
			$service = new ChatService();
			$currentUserId = (int) $_SESSION['user_id'];
			$readIds = $service->markConversationAsRead($currentUserId, $partnerId, $isSupport);
			$window = $service->getMessagesWindow(
				$currentUserId,
				$partnerId,
				[
					'after_id' => $afterId,
					'before_id' => $beforeId,
					'around_id' => $aroundId,
					'limit' => $limit,
					'context' => $contextSize,
				],
				$isSupport
			);
			$messages = $window['messages'];

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
				'meta' => $window['meta'] ?? [],
			]);
		} catch (InvalidArgumentException $exception) {
			JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
		} catch (Throwable $exception) {
			JsonResponse::send(['success' => false, 'message' => 'Не удалось загрузить сообщения'], 500);
		}
	}
}
