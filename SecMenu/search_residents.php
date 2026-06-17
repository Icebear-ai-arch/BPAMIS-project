<?php
include_once __DIR__ . '/../server/server.php';

$term = $_GET['term'] ?? '';
$term = $conn->real_escape_string($term);

$results = [];

$sql = "SELECT CONCAT(First_Name, ' ', Last_Name) AS full_name FROM RESIDENT_INFO 
        WHERE First_Name LIKE '%$term%' OR Last_Name LIKE '%$term%' LIMIT 10";
$res = $conn->query($sql);

while ($row = $res->fetch_assoc()) {
    $results[] = $row['full_name'];
}

echo json_encode($results);
