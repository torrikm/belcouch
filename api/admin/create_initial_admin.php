<?php
require_once __DIR__ . '/../../bootstrap.php';

$action = new CreateInitialAdminAction();
$action->handle();
