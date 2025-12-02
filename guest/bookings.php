<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['registered_guest_id'])) {
    header('Location: ../landing.php');
    exit();
}

$db = db();
$gid = (int)$_SESSION['registered_guest_id'];

// Fetch Guest Bookings
$bookings = [];
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
} catch (Exception $e) {}

// Fetch Hotel Info
$hotel = null;
try {
    $hotel = $db->query("SELECT * FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - <?php echo htmlspecialchars($hotel['hotel_name'] ?? 'Hotel'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --primary-color: #1a1a1a;
            --accent-color: #d4af37;
            --bg-light: #f8f9fa;
            --text-muted: #6c757d;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--primary-color);
        }
        .brand-font { font-family: 'Playfair Display', serif; }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
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
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand brand-font" href="dashboard.php">
                <i class="bi bi-building me-2"></i><?php echo htmlspecialchars($hotel['hotel_name'] ?? 'Hotel System'); ?>
            </a>
            <div class="ms-auto">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold brand-font mb-0">My Bookings History</h3>
                    <a href="book_room.php" class="btn btn-primary" style="background-color: var(--accent-color); border-color: var(--accent-color);">
                        <i class="bi bi-plus-lg me-2"></i>New Booking
                    </a>
                </div>

                <div class="card p-4">
                    <?php if (empty($bookings)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x display-4 mb-3 d-block"></i>
                            <p>No bookings found.</p>
                            <a href="book_room.php" class="btn btn-outline-primary mt-2">Make your first booking</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Room Details</th>
                                        <th>Dates</th>
                                        <th>Guests</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($booking['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($booking['room_type'] ?? 'N/A'); ?></div>
                                            <?php if ($booking['room_number']): ?>
                                                <small class="text-muted">Room <?php echo htmlspecialchars($booking['room_number']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><i class="bi bi-box-arrow-in-right me-2 text-muted"></i><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></div>
                                            <div><i class="bi bi-box-arrow-right me-2 text-muted"></i><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo $booking['adults']; ?> Adults</div>
                                            <?php if ($booking['children'] > 0): ?>
                                            <small class="text-muted"><?php echo $booking['children']; ?> Children</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold">₱<?php echo number_format($booking['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($booking['status'] === 'confirmed'): ?>
                                                <button class="btn btn-sm btn-outline-dark" onclick="showQR(<?php echo $booking['id']; ?>)">
                                                    <i class="bi bi-qr-code me-1"></i>QR
                                                </button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
    </script>
</body>
</html>
