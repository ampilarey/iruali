<?php
// Debug script to check CSS loading and caching issues
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>CSS Debug - iruali</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
        .error { background: #ffe6e6; border-color: #ff9999; }
        .success { background: #e6ffe6; border-color: #99ff99; }
        .info { background: #e6f3ff; border-color: #99ccff; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>CSS Debug Information</h1>
    
    <div class="debug-section info">
        <h2>Server Information</h2>
        <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
        <p><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Not set'; ?></p>
    </div>

    <div class="debug-section info">
        <h2>Build Directory Check</h2>
        <?php
        $buildDir = __DIR__ . '/build';
        if (is_dir($buildDir)) {
            echo '<p class="success">✓ Build directory exists</p>';
            
            $manifestFile = $buildDir . '/manifest.json';
            if (file_exists($manifestFile)) {
                echo '<p class="success">✓ Manifest file exists</p>';
                $manifest = json_decode(file_get_contents($manifestFile), true);
                if ($manifest) {
                    echo '<h3>Manifest Contents:</h3>';
                    echo '<pre>' . json_encode($manifest, JSON_PRETTY_PRINT) . '</pre>';
                }
            } else {
                echo '<p class="error">✗ Manifest file missing</p>';
            }
            
            $cssFiles = glob($buildDir . '/assets/*.css');
            if (!empty($cssFiles)) {
                echo '<p class="success">✓ CSS files found:</p>';
                foreach ($cssFiles as $cssFile) {
                    $filename = basename($cssFile);
                    $size = filesize($cssFile);
                    $modified = date('Y-m-d H:i:s', filemtime($cssFile));
                    echo "<p>- $filename (Size: " . number_format($size) . " bytes, Modified: $modified)</p>";
                }
            } else {
                echo '<p class="error">✗ No CSS files found in build/assets/</p>';
            }
        } else {
            echo '<p class="error">✗ Build directory missing</p>';
        }
        ?>
    </div>

    <div class="debug-section info">
        <h2>CSS File Content Check</h2>
        <?php
        $cssFiles = glob($buildDir . '/assets/*.css');
        if (!empty($cssFiles)) {
            foreach ($cssFiles as $cssFile) {
                $filename = basename($cssFile);
                $content = file_get_contents($cssFile);
                echo "<h3>$filename</h3>";
                echo "<p><strong>File size:</strong> " . number_format(strlen($content)) . " bytes</p>";
                
                // Check for problematic CSS rules
                $problematicRules = [
                    '!important' => substr_count($content, '!important'),
                    'display: flex' => substr_count($content, 'display: flex'),
                    'display: flex !important' => substr_count($content, 'display: flex !important'),
                    'margin-right: 1rem !important' => substr_count($content, 'margin-right: 1rem !important'),
                    'margin-left: 1rem !important' => substr_count($content, 'margin-left: 1rem !important'),
                ];
                
                echo "<h4>Problematic CSS Rules Found:</h4>";
                foreach ($problematicRules as $rule => $count) {
                    if ($count > 0) {
                        echo "<p class='error'>✗ $rule: $count occurrences</p>";
                    } else {
                        echo "<p class='success'>✓ $rule: 0 occurrences</p>";
                    }
                }
                
                // Show first 500 characters of CSS
                echo "<h4>First 500 characters of CSS:</h4>";
                echo "<pre>" . htmlspecialchars(substr($content, 0, 500)) . "...</pre>";
            }
        }
        ?>
    </div>

    <div class="debug-section info">
        <h2>Laravel Environment Check</h2>
        <?php
        $laravelRoot = dirname(__DIR__);
        $envFile = $laravelRoot . '/.env';
        if (file_exists($envFile)) {
            echo '<p class="success">✓ .env file exists</p>';
        } else {
            echo '<p class="error">✗ .env file missing</p>';
        }
        
        $storageDir = $laravelRoot . '/storage';
        if (is_dir($storageDir)) {
            echo '<p class="success">✓ Storage directory exists</p>';
            
            $cacheDir = $storageDir . '/framework/cache';
            if (is_dir($cacheDir)) {
                echo '<p class="success">✓ Cache directory exists</p>';
                $cacheFiles = glob($cacheDir . '/*');
                echo '<p><strong>Cache files:</strong> ' . count($cacheFiles) . ' files</p>';
            }
        } else {
            echo '<p class="error">✗ Storage directory missing</p>';
        }
        ?>
    </div>

    <div class="debug-section info">
        <h2>Headers and Cache Information</h2>
        <h3>Request Headers:</h3>
        <pre><?php print_r(getallheaders()); ?></pre>
        
        <h3>Response Headers (if any):</h3>
        <pre><?php print_r(headers_list()); ?></pre>
    </div>

    <div class="debug-section">
        <h2>Next Steps</h2>
        <ol>
            <li>Check if the CSS files shown above are the latest ones</li>
            <li>If problematic CSS rules are found, the build process may not have completed properly</li>
            <li>Clear Laravel caches: <code>php artisan cache:clear</code></li>
            <li>Clear view caches: <code>php artisan view:clear</code></li>
            <li>Rebuild assets: <code>npm run build</code></li>
            <li>Check if your hosting provider has additional caching layers</li>
        </ol>
    </div>
</body>
</html> 