<?php
session_start();
require_once '../config/database.php';

// Check if user is admin/manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = "Room Management";
$db = db();

function upload_room_image($field)
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return null;
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $tmp = $_FILES[$field]['tmp_name'] ?? '';
    $size = (int)($_FILES[$field]['size'] ?? 0);
    $type = $_FILES[$field]['type'] ?? '';
    if (!$tmp || $size <= 0) return null;
    if ($size > 5 * 1024 * 1024) return null;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$type])) return null;
    $ext = $allowed[$type];
    $baseDir = __DIR__ . '/../uploads/rooms';
    if (!is_dir($baseDir)) { @mkdir(__DIR__ . '/../uploads', 0755, true); @mkdir($baseDir, 0755, true); }
    $name = 'room_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destFs = $baseDir . '/' . $name;
    if (@move_uploaded_file($tmp, $destFs)) {
        return 'uploads/rooms/' . $name;
    }
    return null;
}
// Ensure optional room price column exists
try {
    $chk = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rooms' AND COLUMN_NAME = 'price_per_night'");
    $chk->execute();
    $exists = (int)($chk->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    if ($exists === 0) {
        $db->exec("ALTER TABLE rooms ADD COLUMN price_per_night DECIMAL(10,2) NULL DEFAULT NULL");
    }
} catch (Exception $e) {}

// Handle room actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add_room') {
        $room_number = trim($_POST['room_number']);
        $category_id = $_POST['category_id'];
        $floor = $_POST['floor'];
        $features = trim($_POST['features']);
        $price_per_night = isset($_POST['price_per_night']) && $_POST['price_per_night'] !== '' ? $_POST['price_per_night'] : null;
        $photo_url = upload_room_image('photo');
        
        try {
            $stmt = $db->prepare("INSERT INTO rooms (room_number, category_id, floor, features, photo_url, price_per_night) 
                                  VALUES (:room_number, :category_id, :floor, :features, :photo_url, :price_per_night)");
            $stmt->bindParam(':room_number', $room_number);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':floor', $floor);
            $stmt->bindParam(':features', $features);
            $stmt->bindParam(':photo_url', $photo_url);
            $stmt->bindParam(':price_per_night', $price_per_night);
            $stmt->execute();
            
            $success = "Room added successfully!";
        } catch (PDOException $e) {
            $error = "Error adding room: " . $e->getMessage();
        }
    }
    elseif ($action == 'update_room') {
        $room_id = $_POST['room_id'];
        $room_number = trim($_POST['room_number']);
        $category_id = $_POST['category_id'];
        $floor = $_POST['floor'];
        $features = trim($_POST['features']);
        $status = $_POST['status'];
        $price_per_night = isset($_POST['price_per_night']) && $_POST['price_per_night'] !== '' ? $_POST['price_per_night'] : null;
        
        try {
            $stmt = $db->prepare("UPDATE rooms SET room_number = :room_number, category_id = :category_id, 
                                  floor = :floor, features = :features, status = :status, price_per_night = :price_per_night WHERE id = :id");
            $stmt->bindParam(':id', $room_id);
            $stmt->bindParam(':room_number', $room_number);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':floor', $floor);
            $stmt->bindParam(':features', $features);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':price_per_night', $price_per_night);
            $stmt->execute();
            
            $success = "Room updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating room: " . $e->getMessage();
        }
    }
    elseif ($action == 'delete_room') {
        $room_id = $_POST['room_id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM rooms WHERE id = :id");
            $stmt->bindParam(':id', $room_id);
            $stmt->execute();
            
            $success = "Room deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting room: " . $e->getMessage();
        }
    }
    elseif ($action == 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $base_price = $_POST['base_price'] ?? '';
        $max_occupancy = (int)($_POST['max_occupancy'] ?? 1);
        $amenities = trim($_POST['amenities'] ?? '');
        if ($name && $base_price !== '') {
            try {
                $stmt = $db->prepare("INSERT INTO room_categories (name, description, base_price, max_occupancy, amenities) VALUES (:name, :description, :base_price, :max_occupancy, :amenities)");
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':base_price', $base_price);
                $stmt->bindParam(':max_occupancy', $max_occupancy);
                $stmt->bindParam(':amenities', $amenities);
                $stmt->execute();
                $success = "Category added successfully!";
            } catch (PDOException $e) {
                $error = "Error adding category: " . $e->getMessage();
            }
        } else {
            $error = "Please fill required fields for category.";
        }
    }
    elseif ($action == 'update_category') {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $base_price = $_POST['base_price'] ?? '';
        $max_occupancy = (int)($_POST['max_occupancy'] ?? 1);
        $amenities = trim($_POST['amenities'] ?? '');
        if ($category_id && $name && $base_price !== '') {
            try {
                $stmt = $db->prepare("UPDATE room_categories SET name=:name, description=:description, base_price=:base_price, max_occupancy=:max_occupancy, amenities=:amenities WHERE id=:id");
                $stmt->bindParam(':id', $category_id);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':base_price', $base_price);
                $stmt->bindParam(':max_occupancy', $max_occupancy);
                $stmt->bindParam(':amenities', $amenities);
                $stmt->execute();
                $success = "Category updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating category: " . $e->getMessage();
            }
        } else {
            $error = "Please fill required fields for category.";
        }
    }
    elseif ($action == 'delete_category') {
        $category_id = (int)($_POST['category_id'] ?? 0);
        if ($category_id) {
            try {
                $c = $db->prepare("SELECT COUNT(*) FROM rooms WHERE category_id = :id");
                $c->bindParam(':id', $category_id);
                $c->execute();
                $in_use = (int)$c->fetchColumn();
                if ($in_use > 0) {
                    $error = "Cannot delete category: it is assigned to existing rooms.";
                } else {
                    $stmt = $db->prepare("DELETE FROM room_categories WHERE id = :id");
                    $stmt->bindParam(':id', $category_id);
                    $stmt->execute();
                    $success = "Category deleted successfully!";
                }
            } catch (PDOException $e) {
                $error = "Error deleting category: " . $e->getMessage();
            }
        }
    }
}

// Get all rooms with category information
$stmt = $db->query("SELECT r.*, rc.name as category_name, COALESCE(r.price_per_night, rc.base_price) AS effective_price 
                    FROM rooms r 
                    JOIN room_categories rc ON r.category_id = rc.id 
                    ORDER BY r.floor, r.room_number");
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get room categories for dropdown
$stmt = $db->query("SELECT * FROM room_categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Room Management</h4>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
        <i class="bi bi-plus-circle me-2"></i>Add New Room
    </button>
</div>




<div class="card admin-card">
    <div class="card-header">
        <h6 class="card-title mb-0">All Rooms</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-modern">
                <thead>
                    <tr>
                        <th>Room #</th>
                        <th>Category</th>
                        <th>Floor</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Housekeeping</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room): ?>
                    <tr class="room-status-<?php echo $room['status']; ?>">
                        <td><strong><?php echo $room['room_number']; ?></strong></td>
                        <td><?php echo $room['category_name']; ?></td>
                        <td><?php echo $room['floor']; ?></td>
                        <td>₱<?php echo number_format($room['effective_price'], 2); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                switch($room['status']) {
                                    case 'available': echo 'success'; break;
                                    case 'occupied': echo 'danger'; break;
                                    case 'reserved': echo 'warning'; break;
                                    case 'maintenance': echo 'secondary'; break;
                                    case 'cleaning': echo 'info'; break;
                                    default: echo 'secondary';
                                }
                            ?>">
                                <?php echo ucfirst($room['status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                switch($room['housekeeping_status']) {
                                    case 'clean': echo 'success'; break;
                                    case 'dirty': echo 'danger'; break;
                                    case 'needs_maintenance': echo 'warning'; break;
                                    default: echo 'secondary';
                                }
                            ?>">
                                <?php echo ucfirst($room['housekeeping_status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" 
                                        data-bs-toggle="modal" data-bs-target="#editRoomModal" 
                                        data-room-id="<?php echo $room['id']; ?>"
                                        data-room-number="<?php echo $room['room_number']; ?>"
                                        data-category-id="<?php echo $room['category_id']; ?>"
                                        data-floor="<?php echo $room['floor']; ?>"
                                        data-features="<?php echo htmlspecialchars($room['features']); ?>"
                                        data-price-per-night="<?php echo htmlspecialchars($room['price_per_night']); ?>"
                                        data-status="<?php echo $room['status']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_room">
                                    <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger" 
                                            onclick="return confirm('Are you sure you want to delete this room?')">
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

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_room">
                    
                    <div class="mb-3">
                        <label for="room_number" class="form-label">Room Number</label>
                        <input type="text" class="form-control" id="room_number" name="room_number" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Room Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo $category['name']; ?> - ₱<?php echo number_format($category['base_price'], 2); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="floor" class="form-label">Floor</label>
                        <input type="number" class="form-control" id="floor" name="floor" min="1" max="50" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="price_per_night" class="form-label">Custom Price per Night (optional)</label>
                        <input type="number" step="0.01" class="form-control" id="price_per_night" name="price_per_night" placeholder="Leave blank to use category price">
                    </div>
                    
                    <div class="mb-3">
                        <label for="photo" class="form-label">Photo</label>
                        <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                        <small class="text-muted">JPEG, PNG, or WEBP up to 5MB</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="features" class="form-label">Features</label>
                        <textarea class="form-control" id="features" name="features" rows="3" 
                                  placeholder="e.g., Ocean view, Balcony, Mini-bar"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Room Modal -->
<div class="modal fade" id="editRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_room">
                    <input type="hidden" name="room_id" id="edit_room_id">
                    
                    <div class="mb-3">
                        <label for="edit_room_number" class="form-label">Room Number</label>
                        <input type="text" class="form-control" id="edit_room_number" name="room_number" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_category_id" class="form-label">Room Category</label>
                        <select class="form-select" id="edit_category_id" name="category_id" required>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo $category['name']; ?> - ₱<?php echo number_format($category['base_price'], 2); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_floor" class="form-label">Floor</label>
                        <input type="number" class="form-control" id="edit_floor" name="floor" min="1" max="50" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_price_per_night" class="form-label">Custom Price per Night (optional)</label>
                        <input type="number" step="0.01" class="form-control" id="edit_price_per_night" name="price_per_night">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_features" class="form-label">Features</label>
                        <textarea class="form-control" id="edit_features" name="features" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="reserved">Reserved</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="cleaning">Cleaning</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Handle edit modal data
    document.addEventListener('DOMContentLoaded', function() {
        const editRoomModal = document.getElementById('editRoomModal');
        editRoomModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            document.getElementById('edit_room_id').value = button.getAttribute('data-room-id');
            document.getElementById('edit_room_number').value = button.getAttribute('data-room-number');
            document.getElementById('edit_category_id').value = button.getAttribute('data-category-id');
            document.getElementById('edit_floor').value = button.getAttribute('data-floor');
            var ep = button.getAttribute('data-price-per-night');
            if (document.getElementById('edit_price_per_night')) {
                document.getElementById('edit_price_per_night').value = ep ? ep : '';
            }
            document.getElementById('edit_features').value = button.getAttribute('data-features');
            document.getElementById('edit_status').value = button.getAttribute('data-status');
        });
    });
</script>

<?php include '../includes/admin_footer.php'; ?>
