<?php
session_start();
include '../server/server.php';

header('Content-Type: application/json');

// Utility: ensure a column exists (adds it if missing)
function ensure_column(mysqli $conn, string $table, string $column, string $definition): bool {
	$res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
	if ($res && $res->num_rows > 0) return true;
	return (bool)$conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
}

// Read optional JSON body
$raw = file_get_contents('php://input');
$scope = '';
if ($raw) {
	$data = json_decode($raw, true);
	if (json_last_error() === JSON_ERROR_NONE) {
		$scope = strtolower(trim((string)($data['scope'] ?? '')));
	}
}

$ok = false;
$affected = 0;

if ($scope === 'secretary') {
	// Use role-specific read flag so captain isn't affected
	if (ensure_column($conn, 'notifications', 'is_read_secretary', 'TINYINT(1) NOT NULL DEFAULT 0')) {
		$sql = "UPDATE notifications SET is_read_secretary = 1 WHERE (is_read_secretary = 0 OR is_read_secretary IS NULL)";
		$ok = (bool)$conn->query($sql);
		if ($ok) { $affected = $conn->affected_rows; }
	}
} elseif ($scope === 'captain') {
	// Backward-compat: captain still uses global unless captain-specific exists
	if (ensure_column($conn, 'notifications', 'is_read_captain', 'TINYINT(1) NOT NULL DEFAULT 0')) {
		$sql = "UPDATE notifications SET is_read_captain = 1 WHERE (is_read_captain = 0 OR is_read_captain IS NULL)";
		$ok = (bool)$conn->query($sql);
		if ($ok) { $affected = $conn->affected_rows; }
	} else {
		$sql = "UPDATE notifications SET is_read = 1 WHERE (is_read = 0 OR is_read IS NULL)";
		$ok = (bool)$conn->query($sql);
		if ($ok) { $affected = $conn->affected_rows; }
	}
} elseif ($scope === 'lupon') {
	// Lupon: mark notifications for this lupon OR notifications explicitly targeted to this official
	$luponId = isset($_SESSION['official_id']) ? (int)$_SESSION['official_id'] : 0;
	if ($luponId > 0) {
		// Prefer prepared statement that updates both lupon_id and official_id matches
		$sql = "UPDATE notifications SET is_read = 1 WHERE (is_read = 0 OR is_read IS NULL) AND (lupon_id = ? OR official_id = ? )";
		if ($st = $conn->prepare($sql)) {
			$st->bind_param('ii', $luponId, $luponId);
			$st->execute();
			$ok = true;
			$affected = $conn->affected_rows;
			$st->close();
		}
	}
} elseif ($scope === 'resident') {
	$residentId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
	if ($residentId > 0) {
		if ($st = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE (is_read = 0 OR is_read IS NULL) AND resident_id = ?")) {
			$st->bind_param('i', $residentId);
			$st->execute();
			$ok = true;
			$affected = $conn->affected_rows;
			$st->close();
		}
	}
} elseif ($scope === 'external') {
	$extId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; // external user session uses user_id in this app
	if ($extId > 0) {
		// Try common columns
		$columns = [];
		if ($res = $conn->query("SHOW COLUMNS FROM notifications")) {
			while ($c = $res->fetch_assoc()) { $columns[] = $c['Field']; }
		}
		$candidates = ['external_user_id','external_complainant_id','external_complaint_id'];
		$targetCol = '';
		foreach ($candidates as $cand) { if (in_array($cand, $columns, true)) { $targetCol = $cand; break; } }
		if ($targetCol !== '') {
			$sql = "UPDATE notifications SET is_read = 1 WHERE (is_read = 0 OR is_read IS NULL) AND $targetCol = ?";
			if ($st = $conn->prepare($sql)) {
				$st->bind_param('i', $extId);
				$st->execute();
				$ok = true;
				$affected = $conn->affected_rows;
				$st->close();
			}
		}
	}
} else {
	// Default: no-op for unknown scope
	$ok = false;
}

echo json_encode(['success' => $ok, 'affected' => (int)$affected]);
?>