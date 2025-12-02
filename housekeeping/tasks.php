<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'housekeeping') { header('Location: ../auth/login.php'); exit(); }
$page_title = 'Tasks';
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_task') {
        $id = (int)($_POST['task_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        if ($id > 0 && in_array($status, ['pending','in_progress','completed'])) {
            if ($status === 'completed') {
                $db->prepare("UPDATE housekeeping_tasks SET status='completed', completed_at=NOW() WHERE id=:id")->execute([':id' => $id]);
            } else {
                $db->prepare("UPDATE housekeeping_tasks SET status=:st WHERE id=:id")->execute([':st' => $status, ':id' => $id]);
            }
            $success = 'Task updated.';
        }
    }
}

$tasks = $db->query("SELECT t.*, r.room_number FROM housekeeping_tasks t JOIN rooms r ON t.room_id=r.id ORDER BY t.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <?php echo $success; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">All Tasks</div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Task</th>
            <th>Room</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tasks as $t): ?>
          <tr>
            <td><?php echo ucfirst($t['task_type']); ?></td>
            <td><?php echo $t['room_number']; ?></td>
            <td><span class="badge bg-<?php echo ($t['priority']==='high'?'danger':($t['priority']==='medium'?'warning':'secondary')); ?>"><?php echo ucfirst($t['priority']); ?></span></td>
            <td><span class="badge bg-<?php echo ($t['status']==='pending'?'secondary':($t['status']==='in_progress'?'primary':'success')); ?>"><?php echo ucfirst($t['status']); ?></span></td>
            <td><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
            <td>
              <form method="POST" class="d-inline me-2">
                <input type="hidden" name="action" value="update_task">
                <input type="hidden" name="task_id" value="<?php echo $t['id']; ?>">
                <input type="hidden" name="status" value="in_progress">
                <button type="submit" class="btn btn-sm btn-outline-primary">In Progress</button>
              </form>
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="update_task">
                <input type="hidden" name="task_id" value="<?php echo $t['id']; ?>">
                <input type="hidden" name="status" value="completed">
                <button type="submit" class="btn btn-sm btn-success">Complete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($tasks)): ?>
          <tr><td colspan="6" class="text-muted">No tasks found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

