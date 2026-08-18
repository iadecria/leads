<?php

$path = __DIR__ . '/.env';
$env = file_get_contents($path);

$replacements = [
    'DB_CONNECTION=sqlite' => 'DB_CONNECTION=mysql',
    '# DB_HOST=127.0.0.1' => 'DB_HOST=localhost',
    '# DB_PORT=3306' => 'DB_PORT=3306',
    '# DB_DATABASE=laravel' => 'DB_DATABASE=u616628132_fut',
    '# DB_USERNAME=root' => 'DB_USERNAME=u616628132_fut',
    '# DB_PASSWORD=' => 'DB_PASSWORD=@1bwdmdzB*',
    'DB_HOST=127.0.0.1' => 'DB_HOST=localhost',
    'DB_DATABASE=laravel' => 'DB_DATABASE=u616628132_fut',
    'DB_USERNAME=root' => 'DB_USERNAME=u616628132_fut',
    'DB_PASSWORD=' => 'DB_PASSWORD=@1bwdmdzB*',
];

foreach ($replacements as $search => $replace) {
    $env = str_replace($search, $replace, $env);
}

if (strpos($env, 'DB_CONNECTION=mysql') === false) {
    $env .= "\nDB_CONNECTION=mysql\nDB_HOST=localhost\nDB_PORT=3306\nDB_DATABASE=u616628132_fut\nDB_USERNAME=u616628132_fut\nDB_PASSWORD=@1bwdmdzB*\n";
}

file_put_contents($path, $env);
