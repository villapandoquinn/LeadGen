<?php
require_once __DIR__ . '/../config/database.php';
echo $pdo->query('SELECT VERSION()')->fetchColumn() . "\n";
// Check existing columns
$cols = $pdo->query("SHOW COLUMNS FROM leads")->fetchAll(PDO::FETCH_COLUMN);
echo implode(', ', $cols) . "\n";
$idxs = $pdo->query("SHOW INDEX FROM leads")->fetchAll(PDO::FETCH_ASSOC);
foreach ($idxs as $i) echo $i['Key_name'] . ':' . $i['Column_name'] . "\n";
