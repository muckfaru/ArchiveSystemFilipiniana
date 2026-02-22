<?php
require 'backend/core/config.php';
$stmt = $pdo->query('SELECT id, title, thumbnail_path FROM newspapers ORDER BY id DESC LIMIT 5;');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>