<?php
require __DIR__ . '/vendor/autoload.php';

if (class_exists('Piseth\BakongKhqr\BakongKHQR')) {
    $methods = get_class_methods('Piseth\BakongKhqr\BakongKHQR');
    echo "Methods found:\n";
    print_r($methods);
} else {
    echo "Class not found.\n";
}