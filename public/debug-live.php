<?php
// Debug script for live server troubleshooting
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Live Server Debug - iruali</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; background: white; border-radius: 5px; }
        .error { background: #ffe6e6; border-color: #ff9999; }
        .success { background: #e6ffe6; border-color: #99ff99; }
        .info { background: #e6f3ff; border-color: #99ccff; }
        .warning { background: #fff3cd; border-color: #ffeaa7; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .file-list { background: #f8f9fa; padding: 10px; border-radius: 3px; }
        .file-item { margin: 5px 0; padding: 5px; background: white; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔍 Live Server Debug - iruali</h1>
    
    <div class="debug-section info">
        <h2>📋 Server Information</h2>
        <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
        <p><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Not set'; ?></p>
        <p><strong>Script Path:</strong> <?php echo __FILE__; ?></p>
        <p><strong>Current Directory:</strong> <?php echo getcwd(); ?></p>
    </div>

    <div class="debug-section info">
        <h2>📁 Directory Structure</h2>
        <div class="file-list">
            <h3>Current Directory (<?php echo getcwd(); ?>):</h3>
            <?php
            $files = scandir('.');
            foreach($files as $file) {
                if($file != '.' && $file != '..') {
                    $type = is_dir($file) ? '📁' : '📄';
                    $size = is_file($file) ? ' (' . number_format(filesize($file)) . ' bytes)' : '';
                    echo "<div class='file-item'>{$type} {$file}{$size}</div>";
                }
            }
            ?>
        </div>
    </div>

    <div class="debug-section info">
        <h2>🔧 Build Files Check</h2>
        <?php
        $buildPath = './build';
        if(is_dir($buildPath)) {
            echo "<div class='success'>✅ Build directory exists</div>";
            
            $manifestPath = $buildPath . '/manifest.json';
            if(file_exists($manifestPath)) {
                echo "<div class='success'>✅ Manifest file exists</div>";
                $manifest = json_decode(file_get_contents($manifestPath), true);
                if($manifest) {
                    echo "<div class='success'>✅ Manifest is valid JSON</div>";
                    echo "<pre>" . json_encode($manifest, JSON_PRETTY_PRINT) . "</pre>";
                } else {
                    echo "<div class='error'>❌ Manifest is not valid JSON</div>";
                }
            } else {
                echo "<div class='error'>❌ Manifest file missing</div>";
            }
            
            $assetsPath = $buildPath . '/assets';
            if(is_dir($assetsPath)) {
                echo "<div class='success'>✅ Assets directory exists</div>";
                $assets = scandir($assetsPath);
                foreach($assets as $asset) {
                    if($asset != '.' && $asset != '..') {
                        $size = filesize($assetsPath . '/' . $asset);
                        echo "<div class='file-item'>📄 {$asset} (" . number_format($size) . " bytes)</div>";
                    }
                }
            } else {
                echo "<div class='error'>❌ Assets directory missing</div>";
            }
        } else {
            echo "<div class='error'>❌ Build directory missing</div>";
        }
        ?>
    </div>

    <div class="debug-section info">
        <h2>🌐 Laravel Check</h2>
        <?php
        $laravelPath = '../iruali';
        if(is_dir($laravelPath)) {
            echo "<div class='success'>✅ Laravel app directory exists</div>";
            
            $envPath = $laravelPath . '/.env';
            if(file_exists($envPath)) {
                echo "<div class='success'>✅ .env file exists</div>";
            } else {
                echo "<div class='warning'>⚠️ .env file missing</div>";
            }
            
            $storagePath = $laravelPath . '/storage';
            if(is_dir($storagePath)) {
                echo "<div class='success'>✅ Storage directory exists</div>";
                if(is_writable($storagePath)) {
                    echo "<div class='success'>✅ Storage is writable</div>";
                } else {
                    echo "<div class='error'>❌ Storage is not writable</div>";
                }
            } else {
                echo "<div class='error'>❌ Storage directory missing</div>";
            }
        } else {
            echo "<div class='error'>❌ Laravel app directory missing</div>";
        }
        ?>
    </div>

    <div class="debug-section warning">
        <h2>🚨 Common Issues & Solutions</h2>
        <ul>
            <li><strong>Build files missing:</strong> Run <code>git pull origin main</code> on cPanel</li>
            <li><strong>CSS/JS not loading:</strong> Check if build files are in public_html/build/</li>
            <li><strong>500 errors:</strong> Check Laravel logs in iruali/storage/logs/</li>
            <li><strong>Permission issues:</strong> Run <code>chmod -R 755 storage bootstrap/cache</code></li>
            <li><strong>Cache issues:</strong> Run <code>php artisan cache:clear</code></li>
        </ul>
    </div>

    <div class="debug-section info">
        <h2>📋 Next Steps</h2>
        <ol>
            <li>Check if build files exist in public_html/build/</li>
            <li>Verify Laravel app is in the correct location</li>
            <li>Check file permissions</li>
            <li>Clear Laravel caches</li>
            <li>Check error logs</li>
        </ol>
    </div>

    <div class="debug-section success">
        <h2>✅ Quick Fix Commands</h2>
        <pre>
# On your cPanel server:
git pull origin main
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
rm -rf public_html/build
cp -r iruali/public/build public_html/
chmod -R 755 storage bootstrap/cache
        </pre>
    </div>
</body>
</html> 