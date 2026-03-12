<?php
class GetAvatarAction
{
    public function handle(): void
    {
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            http_response_code(400);
            exit;
        }

        $id = (int) $_GET['id'];

        try {
            $db = new Database();
            $stmt = $db->prepareAndExecute('SELECT avatar_image FROM users WHERE id = ?', 'i', [$id]);
            $stmt->store_result();

            if ($stmt->num_rows === 0) {
                http_response_code(404);
                exit;
            }

            $stmt->bind_result($avatar);
            $stmt->fetch();

            if ($avatar === null) {
                http_response_code(404);
                exit;
            }

            header('Content-Type: image/jpeg');
            header('Cache-Control: max-age=86400');
            echo $avatar;
            exit;
        } catch (Exception $exception) {
            http_response_code(500);
            exit;
        }
    }
}
