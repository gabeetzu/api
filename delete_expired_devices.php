<?php
// Daily cleanup of devices scheduled for deletion

$pdo = new PDO(
    "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4",
    getenv('DB_USER'),
    getenv('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$stmt = $pdo->prepare("DELETE FROM usage_tracking WHERE pending_deletion = 1 AND deletion_due_at <= NOW()");
$stmt->execute();

echo "Deleted " . $stmt->rowCount() . " expired devices\n";
