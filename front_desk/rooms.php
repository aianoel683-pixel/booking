<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'front_desk') { header('Location: ../auth/login.php'); exit(); }
$page_title = 'Rooms';
$db = db();

$success = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_hk_task') {
    $room_id = (int)($_POST['room_id'] ?? 0);
    $task_type = $_POST['task_type'] ?? 'cleaning';
    $priority = $_POST['priority'] ?? 'medium';
    $assigned_to = (int)($_POST['assigned_to'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    if ($room_id > 0 && in_array($task_type, ['cleaning','maintenance','inspection'])) {
        try {
            if ($assigned_to <= 0) {
                $row = $db->query("SELECT id FROM users WHERE role='housekeeping' AND status='active' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $assigned_to = (int)($row['id'] ?? 0);
            }
            if ($assigned_to <= 0) { throw new Exception('No housekeeping user available'); }
            $stmt = $db->prepare("INSERT INTO housekeeping_tasks (room_id, assigned_to, task_type, status, priority, description) VALUES (:room_id, :assigned_to, :task_type, 'pending', :priority, :description)");
            $stmt->execute([':room_id'=>$room_id, ':assigned_to'=>$assigned_to, ':task_type'=>$task_type, ':priority'=>$priority, ':description'=>$description]);
            $success = 'Housekeeping task created.';
        } catch (Exception $e) {
            $error = 'Unable to create task.';
        }
    } else {
        $error = 'Invalid task details.';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_room') {
    $room_number = trim($_POST['room_number'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $floor = (int)($_POST['floor'] ?? 0);
    $features = trim($_POST['features'] ?? '');
    if ($room_number && $category_id && $floor) {
        try {
            $stmt = $db->prepare("INSERT INTO rooms (room_number, category_id, floor, features) VALUES (:room_number, :category_id, :floor, :features)");
            $stmt->execute([':room_number' => $room_number, ':category_id' => $category_id, ':floor' => $floor, ':features' => $features]);
            $success = 'Room added.';
        } catch (Exception $e) {
            $error = 'Unable to add room.';
        }
    } else {
        $error = 'Please fill required fields.';
    }
}

$categories = $db->query("SELECT * FROM room_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$rooms = $db->query("SELECT r.*, rc.name AS category_name, rc.base_price FROM rooms r JOIN room_categories rc ON r.category_id = rc.id ORDER BY r.floor, r.room_number")->fetchAll(PDO::FETCH_ASSOC);
$housekeepers = $db->query("SELECT id, first_name, last_name FROM users WHERE role='housekeeping' AND status='active' ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

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

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4>Rooms</h4>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
    <i class="bi bi-plus-circle me-2"></i>Add Room
  </button>
</div>

<div class="card admin-card">
  <div class="card-header">Rooms</div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover table-modern">
        <thead>
          <tr>
            <th>Room</th>
            <th>Category</th>
            <th>Floor</th>
            <th>Status</th>
            <th>Housekeeping</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rooms as $r): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($r['room_number']); ?></strong></td>
            <td><?php echo htmlspecialchars($r['category_name']); ?></td>
            <td><?php echo (int)($r['floor'] ?? 0); ?></td>
            <td>
              <span class="badge bg-<?php echo ($r['status']==='available'?'success':($r['status']==='occupied'?'danger':($r['status']==='reserved'?'warning':($r['status']==='maintenance'?'secondary':'info')))); ?>">
                <?php echo ucfirst($r['status']); ?>
              </span>
            </td>
            <td>
              <span class="badge bg-<?php echo ($r['housekeeping_status']==='clean'?'success':($r['housekeeping_status']==='dirty'?'danger':'warning')); ?>">
                <?php echo ucfirst(str_replace('_',' ', $r['housekeeping_status'])); ?>
              </span>
            </td>
            <td>
              <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createHkTaskModal"
                      data-room-id="<?php echo (int)$r['id']; ?>" data-room-number="<?php echo htmlspecialchars($r['room_number']); ?>">
                Create Task
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($rooms)): ?>
          <tr><td colspan="5" class="text-muted">No rooms found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer">
    <small class="text-muted">Front Desk view • read-only</small>
  </div>
</div>

<div class="modal fade" id="addRoomModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Room</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="add_room">
          <div class="mb-3">
            <label class="form-label">Room Number</label>
            <input type="text" class="form-control" name="room_number" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Room Category</label>
            <select class="form-select" name="category_id" required>
              <option value="">Select Category</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?> - ₱<?php echo number_format($c['base_price'], 2); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Floor</label>
            <input type="number" class="form-control" name="floor" min="1" max="50" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Features</label>
            <textarea class="form-control" name="features" rows="2"></textarea>
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

<div class="modal fade" id="createHkTaskModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create Housekeeping Task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="create_hk_task">
          <input type="hidden" id="hk_room_id" name="room_id">
          <div class="mb-3">
            <label class="form-label">Room</label>
            <input type="text" class="form-control" id="hk_room_number" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Task Type</label>
            <select class="form-select" name="task_type" required>
              <option value="inspection">Inspection</option>
              <option value="cleaning">Cleaning</option>
              <option value="maintenance">Maintenance</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Priority</label>
            <select class="form-select" name="priority" required>
              <option value="low">Low</option>
              <option value="medium" selected>Medium</option>
              <option value="high">High</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Assign To</label>
            <select class="form-select" name="assigned_to">
              <option value="">Select housekeeper</option>
              <?php foreach ($housekeepers as $hk): ?>
              <option value="<?php echo (int)$hk['id']; ?>"><?php echo htmlspecialchars(($hk['first_name'] ?? '').' '.($hk['last_name'] ?? '')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="3" placeholder="Task details..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Task</button>
        </div>
      </form>
    </div>
  </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var m = document.getElementById('createHkTaskModal');
  if (m) {
    m.addEventListener('show.bs.modal', function(ev){
      var btn = ev.relatedTarget;
      document.getElementById('hk_room_id').value = btn.getAttribute('data-room-id');
      document.getElementById('hk_room_number').value = btn.getAttribute('data-room-number');
    });
  }
});
</script>

<?php include '../includes/footer.php'; ?>
