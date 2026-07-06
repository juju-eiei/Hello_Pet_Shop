<?php
$patterns = ['admin_*.html', 'staff_*.html'];
foreach($patterns as $pattern) {
    $files = glob(__DIR__ . '/' . $pattern);
    foreach($files as $file) {
        $content = file_get_contents($file);
        if (strpos($content, '/src/js/i18n.js') === false) {
            // Insert script tag right before </head>
            $scriptTag = "    <script type=\"module\" src=\"/src/js/i18n.js\"></script>\n</head>";
            $content = str_replace('</head>', $scriptTag, $content);
            file_put_contents($file, $content);
            echo "Injected i18n script into " . basename($file) . "\n";
        }
    }
}
?>
