<?php

class ChatRealtimeNotifier
{
    public static function notifyUsers(array $userIds, array $payload = [], string $event = 'chat:update'): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), function (int $id): bool {
            return $id > 0;
        })));

        if (empty($userIds) || !defined('CHAT_WS_INTERNAL_EMIT_URL')) {
            return;
        }

        $body = json_encode([
            'user_ids' => $userIds,
            'event' => $event,
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            return;
        }

        $headers = "Content-Type: application/json\r\n";
        if (defined('CHAT_WS_SHARED_SECRET')) {
            $headers .= 'X-Chat-Secret: ' . CHAT_WS_SHARED_SECRET . "\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headers,
                'content' => $body,
                'timeout' => 0.35,
                'ignore_errors' => true,
            ]
        ]);

        @file_get_contents(CHAT_WS_INTERNAL_EMIT_URL, false, $context);
    }
}
