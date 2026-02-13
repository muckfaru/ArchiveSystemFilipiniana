<?php
/**
 * AJAX - Check for duplicate user/email
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$type = $_POST['type'] ?? '';
$value = trim($_POST['value'] ?? '');
$userId = intval($_POST['user_id'] ?? 0); // For editing, exclude own ID (optional if needed later)

if (empty($value) || !in_array($type, ['username', 'email'])) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $sql = "SELECT id FROM users WHERE $type = ? AND deleted_at IS NULL";
    $params = [$value];

    // If editing, exclude current user
    if ($userId > 0) {
        $sql .= " AND id != ?";
        $params[] = $userId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $exists = (bool) $stmt->fetch();

    echo json_encode(['exists' => $exists]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
