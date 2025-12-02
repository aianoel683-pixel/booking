<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'housekeeping') { header('Location: ../auth/login.php'); exit(); }
$page_title = 'Room Status';
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $room_id = (int)($_POST['room_id'] ?? 0);
    if ($room_id > 0) {
        if ($_POST['action'] === 'set_status') {
            $hk = $_POST['housekeeping_status'] ?? 'clean';
            $st = $_POST['room_status'] ?? null;
            $params = [':hk' => $hk, ':id' => $room_id];
            $sql = "UPDATE rooms SET housekeeping_status=:hk";
            if ($st) { $sql .= ", status=:st"; $params[':st'] = $st; }
            $sql .= " WHERE id=:id";
            $db->prepare($sql)->execute($params);
            $success = 'Room updated.';
        }
    }
}

$rooms = $db->query("SELECT r.*, rc.name AS category_name FROM rooms r JOIN room_categories rc ON r.category_id=rc.id ORDER BY r.floor, r.room_number")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <?php echo $success; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">Rooms</div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Room</th>
            <th>Category</th>
            <th>Status</th>
            <th>Housekeeping</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rooms as $r): ?>
          <tr>
            <td><strong><?php echo $r['room_number']; ?></strong></td>
            <td><?php echo $r['category_name']; ?></td>
            <td><span class="badge bg-<?php echo ($r['status']==='available'?'success':($r['status']==='occupied'?'danger':($r['status']==='reserved'?'warning':($r['status']==='maintenance'?'secondary':'info')))); ?>"><?php echo ucfirst($r['status']); ?></span></td>
            <td><span class="badge bg-<?php echo ($r['housekeeping_status']==='clean'?'success':($r['housekeeping_status']==='dirty'?'danger':'warning')); ?>"><?php echo ucfirst($r['housekeeping_status']); ?></span></td>
            <td>
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="room_id" value="<?php echo $r['id']; ?>">
                <input type="hidden" name="housekeeping_status" value="clean">
                <button class="btn btn-sm btn-success">Mark Clean</button>
              </form>
              <form method="POST" class="d-inline ms-1">
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="room_id" value="<?php echo $r['id']; ?>">
                <input type="hidden" name="housekeeping_status" value="dirty">
                <button class="btn btn-sm btn-outline-danger">Mark Dirty</button>
              </form>
              <form method="POST" class="d-inline ms-1">
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="room_id" value="<?php echo $r['id']; ?>">
                <input type="hidden" name="housekeeping_status" value="needs_maintenance">
                <button class="btn btn-sm btn-outline-warning">Needs Maintenance</button>
              </form>
              <form method="POST" class="d-inline ms-1">
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="room_id" value="<?php echo $r['id']; ?>">
                <input type="hidden" name="housekeeping_status" value="dirty">
                <input type="hidden" name="room_status" value="cleaning">
                <button class="btn btn-sm btn-primary">Start Cleaning</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

