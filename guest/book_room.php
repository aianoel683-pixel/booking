<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['registered_guest_id'])) {
    header('Location: ../landing.php');
    exit();
}

$db = db();
$gid = (int)$_SESSION['registered_guest_id'];
$message = '';
$error = '';

// Fetch Room Categories
try {
    $room_categories = $db->query('SELECT * FROM room_categories')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_category_id = (int)$_POST['room_category_id'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $adults = (int)$_POST['adults'];
    $children = (int)$_POST['children'];

    if (strtotime($check_in) >= strtotime($check_out)) {
        $error = 'Check-out date must be after check-in date.';
    } else {
        try {
            // Find an available room in the selected category
            $stmt = $db->prepare("
                SELECT id FROM rooms 
                WHERE category_id = :cat_id 
                AND status = 'available' 
                AND id NOT IN (
                    SELECT room_id FROM bookings 
                    WHERE status IN ('confirmed', 'checked_in') 
                    AND (
                        (check_in_date <= :check_in AND check_out_date > :check_in) OR
                        (check_in_date < :check_out AND check_out_date >= :check_out) OR
                        (check_in_date >= :check_in AND check_out_date <= :check_out)
                    )
                )
                LIMIT 1
            ");
            $stmt->execute([
                ':cat_id' => $room_category_id,
                ':check_in' => $check_in,
                ':check_out' => $check_out
            ]);
            $available_room = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($available_room) {
                // Calculate total amount
                $stmt = $db->prepare("SELECT base_price FROM room_categories WHERE id = ?");
                $stmt->execute([$room_category_id]);
                $price = $stmt->fetchColumn();
                
                $days = (strtotime($check_out) - strtotime($check_in)) / (60 * 60 * 24);
                $total_amount = $price * $days;

                // Create Booking
                $stmt = $db->prepare("
                    INSERT INTO bookings (guest_id, room_id, check_in_date, check_out_date, adults, children, total_amount, status, created_by) 
                    VALUES (:gid, :rid, :in_date, :out_date, :adults, :children, :total, 'confirmed', :creator)
                ");
                
                // Note: created_by refers to a user ID. Since guests are self-booking, we might need a system user or nullable field.
                // For now, we'll assume there's a system user (ID 1 usually admin) or adjust schema.
                // Let's check schema. Bookings created_by references users(id).
                // We should probably use a default admin ID or allow NULL for self-booking.
                // Given the schema constraint, let's use ID 1 (Admin) as a fallback for now.
                $system_user_id = 1; 

                $stmt->execute([
                    ':gid' => $gid,
                    ':rid' => $available_room['id'],
                    ':in_date' => $check_in,
                    ':out_date' => $check_out,
                    ':adults' => $adults,
                    ':children' => $children,
                    ':total' => $total_amount,
                    ':creator' => $system_user_id
                ]);

                // Update room status
                $db->prepare("UPDATE rooms SET status = 'reserved' WHERE id = ?")->execute([$available_room['id']]);

                $message = 'Booking confirmed successfully!';
            } else {
                $error = 'No available rooms in this category for the selected dates.';
            }
        } catch (Exception $e) {
            $error = 'Booking failed: ' . $e->getMessage();
        }
    }
}

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
    <title>Book a Room - <?php echo htmlspecialchars($hotel['hotel_name'] ?? 'Hotel'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a1a1a;
            --accent-color: #d4af37;
            --accent-hover: #b39020;
            --bg-light: #f8f9fa;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--primary-color);
        }
        .brand-font { font-family: 'Playfair Display', serif; }
        .btn-gold {
            background-color: var(--accent-color);
            color: white;
            border: none;
        }
        .btn-gold:hover {
            background-color: var(--accent-hover);
            color: white;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
        }
    </style>
</head>
<body>
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
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-5">
                        <h3 class="fw-bold brand-font mb-4 text-center">Book Your Stay</h3>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Room Category</label>
                                <select name="room_category_id" class="form-select" required>
                                    <option value="">Select a Room Type</option>
                                    <?php foreach ($room_categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_GET['cat_id']) && $_GET['cat_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?> (₱<?php echo number_format($cat['base_price']); ?>/night)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">Check-in Date</label>
                                    <input type="date" name="check_in" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Check-out Date</label>
                                    <input type="date" name="check_out" class="form-control" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                </div>
                            </div>
                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <label class="form-label">Adults</label>
                                    <input type="number" name="adults" class="form-control" value="1" min="1" max="5" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Children</label>
                                    <input type="number" name="children" class="form-control" value="0" min="0" max="5">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-gold w-100 py-2">Confirm Booking</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
