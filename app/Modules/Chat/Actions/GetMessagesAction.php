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

        if ($partnerId <= 0) {
            JsonResponse::send(['success' => false, 'message' => 'Некорректный диалог'], 422);
        }

        try {
            $service = new ChatService();
            $currentUserId = (int) $_SESSION['user_id'];
            $readIds = $service->markConversationAsRead($currentUserId, $partnerId);
            $messages = $service->getMessages((int) $_SESSION['user_id'], $partnerId, $afterId);

            if (!empty($readIds)) {
                ChatRealtimeNotifier::notifyUsers(
                    [$partnerId],
                    [
                        'user_id' => $currentUserId,
                        'partner_id' => $partnerId,
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
        } catch (Throwable $exception) {
            JsonResponse::send(['success' => false, 'message' => 'Не удалось загрузить сообщения'], 500);
        }
    }
}
