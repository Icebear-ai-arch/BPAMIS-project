<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Include database connection
    require_once __DIR__ . '/../server/server.php';
    
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        exit;
    }
    
    $officialId = isset($_SESSION['official_id']) ? (int)$_SESSION['official_id'] : 0;
    
    // Try to find the notification
    $notification = null;
    $foundWith = null;
    
    // Try notification_id first
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE notification_id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = bpamis_stmt_get_result($stmt);
    $notification = $result->fetch_assoc();
    $stmt->close();
    
    if ($notification) {
        $foundWith = 'notification_id';
    } else {
        // Try with id column
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = bpamis_stmt_get_result($stmt);
        $notification = $result->fetch_assoc();
        $stmt->close();
        if ($notification) {
            $foundWith = 'id';
        }
    }
    
    if (!$notification) {
        echo json_encode(['success' => false, 'error' => 'Notification not found']);
        exit;
    }
    
    // Insert into notifications_trash using only the basic required fields
    $stmt = $conn->prepare("INSERT INTO notifications_trash (notification_id, type, title, message, created_at, is_read, trashed_at, trashed_by) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
    
    $notifId = $notification['notification_id'] ?? $notification['id'] ?? $id;
    $type = $notification['type'] ?? '';
    $title = $notification['title'] ?? '';
    $message = $notification['message'] ?? '';
    $createdAt = $notification['created_at'] ?? date('Y-m-d H:i:s');
    $isRead = $notification['is_read'] ?? 0;
    
    $stmt->bind_param('isssiii', $notifId, $type, $title, $message, $createdAt, $isRead, $officialId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Notification moved to trash',
            'found_with' => $foundWith,
            'notification_id' => $notifId
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to move to trash: ' . $stmt->error,
            'found_with' => $foundWith
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>