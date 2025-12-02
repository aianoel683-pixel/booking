<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
    <link href="../assets/admin.css" rel="stylesheet">
    <style>
        .room-status-available { background-color: #d4edda; }
        .room-status-occupied { background-color: #f8d7da; }
        .room-status-reserved { background-color: #fff3cd; }
        .room-status-maintenance { background-color: #e2e3e5; }
        .room-status-cleaning { background-color: #cce5ff; }
    </style>
</head>
<body class="admin-layout">
    <div class="container-fluid">
        <?php if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'front_desk'): ?>
        <div class="admin-topbar d-flex justify-content-between align-items-center px-3 py-0">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-light d-md-none" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <span class="brand-small d-md-none">Hotel System</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-bell"></i>
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i><?php echo $_SESSION['username'] ?? 'Account'; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../admin/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block admin-sidebar py-3">
                <div class="position-sticky">
                    <div class="text-center py-4">
                        <h5 class="text-primary">Hotel Booking System</h5>
                        <small class="text-muted"><?php echo $_SESSION['user_role'] ?? 'Guest'; ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <?php if (in_array($_SESSION['user_role'], ['admin', 'manager'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="../admin/dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/rooms.php">
                                <i class="bi bi-door-closed me-2"></i>Room Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/users.php">
                                <i class="bi bi-people me-2"></i>User Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/bookings.php">
                                <i class="bi bi-calendar-check me-2"></i>Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/reports.php">
                                <i class="bi bi-bar-chart me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/settings.php">
                                <i class="bi bi-gear me-2"></i>Settings
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($_SESSION['user_role'] == 'front_desk'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../front_desk/dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../front_desk/checkin.php">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Check-In
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../front_desk/checkout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Check-Out
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../front_desk/bookings.php">
                                <i class="bi bi-calendar-check me-2"></i>Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../front_desk/rooms.php">
                                <i class="bi bi-door-closed me-2"></i>Rooms
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../front_desk/guests.php">
                                <i class="bi bi-person me-2"></i>Guests
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($_SESSION['user_role'] == 'housekeeping'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../housekeeping/dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../housekeeping/tasks.php">
                                <i class="bi bi-list-task me-2"></i>Tasks
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../housekeeping/rooms.php">
                                <i class="bi bi-door-closed me-2"></i>Room Status
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../housekeeping/maintenance.php">
                                <i class="bi bi-tools me-2"></i>Maintenance
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item mt-3">
                            <a class="nav-link text-danger" href="../auth/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-10 ms-sm-auto px-4">
                <?php if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'front_desk'): ?>
                <div class="admin-header-bar compact d-flex justify-content-between align-items-center py-0 mb-0">
                    <div>
                        <h1 class="h3 mb-0"><?php echo $page_title ?? 'Dashboard'; ?></h1>
                        <small class="text-muted-soft">Welcome, <?php echo $_SESSION['first_name'] ?? 'User'; ?></small>
                    </div>
                </div>
                <?php endif; ?>
