<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_require_access();

$user = neuronetix_current_user();
$role = neuronetix_normalize_role((string) ($user['rola'] ?? 'user'));
$panels = neuronetix_current_user_panels();
$targetPanel = neuronetix_default_panel_for_role($role, $panels);

header('Location: ' . neuronetix_panel_url($targetPanel));
exit();
