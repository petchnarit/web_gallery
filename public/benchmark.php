<?php
// php_test.php

echo "<h2>PHP Environment Test</h2>";

// 1. Basic PHP info
echo "<h3>1. PHP Info</h3>";
echo "<pre>";
echo "PHP Version: " . PHP_VERSION . PHP_EOL;
echo "SAPI: " . PHP_SAPI . PHP_EOL;
echo "Memory Limit: " . ini_get('memory_limit') . PHP_EOL;
echo "Max Execution Time: " . ini_get('max_execution_time') . "s" . PHP_EOL;
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . PHP_EOL;
echo "Post Max Size: " . ini_get('post_max_size') . PHP_EOL;
echo "Default Timezone: " . date_default_timezone_get() . PHP_EOL;
echo "</pre>";

// 2. Check Extensions
$extensions = ['gd', 'imagick', 'mbstring', 'intl', 'pdo', 'curl', 'memcached', 'apcu'];
echo "<h3>2. PHP Extensions</h3>";
echo "<ul>";
foreach ($extensions as $ext) {
    echo "<li>$ext: " . (extension_loaded($ext) ? "<span style='color:green'>Loaded</span>" : "<span style='color:red'>Not Loaded</span>") . "</li>";
}
echo "</ul>";

// 3. Opcache status
echo "<h3>3. Opcache Status</h3>";
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    echo "<pre>";
    echo "Opcache Enabled: " . ($status['opcache_enabled'] ? 'Yes' : 'No') . PHP_EOL;
    echo "Memory Usage: " . round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB / " . round($status['memory_usage']['free_memory'] / 1024 / 1024, 2) . " MB free" . PHP_EOL;
    echo "Cached Scripts: " . $status['opcache_statistics']['num_cached_scripts'] . PHP_EOL;
    echo "</pre>";
} else {
    echo "<p>Opcache not available.</p>";
}

// 4. Test Session
echo "<h3>4. Session Test</h3>";
session_start();
if (!isset($_SESSION['test'])) {
    $_SESSION['test'] = 1;
    echo "Session started, value set to 1";
} else {
    $_SESSION['test']++;
    echo "Session value incremented: " . $_SESSION['test'];
}

// 5. Test File Upload (just temp directory check)
echo "<h3>5. File Upload Temp Dir</h3>";
$tmpDir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
echo "Upload Temp Directory: $tmpDir" . PHP_EOL;
echo "Writable: " . (is_writable($tmpDir) ? "<span style='color:green'>Yes</span>" : "<span style='color:red'>No</span>") . PHP_EOL;

// 6. Test curl
echo "<h3>6. cURL Test</h3>";
if (function_exists('curl_version')) {
    $curl = curl_version();
    echo "cURL Version: " . $curl['version'] . PHP_EOL;
    echo "SSL Version: " . $curl['ssl_version'] . PHP_EOL;
} else {
    echo "cURL not installed.";
}
