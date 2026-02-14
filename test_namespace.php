<?php
require __DIR__ . '/vendor/autoload.php';

// Check if the correct class exists
if (class_exists('Piseth\BakongKhqr\KHQR')) {
    echo "✅ Correct namespace found!";
} else {
    echo "❌ Class not found. Check vendor/pisethchhun/bakong-khqr-php/src for the actual class name.";
}