<?php
$dir = new RecursiveDirectoryIterator('/var/www/sistem-tu/resources/views');
$ite = new RecursiveIteratorIterator($dir);

$replacements = [
    // Sky/Blue -> Accent
    '#0284c7' => '#F56C3E',
    '#0284C7' => '#F56C3E',
    '#0ea5e9' => '#F56C3E',
    '#0EA5E9' => '#F56C3E',
    '#0369a1' => '#e45322',
    '#0369A1' => '#e45322',
    '#38bdf8' => '#f9c4b3',
    '#38BDF8' => '#f9c4b3',
    '#075985' => '#be3e15',
    '#075985' => '#be3e15',
    '#e0f2fe' => '#fce4db',
    '#E0F2FE' => '#fce4db',
    '#bae6fd' => '#f9c4b3',
    '#BAE6FD' => '#f9c4b3',
    '#f0f9ff' => '#fef0eb',
    '#F0F9FF' => '#fef0eb',
    '#2563eb' => '#F56C3E', // blue-500
    '#3b82f6' => '#F56C3E', // blue-400

    // Slate/Gray -> Custom Gray
    '#0f172a' => '#171717',
    '#0F172A' => '#171717',
    '#1e293b' => '#171717',
    '#1E293B' => '#171717',
    '#334155' => '#333333',
    '#475569' => '#333333',
    '#64748b' => '#777777',
    '#94a3b8' => '#777777',
    '#cbd5e1' => '#EDEDED',
    '#e2e8f0' => '#EDEDED',
    '#E2E8F0' => '#EDEDED',
    '#f1f5f9' => '#F5F5F5',
    '#f8fafc' => '#F5F5F5',
    '#F8FAFC' => '#F5F5F5',
    '#111827' => '#171717',
    '#1f2937' => '#171717',
    '#374151' => '#333333',
    '#4b5563' => '#333333',
    '#6b7280' => '#777777',
    '#9ca3af' => '#777777',
    '#d1d5db' => '#EDEDED',
    '#e5e7eb' => '#EDEDED',
    '#f3f4f6' => '#F5F5F5',
    '#F3F4F6' => '#F5F5F5',
    '#f9fafb' => '#F5F5F5',
    
    // Theme colors in app.blade.php
    "'blue'   => ['#0ea5e9', '#0284c7', '#0ea5e9', 'Sky Blue']," => "'blue'   => ['#F56C3E', '#F56C3E', '#F56C3E', 'Accent'],",
];

foreach ($ite as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $newContent = strtr($content, $replacements);
        if ($content !== $newContent) {
            file_put_contents($file->getPathname(), $newContent);
            echo "Updated: " . $file->getPathname() . "\n";
        }
    }
}
echo "Done.\n";
