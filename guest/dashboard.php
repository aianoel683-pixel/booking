<?php
session_start();
require_once '../config/database.php';

if (isset($_GET['logout'])) {
    unset($_SESSION['registered_guest_id']);
    unset($_SESSION['registered_guest_name']);
    header('Location: ../landing.php');
    exit();
}

if (!isset($_SESSION['registered_guest_id'])) {
    header('Location: ../landing.php');
    exit();
}

$db = db();
$gid = (int)$_SESSION['registered_guest_id'];

// Create extensions table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS booking_extensions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        booking_id INT NOT NULL,
        requested_date DATE NOT NULL,
        reason TEXT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {}

$success_msg = '';
$error_msg = '';

// Handle Extension Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_extension') {
    $booking_id = (int)$_POST['booking_id'];
    $new_date = $_POST['new_date'];
    $reason = trim($_POST['reason'] ?? '');
    
    if ($booking_id && $new_date) {
        $chk = $db->prepare("SELECT check_out_date FROM bookings WHERE id = :bid AND guest_id = :gid AND status = 'checked_in'");
        $chk->execute([':bid' => $booking_id, ':gid' => $gid]);
        $current_booking = $chk->fetch(PDO::FETCH_ASSOC);
        
        if ($current_booking) {
            if (strtotime($new_date) > strtotime($current_booking['check_out_date'])) {
                // Check if pending request already exists
                $pending = $db->prepare("SELECT id FROM booking_extensions WHERE booking_id = :bid AND status = 'pending'");
                $pending->execute([':bid' => $booking_id]);
                if ($pending->rowCount() == 0) {
                    $ins = $db->prepare("INSERT INTO booking_extensions (booking_id, requested_date, reason) VALUES (:bid, :rd, :r)");
                    if ($ins->execute([':bid' => $booking_id, ':rd' => $new_date, ':r' => $reason])) {
                        $success_msg = "Extension request submitted. Waiting for approval.";
                    } else {
                        $error_msg = "Failed to submit request.";
                    }
                } else {
                    $error_msg = "You already have a pending request for this booking.";
                }
            } else {
                $error_msg = "New date must be after current check-out date.";
            }
        } else {
            $error_msg = "Invalid booking or status.";
        }
    }
}

// Fetch Guest Details
$stmt = $db->prepare('SELECT * FROM guests WHERE id = :id');
$stmt->execute([':id' => $gid]);
$guest = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Hotel Info
$hotel = null;
try {
    $hotel = $db->query("SELECT * FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch Guest Bookings
$bookings = [];
$active_bookings_count = 0;
$total_spent = 0;
try {
    $stmt = $db->prepare("
        SELECT b.*, r.room_number, rc.name as room_type, rc.base_price 
        FROM bookings b 
        LEFT JOIN rooms r ON b.room_id = r.id 
        LEFT JOIN room_categories rc ON r.category_id = rc.id 
        WHERE b.guest_id = :guest_id 
        ORDER BY b.check_in_date DESC
    ");
    $stmt->execute([':guest_id' => $gid]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bookings as $b) {
        if (in_array($b['status'], ['confirmed', 'checked_in'])) {
            $active_bookings_count++;
        }
        if ($b['status'] == 'checked_out') {
            $total_spent += $b['total_amount'];
        }
    }
} catch (Exception $e) {}

// Fetch pending extensions
$pending_extensions = [];
try {
    $pe_stmt = $db->prepare("SELECT booking_id FROM booking_extensions WHERE booking_id IN (SELECT id FROM bookings WHERE guest_id = :gid) AND status = 'pending'");
    $pe_stmt->execute([':gid' => $gid]);
    $pending_extensions = $pe_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Fetch Room Categories for "Explore" section
$room_categories = [];
try {
    $room_categories = $db->query('SELECT * FROM room_categories')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Dashboard - <?php echo htmlspecialchars($hotel['hotel_name'] ?? 'Hotel'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --primary-color: #1a1a1a;
            --accent-color: #d4af37; /* Gold */
            --accent-hover: #b39020;
            --bg-light: #f8f9fa;
            --text-muted: #6c757d;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--primary-color);
        }
        h1, h2, h3, h4, h5, h6, .brand-font {
            font-family: 'Playfair Display', serif;
        }
        
        /* Sidebar / Navbar */
        .navbar-brand {
            font-weight: 700;
            color: var(--accent-color) !important;
            font-size: 1.5rem;
        }
        
        /* Cards */
        .dashboard-card {
            border: none;
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            height: 100%;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .icon-box {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        .bg-gold-light { background-color: rgba(212, 175, 55, 0.1); color: var(--accent-color); }
        .bg-blue-light { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; }
        .bg-green-light { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
        
        /* Buttons */
        .btn-gold {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }
        .btn-gold:hover {
            background-color: var(--accent-hover);
            color: white;
        }
        .btn-outline-gold {
            border: 2px solid var(--accent-color);
            color: var(--accent-color);
            border-radius: 8px;
        }
        .btn-outline-gold:hover {
            background-color: var(--accent-color);
            color: white;
        }
        
        /* Table */
        .table-custom th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom-width: 1px;
        }
        
        .status-badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 600;
            border-radius: 0.25rem;
        }
        .status-confirmed { background-color: #cff4fc; color: #055160; }
        .status-checked_in { background-color: #d1e7dd; color: #0f5132; }
        .status-checked_out { background-color: #e2e3e5; color: #41464b; }
        .status-cancelled { background-color: #f8d7da; color: #842029; }

        .room-card img {
            height: 200px;
            object-fit: cover;
            border-radius: 15px 15px 0 0;
        }
        .room-price {
            color: var(--accent-color);
            font-weight: 700;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand brand-font" href="#">
                <i class="bi bi-building me-2"></i><?php echo htmlspecialchars($hotel['hotel_name'] ?? 'Hotel System'); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3">
                        <span class="text-muted">Welcome, <?php echo htmlspecialchars($guest['first_name']); ?></span>
                    </li>
                    <li class="nav-item">
                        <a href="?logout=1" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-5 px-4">
        
        <?php if($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Welcome Section -->
        <div class="row mb-5 align-items-center">
            <div class="col-md-8">
                <h2 class="display-6 fw-bold mb-2">Hello, <?php echo htmlspecialchars($guest['first_name']); ?>! 👋</h2>
                <p class="text-muted lead">Welcome to your personal dashboard. Manage your stays and explore our rooms.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="book_room.php" class="btn btn-gold btn-lg">
                    <i class="bi bi-calendar-plus me-2"></i>Book a Room
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="dashboard-card p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Total Bookings</p>
                            <h3 class="fw-bold"><?php echo count($bookings); ?></h3>
                        </div>
                        <div class="icon-box bg-blue-light">
                            <i class="bi bi-journal-bookmark"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Active Stays</p>
                            <h3 class="fw-bold"><?php echo $active_bookings_count; ?></h3>
                        </div>
                        <div class="icon-box bg-green-light">
                            <i class="bi bi-door-open"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Total Spent</p>
                            <h3 class="fw-bold"><?php echo $hotel['currency'] ?? 'PHP'; ?> <?php echo number_format($total_spent, 2); ?></h3>
                        </div>
                        <div class="icon-box bg-gold-light">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Recent Bookings -->
            <div class="col-lg-8">
                <div class="dashboard-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0 fw-bold">Your Bookings</h5>
                        <a href="bookings.php" class="btn btn-sm btn-link text-decoration-none" style="color: var(--accent-color);">View All</a>
                    </div>
                    
                    <?php if (empty($bookings)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x display-4 mb-3 d-block"></i>
                            <p>No bookings found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Room</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($bookings, 0, 5) as $booking): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($booking['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($booking['room_type'] ?? 'N/A'); ?></div>
                                            <?php if ($booking['room_number']): ?>
                                                <small class="text-muted">Room <?php echo htmlspecialchars($booking['room_number']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></td>
                                        <td><?php echo number_format($booking['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($booking['status'] === 'confirmed'): ?>
                                                <button class="btn btn-sm btn-outline-dark" onclick="showQR(<?php echo $booking['id']; ?>)">
                                                    <i class="bi bi-qr-code me-1"></i>
                                                </button>
                                            <?php elseif ($booking['status'] === 'checked_in'): ?>
                                                <?php if (in_array($booking['id'], $pending_extensions)): ?>
                                                    <span class="badge bg-warning text-dark">Extension Pending</span>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-gold" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#extensionModal" 
                                                        data-id="<?php echo $booking['id']; ?>"
                                                        data-current="<?php echo $booking['check_out_date']; ?>"
                                                    >Extend Stay</button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Card -->
            <div class="col-lg-4">
                <div class="dashboard-card p-4">
                    <h5 class="mb-4 fw-bold">My Profile</h5>
                    <div class="text-center mb-4">
                        <?php if (!empty($guest['id_photo_url'])): ?>
                            <img src="../<?php echo htmlspecialchars($guest['id_photo_url']); ?>" alt="Profile" class="rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 80px; height: 80px;">
                                <i class="bi bi-person fs-1 text-secondary"></i>
                            </div>
                        <?php endif; ?>
                        <h5 class="mt-2 mb-1"><?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']); ?></h5>
                        <p class="text-muted small"><?php echo htmlspecialchars($guest['email']); ?></p>
                    </div>
                    
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-telephone text-muted me-3"></i>
                        <span><?php echo htmlspecialchars($guest['phone'] ?? 'No phone'); ?></span>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-geo-alt text-muted me-3"></i>
                        <span><?php echo htmlspecialchars($guest['address'] ?? 'No address'); ?></span>
                    </div>
                    <div class="d-grid mt-4">
                        <a href="edit_profile.php" class="btn btn-outline-secondary btn-sm">Edit Profile</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Room Categories -->
        <div class="mt-5" id="room-categories">
            <h3 class="fw-bold mb-4">Our Rooms</h3>
            <div class="row g-4">
                <?php foreach ($room_categories as $cat): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="dashboard-card room-card h-100 overflow-hidden">
                        <!-- Placeholder Image since we don't have real URLs in DB yet mostly -->
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                            <i class="bi bi-image text-muted fs-1"></i>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="card-title fw-bold"><?php echo htmlspecialchars($cat['name']); ?></h5>
                            <p class="card-text text-muted small mb-3 text-truncate"><?php echo htmlspecialchars($cat['description']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="room-price">
                                    <small class="fs-6 text-muted fw-normal">from</small> 
                                    <?php echo number_format($cat['base_price']); ?>
                                </div>
                                <button class="btn btn-sm btn-outline-gold" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#roomModal" 
                                    data-id="<?php echo $cat['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                                    data-desc="<?php echo htmlspecialchars($cat['description']); ?>"
                                    data-price="<?php echo number_format($cat['base_price']); ?>"
                                    data-amenities="<?php echo htmlspecialchars($cat['amenities']); ?>"
                                    data-occupancy="<?php echo $cat['max_occupancy']; ?>"
                                >Details</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Room Details Modal -->
    <div class="modal fade" id="roomModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold brand-font" id="roomModalLabel">Room Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="bg-light rounded mb-3 d-flex align-items-center justify-content-center" style="height: 200px;">
                        <i class="bi bi-image text-muted fs-1"></i>
                    </div>
                    <h4 class="fw-bold mb-2" id="modalRoomName"></h4>
                    <p class="text-muted mb-4" id="modalRoomDesc"></p>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="d-flex align-items-center text-muted">
                                <i class="bi bi-people me-2"></i>
                                <span>Max Occupancy: <span id="modalRoomOccupancy" class="fw-bold text-dark"></span></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center text-muted">
                                <i class="bi bi-tag me-2"></i>
                                <span>Price: <span class="fw-bold text-dark" style="color: var(--accent-color) !important;">₱<span id="modalRoomPrice"></span></span></span>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2">Amenities</h6>
                    <p class="text-muted small" id="modalRoomAmenities"></p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="modalBookBtn" class="btn btn-gold">Book Now</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Extension Request Modal -->
    <div class="modal fade" id="extensionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold brand-font">Request Stay Extension</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="request_extension">
                        <input type="hidden" name="booking_id" id="extBookingId">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Current Check-out</label>
                            <input type="text" class="form-control" id="extCurrentDate" readonly disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Check-out Date</label>
                            <input type="date" class="form-control" name="new_date" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason (Optional)</label>
                            <textarea class="form-control" name="reason" rows="2" placeholder="Why do you need to extend?"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-gold">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrModal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-bold">Booking QR Code</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="qrcode" class="d-flex justify-content-center my-3"></div>
                    <p class="small text-muted mb-0">Scan at front desk to check in</p>
                    <p class="fw-bold text-primary mb-3">Booking #<span id="qrBookingId"></span></p>
                    <button class="btn btn-gold w-100 btn-sm" id="downloadQR">
                        <i class="bi bi-download me-2"></i>Download
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white mt-5 py-4 border-top">
        <div class="container text-center">
            <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($hotel['hotel_name'] ?? 'Hotel System'); ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var extensionModal = document.getElementById('extensionModal');
        if (extensionModal) {
            extensionModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var id = button.getAttribute('data-id');
                var current = button.getAttribute('data-current');
                
                extensionModal.querySelector('#extBookingId').value = id;
                extensionModal.querySelector('#extCurrentDate').value = current;
                
                // Set min date to current checkout + 1 day
                var minDate = new Date(current);
                minDate.setDate(minDate.getDate() + 1);
                extensionModal.querySelector('input[name="new_date"]').min = minDate.toISOString().split('T')[0];
            });
        }

        function showQR(bookingId) {
            var modal = new bootstrap.Modal(document.getElementById('qrModal'));
            var qrContainer = document.getElementById('qrcode');
            var bookingIdSpan = document.getElementById('qrBookingId');
            
            // Clear previous QR
            qrContainer.innerHTML = '';
            bookingIdSpan.textContent = bookingId;
            
            // Generate QR
            var qrData = 'BOOKING:' + bookingId;
            new QRCode(qrContainer, {
                text: qrData,
                width: 180,
                height: 180
            });
            
            // Handle Download
            document.getElementById('downloadQR').onclick = function() {
                var img = qrContainer.querySelector('img');
                if (img && img.src) {
                    var link = document.createElement('a');
                    link.download = 'booking-qr-' + bookingId + '.png';
                    link.href = img.src;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                } else {
                    // Fallback if qrcodejs uses canvas
                    var canvas = qrContainer.querySelector('canvas');
                    if (canvas) {
                        var link = document.createElement('a');
                        link.download = 'booking-qr-' + bookingId + '.png';
                        link.href = canvas.toDataURL("image/png");
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    }
                }
            };
            
            modal.show();
        }

        var roomModal = document.getElementById('roomModal')
        roomModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget
            var id = button.getAttribute('data-id')
            var name = button.getAttribute('data-name')
            var desc = button.getAttribute('data-desc')
            var price = button.getAttribute('data-price')
            var amenities = button.getAttribute('data-amenities')
            var occupancy = button.getAttribute('data-occupancy')
            
            var modalTitle = roomModal.querySelector('.modal-title')
            var modalName = roomModal.querySelector('#modalRoomName')
            var modalDesc = roomModal.querySelector('#modalRoomDesc')
            var modalPrice = roomModal.querySelector('#modalRoomPrice')
            var modalAmenities = roomModal.querySelector('#modalRoomAmenities')
            var modalOccupancy = roomModal.querySelector('#modalRoomOccupancy')
            var bookBtn = roomModal.querySelector('#modalBookBtn')
            
            modalName.textContent = name
            modalDesc.textContent = desc
            modalPrice.textContent = price
            modalAmenities.textContent = amenities
            modalOccupancy.textContent = occupancy
            bookBtn.href = 'book_room.php?cat_id=' + id
        })
    </script>
</body>
</html>
