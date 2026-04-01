<?php
$files = glob("*.html");

$changesMade = 0;
foreach ($files as $file) {
    if ($file === 'admin_customers.html' || $file === 'admin_customer_details.html') {
        continue; // Already correct
    }
    
    $content = file_get_contents($file);
    
    // Replace sidebar link
    $pattern1 = '/href="#"(\s+class="menu-item">)\s*<i class="fas fa-users"><\/i>\s*<span>Customers<\/span>/';
    $replacement1 = 'href="admin_customers.html"$1
                    <i class="fas fa-users"></i>
                    <span>Customers</span>';
    
    // Replace bottom nav link
    $pattern2 = '/href="#"(\s+class="nav-item">)\s*<i class="fas fa-users"><\/i>\s*<span>Customers<\/span>/';
    $replacement2 = 'href="admin_customers.html"$1
                <i class="fas fa-users"></i>
                <span>Customers</span>';
                
    $newContent = preg_replace($pattern1, $replacement1, $content);
    $newContent = preg_replace($pattern2, $replacement2, $newContent);
    
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Updated links in $file\n";
        $changesMade++;
    }
}

if ($changesMade == 0) {
    echo "No files needed updating or pattern mismatch.\n";
} else {
    echo "Total files updated: $changesMade\n";
}
?>
