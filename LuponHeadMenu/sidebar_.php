
<script src="https://cdn.tailwindcss.com"></script>
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
                    }
                }
            }
        }
    </script>

<!-- Sidebar Container -->
<div id="sidebar" class="fixed top-0 left-0 h-full w-4/5 sm:w-72 bg-white shadow-lg transition-transform duration-300 transform -translate-x-full"
     style="z-index:1101;">
  
  <!-- Header Section -->
  <div class="flex items-center justify-between p-4 border-b border-gray-200">
    <div class="flex items-center gap-3">
      <img src="../Assets/Img/logo.png" alt="Logo" class="w-10 h-10">
      <div>
        <div class="text-lg text-sky-600">BPAMIS</div>
        <div class="text-xs text-slate-500">Case Management</div>
      </div>
    </div>
    <button id="close-sidebar" class="text-gray-500 hover:text-gray-700 focus:outline-none">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <!-- Role Indicator -->
  <div class="px-4 py-3 bg-primary-50 border-b border-primary-100">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center">
        <i class="fas fa-gavel text-primary-600"></i>
      </div>
      <div>
        <div class="text-sm text-slate-700">Lupon Tagapamayapa Head</div>
        <div class="text-xs text-slate-500">Adjudication Panel</div>
      </div>
    </div>
  </div>

  <!-- Navigation Menu -->
  <?php
  // Apply top margin only on mobile and only when this sidebar is included on view_cases.php
  $currentScript = basename($_SERVER['PHP_SELF'] ?? '');
  $mobileMtClass = ($currentScript === 'view_cases.php') ? 'mt-32 md:mt-0' : '';
  ?>
  <nav class="p-4 <?= $mobileMtClass ?> overflow-y-auto" style="height: calc(100% - 180px);">
    <ul class="space-y-1">
      <!-- Dashboard -->
      <li>
        <a href="home-luponhead.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-50 hover:text-primary-700 transition-colors">
          <i class="fas fa-home w-5 text-center"></i>
          <span>Dashboard</span>
        </a>
      </li>

      <!-- Case Management Section -->
      <li class="pt-4 pb-2">
        <span class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Case Management</span>
      </li>

      <!-- Assign Case -->
      <li>
        <a href="assign_case.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-50 hover:text-primary-700 transition-colors">
          <i class="fas fa-user-plus w-5 text-center"></i>
          <span>Assign Case</span>
        </a>
      </li>

      <!-- Assigned Cases -->
      <li>
        <a href="assigned_case.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-50 hover:text-primary-700 transition-colors">
          <i class="fas fa-clipboard-list w-5 text-center"></i>
          <span>Assigned Cases</span>
        </a>
      </li>

      <!-- View Cases -->
      <li>
        <a href="view_cases.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-50 hover:text-primary-700 transition-colors">
          <i class="fas fa-folder w-5 text-center"></i>
          <span>View Cases</span>
        </a>
      </li>

      <!-- Schedule Section -->
      <li class="pt-4 pb-2">
        <span class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Schedule</span>
      </li>

      <!-- Appoint Hearing -->
      <!-- <li>
        <a href="appoint_hearing.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-50 hover:text-primary-700 transition-colors">
          <i class="fas fa-calendar-alt w-5 text-center"></i>
          <span>Appoint Hearing</span>
        </a>
      </li> -->

      <!-- View Hearing Calendar -->
      <li>
        <a href="view_hearing_calendar.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-50 hover:text-primary-700 transition-colors">
          <i class="fas fa-calendar-days w-5 text-center"></i>
          <span>View Hearing Calendar</span>
        </a>
      </li>

      <!-- Communication Section -->
      <li class="pt-4 pb-2">
        <span class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Communication</span>
      </li>

      <!-- Feedback -->
      <li>
        <a href="feedback_luponhead.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-50 hover:text-primary-700 transition-colors">
          <i class="fas fa-comments w-5 text-center"></i>
          <span>Feedback</span>
        </a>
      </li>

      
    </a>
    </ul>
  </nav>

  

</div>

<!-- Note: Sidebar is now direct HTML matching secretary's design -->
<script>
// Ensure the sidebar is appended directly to document.body so position:fixed behaves relative to the viewport
document.addEventListener('DOMContentLoaded', function(){
    try {
        var sb = document.getElementById('sidebar');
        if (sb && sb.parentElement !== document.body) {
            document.body.appendChild(sb);
        }
    } catch (e) { /* ignore */ }
});
</script>