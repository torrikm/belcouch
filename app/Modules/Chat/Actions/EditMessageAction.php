<?php

class EditMessageAction
{
    public function handle(): void
    {
        if (!isset($_SESSION['user_id'])) {
            JsonResponse::send(['success' => false, 'message' => 'Требуется авторизация'], 401);
        }

        $messageId = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;
        $message = trim($_POST['message'] ?? '');

        if ($messageId <= 0) {
            JsonResponse::send(['success' => false, 'message' => 'Некорректное сообщение'], 422);
        }

        try {
            $currentUserId = (int) $_SESSION['user_id'];
            $service = new ChatService();
            $updatedMessage = $service->updateMessage($currentUserId, $messageId, $message);
            $participants = $service->getMessageParticipants($messageId);

            if ($participants !== null) {
                ChatRealtimeNotifier::notifyUsers(
                    [(int) $participants['sender_id'], (int) $participants['receiver_id']],
                    [
                        'user_id' => $currentUserId,
                        'message' => $updatedMessage,
                    ],
                    'chat:message_updated'
                );
            }

            JsonResponse::send([
                'success' => true,
                'message_item' => $updatedMessage,
            ]);
        } catch (InvalidArgumentException $exception) {
            JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            JsonResponse::send(['success' => false, 'message' => 'Не удалось отредактировать сообщение'], 500);
        }
    }
}
