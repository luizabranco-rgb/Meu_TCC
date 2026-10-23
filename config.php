<?php
declare(strict_types=1);

return [
    'host' => getenv('DB_HOST') ?: 'sql113.byethost31.com',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: 'b31_42774050_teste',
    'username' => getenv('DB_USER') ?: 'b31_42774050',
    'password' => getenv('DB_PASSWORD') ?: 'senai@459',
];
