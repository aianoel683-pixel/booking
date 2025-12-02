<?php 
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); } 

// Check for pending extension requests for the sidebar badge
$pending_ext_count = 0;
if (isset($db)) {
    try {
        $stmt_ext = $db->query("SELECT COUNT(*) as total FROM booking_extensions WHERE status = 'pending'");
        if ($stmt_ext) {
            $pending_ext_count = $stmt_ext->fetch(PDO::FETCH_ASSOC)['total'];
        }
    } catch (Exception $e) {
        // Silent fail if table doesn't exist yet or DB error
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin'; ?> · Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
    <link href="../assets/admin.css" rel="stylesheet">
</head>
<body class="admin-layout">
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-none d-md-block admin-sidebar py-3">
                <div class="position-sticky">
                    <div class="text-center py-2">
                        <h5 class="brand mb-0">Admin Panel</h5>
                        <small class="text-muted-soft"><?php echo $_SESSION['user_role'] ?? 'Admin'; ?></small>
                    </div>
                    <ul class="nav flex-column mt-3">
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?> d-flex justify-content-between align-items-center" href="../admin/dashboard.php">
                                <span><i class="bi bi-speedometer2 me-2"></i>Dashboard</span>
                                <?php if ($pending_ext_count > 0): ?>
                                    <span class="badge bg-danger rounded-pill"><?php echo $pending_ext_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'rooms.php' ? 'active' : ''; ?>" href="../admin/rooms.php">
                                <i class="bi bi-door-closed me-2"></i>Rooms
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" href="../admin/categories.php">
                                <i class="bi bi-grid-3x3-gap me-2"></i>Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'bookings.php' ? 'active' : ''; ?>" href="../admin/bookings.php">
                                <i class="bi bi-calendar-check me-2"></i>Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="../admin/users.php">
                                <i class="bi bi-people me-2"></i>Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="../admin/reports.php">
                                <i class="bi bi-bar-chart me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="../admin/settings.php">
                                <i class="bi bi-gear me-2"></i>Settings
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link text-danger" href="../auth/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-10 ms-sm-auto px-4">
                <div class="admin-header-bar d-flex justify-content-between align-items-center pt-3 pb-2 mb-3">
                    <div>
                        <h1 class="h3 mb-0"><?php echo $page_title ?? 'Admin'; ?></h1>
                        <small class="text-muted-soft">Welcome, <?php echo $_SESSION['first_name'] ?? 'User'; ?></small>
                    </div>
                </div>
