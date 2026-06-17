<?php
// Quick dump of blotter_info rows
// Place this file in your webroot and open in a browser, or run with: php -S localhost:8000 and open http://localhost:8000/tools/dump_blotter.php

include_once __DIR__ . '/../server/server.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>All rows from <code>blotter_info</code></h2>";

$sql = "SELECT * FROM blotter_info ORDER BY Blotter_ID DESC";
$res = $conn->query($sql);
if (!$res) {
    echo '<p>Error: ' . htmlspecialchars($conn->error) . '</p>';
    exit;
}

echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse">';
// header
$fields = $res->fetch_fields();
echo '<tr style="background:#eee">';
foreach ($fields as $f) {
    echo '<th>' . htmlspecialchars($f->name) . '</th>';
}
echo '</tr>';

while ($row = $res->fetch_assoc()) {
    echo '<tr>';
    foreach ($fields as $f) {
        $val = $row[$f->name] ?? '';
        echo '<td>' . nl2br(htmlspecialchars((string)$val)) . '</td>';
    }
    echo '</tr>';
}

echo '</table>';

echo '<p><a href="?format=json">Download JSON</a></p>';

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    $res->data_seek(0);
    $all = [];
    while ($r = $res->fetch_assoc()) $all[] = $r;
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($all, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
}

// Intentionally not closing $conn (shared include).
