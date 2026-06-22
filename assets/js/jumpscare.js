window.addEventListener('load', function() {
    const min = 1;
    const max = 11; 
    const jumpscareElement = document.getElementById("jumpscare");

    if (!jumpscareElement) return;

    function getRandomInt(min, max) {
        const minCeiled = Math.ceil(min);
        const maxFloored = Math.floor(max);
        return Math.floor(Math.random() * (maxFloored - minCeiled) + minCeiled);
    }

    let randomInt = getRandomInt(min, max);

    if (randomInt === 1) {
        const originalSrc = jumpscareElement.src;
        // Store original styles to revert them perfectly
        const originalTransform = jumpscareElement.style.transform;
        const originalZIndex = jumpscareElement.style.zIndex;

        // 1. Change the image
        jumpscareElement.src = "/assets/images/Media.jpeg";
        // Set scale and over other elements
        jumpscareElement.style.transform = "scale(5)"; 
        jumpscareElement.style.zIndex = "1000";

        setTimeout(function() {
            // Revert image
            jumpscareElement.src = originalSrc;
            
            // Revert size and depth
            jumpscareElement.style.transform = originalTransform;
            jumpscareElement.style.zIndex = originalZIndex;
        }, 1000);
    }
});