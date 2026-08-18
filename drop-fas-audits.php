<?php
$pdo = new PDO('mysql:host=localhost;dbname=u616628132_fut;charset=utf8mb4', 'u616628132_fut', '@1bwdmdzB*');
$pdo->exec('DROP TABLE IF EXISTS fas_audits');
echo "dropped fas_audits\n";
