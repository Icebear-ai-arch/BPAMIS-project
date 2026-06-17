<?php
include_once __DIR__ . '/../server/server.php';

$q = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';

$sql = "SELECT resident_id, CONCAT(firstname, ' ', lastname) AS full_name 
        FROM resident_info 
        WHERE resident_id LIKE '%$q%' OR firstname LIKE '%$q%' OR lastname LIKE '%$q%' 
        LIMIT 10";

$result = $conn->query($sql);
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = ["value" => $row['resident_id'] . " - " . $row['full_name']];
}

header('Content-Type: application/json');
echo json_encode($data);
?>
