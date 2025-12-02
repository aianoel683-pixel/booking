<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'housekeeping') { header('Location: ../auth/login.php'); exit(); }
$page_title = 'Housekeeping Dashboard';
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_task_status') {
    $task_id = (int)($_POST['task_id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    if ($task_id > 0 && in_array($status, ['pending','in_progress','completed'])) {
        if ($status === 'completed') {
            $stmt = $db->prepare("UPDATE housekeeping_tasks SET status='completed', completed_at=NOW() WHERE id=:id");
            $stmt->execute([':id' => $task_id]);
        } else {
            $stmt = $db->prepare("UPDATE housekeeping_tasks SET status=:st WHERE id=:id");
            $stmt->execute([':st' => $status, ':id' => $task_id]);
        }
        $success = 'Task updated.';
    }
}

$clean_rooms = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE housekeeping_status='clean'")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
$dirty_rooms = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE housekeeping_status='dirty'")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
$needs_maint = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE housekeeping_status='needs_maintenance'")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
$under_cleaning = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE status='cleaning'")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;

$tasks_stmt = $db->prepare("SELECT t.*, r.room_number, u.first_name, u.last_name FROM housekeeping_tasks t JOIN rooms r ON t.room_id=r.id JOIN users u ON t.assigned_to=u.id WHERE t.status IN ('pending','in_progress') ORDER BY t.priority DESC, t.created_at ASC LIMIT 20");
$tasks_stmt->execute();
$tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <?php echo $success; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
  <div class="col-md-3 mb-4">
    <div class="card dashboard-card">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted">Clean Rooms</div>
          <div class="h3 mb-0"><?php echo (int)$clean_rooms; ?></div>
        </div>
        <i class="bi bi-check2-square fs-1 text-success"></i>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-4">
    <div class="card dashboard-card">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted">Dirty Rooms</div>
          <div class="h3 mb-0"><?php echo (int)$dirty_rooms; ?></div>
        </div>
        <i class="bi bi-exclamation-triangle fs-1 text-warning"></i>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-4">
    <div class="card dashboard-card">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted">Needs Maintenance</div>
          <div class="h3 mb-0"><?php echo (int)$needs_maint; ?></div>
        </div>
        <i class="bi bi-tools fs-1 text-danger"></i>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-4">
    <div class="card dashboard-card">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted">Under Cleaning</div>
          <div class="h3 mb-0"><?php echo (int)$under_cleaning; ?></div>
        </div>
        <i class="bi bi-brush fs-1 text-primary"></i>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">Active Tasks</div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Task</th>
            <th>Room</th>
            <th>Assigned To</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tasks as $t): ?>
          <tr>
            <td><?php echo ucfirst($t['task_type']); ?></td>
            <td><?php echo $t['room_number']; ?></td>
            <td><?php echo $t['first_name'].' '.$t['last_name']; ?></td>
            <td><span class="badge bg-<?php echo ($t['priority']==='high'?'danger':($t['priority']==='medium'?'warning':'secondary')); ?>"><?php echo ucfirst($t['priority']); ?></span></td>
            <td><span class="badge bg-<?php echo ($t['status']==='pending'?'secondary':($t['status']==='in_progress'?'primary':'success')); ?>"><?php echo ucfirst($t['status']); ?></span></td>
            <td>
              <form method="POST" class="d-inline me-2">
                <input type="hidden" name="action" value="update_task_status">
                <input type="hidden" name="task_id" value="<?php echo $t['id']; ?>">
                <input type="hidden" name="status" value="in_progress">
                <button type="submit" class="btn btn-sm btn-outline-primary">In Progress</button>
              </form>
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="update_task_status">
                <input type="hidden" name="task_id" value="<?php echo $t['id']; ?>">
                <input type="hidden" name="status" value="completed">
                <button type="submit" class="btn btn-sm btn-success">Complete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($tasks)): ?>
          <tr><td colspan="6" class="text-muted">No active tasks.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

