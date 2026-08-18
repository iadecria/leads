#!/bin/sh
set -e

cd domains/decria.com.br/public_html/fut

php -r '
$path = ".env";
$env = file_get_contents($path);
$env = preg_replace("/^#? DB_HOST=.*$/m", "DB_HOST=localhost", $env);
$env = preg_replace("/^#? DB_PORT=.*$/m", "DB_PORT=3306", $env);
$env = preg_replace("/^#? DB_DATABASE=.*$/m", "DB_DATABASE=u616628132_fut", $env);
$env = preg_replace("/^#? DB_USERNAME=.*$/m", "DB_USERNAME=u616628132_fut", $env);
$env = preg_replace("/^#? DB_PASSWORD=.*$/m", "DB_PASSWORD=@1bwdmdzB*", $env);
file_put_contents($path, $env);
'

php artisan config:clear
php artisan migrate --force
