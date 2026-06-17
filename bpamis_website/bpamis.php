<?php
session_start();
// Load site settings for dynamic barangay info
try {
    include_once(__DIR__ . '/../server/server.php');
    // Ensure table exists (no-op if exists)
    $conn->query("CREATE TABLE IF NOT EXISTS site_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT)");
    $settings = [];
    $res = $conn->query("SELECT setting_key, setting_value FROM site_settings");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Throwable $e) {
    // If DB not available on public page, continue with defaults
    $settings = [];
}

// Derive display values with safe integer casting
$families = isset($settings['stats_total_families']) ? (int)$settings['stats_total_families'] : 0;
$seniors = isset($settings['stats_total_seniors']) ? (int)$settings['stats_total_seniors'] : 0;
$children = isset($settings['stats_total_children']) ? (int)$settings['stats_total_children'] : 0;
$adults = isset($settings['stats_total_adults']) ? (int)$settings['stats_total_adults'] : 0;
$childrenAdults = max(0, $children + $adults);
$population = isset($settings['stats_total_population']) ? (int)$settings['stats_total_population'] : 0;
$pwd = isset($settings['community_total_pwd']) ? (int)$settings['community_total_pwd'] : 0;
$communitySeniors = isset($settings['community_total_seniors']) ? (int)$settings['community_total_seniors'] : 0;
$pwdSenior = max(0, $pwd + $communitySeniors);
$economic = isset($settings['community_total_economic']) ? (int)$settings['community_total_economic'] : 0;
$women = isset($settings['community_total_women']) ? (int)$settings['community_total_women'] : 0;
$commChildren = isset($settings['community_total_children']) ? (int)$settings['community_total_children'] : 0;
$womenChildren = max(0, $women + $commChildren);
$aboutRoleDescription = isset($settings['about_role_description']) ? trim($settings['about_role_description']) : '';
$bpamisAdoption = isset($settings['bpamis_adoption_percent']) ? (float)$settings['bpamis_adoption_percent'] : 0.0;
// Apply sensible defaults if DB lacks values, per user's provided figures
if ($families === 0) $families = 1084;
if ($seniors === 0) $seniors = 522;
if ($children === 0) $children = 677; // infants+pre-schoolers+preschool children+school age+teenagers
if ($adults === 0) $adults = 1973;
$childrenAdults = max(0, $children + $adults);
if ($population === 0) $population = 3318;
if ($bpamisAdoption === 0.0) $bpamisAdoption = 79.86;
// Default About/Role description if missing
if ($aboutRoleDescription === '') {
    $aboutRoleDescription = "Barangay Panducot as thriving community in Calumpit, Bulacan, stands as a beacon of peace, unity, and progressive governance. Our barangay values efficient public service and transparent community leadership that serves every resident with dedication and integrity.\n\nAs our community grows and local concerns evolve, Barangay Panducot embraces innovation through BPAMIS (Barangay Panducot Adjudication Management Information System). This cutting-edge digital platform revolutionizes how we handle community disputes, streamline case filing, track resolutions, and ensure fair, transparent mediation for all residents.\n\nWith BPAMIS, we're not just modernizing our processes—we're strengthening the foundation of justice at the grassroots level, making legal services more accessible, efficient, and responsive to our community's needs.";
}
// Calculate Children+Adults as percentage of population
$childrenAdultsPercent = $population > 0 ? (($childrenAdults / $population) * 100.0) : 0.0;
// Calculate Women+Children as percentage of population
$womenChildrenPercent = $population > 0 ? (($womenChildren / $population) * 100.0) : 0.0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>BPAMIS - Barangay Panducot Adjudication Management Information System</title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles/auth.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/marquee.css">
    <link href="https://fonts.googleapis.com/css2?family=Source+Code+Pro&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/marquee.js" defer></script>

    <style>
    /* Responsive welcome pill adjustments */
        @media (max-width: 768px) {
            .welcome-pill[style*="margin-top:50px"] {
                margin-top: 30px !important;
            }
        }        @media (max-width: 480px) {
            .welcome-pill[style*="margin-top:50px"] {
                margin-top: 20px !important;
            }
        }
    
        
        /* User role and card mobile adjustments */
        @media (max-width: 768px) {
            .user-role-card, .user-card {
                margin-left: 1rem;
                margin-right: 1rem;
            }
        }

        @media (max-width: 640px) {
            .user-role-card, .user-card {
                margin-left: 0.75rem;
                margin-right: 0.75rem;
                padding: 1rem;
            }
            
            .user-role-card .role-icon-container, .user-card .w-16 {
                width: 4rem; /* 64px -> 4rem */
                height: 4rem;
                margin-bottom: 0.5rem;
            }
            
            .user-role-card .role-title, .user-card h3 {
                font-size: 1rem; /* Slightly smaller title */
            }
            
            .user-role-card .role-feature, .user-card .space-y-4 {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .user-role-card, .user-card {
                margin-left: 1.5rem;
                margin-right: 0.5rem;
                padding: 0.75rem;
            }
            
            .user-role-card .p-8, .user-card .p-8 {
                padding: 1rem !important;
            }
            
            .user-role-card .space-y-3, .user-card .space-y-4 {
                margin-top: 0.5rem;
            }
            
            .user-role-card .gap-3, .user-card .gap-3 {
                gap: 0.5rem;
            }
        }

        /* Marquee Animation for Service Icons - Enhanced for Mobile */
        .marquee-container {
            overflow: hidden;
            width: 100%;
            position: relative;
            padding: 2.5rem 0;
            white-space: nowrap;
        }

        .marquee-section {
            background: linear-gradient(to right, rgba(255, 255, 255, 0.11), rgba(236, 242, 255, 0.09), rgba(255, 255, 255, 0.11));
            padding: 1rem;
        }

        .marquee-track {
            display: inline-flex;
            align-items: center;
            gap: 2.5rem;
            animation: marquee-move 15s linear infinite;
        }

        .marquee-container:hover .marquee-track {
            animation-play-state: paused;
        }

        @keyframes marquee-move {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
        }

        /* Mobile-specific marquee styles */
        @media (max-width: 768px) {
            .marquee-track {
                animation: marquee-move 15s linear infinite;
                gap: 2rem;
            }

            .marquee-icon {
                width: 4rem !important;
                height: 4rem !important;
                font-size: 2rem !important;
            }

            .marquee-label {
                font-size: 0.9rem !important;
            }
        }

        @media (max-width: 640px) {
            .marquee-track {
                animation: marquee-move 15s linear infinite;
                gap: 1.8rem;
            }

            .marquee-icon {
                width: 3.5rem !important;
                height: 3.5rem !important;
                font-size: 1.8rem !important;
            }

            .marquee-label {
                font-size: 0.85rem !important;
            }
        }

        @media (max-width: 480px) {
            .marquee-section {
                margin-left: 0.5rem !important;
                margin-right: 0.5rem !important;
            }

            .marquee-track {
                animation: marquee-move 15s linear infinite;
                gap: 1.2rem;
            }

            .marquee-icon {
                width: 3rem !important;
                height: 3rem !important;
                font-size: 1.5rem !important;
            }

            .marquee-label {
                margin-top: 0.3rem !important;
                font-size: 0.8rem !important;
            }
        }

        .marquee-icon {
            background: #dbeafe;
            color: #2563eb;
            border-radius: 9999px;
            width: 5rem;
            height: 5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            box-shadow: 0 4px 16px 0 rgba(37, 99, 235, 0.10);
            transition: box-shadow 0.3s, background 0.3s, color 0.3s, transform 0.3s;
        }

        .marquee-icon:hover {
            background: #2563eb;
            color: #fff;
            box-shadow: 0 12px 32px 0 rgba(37, 99, 235, 0.25);
            transform: scale(1.08);
        }

        .marquee-label {
            margin-top: 0.5rem;
            font-size: 1rem;
            color: #333;
            text-align: center;
            white-space: nowrap;
            font-weight: 500;
        }
        

        /* Global Responsive Width Control */
        html,
        body {
            overflow-x: hidden;
            width: 100%;
            max-width: 100%;
        }

        /* Ensure all elements respect container width */
        * {
            box-sizing: border-box;
            max-width: 100%;
        }

        /* Make images responsive */
        img {
            max-width: 100%;
            height: auto;
        }

        /* Premium Stats Panel Effects */
        .premium-stats-panel {
            transform: translateY(0);
            transition: all 0.6s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative;
        }

        .premium-stats-panel:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .premium-panel-gradient {
            background: linear-gradient(135deg,
                    rgba(59, 130, 246, 0.3) 0%,
                    rgba(147, 197, 253, 0.3) 25%,
                    rgba(16, 185, 129, 0.3) 50%,
                    rgba(139, 92, 246, 0.3) 75%,
                    rgba(249, 115, 22, 0.3) 100%);
            background-size: 400% 400%;
            animation: premium-panel-gradient 15s ease infinite;
        }

        @keyframes premium-panel-gradient {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        /* Premium Stat Cards */
        .premium-stat-card {
            transform: translateY(0);
            transition: all 0.5s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        .premium-stat-card:hover {
            transform: translateY(-10px);
        }

        .premium-stat-inner {
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            transition: all 0.5s ease;
        }

        .premium-stat-card:hover .premium-stat-inner {
            box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.1);
        }

        /* Animated border for stat cards */
        .premium-stat-border {
            background: linear-gradient(90deg,
                    rgba(255, 255, 255, 0.2),
                    rgba(255, 255, 255, 0.5),
                    rgba(255, 255, 255, 0.2));
            background-size: 200% 100%;
            animation: shine 3s infinite linear;
            z-index: 1;
        }

        @keyframes shine {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        /* Icon container animations */
        .premium-icon-container {
            transform: scale(1) rotate(0);
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .premium-stat-card:hover .premium-icon-container {
            transform: scale(1.1) rotate(5deg);
        }

        /* Animated light effect in icons */
        .premium-icon-light {
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg,
                    transparent,
                    rgba(255, 255, 255, 0.3),
                    transparent);
            transform: skewX(-20deg);
            top: 0;
            left: -150%;
            animation: icon-light 4s infinite;
            animation-play-state: paused;
        }

        .premium-stat-card:hover .premium-icon-light {
            animation-play-state: running;
        }

        @keyframes icon-light {
            0% {
                left: -150%;
            }

            100% {
                left: 150%;
            }
        }

        /* Subtle particles for stat cards */
        .premium-stat-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .premium-stat-particles::before,
        .premium-stat-particles::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            opacity: 0;
            transition: all 0.5s ease;
        }

        .premium-stat-particles::before {
            width: 8px;
            height: 8px;
            background-color: rgba(255, 255, 255, 0.5);
            top: 20%;
            left: 20%;
        }

        .premium-stat-particles::after {
            width: 12px;
            height: 12px;
            background-color: rgba(255, 255, 255, 0.5);
            bottom: 15%;
            right: 15%;
        }

        .premium-stat-card:hover .premium-stat-particles::before,
        .premium-stat-card:hover .premium-stat-particles::after {
            opacity: 0.7;
            animation: particle-float 3s ease-in-out infinite alternate;
        }

        .premium-stat-card:hover .premium-stat-particles::after {
            animation-delay: 0.5s;
        }

        @keyframes particle-float {
            0% {
                transform: translateY(0) translateX(0);
            }

            100% {
                transform: translateY(-10px) translateX(10px);
            }
        }

        body {
            overflow-x: hidden;
        }

        /* Premium Image Container Effects */
        .premium-image-container {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            position: relative;
        }

        .premium-image-container:hover .premium-overlay {
            opacity: 0.85;
            background-position: 100% 100%;
        }

        .premium-image-container:hover .premium-image {
            transform: scale(1.03);
            filter: saturate(1.1) contrast(1.05);
        }

        .premium-image {
            transition: all 1.5s ease;
            transform-origin: center;
        }

        .premium-overlay {
            transition: all 0.8s ease;
            background-size: 200% 200%;
            background-position: 0% 0%;
            background-image: linear-gradient(to top,
                    rgba(30, 58, 138, 0.7),
                    /* blue-900/70 */
                    transparent 70%,
                    rgba(59, 130, 246, 0.05) 100%
                    /* blue-500/05 */
                );
        }

        .premium-image-border {
            position: absolute;
            inset: 0;
            border: 2px solid transparent;
            border-radius: inherit;
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.2),
                    rgba(255, 255, 255, 0)) border-box;
            -webkit-mask:
                linear-gradient(#fff 0 0) padding-box,
                linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.5s ease;
            z-index: 2;
        }

        .premium-image-container:hover .premium-image-border {
            opacity: 1;
        }

        /* Premium Badge Container Effects */
        .premium-badge-container {
            position: relative;
            width: 56px;
            height: 56px;
            transform: translateY(0) rotate(0);
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden;
        }

        .premium-badge-container:hover {
            transform: translateY(-3px) rotate(5deg) scale(1.1);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        /* Animated gradient border effect */
        .premium-badge-border {
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            padding: 3px;
            background: linear-gradient(135deg,
                    #3b82f6,
                    /* blue-500 */
                    #10b981,
                    /* green-500 */
                    #ec4899,
                    /* pink-500 */
                    #8b5cf6
                    /* purple-500 */
                );
            -webkit-mask:
                linear-gradient(#fff 0 0) content-box,
                linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            animation: rotate-border 4s linear infinite;
            opacity: 0.8;
        }

        @keyframes rotate-border {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Logo glow and pulse effect */
        .premium-logo {
            filter: drop-shadow(0 0 2px rgba(59, 130, 246, 0.3));
            animation: pulse-glow 3s ease-in-out infinite alternate;
        }

        @keyframes pulse-glow {

            0%,
            100% {
                filter: drop-shadow(0 0 2px rgba(59, 130, 246, 0.3));
                transform: scale(1);
            }

            50% {
                filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.6));
                transform: scale(1.05);
            }
        }

        /* Premium Stats Container Effects */
        .premium-stats-container {
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transform: translateY(0);
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .premium-stats-container:hover {
            transform: translateY(-5px) scale(1.03);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        /* Animated gradient background */
        .premium-gradient-bg {
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg,
                    rgba(59, 130, 246, 0.3),
                    /* blue-500 */
                    rgba(16, 185, 129, 0.3),
                    /* green-500 */
                    rgba(236, 72, 153, 0.3),
                    /* pink-500 */
                    rgba(139, 92, 246, 0.3)
                    /* purple-500 */
                );
            animation: rotate-gradient 8s linear infinite;
            z-index: 0;
        }

        @keyframes rotate-gradient {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Floating particles effect */
        .premium-particles {
            position: absolute;
            inset: 0;
            z-index: 1;
            overflow: hidden;
        }

        .premium-particles::before,
        .premium-particles::after {
            content: '';
            position: absolute;
            background: white;
            border-radius: 50%;
            opacity: 0.4;
            animation-timing-function: cubic-bezier(0.25, 0.46, 0.45, 0.94);
            animation-iteration-count: infinite;
        }

        .premium-particles::before {
            width: 6px;
            height: 6px;
            top: 20%;
            left: 20%;
            animation: float-particle 4s infinite;
        }

        .premium-particles::after {
            width: 10px;
            height: 10px;
            bottom: 15%;
            right: 30%;
            animation: float-particle 7s infinite 1s;
        }

        @keyframes float-particle {

            0%,
            100% {
                transform: translateY(0) translateX(0);
                opacity: 0.2;
            }

            25% {
                transform: translateY(-15px) translateX(10px);
                opacity: 0.6;
            }

            50% {
                transform: translateY(5px) translateX(15px);
                opacity: 0.4;
            }

            75% {
                transform: translateY(10px) translateX(-5px);
                opacity: 0.6;
            }
        }

        .hero-section {
            /* background: linear-gradient(180deg, rgba(62, 131, 249, 0.68) 0%, rgba(96, 165, 250, 0.64) 60%, rgb(255, 255, 255)), url('assets/images/brgyhall.png');
             */
            background-size: cover;
            background-position: center;
            height: 860px;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .hero-section::before {
            /* display: none; */
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, rgba(62, 131, 249, 0.83) 0%, rgba(96, 165, 250, 0.64) 60%, rgb(255, 255, 255)), url('assets/images/brgyhall.png');
            filter: blur(8px);
            z-index: 0;
            opacity: 1;
            background-size: cover;
            Background-position: center;

        }

        .hero-section .container {
            position: relative;
            z-index: 1;
        }

        /* Mobile: reduce top padding for hero section */
        @media (max-width: 768px) {
            .hero-section {
                padding-top: 1rem !important;
            }
            .hero-section .container {
                padding-top: 4rem !important; /* ensure no extra top padding inside */
            }
             /* Reduce CTA button text size on mobile */
            .hero-section .cta-buttons a {
                font-size: 0.5rem !important;
            }
            /* Slightly scale down the icon so it matches text size better */
            .hero-section .cta-buttons a i {
                font-size: 0.5em !important;
            }
        }

        @media (max-width: 640px) {
            .hero-section {
                padding-top: 0.75rem !important;
            }
            /* Reduce CTA button text size on mobile */
            .hero-section .cta-buttons a {
                font-size: 0.65rem !important;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            /* Slightly scale down the icon so it matches text size better */
            .hero-section .cta-buttons a i {
                font-size: 0.8em !important;
                margin-right: 0.4rem !important;
            }
        }

        @media (max-width: 480px) {
            .hero-section {
                padding-top: 0.5rem !important;
            }
            /* Further reduce CTA text and padding on very small screens */
            .hero-section .cta-buttons a {
                font-size: 0.6rem !important;
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
                padding-top: 0.5rem !important;
                padding-bottom: 0.5rem !important;
            }
            .hero-section .cta-buttons a i {
                font-size: 0.75em !important;
                margin-right: 0.35rem !important;
            }
        }

        .news-card {
            transition: transform 0.3s ease;
        }

        .news-card:hover {
            transform: translateY(-5px);
        }

        .service-card {
            transition: all 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .gradient-bg {
            background: linear-gradient(to right, #f0f7ff, #e0effe);
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .loader-wrapper {
            position: fixed;
            inset: 0;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity .6s;
        }

        .loader {
            position: relative;
            width: 120px;
            height: 120px;
        }

        .loader-gradient {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: conic-gradient(#60a5fa 0deg, rgba(37, 100, 235, .66) 120deg, rgba(30, 64, 175, .34) 240deg, #60a5fa 360deg);
            animation: spin 1.2s linear infinite;
        }

        .loader-inner {
            position: absolute;
            inset: 10px;
            background: rgba(255, 255, 255, .85);
            border-radius: 50%;
        }

        .loader-logo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 58px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        /* Mobile: keep loader perfectly centered and smaller */
        @media (max-width: 640px) {
            .loader-wrapper {
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .loader {
                width: 88px;
                height: 88px;
            }
            .loader-inner { inset: 8px; }
            .loader-logo { width: 42px; }
        }

        @media (max-width: 480px) {
            .loader { width: 72px; height: 72px; }
            .loader-inner { inset: 6px; }
            .loader-logo { width: 34px; }
        }

        .fade-out {
            opacity: 0;
            pointer-events: none;
        }

        /* Map container styles */
        .map-container {
            position: relative;
            transition: all 0.3s ease;
        }

        .map-container:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
        }

        #modalContent {
            transition: all 0.3s ease-out;
        }

        .scale-95 {
            transform: scale(0.95);
        }

        .scale-100 {
            transform: scale(1);
        }

        /* Note: The appointment modal styles are now included from schedule_appointment_modal.php */

        #contactModal {
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        #contactModalContent {
            transition: all 0.3s ease-out;
        }

        .fade-in-element {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease-out;
        }

        .fade-in-element.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .stagger-fade-delay-1 {
            transition-delay: 0.2s;
        }

        .stagger-fade-delay-2 {
            transition-delay: 0.4s;
        }

        .stagger-fade-delay-3 {
            transition-delay: 0.6s;
        }

        .stagger-fade-delay-4 {
            transition-delay: 0.8s;
        }

        /* Update other sections to maintain consistency */
        #about-bpamis {
            background: linear-gradient(rgba(255, 255, 255, 0.45), rgba(255, 255, 255, 0.45)), url('assets/images/brgyhall.png');
            background-size: cover;
            background-position: center;
            position: relative;
        }

        #featured-services {
            background: linear-gradient(rgba(255, 255, 255, 0.97), rgba(255, 255, 255, 0.97)), url('assets/images/brgyhall.png');
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .service-details {
            margin-bottom: 2rem;
        }

        /* Icon Animation Styles */
        .service-icon {
            transition: all 0.5s ease;
            transform-origin: center;
        }

        /* Gavel Animation */
        .service-card:hover .fa-gavel {
            animation: hammerEffect 1s ease infinite;
        }

        @keyframes hammerEffect {

            0%,
            100% {
                transform: rotate(0deg);
            }

            50% {
                transform: rotate(-20deg);
            }
        }

        /* Document Animation */
        .service-card:hover .fa-file-alt {
            animation: floatFile 1s ease infinite;
        }

        @keyframes floatFile {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        /* Users Animation */
        .service-card:hover .fa-users {
            animation: popUsers 1s ease infinite;
        }

        @keyframes popUsers {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        /* Chevron Animation */
        .chevron-icon {
            transition: transform 0.3s ease;
        }

        .service-card:hover .chevron-icon {
            animation: bounce 1s infinite;
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        /* Hero Button Animations */
        .hero-btn {
            position: relative;
            overflow: hidden;
            transform: translateY(0);
            transition: all 0.3s ease;
            padding: 1rem 2.5rem;
            font-size: 1rem;
            border-radius: 9999px;
            font-weight: 600;
        }

        .hero-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-100%) rotate(45deg);
            transition: transform 0.6s ease;
        }

        .hero-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .hero-btn:hover::before {
            transform: translateX(100%) rotate(45deg);
        }

        .hero-btn-primary {
            background: #2563eb;
            color: white;
        }

        .hero-btn-secondary {
            background: white;
            color: #2563eb;
        }

        .hero-btn-primary:hover {
            background: #1d4ed8;
        }

        .hero-btn-secondary:hover {
            background: #f8fafc;
        }

        .hero-btn:active {
            transform: translateY(-1px) scale(0.98);
        }

        /* Chatbot Button Styles */
        .chatbot-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0281d4, #0c9ced);
            box-shadow: 0 4px 15px rgba(2, 129, 212, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2147483647 !important;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            outline: none;
        }

        .chatbot-button:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 6px 20px rgba(2, 129, 212, 0.35);
        }

        .chatbot-button i {
            font-size: 24px;
            color: white;
            transition: transform 0.3s ease;
        }

        .chatbot-button:hover i {
            transform: rotate(10deg);
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

        .chatbot-container {
            position: fixed;
            bottom: 5.5rem;
            right: 2rem;
            width: 350px;
            max-height: 500px;
            border-radius: 16px;
            background: white;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
            z-index: 2147483646 !important;
            overflow: hidden;
            opacity: 0;
            transform: translateY(20px) scale(0.95);
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .chatbot-container.active {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: all;
        }

        .chatbot-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #0281d4, #0c9ced);
            color: white;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chatbot-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 1rem;
        }

        .chatbot-close {
            background: transparent;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .chatbot-close:hover {
            transform: rotate(90deg);
        }

        .chatbot-body {
            height: 340px;
            overflow-y: auto;
            padding: 20px;
        }

        .chatbot-footer {
            padding: 12px 15px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
        }

        .chatbot-input {
            flex: 1;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 10px 15px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s ease;
        }

        .chatbot-input:focus {
            border-color: #0c9ced;
            box-shadow: 0 0 0 2px rgba(12, 156, 237, 0.1);
        }

        .contact-card {
            transition: transform 0.25s cubic-bezier(.4, 2, .6, 1), box-shadow 0.25s;
            will-change: transform, box-shadow;
        }

        .contact-card:hover {
            transform: scale(1.01);
            box-shadow: 0 12px 32px 0 rgba(37, 99, 235, 0.18);
            z-index: 2;
        }

        .contact-card .contact-icon {
            transition: transform 0.25s cubic-bezier(.4, 2, .6, 1);
        }

        .contact-card:hover .contact-icon {
            transform: scale(1.18) rotate(-12deg);
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

        /* Mobile responsiveness for chatbot */
        @media (max-width: 640px) {
            .chatbot-container {
                width: calc(100% - 32px);
                right: 16px;
                left: 16px;
                bottom: 5rem;
            }

            .chatbot-button {
                bottom: 1.5rem;
                right: 1.5rem;
            }
        }

        /* Learn More Button Styles */
        .learn-more-btn {
            position: relative;
            overflow: hidden;
        }

        .learn-more-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-100%) rotate(45deg);
            transition: transform 0.6s ease;
        }

        .learn-more-btn:hover::before {
            transform: translateX(100%) rotate(45deg);
        }

        .learn-more-btn:active {
            transform: translateY(-1px) scale(0.98);
        }

        /* Enhance the service details animation */
        .service-details {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            max-height: 0;
            opacity: 0;
            overflow: hidden;
        }

        .service-details.show {
            max-height: 500px;
            opacity: 1;
        }

        /* Navigation Button Styles */
        .nav-btn {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .nav-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(120deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: translateX(-100%);
            transition: 0.6s;
            z-index: -1;
        }

        .nav-btn:hover::before {
            transform: translateX(100%);
        }

        .nav-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .nav-btn-secondary:hover {
            color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
            background: rgba(37, 99, 235, 0.05);
        }

        .welcome-pill {
            background-color: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1.25rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 1.5rem;
        }

        .hero-btn {
            padding: 1rem 2.5rem;
            font-size: 1rem;
            border-radius: 9999px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            border: 2px solid transparent;
            text-decoration: none;
        }

        .hero-btn-primary {
            background-color: white;
            color: #2563eb;
            border-color: white;
        }

        .hero-btn-primary:hover {
            background-color: rgba(255, 255, 255, 0.9);
            transform: translateY(-3px);
        }

        .hero-btn-secondary {
            background-color: transparent;
            color: white;
            border-color: white;
        }

        .hero-btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
        }

        /* New Feature Card Styles */
        .feature-card {
            background-color: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease-out;
            border: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .feature-card:hover,
        .feature-card.active {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            border-color: transparent;
        }

        .feature-card .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .feature-card .icon-wrapper {
            background-color: #0c9ced;
            /* Teal 500 */
            color: white;
            border-radius: 0.75rem;
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            transition: all 0.3s ease-out;
        }

        .feature-card:hover .icon-wrapper {
            transform: rotate(-15deg) scale(1.1);
        }

        .feature-card .tag {
            background-color: rgba(20, 184, 166, 0.1);
            /* Teal 500 with 10% opacity */
            color: #0c9ced;
            /* Teal 600 */
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .feature-card .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            /* Gray 800 */
            margin-bottom: 0.5rem;
        }

        .feature-card .card-description {
            font-size: 1rem;
            color: #4b5563;
            /* Gray 600 */
            line-height: 1.6;
            flex-grow: 1;
            /* Pushes the learn more link down */
        }

        .feature-card .learn-more {
            margin-top: 1.5rem;
            font-weight: 400;
            color: #489a59;
            /* Teal 500 */
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            opacity: 0;
        }

        .feature-card:hover .learn-more,
        .feature-card.active .learn-more {
            opacity: 1;
        }

        .feature-card .learn-more .arrow {
            transition: transform 0.2s ease;
        }

        .feature-card:hover .learn-more .arrow {
            transform: translateX(4px);
        }

        /* New Service Icons Section Animation */
        .service-icon-anim {
            background: #dbeafe;
            /* bg-blue-100 */
            color: #2563eb;
            /* text-blue-600 */
            transition: background 0.3s, color 0.3s, box-shadow 0.3s, transform 0.3s;
            box-shadow: 0 4px 16px 0 rgba(37, 99, 235, 0.10);
        }

        .service-icon-anim:hover {
            background: #2563eb;
            /* bg-blue-600 */
            color: #fff;
            /* text-white */
            box-shadow: 0 12px 32px 0 rgba(37, 99, 235, 0.25);
            transform: scale(1.08);
        }

        /* Marquee Animation for Service Icons */
        .marquee-container {
            overflow: hidden;
            width: 100%;
            position: relative;
            padding: 2.5rem 0;
            white-space: nowrap;
        }

        .marquee-track {
            display: inline-flex;
            align-items: center;
            gap: 2.5rem;
            animation: marquee-move 20s linear infinite;
        }

        .marquee-container:hover .marquee-track {
            animation-play-state: paused;
        }

        @keyframes marquee-move {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
        }

        /* Mobile-specific marquee styles */
        @media (max-width: 768px) {
            .marquee-track {
                animation: marquee-move 10s linear infinite;
            }
        }

        @media (max-width: 640px) {
            .marquee-track {
                animation: marquee-move 10s linear infinite;
            }
        }

        @media (max-width: 480px) {
            .marquee-track {
                animation: marquee-move 1.0s0s linear infinite;
                gap: 1.5rem;
                /* Reduced gap for smaller screens */
            }
        }

        .marquee-icon {
            background: #dbeafe;
            color: #2563eb;
            border-radius: 9999px;
            width: 5rem;
            height: 5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            box-shadow: 0 4px 16px 0 rgba(37, 99, 235, 0.10);
            transition: box-shadow 0.3s, background 0.3s, color 0.3s, transform 0.3s;
        }

        .marquee-icon:hover {
            background: #2563eb;
            color: #fff;
            box-shadow: 0 12px 32px 0 rgba(37, 99, 235, 0.25);
            transform: scale(1.08);
        }

        .marquee-label {
            margin-top: 0.5rem;
            font-size: 1rem;
            color: rgb(0, 0, 0);
            text-align: center;
            font-weight: 500;
        }

        .info-container {
            display: flex;
            align-items: center;
        }

        .info-icon {
            margin-left: 0.5rem;
            cursor: pointer;
        }

        .tooltip-content {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }

        .bg-white-blue-white {
            background: linear-gradient(180deg, #fff 0%, #2563eb 40%, #fff 100%);
        }

        /* Enhanced Scroll Animations */
        .scroll-animate {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .scroll-animate.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Slide from left */
        .slide-in-left {
            opacity: 0;
            transform: translateX(-50px);
            transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .slide-in-left.visible {
            opacity: 1;
            transform: translateX(0);
        }

        /* Slide from right */
        .slide-in-right {
            opacity: 0;
            transform: translateX(50px);
            transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .slide-in-right.visible {
            opacity: 1;
            transform: translateX(0);
        }

        /* Scale in animation */
        .scale-in {
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .scale-in.visible {
            opacity: 1;
            transform: scale(1);
        }

        /* Flip animation */
        .flip-in {
            opacity: 0;
            transform: rotateY(90deg);
            transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .flip-in.visible {
            opacity: 1;
            transform: rotateY(0deg);
        }

        /* Parallax effects */
        .parallax-slow {
            transform: translateY(0);
            transition: transform 0.1s ease-out;
        }

        /* Stagger delays for multiple elements */
        .stagger-1 {
            transition-delay: 0.1s;
        }

        .stagger-2 {
            transition-delay: 0.2s;
        }

        .stagger-3 {
            transition-delay: 0.3s;
        }

        .stagger-4 {
            transition-delay: 0.4s;
        }

        .stagger-5 {
            transition-delay: 0.5s;
        }

        .stagger-6 {
            transition-delay: 0.6s;
        }

        /* Progress bar animation */
        .progress-bar {
            width: 0%;
            height: 4px;
            background: linear-gradient(90deg, #2563eb, #0c9ced);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: width 0.3s ease;
        }

        /* Hero section enhanced animations */
        .hero-title {
            opacity: 0;
            transform: translateY(50px) scale(0.9);
            animation: heroTitleIn 1.2s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
            animation-delay: 0.5s;
        }

        .hero-subtitle {
            opacity: 0;
            transform: translateY(30px);
            animation: heroSubtitleIn 1s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
            animation-delay: 0.8s;
        }

        .hero-buttons {
            opacity: 0;
            transform: translateY(20px);
            animation: heroButtonsIn 1s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
            animation-delay: 1.1s;
        }

        @keyframes heroTitleIn {
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes heroSubtitleIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes heroButtonsIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Card hover enhancements */
        .enhanced-card {
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            transform-style: preserve-3d;
        }

        .enhanced-card:hover {
            transform: translateY(-10px) rotateX(2deg);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        /* Floating elements */
        .float-element {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        /* Text reveal animation */
        .text-reveal {
            overflow: hidden;
            position: relative;
        }

        .text-reveal::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #2563eb;
            transform: translateX(-100%);
            transition: transform 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .text-reveal.visible::after {
            transform: translateX(100%);
        }

        /* BPAMIS Users and Roles Modern Animations */
        .user-role-card {
            perspective: 1000px;
            transform-style: preserve-3d;
        }

        .user-role-inner {
            position: relative;
            width: 100%;
            height: 100%;
            text-align: center;
            transition: transform 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-style: preserve-3d;
        }

        .user-role-card:hover .user-role-inner {
            transform: rotateY(2deg) rotateX(2deg) translateZ(10px);
        }

        .role-gradient-bg {
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            border-radius: 1rem;
            background: linear-gradient(45deg, #ff6b6b, rgba(78, 205, 197, 0.47), #45b7d1, rgba(150, 206, 180, 0.51), rgba(254, 201, 87, 0.49));
            background-size: 400% 400%;
            animation: gradientShift 8s ease infinite;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .user-role-card:hover .role-gradient-bg {
            opacity: 1;
        }

        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .role-icon-container {
            position: relative;
            overflow: hidden;
        }

        .role-icon-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: iconRotate 3s linear infinite;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .user-role-card:hover .role-icon-container::before {
            opacity: 1;
        }

        @keyframes iconRotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .role-icon {
            position: relative;
            z-index: 2;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .user-role-card:hover .role-icon {
            transform: scale(1.2) rotate(10deg);
            filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.3));
        }

        .role-title {
            position: relative;
            overflow: hidden;
        }

        .role-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #4ecdc4, #45b7d1);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform: translateX(-50%);
        }

        .user-role-card:hover .role-title::after {
            width: 100%;
        }

        .role-feature {
            opacity: 100;
            transform: translateX(-20px);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .user-role-card:hover .role-feature {
            opacity: 1;
            transform: translateX(0);
        }

        .role-feature:nth-child(1) {
            transition-delay: 0.1s;
        }

        .role-feature:nth-child(2) {
            transition-delay: 0.2s;
        }

        .role-feature:nth-child(3) {
            transition-delay: 0.3s;
        }

        /* Floating particles effect */
        .role-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
            border-radius: 1rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .user-role-card:hover .role-particles {
            opacity: 1;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            animation: floatParticle 6s linear infinite;
        }

        @keyframes floatParticle {
            0% {
                transform: translateY(100%) translateX(0) rotate(0deg);
                opacity: 0;
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                transform: translateY(-100%) translateX(50px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Why Use BPAMIS Icons Hover Animation */
        .feature-benefit-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .feature-benefit-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s ease;
            z-index: 1;
        }

        .feature-benefit-card:hover::before {
            left: 100%;
        }

        .benefit-icon-wrapper {
            position: relative;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 2;
        }

        .feature-benefit-card:hover .benefit-icon-wrapper {
            transform: translateY(-10px) scale(1.1);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        .benefit-icon {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            z-index: 3;
        }

        .feature-benefit-card:hover .benefit-icon {
            animation: iconBounce 0.6s ease-in-out;
            color: #ffffff !important;
        }

        @keyframes iconBounce {

            0%,
            100% {
                transform: scale(1) rotate(0deg);
            }

            25% {
                transform: scale(1.2) rotate(-5deg);
            }

            50% {
                transform: scale(1.1) rotate(5deg);
            }

            75% {
                transform: scale(1.15) rotate(-3deg);
            }
        }

        .benefit-title {
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .feature-benefit-card:hover .benefit-title {
            color: #667eea;
            transform: translateY(-2px);
        }

        .benefit-list {
            position: relative;
            z-index: 2;
        }

        .benefit-item {
            transition: all 0.3s ease;
            opacity: 0.8;
        }

        .feature-benefit-card:hover .benefit-item {
            opacity: 1;
            transform: translateX(5px);
        }

        .benefit-item:nth-child(1) {
            transition-delay: 0.1s;
        }

        .benefit-item:nth-child(2) {
            transition-delay: 0.2s;
        }

        .benefit-item:nth-child(3) {
            transition-delay: 0.3s;
        }

        .benefit-check-icon {
            transition: all 0.3s ease;
        }

        .feature-benefit-card:hover .benefit-check-icon {
            color: #667eea;
            transform: scale(1.2);
        }

        /* Magnetic effect for cards */
        .magnetic-card {
            transition: transform 0.1s ease-out;
        }

        /* Ripple effect */
        .ripple-effect {
            position: relative;
            overflow: hidden;
        }

        .ripple-effect::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .ripple-effect:hover::after {
            width: 300px;
            height: 300px;
        }

        /* Enhanced Demographics Card */
        #demographicsWrapper {
            position: relative;
            overflow: hidden;
            border-radius: 1rem;
            margin: 2rem 0;
        }

        .demographics-card-enhanced {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            overflow: hidden;
            box-shadow:
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            transform: translateY(20px);
            opacity: 0.9;
            position: relative;
            margin: 30px auto;
            max-width: 1200px;
        }

        .demographics-card-enhanced::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #667eea, #764ba2, #f093fb, #f5576c, #4facfe, #00f2fe);
            background-size: 400% 400%;
            border-radius: 26px;
            z-index: -1;
            opacity: 0;
            animation: gradientBorder 8s ease infinite;
            transition: opacity 0.3s ease;
        }

        .demographics-card-enhanced:hover::before {
            opacity: 1;
        }

        .demographics-card-enhanced:hover {
            transform: translateY(0);
            opacity: 1;
            box-shadow:
                0 30px 60px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.3);
        }

        @keyframes gradientBorder {

            0%,
            100% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }
        }

        .demographic-item-enhanced {
            position: relative;
            padding: 2.5rem 2rem;
            text-align: center;
            transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .demographic-item-enhanced:hover {
            transform: translateY(-8px) scale(1.02);
            z-index: 10;
        }

        /* Enhanced Glow Background */
        .demographic-glow-bg {
            position: absolute;
            top: -20px;
            left: -20px;
            right: -20px;
            bottom: -20px;
            border-radius: 20px;
            opacity: 0;
            transition: all 0.4s ease;
            filter: blur(15px);
            z-index: 1;
        }

        .demographic-item-enhanced:hover .demographic-glow-bg {
            opacity: 0.1;
            transform: scale(1.1);
        }

        /* Enhanced Icon Styling */
        .demographic-icon {
            position: relative;
            z-index: 2;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .demographic-item-enhanced:hover .demographic-icon {
            transform: scale(1.2) rotate(5deg);
            filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.2));
        }

        /* Background Icon */
        .demographic-icon-bg {
            position: absolute;
            top: 50%;
            right: -30px;
            transform: translateY(-50%);
            font-size: 120px;
            opacity: 0.05;
            color: currentColor;
            transition: all 0.4s ease;
            z-index: 1;
        }

        .demographic-item-enhanced:hover .demographic-icon-bg {
            right: -20px;
            opacity: 0.1;
            transform: translateY(-50%) rotate(15deg);
        }

        /* Enhanced Number Animation */
        .demographic-number {
            position: relative;
            z-index: 2;
            font-weight: 800;
            letter-spacing: -0.02em;
            transition: all 0.3s ease;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .demographic-item-enhanced:hover .demographic-number {
            transform: scale(1.1);
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Enhanced Accent Line */
        .demographic-accent {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            transform: scaleX(0);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border-radius: 2px 2px 0 0;
        }

        .demographic-item-enhanced:hover .demographic-accent {
            transform: scaleX(1);
        }

        /* Floating Particles for Demographics */
        .demographic-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .demographic-item-enhanced:hover .demographic-particles {
            opacity: 1;
        }

        .demo-particle {
            position: absolute;
            width: 6px;
            height: 6px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 50%;
            animation: demoParticleFloat 4s linear infinite;
        }

        @keyframes demoParticleFloat {
            0% {
                transform: translateY(100%) translateX(0) rotate(0deg);
                opacity: 0;
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                transform: translateY(-100%) translateX(30px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Pulse Ring for Total Population */
        .demographic-pulse-ring {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100px;
            height: 100px;
            transform: translate(-50%, -50%);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            opacity: 0;
            animation: pulseRing 3s ease-out infinite;
        }

        @keyframes pulseRing {
            0% {
                transform: translate(-50%, -50%) scale(0.1);
                opacity: 1;
            }

            100% {
                transform: translate(-50%, -50%) scale(2);
                opacity: 0;
            }
        }

        .demographic-item-enhanced:hover .demographic-pulse-ring {
            animation-play-state: running;
            opacity: 1;
        }

        /* Stats Overlay */
        .demographic-stats-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
            transform: translateY(100%);
            transition: transform 0.4s ease;
        }

        .demographics-card-enhanced:hover .demographic-stats-overlay {
            transform: translateY(0);
        }

        /* Mobile Responsive Enhancements */
        @media (max-width: 768px) {

            /* Container width and padding control */
            .container {
                max-width: 100% !important;
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }

            /* Reduce grid gap spacing */
            .gap-8,
            .gap-12,
            .gap-16 {
                gap: 1rem !important;
            }

            /* Reduce section padding */
            .py-20 {
                padding-top: -2rem !important;
                padding-bottom: -2rem !important;
            }

            .demographic-item-enhanced {
                padding: 2rem 1.5rem;
                min-height: 160px;
            }

            .demographic-number {
                font-size: 2.5rem;
            }

            .demographic-icon i {
                font-size: 2rem;
            }

            .demographic-icon-bg {
                font-size: 80px;
                right: -20px;
            }

            .demographics-card-enhanced {
                margin: 30px 20px;
                border-radius: 20px;
            }

            /* Improved mobile spacing */
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            /* Adjust hero section for mobile */
            .hero-section {
                height: auto;
                padding-top: 6rem;
                padding-bottom: 2rem;
            }

            /* Reduce font sizes on mobile */
            h1.text-7xl {
                font-size: 1.5rem;
                margin-bottom: 0.15rem !important;
            }

            p.text-lg {
                font-size: 0.5rem;
                margin-bottom: 0.9rem !important;
            }

            /* Make buttons more touch-friendly */
            .hero-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.65rem;
                width: 60%;
                min-width: 120px;
                max-width: 180px;
                margin: 0 auto 0.5rem auto;
            }

           

            .hero-btn:last-child {
                margin-bottom: 0;
            }

            /* Fix the flex layout on mobile */
            .flex.justify-center.gap-4 {
                flex-direction: column;
                width: 100%;
                gap: 0.5rem;
                align-items: center;
            }

            /* Add more space between sections on mobile */
            section {
                padding-top: 3rem;
                padding-bottom: 3rem;
            }

            /* Adjust demographics cards for tablet */
            .premium-stats-panel {
                padding: 1.5rem !important;
            }

            .grid.grid-cols-2.lg\:grid-cols-4 {
                grid-template-columns: 1fr 1fr;
                gap: 1rem !important;
            }

            .premium-icon-container {
                width: 3rem !important;
                height: 3rem !important;
                margin-bottom: 0.5rem !important;
            }

            .premium-icon-container i {
                font-size: 1.25rem !important;
            }

            .premium-stat-value {
                font-size: 1.5rem !important;
                margin-bottom: 0.25rem !important;
            }

            .premium-stat-label {
                font-size: 0.75rem !important;
            }
        }

        @media (max-width: 640px) {

            /* Container width and padding control for small screens */
            .container {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }

            /* Adjust button container specifically for hero buttons */
            .flex.justify-center.gap-4 {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 0 !important;
                margin: 0 auto !important;
                width: 100% !important;
                max-width: 280px !important;
            }

            /* Button container adjustments */
            .hero-buttons,
            .flex.flex-col.sm\:flex-row.gap-4 {
                padding-left: 0 !important;
                padding-right: 0 !important;
                width: 100% !important;
                max-width: 280px !important;
                margin: 0 auto !important;
            }

            /* Reduce all paddings */
            .p-8 {
                padding: 1.25rem !important;
            }

            .px-6,
            .px-8 {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }

            /* Further reduce grid gap spacing */
            .gap-8,
            .gap-12,
            .gap-16 {
                gap: 0.75rem !important;
            }

            /* Width 100% for cards in mobile */
            .user-role-card {
                width: 90% !important;
            }

            .feature-card-enhanced {
                width: 100% !important;
            }

            /* Reduce text sizes further for smallest screens */
            h1.text-7xl {
                font-size: 1.5rem;
            }

            .text-lg {
                font-size: 0.5rem;
            }

            h2.text-6xl,
            h2.text-5xl {
                font-size: 1.75rem;
            }

            h3.text-3xl {
                font-size: 1.5rem;
            }

            p.text-lg {
                font-size: 0.5rem;
                margin-bottom: 0.9rem !important;
            }

            /* Ensure grid items stack properly on mobile */
            .grid.grid-cols-2,
            .grid.grid-cols-3,
            .grid.grid-cols-4 {
                grid-template-columns: 1fr;
            }

            /* Fix padding on cards for mobile */
            .p-8 {
                padding: 1.25rem;
            }

            /* Ensure images don't overflow on mobile */
            img {
                max-width: 100%;
                height: auto;
            }

            /* Fix premium stats panel for mobile */
            .premium-stats-panel {
                padding: 1.5rem;
            }

            /* Improve mobile spacing */
            .mb-8 {
                margin-bottom: 1.5rem;
            }

            .mt-10 {
                margin-top: 1.5rem;
            }

            /* Fix button layout on small screens */
            .flex.flex-col.sm\\:flex-row {
                gap: 0.75rem;
            }

            /* Improve demographics cards for small mobile */
            .premium-stats-panel {
                padding: 1rem !important;
                border-radius: 1.25rem !important;
            }

            .grid.grid-cols-2.lg\:grid-cols-4 {
                gap: 0.75rem !important;
            }

            .premium-stat-card .premium-stat-inner {
                padding: 1rem 0.5rem !important;
                border-radius: 1rem !important;
            }

            .premium-stat-value {
                font-size: 1.25rem !important;
            }
        }

        @media (max-width: 480px) {

            /* Extra small screen optimizations */
            .container {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }

            /* Reduce all paddings */
            .p-8,
            .p-6 {
                padding: 1rem !important;
            }

            /* Minimize gap spacing */
            .gap-8,
            .gap-12,
            .gap-16,
            .gap-6,
            .gap-4 {
                gap: 0.5rem !important;
            }

            /* Ensure no overflow in mobile */
            .overflow-x-auto {
                max-width: 100vw;
            }

            /* Make text smaller for very small screens */
            p {
                font-size: 0.8rem !important;
            }

            .demographic-item-enhanced {
                padding: 1.5rem 1rem;
                min-height: 140px;
            }

            .demographic-number {
                font-size: 2rem;
            }

            .demographic-icon i {
                font-size: 1.5rem;
            }

            /* Extra small buttons for very small screens */
            .hero-btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
                margin: 0 auto 0.6rem auto;
                width: 85%;
            }

            /* Further adjustments for very small screens */
            .p-6 {
                padding: 1rem;
            }

            .welcome-pill {
                font-size: 0.75rem;
                padding: 0.375rem 1rem;
            }

            /* Ensure horizontal scrolling doesn't happen */
            body {
                overflow-x: hidden;
                width: 100%;
            }

            /* Reduce icon sizes on very small screens */
            .w-16.h-16 {
                width: 3rem;
                height: 3rem;
            }

            /* Fix spacing on mobile screens */
            .px-8 {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .py-16 {
                padding-top: 2rem;
                padding-bottom: 2rem;
            }
        }

        /* Loading Animation for Numbers */
        @keyframes numberCountUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .demographic-number {
            animation: numberCountUp 0.8s ease-out forwards;
        }

        /* Target all demographics grid sections */
        @media (max-width: 768px) {
            .demographics-card .grid {
                gap: 1rem !important;
            }

            .demographics-card {
                width: 100% !important;
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
        }

        @media (max-width: 480px) {
            .demographics-card {
                margin-top: 1.5rem !important;
                padding-left: 0rem !important;
                padding-right: 0rem !important;
            }

            .demographics-card .mb-12 {
                margin-bottom: 1rem !important;
            }
        }

        /* Enhanced Glassmorphism Effect */
        .demographics-card-enhanced {
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.95),
                    rgba(255, 255, 255, 0.85));
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
        }

        /* Hover State Improvements */
        .demographic-item-enhanced:nth-child(1):hover {
            box-shadow: 0 20px 40px rgba(37, 99, 235, 0.2);
        }

        .demographic-item-enhanced:nth-child(2):hover {
            box-shadow: 0 20px 40px rgba(34, 197, 94, 0.2);
        }

        .demographic-item-enhanced:nth-child(3):hover {
            box-shadow: 0 20px 40px rgba(59, 130, 246, 0.2);
        }

        .demographic-item-enhanced:nth-child(4):hover {
            box-shadow: 0 20px 40px rgba(147, 51, 234, 0.2);
        }

        /* Performance Optimizations */
        .demographic-item-enhanced,
        .demographic-glow-bg,
        .demographic-icon {
            will-change: transform;
        }

        /* Accessibility Improvements */
        @media (prefers-reduced-motion: reduce) {

            .demographic-item-enhanced,
            .demographic-icon,
            .demographic-number,
            .demo-particle,
            .demographic-pulse-ring {
                animation: none !important;
                transition: none !important;
            }
        }

        /* Focus States for Accessibility */
        .demographic-item-enhanced:focus-within {
            outline: 2px solid #60a5fa;
            outline-offset: 2px;
        }

        /* Enhanced VMGO Section Styles */
        .vmgo-card {
            perspective: 1000px;
            transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .vmgo-card-inner {
            position: relative;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2);
            transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-style: preserve-3d;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.3);
            min-height: 280px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .vmgo-card:hover .vmgo-card-inner {
            transform: translateY(-10px) rotateX(5deg) rotateY(2deg);
            box-shadow:
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.4);
        }

        /* Enhanced Glow Background */
        .vmgo-glow-bg {
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            border-radius: 25px;
            opacity: 0;
            transition: all 0.5s ease;
            filter: blur(20px);
            z-index: 1;
        }

        .vision-card:hover .vmgo-glow-bg {
            background: radial-gradient(circle, rgba(20, 184, 166, 0.15) 0%, transparent 70%);
            opacity: 1;
        }

        .mission-card:hover .vmgo-glow-bg {
            background: radial-gradient(circle, rgba(251, 191, 36, 0.15) 0%, transparent 70%);
            opacity: 1;
        }

        .goals-card:hover .vmgo-glow-bg {
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
            opacity: 1;
        }

        .objectives-card:hover .vmgo-glow-bg {
            background: radial-gradient(circle, rgba(147, 51, 234, 0.15) 0%, transparent 70%);
            opacity: 1;
        }

        /* Corner Accent */
        .vmgo-corner-accent {
            position: absolute;
            top: 0;
            left: 0;
            width: 80px;
            height: 80px;
            border-radius: 0 0 40px 0;
            z-index: 2;
            transition: all 0.4s ease;
        }

        .vmgo-corner-teal {
            background: linear-gradient(135deg, #14b8a6, #0d9488);
        }

        .vmgo-corner-yellow {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
        }

        .vmgo-corner-blue {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .vmgo-corner-purple {
            background: linear-gradient(135deg, #9333ea, #7c3aed);
        }

        .vmgo-card:hover .vmgo-corner-accent {
            width: 100px;
            height: 100px;
            border-radius: 0 0 50px 0;
        }

        /* Icon Wrapper Enhanced */
        .vmgo-icon-wrapper {
            position: relative;
            z-index: 10;
            margin: 1rem auto 1.5rem;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .vmgo-icon-teal {
            background: linear-gradient(135deg, #e6fffa, #ccfbf1);
        }

        .vmgo-icon-yellow {
            background: linear-gradient(135deg, #fefce8, #fef3c7);
        }

        .vmgo-icon-blue {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
        }

        .vmgo-icon-purple {
            background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
        }

        .vmgo-card:hover .vmgo-icon-wrapper {
            transform: scale(1.1) rotate(10deg);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .vmgo-icon {
            transition: all 0.4s ease;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }

        .vmgo-card:hover .vmgo-icon {
            transform: scale(1.1);
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }

        /* Enhanced Titles */
        .vmgo-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 10;
            transition: all 0.4s ease;
            letter-spacing: 0.05em;
        }

        .vmgo-title-teal {
            color: #0d9488;
        }

        .vmgo-title-yellow {
            color: #d97706;
        }

        .vmgo-title-blue {
            color: #2563eb;
        }

        .vmgo-title-purple {
            color: #7c3aed;
        }

        .vmgo-card:hover .vmgo-title {
            transform: translateY(-2px);
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Enhanced Content */
        .vmgo-content {
            color: #374151;
            line-height: 1.7;
            position: relative;
            z-index: 10;
            font-size: 1rem;
            flex-grow: 1;
            transition: all 0.3s ease;
        }

        .vmgo-card:hover .vmgo-content {
            color: #1f2937;
            transform: translateY(-1px);
        }

        /* Enhanced Objectives List */
        .vmgo-objectives-list {
            position: relative;
            z-index: 10;
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }



        .vmgo-objective-item:nth-child(1) {
            transition-delay: 0.1s;
        }

        .vmgo-objective-item:nth-child(2) {
            transition-delay: 0.2s;
        }

        .vmgo-objective-item:nth-child(3) {
            transition-delay: 0.3s;
        }

        .vmgo-objective-item:nth-child(4) {
            transition-delay: 0.4s;
        }

        .vmgo-objective-item:nth-child(5) {
            transition-delay: 0.5s;
        }

        .vmgo-bullet {
            width: 8px;
            height: 8px;
            background: linear-gradient(135deg, #9333ea, #7c3aed);
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 6px;
            transition: all 0.3s ease;
        }

        .vmgo-objective-item:hover .vmgo-bullet {
            transform: scale(1.3);
            box-shadow: 0 0 10px rgba(147, 51, 234, 0.5);
        }

        /* Progress Bar */
        .vmgo-progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            width: 0%;
            border-radius: 2px 2px 0 0;
            transition: width 0.8s ease;
            z-index: 10;
        }

        .vmgo-progress-teal {
            background: linear-gradient(90deg, #14b8a6, #0d9488);
        }

        .vmgo-progress-yellow {
            background: linear-gradient(90deg, #fbbf24, #f59e0b);
        }

        .vmgo-progress-blue {
            background: linear-gradient(90deg, #3b82f6, #2563eb);
        }

        .vmgo-progress-purple {
            background: linear-gradient(90deg, #9333ea, #7c3aed);
        }

        .vmgo-card:hover .vmgo-progress-bar {
            width: 100%;
        }

        /* Floating Particles */
        .vmgo-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 3;
        }

        .vmgo-card:hover .vmgo-particles {
            opacity: 1;
        }

        .vmgo-particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            animation: vmgoParticleFloat 4s linear infinite;
        }

        @keyframes vmgoParticleFloat {
            0% {
                transform: translateY(100%) translateX(0) rotate(0deg);
                opacity: 0;
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                transform: translateY(-100%) translateX(25px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Ripple Effect */
        .vmgo-card-inner::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
            transform: translate(-50%, -50%);
            transition: all 0.6s ease;
            z-index: 2;
            pointer-events: none;
        }

        .vmgo-card:hover .vmgo-card-inner::after {
            width: 300px;
            height: 300px;
        }

        /* Stagger Animation Delays */
        .vmgo-card.stagger-1 {
            transition-delay: 0.1s;
        }

        .vmgo-card.stagger-2 {
            transition-delay: 0.2s;
        }

        .vmgo-card.stagger-4 {
            transition-delay: 0.4s;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .vmgo-card-inner {
                padding: 2rem 1.5rem;
                min-height: 250px;
            }

            .vmgo-title {
                font-size: 1.5rem;
            }

            .vmgo-content {
                font-size: 0.9rem;
                line-height: 1.6;
            }

            .vmgo-icon-wrapper {
                width: 70px;
                height: 70px;
            }

            .vmgo-corner-accent {
                width: 60px;
                height: 60px;
                border-radius: 0 0 30px 0;
            }

            .vmgo-card:hover .vmgo-corner-accent {
                width: 75px;
                height: 75px;
                border-radius: 0 0 37px 0;
            }
        }

        @media (max-width: 480px) {
            .vmgo-card-inner {
                padding: 1.5rem 1rem;
                min-height: 220px;
            }

            .vmgo-title {
                font-size: 1.25rem;
                margin-bottom: 0.75rem;
            }

            .vmgo-content {
                font-size: 0.85rem;
            }
        }

        /* Enhanced Security Section Animations - Matching Why Use BPAMIS */
        .security-benefit-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .security-benefit-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s ease;
            z-index: 1;
        }

        .security-benefit-card:hover::before {
            left: 100%;
        }

        .security-icon-wrapper {
            position: relative;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 2;
        }

        .security-benefit-card:hover .security-icon-wrapper {
            transform: translateY(-10px) scale(1.1);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        .security-icon {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            z-index: 3;
        }

        .security-benefit-card:hover .security-icon {
            animation: securityIconBounce 0.6s ease-in-out;
            color: #ffffff !important;
        }

        @keyframes securityIconBounce {

            0%,
            100% {
                transform: scale(1) rotate(0deg);
            }

            25% {
                transform: scale(1.2) rotate(-5deg);
            }

            50% {
                transform: scale(1.1) rotate(5deg);
            }

            75% {
                transform: scale(1.15) rotate(-3deg);
            }
        }

        .security-title {
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .security-benefit-card:hover .security-title {
            color: #667eea;
            transform: translateY(-2px);
        }

        .security-list {
            position: relative;
            z-index: 2;
        }

        .security-item {
            transition: all 0.3s ease;
            opacity: 0.8;
        }

        .feature-benefit-card:hover .benefit-item {
            opacity: 1;
            transform: translateX(5px);
        }

        .security-item:nth-child(1) {
            transition-delay: 0.1s;
        }

        .security-item:nth-child(2) {
            transition-delay: 0.2s;
        }

        .security-item:nth-child(3) {
            transition-delay: 0.3s;
        }

        .security-item:nth-child(4) {
            transition-delay: 0.4s;
        }

        .security-item:nth-child(5) {
            transition-delay: 0.5s;
        }

        .security-check-icon {
            transition: all 0.3s ease;
        }

        .security-benefit-card:hover .security-check-icon {
            color: #667eea;
            transform: scale(1.2);
        }

        .security-mini-icon {
            transition: all 0.3s ease;
        }

        .security-benefit-card:hover .security-mini-icon {
            background: linear-gradient(135deg, #667eea, #764ba2) !important;
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        /* Enhanced Magnetic Card Effect for Security Section */
        .security-benefit-card.magnetic-card {
            transform-style: preserve-3d;
            will-change: transform;
        }

        /* Ripple Effect for Security Cards */
        .security-benefit-card::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(102, 126, 234, 0.1);
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
            z-index: 1;
            pointer-events: none;
        }

        .security-benefit-card:hover::after {
            width: 300px;
            height: 300px;
        }

        /* Enhanced Hover States for Security Section */
        .security-benefit-card:hover {
            transform: translateY(-8px) rotateX(2deg);
            box-shadow:
                0 20px 40px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(102, 126, 234, 0.1);
            background: linear-gradient(135deg, #f8faff 0%, #f0f7ff 100%) !important;
        }

        /* Info Icon Enhanced Animations */
        .info-icon {
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .info-icon:hover {
            transform: scale(1.2) rotate(15deg);
            box-shadow: 0 4px 12px rgba(147, 51, 234, 0.3);
        }

        /* Tooltip Enhanced Animations */
        .tooltip-content {
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform: translateY(10px);
            opacity: 0;
        }

        .tooltip-content:not(.hidden) {
            transform: translateY(0);
            opacity: 1;
        }

        /* Enhanced Scroll Animations for Security Section */
        .security-benefit-card.scale-in {
            opacity: 0;
            transform: scale(0.8) translateY(30px);
            transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .security-benefit-card.scale-in.visible {
            opacity: 1;
            transform: scale(1) translateY(0);
        }

        /* Stagger Animation Delays for Security Cards */
        .security-benefit-card.stagger-1 {
            transition-delay: 0.1s;
        }

        .security-benefit-card.stagger-2 {
            transition-delay: 0.2s;
        }

        .security-benefit-card.stagger-3 {
            transition-delay: 0.3s;
        }

        /* Performance Optimizations */
        .security-benefit-card,
        .security-icon-wrapper,
        .security-icon {
            will-change: transform;
        }

        /* Accessibility */
        @media (prefers-reduced-motion: reduce) {

            .security-benefit-card,
            .security-icon-wrapper,
            .security-icon,
            .security-item,
            .info-icon {
                animation: none !important;
                transition: none !important;
            }
        }

        .security-benefit-card:focus-within {
            outline: 2px solid #60a5fa;
            outline-offset: 4px;
        }

        /* About Barangay Panducot Section */
        #about-panducot {
            background-attachment: fixed;
            background-size: cover;
            position: relative;
        }

        #about-panducot::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg,
                    rgba(239, 246, 255, 0.8) 0%,
                    rgba(255, 255, 255, 0.9) 50%,
                    rgba(238, 242, 255, 0.8) 100%);
            pointer-events: none;
        }

        #about-panducot>.container {
            position: relative;
            z-index: 1;
        }

        /* Community Feature Cards Animation */
        .community-feature-card {
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .community-feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            background: rgba(255, 255, 255, 0.98) !important;
        }

        /* Enhanced Community Stats Cards */
        .community-stat-card {
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        .community-stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        /* Enhanced Text Containers */
        .text-content-box {
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .text-content-box:hover {
            background: rgba(255, 255, 255, 0.3) !important;
            border-color: rgba(255, 255, 255, 0.4);
        }

        /* Enhanced Key Values Cards */
        .key-value-card {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .key-value-card:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.95) !important;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        /* Enhanced Button Styles */
        .btn-primary-enhanced {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.3);
        }

        .btn-primary-enhanced:hover {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            box-shadow: 0 12px 35px rgba(255, 255, 255, 0.4);
        }

        .btn-secondary-enhanced {
            background: rgba(37, 99, 235, 0.9);
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary-enhanced:hover {
            background: rgba(29, 78, 216, 0.95);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 12px 35px rgba(37, 99, 235, 0.4);
        }

        /* Enhanced BPAMIS text styling */
        .bpamis-highlight {
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            font-weight: 700;
        }

        /* Enhanced location badge */
        .location-badge {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-top: 0;
        }

        /* Location badge responsive margin adjustments */
        @media (max-width: 768px) {
            .location-badge {
                margin-top: 1.5rem;
            }
        }

        @media (max-width: 640px) {
            .location-badge {
                margin-top: 2rem;
            }
        }

        @media (max-width: 480px) {
            .location-badge {
                margin-top: 3rem;
            }
        }

        /* Enhanced progress bar */
        .enhanced-divider {
            background: linear-gradient(90deg,
                    rgba(255, 255, 255, 0.8) 0%,
                    rgba(59, 130, 246, 0.6) 50%,
                    rgba(255, 255, 255, 0.4) 100%);
            box-shadow: 0 2px 8px rgba(255, 255, 255, 0.3);
        }

        /* Floating Animation Enhancement */
        @keyframes floatEnhanced {

            0%,
            100% {
                transform: translateY(0px) scale(1);
                filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
            }

            50% {
                transform: translateY(-8px) scale(1.02);
                filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.15));
            }
        }

        .floating-badge {
            animation: floatEnhanced 6s ease-in-out infinite;
        }

        /* Enhanced Stats Overlay */
        .stats-overlay-enhanced {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow:
                0 8px 25px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .text-content-box {
                padding: 1rem !important;
                margin: 0.5rem 0;
            }

            .key-value-card {
                padding: 0.75rem !important;
            }

            .community-stat-card {
                padding: 1rem !important;
            }

            .bpamis-highlight {
                font-size: 1.75rem;
                line-height: 1.2;
            }
        }

        @media (max-width: 480px) {
            .text-content-box {
                padding: 0.75rem !important;
            }

            .location-badge {
                padding: 0.5rem 1rem !important;
                font-size: 0.75rem;
            }
        }

        /* Performance Optimizations */
        .community-feature-card,
        .community-stat-card,
        .text-content-box,
        .key-value-card {
            will-change: transform, background, box-shadow;
        }

        /* Accessibility Improvements */
        @media (prefers-reduced-motion: reduce) {

            .community-feature-card,
            .community-stat-card,
            .floating-badge {
                animation: none !important;
                transition: none !important;
            }
        }

        /* Focus States */
        .community-feature-card:focus-within,
        .key-value-card:focus-within {
            outline: 2px solid rgba(59, 130, 246, 0.6);
            outline-offset: 2px;
        }

        /* Enhanced Quick Links Styles */
        .quick-link-card {
            transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-style: preserve-3d;
        }

        .quick-link-card:hover {
            transform: translateY(-12px) rotateX(5deg) rotateY(2deg);
        }

        .quick-link-card a {
            position: relative;
            overflow: hidden;
            will-change: transform;
        }

        .quick-link-card:hover a {
            transform: translateZ(10px);
        }

        /* Enhanced Card Content Animations */
        .quick-link-card .group:hover .fas {
            animation: quickLinkIconPulse 1s ease-in-out infinite;
        }

        @keyframes quickLinkIconPulse {

            0%,
            100% {
                transform: scale(1.1);
            }

            50% {
                transform: scale(1.2);
            }
        }

        /* Floating Background Elements */
        @keyframes quickLinkFloat {

            0%,
            100% {
                transform: translateY(0px) scale(1);
            }

            50% {
                transform: translateY(-10px) scale(1.05);
            }
        }

        .quick-link-card .absolute.w-16.h-16 {
            animation: quickLinkFloat 6s ease-in-out infinite;
        }

        /* User Guide Modal Styles */
        #userGuideModal .transform {
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        #userGuideModal {
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        /* Custom styles for the User Guide side panel */
        .guide-custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: rgba(59, 130, 246, 0.5) rgba(243, 244, 246, 1);
        }

        .guide-custom-scrollbar::-webkit-scrollbar {
            width: 8px;
        }

        .guide-custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(243, 244, 246, 1);
            border-radius: 4px;
        }

        .guide-custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(59, 130, 246, 0.5);
            border-radius: 4px;
        }

        /* Smooth slide in/out transition */
        #guideModalContent {
            transition: transform 0.3s ease-out;
        }

        /* Focus styles for accessibility */
        .guide-focus-visible-ring:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
        }

        /* Animation for guide answers */
        .guide-answer {
            transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
        }

        /* Remove background image from categories */
        #getting-started,
        #filing-complaints {
            background-image: none !important;
            background-color: #ffffff !important;
        }

        /* Ensure all guide items have white background */
        .guide-item {
            background-color: #ffffff;
        }

        /* Enhanced Quick Actions Cards */
        .quick-action-card {
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .quick-action-card:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        /* Mobile Responsiveness for Quick Links */
        @media (max-width: 768px) {
            .quick-link-card {
                margin: 0 1rem;
            }

            .quick-link-card a {
                padding: 1.5rem !important;
            }

            .quick-link-card .w-20.h-20 {
                width: 4rem;
                height: 4rem;
            }

            .quick-link-card .text-3xl {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .quick-link-card a {
                padding: 1rem !important;
            }

            .quick-link-card .text-xl {
                font-size: 1rem;
            }

            .quick-link-card .text-sm {
                font-size: 0.8rem;
            }
        }

        /* Performance Optimizations */
        .quick-link-card,
        .quick-link-card a,
        .quick-action-card {
            will-change: transform;
        }

        /* Accessibility */
        @media (prefers-reduced-motion: reduce) {

            .quick-link-card,
            .quick-link-card .absolute.w-16.h-16 {
                animation: none !important;
                transition: none !important;
            }
        }

        /* Focus States */
        .quick-link-card a:focus-within {
            outline: 2px solid #60a5fa;
            outline-offset: 2px;
        }

        #childrenCount::after {
            content: '%';
            margin-left: 2px;
        }

        #totalPopulation::after {
            content: '';
            margin-left: 2px;
        }

        #seniorCount::after {
            content: '+';
            margin-left: 2px;
        }

        #adultCount::after {
            content: '+';
            margin-left: 2px;
        }

        #pwdSeniorPopulation::after {
            content: '+';
            margin-left: 2px;
        }

        #economicGroupPopulation::after {
            content: '+';
            margin-left: 2px;
        }

        /* Ensure Women + Children shows a % suffix */
        #womenChildrenPopulation::after {
            content: '%';
            margin-left: 2px;
        }

        #bpamisAdoption::after {
            content: '%';
            margin-left: 2px;
        }
    </style>
</head>

<body>
    <!-- Add this at the very top of the body -->
    <div class="loader-wrapper">
        <div class="loader">
            <div class="loader-gradient"></div>
            <div class="loader-inner"></div>
            <img src="assets/images/logo.png" alt="BPAMIS Logo" class="loader-logo">
        </div>
    </div>

    <?php include_once('includes/bpamis_nav.php'); ?>
    <?php include '../chatbot/bpamis_case_assistant.php' ?>

    <!-- Mobile: normalize horizontal padding for main containers for consistent appearance -->
    <style>
        @media (max-width: 640px) {
            /* Make the main container use the full viewport width so content appears larger on phones.
               Inner panels keep a small horizontal padding to maintain gutters. */
            .container {
                padding-left: 0 !important;
                padding-right: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
                box-sizing: border-box;
            }

            /* Keep inner panels slightly padded so content doesn't touch the edges */
            .premium-stats-panel,
            .marquee-section,
            .premium-image-container,
            .demographics-card,
            .user-role-card > .relative,
            .feature-benefit-card,
            .security-benefit-card {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
                box-sizing: border-box;
            }

            /* Slightly reduce CTA horizontal padding on narrow screens so buttons align nicely */
            .cta-buttons a {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }

            /* Lower large section heading sizes on small screens to improve readability and fit.
               Targets common large heading utility classes used across the page. Adjust if you
               want a different size. */
            h2.text-6xl,
            h2.text-7xl,
            h2.text-5xl,
            h2.text-4xl,
            .text-6xl > h2,
            .text-5xl > h2 {
                font-size: 1.875rem !important; /* ~30px */
                line-height: 1.1 !important;
            }

            /* Make each user role card wider on mobile so the card appears more prominent.
               Expand visually to the container edges but panels keep inner padding (above). */
            .user-role-card > .relative {
                width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                box-sizing: border-box !important;
                padding-left: 1rem !important; /* ensure inner gutter */
                padding-right: 1rem !important;
            }

            /* Make headers inside the standard page header wrapper smaller (match 'Our Services') */
            .text-center.max-w-4xl h2 {
                font-size: 1.5rem !important; /* 24px */
                line-height: 1.15 !important;
            }

            /* Reduce hero H1 on very small screens so the heading wraps cleanly and fits */
            .hero-section .text-4xl {
                font-size: 1.5rem !important; /* ~24px */
                line-height: 1.12 !important;
            }

            /* Add horizontal padding (gutters) to the hero paragraph on mobile so it doesn't touch the edges */
            .hero-section .text-lg {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
                box-sizing: border-box !important;
            }

            /* Team and beneficiaries paragraphs: add horizontal gutters on small screens */
            .team-description,
            .beneficiaries-description {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
                box-sizing: border-box !important;
            }

            /* Featured Services: add an outside gutter on phones */
            .services-container {
                margin-left: 1rem !important;
                margin-right: 1rem !important;
                max-width: fit-content  !important;
            }

            /* Featured Services: add an outside gutter on phones */
            .brgy-container {
                margin-left: 1rem !important;
                margin-right: 1rem !important;
                max-width: fit-content  !important;
            }

            /* Featured Services: add an outside gutter on phones */
            .dgtl-container {
                margin-left: 1rem !important;
                margin-right: 1rem !important;
                max-width: 100% !important;

                font-size: 0.75rem !important; /* slightly smaller text */
                h3.text-xl {
                    font-size: 1rem !important; /* slightly smaller headings */
                }
            }

            /* Featured Services: add an outside gutter on phones */
            .sf-container {
                margin-left: 1rem !important;
                margin-right: 1rem !important;
                max-width: 100% !important;

                font-size: 0.75rem !important; /* slightly smaller text */
                h3.text-xl {
                    font-size: 1rem !important; /* slightly smaller headings */
                }
            }

            /* Featured Services: add an outside gutter on phones */
            .htln-container {
                margin-left: 1rem !important;
                margin-right: 1rem !important;
                max-width: 100% !important;

                font-size: 0.75rem !important; /* slightly smaller text */
                h3.text-2xl {
                    font-size: 1rem !important; /* slightly smaller headings */
                }
            }
        }
    </style>

    <!-- Hero Section -->
    <section class="hero-section flex items-center justify-center text-white text-center">
        <div class="container mx-auto px-4 flex flex-col items-center">
            <div class="welcome-pill" style="margin-top:50px; margin-bottom: 10px;">Welcome to BPAMIS</div>
            <h1 class="text-4xl font-bold max-w-4xl" style="margin-bottom: 20px;">Barangay Panducot
                Adjudication Management Information System</h1>
            <p class="text-lg mb-8 max-w-2xl font-light">Enhancing Local Justice and Empowering Communities Digitally</p>
            <div class="flex justify-center gap-4">
                <a href="register.php" class="hero-btn hero-btn-primary">Get Started</a>
                <a href="about.php" class="hero-btn hero-btn-secondary">Learn More</a>
            </div>

            <!-- Premium Hero Demographics Stats Section -->
            <div class="mt-10 max-w-6xl mx-auto demographics-card scroll-animate slide-in-up">
                <div
                    class="premium-stats-panel bg-white/95 backdrop-blur-md rounded-3xl shadow-xl p-8 lg:p-12 border border-white/30 relative overflow-hidden">
                    <!-- Animated background gradients -->
                    <div class="premium-panel-gradient absolute inset-0 opacity-10"></div>

                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-9 relative z-10">
                        <!-- Families Card - Enhanced Premium Design -->
                        <div class="premium-stat-card group">
                            <div
                                class="premium-stat-inner text-center p-6 rounded-2xl bg-gradient-to-br from-blue-50/80 to-blue-100/80 hover:from-blue-100 hover:to-blue-200 transition-all duration-500 border border-blue-200/50 relative overflow-hidden">
                                <!-- Animated gradient border -->
                                <div
                                    class="premium-stat-border absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                </div>

                                <!-- Animated icon container -->
                                <div
                                    class="premium-icon-container w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-md group-hover:shadow-blue-500/40 transition-all duration-500 relative overflow-hidden">
                                    <!-- Animated light effect -->
                                    <div class="premium-icon-light"></div>
                                    <i class="fas fa-home text-white text-2xl relative z-10"></i>
                                </div>

                                <!-- Premium stats text with animations -->
                                <div class="premium-stat-value text-3xl font-bold text-blue-600 mb-2 group-hover:scale-110 transition-transform duration-300"
                                    id="adultCount">0</div>
                                <div
                                    class="premium-stat-label text-sm text-gray-700 font-medium group-hover:font-semibold transition-all duration-300">
                                    Families</div>

                                <!-- Subtle particles -->
                                <div class="premium-stat-particles"></div>
                            </div>
                        </div>

                        <!-- Senior Citizens Card - Enhanced Premium Design -->
                        <div class="premium-stat-card group">
                            <div
                                class="premium-stat-inner text-center p-6 rounded-2xl bg-gradient-to-br from-green-50/80 to-green-100/80 hover:from-green-100 hover:to-green-200 transition-all duration-500 border border-green-200/50 relative overflow-hidden">
                                <!-- Animated gradient border -->
                                <div
                                    class="premium-stat-border absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                </div>

                                <!-- Animated icon container -->
                                <div
                                    class="premium-icon-container w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-md group-hover:shadow-green-500/40 transition-all duration-500 relative overflow-hidden">
                                    <!-- Animated light effect -->
                                    <div class="premium-icon-light"></div>
                                    <i class="fas fa-walking text-white text-2xl relative z-10"></i>
                                </div>

                                <!-- Premium stats text with animations -->
                                <div class="premium-stat-value text-3xl font-bold text-green-600 mb-2 group-hover:scale-110 transition-transform duration-300"
                                    id="seniorCount">0</div>
                                <div
                                    class="premium-stat-label text-sm text-gray-700 font-medium group-hover:font-semibold transition-all duration-300">
                                    Senior Citizens</div>

                                <!-- Subtle particles -->
                                <div class="premium-stat-particles"></div>
                            </div>
                        </div>

                        <!-- Children & Adults Card - Enhanced Premium Design -->
                        <div class="premium-stat-card group">
                            <div
                                class="premium-stat-inner text-center p-6 rounded-2xl bg-gradient-to-br from-purple-50/80 to-purple-100/80 hover:from-purple-100 hover:to-purple-200 transition-all duration-500 border border-purple-200/50 relative overflow-hidden">
                                <!-- Animated gradient border -->
                                <div
                                    class="premium-stat-border absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                </div>

                                <!-- Animated icon container -->
                                <div
                                    class="premium-icon-container w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-md group-hover:shadow-purple-500/40 transition-all duration-500 relative overflow-hidden">
                                    <!-- Animated light effect -->
                                    <div class="premium-icon-light"></div>
                                    <i class="fas fa-child text-white text-2xl relative z-10"></i>
                                </div>

                                <!-- Premium stats text with animations -->
                                <div class="premium-stat-value text-3xl font-bold text-purple-600 mb-2 group-hover:scale-110 transition-transform duration-300"
                                    id="childrenCount">0</div>
                                <div
                                    class="premium-stat-label text-sm text-gray-700 font-medium group-hover:font-semibold transition-all duration-300">
                                    Children & Adults</div>

                                <!-- Subtle particles -->
                                <div class="premium-stat-particles"></div>
                            </div>
                        </div>

                        <!-- Population Card - Enhanced Premium Design -->
                        <div class="premium-stat-card group">
                            <div
                                class="premium-stat-inner text-center p-6 rounded-2xl bg-gradient-to-br from-orange-50/80 to-orange-100/80 hover:from-orange-100 hover:to-orange-200 transition-all duration-500 border border-orange-200/50 relative overflow-hidden">
                                <!-- Animated gradient border -->
                                <div
                                    class="premium-stat-border absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                </div>

                                <!-- Animated icon container -->
                                <div
                                    class="premium-icon-container w-16 h-16 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-md group-hover:shadow-orange-500/40 transition-all duration-500 relative overflow-hidden">
                                    <!-- Animated light effect -->
                                    <div class="premium-icon-light"></div>
                                    <i class="fas fa-chart-line text-white text-2xl relative z-10"></i>
                                </div>

                                <!-- Premium stats text with animations -->
                                <div class="premium-stat-value text-3xl font-bold text-orange-600 mb-2 group-hover:scale-110 transition-transform duration-300"
                                    id="totalPopulation">0</div>
                                <div
                                    class="premium-stat-label text-sm text-gray-700 font-medium group-hover:font-semibold transition-all duration-300">
                                    Population</div>

                                <!-- Subtle particles -->
                                <div class="premium-stat-particles"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Barangay Panducot Section - Enhanced Color Scheme -->
    <section class="py-20"
        style="background: linear-gradient(180deg, #fff 10%,rgba(37, 100, 235, 0.92) 30%, #fff 100%);">
        <div class="container mx-auto px-4">
            <!-- Main About Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center max-w-7xl mx-auto">
                <!-- Left Side: Visual -->
                <div class="relative scroll-animate slide-in-left -mt-24">
                    <div class="relative group">
                        <!-- Premium Main Image Container -->
                        <div
                            class="relative overflow-hidden rounded-3xl shadow-2xl transform group-hover:scale-105 transition-all duration-500 premium-image-container">
                            <!-- Animated subtle border -->
                            <div class="premium-image-border"></div>

                            <!-- Main image with enhanced treatment -->
                            <img src="assets/images/brgyhall.png" alt="Barangay Panducot Community"
                                class="w-full h-96 object-cover premium-image">

                            <!-- Enhanced Overlay Gradient with subtle animation -->
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-blue-900/70 via-transparent to-transparent premium-overlay">
                            </div>

                            <!-- Floating BPAMIS Badge with Premium Effect -->
                            <div class="absolute top-6 right-6 rounded-full p-3 shadow-lg premium-badge-container">
                                <!-- Animated gradient border -->
                                <div class="premium-badge-border"></div>

                                <!-- Glass background -->
                                <div class="absolute inset-0 bg-white/80 backdrop-blur-md rounded-full"></div>

                                <!-- Logo with hover glow effect -->

                            </div>

                            <!-- Enhanced Community Stats Overlay with Premium Gradient Effect -->
                            <div
                                class="absolute bottom-6 left-6 rounded-2xl p-4 shadow-xl overflow-hidden premium-stats-container">
                                <!-- Animated gradient background -->
                                <div class="premium-gradient-bg"></div>

                                <!-- Glass morphism overlay -->
                                <div class="absolute inset-0 bg-white/70 backdrop-blur-lg"></div>

                                <!-- Floating particles effect -->
                                <div class="premium-particles"></div>

                                <!-- Content with relative positioning to appear above effects -->
                                <div class="flex items-center space-x-6 relative z-10">
                                    <div class="relative z-10 flex items-center justify-right">
                                        <img src="assets/images/logo.png" alt="BPAMIS Logo"
                                            class="w-8 h-8 premium-logo">
                                    </div>
                                    <div class="text-center transform transition-all duration-500 hover:scale-110">
                                        <div
                                            class="text-2xl font-bold bg-gradient-to-r from-blue-800 to-blue-600 bg-clip-text text-transparent">
                                            3,318</div>
                                        <div class="text-xs text-gray-800 font-medium">Residents</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-green-800">100%</div>
                                        <div class="text-xs text-gray-800 font-medium">Local Dispute Coverage</div>
                                    </div>



                                </div>
                            </div>
                        </div>

                        <!-- Enhanced Decorative Elements -->
                        <div class="absolute -top-4 -left-4 w-24 h-24 bg-white/20 rounded-full blur-xl"></div>
                        <div class="absolute -bottom-4 -right-4 w-32 h-32 bg-blue-300/30 rounded-full blur-xl"></div>
                    </div>

                    <!-- Community Features Cards - Enhanced for new background -->
                    <div class="grid grid-cols-2 gap-4 mt-8">
                        <div
                            class="flex items-start space-x-3 bg-white/90 backdrop-blur-sm p-4 rounded-lg border border-white/30 shadow-sm ">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-users text-green-600"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-white">United Community</div>
                                    <div class="text-sm text-gray-700">Strong bonds</div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex items-start space-x-3 bg-white/90 backdrop-blur-sm p-4 rounded-lg border border-white/30 shadow-sm ">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-shield-alt text-blue-600"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-white">Safe Environment</div>
                                    <div class="text-sm text-gray-700">Peace & order</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Text Content - Enhanced for gradient background -->
                <div class="space-y-8 brgy-container scroll-animate slide-in-right">
                    <!-- Section Heading -->
                    <div class="space-y-4">
                        <div
                            class="location-badge inline-flex items-center px-4 py-2 bg-white/90 text-blue-800 rounded-full text-sm font-medium backdrop-blur-sm border border-white/20 shadow-md">
                            <i class="fas fa-map-marker-alt mr-2"></i>
                            Panducot, Calumpit, Bulacan
                        </div>

                        <h2
                            class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold leading-tight text-gray-900 mb-2">
                            Barangay Panducot and the Role of
                            <span
                                class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold bg-gradient-to-r from-blue-600 to-blue-800 bg-clip-text text-transparent">BPAMIS</span>
                        </h2>

                        <div class="w-20 h-1 bg-gradient-to-r from-white to-blue-200 rounded-full shadow-sm"></div>
                    </div>

                    <!-- Main Description - Enhanced text contrast -->
                    <div class="space-y-6">
                        <?php if (!empty($aboutRoleDescription)) : ?>
                        <p
                            class="text-lg text-white leading-relaxed font-medium bg-white/20 backdrop-blur-sm p-4 rounded-lg border border-white/30 bg-white/95 backdrop-blur-md rounded-3xl shadow-xl p-5 lg:p-7 border border-white/30">
                            <?= nl2br(htmlspecialchars($aboutRoleDescription)) ?>
                        </p>
                        <?php endif; ?>
                        
                    </div>

                    <!-- Key Values - Enhanced for gradient background -->
                    <style>
                        @media (max-width: 640px) {
                            /* Hide the entire Key Values block on small screens per request */
                            .key-values-grid { display: none !important; }
                        }
                    </style>
                    <div class="grid grid-cols-2 gap-6 key-values-grid">
                        <div
                            class="flex items-start space-x-3 bg-white/90 backdrop-blur-sm p-4 rounded-lg border border-white/30 shadow-sm ">
                            <div
                                class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mt-1">
                                <i class="fas fa-heart text-green-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-white">Community Care</h4>
                                <p class="text-sm text-gray-700">Everyone matters in our barangay family</p>
                            </div>
                        </div>

                        <div
                            class="flex items-start space-x-3 bg-white/90 backdrop-blur-sm p-4 rounded-lg border border-white/30 shadow-sm">
                            <div
                                class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mt-1">
                                <i class="fas fa-balance-scale text-blue-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-white">Fair Justice</h4>
                                <p class="text-sm text-gray-700">Transparent and equitable resolution</p>
                            </div>
                        </div>

                        <div
                            class="flex items-start space-x-3 bg-white/90 backdrop-blur-sm p-4 rounded-lg border border-white/30 shadow-sm">
                            <div
                                class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mt-1">
                                <i class="fas fa-rocket text-purple-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-white">Innovation</h4>
                                <p class="text-sm text-gray-700">Leading with digital transformation</p>
                            </div>
                        </div>

                        <div
                            class="flex items-start space-x-3 bg-white/90 backdrop-blur-sm p-4 rounded-lg border border-white/30 shadow-sm">
                            <div
                                class="flex-shrink-0 w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mt-1">
                                <i class="fas fa-handshake text-yellow-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-white">Unity</h4>
                                <p class="text-sm text-gray-700">Stronger together as one community</p>
                            </div>
                        </div>
                    </div>

                    
                </div>
            </div>

            <!-- Additional Community Stats Section - Enhanced for gradient background -->
            <!-- Premium Community Stats Section -->
            <div class="mt-20 max-w-6xl mx-auto demographics-card scroll-animate slide-in-up">
                <div
                    class="premium-stats-panel bg-white/95 backdrop-blur-md rounded-3xl shadow-xl p-8 lg:p-12 border border-white/30 relative overflow-hidden">
                    <!-- Animated background gradients -->
                    <div class="premium-panel-gradient absolute inset-0 opacity-10"></div>

                    <div class="text-center mb-12 scroll-animate relative z-10">
                        <h3
                            class="text-3xl font-bold bg-gradient-to-r from-gray-900 via-blue-800 to-gray-900 bg-clip-text text-transparent mb-4">
                            Our Community by the Numbers</h3>
                        <p class="text-gray-700 max-w-2xl mx-auto">Barangay Panducot thrives with a diverse, growing
                            population committed to peace, progress, and community development.</p>
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 relative z-10">
                        <!-- Households Card - Enhanced Premium Design -->
                        <div class="premium-stat-card group">
                            <div
                                class="premium-stat-inner text-center p-6 rounded-2xl bg-gradient-to-br from-blue-50/80 to-blue-100/80 hover:from-blue-100 hover:to-blue-200 transition-all duration-500 border border-blue-200/50 relative overflow-hidden">
                                <!-- Animated gradient border -->
                                <div
                                    class="premium-stat-border absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                </div>

                                <!-- Animated icon container -->
                                <div
                                    class="premium-icon-container w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-md group-hover:shadow-blue-500/40 transition-all duration-500 relative overflow-hidden">
                                    <!-- Animated light effect -->
                                    <div class="premium-icon-light"></div>
                                    <i class="fas fa-wheelchair text-white text-2xl relative z-10"></i>
                                </div>

                                <!-- Premium stats text with animations -->
                                <div class="premium-stat-value text-3xl font-bold text-blue-600 mb-2 group-hover:scale-110 transition-transform duration-300"
                                    id="pwdSeniorPopulation">0</div>
                                <div
                                    class="premium-stat-label text-sm text-gray-700 font-medium group-hover:font-semibold transition-all duration-300">
                                    PWDs & Senior</div>

                                <!-- Subtle particles -->
                                <div class="premium-stat-particles"></div>
                            </div>
                        </div>

                        <!-- Literacy Rate Card - Enhanced Premium Design -->
                        <div class="premium-stat-card group">
                            <div
                                class="premium-stat-inner text-center p-6 rounded-2xl bg-gradient-to-br from-green-50/80 to-green-100/80 hover:from-green-100 hover:to-green-200 transition-all duration-500 border border-green-200/50 relative overflow-hidden">
                                <!-- Animated gradient border -->
                                <div
                                    class="premium-stat-border absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                </div>

                                <!-- Animated icon container -->
                                <div
                                    class="premium-icon-container w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-md group-hover:shadow-green-500/40 transition-all duration-500 relative overflow-hidden">
                                    <!-- Animated light effect -->
                                    <div class="premium-icon-light"></div>
                                    <i class="fas fa-briefcase text-white text-2xl relative z-10"></i>
                                </div>

                                <!-- Premium stats text with animations -->
                                <div class="premium-stat-value text-3xl font-bold text-green-600 mb-2 group-hover:scale-110 transition-transform duration-300"
                                    id="economicGroupPopulation">0</div>
                                <div
                                    class="premium-stat-label text-sm text-gray-700 font-medium group-hover:font-semibold transition-all duration-300">
                                    Economic Group</div>

                                <!-- Subtle particles -->
                                <div class="premium-stat-particles"></div>
                            </div>
                        </div>

                        <!-- Peace Index Card - Enhanced Premium Design -->
                        <div class="premium-stat-card group">
                            <div
                                class="premium-stat-inner text-center p-6 rounded-2xl bg-gradient-to-br from-purple-50/80 to-purple-100/80 hover:from-purple-100 hover:to-purple-200 transition-all duration-500 border border-purple-200/50 relative overflow-hidden">
                                <!-- Animated gradient border -->
                                <div
                                    class="premium-stat-border absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                </div>

                                <!-- Animated icon container -->
                                <div
                                    class="premium-icon-container w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-md group-hover:shadow-purple-500/40 transition-all duration-500 relative overflow-hidden">
                                    <!-- Animated light effect -->
                                    <div class="premium-icon-light"></div>
                                    <i class="fas fa-child text-white text-2xl relative z-10"></i>
                                </div>

                                <!-- Premium stats text with animations -->
                                <div class="premium-stat-value text-3xl font-bold text-purple-600 mb-2 group-hover:scale-110 transition-transform duration-300"
                                    id="womenChildrenPopulation">0</div>
                                <div
                                    class="premium-stat-label text-sm text-gray-700 font-medium group-hover:font-semibold transition-all duration-300">
                                    Women & Children</div>

                                <!-- Subtle particles -->
                                <div class="premium-stat-particles"></div>
                            </div>
                        </div>

                        <!-- BPAMIS Adoption Card - Enhanced Premium Design -->
                        <div class="premium-stat-card group">
                            <div
                                class="premium-stat-inner text-center p-6 rounded-2xl bg-gradient-to-br from-orange-50/80 to-orange-100/80 hover:from-orange-100 hover:to-orange-200 transition-all duration-500 border border-orange-200/50 relative overflow-hidden">
                                <!-- Animated gradient border -->
                                <div
                                    class="premium-stat-border absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                </div>

                                <!-- Animated icon container -->
                                <div
                                    class="premium-icon-container w-16 h-16 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-md group-hover:shadow-orange-500/40 transition-all duration-500 relative overflow-hidden">
                                    <!-- Animated light effect -->
                                    <div class="premium-icon-light"></div>
                                    <i class="fas fa-chart-line text-white text-2xl relative z-10"></i>
                                </div>

                                <!-- Premium stats text with animations -->
                                <div class="premium-stat-value text-3xl font-bold text-orange-600 mb-2 group-hover:scale-110 transition-transform duration-300"
                                    id="bpamisAdoption">0</div>
                                <div
                                    class="premium-stat-label text-sm text-gray-700 font-medium group-hover:font-semibold transition-all duration-300">
                                    BPAMIS Adoption</div>

                                <!-- Subtle particles -->
                                <div class="premium-stat-particles"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- Featured Services - Enhanced Professional Design -->
    <section class="py-20 relative overflow-hidden"
        style="background: linear-gradient(180deg, #fff 0%,rgb(37, 100, 235) 60%, #fff 100%);">
        <!-- Background Elements -->
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50/50 via-white to-indigo-50/30"></div>
        <div class="absolute top-10 right-10 w-72 h-72 bg-blue-100/20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-10 left-10 w-96 h-96 bg-indigo-100/20 rounded-full blur-3xl"></div>

    <div class="container services-container mx-auto px-4 relative z-10">
            <!-- Enhanced Header Section -->
            <div class="text-center max-w-4xl mx-auto mb-20 scroll-animate">
                <div
                    class="inline-flex items-center px-6 py-3 bg-white/80 backdrop-blur-sm text-blue-700 rounded-full text-sm font-semibold mb-6 border border-blue-200/50 shadow-sm">
                    <i class="fas fa-star mr-2 text-blue-500"></i>
                    Our Digital Solutions
                </div>
                <h2
                    class="text-6xl lg:text-7xl font-bold mb-8 bg-gradient-to-r from-gray-900 via-blue-800 to-gray-900 bg-clip-text text-transparent leading-tight">
                    Our Services
                </h2>
                <p class="text-xl text-gray-600 leading-relaxed max-w-1xl mx-auto">
                    Innovative digital solutions tailored for efficient barangay adjudication, designed to streamline
                    community governance and enhance citizen services.
                </p>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full mx-auto mt-8"></div>
            </div>

            <!-- Enhanced Service Cards Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-20">
                <!-- Card 1: Barangay Cases - Enhanced -->
                <div class="group feature-card-enhanced fade-in-element stagger-fade-delay-1 relative">
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-indigo-500/5 rounded-2xl transform rotate-1 group-hover:rotate-0 transition-transform duration-500">
                    </div>
                    <div
                        class="relative bg-white/95 backdrop-blur-sm rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-500 border border-white/50 group-hover:border-blue-200/50">
                        <!-- Floating Elements -->
                        <div
                            class="absolute top-4 right-4 w-20 h-20 bg-gradient-to-br from-blue-400/10 to-indigo-400/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-700">
                        </div>

                        <!-- Enhanced Header -->
                        <div class="flex items-start justify-between mb-8">
                            <div class="relative">
                                <div
                                    class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg group-hover:shadow-blue-500/25 transition-all duration-500 group-hover:scale-110 group-hover:rotate-3">
                                    <i
                                        class="fas fa-gavel text-2xl text-white transform group-hover:scale-110 transition-transform duration-500"></i>
                                </div>
                                <div
                                    class="absolute -top-2 -right-2 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                    <i class="fas fa-check text-white text-xs"></i>
                                </div>
                            </div>
                            <div
                                class="bg-blue-50 text-blue-700 px-4 py-2 rounded-full text-sm font-semibold border border-blue-200/50">
                                Dispute Resolution
                            </div>
                        </div>

                        <!-- Enhanced Content -->
                        <div class="space-y-6">
                            <div>
                                <h3
                                    class="text-2xl font-bold text-gray-900 mb-3 group-hover:text-blue-700 transition-colors duration-300">
                                    Barangay Cases
                                </h3>
                                <p class="text-black leading-relaxed">
                                    Efficient management and resolution of barangay disputes, from filing to automated
                                    hearing schedules and comprehensive digital documentation.
                                </p>
                            </div>

                            <!-- Feature Highlights -->
                            <div class="space-y-3">
                                <div class="flex items-center text-sm text-black">
                                    <div class="w-2 h-2 bg-blue-400 rounded-full mr-3"></div>
                                    <span>Automated case tracking</span>
                                </div>
                                <div class="flex items-center text-sm text-black">
                                    <div class="w-2 h-2 bg-blue-400 rounded-full mr-3"></div>
                                    <span>Digital hearing schedules</span>
                                </div>
                                <div class="flex items-center text-sm text-black">
                                    <div class="w-2 h-2 bg-blue-400 rounded-full mr-3"></div>
                                    <span>Real-time status updates</span>
                                </div>
                            </div>

                            <!-- Enhanced CTA -->
                            <div class="pt-4">
                                <a href="services.php"
                                    class="group/btn inline-flex items-center justify-between w-full p-4 bg-gradient-to-r from-purple-50 to-violet-50 hover:from-purple-500 hover:to-violet-500 rounded-xl border border-purple-200/50 hover:border-transparent text-purple-700 hover:text-white transition-all duration-300 font-semibold">
                                    <span>Explore Service</span>
                                    <i
                                        class="fas fa-arrow-right transform group-hover/btn:translate-x-1 transition-transform duration-300"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Document Processing - Enhanced -->
                <div class="group feature-card-enhanced fade-in-element stagger-fade-delay-2 relative">
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-teal-500/5 rounded-2xl transform -rotate-1 group-hover:rotate-0 transition-transform duration-500">
                    </div>
                    <div
                        class="relative bg-white/95 backdrop-blur-sm rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-500 border border-white/50 group-hover:border-emerald-200/50">
                        <!-- Floating Elements -->
                        <div
                            class="absolute top-4 right-4 w-20 h-20 bg-gradient-to-br from-emerald-400/10 to-teal-400/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-700">
                        </div>

                        <!-- Enhanced Header -->
                        <div class="flex items-start justify-between mb-8">
                            <div class="relative">
                                <div
                                    class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl flex items-center justify-center shadow-lg group-hover:shadow-emerald-500/25 transition-all duration-500 group-hover:scale-110 group-hover:rotate-3">
                                    <i
                                        class="fas fa-file-alt text-2xl text-white transform group-hover:scale-110 transition-transform duration-500"></i>
                                </div>
                                <div
                                    class="absolute -top-2 -right-2 w-6 h-6 bg-orange-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                    <i class="fas fa-cog text-white text-xs"></i>
                                </div>
                            </div>
                            <div
                                class="bg-white text-emerald-700 px-4 py-2 rounded-full text-sm font-semibold border border-emerald-200/50">
                                Tech-Supported
                            </div>
                        </div>

                        <!-- Enhanced Content -->
                        <div class="space-y-6">
                            <div>
                                <h3
                                    class="text-2xl font-bold text-gray-900 mb-3 group-hover:text-emerald-700 transition-colors duration-300">
                                    Document Processing
                                </h3>
                                <p class="text-black leading-relaxed">
                                    Easy and secure online access to barangay certificates and other official documents
                                    with real-time status updates and instant notifications.
                                </p>
                            </div>

                            <!-- Feature Highlights -->
                            <div class="space-y-3">
                                <div class="flex items-center text-sm text-black">
                                    <div class="w-2 h-2 bg-green-200 rounded-full mr-3"></div>
                                    <span>Instant document requests</span>
                                </div>
                                <div class="flex items-center text-sm text-black">
                                    <div class="w-2 h-2 bg-green-200 rounded-full mr-3"></div>
                                    <span>Digital signature support</span>
                                </div>
                                <div class="flex items-center text-sm text-black">
                                    <div class="w-2 h-2 bg-green-200 rounded-full mr-3"></div>
                                    <span>Secure document delivery</span>
                                </div>
                            </div>

                            <!-- Enhanced CTA -->
                            <div class="pt-4">
                                <a href="services.php"
                                    class="group/btn inline-flex items-center justify-between w-full p-4 bg-gradient-to-r from-purple-50 to-violet-50 hover:from-purple-500 hover:to-violet-500 rounded-xl border border-purple-200/50 hover:border-transparent text-purple-700 hover:text-white transition-all duration-300 font-semibold">
                                    <span>Explore Service</span>
                                    <i
                                        class="fas fa-arrow-right transform group-hover/btn:translate-x-1 transition-transform duration-300"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Resident Services - Enhanced -->
                <div class="group feature-card-enhanced fade-in-element stagger-fade-delay-3 relative">
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-purple-500/5 to-violet-500/5 rounded-2xl transform rotate-1 group-hover:rotate-0 transition-transform duration-500">
                    </div>
                    <div
                        class="relative bg-white/95 backdrop-blur-sm rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-500 border border-white/50 group-hover:border-purple-200/50">
                        <!-- Floating Elements -->
                        <div
                            class="absolute top-4 right-4 w-20 h-20 bg-gradient-to-br from-purple-400/10 to-violet-400/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-700">
                        </div>

                        <!-- Enhanced Header -->
                        <div class="flex items-start justify-between mb-8">
                            <div class="relative">
                                <div
                                    class="w-16 h-16 bg-gradient-to-br from-purple-500 to-violet-600 rounded-xl flex items-center justify-center shadow-lg group-hover:shadow-purple-500/25 transition-all duration-500 group-hover:scale-110 group-hover:rotate-3">
                                    <i
                                        class="fas fa-users text-2xl text-white transform group-hover:scale-110 transition-transform duration-500"></i>
                                </div>
                                <div
                                    class="absolute -top-2 -right-2 w-6 h-6 bg-pink-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                    <i class="fas fa-heart text-white text-xs"></i>
                                </div>
                            </div>
                            <div
                                class="bg-purple-50 text-purple-700 px-4 py-2 rounded-full text-sm font-semibold border border-purple-200/50">
                                Community-Centered
                            </div>
                        </div>

                        <!-- Enhanced Content -->
                        <div class="space-y-6">
                            <div>
                                <h3
                                    class="text-2xl font-bold text-gray-900 mb-3 group-hover:text-purple-700 transition-colors duration-300">
                                    Resident Services
                                </h3>
                                <p class="text-black leading-relaxed">
                                    Manage resident profiles, receive community announcements, and schedule
                                    appointments, all in one comprehensive digital platform.
                                </p>
                            </div>

                            <!-- Feature Highlights -->
                            <div class="space-y-3">
                                <div class="flex items-center text-sm text-black">
                                    <div class="w-2 h-2 bg-purple-400 rounded-full mr-3"></div>
                                    <span>Personal dashboard</span>
                                </div>
                                <div class="flex items-center text-sm text-black">
                                    <div class="w-2 h-2 bg-purple-400 rounded-full mr-3"></div>
                                    <span>Community announcements</span>
                                </div>
                                <div class="flex items-center text-sm text-black">
                                    <div class="w-2 h-2 bg-purple-400 rounded-full mr-3"></div>
                                    <span>Online appointment booking</span>
                                </div>
                            </div>

                            <!-- Enhanced CTA -->
                            <div class="pt-4">
                                <a href="services.php"
                                    class="group/btn inline-flex items-center justify-between w-full p-4 bg-gradient-to-r from-purple-50 to-violet-50 hover:from-purple-500 hover:to-violet-500 rounded-xl border border-purple-200/50 hover:border-transparent text-purple-700 hover:text-white transition-all duration-300 font-semibold">
                                    <span>Explore Service</span>
                                    <i
                                        class="fas fa-arrow-right transform group-hover/btn:translate-x-1 transition-transform duration-300"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Service Icons Marquee Section -->
        <div class="mt-16">
            <!-- Enhanced Header -->
            <div class="text-center max-w-3xl mx-auto mb-8 md:mb-12 px-4">
                <h2
                    class="text-4xl md:text-5xl lg:text-3xl font-bold mb-4 md:mb-8 bg-gradient-to-r from-gray-900 via-blue-800 to-gray-900 bg-clip-text text-transparent leading-tight">
                    Complete Digital Ecosystem</h2>

                <p class="text-base md:text-lg text-black leading-relaxed">
                    <i>BPAMIS offers a comprehensive suite of digital services designed to make barangay processes more
                        efficient, transparent, and accessible for everyone in our community.</i>
                </p>
            </div>
            <div style="border-radius: 1.5rem; margin-left: 1rem; margin-right: 1rem;"
                class="shadow-lg relative marquee-section group">
                <div class="marquee-container">
                    <div class="marquee-track">
                        <!-- First set of icons -->
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-gavel"></i></div>
                            <span class="marquee-label">Case Filing</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-file-alt"></i></div>
                            <span class="marquee-label">Documents</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-calendar-alt"></i></div>
                            <span class="marquee-label">Scheduling</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-bullhorn"></i></div>
                            <span class="marquee-label">Notices</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-users"></i></div>
                            <span class="marquee-label">Profiles</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-shield-alt"></i></div>
                            <span class="marquee-label">Security</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-search-location"></i></div>
                            <span class="marquee-label">Track Case</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-chart-bar"></i></div>
                            <span class="marquee-label">Report</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-robot"></i></div>
                            <span class="marquee-label">Assistant</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-user-shield"></i></div>
                            <span class="marquee-label">Role-Based</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-calendar"></i></div>
                            <span class="marquee-label">Calendar</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-user-secret"></i></div>
                            <span class="marquee-label">Guest Access</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-paperclip"></i></div>
                            <span class="marquee-label">File Attachment</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-file-signature"></i></div>
                            <span class="marquee-label">KP Form</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-chart-pie"></i></div>
                            <span class="marquee-label">Statistics</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-user-tie"></i></div>
                            <span class="marquee-label">Captain Tools</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-user-cog"></i></div>
                            <span class="marquee-label">Secretary Tools</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-balance-scale"></i></div>
                            <span class="marquee-label">Lupon Tools</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-sync-alt"></i></div>
                            <span class="marquee-label">Status</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-file-upload"></i></div>
                            <span class="marquee-label">Complaint Filing</span>
                        </div>

                        <!-- Duplicate set for seamless looping -->
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-gavel"></i></div>
                            <span class="marquee-label">Case Filing</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-file-alt"></i></div>
                            <span class="marquee-label">Documents</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-calendar-alt"></i></div>
                            <span class="marquee-label">Scheduling</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-bullhorn"></i></div>
                            <span class="marquee-label">Notices</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-users"></i></div>
                            <span class="marquee-label">Profiles</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-shield-alt"></i></div>
                            <span class="marquee-label">Security</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="marquee-icon"><i class="fas fa-search-location"></i></div>
                            <span class="marquee-label">Track Case</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </section>

    <!-- Team Section -->
    <section class="py-20" style="background: linear-gradient(180deg, #fff 10%,rgba(37, 100, 235, 0.65) 30%, #fff 100%);">
        <div class="container mx-auto px-4">


            <div class="text-center max-w-4xl mx-auto mb-20 fade-in-element" style="margin-top: 10px;">
                <div
                    class="inline-flex items-center px-6 py-3 bg-white/80 backdrop-blur-sm text-blue-700 rounded-full text-sm font-semibold mb-6 border border-blue-200/50 shadow-sm stagger-fade-delay-1">
                    <i class="fas fa-star mr-2 text-blue-500"></i>
                    Barangay Officials
                </div>
                <h2
                    class="text-6xl lg:text-5xl font-bold mb-8 bg-gradient-to-r from-gray-900 via-blue-800 to-gray-900 bg-clip-text text-transparent leading-tight stagger-fade-delay-2">
                    Barangay Officials and Their Roles
                </h2>
                <p class="text-xl text-gray-600 leading-relaxed max-w-1xl mx-auto stagger-fade-delay-3 team-description">
                    This section outlines the different barangay officials roles within BPAMIS and their corresponding
                    responsibilities and access levels.
                </p>
                <div
                    class="w-24 h-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full mx-auto mt-8 stagger-fade-delay-4">
                </div>
            </div>


            <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                <!-- Barangay Secretary Card -->
                <div class="user-role-card fade-in-element stagger-fade-delay-1">
                    <div
                        class="relative bg-white rounded-xl shadow-lg p-8 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl user-role-inner ripple-effect">
                        <div class="role-gradient-bg"></div>
                        <div class="role-particles">
                            <div class="particle" style="left: 20%; animation-delay: 0s;"></div>
                            <div class="particle" style="left: 40%; animation-delay: 1s;"></div>
                            <div class="particle" style="left: 60%; animation-delay: 2s;"></div>
                            <div class="particle" style="left: 80%; animation-delay: 3s;"></div>
                        </div>
                        <div class="relative z-10">
                            <div
                                class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6 role-icon-container">
                                <i class="fas fa-user-tie text-3xl text-blue-600 role-icon"></i>
                            </div>
                            <h4 class="text-xl font-bold text-center text-gray-800 mb-4 role-title">Barangay Secretary
                            </h4>
                            <div class="space-y-3 text-gray-600">
                                <div class="flex items-start gap-3 role-feature" style="margin-left: 0.5rem;">
                                    <i class="fas fa-check-circle text-blue-600 mt-2"></i>
                                    <span>Manages case documents and resident records</span>
                                </div>
                                <div class="flex items-start gap-3 role-feature" style="margin-left: 0.5rem;">
                                    <i class="fas fa-check-circle text-blue-600 mt-1"></i>
                                    <span>Schedules and coordinates hearings</span>
                                </div>
                                <div class="flex items-start gap-3 role-feature" style="margin-left: 0.5rem;">
                                    <i class="fas fa-check-circle text-blue-600 mt-1"></i>
                                    <span>Primary administrator of BPAMIS platform</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Barangay Captain Card -->
                <div class="user-role-card fade-in-element stagger-fade-delay-2">
                    <div
                        class="relative bg-white rounded-xl shadow-lg p-8 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl user-role-inner ripple-effect">
                        <div class="role-gradient-bg"></div>
                        <div class="role-particles">
                            <div class="particle" style="left: 25%; animation-delay: 0.5s;"></div>
                            <div class="particle" style="left: 45%; animation-delay: 1.5s;"></div>
                            <div class="particle" style="left: 65%; animation-delay: 2.5s;"></div>
                            <div class="particle" style="left: 85%; animation-delay: 3.5s;"></div>
                        </div>
                        <div class="relative z-10">
                            <div
                                class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6 role-icon-container">
                                <i class="fas fa-user-shield text-3xl text-green-600 role-icon"></i>
                            </div>
                            <h4 class="text-xl font-bold text-center text-gray-800 mb-4 role-title">Barangay Captain
                            </h4>
                            <div class="space-y-3 text-gray-600">
                                <div class="flex items-start gap-3 role-feature">
                                    <i class="fas fa-check-circle text-green-600 mt-1" style="margin-left: 0.5rem;"></i>
                                    <span>Oversees case resolutions and assignments</span>
                                </div>
                                <div class="flex items-start gap-3 role-feature">
                                    <i class="fas fa-check-circle text-green-600 mt-1" style="margin-left: 0.5rem;"></i>
                                    <span>Act as the mediator and arbitrator</span>
                                </div>
                                <div class="flex items-start gap-3 role-feature">
                                    <i class="fas fa-check-circle text-green-600 mt-1"style="margin-left: 0.5rem;"></i>
                                    <span>Monitors barangay activities through BPAMIS</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lupon Tagapamayapa Card -->
                <div class="user-role-card fade-in-element stagger-fade-delay-3">
                    <div
                        class="relative bg-white rounded-xl shadow-lg p-8 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl user-role-inner ripple-effect">
                        <div class="role-gradient-bg"></div>
                        <div class="role-particles">
                            <div class="particle" style="left: 30%; animation-delay: 1s;"></div>
                            <div class="particle" style="left: 50%; animation-delay: 2s;"></div>
                            <div class="particle" style="left: 70%; animation-delay: 3s;"></div>
                            <div class="particle" style="left: 90%; animation-delay: 4s;"></div>
                        </div>
                        <div class="relative z-10">
                            <div
                                class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6 role-icon-container">
                                <i class="fas fa-balance-scale text-3xl text-purple-600 role-icon"></i>
                            </div>
                            <h4 class="text-xl font-bold text-center text-gray-800 mb-4 role-title">Lupon Tagapamayapa
                            </h4>
                            <div class="space-y-3 text-gray-600">
                                <div class="flex items-start gap-3 role-feature">
                                    <i class="fas fa-check-circle text-purple-600 mt-1" style="margin-left: 0.5rem;"></i>
                                    <span>Handles dispute conciliation processes</span>
                                </div>
                                <div class="flex items-start gap-3 role-feature">
                                    <i class="fas fa-check-circle text-purple-600 mt-1" style="margin-left: 0.5rem;"></i>
                                    <span>Accesses and manages case files</span>
                                </div>
                                <div class="flex items-start gap-3 role-feature">
                                    <i class="fas fa-check-circle text-purple-600 mt-1" style="margin-left: 0.5rem;"></i>
                                    <span>Updates resolution status through BPAMIS</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Who Can Access Our Services Section -->

        <div class="container mx-auto px-4" style="margin-top: 4rem;">
            <div class="text-center max-w-4xl mx-auto mb-20  fade-in-element" style="margin-top: 4rem;">
                <div
                    class="inline-flex items-center px-6 py-3 bg-white/80 backdrop-blur-sm text-blue-700 rounded-full text-sm font-semibold mb-6 border border-blue-200/50 shadow-sm stagger-fade-delay-1">
                    <i class="fas fa-star mr-2 text-blue-500"></i>
                    Beneficiaries
                </div>
                <h2
                    class="text-3xl lg:text-5xl font-bold mb-8 bg-gradient-to-r from-gray-900 via-blue-800 to-gray-900 bg-clip-text text-transparent leading-tight stagger-fade-delay-2">
                    Who Can Access Our Services
                </h2>
                <p class="text-xl text-gray-200 leading-relaxed max-w-1xl mx-auto stagger-fade-delay-3 beneficiaries-description">
                    This section outlines the different user roles within BPAMIS and their 
                    corresponding accessibility to the services provided.
                </p>
                <div
                    class="w-24 h-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full mx-auto mt-8 stagger-fade-delay-4">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <!-- Residents Card -->
                <div class="user-card group slide-in-left stagger-fade-delay-2">
                    <div
                        class="relative bg-white rounded-xl shadow-lg p-8 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl">
                        <div
                            class="absolute -inset-1 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity">
                        </div>
                        <div class="relative">
                            <div class="flex items-center gap-6 mb-8">
                                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-home text-3xl text-blue-600"></i>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-800">Residents</h3>
                                    <p class="text-blue-600">Full Service Access</p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-blue-600 mt-1" style="margin-left: 0.5rem;"></i>
                                    <span class="text-gray-600">Create and manage personal BPAMIS accounts</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-blue-600 mt-1" style="margin-left: 0.5rem;"></i>
                                    <span class="text-gray-600">File complaints and track case progress</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-blue-600 mt-1" style="margin-left: 0.5rem;"></i>
                                    <span class="text-gray-600">Request official documents online</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-blue-600 mt-1" style="margin-left: 0.5rem;"></i>
                                    <span class="text-gray-600">Access community announcements and updates</span>
                                </div>
                                <a href="register.php"
                                    class="mt-6 inline-flex items-center text-blue-600 hover:text-blue-700">
                                    Register as Resident
                                    <i
                                        class="fas fa-arrow-right ml-2 transition-transform group-hover:translate-x-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- External Complainant Card -->
                <div class="user-card group slide-in-right stagger-fade-delay-1">
                    <div
                        class="relative bg-white rounded-xl shadow-lg p-8 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl">
                        <div
                            class="absolute -inset-1 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity">
                        </div>
                        <div class="relative">
                            <div class="flex items-center gap-6 mb-8">
                                <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-friends text-3xl text-purple-600"></i>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-800">External Complainant</h3>
                                    <p class="text-purple-600">Limited Service Access</p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-purple-600 mt-1" style="margin-left: 0.5rem;"></i>
                                    <span class="text-gray-600">File complaints against Panducot residents</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-purple-600 mt-1" style="margin-left: 0.5rem;"></i>
                                    <span class="text-gray-600">Track case status and updates remotely</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-purple-600 mt-1" style="margin-left: 0.5rem;"></i>
                                    <span class="text-gray-600">Receive hearing notifications</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-purple-600 mt-1" style="margin-left: 0.5rem;"></i>
                                    <span class="text-gray-600">Access case-related documents</span>
                                </div>
                                <a href="register.php?type=external"
                                    class="mt-6 inline-flex items-center text-purple-600 hover:text-purple-700">
                                    Register as External Complainant
                                    <i
                                        class="fas fa-arrow-right ml-2 transition-transform group-hover:translate-x-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
        <div class="container mx-auto px-4">
            <!-- Header -->
             <div class="text-center max-w-4xl mx-auto mb-20  fade-in-element" style="margin-top: 4rem;">
                <div
                    class="inline-flex items-center px-6 py-3 bg-white/80 backdrop-blur-sm text-blue-700 rounded-full text-sm font-semibold mb-6 border border-blue-200/50 shadow-sm stagger-fade-delay-1">
                    <i class="fas fa-star mr-2 text-blue-500"></i>
                    Usage
                </div>
                <h2
                    class="text-3xl lg:text-5xl font-bold mb-8 bg-gradient-to-r from-gray-900 via-blue-800 to-gray-900 bg-clip-text text-transparent leading-tight stagger-fade-delay-2">
                    Why Use BPAMIS?
                </h2>
                <div
                    class="w-24 h-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full mx-auto mt-8 stagger-fade-delay-4">
                </div>
            </div>

            <!-- Features Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mt-12">
                <!-- Digital Case Records -->
                <div
                    class="feature-benefit-card dgtl-container bg-white rounded-xl p-8 shadow-sm hover:shadow-md transition-shadow duration-300 scroll-animate scale-in stagger-1">
                    <div
                        class="benefit-icon-wrapper w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <i class="fas fa-folder-open text-2xl text-blue-600 benefit-icon"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4 benefit-title">Digital Case Records</h3>
                    <ul class="space-y-3 text-gray-600 benefit-list">
                        <li class="flex items-start benefit-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 benefit-check-icon"></i>
                            <span>Centralized digital case storage</span>
                        </li>
                        <li class="flex items-start benefit-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 benefit-check-icon"></i>
                            <span>Quick access to case histories</span>
                        </li>
                        <li class="flex items-start benefit-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 benefit-check-icon"></i>
                            <span>Enhanced data security</span>
                        </li>
                    </ul>
                </div>

                <!-- Online Complaints -->
                <div
                    class="feature-benefit-card dgtl-container bg-white rounded-xl p-8 shadow-sm hover:shadow-md transition-shadow duration-300 scroll-animate scale-in stagger-2">
                    <div
                        class="benefit-icon-wrapper w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <i class="fas fa-laptop text-2xl text-blue-600 benefit-icon"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4 benefit-title">Online Complaints</h3>
                    <ul class="space-y-3 text-gray-600 benefit-list">
                        <li class="flex items-start benefit-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 benefit-check-icon"></i>
                            <span>File complaints remotely</span>
                        </li>
                        <li class="flex items-start benefit-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 benefit-check-icon"></i>
                            <span>Real-time case updates</span>
                        </li>
                        <li class="flex items-start benefit-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 benefit-check-icon"></i>
                            <span>Reduced waiting time</span>
                        </li>
                    </ul>
                </div>

                <!-- Live Dashboard -->
                <div
                    class="feature-benefit-card dgtl-container bg-white rounded-xl p-8 shadow-sm hover:shadow-md transition-shadow duration-300 scroll-animate scale-in stagger-3">
                    <div
                        class="benefit-icon-wrapper w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <i class="fas fa-chart-line text-2xl text-blue-600 benefit-icon"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4 benefit-title">Live Dashboard</h3>
                    <ul class="space-y-3 text-gray-600 benefit-list">
                        <li class="flex items-start benefit-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 benefit-check-icon"></i>
                            <span>Track active cases</span>
                        </li>
                        <li class="flex items-start benefit-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 benefit-check-icon"></i>
                            <span>Generate instant reports</span>
                        </li>
                        <li class="flex items-start benefit-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 benefit-check-icon"></i>
                            <span>Monitor resolution rates</span>
                        </li>
                    </ul>
                </div>

                <!-- Case Management -->
                <div
                    class="feature-benefit-card dgtl-container bg-white rounded-xl p-8 shadow-sm hover:shadow-md transition-shadow duration-300 scroll-animate scale-in stagger-4">
                    <div
                        class="benefit-icon-wrapper w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <i class="fas fa-tasks text-2xl text-blue-600 benefit-icon"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4 benefit-title">Case Management</h3>
                    <ul class="space-y-3 text-gray-600 benefit-list">
                        <li class="flex items-start benefit-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 benefit-check-icon"></i>
                            <span>Smart case categorization</span>
                        </li>
                        <li class="flex items-start benefit-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 benefit-check-icon"></i>
                            <span>Automated scheduling</span>
                        </li>
                        <li class="flex items-start benefit-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 benefit-check-icon"></i>
                            <span>Step-by-step tracking</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>


        <!-- Enhanced Data Security and Privacy Section -->

        <div class="container mx-auto px-4" style="margin-top: 4rem;">
            
            <!-- Header -->
             <div class="text-center max-w-4xl mx-auto mb-20  fade-in-element" style="margin-top: 4rem;">
                <div
                    class="inline-flex items-center px-6 py-3 bg-white/80 backdrop-blur-sm text-blue-700 rounded-full text-sm font-semibold mb-6 border border-blue-200/50 shadow-sm stagger-fade-delay-1">
                    <i class="fas fa-star mr-2 text-blue-500"></i>
                    Security
                </div>
                <h2
                    class="text-3xl lg:text-5xl font-bold mb-8 bg-gradient-to-r from-gray-900 via-blue-800 to-gray-900 bg-clip-text text-transparent leading-tight stagger-fade-delay-2">
                    Keep Your Data Safe with BPAMIS
                </h2>
                <div
                    class="w-24 h-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full mx-auto mt-8 stagger-fade-delay-4">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">
                <!-- Legal Framework -->
                <div
                    class="security-benefit-card sf-container bg-blue-50 rounded-xl p-8 shadow-sm hover:shadow-md transition-shadow duration-300 scroll-animate scale-in stagger-1">
                    <div
                        class="security-icon-wrapper w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <i class="fas fa-balance-scale text-2xl text-blue-600 security-icon"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4 security-title">Legal Framework</h3>
                    <ul class="space-y-4 security-list">
                        <!-- Data Privacy Act -->
                        <li class="flex items-start security-item">
                            <div
                                class="flex-shrink-0 w-8 h-8 bg-blue-200 rounded-lg flex items-center justify-center mr-3 security-mini-icon">
                                <i class="fas fa-shield-alt text-white"></i>
                            </div>
                            <div>
                                <div class="flex items-center">
                                    <p class="font-semibold text-gray-800 mr-2">Data Privacy Act</p>
                                    <div class="relative info-container">
                                        <span
                                            class="info-icon text-purple-500 bg-purple-100 rounded-full flex items-center justify-center w-6 h-6 cursor-pointer transition-colors duration-200 hover:bg-purple-500 hover:text-white">
                                            <a href="https://privacy.gov.ph/data-privacy-act/"
                                                        target="_blank"><i class="fas fa-info text-xs pointer-events-none"></i></a>
                                        </span>
                                        
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600">Protected under RA 10173</p>
                            </div>
                        </li>
                        <!-- Cybercrime Prevention Act -->
                        <li class="flex items-start security-item">
                            <div
                                class="flex-shrink-0 w-8 h-8 bg-blue-200 rounded-lg flex items-center justify-center mr-3 security-mini-icon">
                                <i class="fas fa-lock text-white"></i>
                            </div>
                            <div>
                                <div class="flex items-center">
                                    <p class="font-semibold text-gray-800 mr-2">Cybercrime Prevention Act</p>
                                    <div class="relative info-container">
                                       <span
                                            class="info-icon text-purple-500 bg-purple-100 rounded-full flex items-center justify-center w-6 h-6 cursor-pointer transition-colors duration-200 hover:bg-purple-500 hover:text-white">
                                            <a href="https://www.officialgazette.gov.ph/2012/09/12/republic-act-no-10175/"
                                                        target="_blank"><i class="fas fa-info text-xs pointer-events-none"></i></a>
                                        </span>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600">Secured under RA 10175</p>
                            </div>
                        </li>
                        <!-- Local Government Code -->
                        <li class="flex items-start security-item">
                            <div
                                class="flex-shrink-0 w-8 h-8 bg-blue-200 rounded-lg flex items-center justify-center mr-3 security-mini-icon">
                                <i class="fas fa-landmark text-white"></i>
                            </div>
                            <div>
                                <div class="flex items-center">
                                    <p class="font-semibold text-gray-800 mr-2">Local Government Code</p>
                                    <div class="relative info-container">
                                        <span
                                            class="info-icon text-purple-500 bg-purple-100 rounded-full flex items-center justify-center w-6 h-6 cursor-pointer transition-colors duration-200 hover:bg-purple-500 hover:text-white">
                                            <a href="https://www.officialgazette.gov.ph/1991/10/10/republic-act-no-7160/"
                                                        target="_blank"><i class="fas fa-info text-xs pointer-events-none"></i></a>
                                        </span>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600">Compliant with RA 7160</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Security Features -->
                <div
                    class="security-benefit-card sf-container bg-blue-50 rounded-xl p-8 shadow-sm hover:shadow-md transition-shadow duration-300 scroll-animate scale-in stagger-2">
                    <div
                        class="security-icon-wrapper w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <i class="fas fa-shield-alt text-2xl text-blue-600 security-icon"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4 security-title">Security Features</h3>
                    <ul class="space-y-3 text-gray-600 security-list">
                        <li class="flex items-start security-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 security-check-icon"></i>
                            <span>End-to-end data encryption</span>
                        </li>
                        <li class="flex items-start security-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 security-check-icon"></i>
                            <span>Role-based access control</span>
                        </li>
                        <li class="flex items-start security-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 security-check-icon"></i>
                            <span>Secure cloud storage</span>
                        </li>
                        <li class="flex items-start security-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 security-check-icon"></i>
                            <span>Data backup and recovery</span>
                        </li>
                        <li class="flex items-start security-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 security-check-icon"></i>
                            <span>Two-factor authentication</span>
                        </li>
                    </ul>
                </div>

                <!-- Trust & Transparency -->
                <div
                    class="security-benefit-card sf-container bg-blue-50 rounded-xl p-8 shadow-sm hover:shadow-md transition-shadow duration-300 scroll-animate scale-in stagger-3">
                    <div
                        class="security-icon-wrapper w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <i class="fas fa-handshake text-2xl text-blue-600 security-icon"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4 security-title">Trust & Transparency</h3>
                    <div class="space-y-4 text-gray-600 security-list">
                        <p class="flex items-start security-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 security-check-icon"></i>
                            <span>Registered with National Privacy Commission</span>
                        </p>
                        <p class="flex items-start security-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 security-check-icon"></i>
                            <span>Regular security audits and updates</span>
                        </p>
                        <p class="flex items-start security-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 security-check-icon"></i>
                            <span>Transparent case management</span>
                        </p>
                        <p class="flex items-start security-item">
                            <i class="fas fa-check text-blue-500 mt-1 mr-2 security-check-icon"></i>
                            <span>Community-focused governance</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced Quick Links Section -->
    <section class="py-20 relative overflow-hidden"
        style="background: linear-gradient(180deg, #fff 10%,rgba(37, 100, 235, 0.77) 50%, #fff 100%);">
        <!-- Background Elements -->
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50/50 via-white to-indigo-50/30"></div>
        <div class="absolute top-10 right-10 w-72 h-72 bg-blue-100/20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-10 left-10 w-96 h-96 bg-indigo-100/20 rounded-full blur-3xl"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Enhanced Header Section -->
            <div class="text-center max-w-4xl mx-auto mb-20 scroll-animate">
                <div
                    class="inline-flex items-center px-6 py-3 bg-white/80 backdrop-blur-sm text-blue-700 rounded-full text-sm font-semibold mb-6 border border-blue-200/50 shadow-sm">
                    <i class="fas fa-rocket mr-2 text-blue-500"></i>
                    Quick Access
                </div>
                <h2
                    class="text-6xl lg:text-6xl font-bold mb-8 bg-gradient-to-r from-gray-900 via-blue-800 to-gray-900 bg-clip-text text-transparent leading-tight">
                    Quick Links
                </h2>
                <p class="text-xl text-gray-600 leading-relaxed max-w-3xl mx-auto">
                    Easily access scheduling, support, and answers to common questions through these helpful quick
                    links. Get the assistance you need in just a few clicks.
                </p>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full mx-auto mt-8"></div>
            </div>

            <!-- Enhanced Quick Links Grid -->
            <div class="max-w-6xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <!-- Schedule Appointment -->
                    <div class="group quick-link-card fade-in-element stagger-fade-delay-1 relative">
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-indigo-500/5 rounded-2xl transform rotate-1 group-hover:rotate-0 transition-transform duration-500">
                        </div>
                        <a href="#" onclick="openModal(event)"
                            class="relative block bg-white/95 backdrop-blur-sm rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-500 border border-white/50 group-hover:border-blue-200/50 text-center">
                            <!-- Floating Background Elements -->
                            <div
                                class="absolute top-4 right-4 w-16 h-16 bg-gradient-to-br from-blue-400/10 to-indigo-400/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-700">
                            </div>

                            <!-- Icon Container -->
                            <div class="relative mb-6">
                                <div
                                    class="w-20 h-20 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mx-auto shadow-lg group-hover:shadow-blue-500/25 transition-all duration-500 group-hover:scale-110 group-hover:rotate-3">
                                    <i
                                        class="fas fa-calendar-alt text-3xl text-white transform group-hover:scale-110 transition-transform duration-500"></i>
                                </div>
                                <div
                                    class="absolute -top-2 -right-2 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                    <i class="fas fa-check text-white text-xs"></i>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="space-y-4">
                                <h3
                                    class="text-xl font-bold text-gray-900 group-hover:text-white transition-colors duration-300">
                                    Schedule Appointment
                                </h3>
                                <p class="text-gray-200 leading-relaxed text-sm">
                                    Book your visit with our convenient online scheduling system.
                                </p>

                                <!-- Feature Tags -->
                                <div
                                    class="flex flex-wrap gap-2 justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <span
                                        class="bg-blue-100 text-blue-700 px-2 py-1 rounded-full text-xs font-medium">Online
                                        Booking</span>
                                    <span
                                        class="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-medium">Instant
                                        Confirmation</span>
                                </div>
                            </div>

                            <!-- Hover Arrow -->
                            <div
                                class="absolute bottom-4 right-4 opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-x-2 group-hover:translate-x-0">
                                <i class="fas fa-arrow-right text-blue-500"></i>
                            </div>
                        </a>
                    </div>

                    <!-- Contact BPAMIS -->
                    <div class="group quick-link-card fade-in-element stagger-fade-delay-2 relative">
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-teal-500/5 rounded-2xl transform -rotate-1 group-hover:rotate-0 transition-transform duration-500">
                        </div>
                        <a href="#" onclick="openContactModal(event)"
                            class="relative block bg-white/95 backdrop-blur-sm rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-500 border border-white/50 group-hover:border-emerald-200/50 text-center">
                            <!-- Floating Background Elements -->
                            <div
                                class="absolute top-4 right-4 w-16 h-16 bg-gradient-to-br from-emerald-400/10 to-teal-400/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-700">
                            </div>

                            <!-- Icon Container -->
                            <div class="relative mb-6">
                                <div
                                    class="w-20 h-20 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl flex items-center justify-center mx-auto shadow-lg group-hover:shadow-emerald-500/25 transition-all duration-500 group-hover:scale-110 group-hover:rotate-3">
                                    <i
                                        class="fas fa-phone-alt text-3xl text-white transform group-hover:scale-110 transition-transform duration-500"></i>
                                </div>
                                <div
                                    class="absolute -top-2 -right-2 w-6 h-6 bg-orange-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                    <i class="fas fa-lightning-bolt text-white text-xs"></i>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="space-y-4">
                                <h3
                                    class="text-xl font-bold text-gray-900 group-hover:text-white transition-colors duration-300">
                                    Contact BPAMIS Support
                                </h3>
                                <p class="text-gray-200 leading-relaxed text-sm">
                                    Get in touch with our support team for technical assistance or feedback.
                                </p>

                                <!-- Feature Tags -->
                                <div
                                    class="flex flex-wrap gap-2 justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <span
                                        class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded-full text-xs font-medium">Assistance
                                        Center</span>
                                    <span
                                        class="bg-orange-100 text-orange-700 px-2 py-1 rounded-full text-xs font-medium">Quick
                                        Response</span>
                                </div>
                            </div>

                            <!-- Hover Arrow -->
                            <div
                                class="absolute bottom-4 right-4 opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-x-2 group-hover:translate-x-0">
                                <i class="fas fa-arrow-right text-emerald-500"></i>
                            </div>
                        </a>
                    </div>

                    <!-- FAQs -->
                    <div class="group quick-link-card fade-in-element stagger-fade-delay-3 relative">
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-purple-500/5 to-violet-500/5 rounded-2xl transform rotate-1 group-hover:rotate-0 transition-transform duration-500">
                        </div>
                        <a href="#" onclick="openFaqsModal(event)"
                            class="relative block bg-white/95 backdrop-blur-sm rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-500 border border-white/50 group-hover:border-purple-200/50 text-center">
                            <!-- Floating Background Elements -->
                            <div
                                class="absolute top-4 right-4 w-16 h-16 bg-gradient-to-br from-purple-400/10 to-violet-400/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-700">
                            </div>

                            <!-- Icon Container -->
                            <div class="relative mb-6">
                                <div
                                    class="w-20 h-20 bg-gradient-to-br from-purple-500 to-violet-600 rounded-xl flex items-center justify-center mx-auto shadow-lg group-hover:shadow-purple-500/25 transition-all duration-500 group-hover:scale-110 group-hover:rotate-3">
                                    <i
                                        class="fas fa-question-circle text-3xl text-white transform group-hover:scale-110 transition-transform duration-500"></i>
                                </div>
                                <div
                                    class="absolute -top-2 -right-2 w-6 h-6 bg-yellow-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                    <i class="fas fa-star text-white text-xs"></i>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="space-y-4">
                                <h3
                                    class="text-xl font-bold text-gray-900 group-hover:text-purple-700 transition-colors duration-300">
                                    Frequently Asked Questions
                                </h3>
                                <p class="text-gray-200 leading-relaxed text-sm">
                                    Find answers to common questions about local government procedures.
                                </p>

                                <!-- Feature Tags -->
                                <div
                                    class="flex flex-wrap gap-2 justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <span
                                        class="bg-purple-100 text-purple-700 px-2 py-1 rounded-full text-xs font-medium">Public
                                        Inquiries</span>
                                    <span
                                        class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded-full text-xs font-medium">General
                                        Informations</span>
                                </div>
                            </div>

                            <!-- External Link Indicator -->
                            <div
                                class="absolute top-4 left-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <i class="fas fa-external-link-alt text-purple-400 text-sm"></i>
                            </div>

                            <!-- Hover Arrow -->
                            <div
                                class="absolute bottom-4 right-4 opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-x-2 group-hover:translate-x-0">
                                <i class="fas fa-arrow-right text-purple-500"></i>
                            </div>
                        </a>
                    </div>

                    <!-- User Guide / Documentation -->
                    <div class="group quick-link-card fade-in-element stagger-fade-delay-4 relative">
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-orange-500/5 to-red-500/5 rounded-2xl transform -rotate-1 group-hover:rotate-0 transition-transform duration-500">
                        </div>
                        <a href="#" onclick="openUserGuideModal(event)"
                            class="relative block bg-white/95 backdrop-blur-sm rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-500 border border-white/50 group-hover:border-orange-200/50 text-center">
                            <!-- Floating Background Elements -->
                            <div
                                class="absolute top-4 right-4 w-16 h-16 bg-gradient-to-br from-orange-400/10 to-red-400/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-700">
                            </div>

                            <!-- Icon Container -->
                            <div class="relative mb-6">
                                <div
                                    class="w-20 h-20 bg-gradient-to-br from-orange-500 to-red-500 rounded-xl flex items-center justify-center mx-auto shadow-lg group-hover:shadow-orange-500/25 transition-all duration-500 group-hover:scale-110 group-hover:rotate-3">
                                    <i
                                        class="fas fa-book-open text-3xl text-white transform group-hover:scale-110 transition-transform duration-500"></i>
                                </div>
                                <div
                                    class="absolute -top-2 -right-2 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                    <i class="fas fa-graduation-cap text-white text-xs"></i>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="space-y-4">
                                <h3
                                    class="text-xl font-bold text-gray-900 group-hover:text-white transition-colors duration-300">
                                    User Guide
                                </h3>
                                <p class="text-gray-200 leading-relaxed text-sm">
                                    Step-by-step tutorials and comprehensive documentation in using the BPAMIS features
                                    effectively.
                                </p>

                                <!-- Feature Tags -->
                                <div
                                    class="flex flex-wrap gap-2 justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <span
                                        class="bg-orange-100 text-orange-700 px-2 py-1 rounded-full text-xs font-medium">Tutorial
                                        Manual</span>
                                    <span
                                        class="bg-blue-100 text-blue-700 px-2 py-1 rounded-full text-xs font-medium">Step-by-Step
                                        Guide</span>
                                </div>
                            </div>

                            <!-- Hover Arrow -->
                            <div
                                class="absolute bottom-4 right-4 opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-x-2 group-hover:translate-x-0">
                                <i class="fas fa-arrow-right text-orange-500"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Additional Quick Actions -->
            <div class="mt-16 max-w-4xl mx-auto">
                <div
                    class="bg-white/95 backdrop-blur-md rounded-3xl htln-container shadow-xl p-8 lg:p-12 border border-white/30 scroll-animate">
                    <div class="text-center mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Need Immediate Assistance?</h3>
                        <p class="text-gray-600">For urgent matters or emergency situations, use these direct contact
                            options.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Emergency Hotline -->
                        <div
                            class="flex items-center space-x-4 p-4 bg-red-50 rounded-xl border border-red-200/50 hover:bg-red-100 transition-colors duration-300">
                            <div class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-phone text-white"></i>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900">Emergency Hotline</div>
                                <div class="text-sm text-red-600 font-medium">+63 912 345 6789</div>
                            </div>
                        </div>

                        <!-- Email Support -->
                        <div
                            class="flex items-center space-x-4 p-4 bg-blue-50 rounded-xl border border-blue-200/50 hover:bg-blue-100 transition-colors duration-300">
                            <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-envelope text-white"></i>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900">Email Support</div>
                                <div class="text-sm text-blue-600 font-medium">support@bpamis.gov.ph</div>
                            </div>
                        </div>

                        <!-- Office Hours -->
                        <div
                            class="flex items-center space-x-4 p-4 bg-green-50 rounded-xl border border-green-200/50 hover:bg-green-100 transition-colors duration-300">
                            <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900">Office Hours</div>
                                <div class="text-sm text-green-600 font-medium">Mon-Fri 8AM-5PM</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Add this modal markup just before the closing </body> tag -->
    <!-- User Guide Side Panel -->
    <div id="guideModal" class="fixed inset-0 hidden z-50" aria-labelledby="guide-title" role="dialog"
        aria-modal="true">
        <!-- Background overlay with blur -->
        <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-[4px]" aria-hidden="true"
            onclick="closeGuideModal()"></div>

        <!-- Side panel container -->
        <div class="flex justify-end h-full">
            <!-- Side panel with responsive sizing -->
            <div id="guideModalContent"
                class="relative bg-white shadow-xl w-full sm:max-w-md md:max-w-lg h-full flex flex-col transform transition-all ease-out duration-300 translate-x-full"
                tabindex="-1">
                <!-- Side panel header (fixed) -->
                <div
                    class="bg-gradient-to-r from-blue-700 to-blue-500 p-4 flex justify-between items-center sticky top-0 z-10">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-white p-2 rounded-full">
                            <i class="fas fa-book text-blue-600 text-xl"></i>
                        </div>
                        <h2 id="faqs-title" class="ml-3 text-lg font-bold text-white">User Guide Manual</h2>
                    </div>
                    <button
                        class="text-white bg-blue-600 bg-opacity-20 hover:bg-opacity-30 rounded-full p-2 focus:outline-none focus:ring-2 focus:ring-white"
                        onclick="closeGuideModal()" aria-label="Close">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <div class="p-4 overflow-y-auto flex-grow guide-custom-scrollbar bg-white">
                    <div class="space-y-6 bg-white">
                        <!-- Guide Category: Getting Started -->
                        <div id="getting-started" class="bg-white">
                            <h3 class="text-lg font-semibold text-emerald-700 border-b border-emerald-100 pb-2">Getting
                                Started</h3>

                            <!-- Guide Item 1 -->
                            <div class="guide-item border-b border-gray-100 pb-4">
                                <div class="guide-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleGuideAnswer('guide1')" data-guide="guide1">
                                    <h4 class="font-medium text-gray-800">How to Access BPAMIS</h4>
                                    <i
                                        class="fas fa-chevron-down text-emerald-500 transition-transform duration-300"></i>
                                </div>
                                <div id="guide1" class="guide-answer pt-2 pl-4 text-gray-600 hidden">
                                    <p>To access the BPAMIS system:</p>
                                    <ol class="list-decimal pl-5 mt-2 space-y-1">
                                        <li>Open your web browser and navigate to the BPAMIS website</li>
                                        <li>Click on the "Login" button in the top right corner</li>
                                        <li>Enter your username and password</li>
                                        <li>Click "Sign In" to access your dashboard</li>
                                    </ol>
                                    <p class="mt-2">If you don't have an account yet, you'll need to register first.
                                        Click on the "Register" button and follow the instructions.</p>
                                </div>
                            </div>

                            <!-- Guide Item 2 -->
                            <div class="guide-item border-b border-gray-100 pb-4">
                                <div class="guide-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleGuideAnswer('guide2')" data-guide="guide2">
                                    <h4 class="font-medium text-gray-800">Understanding Your Dashboard</h4>
                                    <i
                                        class="fas fa-chevron-down text-emerald-500 transition-transform duration-300"></i>
                                </div>
                                <div id="guide2" class="guide-answer pt-2 pl-4 text-gray-600 hidden">
                                    <p>Your dashboard is customized based on your user role. Here are the key
                                        components:</p>
                                    <ul class="list-disc pl-5 mt-2 space-y-1">
                                        <li><strong>Navigation Menu:</strong> Located on the left side, provides access
                                            to all system features</li>
                                        <li><strong>Quick Stats:</strong> Shows important numbers and metrics relevant
                                            to your role</li>
                                        <li><strong>Recent Activity:</strong> Displays your recent actions and
                                            notifications</li>
                                        <li><strong>Calendar:</strong> Shows upcoming hearings and important dates</li>
                                        <li><strong>Quick Actions:</strong> Buttons for common tasks like filing a new
                                            complaint</li>
                                    </ul>
                                    <p class="mt-2">Hover over any element on the dashboard for more information about
                                        its function.</p>
                                </div>
                            </div>

                            <!-- Guide Item 3 -->
                            <div class="guide-item border-b border-gray-100 pb-4">
                                <div class="guide-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleGuideAnswer('guide3')" data-guide="guide3">
                                    <h4 class="font-medium text-gray-800">Updating Your Profile</h4>
                                    <i
                                        class="fas fa-chevron-down text-emerald-500 transition-transform duration-300"></i>
                                </div>
                                <div id="guide3" class="guide-answer pt-2 pl-4 text-gray-600 hidden">
                                    <p>To update your profile information:</p>
                                    <ol class="list-decimal pl-5 mt-2 space-y-1">
                                        <li>Click on your profile icon in the top right corner</li>
                                        <li>Select "Profile Settings" from the dropdown menu</li>
                                        <li>Update your personal information, contact details, or password</li>
                                        <li>Click "Save Changes" to apply your updates</li>
                                    </ol>
                                    <p class="mt-2">It's important to keep your contact information up-to-date to
                                        receive notifications about case updates and hearing schedules.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Guide Category: Filing Complaints -->
                        <div id="filing-complaints" class="bg-white mt-8">
                            <h3 class="text-lg font-semibold text-emerald-700 border-b border-emerald-100 pb-2">Filing
                                Complaints</h3>

                            <!-- Guide Item 4 -->
                            <div class="guide-item border-b border-gray-100 pb-4">
                                <div class="guide-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleGuideAnswer('guide4')" data-guide="guide4">
                                    <h4 class="font-medium text-gray-800">How to File a New Complaint</h4>
                                    <i
                                        class="fas fa-chevron-down text-emerald-500 transition-transform duration-300"></i>
                                </div>
                                <div id="guide4" class="guide-answer pt-2 pl-4 text-gray-600 hidden">
                                    <p>To file a new complaint in BPAMIS:</p>
                                    <ol class="list-decimal pl-5 mt-2 space-y-2">
                                        <li>From your dashboard, click on "Submit Complaint" or navigate to the
                                            Complaints section in the menu</li>
                                        <li>Fill out the complaint form with all required information:
                                            <ul class="list-disc pl-5 mt-1">
                                                <li>Type of complaint</li>
                                                <li>Date and time of incident</li>
                                                <li>Respondent information (the person you're filing against)</li>
                                                <li>Detailed description of the incident</li>
                                            </ul>
                                        </li>
                                        <li>Upload any supporting documents or evidence (optional)</li>
                                        <li>Review your complaint details</li>
                                        <li>Click "Submit" to file your complaint</li>
                                    </ol>
                                    <p class="mt-2">After submission, you'll receive a confirmation with a complaint
                                        reference number. You can use this number to track the status of your complaint.
                                    </p>
                                </div>
                            </div>

                            <!-- Guide Item 5 -->
                            <div class="guide-item border-b border-gray-100 pb-4">
                                <div class="guide-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleGuideAnswer('guide5')" data-guide="guide5">
                                    <h4 class="font-medium text-gray-800">Tracking Your Complaint Status</h4>
                                    <i
                                        class="fas fa-chevron-down text-emerald-500 transition-transform duration-300"></i>
                                </div>
                                <div id="guide5" class="guide-answer pt-2 pl-4 text-gray-600 hidden">
                                    <p>To check the status of your submitted complaint:</p>
                                    <ol class="list-decimal pl-5 mt-2 space-y-1">
                                        <li>Log in to your BPAMIS account</li>
                                        <li>Navigate to "My Complaints" or "View Complaints" in the menu</li>
                                        <li>Find your complaint in the list or search using your reference number</li>
                                        <li>Click on the complaint to view detailed status information</li>
                                    </ol>
                                    <p class="mt-2">Complaint statuses include:</p>
                                    <ul class="list-disc pl-5 mt-2 space-y-1">
                                        <li><strong>Pending:</strong> Complaint has been received but not yet processed
                                        </li>
                                        <li><strong>Under Review:</strong> Barangay officials are reviewing the
                                            complaint</li>
                                        <li><strong>Scheduled:</strong> A hearing date has been set</li>
                                        <li><strong>Mediation:</strong> The case is in the mediation process</li>
                                        <li><strong>Resolved:</strong> The case has been settled</li>
                                        <li><strong>Referred:</strong> The case has been referred to another body</li>
                                    </ul>
                                    <p class="mt-2">You will receive notifications when your complaint status changes or
                                        when action is required from you.</p>
                                </div>
                            </div>

                            <!-- Guide Item 6 -->
                            <div class="guide-item border-b border-gray-100 pb-4">
                                <div class="guide-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleGuideAnswer('guide6')" data-guide="guide6">
                                    <h4 class="font-medium text-gray-800">Preparing for a Hearing</h4>
                                    <i
                                        class="fas fa-chevron-down text-emerald-500 transition-transform duration-300"></i>
                                </div>
                                <div id="guide6" class="guide-answer pt-2 pl-4 text-gray-600 hidden">
                                    <p>When your case is scheduled for a hearing, follow these steps to prepare:</p>
                                    <ol class="list-decimal pl-5 mt-2 space-y-2">
                                        <li>Confirm your attendance through the system when you receive a hearing
                                            notification</li>
                                        <li>Prepare all relevant documents and evidence to support your case</li>
                                        <li>Review the hearing details in your notification:
                                            <ul class="list-disc pl-5 mt-1">
                                                <li>Date and time of the hearing</li>
                                                <li>Location (physical or virtual)</li>
                                                <li>Names of officials who will be present</li>
                                            </ul>
                                        </li>
                                        <li>If you need to reschedule, request a new date at least 3 days before the
                                            hearing</li>
                                        <li>Arrive at least 15 minutes early on the day of the hearing</li>
                                    </ol>
                                    <p class="mt-2">Remember to maintain respectful conduct during the hearing and
                                        follow the instructions of the Barangay officials.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Help Center Side Panel -->
    <div id="helpCenterModal" class="fixed inset-0 hidden z-50" aria-labelledby="help-center-title" role="dialog"
        aria-modal="true">
        <!-- Background overlay with blur -->
        <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-[4px]" aria-hidden="true"
            onclick="closeHelpCenterModal()"></div>

        <!-- Side panel container -->
        <div class="flex justify-end h-full">
            <!-- Side panel with responsive sizing -->
            <div id="helpCenterModalContent"
                class="relative bg-white shadow-xl w-full sm:max-w-md md:max-w-lg h-full flex flex-col transform transition-all ease-out duration-300 translate-x-full"
                tabindex="-1">
                <!-- Side panel header (fixed) -->
                <div
                    class="bg-gradient-to-r from-blue-700 to-blue-500 p-4 flex justify-between items-center sticky top-0 z-10">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-white p-2 rounded-full">
                            <i class="fas fa-info-circle text-blue-600 text-xl"></i>
                        </div>
                        <h2 id="help-center-title" class="ml-3 text-lg font-bold text-white">Help Center</h2>
                    </div>
                    <button
                        class="text-white bg-blue-600 bg-opacity-20 hover:bg-opacity-30 rounded-full p-2 focus:outline-none focus:ring-2 focus:ring-white"
                        onclick="closeHelpCenterModal()" aria-label="Close">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <div class="p-4 overflow-y-auto flex-grow custom-scrollbar bg-white">
                    <div class="space-y-6 bg-white">
                        <!-- Help Category: Getting Started -->
                        <div id="getting-started" class="bg-white">
                            <h3 class="text-lg font-semibold text-blue-700 border-b border-blue-100 pb-2">Getting
                                Started</h3>

                            <!-- Help Item 1 -->
                            <div class="help-item border-b border-gray-100 pb-4">
                                <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleHelpAnswer('help1')" data-help="help1">
                                    <h4 class="font-medium text-gray-800">Creating an Account</h4>
                                    <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                                </div>
                                <div id="help1" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                    <p>If you're a resident of Barangay Panducot, you can register for an account to
                                        access various services:</p>
                                    <ol class="list-decimal pl-5 mt-2 space-y-1">
                                        <li>Click on the "Register" button on the homepage</li>
                                        <li>Fill in your personal information</li>
                                        <li>Create a secure password</li>
                                        <li>Submit your registration</li>
                                    </ol>
                                    <p class="mt-2">Your account will need to be verified before you can use all
                                        features.</p>
                                    <a href="../register.php"
                                        class="mt-2 inline-block text-sm text-blue-600 hover:text-blue-800 font-medium">Register
                                        Now <i class="fas fa-arrow-right ml-1"></i></a>
                                </div>
                            </div>

                            <!-- Help Item 2 -->
                            <div class="help-item border-b border-gray-100 pb-4">
                                <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleHelpAnswer('help2')" data-help="help2">
                                    <h4 class="font-medium text-gray-800">Logging In</h4>
                                    <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                                </div>
                                <div id="help2" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                    <p>To log in to your BPAMIS account:</p>
                                    <ol class="list-decimal pl-5 mt-2 space-y-1">
                                        <li>Click on the "Login" button on the homepage</li>
                                        <li>Enter your registered email/username</li>
                                        <li>Enter your password</li>
                                        <li>Click "Sign In"</li>
                                    </ol>
                                    <p class="mt-2">If you forgot your password, use the "Forgot Password" link on the
                                        login page.</p>
                                    <a href="../bpamis_website/login.php"
                                        class="mt-2 inline-block text-sm text-blue-600 hover:text-blue-800 font-medium">Log
                                        In <i class="fas fa-arrow-right ml-1"></i></a>
                                </div>
                            </div>

                            <!-- Help Item 3 -->
                            <div class="help-item border-b border-gray-100 pb-4">
                                <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleHelpAnswer('help3')" data-help="help3">
                                    <h4 class="font-medium text-gray-800">System Navigation</h4>
                                    <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                                </div>
                                <div id="help3" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                    <p>Once logged in, you can access various features depending on your role:</p>
                                    <ul class="list-disc pl-5 mt-2 space-y-1">
                                        <li>File complaints</li>
                                        <li>Track case status</li>
                                        <li>View hearing schedules</li>
                                        <li>Access relevant forms and documents</li>
                                    </ul>
                                    <p class="mt-2">The main navigation menu is on the left side of your dashboard and
                                        contains all accessible features for your user role.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Help Category: User Guides -->
                        <div id="user-guides">
                            <h3 class="text-lg font-semibold text-blue-700 border-b border-blue-100 pb-2">User Guides
                            </h3>

                            <!-- Help Item 4 -->
                            <div class="help-item border-b border-gray-100 pb-4">
                                <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleHelpAnswer('help4')" data-help="help4">
                                    <h4 class="font-medium text-gray-800">Filing a Complaint</h4>
                                    <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                                </div>
                                <div id="help4" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                    <ol class="list-decimal pl-5 mt-2 space-y-1">
                                        <li>Log in to your resident account</li>
                                        <li>Navigate to "Submit Complaints" in the menu</li>
                                        <li>Fill out the complaint form with all required details</li>
                                        <li>Upload any supporting documents if necessary</li>
                                        <li>Review your complaint information</li>
                                        <li>Submit your complaint</li>
                                    </ol>
                                    <p class="mt-3 text-sm text-gray-600">Note: Your complaint will be reviewed by the
                                        Barangay Secretary before proceeding to the next steps.</p>
                                </div>
                            </div>

                            <!-- Help Item 5 -->
                            <div class="help-item border-b border-gray-100 pb-4">
                                <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleHelpAnswer('help5')" data-help="help5">
                                    <h4 class="font-medium text-gray-800">Tracking Your Case</h4>
                                    <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                                </div>
                                <div id="help5" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                    <ol class="list-decimal pl-5 mt-2 space-y-1">
                                        <li>Log in to your account</li>
                                        <li>Go to "View Cases" in your dashboard</li>
                                        <li>Find your case in the list and click on it</li>
                                        <li>View the detailed status, updates, and next steps</li>
                                    </ol>
                                    <p class="mt-3 text-sm text-gray-600">You'll receive notifications when there are
                                        updates to your case.</p>
                                </div>
                            </div>

                            <!-- Help Item 6 -->
                            <div class="help-item border-b border-gray-100 pb-4">
                                <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleHelpAnswer('help6')" data-help="help6">
                                    <h4 class="font-medium text-gray-800">Viewing Hearing Schedules</h4>
                                    <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                                </div>
                                <div id="help6" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                    <ol class="list-decimal pl-5 mt-2 space-y-1">
                                        <li>Log in to your account</li>
                                        <li>Navigate to "View Cases" and select your case</li>
                                        <li>Look for the "Hearing Schedule" section</li>
                                        <li>You can also receive SMS/email notifications for upcoming hearings</li>
                                    </ol>
                                    <p class="mt-3 text-sm text-gray-600">If you need to reschedule, contact the
                                        Barangay Secretary as soon as possible.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Help Category: Troubleshooting -->
                        <div id="troubleshooting">
                            <h3 class="text-lg font-semibold text-blue-700 border-b border-blue-100 pb-2">
                                Troubleshooting</h3>

                            <!-- Help Item 7 -->
                            <div class="help-item border-b border-gray-100 pb-4">
                                <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleHelpAnswer('help7')" data-help="help7">
                                    <h4 class="font-medium text-gray-800">Login Problems</h4>
                                    <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                                </div>
                                <div id="help7" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                    <p class="mb-2"><strong>Issue:</strong> Unable to log in to your account</p>
                                    <p class="mb-2"><strong>Solutions:</strong></p>
                                    <ul class="list-disc pl-5 text-gray-700 space-y-1">
                                        <li>Make sure you're using the correct email/username and password</li>
                                        <li>Check if Caps Lock is turned on</li>
                                        <li>Clear your browser cache and cookies</li>
                                        <li>Use the "Forgot Password" link to reset your password</li>
                                        <li>If problems persist, contact the support team</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Help Item 8 -->
                            <div class="help-item border-b border-gray-100 pb-4">
                                <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleHelpAnswer('help8')" data-help="help8">
                                    <h4 class="font-medium text-gray-800">File Upload Issues</h4>
                                    <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                                </div>
                                <div id="help8" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                    <p class="mb-2"><strong>Issue:</strong> Unable to upload documents</p>
                                    <p class="mb-2"><strong>Solutions:</strong></p>
                                    <ul class="list-disc pl-5 text-gray-700 space-y-1">
                                        <li>Ensure the file size is under 5MB</li>
                                        <li>Use only supported file types (PDF, JPG, PNG)</li>
                                        <li>Check your internet connection</li>
                                        <li>Try using a different browser</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Help Item 9 -->
                            <div class="help-item border-b border-gray-100 pb-4">
                                <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                    onclick="toggleHelpAnswer('help9')" data-help="help9">
                                    <h4 class="font-medium text-gray-800">Page Not Loading</h4>
                                    <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                                </div>
                                <div id="help9" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                    <p class="mb-2"><strong>Issue:</strong> Pages are not loading or loading
                                        incompletely</p>
                                    <p class="mb-2"><strong>Solutions:</strong></p>
                                    <ul class="list-disc pl-5 text-gray-700 space-y-1">
                                        <li>Refresh the page</li>
                                        <li>Clear your browser cache</li>
                                        <li>Check your internet connection</li>
                                        <li>Try using a different browser</li>
                                        <li>If using a mobile device, try on a desktop computer</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Help Category: Contact Support -->
                        <div id="contact-support">
                            <h3 class="text-lg font-semibold text-blue-700 border-b border-blue-100 pb-2">Contact
                                Support</h3>

                            <!-- Contact Information -->
                            <div class="mt-4 mb-6">
                                <h4 class="font-medium text-gray-800 mb-3">Support Contact Information</h4>
                                <div class="space-y-3">
                                    <div class="flex items-start">
                                        <i class="fas fa-phone-alt text-blue-600 mt-1 mr-3"></i>
                                        <div>
                                            <p class="text-gray-700 font-medium">Phone Support</p>
                                            <p class="text-gray-600">+63 (xxx) xxx-xxxx</p>
                                            <p class="text-sm text-gray-500">Monday to Friday, 8:00 AM - 5:00 PM</p>
                                        </div>
                                    </div>

                                    <div class="flex items-start">
                                        <i class="fas fa-envelope text-blue-600 mt-1 mr-3"></i>
                                        <div>
                                            <p class="text-gray-700 font-medium">Email Support</p>
                                            <p class="text-gray-600">support@bpamis.gov.ph</p>
                                            <p class="text-sm text-gray-500">We aim to respond within 24 hours</p>
                                        </div>
                                    </div>

                                    <div class="flex items-start">
                                        <i class="fas fa-map-marker-alt text-blue-600 mt-1 mr-3"></i>
                                        <div>
                                            <p class="text-gray-700 font-medium">Visit Us</p>
                                            <p class="text-gray-600">Barangay Hall, Barangay Panducot</p>
                                            <p class="text-gray-600">Calumpit, Bulacan</p>
                                            <p class="text-sm text-gray-500">Monday to Friday, 8:00 AM - 5:00 PM</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Help Section -->
                    <div class="mt-6 pt-4 border-t border-gray-200">
                        <h3 class="text-lg font-semibold text-blue-700 mb-3">Need More Help?</h3>
                        <p class="text-gray-600 text-sm">If you couldn't find your concerns and inquiries, please
                            contact us:</p>
                        <div class="flex flex-col mt-3 gap-2">
                            <a href="#" onclick="openContactModal(event);"
                                class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-envelope mr-2"></i> Contact Us Directly
                            </a>
                            <a href="#" onclick="closeHelpCenterModal(); setTimeout(() => openModal(), 10);"
                                class="inline-flex items-center justify-center px-4 py-2 bg-gray-100 text-gray-800 text-sm rounded-lg hover:bg-gray-200 transition-colors">
                                <i class="fas fa-calendar-alt mr-2"></i> Schedule an Appointment
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- Footer -->
    <?php
    include 'includes/footer.php';
    ?>

    <script>
        // Populate statistics from settings saved via Edit Barangay Info
        document.addEventListener('DOMContentLoaded', function () {
            try {
                const data = {
                    families: <?php echo json_encode($families); ?>,
                    seniors: <?php echo json_encode($seniors); ?>,
                    childrenAdults: <?php echo json_encode($childrenAdults); ?>,
                    childrenAdultsPercent: <?php echo json_encode($childrenAdultsPercent); ?>,
                    population: <?php echo json_encode($population); ?>,
                    pwdSenior: <?php echo json_encode($pwdSenior); ?>,
                    economic: <?php echo json_encode($economic); ?>,
                    womenChildren: <?php echo json_encode($womenChildren); ?>,
                    womenChildrenPercent: <?php echo json_encode($womenChildrenPercent); ?>,
                    bpamisAdoption: <?php echo json_encode($bpamisAdoption); ?>
                };
                const setNum = (id, val) => {
                    const el = document.getElementById(id);
                    if (el && typeof val === 'number' && !isNaN(val) && val > 0) {
                        el.textContent = val.toLocaleString('en-US');
                    }
                };
                setNum('adultCount', data.families);
                setNum('seniorCount', data.seniors);
                // For Children & Adults we show percentage
                const childrenEl = document.getElementById('childrenCount');
                if (childrenEl && typeof data.childrenAdultsPercent === 'number') {
                    childrenEl.textContent = data.childrenAdultsPercent.toFixed(2);
                }
                setNum('totalPopulation', data.population);
                setNum('pwdSeniorPopulation', data.pwdSenior);
                setNum('economicGroupPopulation', data.economic);
                // For Women + Children, show percentage
                const wcEl = document.getElementById('womenChildrenPopulation');
                if (wcEl && typeof data.womenChildrenPercent === 'number') {
                    wcEl.textContent = data.womenChildrenPercent.toFixed(2);
                }
                // Percent adoption display
                const adoptionEl = document.getElementById('bpamisAdoption');
                if (adoptionEl && typeof data.bpamisAdoption === 'number' && !isNaN(data.bpamisAdoption)) {
                    adoptionEl.textContent = data.bpamisAdoption.toFixed(2) + '%';
                }
            } catch (e) {
                // noop
            }
        });
    </script>
    <script>
        // Show auto-logout reminder when redirected with ?auto_logout=1 or ?timeout=true
        (function(){
            try {
                var params = new URLSearchParams(window.location.search);
                if (params.has('auto_logout') || params.has('timeout')) {
                    // remove param so it doesn't persist on refresh
                    params.delete('auto_logout'); params.delete('timeout');
                    var newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                    history.replaceState(null, '', newUrl);

                    // Create modern premium overlay
                    var overlay = document.createElement('div');
                    overlay.id = 'autoLogoutHomeModal';
                    overlay.style.position = 'fixed';
                    overlay.style.inset = '0';
                    overlay.style.display = 'flex';
                    overlay.style.alignItems = 'center';
                    overlay.style.justifyContent = 'center';
                    overlay.style.background = 'rgba(0, 0, 0, 0.5)';
                    overlay.style.backdropFilter = 'blur(4px)';
                    overlay.style.zIndex = '1000000';
                    overlay.style.animation = 'fadeIn 0.3s ease-out';

                    // Create modern premium card
                    var card = document.createElement('div');
                    card.style.background = 'linear-gradient(135deg, #ffffff 0%, #f8fafc 100%)';
                    card.style.padding = '0';
                    card.style.borderRadius = '16px';
                    card.style.boxShadow = '0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.05)';
                    card.style.maxWidth = '90vw';
                    card.style.width = '420px';
                    card.style.textAlign = 'center';
                    card.style.border = '1px solid rgba(148, 163, 184, 0.2)';
                    card.style.animation = 'slideUp 0.4s ease-out';
                    card.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';

                    // Add keyframe animations
                    var style = document.createElement('style');
                    style.textContent = `
                        @keyframes fadeIn {
                            from { opacity: 0; }
                            to { opacity: 1; }
                        }
                        @keyframes slideUp {
                            from { opacity: 0; transform: translateY(20px) scale(0.95); }
                            to { opacity: 1; transform: translateY(0) scale(1); }
                        }
                        #autoLogoutHomeModal .logout-icon-container {
                            width: 64px;
                            height: 64px;
                            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 20px;
                            box-shadow: 0 8px 16px rgba(239, 68, 68, 0.3);
                        }
                        #autoLogoutHomeModal .logout-icon-container svg {
                            width: 32px;
                            height: 32px;
                            color: #ffffff;
                        }
                        #autoLogoutHomeModal button {
                            font-size: 14px;
                            font-weight: 600;
                            padding: 12px 32px;
                            border-radius: 10px;
                            cursor: pointer;
                            transition: all 0.2s ease;
                            border: none;
                            outline: none;
                            background: linear-gradient(135deg, #0c9ced 0%, #0281d4 100%);
                            color: #ffffff;
                            box-shadow: 0 4px 12px rgba(12, 156, 237, 0.3);
                        }
                        #autoLogoutHomeModal button:hover {
                            box-shadow: 0 6px 16px rgba(12, 156, 237, 0.4);
                            transform: translateY(-1px);
                        }
                        #autoLogoutHomeModal button:active {
                            transform: scale(0.98);
                        }
                        @media (max-width: 480px) {
                            #autoLogoutHomeModal .logout-icon-container {
                                width: 56px;
                                height: 56px;
                                margin-bottom: 16px;
                            }
                            #autoLogoutHomeModal .logout-icon-container svg {
                                width: 28px;
                                height: 28px;
                            }
                            #autoLogoutHomeModal button {
                                font-size: 13px;
                                padding: 10px 24px;
                            }
                        }
                    `;
                    document.head.appendChild(style);

                    // Create content container
                    var content = document.createElement('div');
                    content.style.padding = '32px 28px';

                    // Create icon container with SVG
                    var iconContainer = document.createElement('div');
                    iconContainer.className = 'logout-icon-container';
                    iconContainer.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    `;

                    // Create title
                    var title = document.createElement('h3');
                    title.style.fontSize = '22px';
                    title.style.fontWeight = '700';
                    title.style.color = '#111827';
                    title.style.margin = '0 0 12px 0';
                    title.style.letterSpacing = '-0.02em';
                    title.textContent = 'Session Expired';

                    // Create message
                    var msg = document.createElement('p');
                    msg.style.fontSize = '15px';
                    msg.style.color = '#6b7280';
                    msg.style.lineHeight = '1.6';
                    msg.style.margin = '0 0 24px 0';
                    msg.textContent = 'You were logged out due to inactivity for security purposes. Please log in again to continue.';

                    // Create button
                    var ok = document.createElement('button');
                    ok.innerHTML = `
                        <span style="display: inline-flex; align-items: center; gap: 6px;">
                            <svg style="width: 16px; height: 16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Got It
                        </span>
                    `;

                    ok.addEventListener('click', function(){
                        card.style.animation = 'slideDown 0.3s ease-out';
                        overlay.style.animation = 'fadeOut 0.3s ease-out';
                        setTimeout(function() {
                            overlay.remove();
                        }, 300);
                    });

                    // Add slide down animation for exit
                    var exitStyle = document.createElement('style');
                    exitStyle.textContent = `
                        @keyframes slideDown {
                            from { opacity: 1; transform: translateY(0) scale(1); }
                            to { opacity: 0; transform: translateY(20px) scale(0.95); }
                        }
                        @keyframes fadeOut {
                            from { opacity: 1; }
                            to { opacity: 0; }
                        }
                    `;
                    document.head.appendChild(exitStyle);

                    // Assemble the modal
                    content.appendChild(iconContainer);
                    content.appendChild(title);
                    content.appendChild(msg);
                    content.appendChild(ok);
                    card.appendChild(content);
                    overlay.appendChild(card);
                    document.body.appendChild(overlay);
                }
            } catch (e) {
                console.error('auto-logout modal error', e);
            }
        })();
    </script>    <script>

        // Populate statistics from settings saved via Edit Barangay Info
        document.addEventListener('DOMContentLoaded', function () {
            try {
                const data = {
                    families: <?php echo json_encode($families); ?>,
                    seniors: <?php echo json_encode($seniors); ?>,
                    population: <?php echo json_encode($population); ?>,
                    pwdSenior: <?php echo json_encode($pwdSenior); ?>,
                    economic: <?php echo json_encode($economic); ?>,
                    childrenAdultsPercent: <?php echo json_encode($childrenAdultsPercent); ?>,
                    womenChildrenPercent: <?php echo json_encode($womenChildrenPercent); ?>,
                    bpamisAdoption: <?php echo json_encode($bpamisAdoption); ?>
                };

                const formatInt = (v) => (typeof v === 'number' && isFinite(v) && v > 0) ? v.toLocaleString('en-US') : '0';
                const formatPct = (v) => (typeof v === 'number' && isFinite(v) && v >= 0) ? v.toFixed(2) : '0.00';

                const setText = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };

                setText('adultCount', formatInt(data.families));
                setText('seniorCount', formatInt(data.seniors));
                setText('totalPopulation', formatInt(data.population));
                setText('pwdSeniorPopulation', formatInt(data.pwdSenior));
                setText('economicGroupPopulation', formatInt(data.economic));
                setText('childrenCount', formatPct(data.childrenAdultsPercent));
                setText('womenChildrenPopulation', formatPct(data.womenChildrenPercent));
                const adoptionEl = document.getElementById('bpamisAdoption');
                if (adoptionEl && typeof data.bpamisAdoption === 'number' && isFinite(data.bpamisAdoption)) {
                    adoptionEl.textContent = data.bpamisAdoption.toFixed(2) + '%';
                }
            } catch (e) { /* no-op */ }
        });
        function toggleCaseAssistant() {
            const frame = document.getElementById('caseAssistantFrame');
            frame.classList.toggle('hidden');

            if (!frame.classList.contains('hidden')) {
                // Show animation
                frame.style.opacity = '0';
                frame.style.transform = 'translateY(20px) scale(0.95)';
                setTimeout(() => {
                    frame.style.opacity = '1';
                    frame.style.transform = 'translateY(0) scale(1)';
                }, 50);
            }
            /* Key Values: hide description paragraphs and enforce 2-column layout on mobile */

            const style = document.createElement('style');
            style.innerHTML = `
            .key-values-grid p {
                display: none !important;
            }
            .key-values-grid h4 {
                font-size: 0.85rem !important;
                line-height: 1.05 !important;
            }
            .key-values-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 0.5rem !important;
            }
            /* Hide the entire Key Values section on mobile */
            .key-values-grid {
                display: none !important;
            }
        `;
            document.head.appendChild(style);
        }

        // Close case assistant when clicking outside
        document.addEventListener('click', function (event) {
            const frame = document.getElementById('caseAssistantFrame');
            const button = document.getElementById('chatbotButton');

            if (!frame.contains(event.target) && !button.contains(event.target) && !frame.classList.contains('hidden')) {
                toggleCaseAssistant();
            }
        });

        // Prevent clicks inside the iframe from closing it
        document.getElementById('caseAssistantFrame').addEventListener('click', function (event) {
            event.stopPropagation();
        });

    </script>

    <script>

        document.addEventListener('DOMContentLoaded', function () {
            const serviceCards = document.querySelectorAll('.service-card');

            serviceCards.forEach(card => {
                card.addEventListener('click', function () {
                    const container = this.closest('.service-container');
                    const details = container.querySelector('.service-details');
                    const chevron = this.querySelector('.fa-chevron-down');

                    // Toggle the details visibility
                    details.classList.toggle('hidden');
                    details.classList.toggle('show');

                    // Rotate the chevron icon
                    chevron.style.transform = details.classList.contains('hidden')
                        ? 'rotate(0deg)'
                        : 'rotate(180deg)';
                });
            });

            // Prevent button click from triggering card collapse
            const learnMoreBtns = document.querySelectorAll('.learn-more-btn');
            learnMoreBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            // Increased loading time to 3.5 seconds total
            setTimeout(() => {
                const loader = document.querySelector('.loader-wrapper');
                loader.classList.add('fade-out');

                // Increased fade-out transition to 1 second
                setTimeout(() => {
                    loader.remove();
                }, 1000); // Increased from 500ms to 1000ms
            }, 2500); // Increased from 2000ms to 2500ms
        });

        // Optional: Hide loader when all content is fully loaded
        window.addEventListener('load', function () {
            const loader = document.querySelector('.loader-wrapper');
            if (loader) {
                loader.classList.add('fade-out');
                setTimeout(() => {
                    loader.remove();
                }, 1000); // Increased fade-out time
            }
        });

        // Initialize and add the map
        function initMap() {
            // The location of Brgy Panducot (replace with actual coordinates)
            const panducot = { lat: 14.9144, lng: 120.7674 }; // Replace with actual coordinates

            // The map, centered at Brgy Panducot
            const map = new google.Maps.Map(document.getElementById("map"), {
                zoom: 15,
                center: panducot,
                mapId: 'YOUR_MAP_ID', // Optional: for styled maps
                streetViewControl: true,
                mapTypeControl: true,
                fullscreenControl: true,
            });

            // The marker, positioned at Brgy Panducot
            const marker = new google.Maps.Marker({
                position: panducot,
                map: map,
                title: "Barangay Panducot"
            });
        }

        // Note: The appointment modal functionality is included from schedule_appointment_modal.php

        function openContactModal(event) {
            event.preventDefault();
            const modal = document.getElementById('contactModal');
            const modalContent = document.getElementById('contactModalContent');

            modal.classList.remove('hidden');
            // Trigger reflow
            void modalContent.offsetWidth;
            modalContent.classList.remove('opacity-0', 'scale-95');
            modalContent.classList.add('opacity-100', 'scale-100');
        }

        function closeContactModal() {
            const modal = document.getElementById('contactModal');
            const modalContent = document.getElementById('contactModalContent');

            modalContent.classList.remove('opacity-100', 'scale-100');
            modalContent.classList.add('opacity-0', 'scale-95');

            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        document.getElementById('contactForm').addEventListener('submit', function (e) {
            e.preventDefault();
            // Add your form submission logic here
            alert('Message sent successfully!');
            closeContactModal();
        });

        // Close modal when clicking outside
        document.getElementById('contactModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeContactModal();
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            // Intersection Observer for fade-in animations
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, {
                threshold: 0.1, // Trigger when 10% of the element is visible
                rootMargin: '0px 0px -50px 0px' // Slightly offset trigger point
            });

            // Observe all fade-in elements
            document.querySelectorAll('.fade-in-element').forEach(element => {
                observer.observe(element);
            });
        });

        // Add this before the demographics chart initialization
        function animateValue(obj, start, end, duration) {
            // Check if the object exists before trying to animate it
            if (!obj) {
                console.log('Animation target element not found');
                return;
            }

            // Check if already animated (to prevent running multiple times)
            if (obj.dataset.animated === 'true') {
                return;
            }

            // Mark as animated
            obj.dataset.animated = 'true';

            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);

                // Handle decimal values for percentages
                if (Number.isInteger(end)) {
                    obj.innerHTML = Math.floor(progress * (end - start) + start).toLocaleString();
                } else {
                    // Format with 2 decimal places for non-integer values
                    const value = progress * (end - start) + start;
                    obj.innerHTML = value.toFixed(2);
                }

                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        // Create separate Intersection Observers for different demographics sections
        // Dynamic values injected from PHP for animations
        const safeNum = (v, d = 0) => (typeof v === 'number' && isFinite(v)) ? v : d;
        const BP_DATA = {
            families: safeNum(<?php echo json_encode($families); ?>, 0),
            seniors: safeNum(<?php echo json_encode($seniors); ?>, 0),
            childrenAdults: safeNum(<?php echo json_encode($childrenAdults); ?>, 0),
            childrenAdultsPercent: safeNum(<?php echo json_encode($childrenAdultsPercent); ?>, 0),
            population: safeNum(<?php echo json_encode($population); ?>, 0),
            pwdSenior: safeNum(<?php echo json_encode($pwdSenior); ?>, 0),
            womenChildren: safeNum(<?php echo json_encode($womenChildren); ?>, 0),
            womenChildrenPercent: safeNum(<?php echo json_encode($womenChildrenPercent); ?>, 0),
            economic: safeNum(<?php echo json_encode($economic); ?>, 0),
            adoption: safeNum(<?php echo json_encode($bpamisAdoption); ?>, 0)
        };

        const observerCommunityNumbers = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Get the counter elements specific to "Our Community by the Numbers" section
                    const womenChildren = document.getElementById('womenChildrenPopulation');
                    const pwdSenior = document.getElementById('pwdSeniorPopulation');
                    const economic = document.getElementById('economicGroupPopulation');
                    const adoption = document.getElementById('bpamisAdoption');

                    // Animate the counter values only when this specific section is visible
                    if (pwdSenior) animateValue(pwdSenior, 0, BP_DATA.pwdSenior, 2000);
                    if (womenChildren) animateValue(womenChildren, 0, BP_DATA.womenChildrenPercent, 2000);
                    if (economic) animateValue(economic, 0, BP_DATA.economic, 2000);
                    if (adoption) animateValue(adoption, 0, BP_DATA.adoption, 2000);

                    // Only disconnect this specific observer after animations start
                    // to ensure it only runs once per page view
                    observerCommunityNumbers.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.2, // Trigger when 20% of the element is visible
            rootMargin: '0px 0px -100px 0px' // Slightly adjust trigger point
        });

        // Create a separate observer for other demographics sections if needed
    const observerOtherDemographics = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Handle other demographics sections with different counter IDs
                    const totalPopulation = document.getElementById('totalPopulation');
                    const childrenCount = document.getElementById('childrenCount');
                    const adultCount = document.getElementById('adultCount');
                    const seniorCount = document.getElementById('seniorCount');

                    // Only animate if these elements exist (use dynamic values)
                    if (totalPopulation) animateValue(totalPopulation, 0, BP_DATA.population, 2000);
                    // Animate as percentage for Children & Adults
                    if (childrenCount) animateValue(childrenCount, 0, BP_DATA.childrenAdultsPercent, 2000);
                    if (adultCount) animateValue(adultCount, 0, BP_DATA.families, 2000);
                    if (seniorCount) animateValue(seniorCount, 0, BP_DATA.seniors, 2000);

                    // Unobserve after animation
                    observerOtherDemographics.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.2
        });

        // Start observing the specific demographics sections
        document.addEventListener('DOMContentLoaded', function () {
            // Find the specific section containing "Our Community by the Numbers"
            let communityByNumbersSection = null;

            // Use querySelector and check text content
            const allHeadings = document.querySelectorAll('h3.text-3xl');
            for (const heading of allHeadings) {
                if (heading.textContent.includes('Our Community by the Numbers')) {
                    communityByNumbersSection = heading.closest('.premium-stats-panel');
                    break;
                }
            }

            // If we found the section, observe it
            if (communityByNumbersSection) {
                observerCommunityNumbers.observe(communityByNumbersSection);
                console.log('Observing "Our Community by the Numbers" section');
            } else {
                console.log('Could not find "Our Community by the Numbers" section');
            }

            // Get other demographics sections if needed
            const demographicsCards = document.querySelectorAll('.demographics-card');
            demographicsCards.forEach(section => {
                // Check if this section contains the relevant counters
                // but not the "Our Community by the Numbers" heading
                const hasRelevantCounters = section.querySelector('#totalPopulation, #childrenCount, #adultCount, #seniorCount');
                let hasCommunityByNumbersHeading = false;

                const headings = section.querySelectorAll('h3');
                for (const heading of headings) {
                    if (heading.textContent.includes('Our Community by the Numbers')) {
                        hasCommunityByNumbersHeading = true;
                        break;
                    }
                }

                // Only observe if it contains different counter elements
                // and is not the "Our Community by the Numbers" section
                if (hasRelevantCounters && !hasCommunityByNumbersHeading) {
                    observerOtherDemographics.observe(section);
                    console.log('Observing other demographics section');
                }
            });
        });

        function openUserGuideModal(event) {
            event.preventDefault();
            const modal = document.getElementById('guideModal');
            const modalContent = document.getElementById('guideModalContent');

            modal.classList.remove('hidden');
            // Trigger reflow
            void modalContent.offsetWidth;
            modalContent.classList.remove('translate-x-full');
            modalContent.classList.add('translate-x-0');

            // Prevent body scrolling when modal is open
            document.body.classList.add('overflow-hidden');

            // Set focus on modal for accessibility
            setTimeout(() => {
                modalContent.focus();

                // Get all focusable elements
                focusableElements = modalContent.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );

                if (focusableElements.length > 0) {
                    firstFocusableElement = focusableElements[0];
                    lastFocusableElement = focusableElements[focusableElements.length - 1];
                    firstFocusableElement.focus();
                }

                // If a category is specified, scroll to it
                if (guideCategory) {
                    const categoryElement = document.getElementById(guideCategory);
                    if (categoryElement) {
                        categoryElement.scrollIntoView({ behavior: 'smooth' });
                    }
                }
            }, 100);
        }

        function closeGuideModal() {
            const modal = document.getElementById('guideModal');
            const modalContent = document.getElementById('guideModalContent');

            modalContent.classList.remove('translate-x-0');
            modalContent.classList.add('translate-x-full');

            setTimeout(() => {
                modal.classList.add('hidden');
                // Re-enable body scrolling
                document.body.classList.remove('overflow-hidden');

                // Restore focus to the element that was active before opening the modal
                if (window.lastGuideActiveElement) {
                    window.lastGuideActiveElement.focus();
                }
            }, 300);
        }

        // Close modal when clicking outside
        document.getElementById('guideModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeUserGuideModal();
            }
        });

        // Toggle Guide answers
        function toggleGuideAnswer(id) {
            const answer = document.getElementById(id);
            const icon = document.querySelector(`[data-guide="${id}"] i.fa-chevron-down`);

            if (answer.classList.contains('hidden')) {
                // Close other open guide items (accordion behavior)
                document.querySelectorAll('.guide-answer').forEach(item => {
                    if (item.id !== id && !item.classList.contains('hidden')) {
                        item.classList.add('hidden');
                        const otherIcon = document.querySelector(`[data-guide="${item.id}"] i`);
                        if (otherIcon) otherIcon.classList.remove('rotate-180');
                    }
                });

                answer.classList.remove('hidden');
                icon.classList.add('rotate-180');

                // Scroll the answer into view with some offset
                setTimeout(() => {
                    answer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            } else {
                answer.classList.add('hidden');
                icon.classList.remove('rotate-180');
            }
        }



    </script>

    <script>
        // Store focusable elements and last active element
        let helpFocusableElements = [];
        let helpFirstFocusableElement = null;
        let helpLastFocusableElement = null;

        // Help Center Side Panel Functions
        function openHelpCenterModal(event) {
            event.preventDefault();
            const modal = document.getElementById('helpCenterModal');
            const modalContent = document.getElementById('helpCenterModalContent');

            modal.classList.remove('hidden');
            // Trigger reflow
            void modalContent.offsetWidth;
            modalContent.classList.remove('translate-x-full');
            modalContent.classList.add('translate-x-0');

            // Prevent body scrolling when modal is open
            document.body.classList.add('overflow-hidden');

            // Set focus on modal for accessibility
            setTimeout(() => {
                modalContent.focus();

                // Get all focusable elements
                helpFocusableElements = modalContent.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );

                if (helpFocusableElements.length > 0) {
                    helpFirstFocusableElement = helpFocusableElements[0];
                    helpLastFocusableElement = helpFocusableElements[helpFocusableElements.length - 1];
                    helpFirstFocusableElement.focus();
                }

                // If a category is specified, scroll to it
                if (guideCategory) {
                    const categoryElement = document.getElementById(guideCategory);
                    if (categoryElement) {
                        categoryElement.scrollIntoView({ behavior: 'smooth' });
                    }
                }
            }, 100);
        }

        function closeHelpCenterModal() {
            const modal = document.getElementById('helpCenterModal');
            const modalContent = document.getElementById('helpCenterModalContent');

            modalContent.classList.remove('translate-x-0');
            modalContent.classList.add('translate-x-full');

            setTimeout(() => {
                modal.classList.add('hidden');
                // Re-enable body scrolling
                document.body.classList.remove('overflow-hidden');

                // Restore focus to the element that was active before opening the modal
                if (window.lastHelpActiveElement) {
                    window.lastHelpActiveElement.focus();
                }
            }, 300);
        }

        // Toggle help item answers
        function toggleHelpAnswer(id) {
            const answer = document.getElementById(id);
            const icon = document.querySelector(`[data-help="${id}"] i.fa-chevron-down`);

            if (answer.classList.contains('hidden')) {
                // Close other open help items (accordion behavior)
                document.querySelectorAll('.help-answer').forEach(item => {
                    if (item.id !== id && !item.classList.contains('hidden')) {
                        item.classList.add('hidden');
                        const otherIcon = document.querySelector(`[data-help="${item.id}"] i`);
                        if (otherIcon) otherIcon.classList.remove('rotate-180');
                    }
                });

                answer.classList.remove('hidden');
                icon.classList.add('rotate-180');

                // Scroll the answer into view with some offset
                setTimeout(() => {
                    answer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            } else {
                answer.classList.add('hidden');
                icon.classList.remove('rotate-180');
            }
        }

        // Document ready event listener
        document.addEventListener('DOMContentLoaded', function () {
            if (document.getElementById('helpCenterModal')) {
                // Add click listener on the backdrop element directly
                const backdrop = document.querySelector('#helpCenterModal .fixed.inset-0.bg-black');
                if (backdrop) {
                    backdrop.addEventListener('click', function () {
                        closeHelpCenterModal();
                    });
                }

                // Handle keyboard navigation for accessibility
                document.addEventListener('keydown', function (event) {
                    // Close modal when pressing Escape key
                    if (event.key === 'Escape' && !document.getElementById('helpCenterModal').classList.contains('hidden')) {
                        closeHelpCenterModal();
                        return;
                    }

                    // Trap focus inside modal when Tab key is pressed
                    if (event.key === 'Tab' && !document.getElementById('helpCenterModal').classList.contains('hidden')) {
                        // If shift key is also pressed and focus is on first element, move to last element
                        if (event.shiftKey && document.activeElement === helpFirstFocusableElement) {
                            event.preventDefault();
                            helpLastFocusableElement.focus();
                        }
                        // If focus is on last element, move to first element
                        else if (!event.shiftKey && document.activeElement === helpLastFocusableElement) {
                            event.preventDefault();
                            helpFirstFocusableElement.focus();
                        }
                    }
                });

                // Make help category links open the specific help category
                document.querySelectorAll('[data-help-category]').forEach(link => {
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        const category = this.getAttribute('data-help-category');
                        openHelpCenterModal(e, category);
                    });
                });

                // Handle form submission
                const supportForm = document.getElementById('supportForm');
                if (supportForm) {
                    supportForm.addEventListener('submit', function (e) {
                        e.preventDefault();
                        // Here you would typically send the form data to a server
                        alert('Your message has been sent. Our support team will contact you soon.');
                        // Reset the form
                        this.reset();
                    });
                }
            }
        });

        // Helper function to create help links
        function createHelpLink(text, category = null, classes = '') {
            return `<a href="#" data-help-category="${category}" class="help-link ${classes}">${text}</a>`;
        }

    </script>

    <!-- Google Maps JavaScript API -->
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initMap">
    </script>

    <?php include_once('../includes/case_assistant.php'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const infoContainers = document.querySelectorAll('.info-container');

            function closeAllTooltips() {
                document.querySelectorAll('.tooltip-content').forEach(t => {
                    t.classList.add('hidden');
                    t.style.left = '';
                    t.style.right = '';
                    t.style.top = '';
                    t.style.bottom = '';
                    t.style.transform = '';
                    // Remove arrow custom style
                    const arrow = t.querySelector('.tooltip-arrow');
                    if (arrow) {
                        arrow.style.left = '';
                        arrow.style.right = '';
                        arrow.style.top = '';
                        arrow.style.bottom = '';
                        arrow.style.transform = '';
                    }
                });
            }

            infoContainers.forEach(container => {
                const icon = container.querySelector('.info-icon');
                const tooltip = container.querySelector('.tooltip-content');
                const arrow = tooltip ? tooltip.querySelector('.tooltip-arrow') : null;

                if (icon && tooltip && arrow) {
                    icon.addEventListener('click', (event) => {
                        event.stopPropagation();
                        // Hide all other tooltips first
                        closeAllTooltips();
                        tooltip.classList.toggle('hidden');
                        if (!tooltip.classList.contains('hidden')) {
                            // Position the tooltip so it doesn't overflow
                            // Reset styles
                            tooltip.style.left = '';
                            tooltip.style.right = '';
                            tooltip.style.top = '';
                            tooltip.style.bottom = '';
                            tooltip.style.transform = '';

                            // Get bounding rectangles
                            const tooltipRect = tooltip.getBoundingClientRect();
                            const iconRect = icon.getBoundingClientRect();
                            const viewportWidth = window.innerWidth;
                            const viewportHeight = window.innerHeight;

                            // Always place tooltip below the icon
                            let left = iconRect.left + iconRect.width / 2 - tooltipRect.width / 2;
                            let top = iconRect.bottom + 12; // 12px margin below icon

                            // Prevent overflow left
                            if (left < 8) left = 8;
                            // Prevent overflow right
                            if (left + tooltipRect.width > viewportWidth - 8) {
                                left = viewportWidth - tooltipRect.width - 8;
                            }

                            // Set position
                            tooltip.style.position = 'fixed';
                            tooltip.style.left = left + 'px';
                            tooltip.style.top = top + 'px';
                            tooltip.style.right = '';
                            tooltip.style.bottom = '';
                            tooltip.style.transform = 'none';
                            tooltip.style.zIndex = 9999;

                            // Position the arrow at the top of the tooltip, pointing up
                            const iconCenterX = iconRect.left + iconRect.width / 2;
                            const tooltipLeft = left;
                            let arrowLeft = iconCenterX - tooltipLeft - 8; // 8 = half arrow width
                            // Clamp arrow within tooltip
                            if (arrowLeft < 8) arrowLeft = 8;
                            if (arrowLeft > tooltipRect.width - 16) arrowLeft = tooltipRect.width - 16;

                            arrow.style.top = '';
                            arrow.style.bottom = '100%';
                            arrow.style.transform = 'translateY(2px) rotate(45deg)';
                            arrow.style.left = arrowLeft + 'px';
                            arrow.style.right = '';
                        } else {
                            // Reset styles if hidden
                            tooltip.style.left = '';
                            tooltip.style.right = '';
                            tooltip.style.top = '';
                            tooltip.style.bottom = '';
                            tooltip.style.transform = '';
                            tooltip.style.position = '';
                            tooltip.style.zIndex = '';
                            if (arrow) {
                                arrow.style.left = '';
                                arrow.style.right = '';
                                arrow.style.top = '';
                                arrow.style.bottom = '';
                                arrow.style.transform = '';
                            }
                        }
                    });
                }
            });

            // Close tooltips when clicking outside
            window.addEventListener('click', function (e) {
                let clickedTooltip = false;
                document.querySelectorAll('.info-container').forEach(container => {
                    if (container.contains(e.target)) {
                        clickedTooltip = true;
                    }
                });
                if (!clickedTooltip) {
                    closeAllTooltips();
                }
            });

            // Also close on scroll or resize (to avoid misplaced tooltips)
            window.addEventListener('scroll', closeAllTooltips, true);
            window.addEventListener('resize', closeAllTooltips);
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var wrapper = document.getElementById('demographicsWrapper');
            var card = wrapper ? wrapper.querySelector('.demographics-card') : null;
            if (wrapper && card) {
                wrapper.addEventListener('mouseenter', function () {
                    card.classList.remove('demographics-collapsed');
                });
                wrapper.addEventListener('mouseleave', function () {
                    card.classList.add('demographics-collapsed');
                });
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Existing fade-in logic for fade-in-element
            var fadeEls = document.querySelectorAll('.fade-in-element, .slide-up-element, .flip-in-element');
            function checkFadeIn() {
                var windowBottom = window.innerHeight + window.scrollY;
                fadeEls.forEach(function (el) {
                    var rect = el.getBoundingClientRect();
                    var elTop = rect.top + window.scrollY;
                    if (windowBottom > elTop + 60) {
                        el.classList.add('visible');
                    }
                });
            }
            window.addEventListener('scroll', checkFadeIn);
            checkFadeIn();
        });
    </script>

    <script>
        // Add this to your existing JavaScript section
        document.addEventListener('DOMContentLoaded', function () {
            // Magnetic effect for cards
            const magneticCards = document.querySelectorAll('.magnetic-card');

            magneticCards.forEach(card => {
                card.addEventListener('mousemove', (e) => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;

                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;

                    const rotateX = (y - centerY) / 10;
                    const rotateY = (centerX - x) / 10;

                    card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(10px)`;
                });

                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateZ(0px)';
                });
            });

            // Enhanced scroll animations
            const scrollObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

            // Observe all scroll-animated elements
            document.querySelectorAll('.scroll-animate, .slide-in-left, .slide-in-right, .scale-in, .flip-in, .text-reveal').forEach(el => {
                scrollObserver.observe(el);
            });
        });
    </script>

    <!-- Include Schedule Appointment Modal -->
    <?php include('includes/schedule_appointment_modal.php'); ?>

    <!-- Marquee Animation Enhancement for Mobile -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Get all marquee icon elements
            const marqueeIcons = document.querySelectorAll('.marquee-icon');
            const marqueeTrack = document.querySelector('.marquee-track');

            // Touch device detection
            const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

            // Fix for continuous movement - ensure the track width is calculated correctly
            function fixMarqueeWidth() {
                if (window.innerWidth <= 768) {
                    // Get total width of the track content for mobile
                    const trackItems = document.querySelectorAll('.marquee-track > div');
                    if (trackItems.length > 0) {
                        let totalWidth = 0;
                        trackItems.forEach(item => {
                            // Only count the first set of items (before duplicates)
                            if (totalWidth < window.innerWidth * 2) {
                                totalWidth += item.offsetWidth + parseFloat(getComputedStyle(item).marginLeft) +
                                    parseFloat(getComputedStyle(item).marginRight);
                            }
                        });
                        // Force the track to be at least twice the container width
                        marqueeTrack.style.minWidth = (totalWidth) + 'px';
                    }
                }
            }

            // Call the fix on load and resize
            fixMarqueeWidth();
            window.addEventListener('resize', fixMarqueeWidth);

            if (isTouchDevice) {
                // Add touch animation to the icons
                marqueeIcons.forEach((icon, index) => {
                    // Add a touch event listener
                    icon.addEventListener('touchstart', function () {
                        this.style.background = '#2563eb';
                        this.style.color = '#fff';
                        this.style.transform = 'scale(1.08)';
                        this.style.boxShadow = '0 12px 32px 0 rgba(37, 99, 235, 0.25)';

                        // Reset after a delay
                        setTimeout(() => {
                            this.style.background = '';
                            this.style.color = '';
                            this.style.transform = '';
                            this.style.boxShadow = '';
                        }, 500);
                    });
                });
            }

            // For mobile devices, activate some icons animation randomly to draw attention
            if (window.innerWidth <= 768) {
                // Function to randomly pulse an icon
                function randomPulse() {
                    const randomIndex = Math.floor(Math.random() * marqueeIcons.length);
                    const icon = marqueeIcons[randomIndex];

                    // Add temporary highlight
                    icon.style.background = '#dbeafe';
                    icon.style.transform = 'scale(1.05)';

                    // Remove after animation
                    setTimeout(() => {
                        icon.style.background = '';
                        icon.style.transform = '';
                    }, 1000);

                    // Schedule next pulse
                    setTimeout(randomPulse, 3000 + Math.random() * 3000);
                }

                // Start random pulses after a delay
                setTimeout(randomPulse, 3000);
            }
        });
    </script>

    <!-- Ensure marquee animation works properly -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Backup script to ensure the marquee animation works properly
            // This runs in addition to marquee.js for redundancy
            setTimeout(function () {
                const marqueeTrack = document.querySelector('.marquee-track');
                if (marqueeTrack) {
                    // Make sure the animation is applied correctly
                    const containerWidth = marqueeTrack.parentElement.offsetWidth;
                    const trackWidth = marqueeTrack.scrollWidth;

                    // Ensure the track is wide enough (at least 3x container width)
                    if (trackWidth < containerWidth * 3) {
                        marqueeTrack.style.width = (containerWidth * 3) + 'px';
                    }

                    // Apply a longer duration for smoother animation
                    marqueeTrack.style.animationDuration = '50s';

                    console.log('Marquee backup fix applied');
                }
            }, 1000); // Run after a delay to ensure the page is fully loaded
        });
    </script>
    <!-- Optional: auto-refresh when settings change in another tab -->
    <script>
        window.addEventListener('storage', function (e) {
            if (e.key === 'bpamis_settings_updated') {
                // Refresh the page to fetch latest settings from server
                location.reload();
            }
        });
    </script>
</body>

</html>