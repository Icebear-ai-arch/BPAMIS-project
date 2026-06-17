<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BPAMIS - Barangay Panducot Adjudication Management Information System</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="styles/auth.css">
  <link rel="stylesheet" href="assets/css/styles.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
  <style>
    body {
      color: black;
    }

    .hero-section {
      background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('assets/images/brgyhall.png');
      background-size: cover;
      background-position: center;
      height: 600px;
      position: relative;
      overflow: hidden;
    }

    .hero-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: inherit;
      filter: blur(8px);
      -webkit-filter: blur(8px);
      z-index: 0;
    }

    .hero-section .container {
      position: relative;
      z-index: 1;
    }

    /* Mobile responsive styles for hero section */
    @media (max-width: 768px) {
      .hero-section {
        height: auto;
        padding-top: 8rem;
        padding-bottom: 6rem;
      }

      .text-6xl,
      .text-7xl {
        font-size: 2.5rem;
        line-height: 1.2;
      }

      .text-2xl,
      .text-3xl {
        font-size: 1rem;
      }

      .text-sm {
        font-size: 0.7rem;
      }

      .mb-8 {
        margin-bottom: 1.5rem;
      }

      .py-24 {
        padding-top: 4rem;
        padding-bottom: 4rem;
      }

      .w-24,
      .h-24 {
        width: 5rem;
        height: 5rem;
      }

      /* Disable fade animations on mobile for hero section */
      .hero-section .fade-in-element {
        opacity: 1 !important;
        transform: none !important;
        transition: none !important;
        animation: none !important;
      }
    }

    @media (max-width: 640px) {

      .text-6xl,
      .text-7xl {
        font-size: 2rem;
      }

      .text-2xl,
      .text-3xl {
        font-size: 1rem;
      }

      .text-xl {
        font-size: 20px;
      }

      .w-24,
      .h-24 {
        width: 4rem;
        height: 4rem;
      }

      .mb-6 {
        margin-bottom: 1rem;
      }

      .mb-8 {
        margin-bottom: 1.25rem;
      }

      .py-24 {
        padding-top: 3rem;
        padding-bottom: 3rem;
      }
    }

    @media (max-width: 480px) {

      .text-6xl,
      .text-7xl {
        font-size: 1.8rem;
      }

      .text-2xl,
      .text-3xl {
        font-size: 1rem;
      }

      .py-24 {
        padding-top: 2.5rem;
        padding-bottom: 2.5rem;
      }

      .container {
        padding-left: 1rem;
        padding-right: 1rem;
      }

      .w-24,
      .h-24 {
        width: 3.5rem;
        height: 3.5rem;
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
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: #fff;
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      transition: opacity 0.5s ease-out;
    }

    .loader {
      position: relative;
      width: 120px;
      height: 120px;
    }

    .loader-circle {
      position: absolute;
      width: 100%;
      height: 100%;
      border: 4px solid transparent;
      border-top-color: #2563eb;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    .loader-circle:nth-child(2) {
      border-top-color: transparent;
      border-right-color: #2563eb;
      animation-duration: 0.8s;
    }

    .loader-circle:nth-child(3) {
      border-top-color: transparent;
      border-right-color: transparent;
      border-bottom-color: #2563eb;
      animation-duration: 0.6s;
    }

    .loader-logo {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 60px;
      height: 60px;
      opacity: 0;
      animation: fadeIn 0.5s ease-out forwards 0.5s;
    }

    @keyframes spin {
      from {
        transform: rotate(0deg);
      }

      to {
        transform: rotate(360deg);
      }
    }

    @keyframes fadeIn {
      to {
        opacity: 1;
      }
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

    .about-hero {
      position: relative;
      overflow: hidden;
    }

    .about-hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('assets/images/brgyhall.png') center/cover no-repeat;
      filter: blur(8px);
      -webkit-filter: blur(8px);
      transform: scale(1.1);
      z-index: 0;
    }

    .about-hero::after {
      content: '';
      position: absolute;
      inset: 0;
      /* background: rgba(255, 255, 255, 0.9); Changed from gradient to solid white with opacity */
      z-index: 1;
    }

    .content-wrapper {
      position: relative;
      z-index: 2;
      /* Increased z-index to appear above both pseudo-elements */
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(229, 231, 235, 0.5);
      opacity: 0;
      animation: fadeIn 0.8s ease-out 0.5s forwards;
    }

    .text-block {
      line-height: 1.8;
      color: rgba(39, 39, 39, 0.83);
    }

    /* Add these styles in your existing <style> tag */
    .paragraph-wrapper {
      position: relative;
      padding-left: 80px;
      /* Space for icon */
      margin-bottom: 2rem;
    }

    .section-icon {
      position: absolute;
      left: 0;
      top: 0;
      width: 50px;
      height: 50px;
      background: white;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      animation: slideIn 0.8s ease-out forwards;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateX(-20px);
      }

      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    .section-icon:nth-child(1) {
      animation-delay: 0.2s;
    }

    .section-icon:nth-child(2) {
      animation-delay: 0.4s;
    }

    .section-icon:nth-child(3) {
      animation-delay: 0.6s;
    }

    .section-icon:nth-child(4) {
      animation-delay: 0.8s;
    }

    .section-icon:nth-child(5) {
      animation-delay: 1.0s;
    }

    .section-icon:nth-child(6) {
      animation-delay: 1.2s;
    }

    .section-icon i {
      font-size: 24px;
      color: #2563eb;
    }

    @keyframes gradientFlow {
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

    .text-gradient {
      background-size: 200% auto;
      animation: gradientFlow 5s ease infinite;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .animate-fade-in {
      animation: fadeIn 1s ease-out forwards;
    }

    .text-transparent.bg-clip-text {
      -webkit-background-clip: text;
      background-clip: text;
    }

    @keyframes logoPulse {
      0% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.1);
      }

      100% {
        transform: scale(1);
      }
    }

    .logo-pulse {
      animation: logoPulse 2s ease-in-out infinite;
      transform-origin: center;
      transition: all 0.3s ease;
    }

    .logo-pulse:hover {
      animation-play-state: paused;
      transform: scale(1.15);
    }

    .glow-effect {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      background: rgba(37, 99, 235, 0.1);
      filter: blur(8px);
      animation: glowPulse 3s ease-in-out infinite;
      position: absolute;
      inset: 0;
    }

    @keyframes glowPulse {
      0% {
        transform: scale(1);
        opacity: 0.5;
        box-shadow: 0 0 20px 10px rgba(37, 99, 235, 0.2);
      }

      50% {
        transform: scale(1.1);
        opacity: 0.8;
        box-shadow: 0 0 40px 20px rgba(37, 99, 235, 0.4);
      }

      100% {
        transform: scale(1);
        opacity: 0.5;
        box-shadow: 0 0 20px 10px rgba(37, 99, 235, 0.2);
      }
    }

    /* Update existing logo-pulse animation to work with glow */
    .logo-pulse {
      position: relative;
      z-index: 2;
    }

    .logo-pulse:hover+.glow-effect {
      animation-play-state: paused;
      transform: translate(-50%, -50%) scale(1.2);
      box-shadow: 0 0 60px 30px rgba(37, 99, 235, 0.6);
    }

    .slide-in-up {
      opacity: 0;
      transform: translateY(60px);
      animation: slideInUp 1s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
      animation-delay: 0.2s;
    }

    @keyframes slideInUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .slide-in-left {
      opacity: 0;
      transform: translateX(-60px);
      transition: all 1s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    .slide-in-right {
      opacity: 0;
      transform: translateX(60px);
      transition: all 1s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    .scroll-animate {
      opacity: 0;
      transform: translateY(30px);
      transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    .scroll-animate.visible {
      opacity: 1;
      transform: translateY(0);
    }

    /* Enhanced title animations */
    .title-enhanced {
      background: linear-gradient(45deg, #2563eb, #7c3aed, #2563eb);
      background-size: 300% 300%;
      animation: gradientShift 3s ease infinite;
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .subtitle-enhanced {
      opacity: 0;
      transform: translateY(30px);
      animation: slideUpFade 1s ease-out 0.5s forwards;
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

    @keyframes slideUpFade {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Premium VMGO Section Styles */
    .vmgo-card {
      transition: all 0.4s ease;
      overflow: hidden;
      position: relative;
      border: 1px solid rgba(59, 130, 246, 0.1);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
      height: 100%;
      display: flex;
      flex-direction: column;
      transform: translateY(0);
      background: #fff;
    }

    .vmgo-card-inner {
      position: relative;
      z-index: 2;
      flex: 1;
      display: flex;
      flex-direction: column;
      height: 100%;
      padding: 1.5rem;
    }

    .vmgo-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 15px 30px rgba(37, 99, 235, 0.1);
      border-color: rgba(59, 130, 246, 0.3);
    }

    .vmgo-card-header {
      position: relative;
      overflow: hidden;
      border-radius: 8px 8px 0 0;
      padding: 1rem;
    }

    @media (min-width: 768px) {
      .vmgo-card-header {
        padding: 1.5rem;
      }
    }

    .vmgo-card:hover .vmgo-card-inner {
      transform: translateY(-5px);
    }

    .vmgo-glow-bg {
      position: absolute;
      width: 150px;
      height: 150px;
      border-radius: 100%;
      background: radial-gradient(circle, rgba(59, 130, 246, 0.2) 0%, rgba(37, 99, 235, 0) 70%);
      filter: blur(20px);
      opacity: 0;
      transition: all 0.6s ease;
      z-index: 1;
    }

    .vision-card .vmgo-card-header {
      background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%);
    }

    .vision-card:hover .vmgo-glow-bg {
      opacity: 0.8;
      transform: scale(1.5);
      background: radial-gradient(circle, rgba(59, 130, 246, 0.3) 0%, rgba(37, 99, 235, 0) 70%);
    }

    .mission-card .vmgo-card-header {
      background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);
    }

    .mission-card:hover .vmgo-glow-bg {
      opacity: 0.8;
      transform: scale(1.5);
      background: radial-gradient(circle, rgba(59, 130, 246, 0.3) 0%, rgba(37, 99, 235, 0) 70%);
    }

    .goals-card .vmgo-card-header {
      background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);
    }

    .goals-card:hover .vmgo-glow-bg {
      opacity: 0.8;
      transform: scale(1.5);
      background: radial-gradient(circle, rgba(59, 130, 246, 0.3) 0%, rgba(37, 99, 235, 0) 70%);
    }

    .vmgo-corner-accent {
      position: absolute;
      width: 80px;
      height: 80px;
      z-index: 1;
      opacity: 0.7;
      transition: all 0.4s ease;
    }

    .vmgo-corner-top-right {
      top: -20px;
      right: -20px;
    }

    .vmgo-corner-blue {
      background: radial-gradient(circle, rgba(37, 99, 235, 0.3) 0%, rgba(37, 99, 235, 0) 70%);
    }

    .vmgo-card:hover .vmgo-corner-accent {
      transform: scale(1.5);
      opacity: 0.9;
    }

    .vmgo-icon-wrapper {
      width: 60px;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.2);
      margin-bottom: 1rem;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .vmgo-card:hover .vmgo-icon-wrapper {
      transform: rotate(5deg) scale(1.1);
      box-shadow: 0 8px 15px rgba(37, 99, 235, 0.2);
    }

    .vmgo-icon {
      font-size: 1.5rem;
      color: white;
    }

    /* Fade in animations */
    .fade-in {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.8s ease, transform 0.8s ease;
    }

    .fade-in.visible {
      opacity: 1;
      transform: translateY(0);
    }

    .fade-in-delay-100 {
      transition-delay: 0.1s;
    }

    .fade-in-delay-200 {
      transition-delay: 0.2s;
    }

    .fade-in-delay-300 {
      transition-delay: 0.3s;
    }

    .fade-in-delay-400 {
      transition-delay: 0.4s;
    }

    .fade-in-left {
      opacity: 0;
      transform: translateX(-50px);
      transition: opacity 0.8s ease, transform 0.8s ease;
    }

    .fade-in-left.visible {
      opacity: 1;
      transform: translateX(0);
    }

    .fade-in-right {
      opacity: 0;
      transform: translateX(50px);
      transition: opacity 0.8s ease, transform 0.8s ease;
    }

    .fade-in-right.visible {
      opacity: 1;
      transform: translateX(0);
    }

    .logo-pulse:hover {
      animation-play-state: paused;
      transform: scale(1.15);
    }

    .glow-effect {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      background: rgba(37, 99, 235, 0.1);
      filter: blur(8px);
      animation: glowPulse 3s ease-in-out infinite;
      position: absolute;
      inset: 0;
    }

    /* History Section Styles */
    .history-section {
      overflow: hidden;
      position: relative;
    }

    .bg-gradient-180 {
      background: linear-gradient(180deg, #fff 10%, #2563eb 30%, #fff 100%);
      opacity: 0.9;
    }

    /* Diagonal Line Texture */
    .diagonal-lines {
      background-image: repeating-linear-gradient(-45deg,
          rgba(59, 130, 246, 0.05) 0px,
          rgba(59, 130, 246, 0.05) 1px,
          transparent 1px,
          transparent 10px);
      pointer-events: none;
      opacity: 0.7;
    }

    /* Grid Pattern Overlay */
    .grid-pattern {
      background-image:
        linear-gradient(rgba(37, 99, 235, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(37, 99, 235, 0.03) 1px, transparent 1px);
      background-size: 20px 20px;
      pointer-events: none;
      opacity: 0.8;
    }

    /* Shadow Overlay */
    .shadow-overlay {
      background: radial-gradient(ellipse at top, rgba(255, 255, 255, 0.3) 0%, rgba(255, 255, 255, 0) 70%),
        radial-gradient(ellipse at bottom, rgba(37, 99, 235, 0.2) 0%, rgba(37, 99, 235, 0) 70%);
      pointer-events: none;
      opacity: 0.8;
      box-shadow: inset 0 0 30px rgba(0, 0, 0, 0.1);
      animation: shiftShadow 20s ease-in-out infinite alternate;
    }

    /* Additional Line Effects */
    .history-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.4), transparent);
      z-index: 2;
      box-shadow: 0 2px 10px rgba(59, 130, 246, 0.1);
    }

    .history-section::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.4), transparent);
      z-index: 2;
      box-shadow: 0 -2px 10px rgba(59, 130, 246, 0.1);
    }

    @keyframes shiftShadow {
      0% {
        opacity: 0.7;
        background-position: 0% 0%;
      }

      100% {
        opacity: 0.9;
        background-position: 100% 100%;
      }
    }

    /* Timeline Enhancements */
    .timeline-container {
      position: relative;
      z-index: 1;
    }

    .timeline-line {
      width: 2px;
      background: linear-gradient(to bottom, rgba(37, 99, 235, 0.2), rgba(37, 99, 235, 0.8), rgba(37, 99, 235, 0.2));
      box-shadow: 0 0 8px rgba(37, 99, 235, 0.4);
    }

    /* Timeline Card Styling */
    .history-section .bg-white {
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(4px);
      border: 1px solid rgba(255, 255, 255, 0.6);
      box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
      position: relative;
      overflow: hidden;
    }

    .history-section .bg-white::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(to right, #2563eb, #3b82f6, #2563eb);
      z-index: 1;
    }

    .history-section .bg-white:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 30px rgba(31, 38, 135, 0.15);
      transition: all 0.3s ease;
    }

    /* Target all timeline dots */
    .timeline-items .absolute.left-1\/2.transform.-translate-x-1\/2.w-4.h-4 {
      width: 16px !important;
      height: 16px !important;
      filter: drop-shadow(0 0 6px rgba(37, 99, 235, 0.8));
      transition: all 0.3s ease;
      animation: pulse-dot 2s infinite;
    }

    @keyframes pulse-dot {
      0% {
        transform: translateX(-50%) scale(1);
        box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4);
      }

      70% {
        transform: translateX(-50%) scale(1.1);
        box-shadow: 0 0 0 6px rgba(37, 99, 235, 0);
      }

      100% {
        transform: translateX(-50%) scale(1);
        box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
      }
    }

    /* Premium Components Styles */
    .text-shadow-sm {
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .shadow-glow {
      box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
    }

    .pulse-slow {
      animation: pulseSlow 5s ease-in-out infinite;
    }

    @keyframes pulseSlow {

      0%,
      100% {
        opacity: 0.2;
        transform: scale(0.95);
      }

      50% {
        opacity: 0.4;
        transform: scale(1.05);
      }
    }

    .pulse-subtle {
      animation: pulseSubtle 2s ease-in-out infinite;
    }

    @keyframes pulseSubtle {

      0%,
      100% {
        opacity: 0.9;
      }

      50% {
        opacity: 1;
      }
    }

    .shimmer-effect {
      animation: shimmer 8s linear infinite;
    }

    .shimmer-effect-reverse {
      animation: shimmerReverse 8s linear infinite;
    }

    @keyframes shimmer {
      0% {
        transform: rotate(0deg) translate(0, 0) scale(1);
        opacity: 0.1;
      }

      25% {
        transform: rotate(90deg) translate(5px, 5px) scale(1.1);
        opacity: 0.3;
      }

      50% {
        transform: rotate(180deg) translate(0, 0) scale(1);
        opacity: 0.1;
      }

      75% {
        transform: rotate(270deg) translate(-5px, -5px) scale(0.9);
        opacity: 0.3;
      }

      100% {
        transform: rotate(360deg) translate(0, 0) scale(1);
        opacity: 0.1;
      }
    }

    @keyframes shimmerReverse {
      0% {
        transform: rotate(360deg) translate(0, 0) scale(1);
        opacity: 0.1;
      }

      25% {
        transform: rotate(270deg) translate(5px, 5px) scale(1.1);
        opacity: 0.3;
      }

      50% {
        transform: rotate(180deg) translate(0, 0) scale(1);
        opacity: 0.1;
      }

      75% {
        transform: rotate(90deg) translate(-5px, -5px) scale(0.9);
        opacity: 0.3;
      }

      100% {
        transform: rotate(0deg) translate(0, 0) scale(1);
        opacity: 0.1;
      }
    }

    .animate-bounce-subtle {
      animation: bounceSubtle 2s ease-in-out infinite;
    }

    @keyframes bounceSubtle {

      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-3px);
      }
    }
  </style>
</head>

<body>
  <!-- Add this at the very top of the body -->
  <div class="loader-wrapper">
    <div class="loader">
      <div class="loader-circle"></div>
      <div class="loader-circle"></div>
      <div class="loader-circle"></div>

    </div>
  </div>

  <?php include_once('includes/bpamis_nav.php'); ?>

  <!-- Title Section -->
  <section class="py-24"
    style="background: linear-gradient(180deg, #fff 10%,rgba(37, 100, 235, 0.83) 30%, #fff 100%); margin-top: 4rem;">
    <div class="container mx-auto px-4">
      <div class="max-w-4xl mx-auto text-center opacity-0 animate-fade-in">
        <!-- Logo -->
        <div class="mb-8 relative flex justify-center items-center">
          <div class="absolute w-24 h-24">
            <div class="glow-effect absolute"></div>
          </div>
          <img src="assets/images/logo.png" alt="BPAMIS Logo" class="w-24 h-24 logo-pulse relative z-10">
        </div>

        <!-- Acronym -->
        <div class="mb-6">
          <h1 class="text-6xl md:text-7xl font-bold bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600
             text-1xl font-bold bg-gradient-to-r from-blue-800 to-blue-600 bg-clip-text text-transparent">
            BPAMIS
          </h1>
        </div>

        <!-- Separator Line -->
        <div class="relative my-8">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-gray-200"></div>
          </div>
          <div class="relative flex justify-center">
            <span
              class="px-4 bg-gradient-to-r from-blue-50 to-indigo-50 text-sm text-gray-500 uppercase tracking-widest">
              Empowering Communities
            </span>
          </div>
        </div>

        <!-- Full Name -->
        <h2 class="text-2xl md:text-3xl text-gray-800 font-light leading-relaxed">
          Barangay Panducot Adjudication
          <br>
          Management Information System
        </h2>
      </div>
    </div>

    <!-- WHAT BPAMIS STANDS FOR & PURPOSE -->
    <div class="container mx-auto px-4 max-w-4xl grid md:grid-cols-2 gap-8" style="margin-top: 2rem;">
      <div
        class="bg-white/90 backdrop-blur-md rounded-2xl shadow-lg p-6 md:p-8 border border-white/30 flex flex-col justify-center fade-in-left">
        <h2 class="text-lg md:text-2xl font-bold text-blue-800 mb-3">What is BPAMIS?</h2>
        <p class="text-sm md:text-base text-gray-800 mb-2">BPAMIS stands for <strong>Barangay Panducot Adjudication
            Management Information
            System</strong>. It is a digital platform designed to modernize and streamline the management of community
          disputes, case filing, and public service delivery in Barangay Panducot.</p>
        <h3 class="text-base md:text-xl font-semibold text-blue-700 mt-6 mb-2">Purpose</h3>
        <p class="text-sm md:text-base text-gray-800">The system aims to make justice more accessible, efficient, and
          transparent for all
          residents by digitizing case management, document requests, and mediation processes.</p>
      </div>
      <div
        class="bg-white/90 backdrop-blur-md rounded-2xl shadow-lg p-6 md:p-8 border border-white/30 flex flex-col justify-center fade-in-right">
        <h2 class="text-lg md:text-2xl font-bold text-blue-800 mb-3">Development Overview</h2>
        <p class="text-sm md:text-base text-gray-800">BPAMIS was developed in response to the growing need for a more
          efficient and
          accountable way to handle barangay-level disputes and services. It leverages modern technology to support
          barangay officials and empower residents, ensuring that justice and governance keep pace with the community's
          progress.</p>
      </div>
    </div>
  </section>


  <!-- HISTORY OF BARANGAY PANDUCOT -->
  <section class="py-16 history-section relative">
    <!-- Diagonal Line Texture Background -->
    <div class="absolute inset-0 diagonal-lines"></div>
    <!-- Pattern Texture Layer -->
    <div class="absolute inset-0 grid-pattern"></div>


    <div class="container mx-auto px-4 max-w-6xl relative z-10">
      <!-- Enhanced Title Section -->
      <div class="text-center mb-16">
        <div class="relative inline-block">
          <h2 class="text-3xl md:text-5xl font-bold mb-4 title-enhanced text-shadow-sm relative z-10" data-aos="fade-up"
            data-aos-duration="1000">
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-900 to-blue-600">
              History of Barangay Panducot
            </span>
          </h2>
          <div class="absolute w-full h-3 bg-blue-200/30 bottom-5 left-0 transform -rotate-1"></div>
        </div>

        <p class="text-sm md:text-xl text-gray-700 font-medium italic title-enhanced" data-aos="fade-up"
          data-aos-delay="300" data-aos-duration="1000">
          A Journey Through Time and Community
        </p>
      </div>

      <!-- Timeline Container -->
      <div class="relative timeline-container">
        <!-- Vertical Timeline Line (Desktop only) -->
        <div class="hidden md:block absolute md:left-1/2 transform md:-translate-x-px h-full timeline-line fade-in">
        </div>

        <!-- Timeline Items -->
        <div class="space-y-8 md:space-y-16 timeline-items">

          <!-- Pre-Spanish Era -->
          <div class="relative flex items-center">
            <!-- Desktop only: Timeline dot -->
            <div
              class="hidden md:block absolute md:left-1/2 transform -translate-x-1/2 w-4 h-4 bg-blue-600 rounded-full border-4 border-white shadow-lg z-10">
            </div>

            <!-- Mobile: Single column layout, Desktop: Two column layout -->
            <div class="w-full md:flex-1 pr-0 md:pr-4 md:pr-8 fade-in-right">
              <div
                class="relative bg-white rounded-lg md:rounded-xl shadow-md md:shadow-lg p-3 md:p-4 md:p-8 mr-0 md:mr-4 md:mr-8 hover:shadow-lg md:hover:shadow-xl transition-shadow duration-300">
                <!-- Mobile timeline line at bottom -->
                <div class="absolute bottom-0 left-1/4 right-4 h-px bg-blue-600 md:hidden"></div>

                <div class="flex items-start gap-2 md:gap-3 md:gap-6">
                  <div class="flex-shrink-0">
                    <div
                      class="w-8 h-8 md:w-12 md:h-12 md:w-16 md:h-16 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center shadow-md md:shadow-lg">
                      <i class="fas fa-seedling text-white text-sm md:text-lg md:text-xl"></i>
                    </div>
                  </div>
                  <div class="flex-1">
                    <div class="flex items-center gap-2 md:gap-3 mb-2 md:mb-3">
                      <span
                        class="inline-flex items-center px-1.5 md:px-2 md:px-3 py-0.5 md:py-1 rounded-full text-xs md:text-xs md:text-sm font-medium bg-green-100 text-green-800">
                        Pre-Spanish Era
                      </span>
                    </div>
                    <h3 class="text-base md:text-lg md:text-2xl font-bold text-gray-900 mb-1.5 md:mb-2 md:mb-3">Before
                      1568</h3>
                    <ul class="space-y-1.5 md:space-y-2 text-xs md:text-sm md:text-base text-gray-700 leading-relaxed">
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        The area now known as Panducot was inhabited by indigenous people engaged in agriculture and
                        trade along the Pampanga River.
                      </li>
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        Population exceeded 2,000, leading to its recognition as a barangay.
                      </li>
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        Fishing and selling pandan leaves were the primary livelihoods.
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>

            <!-- Desktop only: Empty space for alternating layout -->
            <div class="hidden md:block flex-1 pl-8"></div>
          </div>

          <!-- 1572 Spanish Era -->
          <div class="relative flex items-center">
            <!-- Desktop only: Empty space for alternating layout -->
            <div class="hidden md:block flex-1 pr-8"></div>

            <!-- Desktop only: Timeline dot -->
            <div
              class="hidden md:block absolute md:left-1/2 transform -translate-x-1/2 w-4 h-4 bg-blue-600 rounded-full border-4 border-white shadow-lg z-10">
            </div>

            <!-- Mobile: Single column layout, Desktop: Two column layout -->
            <div class="w-full md:flex-1 pr-0 md:pl-4 md:pl-8 fade-in-left">
              <div
                class="relative bg-white rounded-lg md:rounded-xl shadow-md md:shadow-lg p-3 md:p-4 md:p-8 mr-0 md:ml-4 md:ml-8 hover:shadow-lg md:hover:shadow-xl transition-shadow duration-300">
                <!-- Mobile timeline line at bottom -->
                <div class="absolute bottom-0 left-1/4 right-4 h-px bg-blue-600 md:hidden"></div>

                <div class="flex items-start gap-2 md:gap-3 md:gap-6">
                  <div class="flex-shrink-0">
                    <div
                      class="w-8 h-8 md:w-12 md:h-12 md:w-16 md:h-16 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center shadow-md md:shadow-lg">
                      <i class="fas fa-cross text-white text-sm md:text-lg md:text-xl"></i>
                    </div>
                  </div>
                  <div class="flex-1">
                    <div class="flex items-center gap-2 md:gap-3 mb-2 md:mb-3">
                      <span
                        class="inline-flex items-center px-1.5 md:px-2 md:px-3 py-0.5 md:py-1 rounded-full text-xs md:text-xs md:text-sm font-medium bg-yellow-100 text-yellow-800">
                        Spanish Era
                      </span>
                    </div>
                    <h3 class="text-base md:text-lg md:text-2xl font-bold text-gray-900 mb-1.5 md:mb-2 md:mb-3">1572
                    </h3>
                    <ul class="space-y-1.5 md:space-y-2 text-xs md:text-sm md:text-base text-gray-700 leading-relaxed">
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        Spanish missionaries, led by Padre Diego Vivar Ordonez, arrived in Panducot.
                      </li>
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        The first Catholic church in Bulacan was built in Panducot.
                      </li>
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        Panducot became the starting point for the spread of Christianity in Bulacan.
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 1575 -->
          <div class="relative flex items-center">
            <!-- Desktop only: Timeline dot -->
            <div
              class="hidden md:block absolute md:left-1/2 transform -translate-x-1/2 w-4 h-4 bg-blue-600 rounded-full border-4 border-white shadow-lg z-10">
            </div>

            <!-- Mobile: Single column layout, Desktop: Two column layout -->
            <div class="w-full md:flex-1 pr-0 md:pr-4 md:pr-8 fade-in-right">
              <div
                class="relative bg-white rounded-lg md:rounded-xl shadow-md md:shadow-lg p-3 md:p-4 md:p-8 mr-0 md:mr-4 md:mr-8 hover:shadow-lg md:hover:shadow-xl transition-shadow duration-300">
                <!-- Mobile timeline line at bottom -->
                <div class="absolute bottom-0 left-1/4 right-4 h-px bg-blue-600 md:hidden"></div>

                <div class="flex items-start gap-2 md:gap-3 md:gap-6">
                  <div class="flex-shrink-0">
                    <div
                      class="w-8 h-8 md:w-12 md:h-12 md:w-16 md:h-16 bg-gradient-to-br from-purple-400 to-purple-600 rounded-full flex items-center justify-center shadow-md md:shadow-lg">
                      <i class="fas fa-crown text-white text-sm md:text-lg md:text-xl"></i>
                    </div>
                  </div>
                  <div class="flex-1">
                    <div class="flex items-center gap-2 md:gap-3 mb-2 md:mb-3">
                      <span
                        class="inline-flex items-center px-1.5 md:px-2 md:px-3 py-0.5 md:py-1 rounded-full text-xs md:text-xs md:text-sm font-medium bg-purple-100 text-purple-800">
                        Royal Recognition
                      </span>
                    </div>
                    <h3 class="text-base md:text-lg md:text-2xl font-bold text-gray-900 mb-1.5 md:mb-2 md:mb-3">1575
                    </h3>
                    <ul class="space-y-1.5 md:space-y-2 text-xs md:text-sm md:text-base text-gray-700 leading-relaxed">
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        King of Spain officially recognized Calumpit as a town.
                      </li>
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        The San Juan Bautista Parish Church was established in Calumpit.
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            <!-- Desktop: Empty space for alternating layout -->
            <div class="hidden md:block md:flex-1"></div>
          </div>



          <div class="flex-1 pl-8"></div>


          <!-- 1600s -->
          <div class="relative flex items-center">
            <!-- Desktop only: Timeline dot -->
            <div
              class="hidden md:block absolute md:left-1/2 transform -translate-x-1/2 w-4 h-4 bg-blue-600 rounded-full border-4 border-white shadow-lg z-10">
            </div>

            <!-- Desktop: Empty space for alternating layout -->
            <div class="hidden md:block md:flex-1 pr-8"></div>

            <!-- Mobile: Single column layout, Desktop: Two column layout -->
            <div class="w-full md:flex-1 pl-0 md:pl-4 md:pl-8 fade-in-left">
              <div
                class="relative bg-white rounded-lg md:rounded-xl shadow-md md:shadow-lg p-3 md:p-4 md:p-8 ml-0 md:ml-4 md:ml-8 hover:shadow-lg md:hover:shadow-xl transition-shadow duration-300">
                <!-- Mobile timeline line at bottom -->
                <div class="absolute bottom-0 left-1/4 right-4 h-px bg-blue-600 md:hidden"></div>

                <div class="flex items-start gap-2 md:gap-3 md:gap-6">
                  <div class="flex-shrink-0">
                    <div
                      class="w-8 h-8 md:w-12 md:h-12 md:w-16 md:h-16 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center shadow-md md:shadow-lg">
                      <i class="fas fa-ship text-white text-sm md:text-lg md:text-xl"></i>
                    </div>
                  </div>
                  <div class="flex-1">
                    <div class="flex items-center gap-2 md:gap-3 mb-2 md:mb-3">
                      <span
                        class="inline-flex items-center px-1.5 md:px-2 md:px-3 py-0.5 md:py-1 rounded-full text-xs md:text-xs md:text-sm font-medium bg-blue-100 text-blue-800">
                        Trade Era
                      </span>
                    </div>
                    <h3 class="text-base md:text-lg md:text-2xl font-bold text-gray-900 mb-1.5 md:mb-2 md:mb-3">1600s
                    </h3>
                    <ul class="space-y-1.5 md:space-y-2 text-xs md:text-sm md:text-base text-gray-700 leading-relaxed">
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        Calumpit served as a commercial hub with major trade routes.
                      </li>
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        The church became a spiritual center for surrounding barangays.
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- 1672 -->
          <div class="relative flex items-center">
            <!-- Desktop only: Timeline dot -->
            <div
              class="hidden md:block absolute md:left-1/2 transform -translate-x-1/2 w-4 h-4 bg-blue-600 rounded-full border-4 border-white shadow-lg z-10">
            </div>

            <!-- Mobile: Single column layout, Desktop: Two column layout -->
            <div class="w-full md:flex-1 pr-0 md:pr-4 md:pr-8 fade-in-right">
              <div
                class="relative bg-white rounded-lg md:rounded-xl shadow-md md:shadow-lg p-3 md:p-4 md:p-8 mr-0 md:mr-4 md:mr-8 hover:shadow-lg md:hover:shadow-xl transition-shadow duration-300">
                <!-- Mobile timeline line at bottom -->
                <div class="absolute bottom-0 left-1/4 right-4 h-px bg-blue-600 md:hidden"></div>

                <div class="flex items-start gap-2 md:gap-3 md:gap-6">
                  <div class="flex-shrink-0">
                    <div
                      class="w-8 h-8 md:w-12 md:h-12 md:w-16 md:h-16 bg-gradient-to-br from-gray-400 to-gray-600 rounded-full flex items-center justify-center shadow-md md:shadow-lg">
                      <i class="fas fa-university text-white text-sm md:text-lg md:text-xl"></i>
                    </div>
                  </div>
                  <div class="flex-1">
                    <div class="flex items-center gap-2 md:gap-3 mb-2 md:mb-3">
                      <span
                        class="inline-flex items-center px-1.5 md:px-2 md:px-3 py-0.5 md:py-1 rounded-full text-xs md:text-xs md:text-sm font-medium bg-gray-100 text-gray-800">
                        Government
                      </span>
                    </div>
                    <h3 class="text-base md:text-lg md:text-2xl font-bold text-gray-900 mb-1.5 md:mb-2 md:mb-3">1672
                    </h3>
                    <ul class="space-y-1.5 md:space-y-2 text-xs md:text-sm md:text-base text-gray-700 leading-relaxed">
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        Establishment of the first civil government in Calumpit.
                      </li>
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        Marcos de Arce became the first Alcalde Mayor.
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            <!-- Desktop: Empty space for alternating layout -->
            <div class="hidden md:block md:flex-1 pl-8"></div>
          </div>

          <!-- Revolution Era -->
          <div class="relative flex items-center">
            <!-- Desktop only: Timeline dot -->
            <div
              class="hidden md:block absolute md:left-1/2 transform -translate-x-1/2 w-4 h-4 bg-blue-600 rounded-full border-4 border-white shadow-lg z-10">
            </div>

            <!-- Desktop: Empty space for alternating layout -->
            <div class="hidden md:block md:flex-1 pr-8"></div>

            <!-- Mobile: Single column layout, Desktop: Two column layout -->
            <div class="w-full md:flex-1 pl-0 md:pl-4 md:pl-8 fade-in-left">
              <div
                class="relative bg-white rounded-lg md:rounded-xl shadow-md md:shadow-lg p-3 md:p-4 md:p-8 ml-0 md:ml-4 md:ml-8 hover:shadow-lg md:hover:shadow-xl transition-shadow duration-300">
                <!-- Mobile timeline line at bottom -->
                <div class="absolute bottom-0 left-1/4 right-4 h-px bg-blue-600 md:hidden"></div>

                <div class="flex items-start gap-2 md:gap-3 md:gap-6">
                  <div class="flex-shrink-0">
                    <div
                      class="w-8 h-8 md:w-12 md:h-12 md:w-16 md:h-16 bg-gradient-to-br from-red-400 to-red-600 rounded-full flex items-center justify-center shadow-md md:shadow-lg">
                      <i class="fas fa-fist-raised text-white text-sm md:text-lg md:text-xl"></i>
                    </div>
                  </div>
                  <div class="flex-1">
                    <div class="flex items-center gap-2 md:gap-3 mb-2 md:mb-3">
                      <span
                        class="inline-flex items-center px-1.5 md:px-2 md:px-3 py-0.5 md:py-1 rounded-full text-xs md:text-xs md:text-sm font-medium bg-red-100 text-red-800">
                        Revolution
                      </span>
                    </div>
                    <h3 class="text-base md:text-lg md:text-2xl font-bold text-gray-900 mb-1.5 md:mb-2 md:mb-3">
                      1896-1899
                    </h3>
                    <ul class="space-y-1.5 md:space-y-2 text-xs md:text-sm md:text-base text-gray-700 leading-relaxed">
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        Panducot residents joined local uprisings against Spanish rule.
                      </li>
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        Battle of Calumpit fought against American troops.
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Modern Era -->
          <div class="relative flex items-center">
            <!-- Desktop only: Timeline dot -->
            <div
              class="hidden md:block absolute md:left-1/2 transform -translate-x-1/2 w-4 h-4 bg-blue-600 rounded-full border-4 border-white shadow-lg z-10">
            </div>

            <!-- Mobile: Single column layout, Desktop: Two column layout -->
            <div class="w-full md:flex-1 pr-0 md:pr-4 md:pr-8 fade-in-right">
              <div
                class="relative bg-white rounded-lg md:rounded-xl shadow-md md:shadow-lg p-3 md:p-4 md:p-8 mr-0 md:mr-4 md:mr-8 hover:shadow-lg md:hover:shadow-xl transition-shadow duration-300">
                <!-- Mobile timeline line at bottom -->
                <div class="absolute bottom-0 left-1/4 right-4 h-px bg-blue-600 md:hidden"></div>

                <div class="flex items-start gap-2 md:gap-3 md:gap-6">
                  <div class="flex-shrink-0">
                    <div
                      class="w-8 h-8 md:w-12 md:h-12 md:w-16 md:h-16 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-full flex items-center justify-center shadow-md md:shadow-lg">
                      <i class="fas fa-city text-blue-800 text-sm md:text-lg md:text-xl"></i>
                    </div>
                  </div>
                  <div class="flex-1">
                    <div class="flex items-center gap-2 md:gap-3 mb-2 md:mb-3">
                      <span
                        class="inline-flex items-center px-1.5 md:px-2 md:px-3 py-0.5 md:py-1 rounded-full text-xs md:text-xs md:text-sm font-medium bg-emerald-100 text-emerald-800">
                        Modern Era
                      </span>
                    </div>
                    <h3 class="text-base md:text-lg md:text-2xl font-bold text-gray-900 mb-1.5 md:mb-2 md:mb-3">1990s -
                      Present</h3>
                    <ul class="space-y-1.5 md:space-y-2 text-xs md:text-sm md:text-base text-gray-700 leading-relaxed">
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        Continuous development in infrastructure and connectivity.
                      </li>
                      <li class="flex items-start gap-1.5 md:gap-2">
                        <span
                          class="w-1.5 h-1.5 md:w-2 md:h-2 bg-blue-600 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></span>
                        Balancing agricultural traditions with urban growth.
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            <!-- Desktop: Empty space for alternating layout -->
            <div class="hidden md:block md:flex-1 pl-8"></div>
          </div>

        </div>
      </div>
    </div>

    <!-- Name Origins Section -->
    <div class="mt-20 px-4 md:px-64">
      <div class="text-center mb-16">
        <div class="relative inline-block">
          <h2 class="text-3xl md:text-5xl font-bold mb-4 title-enhanced text-shadow-sm relative z-10" data-aos="fade-up"
            data-aos-duration="1000">
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-900 to-blue-600">
              Name Origins
            </span>
          </h2>
          <div class="absolute w-full h-3 bg-blue-200/30 bottom-5 left-0 transform -rotate-1"></div>
        </div>

        <p class="text-sm md:text-xl text-gray-700 font-medium italic title-enhanced" data-aos="fade-up"
          data-aos-delay="300" data-aos-duration="1000">
          Discover the rich history behind the name "Panducot" and its significance to the community.
        </p>
      </div>

      <div class="grid md:grid-cols-2 gap-8">
        <div
          class="bg-white rounded-xl shadow-lg p-4 md:p-8 hover:shadow-xl transition-shadow duration-300  fade-in-left">
          <div class="flex items-start gap-3 md:gap-4">
            <div
              class="w-10 h-10 md:w-12 md:h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
              <i class="fas fa-leaf text-blue-600 text-lg md:text-xl"></i>
            </div>
            <div>
              <h4 class="text-sm md:text-xl font-semibold text-gray-900 mb-2 md:mb-3">Panducot</h4>
              <p class="text-sm md:text-base text-gray-700 leading-relaxed">
                Derived from "Pandan at Dukot" (Pandan trees and scooping fish/crabs).
                Locals used "panducot" (scooper) for fishing, giving rise to the name Panducot.
              </p>
            </div>
          </div>
        </div>

        <div
          class="bg-white rounded-xl shadow-lg p-4 md:p-8 hover:shadow-xl transition-shadow duration-300 fade-in-right">
          <div class="flex items-start gap-3 md:gap-4">
            <div
              class="w-10 h-10 md:w-12 md:h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
              <i class="fas fa-tree text-green-600 text-lg md:text-xl"></i>
            </div>
            <div>
              <h4 class="text-sm md:text-xl font-semibold text-gray-900 mb-2 md:mb-3">Calumpit</h4>
              <p class="text-sm md:text-base text-gray-700 leading-relaxed">
                Named after the Calumpit hardwood trees found near the Calumpit Church.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    </div>
  </section>

  <!-- VMGO SECTION -->
  <section class="py-16" style="background: linear-gradient(180deg, #fff 10%,rgba(37, 100, 235, 0.76) 30%, #fff 100%);">

    <!-- Diagonal Line Texture Background -->
    <div class="absolute inset-0 diagonal-lines"></div>
    <!-- Pattern Texture Layer -->
    <div class="absolute inset-0 grid-pattern"></div>

    <div class="container mx-auto px-4 md:px-4 max-w-6xl">
      <!-- Section Title -->
      <div class="text-center mb-12 md:mb-16 fade-in">
        <h2 class="text-2xl md:text-4xl md:text-5xl font-bold mb-3 md:mb-4 text-gray-800">
          <span class="bg-gradient-to-r from-blue-900 to-blue-700 bg-clip-text text-transparent">Vision, Mission &
            Goals</span>
        </h2>
        <div class="w-16 md:w-24 md:w-32 h-0.5 md:h-1 bg-gradient-to-r from-blue-900 to-blue-500 mx-auto mb-4 md:mb-6"></div>
        <p class="text-gray-600 max-w-3xl mx-auto text-base md:text-lg px-4 md:px-0">The guiding principles that drive our commitment to effective
          barangay governance and justice administration.</p>
      </div>

      <!-- Main Content -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8 mb-12 md:mb-16">
        <!-- Vision Card -->
        <div class="vmgo-card vision-card rounded-xl fade-in fade-in-delay-100">
          <div class="vmgo-glow-bg top-0 right-0"></div>
          <div class="vmgo-corner-accent vmgo-corner-top-right vmgo-corner-blue"></div>

          <div class="vmgo-card-header flex items-center justify-between">
            <h3 class="text-xl md:text-2xl font-bold text-white">Vision</h3>
            <div class="vmgo-icon-wrapper">
              <svg xmlns="http://www.w3.org/2000/svg" class="vmgo-icon h-6 w-6 md:h-7 md:w-7" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </div>
          </div>

          <div class="vmgo-card-inner">
            <p class="text-sm md:text-base text-gray-700 leading-relaxed">
              To be a model barangay for digital justice, community unity, and progressive governance, setting the
              standard for technology-driven public service in the Philippines.
            </p>

            <div class="mt-auto pt-4 md:pt-6">
              <div class="w-full h-1 bg-gradient-to-r from-blue-900 to-blue-500"></div>
            </div>
          </div>
        </div>

        <!-- Mission Card -->
        <div class="vmgo-card mission-card rounded-xl fade-in fade-in-delay-200">
          <div class="vmgo-glow-bg top-0 left-0"></div>
          <div class="vmgo-corner-accent vmgo-corner-top-right vmgo-corner-blue"></div>

          <div class="vmgo-card-header flex items-center justify-between">
            <h3 class="text-xl md:text-2xl font-bold text-white">Mission</h3>
            <div class="vmgo-icon-wrapper">
              <svg xmlns="http://www.w3.org/2000/svg" class="vmgo-icon h-6 w-6 md:h-7 md:w-7" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
          </div>

          <div class="vmgo-card-inner">
            <p class="text-sm md:text-base text-gray-700 leading-relaxed">
              To deliver fair, efficient, and transparent justice and public service to every resident through
              innovative digital solutions that simplify processes, enhance accessibility, and build community trust.
            </p>

            <div class="mt-auto pt-4 md:pt-6">
              <div class="w-full h-1 bg-gradient-to-r from-blue-800 to-blue-400"></div>
            </div>
          </div>
        </div>

        <!-- Goals Card -->
        <div class="vmgo-card goals-card rounded-xl fade-in fade-in-delay-300">
          <div class="vmgo-glow-bg bottom-0 right-0"></div>
          <div class="vmgo-corner-accent vmgo-corner-top-right vmgo-corner-blue"></div>

          <div class="vmgo-card-header flex items-center justify-between">
            <h3 class="text-xl md:text-2xl font-bold text-white">Goals</h3>
            <div class="vmgo-icon-wrapper">
              <svg xmlns="http://www.w3.org/2000/svg" class="vmgo-icon h-6 w-6 md:h-7 md:w-7" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
          </div>

          <div class="vmgo-card-inner">
            <ul class="space-y-2 md:space-y-3 text-sm md:text-base text-gray-700">
              <li class="flex items-start">
                <svg class="h-4 w-4 md:h-5 md:w-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Streamline case management and dispute resolution</span>
              </li>
              <li class="flex items-start">
                <svg class="h-4 w-4 md:h-5 md:w-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Promote transparency and accountability</span>
              </li>
              <li class="flex items-start">
                <svg class="h-4 w-4 md:h-5 md:w-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Empower residents and officials with digital tools</span>
              </li>
            </ul>

            <div class="mt-auto pt-4 md:pt-6">
              <div class="w-full h-1 bg-gradient-to-r from-blue-700 to-blue-300"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Objectives & Core Values -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-10">
        <!-- Objectives -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100 fade-in fade-in-left">
          <div class="bg-blue-800 py-3 md:py-4 px-4 md:px-6">
            <h3 class="text-xl md:text-2xl font-bold text-white">Objectives</h3>
          </div>
          <div class="p-4 md:p-6">
            <ul class="space-y-3 md:space-y-4">
              <li class="flex items-start">
                <div
                  class="flex-shrink-0 h-6 w-6 md:h-8 md:w-8 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center font-bold text-sm md:text-lg mr-2 md:mr-3">
                  1</div>
                <div>
                  <p class="text-sm md:text-base text-gray-700">Enhance Community Preparedness</p>
                </div>
              </li>
              <li class="flex items-start">
                <div
                  class="flex-shrink-0 h-6 w-6 md:h-8 md:w-8 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center font-bold text-sm md:text-lg mr-2 md:mr-3">
                  2</div>
                <div>
                  <p class="text-sm md:text-base text-gray-700">Promote Environmental Sustainability</p>
                </div>
              </li>
              <li class="flex items-start">
                <div
                  class="flex-shrink-0 h-6 w-6 md:h-8 md:w-8 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center font-bold text-sm md:text-lg mr-2 md:mr-3">
                  3</div>
                <div>
                  <p class="text-sm md:text-base text-gray-700">Improve Infrastructure Resilience</p>
                </div>
              </li>
              <li class="flex items-start">
                <div
                  class="flex-shrink-0 h-6 w-6 md:h-8 md:w-8 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center font-bold text-sm md:text-lg mr-2 md:mr-3">
                  4</div>
                <div>
                  <p class="text-sm md:text-base text-gray-700">Foster Strong Community Engagement</p>
                </div>
              </li>
              <li class="flex items-start">
                <div
                  class="flex-shrink-0 h-6 w-6 md:h-8 md:w-8 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center font-bold text-sm md:text-lg mr-2 md:mr-3">
                  5</div>
                <div>
                  <p class="text-sm md:text-base text-gray-700">Develop Effective Communication Systems</p>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <!-- Core Values -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100 fade-in fade-in-left">
          <div class="bg-blue-800 py-3 md:py-4 px-4 md:px-6">
            <h3 class="text-xl md:text-2xl font-bold text-white">Core Values</h3>
          </div>
          <div class="p-4 md:p-6">
            <div class="grid grid-cols-1 gap-3 md:gap-4">
              <div class="bg-blue-50 rounded-lg p-3 md:p-4 border-l-4 border-blue-800">
                <h4 class="font-bold text-sm md:text-base text-blue-900 mb-1">Integrity & Transparency</h4>
                <p class="text-xs md:text-sm text-gray-700">Upholding honesty and openness in all processes</p>
              </div>
              <div class="bg-blue-50 rounded-lg p-3 md:p-4 border-l-4 border-blue-800">
                <h4 class="font-bold text-sm md:text-base text-blue-900 mb-1">Professionalism & Fairness</h4>
                <p class="text-xs md:text-sm text-gray-700">Treating every case with respect and impartiality</p>
              </div>
              <div class="bg-blue-50 rounded-lg p-3 md:p-4 border-l-4 border-blue-800">
                <h4 class="font-bold text-sm md:text-base text-blue-900 mb-1">Community-Centered</h4>
                <p class="text-xs md:text-sm text-gray-700">Prioritizing the needs and welfare of residents</p>
              </div>
              <div class="bg-blue-50 rounded-lg p-3 md:p-4 border-l-4 border-blue-800">
                <h4 class="font-bold text-sm md:text-base text-blue-900 mb-1">Accountability</h4>
                <p class="text-xs md:text-sm text-gray-700">Taking responsibility for decisions and outcomes</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CONNECTION TO BARANGAY PANDUCOT -->
  <section class="py-16 history-section" style="background: linear-gradient(180deg, #fff 10%,rgba(37, 100, 235, 0.82) 30%, #fff 100%);">

    <!-- Diagonal Line Texture Background -->
    <div class="absolute inset-0 diagonal-lines"></div>
    <!-- Pattern Texture Layer -->
    <div class="absolute inset-0 grid-pattern"></div>

    <div class="container mx-auto px-4 md:px-4 max-w-6xl">
      <div class="text-center mb-8 md:mb-10 fade-in">
        <h2 class="text-2xl md:text-4xl font-bold mb-3 md:mb-4 text-gray-800">
          <span class="bg-gradient-to-r from-blue-900 to-blue-700 bg-clip-text text-transparent">BPAMIS & Barangay
            Panducot</span>
        </h2>
        <div class="w-16 md:w-24 h-0.5 md:h-1 bg-gradient-to-r from-blue-900 to-blue-500 mx-auto mb-4 md:mb-6"></div>
        <p class="text-gray-600 max-w-3xl mx-auto text-sm md:text-lg px-4 md:px-0">How our digital platform strengthens community governance and
          justice delivery</p>
      </div>

      <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100 fade-in fade-in-left">
        <div class="bg-gradient-to-r from-blue-900 to-blue-700 py-4 md:py-6 px-4 md:px-8">
          <h3 class="text-xl md:text-2xl font-bold text-white">Community Impact & Integration</h3>
        </div>
        <div class="p-4 md:p-8">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
            <div
              class="flex flex-col items-center text-center p-4 md:p-6 bg-blue-50 rounded-xl hover:shadow-md transition-all">
              <div class="w-12 h-12 md:w-16 md:h-16 bg-blue-100 rounded-full flex items-center justify-center mb-3 md:mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-8 md:w-8 text-blue-700" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
              </div>
              <h4 class="text-sm md:text-xl font-semibold text-blue-900 mb-1.5 md:mb-2">Digital Case Management</h4>
              <p class="text-xs md:text-base text-gray-700">BPAMIS strengthens local justice delivery by digitizing and organizing case
                management</p>
            </div>

            <div
              class="flex flex-col items-center text-center p-4 md:p-6 bg-blue-50 rounded-xl hover:shadow-md transition-all">
              <div class="w-12 h-12 md:w-16 md:h-16 bg-blue-100 rounded-full flex items-center justify-center mb-3 md:mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-8 md:w-8 text-blue-700" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h.5A2.5 2.5 0 0020 5.5v-1.5" />
                </svg>
              </div>
              <h4 class="text-sm md:text-xl font-semibold text-blue-900 mb-1.5 md:mb-2">Technology Bridge</h4>
              <p class="text-xs md:text-base text-gray-700">Bridges governance and technology for a more responsive barangay administration
              </p>
            </div>

            <div
              class="flex flex-col items-center text-center p-4 md:p-6 bg-blue-50 rounded-xl hover:shadow-md transition-all">
              <div class="w-12 h-12 md:w-16 md:h-16 bg-blue-100 rounded-full flex items-center justify-center mb-3 md:mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-8 md:w-8 text-blue-700" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
              </div>
              <h4 class="text-sm md:text-xl font-semibold text-blue-900 mb-1.5 md:mb-2">Community Empowerment</h4>
              <p class="text-xs md:text-base text-gray-700">Empowers both officials and residents to participate in transparent, efficient
                governance</p>
            </div>
          </div>

          <div class="mt-6 md:mt-10 bg-gray-50 p-4 md:p-6 rounded-xl border border-gray-200">
            <div class="flex items-start">
              <div class="flex-shrink-0 bg-blue-100 rounded-full p-2 md:p-3 mr-3 md:mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 md:h-6 md:w-6 text-blue-700" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <h4 class="text-sm md:text-lg font-semibold text-blue-900 mb-1.5 md:mb-2">Why It Matters</h4>
                <p class="text-sm md:text-base text-gray-700">
                  BPAMIS represents the convergence of traditional barangay governance with modern technology, ensuring
                  that Barangay Panducot can provide essential services to all residents regardless of technical
                  knowledge or access. This system embodies our commitment to inclusive community development.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- SERVICES OFFERED -->

    <div class="container mx-auto px-4 md:px-4 max-w-6xl" style="margin-top: 50px;">
      <div class="text-center mb-8 md:mb-12 fade-in">
        <h2 class="text-2xl md:text-4xl font-bold mb-3 md:mb-4 text-gray-800">
          <span class="bg-gradient-to-r from-blue-900 to-blue-700 bg-clip-text text-transparent">Services Offered</span>
        </h2>
        <div class="w-16 md:w-24 h-0.5 md:h-1 bg-gradient-to-r from-blue-900 to-blue-500 mx-auto mb-4 md:mb-6"></div>
        <p class="text-gray-200 max-w-3xl mx-auto text-sm md:text-lg px-4 md:px-0">Comprehensive digital solutions to streamline barangay
          processes and enhance community service delivery</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
        <!-- Service Card 1 -->
        <div
          class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden group fade-in fade-in-delay-100">
          <div class="h-3 bg-gradient-to-r from-blue-900 to-blue-700"></div>
          <div class="p-4 md:p-6">
            <div
              class="w-12 h-12 md:w-14 md:h-14 rounded-lg bg-blue-100 flex items-center justify-center mb-3 md:mb-4 text-blue-700 group-hover:bg-blue-700 group-hover:text-white transition-all duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-7 md:w-7" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <h3 class="text-sm md:text-xl font-bold text-gray-800 mb-1.5 md:mb-2">Case Management</h3>
            <p class="text-sm md:text-base text-gray-600 mb-3 md:mb-4">Digital case filing, tracking, and comprehensive case history for efficient
              dispute resolution</p>
            
          </div>
        </div>

        <!-- Service Card 2 -->
        <div
          class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden group fade-in fade-in-delay-200">
          <div class="h-3 bg-gradient-to-r from-blue-800 to-blue-600"></div>
          <div class="p-4 md:p-6">
            <div
              class="w-12 h-12 md:w-14 md:h-14 rounded-lg bg-blue-100 flex items-center justify-center mb-3 md:mb-4 text-blue-700 group-hover:bg-blue-700 group-hover:text-white transition-all duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-7 md:w-7" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <h3 class="text-sm md:text-xl font-bold text-gray-800 mb-1.5 md:mb-2">Appointment Scheduling</h3>
            <p class="text-sm md:text-base text-gray-600 mb-3 md:mb-4">Mediation scheduling, monitoring, and automated notification system for all
              participants</p>
            
          </div>
        </div>

        <!-- Service Card 3 -->
        <div
          class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden group fade-in fade-in-delay-300">
          <div class="h-3 bg-gradient-to-r from-blue-700 to-blue-500"></div>
          <div class="p-4 md:p-6">
            <div
              class="w-12 h-12 md:w-14 md:h-14 rounded-lg bg-blue-100 flex items-center justify-center mb-3 md:mb-4 text-blue-700 group-hover:bg-blue-700 group-hover:text-white transition-all duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-7 md:w-7" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-sm md:text-xl font-bold text-gray-800 mb-1.5 md:mb-2">Document Access</h3>
            <p class="text-sm md:text-base text-gray-600 mb-3 md:mb-4">Secure access to official documents, certificates, and forms with digital
              authentication</p>
            
          </div>
        </div>

        <!-- Service Card 4 -->
        <div
          class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden group fade-in fade-in-delay-400">
          <div class="h-3 bg-gradient-to-r from-blue-600 to-blue-400"></div>
          <div class="p-4 md:p-6">
            <div
              class="w-12 h-12 md:w-14 md:h-14 rounded-lg bg-blue-100 flex items-center justify-center mb-3 md:mb-4 text-blue-700 group-hover:bg-blue-700 group-hover:text-white transition-all duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-7 md:w-7" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <h3 class="text-sm md:text-xl font-bold text-gray-800 mb-1.5 md:mb-2">Resident Management</h3>
            <p class="text-sm md:text-base text-gray-600 mb-3 md:mb-4">Comprehensive resident profiles, community updates, and notification system
            </p>
           
          </div>
        </div>
      </div>

      <div class="mt-12 md:mt-16 bg-white rounded-xl shadow-md overflow-hidden fade-in">
        <div class="bg-gradient-to-r from-blue-900 to-blue-700 py-4 md:py-5 px-4 md:px-6">
          <h3 class="text-xl md:text-2xl font-bold text-white">System Features & Innovations</h3>
        </div>
        <div class="p-4 md:p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 md:gap-x-16 gap-y-6 md:gap-y-8">
            <div class="flex items-start">
              <div class="flex-shrink-0 mr-3 md:mr-4">
                <div class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-blue-100 flex items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5 text-blue-700" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 10V3L4 14h7v7l9-11h-7z" />
                  </svg>
                </div>
              </div>
              <div>
                <h4 class="text-sm md:text-lg font-semibold text-gray-800 mb-1">Automated Workflows</h4>
                <p class="text-sm md:text-base text-gray-600">Streamlined processes with predefined steps and automatic progression through
                  case stages</p>
              </div>
            </div>

            <div class="flex items-start">
              <div class="flex-shrink-0 mr-3 md:mr-4">
                <div class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-blue-100 flex items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5 text-blue-700" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
              </div>
              <div>
                <h4 class="text-sm md:text-lg font-semibold text-gray-800 mb-1">Secure Transactions</h4>
                <p class="text-sm md:text-base text-gray-600">End-to-end encrypted communications and secure document handling protocols</p>
              </div>
            </div>

            <div class="flex items-start">
              <div class="flex-shrink-0 mr-3 md:mr-4">
                <div class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-blue-100 flex items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5 text-blue-700" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                  </svg>
                </div>
              </div>
              <div>
                <h4 class="text-sm md:text-lg font-semibold text-gray-800 mb-1">Real-time Updates</h4>
                <p class="text-sm md:text-base text-gray-600">Instant notifications for case status changes and upcoming appointments</p>
              </div>
            </div>

            <div class="flex items-start">
              <div class="flex-shrink-0 mr-3 md:mr-4">
                <div class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-blue-100 flex items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5 text-blue-700" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                  </svg>
                </div>
              </div>
              <div>
                <h4 class="text-sm md:text-lg font-semibold text-gray-800 mb-1">Role-based Access</h4>
                <p class="text-sm md:text-base text-gray-600">Tailored interfaces and permissions for officials, residents, and guests</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- IMPACT ON COMMUNITY GOVERNANCE -->
  <section class="py-16 bg-gradient-to-b from-white to-gray-50 history-section">

    <!-- Diagonal Line Texture Background -->
    <div class="absolute inset-0 diagonal-lines"></div>
    <!-- Pattern Texture Layer -->
    <div class="absolute inset-0 grid-pattern"></div>


    <div class="container mx-auto px-4 md:px-4 max-w-6xl">
      <div class="text-center mb-8 md:mb-12 fade-in">
        <h2 class="text-2xl md:text-4xl font-bold mb-3 md:mb-4 text-gray-800">
          <span class="bg-gradient-to-r from-blue-900 to-blue-700 bg-clip-text text-transparent">Impact on Community
            Governance</span>
        </h2>
        <div class="w-16 md:w-24 h-0.5 md:h-1 bg-gradient-to-r from-blue-900 to-blue-500 mx-auto mb-4 md:mb-6"></div>
        <p class="text-gray-600 max-w-3xl mx-auto text-sm md:text-lg px-4 md:px-0">Transforming barangay administration through digital
          innovation and inclusive governance</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">
        <!-- Impact Card 1 -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100 hover:shadow-lg 
          transition-all duration-300 transform hover:-translate-y-1 fade-in fade-in-delay-100">
          <div class="h-2 bg-blue-700"></div>
          <div class="p-4 md:p-8">
            <div class="w-12 h-12 md:w-16 md:h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4 md:mb-6 mx-auto">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-8 md:w-8 text-blue-700" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-sm md:text-xl font-bold text-gray-800 mb-3 md:mb-4 text-center">Efficiency & Productivity</h3>
            <div class="space-y-3 md:space-y-4">
              <div class="flex items-start">
                <svg class="h-4 w-4 md:h-5 md:w-5 text-blue-600 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="text-sm md:text-base text-gray-700">Reduces manual paperwork by 75%</p>
              </div>
              <div class="flex items-start">
                <svg class="h-4 w-4 md:h-5 md:w-5 text-blue-600 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="text-sm md:text-base text-gray-700">Decreases processing time by 60%</p>
              </div>
              <div class="flex items-start">
                <svg class="h-4 w-4 md:h-5 md:w-5 text-blue-600 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="text-sm md:text-base text-gray-700">Allows officials to handle 40% more cases</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Impact Card 2 -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100 hover:shadow-lg transition-all 
          duration-300 transform hover:-translate-y-1 fade-in fade-in-delay-200">
          <div class="h-2 bg-blue-700"></div>
          <div class="p-4 md:p-8">
            <div class="w-12 h-12 md:w-16 md:h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4 md:mb-6 mx-auto">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-8 md:w-8 text-blue-700" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
            </div>
            <h3 class="text-sm md:text-xl font-bold text-gray-800 mb-3 md:mb-4 text-center">Service Delivery</h3>
            <div class="space-y-3 md:space-y-4">
              <div class="flex items-start">
                <svg class="h-4 w-4 md:h-5 md:w-5 text-blue-600 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="text-sm md:text-base text-gray-700">Speeds up case resolutions by 50%</p>
              </div>
              <div class="flex items-start">
                <svg class="h-4 w-4 md:h-5 md:w-5 text-blue-600 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="text-sm md:text-base text-gray-700">Enhances accuracy of documentation</p>
              </div>
              <div class="flex items-start">
                <svg class="h-4 w-4 md:h-5 md:w-5 text-blue-600 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="text-sm md:text-base text-gray-700">Enables 24/7 online service availability</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Impact Card 3 -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100 hover:shadow-lg 
          transition-all duration-300 transform hover:-translate-y-1 fade-in fade-in-delay-300">
          <div class="h-2 bg-blue-700"></div>
          <div class="p-4 md:p-8">
            <div class="w-12 h-12 md:w-16 md:h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4 md:mb-6 mx-auto">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-8 md:w-8 text-blue-700" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
            </div>
            <h3 class="text-sm md:text-xl font-bold text-gray-800 mb-3 md:mb-4 text-center">Citizen Empowerment</h3>
            <div class="space-y-3 md:space-y-4">
              <div class="flex items-start">
                <svg class="h-4 w-4 md:h-5 md:w-5 text-blue-600 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="text-sm md:text-base text-gray-700">Increases citizen participation by 80%</p>
              </div>
              <div class="flex items-start">
                <svg class="h-4 w-4 md:h-5 md:w-5 text-blue-600 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="text-sm md:text-base text-gray-700">Enhances transparency in governance</p>
              </div>
              <div class="flex items-start">
                <svg class="h-4 w-4 md:h-5 md:w-5 text-blue-600 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="text-sm md:text-base text-gray-700">Provides equal access to information</p>
              </div>
            </div>
          </div>
        </div>
      </div>


      <!-- Need Personalized Assistance Section - Premium Version -->
      <div class="mt-16 mb-16 md:mt-18 md:mb-18 fade-in fade-in-delay-400 relative">
        <!-- Animated Background Glow -->
        <div class="absolute inset-0 bg-gradient-to-r from-blue-900/30 to-indigo-800/30 rounded-xl blur-xl pulse-slow">
        </div>

        <!-- Premium Card Container -->
        <div
          class="relative bg-gradient-to-r from-blue-800 via-indigo-700 to-blue-900 rounded-xl overflow-hidden shadow-2xl border border-blue-500/20 transform transition-all duration-500 hover:scale-[1.01]">
          <!-- Decorative Corner Elements -->
          <div class="absolute top-0 left-0 w-24 h-24 border-t-4 border-l-4 border-blue-300/30 rounded-tl-xl"></div>
          <div class="absolute bottom-0 right-0 w-24 h-24 border-b-4 border-r-4 border-blue-300/30 rounded-br-xl"></div>

          <!-- Shimmering Accent -->
          <div
            class="absolute -top-20 -right-20 w-40 h-40 bg-gradient-to-br from-blue-400/30 to-indigo-500/10 rounded-full blur-md shimmer-effect">
          </div>
          <div
            class="absolute -bottom-20 -left-20 w-40 h-40 bg-gradient-to-tr from-indigo-400/20 to-blue-500/10 rounded-full blur-md shimmer-effect-reverse">
          </div>

          <!-- Content Container -->
          <div class="px-10 py-16 md:py-14 md:px-12 text-center relative z-10">
            <!-- Premium Badge -->
            <div
              class="absolute top-4 right-4 bg-gradient-to-r from-amber-400 to-amber-600 text-xs text-white px-2 py-1 rounded-full font-semibold tracking-wider shadow-lg transform rotate-3 pulse-subtle">
              BPAMIS SUPPORT</div>

            <!-- Heading with Enhanced Typography -->
            <h3 class="text-xl md:text-2xl font-extrabold text-white mb-2 tracking-tight text-shadow-sm">
              Need Personalized Assistance?
            </h3>
            <div class="w-16 h-0.5 md:w-18 md:h-1 bg-gradient-to-r from-blue-400 to-indigo-400 mx-auto mb-4 md:mb-6 rounded-full shadow-glow">
            </div>

            <p class="text-xs md:text-sm text-blue-100 mb-6 md:mb-8 max-w-xl md:max-w-2xl mx-auto leading-relaxed">
              Our community support team is ready to help you navigate BPAMIS services or address any questions you may
              have about the system.
            </p>

            <!-- Enhanced CTA Buttons -->
            <div class="px-12 py- 12 flex flex-col sm:flex-row gap-3 md:gap-5 justify-center">
              <!-- Schedule Appointment Button - Primary CTA -->
              <a href="#"
                onclick="event.preventDefault(); document.getElementById('appointmentModal').classList.remove('hidden'); document.getElementById('modalContent').classList.remove('opacity-0', 'scale-95'); document.getElementById('modalContent').classList.add('opacity-100', 'scale-100');"
                class="inline-flex items-center justify-center px-3 py-2 md:px-8 md:py-4 text-xs md:text-sm font-bold rounded-lg text-blue-900 bg-gradient-to-r from-white to-blue-50 hover:from-blue-50 hover:to-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-xl transition-all duration-300 transform hover:-translate-y-1 hover:shadow-blue-500/20 group">
                <svg xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4 md:h-5 md:w-5 mr-2 md:mr-3 text-blue-600 group-hover:animate-bounce-subtle" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>Schedule Appointment</span>
                <svg xmlns="http://www.w3.org/2000/svg"
                  class="h-3 w-3 md:h-4 md:w-4 ml-1 md:ml-2 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </a>

              <!-- Contact Support Button - Secondary CTA -->
              <a href="contact.php"
                class="inline-flex items-center justify-center px-3 py-2 md:px-8 md:py-4 text-xs md:text-sm font-bold rounded-lg text-white border-2 border-blue-300/30 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-300 transform hover:-translate-y-1 group backdrop-blur-sm">
                <svg xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4 md:h-5 md:w-5 mr-2 md:mr-3 text-blue-300 group-hover:animate-pulse-subtle" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <span>Contact Support</span>
                <svg xmlns="http://www.w3.org/2000/svg"
                  class="h-3 w-3 md:h-4 md:w-4 ml-1 md:ml-2 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </a>
            </div>
          </div>
        </div>
      </div>
  </section>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // Remove loader when page is fully loaded
      window.addEventListener('load', function () {
        const loader = document.querySelector('.loader-wrapper');
        if (loader) {
          loader.classList.add('fade-out');
          setTimeout(() => {
            loader.remove();
          }, 1000);
        }
      });

      // Initialize Intersection Observer for fade-in animations
      const fadeElements = document.querySelectorAll('.fade-in, .fade-in-left, .fade-in-right');

      const fadeObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
          }
        });
      }, {
        root: null,
        threshold: 0.15,
        rootMargin: '0px 0px -50px 0px'
      });

      // Observe all fade elements
      fadeElements.forEach(el => {
        fadeObserver.observe(el);
      });

      // Initialize slide-in animation for elements with slide-in classes
      const slideElements = document.querySelectorAll('.slide-in-left, .slide-in-right');

      function checkSlide() {
        slideElements.forEach(slideEl => {
          // Half way through the element
          const slideInAt = (window.scrollY + window.innerHeight) - slideEl.offsetHeight / 2;
          // Bottom of the element
          const elementBottom = slideEl.offsetTop + slideEl.offsetHeight;
          const isHalfShown = slideInAt > slideEl.offsetTop;
          const isNotScrolledPast = window.scrollY < elementBottom;

          if (isHalfShown && isNotScrolledPast) {
            slideEl.style.opacity = '1';
            slideEl.style.transform = 'translateX(0)';
          }
        });
      }

      // Run check slide on scroll
      window.addEventListener('scroll', checkSlide);

      // Initialize AOS library if it exists
      if (typeof AOS !== 'undefined') {
        AOS.init({
          duration: 1000,
          once: true,
          offset: 100
        });
      }

      // Force loader removal after 5 seconds (safety measure)
      setTimeout(() => {
        const loader = document.querySelector('.loader-wrapper');
        if (loader) {
          loader.remove();
        }
      }, 5000);
    });
  </script>

  <!-- Include Footer -->
  <?php include('includes/footer.php'); ?>

  <!-- Include necessary modals -->
  <?php include('includes/faqs_modal.php'); ?>
  <?php include('includes/help_center_modal.php'); ?>
  <?php include('includes/user_guide_modal.php'); ?>
  <?php include('includes/schedule_appointment_modal.php'); ?>
</body>

</html>