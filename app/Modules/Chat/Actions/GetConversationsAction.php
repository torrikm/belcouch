<?php

class GetConversationsAction
{
    public function handle(): void
    {
        if (!isset($_SESSION['user_id'])) {
            JsonResponse::send(['success' => false, 'message' => 'Требуется авторизация'], 401);
        }

        try {
            $service = new ChatService();
            $conversations = $service->getConversationList((int) $_SESSION['user_id']);

            JsonResponse::send([
                'success' => true,
                'conversations' => $conversations,
            ]);
        } catch (Throwable $exception) {
            JsonResponse::send(['success' => false, 'message' => 'Не удалось загрузить диалоги'], 500);
        }
    }
}
