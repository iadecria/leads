<?php

$env = parse_ini_file(__DIR__ . '/.env');
$pdo = new PDO(
    'mysql:host=' . $env['DB_HOST'] . ';dbname=' . $env['DB_DATABASE'] . ';charset=utf8mb4',
    $env['DB_USERNAME'],
    $env['DB_PASSWORD']
);
$pdo->exec('DROP TABLE IF EXISTS fas_analyses');
echo "dropped\n";
