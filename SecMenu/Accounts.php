<?php
include './schedule/db-connect.php';
include '../controllers/session_control.php';
// Barangay Captains
$sqlCaptains = "SELECT 
    Official_ID AS id,
    Position AS role,
    Name,
    contact_number,
    official_username,
    isActive,
    email
FROM barangay_officials
WHERE LOWER(TRIM(Position)) = 'barangay captain'";

$resultCaptains = $conn->query($sqlCaptains);
$captains = [];
if ($resultCaptains && $resultCaptains->num_rows > 0) {
    while ($row = $resultCaptains->fetch_assoc()) {
        $captains[] = $row;
    }
}

// Barangay Secretaries
$sqlSecretaries = "SELECT 
    Official_ID AS id,
    Position AS role,
    Name,
    contact_number,
    official_username,
    isActive,
    email
FROM barangay_officials
WHERE LOWER(TRIM(Position)) = 'barangay secretary'";

$resultSecretaries = $conn->query($sqlSecretaries);
$secretaries = [];
if ($resultSecretaries && $resultSecretaries->num_rows > 0) {
    while ($row = $resultSecretaries->fetch_assoc()) {
        $secretaries[] = $row;
    }
}
// Barangay Lupons
$sqlLupons = "SELECT 
    Official_ID AS id,
    Position AS role,
    Name,
    contact_number,
    official_username,
    isActive,
    email
FROM barangay_officials
WHERE LOWER(TRIM(Position)) = 'lupon tagapamayapa'";

$resultLupons = $conn->query($sqlLupons);
$lupons = [];
if ($resultLupons && $resultLupons->num_rows > 0) {
    while ($row = $resultLupons->fetch_assoc()) {
        $lupons[] = $row;
    }
}

// Residents
$sqlResidents = "SELECT 
    Resident_ID AS id,
    CONCAT(First_name, ' ', Middle_name, ' ', Last_name) AS fullname,
    Birthdate,
    Address,
    Contact_Number,
    isVerify AS isActive,
    Resident_Username AS username,
    email
FROM resident_info";

$resultResidents = $conn->query($sqlResidents);
$residents = [];
if ($resultResidents && $resultResidents->num_rows > 0) {
    while ($row = $resultResidents->fetch_assoc()) {
        $residents[] = $row;
    }
}

// External Complainants
$sqlExternals = "SELECT 
    External_Complaint_ID AS id,
    CONCAT(First_name, ' ', Middle_name, ' ', Last_name) AS fullname,
    Birthdate,
    Address,
    email,
    isActive,
    External_Username AS username
FROM external_complainant";

$resultExternals = $conn->query($sqlExternals);
$externals = [];
if ($resultExternals && $resultExternals->num_rows > 0) {
    while ($row = $resultExternals->fetch_assoc()) {
        $externals[] = $row;
    }
}

// Normalize and merge all accounts for "All" tab
$allAccounts = [];

// Normalize captains
foreach ($captains as $c) {
    $allAccounts[] = [
        'id' => $c['id'],
        'account_type' => 'official',
        'role' => $c['role'],
        'name' => $c['Name'],
        'contact_number' => $c['contact_number'],
        'username' => $c['official_username'],
        'isActive' => $c['isActive'],
        'email' => $c['email'],
        'birthdate' => null,
        'address' => null,
    ];
}

// Normalize secretaries
foreach ($secretaries as $s) {
    $allAccounts[] = [
        'id' => $s['id'],
        'account_type' => 'official',
        'role' => $s['role'],
        'name' => $s['Name'],
        'contact_number' => $s['contact_number'],
        'username' => $s['official_username'],
        'isActive' => $s['isActive'],
        'email' => $s['email'],
        'birthdate' => null,
        'address' => null,
    ];
}

// Normalize lupon
foreach ($lupons as $s) {
    $allAccounts[] = [
        'id' => $s['id'],
        'account_type' => 'official',
        'role' => $s['role'],
        'name' => $s['Name'],
        'contact_number' => $s['contact_number'],
        'username' => $s['official_username'],
        'isActive' => $s['isActive'],
        'email' => $s['email'],
        'birthdate' => null,
        'address' => null,
    ];
}

// Normalize residents
foreach ($residents as $r) {
    $allAccounts[] = [
        'id' => $r['id'],
        'account_type' => 'resident',
        'role' => 'Resident',
        'name' => $r['fullname'],
        'birthdate' => $r['Birthdate'],
        'address' => $r['Address'],
        'contact_number' => $r['Contact_Number'],
        'username' => $r['username'],
        'isActive' => $r['isActive'],
        'email' => $r['email'],
    ];
}

// Normalize externals
foreach ($externals as $e) {
    $allAccounts[] = [
        'id' => $e['id'],
        'account_type' => 'external',
        'role' => 'External Complaint',
        'name' => $e['fullname'],
        'birthdate' => $e['Birthdate'],
        'address' => $e['Address'],
        'contact_number' => '', // externals don’t have contact number
        'username' => $e['username'],
        'isActive' => $e['isActive'],
        'email' => $e['email'],
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Accounts</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f7ff',
                            100: '#e0effe',
                            200: '#bae2fd',
                            300: '#7cccfd',
                            400: '#36b3f9',
                            500: '#0c9ced',
                            600: '#0281d4',
                            700: '#026aad',
                            800: '#065a8f',
                            900: '#0a4b76'
                        }
                    },
                    animation: {
                        'bell-ring': 'bell-ring 1s ease-in-out',
                    },
                    keyframes: {
                        'bell-ring': {
                            '0%, 100%': { transform: 'rotate(0)' },
                            '20%, 60%': { transform: 'rotate(8deg)' },
                            '40%, 80%': { transform: 'rotate(-8deg)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .gradient-bg {
            background: linear-gradient(to right, #f0f7ff, #e0effe);
        }
    /* Account card enhancements */
    .account-card { position: relative; }
    .account-card .hover-layer { transition: opacity .35s; }
    .account-card:hover .hover-layer { opacity: 1; }
    .account-card:focus-within { box-shadow: 0 0 0 3px rgba(14,165,233,.35); }
    </style>
</head>
<body class="bg-gray-50 font-sans relative overflow-x-hidden">
    <?php include_once('../includes/barangay_official_sec_nav.php'); ?>

    <!-- Global Blue Blush Background Orbs -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <!-- Top-left soft blue glow -->
        <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full bg-blue-200/40 blur-3xl animate-[float_14s_ease-in-out_infinite]"></div>
        <!-- Mid-right cool cyan accent -->
        <div class="absolute top-1/3 -right-52 w-[560px] h-[560px] rounded-full bg-cyan-200/40 blur-[160px] animate-[float_18s_ease-in-out_infinite]"></div>
        <!-- Bottom-center light indigo wash -->
        <div class="absolute -bottom-52 left-1/3 w-[520px] h-[520px] rounded-full bg-indigo-200/30 blur-3xl animate-[float_16s_ease-in-out_infinite]"></div>
        <!-- Subtle center diffusion -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] rounded-full bg-gradient-to-br from-blue-50 via-white to-cyan-50 opacity-70 blur-[200px]"></div>
    </div>

    <!-- Page Header (Enhanced Hero) -->
    <?php 
        $totalAll = count($allAccounts);
        $officialsCount = count($captains)+count($secretaries)+count($lupons);
        $residentCount = count($residents);
        $externalCount = count($externals);
    ?>
    <div class="w-full mt-6 md:mt-8 px-4">
        <div class="relative gradient-bg rounded-2xl shadow-sm p-8 md:p-10 overflow-hidden relative max-w-screen-2xl mx-auto">
            <div class="absolute top-0 right-0 w-72 h-72 bg-primary-100 rounded-full -mr-28 -mt-28 opacity-70 animate-[float_10s_ease-in-out_infinite]"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-primary-200 rounded-full -ml-16 -mb-16 opacity-60 animate-[float_7s_ease-in-out_infinite]"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[620px] h-[620px] bg-gradient-to-br from-primary-50 via-white to-primary-100 opacity-30 blur-3xl rounded-full pointer-events-none"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-8">
                <div class="max-w-2xl">
                    <h1 class="text-3xl md:text-4xl font-light text-primary-900 tracking-tight">Manage <span class="font-semibold">User Accounts</span></h1>
                    <p class="mt-4 text-gray-600 leading-relaxed">Centralized directory of barangay officials, residents, and external complainants. Use the smart tabs & search to locate profiles quickly.</p>
                    <div class="mt-5 flex flex-wrap gap-3 text-xs text-primary-700/80 font-medium">
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-users-gear text-primary-500"></i> Unified Directory</span>
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-magnifying-glass text-primary-500"></i> Smart Search</span>
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-layer-group text-primary-500"></i> Filter Tabs</span>
                    </div>
                </div>
                <div class="hidden md:flex flex-col gap-3 min-w-[260px]">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-primary-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-primary-500 font-semibold">All</span><span class="mt-1 text-lg font-semibold text-primary-700"><?= $totalAll ?></span></div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-blue-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-blue-600 font-semibold">Officials</span><span class="mt-1 text-lg font-semibold text-blue-700"><?= $officialsCount ?></span></div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-green-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-green-600 font-semibold">Residents</span><span class="mt-1 text-lg font-semibold text-green-700"><?= $residentCount ?></span></div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-cyan-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-cyan-600 font-semibold">External</span><span class="mt-1 text-lg font-semibold text-cyan-700"><?= $externalCount ?></span></div>
                    </div>
                    <div class="text-[11px] text-primary-700/70 text-center">Directory overview</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters + Search -->
    <div class="w-full mt-8 px-4">
        <div class="max-w-screen-2xl mx-auto">
            <div class="relative bg-white/90 backdrop-blur-sm border border-gray-100 rounded-2xl shadow-sm p-6 md:p-7 overflow-hidden filter-container" style="position:relative;z-index:10;">
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full opacity-70"></div>
                <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-gradient-to-tr from-primary-50 to-primary-100 rounded-full opacity-60"></div>
                <div class="relative z-10 flex flex-col md:flex-row md:items-center gap-5 md:gap-6">
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" class="acc-chip px-3 py-1.5 text-xs font-medium rounded-full bg-primary-600 text-white shadow-sm" data-target="accounts-all">All</button>
                        <button type="button" class="acc-chip px-3 py-1.5 text-xs font-medium rounded-full bg-primary-50 text-primary-600 border border-primary-100 hover:bg-primary-100 transition" data-target="accounts-residents">Residents</button>
                        <button type="button" class="acc-chip px-3 py-1.5 text-xs font-medium rounded-full bg-primary-50 text-primary-600 border border-primary-100 hover:bg-primary-100 transition" data-target="accounts-external">External</button>
                        <button type="button" class="acc-chip px-3 py-1.5 text-xs font-medium rounded-full bg-primary-50 text-primary-600 border border-primary-100 hover:bg-primary-100 transition" data-target="accounts-captain">Captain</button>
                        <button type="button" class="acc-chip px-3 py-1.5 text-xs font-medium rounded-full bg-primary-50 text-primary-600 border border-primary-100 hover:bg-primary-100 transition" data-target="accounts-secretary">Secretary</button>
                        <button type="button" class="acc-chip px-3 py-1.5 text-xs font-medium rounded-full bg-primary-50 text-primary-600 border border-primary-100 hover:bg-primary-100 transition" data-target="accounts-lupon">Lupon</button>
                    </div>
                    <div class="md:ml-auto relative max-w-md w-full group">
                        <label for="searchName" class="sr-only">Search by name</label>
                        <input type="text" id="searchName" placeholder="Search by name..." class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-sm transition" />
                        <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-primary-400 group-focus-within:text-primary-500 transition"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Accounts Sections -->
    <div class="w-full mt-6 px-4">
        <div class="max-w-screen-2xl mx-auto space-y-4">

            <!-- All Accounts -->
            <div id="accounts-all" class="accounts-section">
                <?php foreach ($allAccounts as $acc): ?>
                    <div 
                        class="account-card group p-4 bg-white/80 backdrop-blur rounded-xl border border-gray-100 mb-3 cursor-pointer transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:border-primary-200"
                        data-account='<?= htmlspecialchars(json_encode($acc), ENT_QUOTES, 'UTF-8') ?>'
                    >
                        <div class="hover-layer absolute inset-0 rounded-xl pointer-events-none opacity-0 bg-gradient-to-br from-primary-50/70 via-transparent to-white"></div>
                        <div class="relative flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-gray-800 truncate max-w-xs"><?= htmlspecialchars($acc['name'] ?? 'N/A') ?></span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-primary-50 text-primary-600 border border-primary-100 font-medium">
                                        <?= htmlspecialchars(ucfirst($acc['account_type'])) ?>
                                    </span>
                                    <?php if (!empty($acc['role'])): ?>
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 border font-medium">
                                            <?= htmlspecialchars($acc['role']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full <?= $acc['isActive'] ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' ?> font-medium">
                                        <?= $acc['isActive'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                                <?php if (!empty($acc['email'])): ?>
                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1"><i class="fa fa-envelope text-[10px]"></i><span class="truncate max-w-[220px]"><?= htmlspecialchars($acc['email']) ?></span></div>
                                <?php endif; ?>
                            </div>
                            <a href="view_account.php?type=<?= urlencode($acc['account_type']) ?>&id=<?= urlencode($acc['id']) ?>" class="relative z-10 shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary-600 text-white text-sm font-medium shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition">
                                <i class="fa fa-eye text-xs"></i>
                                <span>View Details</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Residents -->
            <div id="accounts-residents" class="accounts-section hidden">
                <?php foreach ($residents as $res): ?>
                    <?php 
                        $resData = [
                            'id' => $res['id'],
                            'account_type' => 'resident',
                            'role' => 'Resident',
                            'name' => $res['fullname'],
                            'birthdate' => $res['Birthdate'],
                            'address' => $res['Address'],
                            'contact_number' => $res['Contact_Number'],
                            'username' => $res['username'],
                            'isActive' => $res['isActive'],
                            'email' => $res['email'],
                        ];
                    ?>
                    <div 
                        class="account-card group p-4 bg-white/80 backdrop-blur rounded-xl border border-gray-100 mb-3 cursor-pointer transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:border-primary-200"
                        data-account='<?= htmlspecialchars(json_encode($resData), ENT_QUOTES, 'UTF-8') ?>'
                    >
                        <div class="hover-layer absolute inset-0 rounded-xl pointer-events-none opacity-0 bg-gradient-to-br from-primary-50/70 via-transparent to-white"></div>
                        <div class="relative flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-gray-800 truncate max-w-xs"><?= htmlspecialchars($res['fullname']) ?></span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-primary-50 text-primary-600 border border-primary-100 font-medium">Resident</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full <?= $res['isActive'] ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' ?> font-medium"><?= $res['isActive'] ? 'Active' : 'Inactive' ?></span>
                                </div>
                                <?php if (!empty($res['email'])): ?>
                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1"><i class="fa fa-envelope text-[10px]"></i><span class="truncate max-w-[220px]"><?= htmlspecialchars($res['email']) ?></span></div>
                                <?php endif; ?>
                            </div>
                            <a href="view_account.php?type=resident&id=<?= urlencode($res['id']) ?>" class="relative z-10 shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary-600 text-white text-sm font-medium shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition">
                                <i class="fa fa-eye text-xs"></i>
                                <span>View Details</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- External Complainants -->
            <div id="accounts-external" class="accounts-section hidden">
                <?php foreach ($externals as $ext): ?>
                    <?php
                        $extData = [
                            'id' => $ext['id'],
                            'account_type' => 'external',
                            'role' => 'External Complaint',
                            'name' => $ext['fullname'],
                            'birthdate' => $ext['Birthdate'],
                            'address' => $ext['Address'],
                            'contact_number' => '',
                            'username' => $ext['username'],
                            'isActive' => $ext['isActive'],
                            'email' => $ext['email'],
                        ];
                    ?>
                    <div 
                        class="account-card group p-4 bg-white/80 backdrop-blur rounded-xl border border-gray-100 mb-3 cursor-pointer transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:border-primary-200"
                        data-account='<?= htmlspecialchars(json_encode($extData), ENT_QUOTES, 'UTF-8') ?>'
                    >
                        <div class="hover-layer absolute inset-0 rounded-xl pointer-events-none opacity-0 bg-gradient-to-br from-primary-50/70 via-transparent to-white"></div>
                        <div class="relative flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-gray-800 truncate max-w-xs"><?= htmlspecialchars($ext['fullname']) ?></span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-primary-50 text-primary-600 border border-primary-100 font-medium">External</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full <?= $ext['isActive'] ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' ?> font-medium"><?= $ext['isActive'] ? 'Active' : 'Inactive' ?></span>
                                </div>
                                <?php if (!empty($ext['email'])): ?>
                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1"><i class="fa fa-envelope text-[10px]"></i><span class="truncate max-w-[220px]"><?= htmlspecialchars($ext['email']) ?></span></div>
                                <?php endif; ?>
                            </div>
                            <a href="view_account.php?type=external&id=<?= urlencode($ext['id']) ?>" class="relative z-10 shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary-600 text-white text-sm font-medium shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition">
                                <i class="fa fa-eye text-xs"></i>
                                <span>View Details</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Captains -->
            <div id="accounts-captain" class="accounts-section hidden">
                <?php foreach ($captains as $cap): ?>
                    <?php
                        $capData = [
                            'id' => $cap['id'],
                            'account_type' => 'official',
                            'role' => $cap['role'],
                            'name' => $cap['Name'],
                            'birthdate' => null,
                            'address' => null,
                            'contact_number' => $cap['contact_number'],
                            'username' => $cap['official_username'],
                            'isActive' => $cap['isActive'],
                            'email' => $cap['email'],
                        ];
                    ?>
                    <div 
                        class="account-card group p-4 bg-white/80 backdrop-blur rounded-xl border border-gray-100 mb-3 cursor-pointer transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:border-primary-200"
                        data-account='<?= htmlspecialchars(json_encode($capData), ENT_QUOTES, 'UTF-8') ?>'
                    >
                        <div class="hover-layer absolute inset-0 rounded-xl pointer-events-none opacity-0 bg-gradient-to-br from-primary-50/70 via-transparent to-white"></div>
                        <div class="relative flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-gray-800 truncate max-w-xs"><?= htmlspecialchars($cap['Name']) ?></span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-primary-50 text-primary-600 border border-primary-100 font-medium">Official</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 border font-medium">Captain</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full <?= $cap['isActive'] ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' ?> font-medium"><?= $cap['isActive'] ? 'Active' : 'Inactive' ?></span>
                                </div>
                                <?php if (!empty($cap['email'])): ?>
                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1"><i class="fa fa-envelope text-[10px]"></i><span class="truncate max-w-[220px]"><?= htmlspecialchars($cap['email']) ?></span></div>
                                <?php endif; ?>
                            </div>
                            <a href="view_account.php?type=official&id=<?= urlencode($cap['id']) ?>" class="relative z-10 shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary-600 text-white text-sm font-medium shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition">
                                <i class="fa fa-eye text-xs"></i>
                                <span>View Details</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Secretaries -->
            <div id="accounts-secretary" class="accounts-section hidden">
                <?php foreach ($secretaries as $sec): ?>
                    <?php
                        $secData = [
                            'id' => $sec['id'],
                            'account_type' => 'official',
                            'role' => $sec['role'],
                            'name' => $sec['Name'],
                            'birthdate' => null,
                            'address' => null,
                            'contact_number' => $sec['contact_number'],
                            'username' => $sec['official_username'],
                            'isActive' => $sec['isActive'],
                            'email' => $sec['email'],
                        ];
                    ?>
                    <div 
                        class="account-card group p-4 bg-white/80 backdrop-blur rounded-xl border border-gray-100 mb-3 cursor-pointer transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:border-primary-200"
                        data-account='<?= htmlspecialchars(json_encode($secData), ENT_QUOTES, 'UTF-8') ?>'
                    >
                        <div class="hover-layer absolute inset-0 rounded-xl pointer-events-none opacity-0 bg-gradient-to-br from-primary-50/70 via-transparent to-white"></div>
                        <div class="relative flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-gray-800 truncate max-w-xs"><?= htmlspecialchars($sec['Name']) ?></span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-primary-50 text-primary-600 border border-primary-100 font-medium">Official</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 border font-medium">Secretary</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full <?= $sec['isActive'] ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' ?> font-medium"><?= $sec['isActive'] ? 'Active' : 'Inactive' ?></span>
                                </div>
                                <?php if (!empty($sec['email'])): ?>
                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1"><i class="fa fa-envelope text-[10px]"></i><span class="truncate max-w-[220px]"><?= htmlspecialchars($sec['email']) ?></span></div>
                                <?php endif; ?>
                            </div>
                            <a href="view_account.php?type=official&id=<?= urlencode($sec['id']) ?>" class="relative z-10 shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary-600 text-white text-sm font-medium shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition">
                                <i class="fa fa-eye text-xs"></i>
                                <span>View Details</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>

         <!-- Lupon Tagapamayapa -->
            <div id="accounts-lupon" class="accounts-section hidden">
                <?php foreach ($lupons as $lup): ?>
                    <?php
                        $luponData = [
                            'id' => $lup['id'],
                            'account_type' => 'official',
                            'role' => $lup['role'],
                            'name' => $lup['Name'],
                            'birthdate' => null,
                            'address' => null,
                            'contact_number' => $lup['contact_number'],
                            'username' => $lup['official_username'],
                            'isActive' => $lup['isActive'],
                            'email' => $lup['email'],
                        ];
                    ?>
                    <div 
                        class="account-card group p-4 bg-white/80 backdrop-blur rounded-xl border border-gray-100 mb-3 cursor-pointer transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:border-primary-200"
                        data-account='<?= htmlspecialchars(json_encode($luponData), ENT_QUOTES, 'UTF-8') ?>'
                    >
                        <div class="hover-layer absolute inset-0 rounded-xl pointer-events-none opacity-0 bg-gradient-to-br from-primary-50/70 via-transparent to-white"></div>
                        <div class="relative flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-gray-800 truncate max-w-xs"><?= htmlspecialchars($lup['Name']) ?></span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-primary-50 text-primary-600 border border-primary-100 font-medium">Official</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 border font-medium">Lupon</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full <?= $lup['isActive'] ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' ?> font-medium"><?= $lup['isActive'] ? 'Active' : 'Inactive' ?></span>
                                </div>
                                <?php if (!empty($lup['email'])): ?>
                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1"><i class="fa fa-envelope text-[10px]"></i><span class="truncate max-w-[220px]"><?= htmlspecialchars($lup['email']) ?></span></div>
                                <?php endif; ?>
                            </div>
                            <a href="view_account.php?type=official&id=<?= urlencode($lup['id']) ?>" class="relative z-10 shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary-600 text-white text-sm font-medium shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition">
                                <i class="fa fa-eye text-xs"></i>
                                <span>View Details</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

    <script>
        // Enhanced tab chip + search logic
        const chipButtons = document.querySelectorAll('.acc-chip');
        const sections = document.querySelectorAll('.accounts-section');
        const sectionMap = {
            'accounts-all': 'accounts-all',
            'accounts-residents': 'accounts-residents',
            'accounts-external': 'accounts-external',
            'accounts-captain': 'accounts-captain',
            'accounts-secretary': 'accounts-secretary',
            'accounts-lupon': 'accounts-lupon'
        };
        function activateSection(targetId){
            sections.forEach(s=> s.classList.add('hidden'));
            const el = document.getElementById(targetId);
            if(el) el.classList.remove('hidden');
        }
        chipButtons.forEach(btn=>{
            btn.addEventListener('click',()=>{
                chipButtons.forEach(b=> b.classList.remove('active','bg-primary-600','text-white','shadow'));
                chipButtons.forEach(b=>{
                    if(!b.classList.contains('active')){
                        b.classList.remove('bg-primary-600','text-white','shadow');
                        b.classList.add('bg-primary-50','text-primary-600','border','border-primary-100');
                    }
                });
                btn.classList.add('active','bg-primary-600','text-white','shadow');
                btn.classList.remove('bg-primary-50','text-primary-600','border','border-primary-100');
                const target = btn.dataset.target;
                activateSection(target);
                filterVisibleList();
            });
        });
        // Default first active
        activateSection('accounts-all');

        // Search filtering limited to active section
        const searchInput = document.getElementById('searchName');
        function filterVisibleList(){
            const term = (searchInput.value||'').toLowerCase();
            const currentSection = [...sections].find(sec=> !sec.classList.contains('hidden'));
            if(!currentSection) return;
            currentSection.querySelectorAll('[data-account]').forEach(item=>{
                try{
                    const data = JSON.parse(item.getAttribute('data-account')) || {};
                    const name = (data.name||'').toLowerCase();
                    item.style.display = name.includes(term)? '' : 'none';
                }catch(e){ item.style.display=''; }
            });
        }
        searchInput.addEventListener('input', filterVisibleList);
    </script>
    <?php include 'sidebar_.php';?>
    <?php include('../chatbot/bpamis_case_assistant.php'); ?>
</body>
</html>
