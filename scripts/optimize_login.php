<?php
$path = '/var/www/sistem-tu/resources/views/siswa/auth/login.blade.php';
$content = file_get_contents($path);

// We need to remove the heavy backdrop-filter (backdrop-blur-xl and backdrop-blur-sm) 
// as well as the heavy box shadows because mobile browsers struggle with them when scrolling.
// We will also remove the heavy animations from the background (blob/pattern).

// 1. Remove backdrop-blurs
$content = str_replace('backdrop-blur-sm', '', $content);
$content = str_replace('backdrop-blur-md', '', $content);
$content = str_replace('backdrop-blur-xl', '', $content);
$content = str_replace('bg-white/90', 'bg-white', $content); // Solid background for main card
$content = str_replace('bg-white/60', 'bg-white/80', $content);

// 2. Remove or simplify heavy shadow on the card
$content = str_replace('shadow-[0_20px_40px_-15px_rgba(0,0,0,0.05)]', 'shadow-2xl', $content);

// 3. Simplify the CSS animations to reduce repaints
$cssToReplace = <<<'CSS'
        /* Animated Background Pattern */
        .bg-pattern {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: radial-gradient(#F56C3E 1px, transparent 1px);
            background-size: 32px 32px;
            opacity: 0.1;
            z-index: 0;
            animation: patternMove 20s linear infinite;
        }

        /* Decorative Blurs */
        .blob-1, .blob-2 {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            opacity: 0.5;
            animation: float 10s ease-in-out infinite alternate;
        }
CSS;

$cssReplacement = <<<'CSS'
        /* Static Background Pattern (Optimized for Mobile) */
        .bg-pattern {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: radial-gradient(#F56C3E 1px, transparent 1px);
            background-size: 32px 32px;
            opacity: 0.08;
            z-index: 0;
            /* Animation removed for performance */
        }

        /* Decorative Blurs (Static to save battery & CPU) */
        .blob-1, .blob-2 {
            position: fixed;
            border-radius: 50%;
            filter: blur(60px);
            z-index: 0;
            opacity: 0.4;
            /* Animation removed for performance */
            will-change: transform;
        }
CSS;

$content = str_replace($cssToReplace, $cssReplacement, $content);

file_put_contents($path, $content);
echo "Optimized $path\n";
