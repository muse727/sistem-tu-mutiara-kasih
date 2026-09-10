<?php
$path = '/var/www/sistem-tu/resources/views/siswa/auth/login.blade.php';
$content = file_get_contents($path);

// 1. Fix the main issue: `overflow-hidden` on body prevents scrolling on mobile devices!
$content = str_replace('<body class="min-h-screen flex items-center justify-center px-4 py-10 relative overflow-hidden">', '<body class="min-h-screen flex items-center justify-center px-4 py-10 relative">', $content);

// 2. Ensure background decorative elements do not block touch events which can also disrupt scrolling
$cssToReplace = <<<'CSS'
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

$cssReplacement = <<<'CSS'
        /* Lightweight Animated Background Pattern */
        .bg-pattern {
            position: fixed;
            top: -50px; left: -50px; right: -50px; bottom: -50px;
            background-image: radial-gradient(#F56C3E 1px, transparent 1px);
            background-size: 32px 32px;
            opacity: 0.08;
            z-index: -1;
            pointer-events: none; /* VERY IMPORTANT so it doesn't block scroll */
            animation: patternMove 30s linear infinite;
        }

        /* Decorative Blurs */
        .blob-1, .blob-2 {
            position: fixed;
            border-radius: 50%;
            filter: blur(60px);
            z-index: -1;
            opacity: 0.4;
            pointer-events: none; /* VERY IMPORTANT */
            animation: float 12s ease-in-out infinite alternate;
            will-change: transform;
        }
CSS;

// Since we changed it in the last step, let's just do a robust regex or str_replace for pointer-events and z-index if the exact string matching fails
$content = str_replace($cssToReplace, $cssReplacement, $content);

// Ensure z-index is -1 and pointer-events: none is applied to background elements
if (strpos($content, 'pointer-events: none;') === false) {
    // If the exact replace failed, inject it manually
    $content = preg_replace('/\.bg-pattern \{/', ".bg-pattern {\n            pointer-events: none;\n            z-index: -1;", $content);
    $content = preg_replace('/\.blob-1, \.blob-2 \{/', ".blob-1, .blob-2 {\n            pointer-events: none;\n            z-index: -1;", $content);
}

// Ensure the animation keyframes are back if they were removed, or just add lightweight ones
if (strpos($content, '@keyframes patternMove') === false) {
    $keyframes = <<<'CSS'
        @keyframes patternMove {
            0% { transform: translateY(0); }
            100% { transform: translateY(32px); }
        }

        @keyframes float {
            0% { transform: translateY(0) scale(1); }
            100% { transform: translateY(20px) scale(1.05); }
        }
CSS;
    $content = str_replace('</style>', $keyframes . "\n    </style>", $content);
}

file_put_contents($path, $content);
echo "Scroll & Animation Fixed in $path\n";
