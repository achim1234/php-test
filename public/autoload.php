<?php
#die('in autoload');
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
ini_set('display_errors', '0');
spl_autoload_register(function ($class)
{
    $prefix = 'Classes\\';

    if (str_starts_with($class, $prefix)) {
        $relativeClass = substr($class, strlen($prefix));

        $file = __DIR__ . '/Classes/' . str_replace('\\', '/', $relativeClass) . '.php';

        if(file_exists($file)) {
            require $file;
        }
    }
});
