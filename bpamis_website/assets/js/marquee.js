    // Marquee Animation Enhancement
document.addEventListener('DOMContentLoaded', function() {
    // Get all marquee icon elements
    const marqueeIcons = document.querySelectorAll('.marquee-icon');
    const marqueeTrack = document.querySelector('.marquee-track');
    const marqueeContainer = document.querySelector('.marquee-container');
    
    // Fix for continuous movement - ensure the track width is calculated correctly
    function fixMarqueeWidth() {
        if (marqueeTrack) {
            // Get all the flex items inside the track
            const flexItems = document.querySelectorAll('.marquee-track > .flex');
            
            if (flexItems.length > 0) {
                // Measure the total content width
                let totalContentWidth = marqueeTrack.scrollWidth;
                const containerWidth = marqueeContainer.offsetWidth;
                
                // Get the computed style of the track
                const trackStyle = window.getComputedStyle(marqueeTrack);
                const gapSize = parseFloat(trackStyle.gap) || parseFloat(trackStyle.columnGap) || 0;
                
                // Force minimum width to be at least 3x container width to ensure seamless looping
                // This ensures the entire marquee displays completely before the first item repeats
                const minRequiredWidth = Math.max(totalContentWidth, containerWidth * 3);
                const extraBuffer = 200; // Extra buffer to ensure no gaps
                
                // Create duplicate nodes if needed for continuous scrolling
                const allItems = document.querySelectorAll('.marquee-track > .flex');
                if (allItems.length < 30) { // Only duplicate if we don't already have enough items
                    // Create copies of all items for seamless looping
                    allItems.forEach(item => {
                        const clone = item.cloneNode(true);
                        marqueeTrack.appendChild(clone);
                    });
                }
                
                // Set the width with extra buffer
                marqueeTrack.style.width = (minRequiredWidth + extraBuffer) + 'px';
                
                // Calculate animation duration - slower = smoother
                // Scale duration based on content width - more content = slower animation
                const baseDuration = 45;
                const speedFactor = 25; // Higher = slower
                const calculatedDuration = totalContentWidth / speedFactor;
                const newDuration = Math.max(baseDuration, calculatedDuration) + 's';
                
                // Apply the animation duration
                marqueeTrack.style.animationDuration = newDuration;
                
                console.log('Marquee setup complete:', {
                    contentWidth: totalContentWidth,
                    containerWidth: containerWidth,
                    trackWidth: minRequiredWidth + extraBuffer,
                    duration: newDuration
                });
            }
        }
    }
    
    // Run the fix on load and on resize
    fixMarqueeWidth();
    window.addEventListener('resize', fixMarqueeWidth);
    
    // Touch device detection
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    
    if (isTouchDevice) {
        // Add touch animation to the icons
        marqueeIcons.forEach((icon, index) => {
            // Add a touch event listener
            icon.addEventListener('touchstart', function() {
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
    
    // Mobile-specific animations removed as per request
});
