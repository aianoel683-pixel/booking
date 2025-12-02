<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'housekeeping') { header('Location: ../auth/login.php'); exit(); }
$page_title = 'Maintenance';
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'report_issue') {
        $room_id = (int)($_POST['room_id'] ?? 0);
        $issue_type = trim($_POST['issue_type'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        if ($room_id && $issue_type && $description) {
            $stmt = $db->prepare("INSERT INTO maintenance_requests (room_id, reported_by, issue_type, description, status, priority) VALUES (:room_id, :reported_by, :issue_type, :description, 'reported', :priority)");
            $stmt->execute([':room_id'=>$room_id, ':reported_by'=>$_SESSION['user_id'], ':issue_type'=>$issue_type, ':description'=>$description, ':priority'=>$priority]);
            $success = 'Issue reported.';
        }
    } elseif ($_POST['action'] === 'update_status') {
        $id = (int)($_POST['request_id'] ?? 0);
        $status = $_POST['status'] ?? 'reported';
        if ($id > 0 && in_array($status, ['reported','in_progress','resolved'])) {
            if ($status === 'resolved') {
                $db->prepare("UPDATE maintenance_requests SET status='resolved', resolved_at=NOW() WHERE id=:id")->execute([':id' => $id]);
            } else {
                $db->prepare("UPDATE maintenance_requests SET status=:st WHERE id=:id")->execute([':st' => $status, ':id' => $id]);
            }
            $success = 'Request updated.';
        }
    }
}

$rooms = $db->query("SELECT id, room_number FROM rooms ORDER BY room_number")->fetchAll(PDO::FETCH_ASSOC);
$requests = $db->query("SELECT m.*, r.room_number FROM maintenance_requests m JOIN rooms r ON m.room_id=r.id ORDER BY m.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <?php echo $success; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4>Maintenance Requests</h4>
</div>

<div class="card mb-4">
  <div class="card-header">Report Issue</div>
  <div class="card-body">
    <form method="POST" class="row g-3">
      <input type="hidden" name="action" value="report_issue">
      <div class="col-md-3">
        <label class="form-label">Room</label>
        <select name="room_id" class="form-select" required>
          <option value="">Select Room</option>
          <?php foreach ($rooms as $r): ?>
          <option value="<?php echo $r['id']; ?>"><?php echo $r['room_number']; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Issue Type</label>
        <input type="text" name="issue_type" class="form-control" placeholder="e.g., AC not cooling" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Priority</label>
        <select name="priority" class="form-select">
          <option value="low">Low</option>
          <option value="medium" selected>Medium</option>
          <option value="high">High</option>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3" required></textarea>
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary">Submit</button>
      </div>
    </form>
  </div>
  </div>

<div class="card">
  <div class="card-header">All Requests</div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Room</th>
            <th>Issue</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $m): ?>
          <tr>
            <td><?php echo $m['room_number']; ?></td>
            <td><?php echo $m['issue_type']; ?></td>
            <td><span class="badge bg-<?php echo ($m['priority']==='high'?'danger':($m['priority']==='medium'?'warning':'secondary')); ?>"><?php echo ucfirst($m['priority']); ?></span></td>
            <td><span class="badge bg-<?php echo ($m['status']==='reported'?'secondary':($m['status']==='in_progress'?'primary':'success')); ?>"><?php echo ucfirst($m['status']); ?></span></td>
            <td><?php echo date('M d, Y', strtotime($m['created_at'])); ?></td>
            <td>
              <form method="POST" class="d-inline me-2">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="request_id" value="<?php echo $m['id']; ?>">
                <input type="hidden" name="status" value="in_progress">
                <button class="btn btn-sm btn-outline-primary">In Progress</button>
              </form>
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="request_id" value="<?php echo $m['id']; ?>">
                <input type="hidden" name="status" value="resolved">
                <button class="btn btn-sm btn-success">Resolve</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($requests)): ?>
          <tr><td colspan="6" class="text-muted">No maintenance requests.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

