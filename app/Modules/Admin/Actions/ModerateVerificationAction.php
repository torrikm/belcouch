<?php
class ModerateVerificationAction
{
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            JsonResponse::send(['success' => false, 'message' => 'Метод не поддерживается'], 405);
        }

        AdminAccess::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            JsonResponse::send(['success' => false, 'message' => 'Недействительный CSRF токен'], 419);
        }

        $requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        $status = trim((string) ($_POST['status'] ?? ''));
        $adminNote = isset($_POST['admin_note']) ? trim((string) $_POST['admin_note']) : null;

        if ($requestId <= 0) {
            JsonResponse::send(['success' => false, 'message' => 'Некорректная заявка'], 422);
        }

        try {
            $service = new AdminVerificationService();
            $service->moderate($requestId, $status, $adminNote, (int) $_SESSION['user_id']);
            JsonResponse::send(['success' => true, 'message' => $status === 'approved' ? 'Пользователь верифицирован' : 'Заявка отклонена']);
        } catch (Exception $exception) {
            JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
