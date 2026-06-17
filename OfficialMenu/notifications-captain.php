<?php
include '../controllers/session_control.php';
include '../server/server.php';

$unverifiedAccounts = [];

$sql = "SELECT resident_id, first_name, last_name, email FROM resident_info WHERE isverify = 0";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $unverifiedAccounts[] = $row;
    }
}


// Fetch all relevant notifications for the captain (exclude trashed)
$sql = "SELECT n.* FROM notifications n
        LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
        WHERE n.type IN ('Unverified', 'Hearing', 'Complaint', 'Case', 'Arbitration') 
                    AND t.notification_id IS NULL
                    AND LOWER(COALESCE(n.title, '')) <> 'barangay notice: you have been named as respondent'
        ORDER BY n.created_at DESC";
$result = $conn->query($sql);

$notifications = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Exclude "New Case Assigned" notifications and specific Conciliation assignment messages
$notifications = array_values(array_filter($notifications, function($n){
    $msg = trim($n['message'] ?? '');
    $title = trim($n['title'] ?? '');
    if (strcasecmp($title, 'New Case Assigned') === 0) return false;
    if (strcasecmp($title, 'Barangay Notice: You Have Been Named as Respondent') === 0) return false;
    if (preg_match('/^A new case #\d+ has been assigned to you in the Conciliation stage\.?$/i', $msg)) return false;
    return true;
}));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
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
                        'float': 'float 3s ease-in-out infinite',
                        'pulse-subtle': 'pulse-subtle 2s infinite',
                        'bell-ring': 'bell-ring 1s ease-in-out',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' }
                        },
                        'pulse-subtle': {
                            '0%, 100%': { opacity: 1 },
                            '50%': { opacity: 0.8 }
                        },
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
        .shadow-glow { box-shadow: 0 0 0 1px rgba(12,156,237,0.08), 0 10px 24px -8px rgba(6,90,143,0.20); }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .notification-card {
            transition: all 0.2s ease;
        }
        .notification-card:hover {
            background-color: #f9fafc;
        }
        .unread-indicator {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #0c9ced;
            top: 22px;
            right: 22px;
        }
        
        /* Notification item styles */
        .notification-item {
            transition: all 0.2s ease;
        }
        .notification-item:hover {
            background-color: #f9fafb;
        }
        .notification-dot {
            transition: all 0.2s ease;
        }
        .notification-item:hover .notification-dot {
            transform: scale(1.2);
        }
        .gradient-bg {
            background: linear-gradient(to right, #f0f7ff, #e0effe);
        }
        
        /* Empty state animation */
        .empty-icon-container {
            animation: float 4s ease-in-out infinite;
        }
        
        /* Mobile Optimizations */
        @media (max-width: 640px) {
            .gradient-bg { padding: 1rem !important; }
        }
        
        /* Slide-out on delete animation */
        .notification-card.slide-out-left {
            transform: translateX(-100%);
            opacity: 0;
            transition: transform 0.3s ease-out, opacity 0.3s ease-out;
        }
      
        .pulse {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-color: rgba(2, 129, 212, 0.7);
            opacity: 0;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(0.95);
                opacity: 0.7;
            }
            70% {
                transform: scale(1.1);
                opacity: 0;
            }
            100% {
                transform: scale(0.95);
                opacity: 0;
            }
        }
       
        
        .send-button {
            background: #0c9ced;
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-left: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        .send-button:hover {
            background: #0281d4;
        }
        
        .chat-message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }
        
        .user-message {
            justify-content: flex-end;
        }
        
        .bot-message {
            justify-content: flex-start;
        }
        
        .message-content {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
            position: relative;
        }
        
        .user-message .message-content {
            background-color: #0c9ced;
            color: white;
            border-bottom-right-radius: 4px;
            margin-right: 10px;
        }
        
        .bot-message .message-content {
            background-color: #f0f7ff;
            color: #333;
            border-bottom-left-radius: 4px;
            margin-left: 10px;
        }
        
        .bot-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e0effe;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .bot-avatar i {
            color: #0281d4;
            font-size: 16px;
        }
        
        .message-time {
            font-size: 10px;
            color: #888;
            margin-top: 4px;
            text-align: right;
        }
        
     
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include_once ('../includes/barangay_official_cap_nav.php'); ?>
    <?php include('../chatbot/bpamis_case_assistant.php'); ?>
    <!-- Global Background Blush (floating orbs) -->
    <div class="fixed inset-0 -z-10 pointer-events-none overflow-hidden">
        <div class="absolute -top-16 -right-24 w-[28rem] h-[28rem] bg-primary-100 rounded-full blur-3xl opacity-60 animate-float"></div>
        <div class="absolute top-1/3 -left-24 w-[22rem] h-[22rem] bg-primary-200 rounded-full blur-3xl opacity-50 animate-float"></div>
        <div class="absolute -bottom-24 right-1/4 w-[20rem] h-[20rem] bg-primary-50 rounded-full blur-2xl opacity-60 animate-float" style="animation-delay:1.2s"></div>
    </div>

    <!-- Page Header -->
    <div class="max-w-7xl mx-auto w-full mt-4 sm:mt-6 md:mt-8 lg:mt-10 px-3 sm:px-4">
        <div class="gradient-bg rounded-xl sm:rounded-2xl shadow-sm p-4 sm:p-6 md:p-8 lg:p-10 relative overflow-hidden border border-primary-100/60 shadow-glow">
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary-100 rounded-full -mr-20 -mt-20 opacity-70 animate-float"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-primary-200 rounded-full -ml-10 -mb-10 opacity-60 animate-float"></div>
            <div class="relative z-10 flex justify-between items-center gap-4">
                <div class="flex-1 min-w-0">
                    <h2 class="text-xl sm:text-2xl md:text-3xl font-light text-primary-800">Your <span class="font-medium">Notifications</span></h2>
                    <p class="mt-2 sm:mt-3 text-xs sm:text-sm md:text-base text-gray-600 max-w-md">Stay updated with the latest activity in your cases and complaints.</p>
                </div>
                <div class="hidden md:flex items-center flex-shrink-0">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 bg-blue-50 rounded-full flex items-center justify-center animate-bell-ring">
                        <i class="fas fa-bell text-primary-500 text-xl sm:text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters & Search -->
    <div class="max-w-7xl mx-auto w-full mt-4 sm:mt-6 px-3 sm:px-4">
        <div class="relative rounded-xl sm:rounded-2xl border border-primary-100/60 bg-gradient-to-r from-white to-primary-50/40 shadow-glow p-4 sm:p-5 md:p-6 overflow-hidden">
            <div class="absolute -top-12 -right-10 w-40 h-40 bg-primary-100 rounded-full blur-2xl opacity-70 animate-float"></div>
            <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-primary-200 rounded-full blur-2xl opacity-60 animate-float" style="animation-delay:.8s"></div>
                <div class="relative z-10 space-y-3 sm:space-y-4">
                    <?php if (isset($_GET['mark'])): ?>
                        <div class="px-3 py-2 rounded-md text-xs sm:text-sm <?php echo ($_GET['mark'] == '1') ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                            <?php if ($_GET['mark'] == '1'): ?>
                                Marked <?php echo isset($_GET['affected']) ? (int)$_GET['affected'] : 0; ?> notifications as read.
                            <?php else: ?>
                                Failed to mark notifications as read<?php echo isset($_GET['error']) ? ': ' . htmlspecialchars($_GET['error']) : ''; ?>.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <!-- Filter Buttons and Mark All Read -->
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                        <button class="filter-btn px-2 py-1 sm:px-3 bg-primary-50 text-primary-700 rounded-lg text-[10px] sm:text-xs font-medium border border-primary-100">All</button>
                        <button class="filter-btn px-2 py-1 sm:px-3 text-gray-500 rounded-lg text-[10px] sm:text-xs hover:bg-gray-50">Unread</button>
                        <button class="filter-btn px-2 py-1 sm:px-3 text-gray-500 rounded-lg text-[10px] sm:text-xs hover:bg-gray-50">Cases</button>
                        <button class="filter-btn px-2 py-1 sm:px-3 text-gray-500 rounded-lg text-[10px] sm:text-xs hover:bg-gray-50">Complaints</button>
                        <button class="filter-btn px-2 py-1 sm:px-3 text-gray-500 rounded-lg text-[10px] sm:text-xs hover:bg-gray-50">Hearings</button>
                    </div>
                    
                    <a href="../controllers/mark_all_notifications_read_captain_redirect.php" class="px-2 py-1 sm:px-3 text-gray-500 rounded-lg text-[10px] sm:text-xs hover:bg-gray-50 flex items-center gap-1 border border-gray-200">
                        <i class="fa-solid fa-check-circle text-xs sm:text-sm"></i> Mark All Read (Direct)
                    </a>
                </div>
                
                <!-- Search and Filters - Two Rows -->
                <div class="space-y-3">
                    <!-- Search - Full Width -->
                    <div class="relative">
                        <input 
                            type="text" 
                            placeholder="Search notifications..." 
                            class="pl-9 sm:pl-11 pr-3 sm:pr-4 py-2 sm:py-3 rounded-lg sm:rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-300 w-full bg-white/80 backdrop-blur text-xs sm:text-sm"
                        >
                        <div class="absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-xs sm:text-sm">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                    
                    <!-- Filters Row -->
                    <div class="grid grid-cols-4 gap-2 sm:gap-3 pb-3 sm:pb-0">
                        <select id="monthFilter" class="py-2 px-2 sm:px-3 rounded-lg border border-gray-200 text-[10px] sm:text-xs focus:outline-none focus:ring-2 focus:ring-primary-100 bg-white/80 backdrop-blur appearance-none">
                            <option value="">All Months</option>
                            <?php for ($m=1; $m<=12; $m++){ $mn = date('F', mktime(0,0,0,$m,1)); echo "<option value=\"$m\">$mn</option>"; } ?>
                        </select>
                        <select id="yearFilter" class="py-2 px-2 sm:px-3 rounded-lg border border-gray-200 text-[10px] sm:text-xs focus:outline-none focus:ring-2 focus:ring-primary-100 bg-white/80 backdrop-blur appearance-none">
                            <option value="">All Years</option>
                            <?php $cy = (int)date('Y'); for ($y=$cy; $y>=$cy-10; $y--){ echo "<option value=\"$y\">$y</option>"; } ?>
                        </select>
                        <select id="sortSelect" class="py-2 px-2 sm:px-3 rounded-lg border border-gray-200 text-[10px] sm:text-xs focus:outline-none focus:ring-2 focus:ring-primary-100 bg-white/80 backdrop-blur appearance-none">
                            <option value="newest">Newest</option>
                            <option value="oldest">Oldest</option>
                        </select>
                        <button id="resetFilters" class="py-2 px-2 sm:px-3 rounded-lg border border-gray-200 text-[10px] sm:text-xs hover:bg-gray-50 bg-white/80 backdrop-blur"><i class="fa-solid fa-rotate-left"></i><span class="hidden xl:inline ml-1">Reset</span></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications List -->
<div id="notification-regular">
    <div class="max-w-7xl mx-auto w-full mt-4 sm:mt-6 px-3 sm:px-4 pb-10">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="divide-y divide-gray-100">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $row): ?>
                        <?php
                            // Choose icon and colors based on type
                            $icon = 'fa-bell';
                            $bgColor = 'bg-gray-100';
                            $iconColor = 'text-gray-600';

                            switch ($row['type']) {
                                case 'Hearing':
                                    $icon = 'fa-calendar-alt';
                                    $bgColor = 'bg-blue-100';
                                    $iconColor = 'text-blue-600';
                                    break;
                                case 'Complaint':
                                    $icon = 'fa-file-alt';
                                    $bgColor = 'bg-green-100';
                                    $iconColor = 'text-green-600';
                                    break;
                                case 'Case':
                                    $icon = 'fa-gavel';
                                    $bgColor = 'bg-yellow-100';
                                    $iconColor = 'text-yellow-600';
                                    break;
                                case 'Arbitration':
                                    $icon = 'fa-scale-balanced';
                                    $bgColor = 'bg-purple-100';
                                    $iconColor = 'text-purple-600';
                                    break;
                            }

                            // Use captain-specific unread flag if available
                            $isUnread = ((int)($row['is_read_captain'] ?? $row['is_read'] ?? 0)) === 0;
                            $created = date("M d, Y \\a\\t h:i A", strtotime($row['created_at']));
                        ?>
                       <div class="notification-card p-3 sm:p-4 md:p-5 relative cursor-pointer <?= $isUnread ? 'bg-gray-50' : '' ?>" 
     data-type="<?= strtolower($row['type']) ?>" 
     data-unread="<?= $isUnread ? 'true' : 'false' ?>"
     data-created="<?= htmlspecialchars($row['created_at']) ?>">


                            <?php if ($isUnread): ?>
                                <div class="unread-indicator animate-pulse-subtle"></div>
                            <?php endif; ?>
                            <div class="flex">
                                <div class="flex-shrink-0 mr-3 sm:mr-4">
                                    <div class="w-8 h-8 sm:w-9 sm:h-9 md:w-10 md:h-10 <?= $bgColor ?> rounded-full flex items-center justify-center">
                                        <i class="fas <?= $icon ?> <?= $iconColor ?> text-xs sm:text-sm"></i>
                                    </div>
                                </div>
                                <div class="flex-grow min-w-0">
                                    <p class="text-xs sm:text-sm font-medium truncate"><?= htmlspecialchars($row['title']) ?></p>
                                    <p class="text-xs sm:text-sm text-gray-600 mt-1 line-clamp-2"><?= htmlspecialchars(preg_replace('/^(\s*)Your\b/', '$1the', $row['message'], 1)) ?></p>
                                    <div class="flex justify-between items-center mt-2 gap-2">
                                        <p class="text-[10px] sm:text-xs text-gray-500 truncate"><?= $created ?></p>
                                        <div class="flex gap-2 flex-shrink-0">
                                            <a href="view_notification.php?id=<?= $row['notification_id'] ?>" class="text-primary-600 hover:text-primary-700 text-[10px] sm:text-xs font-medium">View</a>
                                            <button type="button" class="btn-delete-notif inline-flex items-center gap-1 px-1.5 py-0.5 sm:px-2 sm:py-1 rounded text-[10px] sm:text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-50 transition" title="Delete" data-id="<?= $row['notification_id'] ?>">
                                                <i class="fa-solid fa-trash"></i>
                                                <span class="hidden sm:inline">Delete</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-5 text-sm text-gray-500">No notifications found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
 
    <!-- para sa unverified account--> 
  <div id="notification-unverified" class="w-full mt-4 sm:mt-6 px-3 sm:px-4 pb-10">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="divide-y divide-gray-100">
            <div class="p-3 sm:p-4 bg-gray-50">
                <h3 class="text-xs sm:text-sm font-medium text-gray-500">Unverified Accounts</h3>
            </div>

            <?php if (!empty($unverifiedAccounts)): ?>
                <?php foreach ($unverifiedAccounts as $account): ?>
                    <div class="notification-card p-3 sm:p-4 md:p-5 relative cursor-pointer">
                        <div class="flex">
                            <div class="flex-shrink-0 mr-3 sm:mr-4">
                                <div class="w-8 h-8 sm:w-9 sm:h-9 md:w-10 md:h-10 bg-red-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-circle text-red-600 text-xs sm:text-sm"></i>
                                </div>
                            </div>
                            <div class="flex-grow min-w-0">
                                <p class="text-xs sm:text-sm font-medium">Unverified Account</p>
                                <p class="text-xs sm:text-sm text-gray-600 mt-1 truncate">
                                    <?php echo htmlspecialchars($account['first_name'] . ' ' . $account['last_name']); ?> 
                                    <span class="hidden sm:inline">(<?php echo htmlspecialchars($account['email']); ?>)</span>
                                </p>
                                <div class="flex justify-between items-center mt-2 gap-2">
                                    <p class="text-[10px] sm:text-xs text-gray-500">Awaiting verification</p>
                                    <div class="flex gap-2 flex-shrink-0">
                                        <form method="POST" action="../controllers/account_verification.php">
                                            <input type="hidden" name="id" value="<?php echo $account['resident_id']; ?>">
                                            <button name="verify" type="submit" class="text-green-600 hover:text-green-700 text-[10px] sm:text-xs font-medium">Verify</button>
                                        </form>
                                        <form method="POST" action="../controllers/account_verification.php">
                                            <input type="hidden" name="id" value="<?php echo $account['resident_id']; ?>">
                                            <button name="remove" type="submit" class="text-red-600 hover:text-red-700 text-[10px] sm:text-xs font-medium">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-5 text-sm text-gray-500">No unverified accounts found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
   
            
            
            <div class="mt-4 sm:mt-6 flex justify-center px-3 sm:px-4">
                <button onclick="window.location.href='home-captain.php'" class="px-3 py-1.5 sm:px-4 sm:py-2 text-gray-500 hover:text-gray-700 flex items-center transition-colors text-xs sm:text-sm">
                    <i class="fas fa-arrow-left mr-1.5 sm:mr-2 text-xs sm:text-sm"></i> <span>Back to Dashboard</span>
                </button>
            </div>
        </div>
    
    <!-- No notifications state (hidden by default) -->
    <div id="no-notifications" class="hidden w-full mt-10 px-4 pb-10">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-10 text-center">
            <div class="flex justify-center mb-4">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center">
                    <i class="fas fa-bell-slash text-gray-300 text-3xl"></i>
                </div>
            </div>
            <h3 class="text-lg font-medium text-gray-700 mb-2">No notifications yet</h3>
            <p class="text-gray-500 max-w-md mx-auto">When you receive new notifications about your cases or complaints, they will appear here.</p>
            <div class="mt-6">
                <button onclick="window.location.href='home-captain.php'" class="px-4 py-2 bg-primary-50 text-primary-600 rounded-lg text-sm font-medium hover:bg-primary-100 transition-colors">
                    Return to Dashboard
                </button>
            </div>
        </div>
    </div>
<?php include 'sidebar_.php';?>

    <script>
     document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
        const searchInput = document.querySelector('input[type="text"]');
        const notificationCards = Array.from(document.querySelectorAll('#notification-regular .notification-card'));
        const listWrapper = document.querySelector('#notification-regular .divide-y.divide-gray-100');
        const monthSelect = document.getElementById('monthFilter');
        const yearSelect = document.getElementById('yearFilter');
        const sortSelect = document.getElementById('sortSelect');
        const resetBtn = document.getElementById('resetFilters');
        let activeFilter = 'All';

        function applyFilters() {
            const query = (searchInput?.value || '').toLowerCase();
            const month = monthSelect?.value || '';
            const year = yearSelect?.value || '';
            let hasResults = false;

            notificationCards.forEach(card => {
                const type = card.dataset.type; // 'case','complaint','hearing','unverified'
                const isUnread = card.dataset.unread === 'true';
                const content = card.textContent.toLowerCase();
                const created = card.dataset.created || '';
                const [y, m] = created.split(/[T\s-:]/); // crude parse: y=0, m=1
                const okMonth = !month || parseInt(m || '0') === parseInt(month);
                const okYear = !year || parseInt(y || '0') === parseInt(year);

                let matchesFilter = false;
                if (activeFilter === 'All') matchesFilter = true;
                else if (activeFilter === 'Unread') matchesFilter = isUnread;
                else if (activeFilter === 'Unverified Accounts') matchesFilter = false; // handled via container toggle below
                else {
                    const normalizedFilter = activeFilter.toLowerCase().replace(/s$/, '');
                    matchesFilter = type === normalizedFilter;
                }

                const matchesSearch = !query || content.includes(query);
                const show = matchesSearch && matchesFilter && okMonth && okYear;
                card.style.display = show ? '' : 'none';
                hasResults = hasResults || show;
            });

            const container = document.querySelector('#notification-regular .bg-white');

            if (activeFilter === 'Unverified Accounts') {
                container?.classList.add('hidden');
                document.getElementById('notification-unverified').classList.remove('hidden');
                document.getElementById('no-notifications').classList.add('hidden');
                return;
            }

            document.getElementById('notification-unverified').classList.add('hidden');

            if (!hasResults) {
                container?.classList.add('hidden');
                const empty = document.getElementById('no-notifications');
                empty.classList.remove('hidden');
                empty.querySelector('h3').textContent = 'No matching notifications';
            } else {
                container?.classList.remove('hidden');
                document.getElementById('no-notifications').classList.add('hidden');
            }

            document.querySelector('.mt-6.flex.justify-center').classList.toggle('hidden', activeFilter !== 'All');
        }

        function sortCards() {
            if (!listWrapper) return;
            const order = sortSelect?.value || 'newest';
            const items = Array.from(listWrapper.children).filter(el => el.classList.contains('notification-card'));
            items.sort((a, b) => {
                const da = new Date(a.dataset.created || 0).getTime();
                const db = new Date(b.dataset.created || 0).getTime();
                return order === 'oldest' ? da - db : db - da;
            });
            items.forEach(el => listWrapper.appendChild(el));
        }

        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                filterButtons.forEach(btn => {
                    btn.classList.remove('bg-primary-50', 'text-primary-700', 'border', 'border-primary-100');
                    btn.classList.add('text-gray-500');
                });
                this.classList.remove('text-gray-500');
                this.classList.add('bg-primary-50', 'text-primary-700', 'border', 'border-primary-100');
                activeFilter = (this.textContent || '').trim();
                applyFilters();
            });
        });

        searchInput?.addEventListener('input', applyFilters);
        monthSelect?.addEventListener('change', applyFilters);
        yearSelect?.addEventListener('change', applyFilters);
        sortSelect?.addEventListener('change', () => { sortCards(); applyFilters(); });
        resetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (monthSelect) monthSelect.value = '';
            if (yearSelect) yearSelect.value = '';
            if (sortSelect) sortSelect.value = 'newest';
            // reset active filter
            activeFilter = 'All';
            filterButtons.forEach(btn => {
                btn.classList.remove('bg-primary-50', 'text-primary-700', 'border', 'border-primary-100');
                btn.classList.add('text-gray-500');
            });
            const first = filterButtons[0];
            if (first) {
                first.classList.add('bg-primary-50','text-primary-700','border','border-primary-100');
                first.classList.remove('text-gray-500');
            }
            sortCards();
            applyFilters();
        });

        sortCards();
        applyFilters();

        // Mark all as read functionality (server + UI with confirmation)
        const markAllButton = document.getElementById('markAllReadBtn');
        markAllButton?.addEventListener('click', async function() {
            const confirmed = window.confirm('Are you sure you want to mark all notifications as read?');
            if (!confirmed) return;
            const originalText = this.innerHTML;
            this.disabled = true;
            this.classList.add('opacity-60','pointer-events-none');
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs sm:text-sm"></i> Working...';
            try {
                const url = '../controllers/mark_all_notifications_read_captain.php?t=' + Date.now();
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                if (!res.ok) throw new Error('Request failed: ' + res.status);
                const ct = (res.headers.get('content-type') || '').toLowerCase();
                let data;
                if (!ct.includes('application/json')) {
                    const txt = await res.text();
                    throw new Error('Unexpected response (Content-Type=' + ct + '): ' + txt.slice(0, 200));
                } else {
                    data = await res.json();
                }
                if (data && data.success) {
                    // Update UI cards
                    document.querySelectorAll('#notification-regular .notification-card').forEach(card => {
                        card.dataset.unread = 'false';
                    });
                    document.querySelectorAll('#notification-regular .unread-indicator').forEach(indicator => {
                        indicator.classList.add('opacity-0');
                        setTimeout(() => { indicator.remove(); }, 200);
                    });
                    // Re-apply filters so 'Unread' view hides items immediately
                    try { applyFilters(); } catch(_) {}
                    // Optionally hide any nav numeric badge near the bell icon
                    try {
                        document.querySelectorAll('nav .fa-bell').forEach(icon => {
                            const container = icon.closest('a,div,button') || icon.parentElement;
                            if (!container) return;
                            container.querySelectorAll('span').forEach(sp => {
                                if (/^\d+$/.test((sp.textContent||'').trim())) sp.style.display = 'none';
                            });
                        });
                    } catch(_) {}
                } else {
                    alert('Could not mark all as read. Please try again.');
                }
            } catch (e) {
                console.warn('Failed to mark all as read:', e);
                alert('Mark all as read failed: ' + (e && e.message ? e.message : String(e)));
            } finally {
                this.disabled = false;
                this.classList.remove('opacity-60','pointer-events-none');
                this.innerHTML = originalText;
            }
        });

        // New Mark all as read (fallback endpoint via scope=captain)
        const markAllButton2 = document.getElementById('markAllReadBtn2');
        markAllButton2?.addEventListener('click', async function() {
            const confirmed = window.confirm('Mark ALL captain notifications as read now?');
            if (!confirmed) return;
            const original = this.innerHTML;
            this.disabled = true;
            this.classList.add('opacity-60','pointer-events-none');
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs sm:text-sm"></i> Marking...';
            try {
                const res = await fetch('../controllers/mark_all_notifications_read.php?t=' + Date.now(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                    body: JSON.stringify({ scope: 'captain' })
                });
                if (!res.ok) throw new Error('Request failed: ' + res.status);
                const ct = (res.headers.get('content-type') || '').toLowerCase();
                if (!ct.includes('application/json')) {
                    const text = await res.text();
                    throw new Error('Unexpected response: ' + text.slice(0,200));
                }
                const data = await res.json();
                if (!data || !data.success) throw new Error('Server returned success=false');
                // Update UI
                document.querySelectorAll('#notification-regular .notification-card').forEach(card => {
                    card.dataset.unread = 'false';
                });
                document.querySelectorAll('#notification-regular .unread-indicator').forEach(el => {
                    el.classList.add('opacity-0');
                    setTimeout(() => el.remove(), 200);
                });
                try { applyFilters(); } catch(_) {}
                try { alert('Marked ' + (data.affected ?? 0) + ' notifications as read.'); } catch(_) {}
            } catch (e) {
                console.warn('Mark all (new) failed:', e);
                alert('Mark all (new) failed: ' + (e && e.message ? e.message : String(e)));
            } finally {
                this.disabled = false;
                this.classList.remove('opacity-60','pointer-events-none');
                this.innerHTML = original;
            }
        });

        // Mobile menu toggle
        const menuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        if (menuButton && mobileMenu) {
            menuButton.addEventListener('click', function() {
                this.classList.toggle('active');
                mobileMenu.style.transform = (mobileMenu.style.transform === 'translateY(0%)') ? 'translateY(-100%)' : 'translateY(0%)';
            });
        }

        // Delete notification handler  
        document.addEventListener('click', async function(e) {
            const btn = e.target.closest('.btn-delete-notif');
            if (!btn) return;
            
            const notifId = btn.dataset.id;
            if (!notifId) return;
            
            const confirmed = window.confirm('Delete this notification? This action cannot be undone.');
            if (!confirmed) return;
            
            const card = btn.closest('.notification-card');
            const wasUnread = card ? (card.dataset.unread === 'true') : false;
            
            try {
                // Show loading state
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                
                const res = await fetch('../controllers/delete_notification.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    credentials: 'same-origin',
                    cache: 'no-store',
                    body: JSON.stringify({id: notifId})
                });
                
                if (!res.ok) throw new Error('Network error: ' + res.status);
                
                const ct = (res.headers.get('content-type') || '').toLowerCase();
                if (!ct.includes('application/json')) {
                    const text = await res.text();
                    throw new Error('Expected JSON, got: ' + text.slice(0,100));
                }
                
                const data = await res.json();
                if (!data || !data.success) {
                    const details = (data?.message || data?.error || '');
                    throw new Error(details || 'Server error');
                }
                
                // Animate removal
                if (card) {
                    card.classList.add('slide-out-left');
                    setTimeout(() => {
                        card.remove();
                        // Update navbar badge if notification was unread
                        if (wasUnread) {
                            const badge = document.getElementById('notif-count-badge');
                            if (badge && !badge.classList.contains('hidden')) {
                                const currentCount = parseInt(badge.textContent) || 0;
                                const newCount = Math.max(0, currentCount - 1);
                                badge.textContent = newCount;
                                if (newCount === 0) {
                                    badge.classList.add('hidden');
                                }
                            }
                        }
                    }, 300);
                }
                
            } catch (err) {
                console.warn('Delete failed:', err);
                alert('Failed to delete notification: ' + (err.message || err));
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash"></i><span class="hidden sm:inline">Delete</span>';
            }
        });
    });
    </script>
   

</body>
</html>

