<?php
session_start();
require_once '../config/database.php';

// Check if user is admin/manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Booking Management";
$db = db();

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create_booking') {
        $guest_id = $_POST['guest_id'];
        $room_id = $_POST['room_id'];
        $check_in_date = $_POST['check_in_date'];
        $check_out_date = $_POST['check_out_date'];
        $adults = $_POST['adults'];
        $children = $_POST['children'];
        $special_requests = trim($_POST['special_requests']);
        
        // Calculate total amount
        $stmt = $db->prepare("SELECT rc.base_price FROM rooms r 
                              JOIN room_categories rc ON r.category_id = rc.id 
                              WHERE r.id = :room_id");
        $stmt->bindParam(':room_id', $room_id);
        $stmt->execute();
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $nights = (strtotime($check_out_date) - strtotime($check_in_date)) / (60 * 60 * 24);
        $total_amount = $room['base_price'] * $nights;
        
        try {
            $stmt = $db->prepare("INSERT INTO bookings (guest_id, room_id, check_in_date, check_out_date, 
                                  adults, children, total_amount, special_requests, created_by) 
                                  VALUES (:guest_id, :room_id, :check_in_date, :check_out_date, 
                                  :adults, :children, :total_amount, :special_requests, :created_by)");
            $stmt->bindParam(':guest_id', $guest_id);
            $stmt->bindParam(':room_id', $room_id);
            $stmt->bindParam(':check_in_date', $check_in_date);
            $stmt->bindParam(':check_out_date', $check_out_date);
            $stmt->bindParam(':adults', $adults);
            $stmt->bindParam(':children', $children);
            $stmt->bindParam(':total_amount', $total_amount);
            $stmt->bindParam(':special_requests', $special_requests);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->execute();
            
            // Update room status to reserved
            $stmt = $db->prepare("UPDATE rooms SET status = 'reserved' WHERE id = :room_id");
            $stmt->bindParam(':room_id', $room_id);
            $stmt->execute();
            
            $success = "Booking created successfully!";
        } catch (PDOException $e) {
            $error = "Error creating booking: " . $e->getMessage();
        }
    }
    elseif ($action == 'update_booking_status') {
        $booking_id = $_POST['booking_id'];
        $status = $_POST['status'];
        
        try {
            $stmt = $db->prepare("UPDATE bookings SET status = :status WHERE id = :id");
            $stmt->bindParam(':id', $booking_id);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            
            // Update room status based on booking status
            if ($status == 'checked_in') {
                $stmt = $db->prepare("UPDATE rooms r JOIN bookings b ON r.id = b.room_id 
                                      SET r.status = 'occupied' WHERE b.id = :id");
                $stmt->bindParam(':id', $booking_id);
                $stmt->execute();
            } elseif ($status == 'checked_out' || $status == 'cancelled') {
                $stmt = $db->prepare("UPDATE rooms r JOIN bookings b ON r.id = b.room_id 
                                      SET r.status = 'available', r.housekeeping_status = 'dirty' 
                                      WHERE b.id = :id");
                $stmt->bindParam(':id', $booking_id);
                $stmt->execute();
            }
            
            $success = "Booking status updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating booking: " . $e->getMessage();
        }
    }
    elseif ($action == 'delete_booking') {
        $booking_id = $_POST['booking_id'];
        
        try {
            // Get room ID before deleting
            $stmt = $db->prepare("SELECT room_id FROM bookings WHERE id = :id");
            $stmt->bindParam(':id', $booking_id);
            $stmt->execute();
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("DELETE FROM bookings WHERE id = :id");
            $stmt->bindParam(':id', $booking_id);
            $stmt->execute();
            
            // Update room status back to available
            $stmt = $db->prepare("UPDATE rooms SET status = 'available' WHERE id = :room_id");
            $stmt->bindParam(':room_id', $booking['room_id']);
            $stmt->execute();
            
            $success = "Booking deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting booking: " . $e->getMessage();
        }
    }
}

// Get all bookings with guest and room information
$stmt = $db->query("SELECT b.*, g.first_name, g.last_name, g.email, g.phone, 
                    r.room_number, rc.name as category_name, u.username as created_by_name
                    FROM bookings b 
                    JOIN guests g ON b.guest_id = g.id 
                    JOIN rooms r ON b.room_id = r.id 
                    JOIN room_categories rc ON r.category_id = rc.id
                    JOIN users u ON b.created_by = u.id
                    ORDER BY b.created_at DESC");
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available rooms for booking creation
$stmt = $db->query("SELECT r.*, rc.name as category_name, rc.base_price 
                    FROM rooms r 
                    JOIN room_categories rc ON r.category_id = rc.id 
                    WHERE r.status = 'available' 
                    ORDER BY r.room_number");
$available_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all guests for booking creation
$stmt = $db->query("SELECT * FROM guests ORDER BY first_name, last_name");
$guests = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Booking Management</h4>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBookingModal">
        <i class="bi bi-calendar-plus me-2"></i>Create Booking
    </button>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card admin-card">
    <div class="card-header">
        <h6 class="card-title mb-0">All Bookings</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-modern">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Guest</th>
                        <th>Room</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Nights</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): 
                        $nights = (strtotime($booking['check_out_date']) - strtotime($booking['check_in_date'])) / (60 * 60 * 24);
                    ?>
                    <tr>
                        <td>#<?php echo $booking['id']; ?></td>
                        <td>
                            <div><?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?></div>
                            <small class="text-muted"><?php echo $booking['email']; ?></small>
                        </td>
                        <td>
                            <div><?php echo $booking['room_number']; ?></div>
                            <small class="text-muted"><?php echo $booking['category_name']; ?></small>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></td>
                        <td><?php echo $nights; ?></td>
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
                        <td><?php echo $booking['created_by_name']; ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" 
                                        data-bs-toggle="modal" data-bs-target="#updateStatusModal" 
                                        data-booking-id="<?php echo $booking['id']; ?>"
                                        data-current-status="<?php echo $booking['status']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_booking">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger" 
                                            onclick="return confirm('Are you sure you want to delete this booking?')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Booking Modal -->
<div class="modal fade" id="createBookingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_booking">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="guest_id" class="form-label">Guest</label>
                            <select class="form-select" id="guest_id" name="guest_id" required>
                                <option value="">Select Guest</option>
                                <?php foreach ($guests as $guest): ?>
                                <option value="<?php echo $guest['id']; ?>">
                                    <?php echo $guest['first_name'] . ' ' . $guest['last_name']; ?> 
                                    (<?php echo $guest['email'] ?: $guest['phone']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">
                                <a href="#" data-bs-toggle="modal" data-bs-target="#addGuestModal">
                                    + Add new guest
                                </a>
                            </small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="room_id" class="form-label">Room</label>
                            <select class="form-select" id="room_id" name="room_id" required>
                                <option value="">Select Room</option>
                                <?php foreach ($available_rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>" data-price="<?php echo $room['base_price']; ?>">
                                    <?php echo $room['room_number']; ?> - <?php echo $room['category_name']; ?> 
                                    (₱<?php echo number_format($room['base_price'], 2); ?>/night)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="check_in_date" class="form-label">Check-in Date</label>
                            <input type="date" class="form-control" id="check_in_date" name="check_in_date" required
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="check_out_date" class="form-label">Check-out Date</label>
                            <input type="date" class="form-control" id="check_out_date" name="check_out_date" required
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="adults" class="form-label">Adults</label>
                            <input type="number" class="form-control" id="adults" name="adults" min="1" max="10" value="1" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="children" class="form-label">Children</label>
                            <input type="number" class="form-control" id="children" name="children" min="0" max="10" value="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="special_requests" class="form-label">Special Requests</label>
                        <textarea class="form-control" id="special_requests" name="special_requests" rows="3" 
                                  placeholder="Any special requests or notes..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Estimated Total: </strong>
                        <span id="estimated_total">₱0.00</span>
                        (<span id="nights_count">0</span> nights)
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Booking Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_booking_status">
                    <input type="hidden" name="booking_id" id="update_booking_id">
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="confirmed">Confirmed</option>
                            <option value="checked_in">Checked In</option>
                            <option value="checked_out">Checked Out</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Calculate estimated total
    function calculateTotal() {
        const roomSelect = document.getElementById('room_id');
        const checkInDate = new Date(document.getElementById('check_in_date').value);
        const checkOutDate = new Date(document.getElementById('check_out_date').value);
        
        if (roomSelect.value && checkInDate && checkOutDate && checkOutDate > checkInDate) {
            const price = parseFloat(roomSelect.options[roomSelect.selectedIndex].getAttribute('data-price'));
            const nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
            const total = price * nights;
            
            document.getElementById('estimated_total').textContent = '₱' + total.toFixed(2);
            document.getElementById('nights_count').textContent = nights;
        } else {
            document.getElementById('estimated_total').textContent = '₱0.00';
            document.getElementById('nights_count').textContent = '0';
        }
    }
    
    // Add event listeners
    document.getElementById('room_id').addEventListener('change', calculateTotal);
    document.getElementById('check_in_date').addEventListener('change', calculateTotal);
    document.getElementById('check_out_date').addEventListener('change', calculateTotal);
    
    // Handle update status modal
    document.addEventListener('DOMContentLoaded', function() {
        const updateStatusModal = document.getElementById('updateStatusModal');
        updateStatusModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('update_booking_id').value = button.getAttribute('data-booking-id');
            document.getElementById('status').value = button.getAttribute('data-current-status');
        });
    });
</script>

<?php include '../includes/admin_footer.php'; ?>
