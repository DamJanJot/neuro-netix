<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

neuronetix_json_response([
    'status' => 'ok',
    'version' => '1.0.0',
    'timestamp' => date('c'),
]);
