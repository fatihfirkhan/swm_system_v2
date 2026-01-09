<?php
/**
 * PHPMailer Autoloader
 * Simple autoloader for PHPMailer classes
 */

spl_autoload_register(function ($class) {
    // PHPMailer namespace prefix
    $prefix = 'PHPMailer\\PHPMailer\\';

    // Base directory for PHPMailer source files
    $base_dir = __DIR__ . '/phpmailer/phpmailer/src/';

    // Check if the class uses the PHPMailer namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Build the file path
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
