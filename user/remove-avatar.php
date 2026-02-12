<?php
// file: user/remove-avatar.php
require_once 'auth_check.php';

$old_image = $user['profile_image'] ?? 'default-avatar.png';

if ($old_image !== 'default-avatar.png') {
    $stmt = $conn->prepare("UPDATE users SET profile_image = 'default-avatar.png' WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        // Delete the physical file
        $full_path = '../' . $old_image;
        if (file_exists($full_path)) {
            unlink($full_path);
        }
        $_SESSION['success'] = "Profile picture removed.";
    } else {
        $_SESSION['error'] = "Failed to remove profile picture.";
    }
    $stmt->close();
}

header("Location: profile.php");
exit;