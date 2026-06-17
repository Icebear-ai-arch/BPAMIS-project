<?php
// Minimal environment health check (safe to keep temporarily during deployment).
// Shows PHP version + key extensions. Does NOT print DB credentials.

header('Content-Type: text/html; charset=utf-8');

function yesno($v) { return $v ? 'YES' : 'NO'; }

$root = realpath(__DIR__ . '/..');
$uploads = $root ? ($root . DIRECTORY_SEPARATOR . 'uploads') : null;
$logFile = $uploads ? ($uploads . DIRECTORY_SEPARATOR . 'bpamis_php_fatal.log') : null;

$php = PHP_VERSION;
$versionId = defined('PHP_VERSION_ID') ? PHP_VERSION_ID : 0;
$minOk = ($versionId >= 70000);

$checks = [
    'PHP >= 7.0' => $minOk,
    'extension mysqli' => extension_loaded('mysqli'),
    'extension mbstring' => extension_loaded('mbstring'),
    'function mysqli_stmt::get_result exists' => method_exists('mysqli_stmt', 'get_result'),
    'function json_encode exists' => function_exists('json_encode'),
    'uploads/ exists' => ($uploads && is_dir($uploads)),
    'uploads/ writable' => ($uploads && is_dir($uploads) && @is_writable($uploads)),
];

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>BPAMIS Health Check</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:24px;background:#f7f7fb;color:#111;}
    .card{background:#fff;border:1px solid #e6e6ef;border-radius:10px;padding:16px;max-width:900px;}
    h1{font-size:18px;margin:0 0 10px 0;}
    .kv{display:grid;grid-template-columns:220px 1fr;gap:8px 12px;margin:12px 0;}
    .k{color:#444;}
    .v{font-weight:600;}
    table{border-collapse:collapse;width:100%;margin-top:10px;}
    td{border-top:1px solid #eee;padding:8px 6px;vertical-align:top;}
    .ok{color:#0a7a2a;}
    .bad{color:#b00020;}
    .muted{color:#666;font-size:12px;}
    code{background:#f3f3f8;padding:2px 6px;border-radius:6px;}
  </style>
</head>
<body>
  <div class="card">
    <h1>BPAMIS Health Check</h1>
    <div class="kv">
      <div class="k">PHP Version</div><div class="v"><?php echo htmlspecialchars($php); ?></div>
      <div class="k">SAPI</div><div class="v"><?php echo htmlspecialchars(php_sapi_name()); ?></div>
      <div class="k">Document Root</div><div class="v"><?php echo htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? ''); ?></div>
      <div class="k">Project Root (detected)</div><div class="v"><?php echo htmlspecialchars((string)$root); ?></div>
      <div class="k">Fatal log file</div><div class="v"><?php echo htmlspecialchars((string)$logFile); ?></div>
    </div>

    <table>
      <tbody>
        <?php foreach ($checks as $label => $ok): ?>
          <tr>
            <td style="width:320px"><?php echo htmlspecialchars($label); ?></td>
            <td class="<?php echo $ok ? 'ok' : 'bad'; ?>"><?php echo $ok ? 'OK' : 'NOT OK'; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <p class="muted" style="margin-top:12px;">
      If pages are blank, check the server-side log at <code>uploads/bpamis_php_fatal.log</code> (created by <code>server/server.php</code>).
      Remove this file after debugging.
    </p>
  </div>
</body>
</html>
