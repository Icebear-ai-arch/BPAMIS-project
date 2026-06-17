<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Zxing\QrReader;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['national_id'])) {
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = basename($_FILES['national_id']['name']);
    $uploadPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['national_id']['tmp_name'], $uploadPath)) {
        echo "<p><strong>File uploaded successfully!</strong></p>";

        try {
            $qrcode = new QrReader($uploadPath);
            $qrText = $qrcode->text();

            if ($qrText) {
                echo "<p><strong>QR Code Detected:</strong> " . htmlspecialchars($qrText) . "</p>";

                // ✅ Check if it matches a PhilSys-like pattern
                // Typical PhilSys format: 0000-0000-0000-0000 (16 digits with hyphens)
                if (preg_match('/^\d{4}-\d{4}-\d{4}-\d{4}$/', $qrText)) {
                    echo "<p style='color:green'><strong>✅ This QR code appears to contain a valid PhilSys ID format.</strong></p>";
                } else {
                    echo "<p style='color:orange'><strong>⚠️ QR code detected, but it does not match a typical National ID format.</strong><br>
                    It might still be valid, but further verification with PSA is needed.</p>";
                }

            } else {
                echo "<p style='color:red'>No valid QR code found in the uploaded image.</p>";
            }

        } catch (Exception $e) {
            echo "<p style='color:red'>Error reading QR Code: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p style='color:red'>Error uploading file.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>National ID Verification</title>
</head>
<body style="font-family: Arial; margin: 40px;">
    <h2>Upload National ID to Verify</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="national_id" accept="image/*" required>
        <br><br>
        <button type="submit">Upload & Verify</button>
    </form>
</body>
</html>
