<?php
class GetVerificationDocumentAction
{
    public function handle(): void
    {
        AdminAccess::requireAdmin();

        $requestId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($requestId <= 0) {
            http_response_code(400);
            exit;
        }

        $service = new AdminVerificationService();
        $service->outputDocument($requestId);
    }
}
