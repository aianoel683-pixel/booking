<?php
session_start();
require_once '../config/database.php';

// Check if user is admin/manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Admin Dashboard";

// Get dashboard statistics
$db = db();

// Handle Approve/Reject Extension
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['approve_extension', 'reject_extension'])) {
    $ext_id = (int)$_POST['ext_id'];
    
    // Fetch extension details
    $ext = $db->prepare("SELECT * FROM booking_extensions WHERE id = :id AND status = 'pending'");
    $ext->execute([':id' => $ext_id]);
    $request = $ext->fetch(PDO::FETCH_ASSOC);
    
    if ($request) {
        if ($_POST['action'] === 'approve_extension') {
            // Update booking date
            $b_stmt = $db->query("SELECT b.*, r.price as room_price, rc.base_price FROM bookings b JOIN rooms r ON b.room_id = r.id LEFT JOIN room_categories rc ON r.category_id = rc.id WHERE b.id = " . $request['booking_id']);
            $booking_info = $b_stmt->fetch(PDO::FETCH_ASSOC);
            
            $price_per_night = $booking_info['room_price'] ?: $booking_info['base_price'];
            
            $old_date = new DateTime($booking_info['check_out_date']);
            $new_date = new DateTime($request['requested_date']);
            $diff = $old_date->diff($new_date)->days;
            
            if ($diff > 0) {
                $add_cost = $diff * $price_per_night;
                $db->prepare("UPDATE bookings SET check_out_date = :nd, total_amount = total_amount + :ac WHERE id = :bid")
                   ->execute([':nd' => $request['requested_date'], ':ac' => $add_cost, ':bid' => $request['booking_id']]);
                   
                $db->prepare("UPDATE booking_extensions SET status = 'approved' WHERE id = :id")->execute([':id' => $ext_id]);
                $success_msg = "Extension approved. Booking updated.";
            }
        } else {
            $db->prepare("UPDATE booking_extensions SET status = 'rejected' WHERE id = :id")->execute([':id' => $ext_id]);
            $success_msg = "Extension request rejected.";
        }
    }
}

// Fetch Pending Requests
$pending_requests = [];
try {
    $pending_requests = $db->query("
        SELECT be.*, b.check_in_date, b.check_out_date as current_check_out, g.first_name, g.last_name, r.room_number 
        FROM booking_extensions be 
        JOIN bookings b ON be.booking_id = b.id 
        JOIN guests g ON b.guest_id = g.id 
        JOIN rooms r ON b.room_id = r.id 
        WHERE be.status = 'pending'
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Total bookings
$stmt = $db->query("SELECT COUNT(*) as total FROM bookings");
$total_bookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Available rooms
$stmt = $db->query("SELECT COUNT(*) as total FROM rooms WHERE status = 'available'");
$available_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Occupied rooms
$stmt = $db->query("SELECT COUNT(*) as total FROM rooms WHERE status = 'occupied'");
$occupied_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Reserved rooms
$stmt = $db->query("SELECT COUNT(*) as total FROM rooms WHERE status = 'reserved'");
$reserved_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Today's check-ins
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE check_in_date = :today AND status = 'confirmed'");
$stmt->bindParam(':today', $today);
$stmt->execute();
$today_checkins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Today's check-outs
$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE check_out_date = :today AND status = 'checked_in'");
$stmt->bindParam(':today', $today);
$stmt->execute();
$today_checkouts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent bookings
$stmt = $db->query("SELECT b.*, g.first_name, g.last_name, r.room_number 
                   FROM bookings b 
                   JOIN guests g ON b.guest_id = g.id 
                   JOIN rooms r ON b.room_id = r.id 
                   ORDER BY b.created_at DESC LIMIT 5");
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Revenue this month
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as revenue 
                      FROM bookings 
                      WHERE created_at BETWEEN :start AND :end AND payment_status = 'paid'");
$stmt->bindParam(':start', $month_start);
$stmt->bindParam(':end', $month_end);
$stmt->execute();
$monthly_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'];

// System Monitoring Stats
$disk_free = disk_free_space(".");
$disk_total = disk_total_space(".");
$disk_used = $disk_total - $disk_free;
$disk_percentage = round(($disk_used / $disk_total) * 100);

$db_uptime_q = $db->query("SHOW STATUS LIKE 'Uptime'");
$db_uptime = $db_uptime_q ? $db_uptime_q->fetch(PDO::FETCH_ASSOC)['Value'] : 0;
$db_uptime_hours = floor($db_uptime / 3600);

$db_threads_q = $db->query("SHOW STATUS LIKE 'Threads_connected'");
$db_threads = $db_threads_q ? $db_threads_q->fetch(PDO::FETCH_ASSOC)['Value'] : 0;

$memory_usage = round(memory_get_usage() / 1024 / 1024, 2); // MB
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$php_version = phpversion();
$client_ip = $_SERVER['REMOTE_ADDR'];

include '../includes/admin_header.php';
?>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card admin-card">
            <div class="card-body dashboard-metric">
                <div>
                    <div class="label">Total Bookings</div>
                    <div class="value"><?php echo (int)$total_bookings; ?></div>
                </div>
                <i class="bi bi-calendar-check metric-icon text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card admin-card">
            <div class="card-body dashboard-metric">
                <div>
                    <div class="label">Available Rooms</div>
                    <div class="value"><?php echo (int)$available_rooms; ?></div>
                </div>
                <i class="bi bi-door-open metric-icon text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card admin-card">
            <div class="card-body dashboard-metric">
                <div>
                    <div class="label">Occupied Rooms</div>
                    <div class="value"><?php echo (int)$occupied_rooms; ?></div>
                </div>
                <i class="bi bi-door-closed metric-icon text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card admin-card">
            <div class="card-body dashboard-metric">
                <div>
                    <div class="label">Reserved Rooms</div>
                    <div class="value"><?php echo (int)$reserved_rooms; ?></div>
                </div>
                <i class="bi bi-bookmark metric-icon text-warning"></i>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($pending_requests)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card admin-card border-warning">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0"><i class="bi bi-exclamation-circle me-2"></i>Pending Stay Extension Requests</h6>
                <span class="badge bg-dark"><?php echo count($pending_requests); ?> Pending</span>
            </div>
            <div class="card-body">
                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Room</th>
                                <th>Current Check-out</th>
                                <th>Requested New Date</th>
                                <th>Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $req): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($req['room_number']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($req['current_check_out'])); ?></td>
                                <td class="fw-bold text-primary"><?php echo date('M d, Y', strtotime($req['requested_date'])); ?></td>
                                <td><?php echo htmlspecialchars($req['reason']); ?></td>
                                <td>
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="ext_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" name="action" value="approve_extension" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                        <button type="submit" name="action" value="reject_extension" class="btn btn-sm btn-danger" onclick="return confirm('Reject this request?');">
                                            <i class="bi bi-x-lg"></i> Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card admin-card">
            <div class="card-header">
                <h6 class="card-title mb-0">Revenue This Month</h6>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="display-6">₱<?php echo number_format($monthly_revenue, 2); ?></div>
                    <i class="bi bi-cash-coin metric-icon text-success"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card admin-card">
            <div class="card-header">
                <h6 class="card-title mb-0">Occupancy Overview</h6>
            </div>
            <div class="card-body">
                <canvas id="occupancyChart" class="admin-chart"></canvas>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <!-- Quick Actions -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <a href="rooms.php?action=add" class="btn btn-outline-primary w-100">
                            <i class="bi bi-plus-circle me-2"></i>Add Room
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="bookings.php?action=create" class="btn btn-outline-success w-100">
                            <i class="bi bi-calendar-plus me-2"></i>Create Booking
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="users.php?action=add" class="btn btn-outline-info w-100">
                            <i class="bi bi-person-plus me-2"></i>Add User
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="reports.php" class="btn btn-outline-warning w-100">
                            <i class="bi bi-bar-chart me-2"></i>View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Today's Schedule -->
    <div class="col-md-6 mb-4">
        <div class="card admin-card">
            <div class="card-header">
                <h6 class="card-title mb-0">Today's Schedule</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span class="badge bg-success">
                        <i class="bi bi-box-arrow-in-right me-1"></i>
                        Check-ins: <?php echo $today_checkins; ?>
                    </span>
                    <span class="badge bg-danger">
                        <i class="bi bi-box-arrow-right me-1"></i>
                        Check-outs: <?php echo $today_checkouts; ?>
                    </span>
                </div>
                <div class="progress progress-slim mb-3">
                    <div class="progress-bar bg-success" style="width: <?php echo ($today_checkins / max($today_checkins + $today_checkouts, 1)) * 100; ?>%"></div>
                    <div class="progress-bar bg-danger" style="width: <?php echo ($today_checkouts / max($today_checkins + $today_checkouts, 1)) * 100; ?>%"></div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- System Monitoring -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card admin-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">System Resources</h6>
                <span class="badge bg-success">System Online</span>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Disk Usage</span>
                        <span class="small fw-bold"><?php echo round($disk_used/1024/1024/1024, 2); ?> GB / <?php echo round($disk_total/1024/1024/1024, 2); ?> GB</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar <?php echo $disk_percentage > 90 ? 'bg-danger' : 'bg-info'; ?>" role="progressbar" style="width: <?php echo $disk_percentage; ?>%"></div>
                    </div>
                    <div class="text-end"><small class="text-muted"><?php echo $disk_percentage; ?>% Used</small></div>
                </div>
                
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 border rounded bg-light text-center">
                            <div class="text-muted small mb-1">PHP Memory</div>
                            <div class="h5 mb-0 text-primary"><?php echo $memory_usage; ?> MB</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 border rounded bg-light text-center">
                            <div class="text-muted small mb-1">Active DB Connections</div>
                            <div class="h5 mb-0 text-success"><?php echo $db_threads; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card admin-card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0">Network & Server Info</h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Server Software</span>
                        <span class="text-muted small text-end" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($server_software); ?>"><?php echo htmlspecialchars($server_software); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>PHP Version</span>
                        <span class="badge bg-secondary">v<?php echo $php_version; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Database Uptime</span>
                        <span class="text-muted"><?php echo $db_uptime_hours; ?> hours</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Client IP Address</span>
                        <span class="font-monospace bg-light px-2 py-1 rounded"><?php echo $client_ip; ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Recent Bookings -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Recent Bookings</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-modern">
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Room</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                            <tr>
                                <td><?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?></td>
                                <td><?php echo $booking['room_number']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></td>
                                <td>₱<?php echo number_format($booking['total_amount'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch($booking['status']) {
                                            case 'confirmed': echo 'warning'; break;
                                            case 'checked_in': echo 'success'; break;
                                            case 'checked_out': echo 'info'; break;
                                            case 'cancelled': echo 'danger'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        var ctx = document.getElementById('occupancyChart');
        if (!ctx) return;
        var chart = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Available','Occupied','Reserved'],
                datasets: [{
                    data: [<?php echo (int)$available_rooms; ?>, <?php echo (int)$occupied_rooms; ?>, <?php echo (int)$reserved_rooms; ?>],
                    backgroundColor: ['#2a9d8f','#e76f51','#e9c46a'],
                    hoverOffset: 4
                }]
            },
            options: {
                plugins: { legend: { position: 'bottom' } },
                cutout: '60%'
            }
        });
    })();
</script>

<?php include '../includes/admin_footer.php'; ?>
