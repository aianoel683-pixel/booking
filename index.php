<?php
session_start();

if (isset($_SESSION['user_id'])) {
    $user_role = $_SESSION['user_role'];
    switch ($user_role) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'front_desk':
            header('Location: front_desk/dashboard.php');
            break;
        case 'housekeeping':
            header('Location: housekeeping/dashboard.php');
            break;
        case 'manager':
            header('Location: admin/dashboard.php');
            break;
        default:
            header('Location: landing.php');
            break;
    }
    exit();
}

if (isset($_SESSION['registered_guest_id'])) {
    header('Location: guest/dashboard.php');
    exit();
}

header('Location: landing.php');
exit();
?>
