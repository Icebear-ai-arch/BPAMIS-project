<nav class="bg-white shadow-lg">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <a href="bpamis.php" class="flex items-center space-x-3">
                <img src="../SecMenu/logo.png" alt="BPAMIS Logo" class="h-10">
                <span class="font-semibold text-xl">BPAMIS</span>
            </a>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex space-x-8">
                <a href="bpamis.php" class="nav-link group flex items-center space-x-2">
                    <i class="fas fa-home transform group-hover:scale-110 transition-transform"></i>
                    <span>Home</span>
                </a>
                <a href="#about-bpamis" class="nav-link group flex items-center space-x-2">
                    <i class="fas fa-info-circle animate-bounce group-hover:animate-none"></i>
                    <span>About</span>
                </a>
                <a href="#featured-services" class="nav-link group flex items-center space-x-2">
                    <i class="fas fa-cogs animate-spin-slow group-hover:animate-none"></i>
                    <span>Services</span>
                </a>
                <a href="login.php" class="nav-link group flex items-center space-x-2">
                    <i class="fas fa-sign-in-alt group-hover:translate-x-1 transition-transform"></i>
                    <span>Login</span>
                </a>
                <a href="register.php" class="nav-link group flex items-center space-x-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-user-plus animate-pulse group-hover:animate-none"></i>
                    <span>Register</span>
                </a>
            </div>

            <!-- Mobile menu button -->
            <button class="md:hidden flex items-center" id="mobile-menu-button">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>

        <!-- Mobile Navigation -->
        <div class="md:hidden hidden" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="bpamis.php" class="mobile-nav-link flex items-center space-x-2 p-2">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="#about-bpamis" class="mobile-nav-link flex items-center space-x-2 p-2">
                    <i class="fas fa-info-circle"></i>
                    <span>About</span>
                </a>
                <a href="#featured-services" class="mobile-nav-link flex items-center space-x-2 p-2">
                    <i class="fas fa-cogs"></i>
                    <span>Services</span>
                </a>
                <a href="login.php" class="mobile-nav-link flex items-center space-x-2 p-2">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </a>
                <a href="register.php" class="mobile-nav-link flex items-center space-x-2 p-2">
                    <i class="fas fa-user-plus"></i>
                    <span>Register</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<style>
    /* Navigation Link Styles */
    .nav-link {
        @apply text-gray-600 hover:text-blue-600 transition-colors duration-200 font-medium;
    }
    
    .mobile-nav-link {
        @apply text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all duration-200;
    }

    /* Custom Animation Class */
    .animate-spin-slow {
        animation: spin 3s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }

    /* Group Hover Effects */
    .group:hover .group-hover\:scale-110 {
        transform: scale(1.1);
    }

    .group:hover .group-hover\:translate-x-1 {
        transform: translateX(0.25rem);
    }

    /* Animated Icons */
    .fas {
        transition: all 0.3s ease;
    }

    .nav-link:hover .fas {
        transform: translateY(-2px);
    }
</style>

<script>
    // Ensure favicon uses SecMenu/logo.png on public site pages
    (function setFavicon(){
        try {
            var link = document.querySelector("link[rel*='icon']") || document.createElement('link');
            link.type = 'image/png';
            link.rel = 'icon';
            link.href = '../SecMenu/logo.png';
            document.head.appendChild(link);
        } catch(e) {}
    })();
    // Mobile menu toggle
    document.addEventListener('DOMContentLoaded', function() {
        const menuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        
        menuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
            // Animate menu icon
            const icon = this.querySelector('.fas');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        });
    });
</script>